<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\Transcript;
use App\Models\TranscriptFile;
use App\Models\TranscriptSegment;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class ImportService
{
    private const MAX_FILE_BYTES = 26214400; // 25 MB

    /** @var array<string, string> */
    private const ALLOWED_MIME = [
        'txt' => 'text/plain',
        'srt' => 'application/x-subrip',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pdf' => 'application/pdf',
    ];

    public function __construct(private readonly WorkspaceService $workspaces)
    {
    }

    /**
     * @param list<UploadedFile> $files
     * @return array<string, mixed>
     */
    public function importBatch(
        string $workspaceId,
        string $userId,
        array $files,
        ?string $projectId = null
    ): array {
        $this->workspaces->requireMembership($workspaceId, $userId, ['owner', 'admin', 'editor']);

        if ($projectId !== null && $projectId !== '') {
            $projectExists = Project::query()
                ->where('id', $projectId)
                ->where('workspace_id', $workspaceId)
                ->exists();
            if (!$projectExists) {
                throw new HttpResponseException(
                    response()->json(['detail' => 'Project not found in this workspace.'], 404)
                );
            }
        } else {
            $projectId = null;
        }

        $results = [];
        $successCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;

        foreach ($files as $file) {
            $originalName = $this->sanitizeFilename($file->getClientOriginalName() ?: 'uploaded_transcript.txt');
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!isset(self::ALLOWED_MIME[$ext])) {
                $failedCount++;
                $results[] = [
                    'filename' => $originalName,
                    'status' => 'error',
                    'transcript_id' => null,
                    'transcript' => null,
                    'error_code' => 'UNSUPPORTED_FORMAT',
                    'error_message' => "Unsupported file extension '.{$ext}'. Allowed: txt, srt, docx, pdf.",
                ];
                continue;
            }

            $size = (int) $file->getSize();
            if ($size <= 0 || $size > self::MAX_FILE_BYTES) {
                $failedCount++;
                $results[] = [
                    'filename' => $originalName,
                    'status' => 'error',
                    'transcript_id' => null,
                    'transcript' => null,
                    'error_code' => 'INVALID_FILE_SIZE',
                    'error_message' => 'File must be non-empty and at most 25 MB.',
                ];
                continue;
            }

            $rawBytes = @file_get_contents($file->getRealPath());
            if ($rawBytes === false || $rawBytes === '') {
                $failedCount++;
                $results[] = [
                    'filename' => $originalName,
                    'status' => 'error',
                    'transcript_id' => null,
                    'transcript' => null,
                    'error_code' => 'READ_ERROR',
                    'error_message' => 'Unable to read uploaded file.',
                ];
                continue;
            }

            try {
                $extracted = $this->extractByExtension($ext, $rawBytes);
            } catch (\Throwable $e) {
                $failedCount++;
                $results[] = [
                    'filename' => $originalName,
                    'status' => 'error',
                    'transcript_id' => null,
                    'transcript' => null,
                    'error_code' => 'EXTRACTION_FAILED',
                    'error_message' => $e->getMessage(),
                ];
                continue;
            }

            $contentText = trim($extracted['text']);
            if ($contentText === '') {
                $failedCount++;
                $results[] = [
                    'filename' => $originalName,
                    'status' => 'error',
                    'transcript_id' => null,
                    'transcript' => null,
                    'error_code' => 'EMPTY_CONTENT',
                    'error_message' => 'No readable transcript text could be extracted.',
                ];
                continue;
            }

            $contentHash = hash('sha256', $contentText);
            $existing = Transcript::query()
                ->where('workspace_id', $workspaceId)
                ->where('content_hash', $contentHash)
                ->first();

            if ($existing) {
                $duplicateCount++;
                $results[] = [
                    'filename' => $originalName,
                    'status' => 'duplicate',
                    'transcript_id' => $existing->id,
                    'transcript' => null,
                    'error_code' => 'DUPLICATE_TRANSCRIPT',
                    'error_message' => 'An identical transcript already exists in this workspace.',
                ];
                continue;
            }

            $fileSha256 = hash('sha256', $rawBytes);
            $title = pathinfo($originalName, PATHINFO_FILENAME) ?: 'Imported Transcript';
            $title = mb_substr($title, 0, 255);

            $transcript = DB::transaction(function () use (
                $workspaceId,
                $projectId,
                $userId,
                $title,
                $ext,
                $contentText,
                $contentHash,
                $extracted,
                $originalName,
                $rawBytes,
                $fileSha256
            ): Transcript {
                $t = Transcript::create([
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $workspaceId,
                    'project_id' => $projectId,
                    'created_by_user_id' => $userId,
                    'title' => $title,
                    'source_type' => $ext.'_import',
                    'language' => 'en',
                    'status' => 'imported',
                    'content_text' => $contentText,
                    'content_hash' => $contentHash,
                ]);

                $segments = $extracted['segments'];
                if (empty($segments)) {
                    $segments = $this->parsePlainSegments($contentText);
                }

                foreach ($segments as $idx => $seg) {
                    TranscriptSegment::create([
                        'id' => (string) Str::uuid(),
                        'transcript_id' => $t->id,
                        'sequence_number' => $idx + 1,
                        'speaker_label' => $seg['speaker_label'] ?? null,
                        'start_ms' => $seg['start_ms'] ?? null,
                        'end_ms' => $seg['end_ms'] ?? null,
                        'text' => $seg['text'],
                    ]);
                }

                $storageKey = sprintf('workspaces/%s/imports/%s_%s', $workspaceId, (string) Str::uuid(), $originalName);
                Storage::disk('local')->put($storageKey, $rawBytes);

                TranscriptFile::create([
                    'id' => (string) Str::uuid(),
                    'transcript_id' => $t->id,
                    'workspace_id' => $workspaceId,
                    'storage_key' => $storageKey,
                    'original_filename' => $originalName,
                    'mime_type' => self::ALLOWED_MIME[$ext],
                    'size_bytes' => strlen($rawBytes),
                    'sha256' => $fileSha256,
                    'file_role' => 'original',
                    'created_at' => now(),
                ]);

                AuditEvent::create([
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $workspaceId,
                    'actor_user_id' => $userId,
                    'action' => 'transcript.imported',
                    'resource_type' => 'transcript',
                    'resource_id' => $t->id,
                    'metadata' => ['format' => $ext, 'size_bytes' => strlen($rawBytes)],
                    'created_at' => now(),
                ]);

                return $t->loadCount('segments');
            });

            $successCount++;
            $results[] = [
                'filename' => $originalName,
                'status' => 'success',
                'transcript_id' => $transcript->id,
                'transcript' => [
                    'id' => $transcript->id,
                    'workspace_id' => $transcript->workspace_id,
                    'project_id' => $transcript->project_id,
                    'title' => $transcript->title,
                    'description' => $transcript->description,
                    'source_type' => $transcript->source_type,
                    'recorded_at' => $transcript->recorded_at?->toIso8601String(),
                    'language' => $transcript->language,
                    'status' => $transcript->status,
                    'content_hash' => $transcript->content_hash,
                    'created_at' => $transcript->created_at?->toIso8601String(),
                    'segment_count' => (int) ($transcript->segments_count ?? 0),
                ],
                'error_code' => null,
                'error_message' => null,
            ];
        }

        return [
            'workspace_id' => $workspaceId,
            'total_files' => count($files),
            'successful_count' => $successCount,
            'duplicate_count' => $duplicateCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    /**
     * @return array{text: string, segments: list<array{speaker_label: ?string, start_ms: ?int, end_ms: ?int, text: string}>}
     */
    private function extractByExtension(string $ext, string $rawBytes): array
    {
        return match ($ext) {
            'txt' => $this->extractTxt($rawBytes),
            'srt' => $this->extractSrt($rawBytes),
            'docx' => $this->extractDocx($rawBytes),
            'pdf' => $this->extractPdf($rawBytes),
            default => throw new RuntimeException("Unsupported format: {$ext}"),
        };
    }

    /**
     * @return array{text: string, segments: list<array{speaker_label: ?string, start_ms: ?int, end_ms: ?int, text: string}>}
     */
    private function extractTxt(string $rawBytes): array
    {
        $text = $this->normalizeUtf8($rawBytes);

        return [
            'text' => $text,
            'segments' => $this->parsePlainSegments($text),
        ];
    }

    /**
     * @return array{text: string, segments: list<array{speaker_label: ?string, start_ms: ?int, end_ms: ?int, text: string}>}
     */
    private function extractSrt(string $rawBytes): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $this->normalizeUtf8($rawBytes));
        $blocks = preg_split('/\n{2,}/', trim($raw)) ?: [];
        $segments = [];
        $linesOut = [];

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), static fn (string $l): bool => $l !== ''));
            if (count($lines) < 2) {
                continue;
            }

            $timeLineIdx = preg_match('/-->/', $lines[0]) ? 0 : (preg_match('/-->/', $lines[1] ?? '') ? 1 : -1);
            if ($timeLineIdx === -1) {
                continue;
            }

            [$startMs, $endMs] = $this->parseSrtTimeRange($lines[$timeLineIdx]);
            $bodyLines = array_slice($lines, $timeLineIdx + 1);
            $cueText = trim(implode(' ', $bodyLines));
            if ($cueText === '') {
                continue;
            }

            [$speaker, $cleanText] = $this->splitSpeaker($cueText);
            $segments[] = [
                'speaker_label' => $speaker,
                'start_ms' => $startMs,
                'end_ms' => $endMs,
                'text' => $cleanText,
            ];
            $linesOut[] = $speaker ? "{$speaker}: {$cleanText}" : $cleanText;
        }

        return [
            'text' => implode("\n", $linesOut),
            'segments' => $segments,
        ];
    }

    /**
     * @return array{text: string, segments: list<array{speaker_label: ?string, start_ms: ?int, end_ms: ?int, text: string}>}
     */
    private function extractDocx(string $rawBytes): array
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'docx_');
        if ($tmpPath === false) {
            throw new RuntimeException('Failed to create temporary file for DOCX.');
        }

        try {
            file_put_contents($tmpPath, $rawBytes);
            $zip = new ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                throw new RuntimeException('Invalid DOCX archive.');
            }

            $xmlContent = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xmlContent === false) {
                throw new RuntimeException('DOCX archive does not contain word/document.xml.');
            }

            $xml = @simplexml_load_string($xmlContent, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if ($xml === false) {
                throw new RuntimeException('Failed to parse DOCX XML content.');
            }

            $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $paragraphs = $xml->xpath('//w:body/w:p') ?: [];
            $lines = [];
            foreach ($paragraphs as $p) {
                $runs = $p->xpath('.//w:t') ?: [];
                $paraText = '';
                foreach ($runs as $run) {
                    $paraText .= (string) $run;
                }
                $paraText = trim($paraText);
                if ($paraText !== '') {
                    $lines[] = $paraText;
                }
            }

            $fullText = implode("\n", $lines);

            return [
                'text' => $fullText,
                'segments' => $this->parsePlainSegments($fullText),
            ];
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Pure-PHP PDF text extractor supporting uncompressed and FlateDecode text streams.
     *
     * @return array{text: string, segments: list<array{speaker_label: ?string, start_ms: ?int, end_ms: ?int, text: string}>}
     */
    private function extractPdf(string $rawBytes): array
    {
        if (!str_starts_with($rawBytes, '%PDF-')) {
            throw new RuntimeException('Invalid PDF header.');
        }

        $chunks = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $rawBytes, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                $candidate = $decoded !== false ? $decoded : $stream;

                if (preg_match_all('/\(([^()\\\\]*(?:\\\\.[^()\\\\]*)*)\)\s*Tj/s', $candidate, $tjMatches)) {
                    foreach ($tjMatches[1] as $lit) {
                        $chunks[] = $this->unescapePdfLiteral($lit);
                    }
                }
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $candidate, $tjArrMatches)) {
                    foreach ($tjArrMatches[1] as $arrBody) {
                        if (preg_match_all('/\(([^()\\\\]*(?:\\\\.[^()\\\\]*)*)\)/s', $arrBody, $inner)) {
                            $chunks[] = implode('', array_map([$this, 'unescapePdfLiteral'], $inner[1]));
                        }
                    }
                }
            }
        }

        $text = trim(preg_replace('/\s+/', ' ', implode("\n", $chunks)) ?? '');
        if ($text === '') {
            // Fallback: extract printable ASCII/UTF-8 runs if PDF uses non-standard encoding
            if (preg_match_all('/\(([^\)]{4,})\)/', $rawBytes, $fallback)) {
                $text = trim(implode(' ', array_map([$this, 'unescapePdfLiteral'], $fallback[1])));
            }
        }

        return [
            'text' => $text,
            'segments' => $this->parsePlainSegments($text),
        ];
    }

    private function unescapePdfLiteral(string $literal): string
    {
        return str_replace(
            ['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'],
            ["\n", "\r", "\t", '(', ')', '\\'],
            $literal
        );
    }

    /**
     * @return list<array{speaker_label: ?string, start_ms: ?int, end_ms: ?int, text: string}>
     */
    private function parsePlainSegments(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $text) ?: []), static fn (string $l): bool => $l !== ''));
        $segments = [];
        foreach ($lines as $line) {
            [$speaker, $clean] = $this->splitSpeaker($line);
            $segments[] = [
                'speaker_label' => $speaker,
                'start_ms' => null,
                'end_ms' => null,
                'text' => $clean,
            ];
        }

        return $segments;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function splitSpeaker(string $line): array
    {
        if (preg_match('/^([A-Za-z0-9 _.-]{1,40}):\s+(.+)$/', $line, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [null, $line];
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function parseSrtTimeRange(string $line): array
    {
        if (!preg_match('/(\d{2}:\d{2}:\d{2}[,\.]\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}[,\.]\d{3})/', $line, $m)) {
            return [null, null];
        }

        return [$this->srtTimeToMs($m[1]), $this->srtTimeToMs($m[2])];
    }

    private function srtTimeToMs(string $time): int
    {
        $normalized = str_replace(',', '.', $time);
        [$hms, $ms] = explode('.', $normalized);
        [$h, $m, $s] = array_map('intval', explode(':', $hms));

        return (($h * 3600) + ($m * 60) + $s) * 1000 + (int) $ms;
    }

    private function normalizeUtf8(string $bytes): string
    {
        // Strip UTF-8 BOM if present
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }

        return mb_convert_encoding($bytes, 'UTF-8', 'UTF-8');
    }

    private function sanitizeFilename(string $filename): string
    {
        $base = basename(str_replace('\\', '/', $filename));
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?? 'file.txt';

        return $clean !== '' ? mb_substr($clean, 0, 180) : 'file.txt';
    }
}
