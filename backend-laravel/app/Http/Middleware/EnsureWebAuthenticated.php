<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!is_file(storage_path('app/installed.lock'))) {
            if ($request->expectsJson() || $request->is('app/api/*')) {
                return response()->json(['detail' => 'Application is not installed yet.'], 503);
            }

            return redirect()->route('install.show');
        }

        $user = $request->user();
        if ($user === null || $user->status !== 'active') {
            if ($request->expectsJson() || $request->is('app/api/*')) {
                return response()->json(['detail' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
