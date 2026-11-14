<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exports)
    {
    }

    public function index(Request $request, string $workspaceId): JsonResponse
    {
        return response()->json($this->exports->listWorkspaceExports($workspaceId, $request->user()->id));
    }

    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'transcript_ids' => ['required', 'array', 'min:1'],
            'transcript_ids.*' => ['string'],
            'format' => ['sometimes', 'string', 'in:docx,pdf,both'],
            'template' => ['sometimes', 'string', 'in:clean_transcript,meeting_minutes,executive_summary,transcript_with_actions'],
            'title' => ['nullable', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'include_metadata' => ['sometimes', 'boolean'],
            'include_ai_summary' => ['sometimes', 'boolean'],
            'include_action_items' => ['sometimes', 'boolean'],
        ]);

        $job = $this->exports->createExportJob(
            $workspaceId,
            $request->user()->id,
            $validated['transcript_ids'],
            $validated
        );

        return response()->json($job, 201);
    }

    public function demoPackage(Request $request, string $workspaceId): JsonResponse
    {
        $job = $this->exports->createDemoExport($workspaceId, $request->user()->id);

        return response()->json($job, 201);
    }

    public function show(Request $request, string $exportId): JsonResponse
    {
        return response()->json($this->exports->getExportJob($exportId, $request->user()->id));
    }

    public function download(Request $request, string $exportId): Response
    {
        [$bytes, $filename] = $this->exports->downloadExportArchive($exportId, $request->user()->id);

        return response($bytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
