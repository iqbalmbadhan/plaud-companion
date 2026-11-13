<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks /install routes once storage/app/installed.lock exists.
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_file(storage_path('app/installed.lock'))) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'already_installed',
                    'detail' => 'Application is already installed.',
                ], 404);
            }

            abort(404);
        }

        return $next($request);
    }
}
