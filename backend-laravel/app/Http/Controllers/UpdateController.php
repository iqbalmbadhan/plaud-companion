<?php

namespace App\Http\Controllers;

use App\Services\Updater;
use App\Support\Update\Package;
use App\Support\Update\UpdateException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PDO;
use Throwable;
use ZipArchive;

class UpdateController extends Controller
{
    public function show(Request $request): View
    {
        if ($request->user()?->is_super_admin !== true) {
            abort(403, 'Super-admin privileges are required.');
        }

        return view('settings.updates', [
            'phpLimits' => $this->phpUploadLimits(),
            'maxZipMb' => (int) config('updater.max_zip_mb', 150),
            'updaterEnabled' => Package::enabled(),
        ]);
    }

    public function status(Request $request, Updater $updater): JsonResponse
    {
        $stagedPath = $this->stagedZipPath();
        $stagedInfo = null;

        if (is_file($stagedPath)) {
            $stagedInfo = [
                'exists' => true,
                'size_bytes' => (int) filesize($stagedPath),
                'sha256' => hash_file('sha256', $stagedPath) ?: null,
                'modified_at' => gmdate('c', (int) filemtime($stagedPath)),
            ];

            if (Package::enabled()) {
                try {
                    $pkg = Package::open($stagedPath);
                    $stagedInfo['verified'] = true;
                    $stagedInfo['version'] = $pkg->version;
                    $stagedInfo['kind'] = $pkg->kind;
                    $stagedInfo['min_version'] = $pkg->minVersion;
                    $stagedInfo['built_at'] = $pkg->builtAt;
                    $stagedInfo['file_count'] = count($pkg->files);
                } catch (UpdateException $e) {
                    $stagedInfo['verified'] = false;
                    $stagedInfo['error'] = $e->errorCode;
                    $stagedInfo['error_message'] = $e->getMessage();
                }
            } else {
                $stagedInfo['verified'] = false;
                $stagedInfo['error'] = 'update_disabled';
                $stagedInfo['error_message'] = 'Updater public key is not configured.';
            }
        }

        return response()->json([
            'enabled' => Package::enabled(),
            'current_version' => $this->currentVersion(),
            'status' => $updater->summary(),
            'previous' => $updater->previous(),
            'staged_upload' => $stagedInfo,
            'last_backup' => $this->latestBackupInfo(),
            'php_limits' => $this->phpUploadLimits(),
            'max_zip_mb' => (int) config('updater.max_zip_mb', 150),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $maxZipBytes = ((int) config('updater.max_zip_mb', 150)) * 1024 * 1024;
        $workDir = $this->workDir();
        $this->ensureDir($workDir);

        // Chunked upload path
        if ($request->hasFile('chunk')) {
            $validated = $request->validate([
                'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
                'chunk_index' => ['required', 'integer', 'min:0', 'max:10000'],
                'total_chunks' => ['required', 'integer', 'min:1', 'max:10000'],
                'total_size' => ['required', 'integer', 'min:1', 'max:' . $maxZipBytes],
                'chunk' => ['required', 'file'],
            ]);

            $uploadId = (string) $validated['upload_id'];
            $chunkIndex = (int) $validated['chunk_index'];
            $totalChunks = (int) $validated['total_chunks'];
            $totalSize = (int) $validated['total_size'];

            if ($chunkIndex >= $totalChunks) {
                return response()->json([
                    'error' => 'invalid_chunk_index',
                    'detail' => 'chunk_index must be less than total_chunks.',
                ], 422);
            }

            $chunkDir = $workDir . DIRECTORY_SEPARATOR . 'chunks' . DIRECTORY_SEPARATOR . $uploadId;
            $this->ensureDir($chunkDir);

            $chunkFile = $request->file('chunk');
            $chunkFile->move($chunkDir, sprintf('%05d.part', $chunkIndex));

            // Check if all chunks have arrived
            $received = 0;
            $accumulatedBytes = 0;
            for ($i = 0; $i < $totalChunks; $i++) {
                $partPath = $chunkDir . DIRECTORY_SEPARATOR . sprintf('%05d.part', $i);
                if (is_file($partPath)) {
                    $received++;
                    $accumulatedBytes += (int) filesize($partPath);
                }
            }

            if ($received < $totalChunks) {
                return response()->json([
                    'ok' => true,
                    'complete' => false,
                    'received_chunks' => $received,
                    'total_chunks' => $totalChunks,
                ]);
            }

            if ($accumulatedBytes > $maxZipBytes || $accumulatedBytes !== $totalSize) {
                $this->removeDirectory($chunkDir);
                return response()->json([
                    'error' => 'update_size_mismatch',
                    'detail' => 'Assembled upload size does not match declared total_size or exceeds limit.',
                ], 422);
            }

            $tmpAssembled = $workDir . DIRECTORY_SEPARATOR . 'staged.zip.tmp.' . bin2hex(random_bytes(4));
            $outHandle = fopen($tmpAssembled, 'wb');
            if ($outHandle === false) {
                return response()->json(['error' => 'io_error', 'detail' => 'Unable to assemble chunks.'], 500);
            }

            try {
                for ($i = 0; $i < $totalChunks; $i++) {
                    $partPath = $chunkDir . DIRECTORY_SEPARATOR . sprintf('%05d.part', $i);
                    $inHandle = fopen($partPath, 'rb');
                    if ($inHandle === false) {
                        throw new \RuntimeException("Failed to read chunk {$i}.");
                    }
                    stream_copy_to_stream($inHandle, $outHandle);
                    fclose($inHandle);
                }
            } finally {
                fclose($outHandle);
                $this->removeDirectory($chunkDir);
            }

            return $this->finalizeStagedUpload($request, $tmpAssembled);
        }

        // Single-file upload path
        $request->validate([
            'file' => ['required', 'file', 'max:' . ((int) config('updater.max_zip_mb', 150) * 1024)],
        ]);

        $uploaded = $request->file('file');
        $tmpPath = $workDir . DIRECTORY_SEPARATOR . 'staged.zip.tmp.' . bin2hex(random_bytes(4));
        $uploaded->move($workDir, basename($tmpPath));

        return $this->finalizeStagedUpload($request, $tmpPath);
    }

    public function apply(Request $request, Updater $updater): JsonResponse
    {
        if (!Package::enabled()) {
            return response()->json([
                'error' => 'update_disabled',
                'detail' => 'Updater public key is not configured.',
            ], 503);
        }

        $validated = $request->validate([
            'confirmation' => ['required', 'string'],
            'allow_downgrade' => ['sometimes', 'boolean'],
        ]);

        if (trim((string) $validated['confirmation']) !== 'APPLY UPDATE') {
            return response()->json([
                'error' => 'confirmation_required',
                'detail' => 'Typed confirmation must exactly match "APPLY UPDATE".',
            ], 422);
        }

        $stagedPath = $this->stagedZipPath();
        if (!is_file($stagedPath)) {
            return response()->json([
                'error' => 'update_missing_package',
                'detail' => 'No staged update package found. Upload a signed release zip first.',
            ], 422);
        }

        $allowDowngrade = (bool) ($validated['allow_downgrade'] ?? false);

        try {
            $result = $updater->apply($stagedPath, $allowDowngrade);

            $this->recordAuditEvent($request, 'update.applied', 'system_update', $result['version'] ?? null, [
                'from' => $result['from'] ?? null,
                'to' => $result['version'] ?? null,
                'allow_downgrade' => $allowDowngrade,
            ]);

            return response()->json([
                'ok' => true,
                'result' => $result,
            ]);
        } catch (UpdateException $e) {
            $this->recordAuditEvent($request, 'update.apply_failed', 'system_update', null, [
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->errorCode,
                'detail' => $e->getMessage(),
                'params' => $e->params,
            ], $e->httpStatus);
        }
    }

    public function rollback(Request $request, Updater $updater): JsonResponse
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        if (trim((string) $validated['confirmation']) !== 'ROLLBACK') {
            return response()->json([
                'error' => 'confirmation_required',
                'detail' => 'Typed confirmation must exactly match "ROLLBACK".',
            ], 422);
        }

        try {
            $result = $updater->rollback();

            $this->recordAuditEvent($request, 'update.rolled_back', 'system_update', $result['version'] ?? null, [
                'status' => $result['state'] ?? 'ok',
            ]);

            return response()->json([
                'ok' => true,
                'result' => $result,
            ]);
        } catch (UpdateException $e) {
            $this->recordAuditEvent($request, 'update.rollback_failed', 'system_update', null, [
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->errorCode,
                'detail' => $e->getMessage(),
            ], $e->httpStatus);
        }
    }

    public function backup(Request $request): JsonResponse
    {
        $backupDir = $this->backupDir();
        $this->ensureDir($backupDir);

        $timestamp = gmdate('Ymd-His');
        $filename = "backup-{$timestamp}.zip";
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $filename;
        $tmpBackup = $backupPath . '.tmp.' . bin2hex(random_bytes(4));

        try {
            $this->buildBackupArchive($tmpBackup);
            if (!@rename($tmpBackup, $backupPath)) {
                throw new \RuntimeException('Failed to finalize backup archive.');
            }
        } catch (Throwable $e) {
            @unlink($tmpBackup);
            $this->recordAuditEvent($request, 'system.backup_failed', 'system_backup', null, [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'backup_failed',
                'detail' => 'Backup failed: ' . $e->getMessage(),
            ], 500);
        }

        $sizeBytes = (int) filesize($backupPath);
        $sha256 = hash_file('sha256', $backupPath) ?: null;

        $this->recordAuditEvent($request, 'system.backup_created', 'system_backup', $filename, [
            'filename' => $filename,
            'size_bytes' => $sizeBytes,
            'sha256' => $sha256,
        ]);

        return response()->json([
            'ok' => true,
            'backup' => [
                'filename' => $filename,
                'size_bytes' => $sizeBytes,
                'sha256' => $sha256,
                'created_at' => gmdate('c'),
            ],
        ]);
    }

    public function deleteUpload(Request $request): JsonResponse
    {
        $stagedPath = $this->stagedZipPath();
        $deleted = false;

        if (is_file($stagedPath)) {
            $deleted = @unlink($stagedPath);
        }

        $chunksDir = $this->workDir() . DIRECTORY_SEPARATOR . 'chunks';
        if (is_dir($chunksDir)) {
            $this->removeDirectory($chunksDir);
        }

        $this->recordAuditEvent($request, 'update.staged_deleted', 'system_update', null, [
            'deleted' => $deleted,
        ]);

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }

    private function finalizeStagedUpload(Request $request, string $tmpPath): JsonResponse
    {
        $stagedPath = $this->stagedZipPath();
        if (!@rename($tmpPath, $stagedPath)) {
            @unlink($tmpPath);
            return response()->json(['error' => 'io_error', 'detail' => 'Failed to stage uploaded zip.'], 500);
        }

        $sizeBytes = (int) filesize($stagedPath);
        $sha256 = hash_file('sha256', $stagedPath) ?: null;
        $meta = [
            'size_bytes' => $sizeBytes,
            'sha256' => $sha256,
        ];

        if (Package::enabled()) {
            try {
                $pkg = Package::open($stagedPath);
                $meta['verified'] = true;
                $meta['version'] = $pkg->version;
                $meta['kind'] = $pkg->kind;
                $meta['min_version'] = $pkg->minVersion;
                $meta['built_at'] = $pkg->builtAt;
                $meta['file_count'] = count($pkg->files);
            } catch (UpdateException $e) {
                @unlink($stagedPath);
                $this->recordAuditEvent($request, 'update.upload_rejected', 'system_update', null, [
                    'error_code' => $e->errorCode,
                    'size_bytes' => $sizeBytes,
                    'sha256' => $sha256,
                ]);

                return response()->json([
                    'ok' => false,
                    'error' => $e->errorCode,
                    'detail' => $e->getMessage(),
                ], $e->httpStatus);
            }
        } else {
            $meta['verified'] = false;
        }

        $this->recordAuditEvent($request, 'update.package_uploaded', 'system_update', $meta['version'] ?? null, $meta);

        return response()->json([
            'ok' => true,
            'complete' => true,
            'staged_upload' => $meta,
        ]);
    }

    private function buildBackupArchive(string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not open backup archive for writing.');
        }

        try {
            $metadata = [
                'created_at' => gmdate('c'),
                'version' => $this->currentVersion(),
                'db_connection' => (string) config('database.default'),
            ];
            $zip->addFromString('backup-meta.json', (string) json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $versionFile = base_path('VERSION');
            if (is_file($versionFile)) {
                $zip->addFile($versionFile, 'VERSION');
            }

            $lockFile = storage_path('app/installed.lock');
            if (is_file($lockFile)) {
                $zip->addFile($lockFile, 'installed.lock');
            }

            $connectionName = (string) config('database.default');
            if ($connectionName === 'sqlite') {
                $sqlitePath = (string) config('database.connections.sqlite.database');
                if ($sqlitePath !== ':memory:' && is_file($sqlitePath)) {
                    $zip->addFile($sqlitePath, 'database/database.sqlite');
                }
            } elseif ($connectionName === 'mysql') {
                $sqlDump = $this->dumpMysqlViaPdo();
                $zip->addFromString('database/dump.sql', $sqlDump);
            }
        } finally {
            $zip->close();
        }
    }

    private function dumpMysqlViaPdo(): string
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $lines = [
            '-- PLAUD Companion MySQL Backup',
            '-- Generated: ' . gmdate('c'),
            'SET FOREIGN_KEY_CHECKS=0;',
            '',
        ];

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $quotedTable = '`' . str_replace('`', '``', (string) $table) . '`';
            $createRow = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_NUM);
            if (is_array($createRow) && isset($createRow[1])) {
                $lines[] = "DROP TABLE IF EXISTS {$quotedTable};";
                $lines[] = $createRow[1] . ';';
                $lines[] = '';
            }

