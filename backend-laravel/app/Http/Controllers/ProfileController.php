<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\ExportJob;
use App\Models\Transcript;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile view.
     */
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        $initials = $this->calculateInitials($user->display_name ?: $user->email);

        $workspaces = WorkspaceMember::with('workspace')
            ->where('user_id', $user->id)
            ->get();

        $transcriptsCount = Transcript::where('created_by_user_id', $user->id)->count();
        $exportsCount = ExportJob::where('requested_by_user_id', $user->id)->count();

        return view('profile.show', [
            'user' => $user,
            'initials' => $initials,
            'workspaces' => $workspaces,
            'transcriptsCount' => $transcriptsCount,
            'exportsCount' => $exportsCount,
        ]);
    }

    /**
     * Update the authenticated user's display name.
     */
    public function updateProfile(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'display_name' => 'required|string|min:2|max:100',
        ]);

        $user->display_name = trim($validated['display_name']);
        $user->save();

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => $user->id,
            'action' => 'user.profile_updated',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'metadata' => [
                'display_name' => $user->display_name,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            'created_at' => now(),
        ]);

        if ($request->expectsJson() || $request->is('*/api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'user' => $user,
            ]);
        }

        return back()->with('status', 'Profile display name updated successfully.');
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // A developer working as this user must not change their password.
        if (\App\Support\Impersonation::active()) {
            $msg = 'Passwords cannot be changed while working as another user.';
            if ($request->expectsJson() || $request->is('*/api/*')) {
                return response()->json(['error' => 'impersonating', 'message' => $msg], 403);
            }

            return back()->withErrors(['current_password' => $msg]);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        $tokenService = app(TokenService::class);
        if (!$tokenService->verifyPassword($validated['current_password'], $user->password_hash)) {
            if ($request->expectsJson() || $request->is('*/api/*')) {
                return response()->json([
                    'message' => 'The provided current password does not match our records.',
                    'errors' => [
                        'current_password' => ['The provided current password does not match our records.'],
                    ],
                ], 422);
            }

            return back()->withErrors([
                'current_password' => 'The provided current password does not match our records.',
            ]);
        }

        $user->password_hash = $tokenService->hashPassword($validated['password']);
        $user->save();
        // Sign out every other browser and invalidate API/mobile tokens; keep this session.
        $tokenService->revokeAllSessions($user);
        $request->session()->regenerate();
        $tokenService->markSessionAuthenticated($request);

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => $user->id,
            'action' => 'user.password_changed',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            'created_at' => now(),
        ]);

        if ($request->expectsJson() || $request->is('*/api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully. You were signed out on all other devices.',
            ]);
        }

        return back()->with('status', 'Your password has been changed successfully. You were signed out on all other devices.');
    }

    /**
     * Return JSON profile payload for API endpoints.
     */
    public function apiShow(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $initials = $this->calculateInitials($user->display_name ?: $user->email);

        $workspaces = WorkspaceMember::with('workspace')
            ->where('user_id', $user->id)
            ->get();

        $transcriptsCount = Transcript::where('created_by_user_id', $user->id)->count();
        $exportsCount = ExportJob::where('requested_by_user_id', $user->id)->count();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'status' => $user->status,
                'is_super_admin' => (bool) $user->is_super_admin,
                'initials' => $initials,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'workspaces' => $workspaces,
            'metrics' => [
                'transcripts_count' => $transcriptsCount,
                'exports_count' => $exportsCount,
            ],
        ]);
    }

    /**
     * Helper to compute initials from a name or email.
     */
    protected function calculateInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = isset($parts[0][0]) ? strtoupper($parts[0][0]) : 'P';
        $second = isset($parts[1][0]) ? strtoupper($parts[1][0]) : '';
        return $first . $second;
    }
}
