<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaces)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->workspaces->listUserWorkspaces($request->user()->id));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['sometimes', 'string', 'in:personal,team'],
        ]);

        $workspace = $this->workspaces->createWorkspace(
            $request->user()->id,
            $validated['name'],
            $validated['type'] ?? 'team'
        );

        return response()->json($workspace, 201);
    }

    public function show(Request $request, string $workspaceId): JsonResponse
    {
        return response()->json($this->workspaces->getWorkspace($workspaceId, $request->user()->id));
    }

    public function members(Request $request, string $workspaceId): JsonResponse
    {
        return response()->json($this->workspaces->listMembers($workspaceId, $request->user()->id));
    }

    public function invite(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,editor,viewer'],
        ]);

        $invitation = $this->workspaces->inviteMember(
            $workspaceId,
            $request->user()->id,
            $validated['email'],
            $validated['role'] ?? 'editor'
        );

        return response()->json($invitation, 201);
    }
}
