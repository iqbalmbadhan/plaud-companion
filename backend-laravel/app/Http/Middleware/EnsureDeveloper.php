<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows ONLY developers (is_developer = true), and never while impersonating another user.
 */
class EnsureDeveloper
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $json = $request->expectsJson() || $request->is('api/*') || $request->is('*/api/*');

        if ($user === null) {
            return $json ? response()->json(['error' => 'unauthenticated', 'detail' => 'Sign in required.'], 401) : redirect()->guest(route('login'));
        }

        if (Impersonation::active()) {
            $detail = 'End the impersonation session before using developer tools.';

            return $json ? response()->json(['error' => 'impersonating', 'detail' => $detail], 403) : abort(403, $detail);
        }

        if (!$user->isDeveloper()) {
            return $json ? response()->json(['error' => 'forbidden', 'detail' => 'Developer access is required.'], 403) : abort(403, 'Developer access is required.');
        }

        return $next($request);
    }
}
