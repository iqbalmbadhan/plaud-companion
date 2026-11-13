<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\ExportJob;
use App\Models\ExportJobItem;
use App\Models\Transcript;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ExportService
{
    public function __construct(private readonly WorkspaceService $workspaces)
    {
    }

    /**
     * @param list<string> $transcriptIds
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createExportJob(
        string $workspaceId,
        string $userId,
        array $transcriptIds,
        array $options = []
    ): array {
        $this->workspaces->requireMembership($workspaceId, $userId, ['owner', 'admin', 'editor']);

        $transcripts = Transcript::query()
            ->with('segments')
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $transcriptIds)
            ->get()
            ->keyBy('id');

        if ($transcripts->isEmpty()) {
            throw new HttpResponseException(
                response()->json(['detail' => 'No matching transcripts found in this workspace.'], 404)
            );
        }

        $format = (string) ($options['format'] ?? 'both');
        $template = (string) ($options['template'] ?? 'clean_transcript');
        $jobId = (string) Str::uuid();

        $job = ExportJob::create([
            'id' => $jobId,
            'workspace_id' => $workspaceId,
            'requested_by_user_id' => $userId,
            'status' => 'processing',
            'template' => $template,
            'settings' => [
                'format' => $format,
                'title' => $options['title'] ?? null,
                'organization_name' => $options['organization_name'] ?? null,
                'include_metadata' => (bool) ($options['include_metadata'] ?? true),
                'include_ai_summary' => (bool) ($options['include_ai_summary'] ?? true),
                'include_action_items' => (bool) ($options['include_action_items'] ?? true),
            ],
            'total_items' => count($transcriptIds),
            'completed_items' => 0,
            'failed_items' => 0,
            'expires_at' => now()->addHours(24),
        ]);

        $tmpZip = tempnam(sys_get_temp_dir(), 'export_');
        if ($tmpZip === false) {
            throw new RuntimeException('Failed to allocate temporary export archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpZip);
            throw new RuntimeException('Failed to create export ZIP archive.');
        }

        $completed = 0;
        $failed = 0;
        $manifestItems = [];
        $itemResponses = [];

        try {
            foreach ($transcriptIds as $tid) {
                /** @var Transcript|null $transcript */
                $transcript = $transcripts->get($tid);
                if (!$transcript) {
                    $failed++;
                    $item = ExportJobItem::create([
                        'id' => (string) Str::uuid(),
                        'export_job_id' => $job->id,
                        'transcript_id' => $tid,
                        'status' => 'failed',
                        'error_code' => 'NOT_FOUND',
                        'attempt_count' => 1,
                    ]);
                    $itemResponses[] = [
                        'id' => $item->id,
                        'transcript_id' => $tid,
                        'transcript_title' => 'Unknown',
                        'status' => 'failed',
                        'output_filename' => null,
                        'format' => $format,
                        'error_code' => 'NOT_FOUND',
                    ];
                    continue;
                }

                $safeSlug = $this->slugifyFilename($transcript->title);
                $formatsToBuild = $format === 'both' ? ['docx', 'pdf'] : [$format];
                $lastFilename = null;

                foreach ($formatsToBuild as $fmt) {
                    $filename = "{$safeSlug}.{$fmt}";
                    $bytes = $fmt === 'docx'
                        ? $this->buildDocxBytes($transcript, $options)
                        : $this->buildPdfBytes($transcript, $options);
                    $zip->addFromString($filename, $bytes);
                    $lastFilename = $filename;
                }

                $completed++;
                $item = ExportJobItem::create([
                    'id' => (string) Str::uuid(),
                    'export_job_id' => $job->id,
                    'transcript_id' => $transcript->id,
                    'status' => 'completed',
                    'output_storage_key' => $lastFilename,
                    'attempt_count' => 1,
                ]);

                $manifestItems[] = [
                    'transcript_id' => $transcript->id,
                    'title' => $transcript->title,
                    'files' => array_map(static fn (string $f): string => "{$safeSlug}.{$f}", $formatsToBuild),
                ];

                $itemResponses[] = [
                    'id' => $item->id,
                    'transcript_id' => $transcript->id,
                    'transcript_title' => $transcript->title,
                    'status' => 'completed',
                    'output_filename' => $lastFilename,
                    'format' => $format,
                    'error_code' => null,
                ];
            }

            $manifestJson = (string) json_encode([
                'export_job_id' => $job->id,
                'workspace_id' => $workspaceId,
                'template' => $template,
                'format' => $format,
                'generated_at' => now()->toIso8601String(),
                'items' => $manifestItems,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $zip->addFromString('manifest.json', $manifestJson);
            $zip->close();

            $archiveBytes = (string) file_get_contents($tmpZip);
            $archiveKey = "workspaces/{$workspaceId}/exports/{$job->id}.zip";
            $manifestKey = "workspaces/{$workspaceId}/exports/{$job->id}_manifest.json";

            Storage::disk('local')->put($archiveKey, $archiveBytes);
            Storage::disk('local')->put($manifestKey, $manifestJson);

            $finalStatus = $failed === 0
                ? 'completed'
                : ($completed > 0 ? 'partially_completed' : 'failed');

            $job->update([
                'status' => $finalStatus,
                'completed_items' => $completed,
                'failed_items' => $failed,
                'archive_storage_key' => $archiveKey,
                'manifest_storage_key' => $manifestKey,
            ]);

            AuditEvent::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'actor_user_id' => $userId,
                'action' => 'export.created',
                'resource_type' => 'export_job',
                'resource_id' => $job->id,
                'metadata' => ['format' => $format, 'total_items' => count($transcriptIds)],
                'created_at' => now(),
            ]);

            return $this->formatJobResponse($job, $itemResponses);
        } finally {
            @unlink($tmpZip);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getExportJob(string $exportId, string $userId): array
    {
        $job = ExportJob::query()->with(['items.transcript'])->find($exportId);
        if (!$job) {
            throw new HttpResponseException(response()->json(['detail' => 'Export job not found.'], 404));
        }

        $this->workspaces->requireMembership($job->workspace_id, $userId);

        $format = (string) ($job->settings['format'] ?? 'both');
        $items = $job->items->map(static fn (ExportJobItem $item): array => [
            'id' => $item->id,
            'transcript_id' => $item->transcript_id,
            'transcript_title' => $item->transcript?->title ?? 'Unknown',
            'status' => $item->status,
            'output_filename' => $item->output_storage_key,
            'format' => $format,
            'error_code' => $item->error_code,
        ])->all();

        return $this->formatJobResponse($job, $items);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function downloadExportArchive(string $exportId, string $userId): array
    {
        $job = ExportJob::query()->find($exportId);
        if (!$job) {
            throw new HttpResponseException(response()->json(['detail' => 'Export job not found.'], 404));
        }

        $this->workspaces->requireMembership($job->workspace_id, $userId, ['owner', 'admin', 'editor']);

        if (empty($job->archive_storage_key) || !Storage::disk('local')->exists($job->archive_storage_key)) {
            throw new HttpResponseException(response()->json(['detail' => 'Export archive is not available.'], 404));
        }

        $bytes = (string) Storage::disk('local')->get($job->archive_storage_key);

        return [$bytes, "plaud-export-{$job->id}.zip"];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function formatJobResponse(ExportJob $job, array $items): array
    {
        return [
            'id' => $job->id,
            'workspace_id' => $job->workspace_id,
            'status' => $job->status,
            'template' => $job->template,
            'format' => (string) ($job->settings['format'] ?? 'both'),
            'total_items' => $job->total_items,
            'completed_items' => $job->completed_items,
            'failed_items' => $job->failed_items,
            'download_url' => $job->archive_storage_key ? "/api/v1/exports/{$job->id}/download" : null,
            'created_at' => $job->created_at?->toIso8601String(),
            'items' => $items,
        ];
    }

    /**
     * Pure-XML DOCX builder using ZipArchive.
     *
     * @param array<string, mixed> $options
     */
    private function buildDocxBytes(Transcript $transcript, array $options): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'docx_out_');
        if ($tmpPath === false) {
            throw new RuntimeException('Unable to allocate temporary DOCX file.');
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';

        $paragraphs = [];
        $paragraphs[] = $this->docxParagraph($transcript->title, true);
        if ((bool) ($options['include_metadata'] ?? true)) {
            $meta = sprintf('Source: %s | Language: %s | Created: %s', $transcript->source_type, $transcript->language ?? 'en', $transcript->created_at?->toDateTimeString() ?? '');
            $paragraphs[] = $this->docxParagraph($meta, false);
        }

        if ($transcript->segments->isNotEmpty()) {
            foreach ($transcript->segments as $seg) {
                $prefix = $seg->speaker_label ? $seg->speaker_label.': ' : '';
                $paragraphs[] = $this->docxParagraph($prefix.$seg->text, false);
            }
        } else {
            foreach (preg_split('/\r?\n/', $transcript->content_text) ?: [] as $line) {
                if (trim($line) !== '') {
                    $paragraphs[] = $this->docxParagraph(trim($line), false);
                }
            }
        }

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.implode('', $paragraphs).'<w:sectPr/></w:body>'
            .'</w:document>';

        try {
            $zip = new ZipArchive();
            if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Failed to create DOCX zip.');
            }
            $zip->addFromString('[Content_Types].xml', $contentTypes);
            $zip->addFromString('_rels/.rels', $rels);
            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();

            return (string) file_get_contents($tmpPath);
        } finally {
            @unlink($tmpPath);
        }
    }

    private function docxParagraph(string $text, bool $bold): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $rPr = $bold ? '<w:rPr><w:b/></w:rPr>' : '';

        return "<w:p><w:r>{$rPr}<w:t xml:space=\"preserve\">{$escaped}</w:t></w:r></w:p>";
    }

    /**
     * Pure-PHP minimal valid PDF-1.4 generator.
     *
     * @param array<string, mixed> $options
     */
    private function buildPdfBytes(Transcript $transcript, array $options): string
    {
        $lines = [$transcript->title, ''];
        if ((bool) ($options['include_metadata'] ?? true)) {
            $lines[] = sprintf('Source: %s | Language: %s', $transcript->source_type, $transcript->language ?? 'en');
            $lines[] = '';
        }

        $bodyLines = preg_split('/\r?\n/', $transcript->content_text) ?: [];
        foreach (array_slice($bodyLines, 0, 55) as $line) {
            $wrapped = str_split(trim($line), 90);
            foreach ($wrapped as $chunk) {
                if ($chunk !== '') {
                    $lines[] = $chunk;
                }
            }
        }

        $streamOps = ["BT", "/F1 11 Tf", "50 760 Td", "14 TL"];
        foreach ($lines as $idx => $line) {
            $ascii = preg_replace('/[^\x20-\x7E]/', '?', $line) ?? '';
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
            $streamOps[] = $idx === 0 ? "({$escaped}) Tj" : "T* ({$escaped}) Tj";
        }
        $streamOps[] = "ET";
        $stream = implode("\n", $streamOps);

        $objects = [
            1 => "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            2 => "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            3 => "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
            4 => "4 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream\nendobj\n",
            5 => "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $obj;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($objects as $num => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$num]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function slugifyFilename(string $title): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($title)) ?? 'transcript';
        $slug = trim($slug, '-');

        return $slug !== '' ? mb_substr($slug, 0, 80) : 'transcript';
    }
}
