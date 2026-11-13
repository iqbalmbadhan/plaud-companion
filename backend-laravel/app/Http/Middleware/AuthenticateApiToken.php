<?php

namespace App\Http\Middleware;

use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if (!$bearer) {
            return response()->json(['detail' => 'Authentication credentials were not provided.'], 401);
        }

        $user = $this->tokens->resolveUserFromBearer($bearer);
        if (!$user) {
            return response()->json(['detail' => 'Invalid or expired authentication token.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['detail' => 'Account is suspended or pending verification.'], 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
