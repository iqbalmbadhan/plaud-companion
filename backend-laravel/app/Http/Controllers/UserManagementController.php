<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PasswordResetService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $suspendedUsers = User::where('status', 'suspended')->count();
        $superAdmins = User::where('is_super_admin', true)->count();

        return view('settings.users', [
            'metrics' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'suspended' => $suspendedUsers,
                'super_admins' => $superAdmins,
            ],
            'currentUser' => $request->user(),
            'isDeveloper' => $request->user()->isDeveloper(),
        ]);
    }

    public function apiList(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $query = User::query()->orderBy('created_at', 'desc');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('display_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($status !== '' && in_array($status, ['active', 'suspended'], true)) {
            $query->where('status', $status);
        }

        $users = $query->get()->map(function (User $u) use ($request) {
            $wsCount = Workspace::where('owner_user_id', $u->id)->count();
            return [
                'id' => $u->id,
                'email' => $u->email,
                'display_name' => $u->display_name,
                'status' => $u->status,
                'is_super_admin' => (bool) $u->is_super_admin,
                'is_developer' => $u->isDeveloper(),
                'is_current_user' => $u->id === $request->user()->id,
                'can_manage' => $this->canManage($request->user(), $u),
                'workspaces_count' => $wsCount,
                'created_at' => $u->created_at?->format('M d, Y H:i') ?? 'N/A',
            ];
        });

        return response()->json([
            'users' => $users,
            'total' => $users->count(),
        ]);
    }

    public function apiProfile(Request $request, string $userId): JsonResponse
    {
        $user = User::query()->find($userId);
        if (!$user) {
            return response()->json(['error' => 'not_found', 'message' => 'User not found.'], 404);
        }

        $workspaces = Workspace::where('owner_user_id', $user->id)->get();
        $transcriptsCount = Transcript::where('created_by_user_id', $user->id)->count();
        $deviceActivity = AuditEvent::where('actor_user_id', $user->id)
            ->where('action', 'device.ble_paired')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'display_name' => $user->display_name,
            'status' => $user->status,
            'is_super_admin' => (bool) $user->is_super_admin,
            'is_developer' => $user->isDeveloper(),
            'created_at' => $user->created_at?->format('M d, Y H:i'),
            'workspaces' => $workspaces,
            'workspaces_count' => $workspaces->count(),
            'transcripts_count' => $transcriptsCount,
            'device_activity' => $deviceActivity,
        ]);
    }

    public function toggleStatus(Request $request, string $userId): JsonResponse
    {
        $currentUser = $request->user();
        if ($currentUser->id === $userId) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Safeguard: You cannot suspend your own active administrator account.',
            ], 400);
        }

        $user = User::query()->find($userId);
        if (!$user) {
            return response()->json(['error' => 'not_found', 'message' => 'User not found.'], 404);
        }

        if (!$this->canManage($currentUser, $user)) {
            return $this->developerProtected();
        }
        if ($user->isDeveloper() && $user->status === 'active'
            && User::query()->where('is_developer', true)->where('status', 'active')->count() <= 1) {
            return response()->json(['error' => 'forbidden', 'message' => 'You cannot suspend the last active developer.'], 400);
        }

        $previousStatus = $user->status;
        $newStatus = $previousStatus === 'active' ? 'suspended' : 'active';
        $user->status = $newStatus;
        $user->save();
        if ($newStatus === 'suspended') {
            // Suspension also ends every session and API token, so reactivation never revives them.
            app(TokenService::class)->revokeAllSessions($user);
        }

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => $currentUser->id,
            'action' => $newStatus === 'suspended' ? 'user.suspended' : 'user.reactivated',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'metadata' => [
                'target_email' => $user->email,
                'target_display_name' => $user->display_name,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'status' => $user->status,
            'message' => $newStatus === 'suspended'
                ? "User {$user->display_name} has been suspended. Access to Web and API is now blocked."
                : "User {$user->display_name} has been reactivated successfully.",
        ]);
    }

    public function toggleAdmin(Request $request, string $userId): JsonResponse
    {
        $currentUser = $request->user();
        if ($currentUser->id === $userId) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Safeguard: You cannot modify your own super-admin status.',
            ], 400);
        }

        $user = User::query()->find($userId);
        if (!$user) {
            return response()->json(['error' => 'not_found', 'message' => 'User not found.'], 404);
        }

        if (!$this->canManage($currentUser, $user)) {
            return $this->developerProtected();
        }
        if ($user->isDeveloper()) {
            return response()->json(['error' => 'forbidden', 'message' => 'Developers always have admin rights. Remove the developer role first.'], 400);
        }

        $user->is_super_admin = !$user->is_super_admin;
        $user->save();
        $this->audit($request, $user->is_super_admin ? 'user.admin_granted' : 'user.admin_revoked', $user);

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'is_super_admin' => (bool) $user->is_super_admin,
            'message' => $user->is_super_admin
                ? "User {$user->display_name} has been promoted to Super-Admin."
                : "User {$user->display_name} has been demoted to standard user.",
        ]);
    }

    public function signOutEverywhere(Request $request, string $userId): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return response()->json(['error' => 'not_found', 'message' => 'User not found.'], 404);
        }
        if (!$this->canManage($request->user(), $user)) {
            return $this->developerProtected();
        }

        app(TokenService::class)->revokeAllSessions($user);
        $this->audit($request, 'user.sessions_revoked', $user);

        return response()->json(['success' => true, 'message' => "{$user->display_name} was signed out on every device and app."]);
    }

    public function sendPasswordReset(Request $request, string $userId): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return response()->json(['error' => 'not_found', 'message' => 'User not found.'], 404);
        }
        if (!$this->canManage($request->user(), $user)) {
            return $this->developerProtected();
        }
        if ($user->status !== 'active') {
            return response()->json(['error' => 'forbidden', 'message' => 'Reactivate the account before sending a password reset.'], 400);
        }

        $issued = app(PasswordResetService::class)->issue($user);
        $this->audit($request, 'user.password_reset_sent', $user, ['mailed' => $issued['mailed']]);

        $response = [
            'success' => true,
            'mailed' => $issued['mailed'],
            'message' => $issued['mailed']
                ? "A password reset link was emailed to {$user->email}. It expires in 60 minutes."
                : ($issued['show_link'] ? 'Mail is not configured (development). Use the link below; it expires in 60 minutes.' : 'The reset link could not be emailed. Check the mail settings.'),
        ];
        if ($issued['show_link']) {
            $response['reset_url'] = $issued['url'];
        }

        return response()->json($response, $issued['mailed'] || $issued['show_link'] ? 200 : 502);
    }

    /** Only developers may change a developer's account. */
    private function canManage(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && (!$target->isDeveloper() || $actor->isDeveloper());
    }

    private function developerProtected(): JsonResponse
    {
        return response()->json(['error' => 'forbidden', 'message' => 'Only a developer can change a developer account.'], 403);
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Request $request, string $action, User $target, array $metadata = []): void
    {
        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'resource_type' => 'user',
            'resource_id' => $target->id,
            'metadata' => array_merge(['target_email' => $target->email], $metadata),
            'created_at' => now(),
        ]);
    }
}
