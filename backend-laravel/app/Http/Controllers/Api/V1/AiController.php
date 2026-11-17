<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Transcript;
use App\Services\AiService;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AiController extends Controller
{
    public function __construct(
        private readonly AiService $ai,
        private readonly WorkspaceService $workspaces
    ) {
    }

    public function connectors(Request $request, string $workspaceId): JsonResponse
    {
        return response()->json($this->ai->listConnectors($workspaceId, $request->user()->id));
    }

    public function connect(Request $request, string $workspaceId, string $providerId): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($this->ai->connectProvider(
            $workspaceId,
            $request->user()->id,
            $providerId,
            $validated['api_key'] ?? null,
            $validated['model'] ?? null
        ));
    }

    public function test(Request $request, string $workspaceId, string $providerId): JsonResponse
    {
        return response()->json($this->ai->testWorkspaceConnection($workspaceId, $request->user()->id, $providerId));
    }

    public function fetchModels(Request $request, string $workspaceId, string $providerId): JsonResponse
    {
        $apiKey = (string) ($request->input('api_key', ''));
        if ($apiKey === '') {
            return response()->json(['error' => 'api_key required', 'models' => []], 422);
        }

        $models = $this->ai->fetchProviderModels($workspaceId, $request->user()->id, $providerId, $apiKey);

        return response()->json(['provider_id' => $providerId, 'models' => $models]);
    }

    public function summary(Request $request, string $arg1, ?string $arg2 = null): JsonResponse
    {
        $transcriptId = $arg2 !== null ? $arg2 : $arg1;
        $workspaceId = $arg2 !== null ? $arg1 : null;

        $validated = $request->validate([
            'summary_type' => ['sometimes', 'string', 'in:concise,detailed,executive,meeting_minutes'],
            'provider_id' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:100'],
        ]);

        $transcript = Transcript::query()->find($transcriptId);
        if (!$transcript) {
            return response()->json(['detail' => 'Transcript not found.'], 404);
        }

        if ($workspaceId !== null && $transcript->workspace_id !== $workspaceId) {
            return response()->json(['detail' => 'Transcript does not belong to specified workspace.'], 404);
        }

        $this->workspaces->requireMembership($transcript->workspace_id, $request->user()->id);

        return response()->json($this->ai->generateSummary(
            $transcript,
            $validated['summary_type'] ?? 'concise',
            $validated['provider_id'] ?? null,
            $validated['model'] ?? null
        ));
    }

    public function actionItems(Request $request, string $arg1, ?string $arg2 = null): JsonResponse
    {
        $transcriptId = $arg2 !== null ? $arg2 : $arg1;
        $workspaceId = $arg2 !== null ? $arg1 : null;

        $transcript = Transcript::query()->find($transcriptId);
        if (!$transcript) {
            return response()->json(['detail' => 'Transcript not found.'], 404);
        }

        if ($workspaceId !== null && $transcript->workspace_id !== $workspaceId) {
            return response()->json(['detail' => 'Transcript does not belong to specified workspace.'], 404);
        }

        $this->workspaces->requireMembership($transcript->workspace_id, $request->user()->id);

        return response()->json($this->ai->extractActionItems($transcript));
    }

    public function ask(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:1000'],
            'transcript_ids' => ['required', 'array', 'min:1', 'max:20'],
            'transcript_ids.*' => ['string'],
            'provider_id' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:100'],
        ]);

        $this->workspaces->requireMembership($workspaceId, $request->user()->id);

        try {
            $transcripts = Transcript::query()
                ->with('segments')
                ->where('workspace_id', $workspaceId)
                ->whereIn('id', $validated['transcript_ids'])
                ->get();

            if ($transcripts->isEmpty()) {
                return response()->json(['detail' => 'None of the specified transcripts were found in this workspace.'], 400);
            }

            $tData = $transcripts->map(static fn (Transcript $t): array => [
                'id' => $t->id,
                'title' => $t->title,
                'text' => (string) ($t->content_text ?? ''),
                'speaker' => $t->segments->first()?->speaker_label ?? 'Speaker',
            ])->all();

            return response()->json($this->ai->answerQuestion(
                $workspaceId,
                $validated['question'],
                $tData,
                $validated['provider_id'] ?? null,
                $validated['model'] ?? null
            ));
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $rawMsg = $e->getMessage();
            $sanitized = preg_replace('/(key=)[^&\s]+/i', '$1[REDACTED]', $rawMsg);
            $sanitized = preg_replace('/(Bearer\s+)[A-Za-z0-9_\-\.]+/i', '$1[REDACTED]', $sanitized);
            $sanitized = preg_replace('/sk-[a-zA-Z0-9_\-]{10,}/i', '[REDACTED]', $sanitized);

            return response()->json([
                'answer' => 'Unable to complete cross-transcript AI synthesis at this time: ' . \Illuminate\Support\Str::limit($sanitized, 160),
                'citations' => [],
                'provider_used' => 'system-error-handler',
                'model_used' => 'none',
                'has_sufficient_evidence' => false,
                'error' => true,
            ], 200);
        }
    }
}
