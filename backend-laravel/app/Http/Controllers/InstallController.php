<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PDO;
use Throwable;

class InstallController extends Controller
{
    public function show(Request $request)
    {
        return response()
            ->view('install.wizard', [
                'preflight' => $this->collectPreflight(),
                'defaults' => [
                    'app_name' => 'PLAUD Companion',
                    'app_url' => $request->getSchemeAndHttpHost(),
                    'db_connection' => 'mysql',
                    'db_host' => '127.0.0.1',
                    'db_port' => 3306,
                    'db_database' => 'plaud_companion',
                    'db_username' => 'plaud',
                ],
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function preflight(): JsonResponse
    {
        return response()->json($this->collectPreflight());
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $validated = $this->validateDatabaseInput($request);

        try {
            $serverVersion = $this->probeDatabaseConnection($validated);

            return response()->json([
                'ok' => true,
                'server_version' => $serverVersion,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Database connection failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function configureEnvironment(Request $request): JsonResponse
    {
        if (is_file(storage_path('app/installed.lock'))) {
            return response()->json(['error' => 'already_installed'], 404);
        }

        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'app_url' => ['required', 'url', 'max:255'],
            'db_connection' => ['required', 'in:mysql,sqlite'],
            'db_host' => ['required_if:db_connection,mysql', 'nullable', 'string', 'max:255'],
            'db_port' => ['required_if:db_connection,mysql', 'nullable', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required_if:db_connection,mysql', 'nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        // Verify database connectivity before writing .env
        try {
            $this->probeDatabaseConnection($validated);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Database connection failed: ' . $e->getMessage(),
            ], 422);
        }

        try {
            $this->writeEnvFile($validated);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Failed to write .env file: ' . $e->getMessage(),
            ], 500);
        }

        // Clear cached config if present so the next step picks up the new .env
        $cachedConfig = base_path('bootstrap/cache/config.php');
        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }

        return response()->json([
            'ok' => true,
            'message' => '.env configured with generated APP_KEY and JWT_SECRET_KEY.',
        ]);
    }

    public function install(Request $request): JsonResponse
    {
        if (is_file(storage_path('app/installed.lock'))) {
            return response()->json(['error' => 'already_installed'], 404);
        }

        $preflight = $this->collectPreflight();
        if (!$preflight['all_ok']) {
            return response()->json([
                'ok' => false,
                'error' => 'Preflight checks failed. Resolve requirements before installing.',
                'preflight' => $preflight,
            ], 422);
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'max:128'],
            'workspace_name' => ['nullable', 'string', 'min:2', 'max:100'],
        ]);

        $this->reloadDatabaseConfigFromEnv();

        try {
            Artisan::call('migrate', ['--force' => true]);
            $migrationOutput = trim(Artisan::output());
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Database migration failed: ' . $e->getMessage(),
            ], 500);
        }

        $now = gmdate('Y-m-d H:i:s');
        $userId = Str::uuid()->toString();
        $workspaceId = Str::uuid()->toString();
        $memberId = Str::uuid()->toString();
        $workspaceName = trim((string) ($validated['workspace_name'] ?? '')) ?: 'Default Workspace';
        $passwordHash = defined('PASSWORD_ARGON2ID')
            ? password_hash($validated['password'], PASSWORD_ARGON2ID)
            : Hash::make($validated['password']);

        try {
            DB::transaction(function () use (
                $userId,
                $workspaceId,
                $memberId,
                $workspaceName,
                $validated,
                $passwordHash,
                $now
            ): void {
                $existingUser = DB::table('users')
                    ->where('email', strtolower(trim($validated['email'])))
                    ->first();

                if ($existingUser !== null) {
                    throw new \RuntimeException('A user with that email already exists.');
                }

                DB::table('users')->insert([
                    'id' => $userId,
                    'email' => strtolower(trim($validated['email'])),
                    'display_name' => trim($validated['display_name']),
                    'password_hash' => $passwordHash,
                    'status' => 'active',
                    'is_super_admin' => true,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('workspaces')->insert([
                    'id' => $workspaceId,
                    'name' => $workspaceName,
                    'type' => 'team',
                    'owner_user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('workspace_members')->insert([
                    'id' => $memberId,
                    'workspace_id' => $workspaceId,
                    'user_id' => $userId,
                    'role' => 'owner',
                    'status' => 'active',
                    'joined_at' => $now,
                ]);
            });
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Failed to create super-admin account: ' . $e->getMessage(),
            ], 422);
        }

