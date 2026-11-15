<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if ($user !== null) {
            $freshUser = \App\Models\User::query()->find($user->id);
            if ($freshUser === null) {
                $user = null;
            } else {
                $user = $freshUser;
            }
        }

        if ($user === null) {
            if ($request->expectsJson() || $request->is('app/api/*')) {
                return response()->json(['detail' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        if ($user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('app/api/*')) {
                return response()->json([
                    'detail' => 'Your account has been suspended by an administrator. Please contact support.',
                ], 403);
            }

            return redirect()->route('login')->with('suspended', 'Your account has been suspended by an administrator. Please contact support.');
        }

        return $next($request);
    }
}
