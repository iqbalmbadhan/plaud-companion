<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaces)
    {
    }

    public function index(Request $request, string $workspaceId): JsonResponse
    {
        $this->workspaces->requireMembership($workspaceId, $request->user()->id);

        $projects = Project::query()
            ->withCount('transcripts')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (Project $p): array => [
                'id' => $p->id,
                'workspace_id' => $p->workspace_id,
                'name' => $p->name,
                'description' => $p->description,
                'status' => $p->status,
                'created_at' => $p->created_at?->toIso8601String(),
                'transcript_count' => (int) ($p->transcripts_count ?? 0),
            ]);

        return response()->json($projects);
    }

    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $this->workspaces->requireMembership($workspaceId, $request->user()->id, ['owner', 'admin', 'editor']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $project = Project::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'status' => 'active',
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'created_at' => $project->created_at?->toIso8601String(),
            'transcript_count' => 0,
        ], 201);
    }

    public function show(Request $request, string $projectId): JsonResponse
    {
        $project = Project::query()->withCount('transcripts')->find($projectId);
        if (!$project) {
            return response()->json(['detail' => 'Project not found.'], 404);
        }

        $this->workspaces->requireMembership($project->workspace_id, $request->user()->id);

        return response()->json([
            'id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'created_at' => $project->created_at?->toIso8601String(),
            'transcript_count' => (int) ($project->transcripts_count ?? 0),
        ]);
    }
}