        // Write storage/app/installed.lock atomically
        $lockPath = storage_path('app/installed.lock');
        $lockDir = dirname($lockPath);
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }
        @unlink(storage_path('framework/.install_app_key'));

        $versionFile = base_path('VERSION');
        $version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '1.0.0';
        $lockPayload = json_encode([
            'installed_at' => gmdate('c'),
            'version' => $version,
            'super_admin_id' => $userId,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $tmpLock = $lockPath . '.tmp.' . bin2hex(random_bytes(4));
        file_put_contents($tmpLock, $lockPayload . "\n", LOCK_EX);
        @rename($tmpLock, $lockPath);

        return response()->json([
            'ok' => true,
            'message' => 'Installation complete. Installer routes are now locked.',
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'migration_output' => $migrationOutput,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectPreflight(): array
    {
        $phpOk = version_compare(PHP_VERSION, '8.3.0', '>=');

        $requiredExtensions = ['sodium', 'zip', 'pdo', 'pdo_mysql', 'mbstring', 'openssl'];
        $extensions = [];
        $extensionsOk = true;
        foreach ($requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            $extensions[$ext] = $loaded;
            if (!$loaded) {
                $extensionsOk = false;
            }
        }

        $writableTargets = [
            'storage' => storage_path(),
            'storage/app' => storage_path('app'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];
        $writable = [];
        $writableOk = true;
        foreach ($writableTargets as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $isWritable = is_dir($path) && is_writable($path);
            $writable[$label] = $isWritable;
            if (!$isWritable) {
                $writableOk = false;
            }
        }

        $disabledFunctions = array_map(
            'trim',
            explode(',', (string) ini_get('disable_functions'))
        );
        $procOpenEnabled = function_exists('proc_open') && !in_array('proc_open', $disabledFunctions, true);

        return [
            'php_version' => PHP_VERSION,
            'php_ok' => $phpOk,
            'extensions' => $extensions,
            'extensions_ok' => $extensionsOk,
            'writable' => $writable,
            'writable_ok' => $writableOk,
            'proc_open_enabled' => $procOpenEnabled,
            'upload_limits' => [
                'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'post_max_size' => (string) ini_get('post_max_size'),
                'memory_limit' => (string) ini_get('memory_limit'),
            ],
            'all_ok' => $phpOk && $extensionsOk && $writableOk,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDatabaseInput(Request $request): array
    {
        return $request->validate([
            'db_connection' => ['required', 'in:mysql,sqlite'],
            'db_host' => ['required_if:db_connection,mysql', 'nullable', 'string', 'max:255'],
            'db_port' => ['required_if:db_connection,mysql', 'nullable', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required_if:db_connection,mysql', 'nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function probeDatabaseConnection(array $cfg): string
    {
        if ($cfg['db_connection'] === 'sqlite') {
            $dbPath = (string) $cfg['db_database'];
            if ($dbPath !== ':memory:') {
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
            }
            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            return (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        }

        $host = (string) ($cfg['db_host'] ?? '127.0.0.1');
        $port = (int) ($cfg['db_port'] ?? 3306);
        $database = (string) $cfg['db_database'];
        $username = (string) ($cfg['db_username'] ?? '');
        $password = (string) ($cfg['db_password'] ?? '');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        return (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function writeEnvFile(array $validated): void
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        if (is_file($envPath)) {
            $content = (string) file_get_contents($envPath);
        } elseif (is_file($examplePath)) {
            $content = (string) file_get_contents($examplePath);
        } else {
            $content = '';
        }

        $existingAppKey = $this->readEnvValue($content, 'APP_KEY');
        if ($existingAppKey === '' || !str_starts_with($existingAppKey, 'base64:')) {
            $installKeyFile = storage_path('framework/.install_app_key');
            $bootstrapKey = is_file($installKeyFile) ? trim((string) @file_get_contents($installKeyFile)) : '';
            $appKey = str_starts_with($bootstrapKey, 'base64:')
                ? $bootstrapKey
                : 'base64:' . base64_encode(random_bytes(32));
        } else {
            $appKey = $existingAppKey;
        }

        $existingJwt = $this->readEnvValue($content, 'JWT_SECRET_KEY');
        $jwtSecret = ($existingJwt !== '' && !str_starts_with($existingJwt, 'replace_with_'))
            ? $existingJwt
            : bin2hex(random_bytes(32));

        $updates = [
            'APP_NAME' => (string) $validated['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim((string) $validated['app_url'], '/'),
            'DB_CONNECTION' => (string) $validated['db_connection'],
            'DB_HOST' => (string) ($validated['db_host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($validated['db_port'] ?? '3306'),
            'DB_DATABASE' => (string) $validated['db_database'],
            'DB_USERNAME' => (string) ($validated['db_username'] ?? ''),
            'DB_PASSWORD' => (string) ($validated['db_password'] ?? ''),
            'JWT_SECRET_KEY' => $jwtSecret,
        ];

        foreach ($updates as $key => $value) {
            $content = $this->upsertEnvLine($content, $key, $value);
        }

        $tmpEnv = $envPath . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmpEnv, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write temporary .env file.');
        }
        if (!@rename($tmpEnv, $envPath)) {
            @unlink($tmpEnv);
            throw new \RuntimeException('Unable to move .env file into place.');
        }
    }

    private function readEnvValue(string $content, string $key): string
    {
        if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $content, $m)) {
            return trim($m[1], " \t\n\r\0\x0B\"'");
        }
        return '';
    }

    private function upsertEnvLine(string $content, string $key, string $value): string
    {
        if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
            throw new \InvalidArgumentException("Invalid control character in {$key}.");
        }

        $formatted = $this->formatEnvValue($value);
        $line = "{$key}={$formatted}";
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content)) {
            return (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $content, 1);
        }

        $suffix = ($content !== '' && !str_ends_with($content, "\n")) ? "\n" : '';
        return $content . $suffix . $line . "\n";
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/[\s#"\'\\\\$]/', $value)) {
            return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
        }
        return $value;
    }

    private function reloadDatabaseConfigFromEnv(): void
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return;
        }
        $content = (string) file_get_contents($envPath);
        $conn = $this->readEnvValue($content, 'DB_CONNECTION') ?: 'mysql';
        Config::set('database.default', $conn);

        if ($conn === 'sqlite') {
            Config::set('database.connections.sqlite.database', $this->readEnvValue($content, 'DB_DATABASE'));
        } else {
            Config::set('database.connections.mysql.host', $this->readEnvValue($content, 'DB_HOST') ?: '127.0.0.1');
            Config::set('database.connections.mysql.port', $this->readEnvValue($content, 'DB_PORT') ?: '3306');
            Config::set('database.connections.mysql.database', $this->readEnvValue($content, 'DB_DATABASE'));
            Config::set('database.connections.mysql.username', $this->readEnvValue($content, 'DB_USERNAME'));
            Config::set('database.connections.mysql.password', $this->readEnvValue($content, 'DB_PASSWORD'));
        }

        DB::purge($conn);
    }
}
