<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ImportController extends Controller
{
    public function __construct(private readonly ImportService $imports)
    {
    }

    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $files = $request->file('files');
        if (empty($files)) {
            $single = $request->file('file');
            $files = $single ? [$single] : [];
        }
        if (!is_array($files)) {
            $files = [$files];
        }
        $files = array_values(array_filter($files));

        if (empty($files)) {
            return response()->json(['detail' => 'No files provided for upload.'], 400);
        }

        $projectId = $request->input('project_id');
        $result = $this->imports->importBatch(
            $workspaceId,
            $request->user()->id,
            $files,
            is_string($projectId) ? $projectId : null
        );

        return response()->json($result);
    }
}
