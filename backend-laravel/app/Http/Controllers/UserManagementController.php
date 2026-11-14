<?php

namespace App\Http\Controllers;

use App\Models\Transcript;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
                'is_current_user' => $u->id === $request->user()->id,
                'workspaces_count' => $wsCount,
                'created_at' => $u->created_at?->format('M d, Y H:i') ?? 'N/A',
            ];
        });

        return response()->json([
            'users' => $users,
            'total' => $users->count(),
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

        $newStatus = $user->status === 'active' ? 'suspended' : 'active';
        $user->status = $newStatus;
        $user->save();

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

        $user->is_super_admin = !$user->is_super_admin;
        $user->save();

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'is_super_admin' => (bool) $user->is_super_admin,
            'message' => $user->is_super_admin
                ? "User {$user->display_name} has been promoted to Super-Admin."
                : "User {$user->display_name} has been demoted to standard user.",
        ]);
    }
}
