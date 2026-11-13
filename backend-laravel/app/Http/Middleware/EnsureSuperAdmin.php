<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows ONLY users with is_super_admin === true.
 * Workspace admins, editors, reviewers, members, and guests are rejected with 403.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_super_admin !== true) {
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
