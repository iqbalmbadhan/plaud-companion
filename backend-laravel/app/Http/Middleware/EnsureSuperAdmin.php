<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows ONLY super-admins and developers (is_super_admin or is_developer).
 * Workspace admins, editors, reviewers, members, and guests are rejected with 403.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'forbidden',
                    'detail' => 'Super-admin privileges are required.',
                ], 403);
            }

            return redirect()->guest(route('login'));
        }

        // Admin areas are closed while a developer is working as another user.
        if (\App\Support\Impersonation::active()) {
            $detail = 'End the impersonation session before using admin tools.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'impersonating', 'detail' => $detail], 403);
            }

            abort(403, $detail);
        }

        // Developers have every super-admin power.
        if (!$user->hasAdminAccess()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'forbidden',
                    'detail' => 'Super-admin privileges are required.',
                ], 403);
            }

            abort(403, 'Super-admin privileges are required.');
        }

        return $next($request);
    }
}
