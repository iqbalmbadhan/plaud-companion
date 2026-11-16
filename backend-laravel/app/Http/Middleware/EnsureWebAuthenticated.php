<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\TokenService;
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

        // Impersonation: end it when time is up or the impersonated account was suspended.
        if (\App\Support\Impersonation::active() && (\App\Support\Impersonation::expired() || $user->status !== 'active')) {
            $why = \App\Support\Impersonation::expired() ? 'expired' : 'target_suspended';
            if (\App\Support\Impersonation::stop($request, $why)) {
                $notice = $why === 'expired'
                    ? 'Impersonation ended after ' . \App\Support\Impersonation::MINUTES . ' minutes. You are back in your developer account.'
                    : 'Impersonation ended because that account is suspended.';
                if ($request->expectsJson() || $request->is('app/api/*')) {
                    return response()->json(['detail' => $notice, 'impersonation_ended' => true], 409);
                }

                return redirect()->route('settings.users')->with('status', $notice);
            }

            return redirect()->route('login');
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

        // Sessions that signed in before a password change / "sign out everywhere" are ended.
        $tokens = app(TokenService::class);
        $authAt = $request->session()->get(TokenService::SESSION_AUTH_AT);
        if ($authAt === null && Auth::viaRemember()) {
            // A "remember me" login can only succeed with the current remember token, which is rotated on revocation.
            $tokens->markSessionAuthenticated($request);
            $authAt = now()->getTimestamp();
        }
        if ($user->sessions_valid_after !== null && ($authAt === null || !$tokens->issuedAfterRevocation($user, (int) $authAt))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $notice = 'You were signed out because your password was changed or an administrator ended your sessions. Please sign in again.';

            if ($request->expectsJson() || $request->is('app/api/*')) {
                return response()->json(['detail' => $notice], 401);
            }

            return redirect()->route('login')->with('status', $notice);
        }

        return $next($request);
    }
}
