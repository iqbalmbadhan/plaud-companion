<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureNotInstalled;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureWebAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$basePath = dirname(__DIR__);
if (!is_file($basePath . '/storage/app/installed.lock')) {
    foreach ([
        '/storage/app/private',
        '/storage/app/public',
        '/storage/app/updates',
        '/storage/framework/cache/data',
        '/storage/framework/sessions',
        '/storage/framework/views',
        '/storage/logs',
        '/bootstrap/cache',
    ] as $relDir) {
        if (!is_dir($basePath . $relDir)) {
            @mkdir($basePath . $relDir, 0775, true);
        }
    }

    $envFile = $basePath . '/.env';
    $envContents = is_file($envFile) ? (string) @file_get_contents($envFile) : '';
    $hasEnvAppKey = $envContents !== '' && preg_match('/^APP_KEY=base64:/m', $envContents) === 1;

    if (!$hasEnvAppKey && empty($_ENV['APP_KEY']) && empty($_SERVER['APP_KEY'])) {
        $installKeyFile = $basePath . '/storage/framework/.install_app_key';
        $installKey = is_file($installKeyFile) ? trim((string) @file_get_contents($installKeyFile)) : '';
        if (!str_starts_with($installKey, 'base64:')) {
            $installKey = 'base64:' . base64_encode(random_bytes(32));
            @file_put_contents($installKeyFile, $installKey, LOCK_EX);
        }
        $_ENV['APP_KEY'] = $installKey;
        $_SERVER['APP_KEY'] = $installKey;
        @putenv('APP_KEY=' . $installKey);
    }

    foreach (['SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'SESSION_COOKIE' => 'plaud_install_session'] as $k => $v) {
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
        @putenv("{$k}={$v}");
    }
}

return Application::configure(basePath: $basePath)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'auth.token' => AuthenticateApiToken::class,
            'web.auth' => EnsureWebAuthenticated::class,
            'not_installed' => EnsureNotInstalled::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
