<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TranscriptController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaces)
    {
    }

    public function index(Request $request, string $workspaceId): JsonResponse
    {
        $this->workspaces->requireMembership($workspaceId, $request->user()->id);

        $limit = max(1, min(100, (int) $request->query('limit', 50)));
        $offset = max(0, (int) $request->query('offset', 0));
        $projectId = $request->query('project_id');
        $q = $request->query('q');

        $query = Transcript::query()
            ->withCount('segments')
            ->where('workspace_id', $workspaceId);

        if (is_string($projectId) && $projectId !== '') {
            $query->where('project_id', $projectId);
        }

        if (is_string($q) && trim($q) !== '') {
            $term = '%'.trim($q).'%';
            $query->where(function ($sub) use ($term): void {
                $sub->where('title', 'like', $term)
                    ->orWhere('content_text', 'like', $term);
            });
        }

        $items = $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(static fn (Transcript $t): array => [
                'id' => $t->id,
                'workspace_id' => $t->workspace_id,
                'project_id' => $t->project_id,
                'title' => $t->title,
                'description' => $t->description,
                'source_type' => $t->source_type,
                'recorded_at' => $t->recorded_at?->toIso8601String(),
                'language' => $t->language,
                'status' => $t->status,
                'content_hash' => $t->content_hash,
                'created_at' => $t->created_at?->toIso8601String(),
                'segment_count' => (int) ($t->segments_count ?? 0),
            ]);

        return response()->json($items);
    }

    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $this->workspaces->requireMembership($workspaceId, $request->user()->id, ['owner', 'admin', 'editor']);

        if (!$request->has('content_text') && $request->has('raw_text')) {
            $request->merge(['content_text' => $request->input('raw_text')]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['nullable', 'string'],
            'source_type' => ['sometimes', 'string', 'max:50'],
            'recorded_at' => ['nullable', 'date'],
            'language' => ['nullable', 'string', 'max:20'],
            'content_text' => ['required', 'string', 'min:1'],
            'segments' => ['nullable', 'array'],
            'segments.*.sequence_number' => ['required_with:segments', 'integer'],
            'segments.*.speaker_label' => ['nullable', 'string', 'max:100'],
            'segments.*.start_ms' => ['nullable', 'integer'],
            'segments.*.end_ms' => ['nullable', 'integer'],
            'segments.*.text' => ['required_with:segments', 'string', 'min:1'],
        ]);

        if (!empty($validated['project_id'])) {
            $projectExists = Project::query()
                ->where('id', $validated['project_id'])
                ->where('workspace_id', $workspaceId)
                ->exists();
            if (!$projectExists) {
                return response()->json(['detail' => 'Project not found in this workspace.'], 404);
            }
        }

        $contentHash = hash('sha256', trim($validated['content_text']));
        $segmentsInput = $validated['segments'] ?? [];
        if (empty($segmentsInput)) {
            $segmentsInput = $this->parseSpeakerSegments($validated['content_text']);
        }

        $transcript = DB::transaction(function () use ($workspaceId, $request, $validated, $contentHash, $segmentsInput): Transcript {
            $t = Transcript::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'project_id' => $validated['project_id'] ?? null,
                'created_by_user_id' => $request->user()->id,
                'title' => trim($validated['title']),
                'description' => $validated['description'] ?? null,
                'source_type' => $validated['source_type'] ?? 'manual_upload',
                'recorded_at' => $validated['recorded_at'] ?? null,
                'language' => $validated['language'] ?? 'en',
                'status' => 'processed',
                'content_text' => $validated['content_text'],
                'content_hash' => $contentHash,
            ]);

            foreach ($segmentsInput as $seg) {
                TranscriptSegment::create([
                    'id' => (string) Str::uuid(),
                    'transcript_id' => $t->id,
                    'sequence_number' => (int) $seg['sequence_number'],
                    'speaker_label' => $seg['speaker_label'] ?? null,
                    'start_ms' => $seg['start_ms'] ?? null,
                    'end_ms' => $seg['end_ms'] ?? null,
                    'text' => $seg['text'],
                ]);
            }

            return $t->load('segments');
        });

        return response()->json($this->formatDetail($transcript), 201);
    }

    public function show(Request $request, string $transcriptId): JsonResponse
    {
        $transcript = Transcript::query()->with('segments')->find($transcriptId);
        if (!$transcript) {
            return response()->json(['detail' => 'Transcript not found.'], 404);
        }

        $this->workspaces->requireMembership($transcript->workspace_id, $request->user()->id);

        return response()->json($this->formatDetail($transcript));
    }

    public function destroy(Request $request, string $transcriptId): JsonResponse
    {
        $transcript = Transcript::query()->find($transcriptId);
        if (!$transcript) {
            return response()->json(['detail' => 'Transcript not found.'], 404);
        }

        $this->workspaces->requireMembership($transcript->workspace_id, $request->user()->id, ['owner', 'admin', 'editor']);
        $transcript->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDetail(Transcript $t): array
    {
        return [
            'id' => $t->id,
            'workspace_id' => $t->workspace_id,
            'project_id' => $t->project_id,
            'title' => $t->title,
            'description' => $t->description,
            'source_type' => $t->source_type,
            'recorded_at' => $t->recorded_at?->toIso8601String(),
            'language' => $t->language,
            'status' => $t->status,
            'content_hash' => $t->content_hash,
            'created_at' => $t->created_at?->toIso8601String(),
            'segment_count' => $t->segments->count(),
            'content_text' => $t->content_text,
            'segments' => $t->segments->map(static fn (TranscriptSegment $s): array => [
                'id' => $s->id,
                'sequence_number' => $s->sequence_number,
                'speaker_label' => $s->speaker_label,
                'start_ms' => $s->start_ms,
                'end_ms' => $s->end_ms,
                'text' => $s->text,
            ])->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseSpeakerSegments(string $text): array
    {
        $lines = preg_split('/\R+/', trim($text)) ?: [];
        $segments = [];
        $seq = 1;
        $cursorMs = 0;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            $speaker = 'Speaker ' . (($seq % 2 === 1) ? '1' : '2');
            $startMs = $cursorMs;
            $body = $line;

            if (preg_match('/^\[?(\d{1,2}):(\d{2})(?::(\d{2}))?\]?\s*([^:]{1,50}):\s*(.+)$/', $line, $m)) {
                if ($m[3] !== '') {
                    $startMs = ((int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3]) * 1000;
                } else {
                    $startMs = ((int) $m[1] * 60 + (int) $m[2]) * 1000;
                }
                $speaker = trim($m[4]);
                $body = trim($m[5]);
            } elseif (preg_match('/^([^:]{1,50}):\s*(.+)$/', $line, $m)) {
                $speaker = trim($m[1]);
                $body = trim($m[2]);
            }

            $endMs = $startMs + 15000;
            $cursorMs = $endMs;

            $segments[] = [
                'sequence_number' => $seq++,
                'speaker_label' => $speaker,
                'start_ms' => $startMs,
                'end_ms' => $endMs,
                'text' => $body,
            ];
        }

        return $segments;
    }
}
