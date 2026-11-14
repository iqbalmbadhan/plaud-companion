<?php

$rootDir = function_exists('base_path') ? base_path() : dirname(__DIR__);
$publicDir = function_exists('public_path') ? public_path() : $rootDir . DIRECTORY_SEPARATOR . 'public';
$storageDir = function_exists('storage_path') ? storage_path() : $rootDir . DIRECTORY_SEPARATOR . 'storage';
$configDir = function_exists('config_path') ? config_path() : __DIR__;
$envFn = function_exists('env') ? 'env' : static fn (string $k, mixed $d = null): mixed => $_ENV[$k] ?? $d;

$keyFile = $configDir . DIRECTORY_SEPARATOR . 'updater-public.key';
$envKey = $envFn('UPDATER_PUBLIC_KEY', '');

return [
    /*
    |--------------------------------------------------------------------------
    | Signed Updater Paths & Limits
    |--------------------------------------------------------------------------
    */
    'root' => $rootDir,
    'public' => $publicDir,
    'work' => $storageDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'updates',
    'previous' => $storageDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'updates',

    // Empty by default until developer generates ed25519 keypair outside repo.
    // Production reads config/updater-public.key or UPDATER_PUBLIC_KEY env.
    'public_key' => $envKey ?: (
        is_file($keyFile)
            ? trim((string) file_get_contents($keyFile))
            : ''
    ),
    'public_key_file' => $keyFile,

    // Folders swapped wholesale on a full update
    'code_dirs' => [
        'app',
        'bootstrap',
        'config',
        'database',
        'lang',
        'resources',
        'routes',
        'vendor',
    ],

    // Single root files allowed in a release zip
    'root_files' => [
        'artisan',
        'composer.json',
        'composer.lock',
        'VERSION',
    ],

    // Public sub-folders swapped wholesale (built assets)
    'public_dirs' => [
        'assets',
        'build',
    ],

    // Never accepted from any release zip (case-insensitive normalized check)
    // Exception: config/updater-public.key is explicitly allowed.
    'forbidden' => [
        '/^\.env(\..+)?$/i',
        '/^storage(\/|$)/i',
        '/^bootstrap\/cache(\/|$)/i',
        '/^\.git(\/|$)/i',
        '/^auth\.json$/i',
        '/^install\.php$/i',
        '/^public\/index\.php$/i',
        '/^public\/\.htaccess$/i',
        '/^\.htaccess$/i',
        '/^\.user\.ini$/i',
        '/^.*install[-_]?token.*$/i',
        '/^(?!vendor\/).*\.(sql|pem|key|bak)$/i',
    ],

    'public_key_exception' => 'config/updater-public.key',

    // Zip-bomb & safety limits
    'max_zip_mb' => 150,
    'max_unpacked_mb' => 450,
    'max_single_file_mb' => 50,
    'max_entries' => 25000,
    'max_compression_ratio' => 100,

    // Keep exactly one previous copy for manual rollback
    'keep_previous' => 1,

    // Run database migrations in-process via Artisan::call for 100% stability on web/FPM hosts
    'fresh_process' => false,

    // Optional public update feed (no tokens, HTTPS + SSRF-guarded)
    'feed_url' => $envFn('UPDATER_FEED_URL', ''),
    'feed_enabled' => (bool) $envFn('UPDATER_FEED_ENABLED', false),
];
