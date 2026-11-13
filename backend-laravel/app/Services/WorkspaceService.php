<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Transcript;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceService
{
    /**
     * @param list<string>|null $allowedRoles
     */
    public function requireMembership(string $workspaceId, string $userId, ?array $allowedRoles = null): WorkspaceMember
    {
        $workspace = Workspace::query()->where('id', $workspaceId)->first();
        if (!$workspace) {
            throw new HttpResponseException(response()->json(['detail' => 'Workspace not found.'], 404));
        }

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$member) {
            throw new HttpResponseException(response()->json(['detail' => 'Access denied to this workspace.'], 403));
        }

        if ($allowedRoles !== null && !in_array($member->role, $allowedRoles, true)) {
            throw new HttpResponseException(response()->json(['detail' => 'Insufficient permissions in this workspace.'], 403));
        }

        return $member;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUserWorkspaces(string $userId): array
    {
        $memberships = WorkspaceMember::query()
            ->with('workspace')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->get();

        $workspaceIds = $memberships->pluck('workspace_id')->filter()->unique()->values()->all();
        $counts = Transcript::query()
            ->selectRaw('workspace_id, COUNT(*) as cnt')
            ->whereIn('workspace_id', $workspaceIds)
            ->groupBy('workspace_id')
            ->pluck('cnt', 'workspace_id');

        $result = [];
        foreach ($memberships as $m) {
            $ws = $m->workspace;
            if (!$ws) {
                continue;
            }
            $result[] = [
                'id' => $ws->id,
                'name' => $ws->name,
                'type' => $ws->type,
                'owner_user_id' => $ws->owner_user_id,
                'created_at' => $ws->created_at?->toIso8601String(),
                'role' => $m->role,
                'transcript_count' => (int) ($counts[$ws->id] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function createWorkspace(string $userId, string $name, string $type = 'team'): array
    {
        return DB::transaction(function () use ($userId, $name, $type): array {
            $workspace = Workspace::create([
                'id' => (string) Str::uuid(),
                'name' => trim($name),
                'type' => $type,
                'owner_user_id' => $userId,
            ]);

            WorkspaceMember::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'user_id' => $userId,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            AuditEvent::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'actor_user_id' => $userId,
                'action' => 'workspace.created',
                'resource_type' => 'workspace',
                'resource_id' => $workspace->id,
                'metadata' => ['type' => $type],
                'created_at' => now(),
            ]);

            return [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'type' => $workspace->type,
                'owner_user_id' => $workspace->owner_user_id,
                'created_at' => $workspace->created_at?->toIso8601String(),
                'role' => 'owner',
                'transcript_count' => 0,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getWorkspace(string $workspaceId, string $userId): array
    {
        $member = $this->requireMembership($workspaceId, $userId);
        $ws = Workspace::query()->findOrFail($workspaceId);
        $count = Transcript::query()->where('workspace_id', $workspaceId)->count();

        return [
            'id' => $ws->id,
            'name' => $ws->name,
            'type' => $ws->type,
            'owner_user_id' => $ws->owner_user_id,
            'created_at' => $ws->created_at?->toIso8601String(),
            'role' => $member->role,
            'transcript_count' => $count,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMembers(string $workspaceId, string $userId): array
    {
        $this->requireMembership($workspaceId, $userId);

        return WorkspaceMember::query()
            ->with('user')
            ->where('workspace_id', $workspaceId)
            ->orderBy('joined_at')
            ->get()
            ->map(static fn (WorkspaceMember $m): array => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'workspace_id' => $m->workspace_id,
                'role' => $m->role,
                'status' => $m->status,
                'joined_at' => $m->joined_at?->toIso8601String(),
                'email' => $m->user?->email,
                'display_name' => $m->user?->display_name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function inviteMember(string $workspaceId, string $userId, string $email, string $role = 'editor'): array
    {
        $this->requireMembership($workspaceId, $userId, ['owner', 'admin']);

        $rawToken = Str::random(48);
        $invitation = WorkspaceInvitation::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'email' => strtolower(trim($email)),
            'role' => $role,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_by_user_id' => $userId,
            'created_at' => now(),
        ]);

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'actor_user_id' => $userId,
            'action' => 'workspace.member_invited',
            'resource_type' => 'workspace_invitation',
            'resource_id' => $invitation->id,
            'metadata' => ['role' => $role],
            'created_at' => now(),
        ]);

        return [
            'id' => $invitation->id,
            'workspace_id' => $invitation->workspace_id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
        ];
    }
}