            $stmt = $pdo->query("SELECT * FROM {$quotedTable}");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_map(
                    static fn (string $c): string => '`' . str_replace('`', '``', $c) . '`',
                    array_keys($row)
                );
                $vals = array_map(
                    static fn (mixed $v): string => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    array_values($row)
                );
                $lines[] = sprintf(
                    'INSERT INTO %s (%s) VALUES (%s);',
                    $quotedTable,
                    implode(', ', $cols),
                    implode(', ', $vals)
                );
            }
            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function recordAuditEvent(
        Request $request,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        array $metadata = []
    ): void {
        try {
            DB::table('audit_events')->insert([
                'id' => Str::uuid()->toString(),
                'workspace_id' => null,
                'actor_user_id' => $request->user()?->id,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Do not fail update/rollback response if audit table is temporarily unavailable.
        }
    }

    private function workDir(): string
    {
        return (string) config('updater.work', storage_path('app/private/updates'));
    }

    private function stagedZipPath(): string
    {
        return $this->workDir() . DIRECTORY_SEPARATOR . 'staged.zip';
    }

    private function backupDir(): string
    {
        return $this->workDir() . DIRECTORY_SEPARATOR . 'backups';
    }

    private function currentVersion(): string
    {
        $file = base_path('VERSION');
        return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0.0';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestBackupInfo(): ?array
    {
        $dir = $this->backupDir();
        if (!is_dir($dir)) {
            return null;
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . 'backup-*.zip') ?: [];
        if ($files === []) {
            return null;
        }
        rsort($files, SORT_STRING);
        $latest = $files[0];

        return [
            'filename' => basename($latest),
            'size_bytes' => (int) filesize($latest),
            'created_at' => gmdate('c', (int) filemtime($latest)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function phpUploadLimits(): array
    {
        $uploadMax = (string) ini_get('upload_max_filesize');
        $postMax = (string) ini_get('post_max_size');

        return [
            'upload_max_filesize' => $uploadMax,
            'upload_max_bytes' => $this->parseIniBytes($uploadMax),
            'post_max_size' => $postMax,
            'post_max_bytes' => $this->parseIniBytes($postMax),
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_execution_time' => (int) ini_get('max_execution_time'),
        ];
    }

    private function parseIniBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '' || $val === '-1') {
            return PHP_INT_MAX;
        }
        $unit = strtolower(substr($val, -1));
        $num = (int) $val;
        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $val,
        };
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($dir);
    }
}
