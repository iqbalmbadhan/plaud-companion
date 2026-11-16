<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Portable, passphrase-encrypted backup of client data, and restore.
 *
 * Protects client data at rest (AGENTS.md "cryptographic security"): the whole archive —
 * database rows, private files, and the app's encryption key — is sealed with AES-256-GCM
 * under a key derived from the admin's passphrase (PBKDF2-SHA256). Without the passphrase
 * the file is unreadable, so it is safe to store off-site.
 *
 * The backup is engine-neutral (row-level JSON), so a MySQL backup restores onto a fresh
 * install and vice-versa. The passphrase is never stored or logged.
 */
class DataBackupService
{
    public const MAGIC = 'PLAUDBK2';
    private const KDF_ITERATIONS = 200000;

    /** Tables we never back up: transient/session state that should not travel between installs. */
    private const SKIP_TABLES = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions'];

    public function backupDir(): string
    {
        $dir = storage_path('app/private/backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return $dir;
    }

    /**
     * Builds an encrypted backup file and returns its absolute path.
     * @return array{path: string, filename: string, size_bytes: int, tables: int, files: int}
     */
    public function create(string $passphrase): array
    {
        $this->assertPassphrase($passphrase);

        $tmpZip = tempnam(sys_get_temp_dir(), 'plaudbk_');
        try {
            [$tableCount, $fileCount] = $this->buildPayloadZip($tmpZip);
            $plaintext = (string) file_get_contents($tmpZip);
        } finally {
            @unlink($tmpZip);
        }

        $salt = random_bytes(16);
        $nonce = random_bytes(12);
        $key = hash_pbkdf2('sha256', $passphrase, $salt, self::KDF_ITERATIONS, 32, true);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        $header = [
            'magic' => self::MAGIC,
            'created_at' => gmdate('c'),
            'app_version' => (string) config('app.version', '1.0.0'),
            'kdf' => ['algo' => 'pbkdf2-sha256', 'iterations' => self::KDF_ITERATIONS, 'salt' => base64_encode($salt)],
            'cipher' => 'aes-256-gcm',
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'tables' => $tableCount,
            'files' => $fileCount,
        ];

        $filename = 'plaud-backup-' . gmdate('Ymd-His') . '.plaudbackup';
        $path = $this->backupDir() . DIRECTORY_SEPARATOR . $filename;
        $out = json_encode($header, JSON_UNESCAPED_SLASHES) . "\n" . $cipher;
        if (file_put_contents($path, $out, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the backup file.');
        }
        @chmod($path, 0600);

        return ['path' => $path, 'filename' => $filename, 'size_bytes' => (int) filesize($path), 'tables' => $tableCount, 'files' => $fileCount];
    }

    /** Reads the unencrypted header of a backup file (no passphrase needed). */
    public function inspect(string $path): array
    {
        $header = $this->readHeader($path);

        return [
            'created_at' => $header['created_at'] ?? null,
            'app_version' => $header['app_version'] ?? null,
            'tables' => $header['tables'] ?? null,
            'files' => $header['files'] ?? null,
        ];
    }

    /**
     * Restores a backup. Wrong passphrase or a tampered file is detected by the GCM tag and rejected.
     *
     * @param bool $restoreAppKey When true, also adopt the backup's encryption key (needed when restoring
     *                            onto a different install, or the restored encrypted data cannot be read).
     * @return array{tables: int, rows: int, files: int, app_key_matched: bool, app_key_restored: bool}
     */
    public function restore(string $path, string $passphrase, bool $restoreAppKey = false): array
    {
        $header = $this->readHeader($path);
        $raw = (string) file_get_contents($path);
        $cipher = substr($raw, strpos($raw, "\n") + 1);

        $key = hash_pbkdf2('sha256', $passphrase, base64_decode($header['kdf']['salt']), (int) $header['kdf']['iterations'], 32, true);
        $plaintext = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            base64_decode($header['nonce']),
            base64_decode($header['tag'])
        );
        if ($plaintext === false) {
            throw new BackupPassphraseException();
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'plaudrs_');
        try {
            file_put_contents($tmpZip, $plaintext);
            $zip = new ZipArchive();
            if ($zip->open($tmpZip) !== true) {
                throw new RuntimeException('The backup archive is unreadable.');
            }
            try {
                $database = json_decode((string) $zip->getFromName('database.json'), true, 512, JSON_THROW_ON_ERROR);
                $backupKey = (string) $zip->getFromName('app_key.txt');

                $rows = $this->restoreDatabase($database);
                $files = $this->restoreFiles($zip);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($tmpZip);
        }

        $currentKey = (string) config('app.key');
        $matched = $backupKey !== '' && hash_equals($currentKey, $backupKey);
        $restored = false;
        if (!$matched && $restoreAppKey && $backupKey !== '') {
            $this->adoptAppKey($backupKey);
            $restored = true;
        }

        return [
            'tables' => count($database),
            'rows' => $rows,
            'files' => $files,
            'app_key_matched' => $matched,
            'app_key_restored' => $restored,
        ];
    }

    // ------------------------------------------------------------------ internals

    /** @return array{0:int,1:int} [tableCount, fileCount] */
    private function buildPayloadZip(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        $database = [];
        $tableCount = 0;
        foreach ($this->tables() as $table) {
            $database[$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
            $tableCount++;
        }
        $zip->addFromString('database.json', (string) json_encode($database, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('app_key.txt', (string) config('app.key'));
        $zip->addFromString('manifest.json', (string) json_encode([
            'created_at' => gmdate('c'),
            'app_version' => (string) config('app.version', '1.0.0'),
            'db_driver' => DB::connection()->getDriverName(),
        ], JSON_PRETTY_PRINT));

        $fileCount = 0;
        $disk = Storage::disk('local');
        foreach (DB::table('transcript_files')->pluck('storage_key') as $storageKey) {
            $storageKey = (string) $storageKey;
            if ($storageKey !== '' && $disk->exists($storageKey)) {
                $zip->addFromString('files/' . $storageKey, (string) $disk->get($storageKey));
                $fileCount++;
            }
        }

        $zip->close();

        return [$tableCount, $fileCount];
    }

    /**
     * Base tables in the current database only. Schema::getTableListing() spans every schema on a
     * shared MySQL server (phpmyadmin, mysql, …), so we query the active database explicitly.
     * @return list<string>
     */
    private function tables(): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            $names = DB::table('information_schema.tables')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_type', 'BASE TABLE')
                ->pluck('TABLE_NAME')
                ->all();
        } else {
            $names = DB::table('sqlite_master')->where('type', 'table')->pluck('name')->all();
        }

        $tables = [];
        foreach ($names as $name) {
            $name = (string) $name;
            if (!in_array($name, self::SKIP_TABLES, true) && !str_starts_with($name, 'sqlite_')) {
                $tables[] = $name;
            }
        }
        sort($tables);

        return $tables;
    }

    /** @param array<string, list<array<string,mixed>>> $database */
    private function restoreDatabase(array $database): int
    {
        $driver = DB::connection()->getDriverName();
        $rows = 0;

        DB::transaction(function () use ($database, $driver, &$rows) {
            // Suspend foreign-key enforcement for the span of the restore. SQLite's
            // defer_foreign_keys works inside a transaction (the plain pragma does not) and
            // checks at commit, by when every row has been reinserted.
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA defer_foreign_keys = ON');
            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            foreach (array_keys($database) as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
            foreach ($database as $table => $records) {
                if (!Schema::hasTable($table)) {
                    continue;
                }
                foreach (array_chunk($records, 200) as $chunk) {
                    if ($chunk !== []) {
                        DB::table($table)->insert($chunk);
                        $rows += count($chunk);
                    }
                }
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        return $rows;
    }

    private function restoreFiles(ZipArchive $zip): int
    {
        $disk = Storage::disk('local');
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (!str_starts_with($name, 'files/') || str_ends_with($name, '/')) {
                continue;
            }
            $storageKey = substr($name, strlen('files/'));
            if (str_contains($storageKey, '..')) {
                continue; // path traversal guard
            }
            $disk->put($storageKey, (string) $zip->getFromIndex($i));
            $count++;
        }

        return $count;
    }

    /** Writes APP_KEY into .env and the running config, so restored encrypted data stays readable. */
    private function adoptAppKey(string $key): void
    {
        config(['app.key' => $key]);
        $envPath = base_path('.env');
        if (is_file($envPath) && is_writable($envPath)) {
            $env = (string) file_get_contents($envPath);
            $line = 'APP_KEY=' . $key;
            $env = preg_match('/^APP_KEY=.*$/m', $env)
                ? preg_replace('/^APP_KEY=.*$/m', $line, $env)
                : $env . "\n" . $line . "\n";
            file_put_contents($envPath, $env, LOCK_EX);
        }
    }

    private function readHeader(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Backup file not found.');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open the backup file.');
        }
        $firstLine = (string) fgets($handle, 1024 * 64);
        fclose($handle);

        $header = json_decode(trim($firstLine), true);
        if (!is_array($header) || ($header['magic'] ?? null) !== self::MAGIC) {
            throw new RuntimeException('This file is not a PLAUD Companion backup.');
        }

        return $header;
    }

    private function assertPassphrase(string $passphrase): void
    {
        if (mb_strlen($passphrase) < 10) {
            throw new RuntimeException('The backup passphrase must be at least 10 characters.');
        }
    }
}
