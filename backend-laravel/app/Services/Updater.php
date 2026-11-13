<?php

namespace App\Services;

use App\Support\Update\MigrationRunner;
use App\Support\Update\Package;
use App\Support\Update\UpdateException;
use App\Support\Update\UpdateStatus;
use Closure;
use ZipArchive;

class Updater
{
    private readonly array $config;
    private readonly string $root;
    private readonly string $publicDir;
    private readonly string $workDir;
    private readonly string $previousDir;
    private readonly UpdateStatus $status;
    private readonly MigrationRunner $migrationRunner;
    private readonly ?Closure $safetyCallback;
    private readonly ?Closure $maintenanceCallback;
    private readonly ?Closure $swapHook;

    public function __construct(
        ?array $config = null,
        ?UpdateStatus $status = null,
        ?MigrationRunner $migrationRunner = null,
        ?Closure $safetyCallback = null,
        ?Closure $maintenanceCallback = null,
        ?Closure $swapHook = null,
    ) {
        $this->config = $this->resolveConfig($config);
        $this->root = rtrim((string) ($this->config['root'] ?? dirname(__DIR__, 2)), '/\\');
        $this->publicDir = rtrim((string) ($this->config['public'] ?? ($this->root . DIRECTORY_SEPARATOR . 'public')), '/\\');
        $this->workDir = rtrim((string) ($this->config['work'] ?? ($this->root . DIRECTORY_SEPARATOR . 'storage/app/private/updates')), '/\\');
        $this->previousDir = rtrim((string) ($this->config['previous'] ?? ($this->root . DIRECTORY_SEPARATOR . 'storage/app/updates')), '/\\');

        $this->status = $status ?? new UpdateStatus($this->workDir);
        $this->migrationRunner = $migrationRunner ?? new MigrationRunner(
            $this->root,
            (bool) ($this->config['fresh_process'] ?? true),
        );
        $this->safetyCallback = $safetyCallback;
        $this->maintenanceCallback = $maintenanceCallback;
        $this->swapHook = $swapHook;
    }

    public function apply(string $zipPath, bool $allowDowngrade = false): array
    {
        return $this->status->withLock(function () use ($zipPath, $allowDowngrade): array {
            $startedAt = gmdate('c');
            $fromVersion = $this->currentVersion();
            $runId = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));
            $stageDir = $this->workDir . DIRECTORY_SEPARATOR . 'stage' . DIRECTORY_SEPARATOR . $runId;

            $this->status->write([
                'state' => 'running',
                'step' => 'verify',
                'from' => $fromVersion,
                'version' => null,
                'error' => null,
                'params' => ['allow_downgrade' => $allowDowngrade],
                'rolled_back' => false,
                'migrations_ran' => false,
                'started_at' => $startedAt,
                'finished_at' => null,
                'summary' => null,
            ]);

            $inMaintenance = false;
            $swapStarted = false;
            $journal = [];
            $backupDir = '';
            $staleBackupDir = null;
            $pkg = null;

            try {
                // 1. VERIFY
                $pkg = Package::open($zipPath, $this->config);

                $this->status->patch([
                    'version' => $pkg->version,
                    'params' => [
                        'allow_downgrade' => $allowDowngrade,
                        'kind' => $pkg->kind,
                        'min_version' => $pkg->minVersion,
                    ],
                ]);

                if (!$allowDowngrade && version_compare($pkg->version, $fromVersion, '<=')) {
                    throw new UpdateException(
                        'update_downgrade_refused',
                        "Target version {$pkg->version} is not newer than current version {$fromVersion}."
                    );
                }

                if ($pkg->kind === 'partial' && $pkg->minVersion !== null) {
                    if (version_compare($fromVersion, $pkg->minVersion, '<')) {
                        throw new UpdateException(
                            'update_min_version',
                            "Current version {$fromVersion} is below required minimum {$pkg->minVersion}."
                        );
                    }
                }

                $this->assertTargetsWritable($pkg);
                $this->assertDiskSpace($zipPath);

                // 2. SAFETY
                $this->status->patch(['step' => 'safety']);
                if ($this->safetyCallback !== null) {
                    ($this->safetyCallback)($pkg, $fromVersion);
                }

                // 3. STAGE
                $this->status->patch(['step' => 'stage']);
                $this->stagePackage($pkg, $stageDir);

                // 4. ARTISAN DOWN + PRELOAD
                $this->status->patch(['step' => 'down']);
                $this->setMaintenance(true);
                $inMaintenance = true;
                $this->preloadUpdaterClasses();

                // 5. JOURNALED SWAP
                $this->status->patch(['step' => 'swap']);
                $backupDir = $this->previousDir . DIRECTORY_SEPARATOR . $fromVersion;
                if (is_dir($backupDir)) {
                    $staleBackupDir = $backupDir . '.old.' . $runId;
                    $this->movePath($backupDir, $staleBackupDir);
                }
                $this->ensureDir($backupDir);

                $swapStarted = true;
                $journal = $this->performJournaledSwap($pkg, $stageDir, $backupDir, $fromVersion);

                if ($this->swapHook !== null) {
                    ($this->swapHook)('after_swap');
                }

                // 6. MIGRATE
                $this->status->patch(['step' => 'migrate']);
                $migResult = $this->migrationRunner->run();
                $migrationsRan = (bool) ($migResult['ran'] ?? false);

                // 7. FINISH
                $this->status->patch(['step' => 'finish']);
                $this->clearFrameworkCaches();
                $this->removeDirectory($stageDir);
                if (is_file($zipPath)) {
                    @unlink($zipPath);
                }
                if ($staleBackupDir !== null && is_dir($staleBackupDir)) {
                    $this->removeDirectory($staleBackupDir);
                }
                $this->prunePrevious((int) ($this->config['keep_previous'] ?? 1));

                $this->setMaintenance(false);
                $inMaintenance = false;

                $finishedAt = gmdate('c');
                $summaryText = sprintf(
                    'Updated from %s to %s (%s, %d files)',
                    $fromVersion,
                    $pkg->version,
                    $pkg->kind,
                    count($pkg->files)
                );

                $this->status->writeLast([
                    'from' => $fromVersion,
                    'version' => $pkg->version,
                    'kind' => $pkg->kind,
                    'migrations_ran' => $migrationsRan,
                    'rolled_back' => false,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'summary' => $summaryText,
                ]);

                return $this->status->write([
                    'state' => 'ok',
                    'step' => 'done',
                    'from' => $fromVersion,
                    'version' => $pkg->version,
                    'error' => null,
                    'params' => [
                        'allow_downgrade' => $allowDowngrade,
                        'kind' => $pkg->kind,
                    ],
                    'rolled_back' => false,
                    'migrations_ran' => $migrationsRan,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'summary' => $summaryText,
                ]);
            } catch (\Throwable $e) {
                $this->removeDirectory($stageDir);

                $updateEx = $e instanceof UpdateException
                    ? $e
                    : new UpdateException('update_swap_failed', $e->getMessage(), [], 422, $e);

                if (!$swapStarted) {
                    if ($inMaintenance) {
                        try {
                            $this->setMaintenance(false);
                        } catch (\Throwable) {
                            // Best-effort exit maintenance on pre-swap failure
                        }
                    }

                    $this->status->write([
                        'state' => 'failed',
                        'step' => $this->status->read()['step'],
                        'from' => $fromVersion,
                        'version' => $pkg?->version,
                        'error' => [
                            'code' => $updateEx->errorCode,
                            'message' => $updateEx->getMessage(),
                        ],
                        'params' => ['allow_downgrade' => $allowDowngrade],
                        'rolled_back' => false,
                        'migrations_ran' => false,
                        'started_at' => $startedAt,
                        'finished_at' => gmdate('c'),
                        'summary' => 'Update failed before swap: ' . $updateEx->getMessage(),
                    ]);

                    throw $updateEx;
                }

                // Failure during or after swap -> attempt automatic journal revert
                try {
                    if ($this->swapHook !== null) {
                        ($this->swapHook)('before_revert');
                    }
                    $this->revertJournal($journal, $backupDir);
                    if ($staleBackupDir !== null && is_dir($staleBackupDir) && !is_dir($backupDir)) {
                        $this->movePath($staleBackupDir, $backupDir);
                    }
                    $this->clearFrameworkCaches();
                    if ($inMaintenance) {
                        $this->setMaintenance(false);
                    }

                    $this->status->write([
                        'state' => 'failed',
                        'step' => 'rolled_back',
                        'from' => $fromVersion,
                        'version' => $pkg?->version,
                        'error' => [
                            'code' => $updateEx->errorCode,
                            'message' => $updateEx->getMessage(),
                        ],
                        'params' => ['allow_downgrade' => $allowDowngrade],
                        'rolled_back' => true,
                        'migrations_ran' => false,
                        'started_at' => $startedAt,
                        'finished_at' => gmdate('c'),
                        'summary' => 'Update failed and was automatically rolled back: ' . $updateEx->getMessage(),
                    ]);

                    throw $updateEx;
                } catch (\Throwable $revertErr) {
                    if ($revertErr === $updateEx) {
                        throw $updateEx;
                    }
                    // Rollback itself failed -> stay in maintenance mode and mark rolled_back = false
                    $this->status->write([
                        'state' => 'failed',
                        'step' => 'rollback_failed',
                        'from' => $fromVersion,
                        'version' => $pkg?->version,
                        'error' => [
                            'code' => 'update_rollback_failed',
                            'message' => 'Update failed (' . $updateEx->getMessage() . ') and rollback failed: ' . $revertErr->getMessage(),
                        ],
                        'params' => ['allow_downgrade' => $allowDowngrade],
                        'rolled_back' => false,
                        'migrations_ran' => false,
                        'started_at' => $startedAt,
                        'finished_at' => gmdate('c'),
                        'summary' => 'CRITICAL: Update and automatic rollback both failed; staying in maintenance mode.',
                    ]);

                    throw new UpdateException(
                        'update_rollback_failed',
                        'Rollback failed after update error: ' . $revertErr->getMessage(),
                        [],
                        500,
                        $revertErr
                    );
                }
            }
        });
    }

    public function rollback(): array
    {
        return $this->status->withLock(function (): array {
            $prev = $this->previous();
            if ($prev === null) {
                throw new UpdateException('update_no_previous', 'No previous version backup is available for rollback.', [], 404);
            }

            $backupDir = $prev['path'];
            $journalFile = $backupDir . DIRECTORY_SEPARATOR . '.updater-journal.json';
            if (!is_file($journalFile)) {
                throw new UpdateException('update_no_previous', 'Previous backup is missing its rollback journal.', [], 422);
            }

            $journal = json_decode((string) file_get_contents($journalFile), true);
            if (!is_array($journal) || !isset($journal['entries']) || !is_array($journal['entries'])) {
                throw new UpdateException('update_no_previous', 'Rollback journal is corrupted.', [], 422);
            }

            $startedAt = gmdate('c');
            $currentVersion = $this->currentVersion();
            $targetVersion = (string) ($journal['from'] ?? $prev['version']);

            $this->status->write([
                'state' => 'running',
                'step' => 'rollback',
                'from' => $currentVersion,
                'version' => $targetVersion,
                'error' => null,
                'params' => ['manual_rollback' => true],
                'rolled_back' => false,
                'migrations_ran' => false,
                'started_at' => $startedAt,
                'finished_at' => null,
                'summary' => null,
            ]);

            $this->setMaintenance(true);
            $this->preloadUpdaterClasses();

            try {
                $this->revertJournal($journal, $backupDir);
                $this->clearFrameworkCaches();
                $this->setMaintenance(false);

                $finishedAt = gmdate('c');
                $summaryText = "Manually rolled back from {$currentVersion} to {$targetVersion}";

                $this->status->writeLast([
                    'from' => $currentVersion,
                    'version' => $targetVersion,
                    'kind' => 'rollback',
                    'migrations_ran' => false,
                    'rolled_back' => true,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'summary' => $summaryText,
                ]);

                return $this->status->write([
                    'state' => 'ok',
                    'step' => 'done',
                    'from' => $currentVersion,
                    'version' => $targetVersion,
                    'error' => null,
                    'params' => ['manual_rollback' => true],
                    'rolled_back' => true,
                    'migrations_ran' => false,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'summary' => $summaryText,
                ]);
            } catch (\Throwable $e) {
                $this->status->write([
                    'state' => 'failed',
                    'step' => 'rollback_failed',
                    'from' => $currentVersion,
                    'version' => $targetVersion,
                    'error' => [
                        'code' => 'update_rollback_failed',
                        'message' => $e->getMessage(),
                    ],
                    'params' => ['manual_rollback' => true],
                    'rolled_back' => false,
                    'migrations_ran' => false,
                    'started_at' => $startedAt,
                    'finished_at' => gmdate('c'),
                    'summary' => 'Manual rollback failed; staying in maintenance mode.',
                ]);

                throw new UpdateException('update_rollback_failed', 'Manual rollback failed: ' . $e->getMessage(), [], 500, $e);
            }
        });
    }

    public function summary(): array
    {
        return [
            'enabled' => Package::enabled($this->config),
            'current_version' => $this->currentVersion(),
            'locked' => $this->status->isLocked(),
            'status' => $this->status->read(),
            'last' => $this->status->readLast(),
            'previous' => $this->previous(),
        ];
    }

    public function previous(): ?array
    {
        if (!is_dir($this->previousDir)) {
            return null;
        }
        $dirs = [];
        foreach (scandir($this->previousDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_contains($entry, '.old.')) {
                continue;
            }
            $full = $this->previousDir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full) && is_file($full . DIRECTORY_SEPARATOR . '.updater-journal.json')) {
                $dirs[] = [
                    'version' => $entry,
                    'path' => $full,
                    'mtime' => @filemtime($full) ?: 0,
                ];
            }
        }
        if ($dirs === []) {
            return null;
        }
        usort($dirs, fn (array $a, array $b) => strcmp($b['version'], $a['version']) ?: ($b['mtime'] <=> $a['mtime']));
        return $dirs[0];
    }

    public function cleanup(): void
    {
        if ($this->status->isLocked()) {
            return;
        }
        $stageBase = $this->workDir . DIRECTORY_SEPARATOR . 'stage';
        if (is_dir($stageBase)) {
            foreach (scandir($stageBase) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $p = $stageBase . DIRECTORY_SEPARATOR . $item;
                is_dir($p) ? $this->removeDirectory($p) : @unlink($p);
            }
        }
        foreach (glob($this->workDir . DIRECTORY_SEPARATOR . 'upload_*.zip') ?: [] as $zip) {
            @unlink($zip);
        }
        foreach (glob($this->workDir . DIRECTORY_SEPARATOR . '*.tmp.*') ?: [] as $tmp) {
            @unlink($tmp);
        }
    }

    public function currentVersion(): string
    {
        $file = $this->root . DIRECTORY_SEPARATOR . 'VERSION';
        if (!is_file($file)) {
            return '0000.00.00.0000';
        }
        $v = trim((string) @file_get_contents($file));
        return $v !== '' ? $v : '0000.00.00.0000';
    }

    private function assertTargetsWritable(Package $pkg): void
    {
        $this->ensureDir($this->workDir);
        $this->ensureDir($this->previousDir);
        if (!$this->isPathWritable($this->workDir) || !$this->isPathWritable($this->previousDir)) {
            throw new UpdateException('update_not_writable', 'Updater work or previous directory is not writable.');
        }

        if (!$this->isPathWritable($this->root)) {
            throw new UpdateException('update_not_writable', 'Application root directory is not writable.');
        }

        $pathsToCheck = array_merge(array_keys($pkg->files), $pkg->removed);
        foreach ($pathsToCheck as $rel) {
            $full = $this->resolveTargetPath($rel);
            if (file_exists($full) && !$this->isPathWritable($full)) {
                throw new UpdateException('update_not_writable', "Target path is not writable: {$rel}");
            }
            $probe = dirname($full);
            while (!file_exists($probe)) {
                $parent = dirname($probe);
                if ($parent === $probe) {
                    break;
                }
                $probe = $parent;
            }
            if (!$this->isPathWritable($probe)) {
                throw new UpdateException('update_not_writable', "Parent directory is not writable for: {$rel}");
            }
        }
    }

    private function isPathWritable(string $path): bool
    {
        if (is_writable($path)) {
            return true;
        }
        if (DIRECTORY_SEPARATOR !== '\\') {
            return false;
        }
        if (is_dir($path)) {
            $probe = $path . DIRECTORY_SEPARATOR . '.write_probe_' . bin2hex(random_bytes(3));
            $fh = @fopen($probe, 'xb');
            if ($fh !== false) {
                fclose($fh);
                @unlink($probe);
                return true;
            }
            return false;
        }
        if (is_file($path)) {
            $fh = @fopen($path, 'r+');
            if ($fh !== false) {
                fclose($fh);
                return true;
            }
            return false;
        }
        return false;
    }

    private function assertDiskSpace(string $zipPath): void
    {
        $zipSize = filesize($zipPath);
        if ($zipSize === false || $zipSize <= 0) {
            throw new UpdateException('update_bad_zip', 'Release zip is empty or unreadable.');
        }
        $free = @disk_free_space($this->root);
        if ($free === false || $free < ($zipSize * 2)) {
            throw new UpdateException(
                'update_low_disk',
                'Insufficient free disk space (requires at least 2x the release archive size).'
            );
        }
    }

    private function stagePackage(Package $pkg, string $stageDir): void
    {
        $this->ensureDir($stageDir);
        $zip = new ZipArchive();
        if ($zip->open($pkg->zipPath) !== true) {
            throw new UpdateException('update_bad_zip', 'Failed to open release zip for staging.');
        }

        try {
            foreach ($pkg->files as $rel => $meta) {
                $dest = $stageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                $this->ensureDir(dirname($dest));

                $stream = $zip->getStream($rel);
                if ($stream === false) {
                    throw new UpdateException('update_bad_zip', "Unable to extract entry: {$rel}");
                }
                $out = @fopen($dest, 'wb');
                if ($out === false) {
                    fclose($stream);
                    throw new UpdateException('update_not_writable', "Unable to write staged file: {$rel}");
                }
                try {
                    while (!feof($stream)) {
                        $buf = fread($stream, 65536);
                        if ($buf === false) {
                            throw new UpdateException('update_bad_zip', "Read error while staging: {$rel}");
                        }
                        if ($buf !== '' && fwrite($out, $buf) === false) {
                            throw new UpdateException('update_io_failed', "Write error while staging: {$rel}");
                        }
                    }
                } finally {
                    fclose($out);
                    fclose($stream);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function performJournaledSwap(Package $pkg, string $stageDir, string $backupDir, string $fromVersion): array
    {
        $entries = [];

        if ($pkg->kind === 'full') {
            $units = $this->collectFullSwapUnits($pkg, $stageDir);
            foreach ($units as $rel) {
                $live = $this->resolveTargetPath($rel);
                $staged = $stageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                $backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

                $hadLive = file_exists($live);
                $hadStaged = file_exists($staged);

                if ($hadLive) {
                    $this->movePath($live, $backup);
                }
                if ($hadStaged) {
                    $this->movePath($staged, $live);
                }

                $entries[] = [
                    'rel' => $rel,
                    'had_live' => $hadLive,
                    'had_staged' => $hadStaged,
                ];
            }
        } else {
            // Partial update: only listed files + removed files; vendor and unlisted files stay untouched.
            foreach (array_keys($pkg->files) as $rel) {
                $live = $this->resolveTargetPath($rel);
                $staged = $stageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                $backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

                $hadLive = is_file($live);
                if ($hadLive) {
                    $this->movePath($live, $backup);
                }
                $this->movePath($staged, $live);

                $entries[] = [
                    'rel' => $rel,
                    'had_live' => $hadLive,
                    'had_staged' => true,
                ];
            }

            foreach ($pkg->removed as $rel) {
                $live = $this->resolveTargetPath($rel);
                $backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_file($live)) {
                    $this->movePath($live, $backup);
                    $entries[] = [
                        'rel' => $rel,
                        'had_live' => true,
                        'had_staged' => false,
                    ];
                }
            }
        }

        $journal = [
            'kind' => $pkg->kind,
            'from' => $fromVersion,
            'to' => $pkg->version,
            'created_at' => gmdate('c'),
            'entries' => $entries,
        ];

        $this->status->writeJsonAtomic($backupDir . DIRECTORY_SEPARATOR . '.updater-journal.json', $journal);
        return $journal;
    }

    private function collectFullSwapUnits(Package $pkg, string $stageDir): array
    {
        $units = [];

        foreach ((array) ($this->config['code_dirs'] ?? []) as $dir) {
            if ($dir === 'bootstrap') {
                // Swap children of bootstrap/ individually so bootstrap/cache/ is preserved untouched.
                $children = [];
                foreach ([$this->root . DIRECTORY_SEPARATOR . 'bootstrap', $stageDir . DIRECTORY_SEPARATOR . 'bootstrap'] as $base) {
                    if (is_dir($base)) {
                        foreach (scandir($base) ?: [] as $child) {
                            if ($child !== '.' && $child !== '..' && $child !== 'cache') {
                                $children['bootstrap/' . $child] = true;
                            }
                        }
                    }
                }
                foreach (array_keys($children) as $rel) {
                    $units[] = $rel;
                }
                continue;
            }
            $units[] = $dir;
        }

        foreach ((array) ($this->config['root_files'] ?? []) as $file) {
            $units[] = $file;
        }

        foreach ((array) ($this->config['public_dirs'] ?? []) as $pDir) {
            $units[] = 'public/' . $pDir;
        }

        // Single files directly under public/ present in package (e.g. public/favicon.ico)
        foreach (array_keys($pkg->files) as $rel) {
            if (str_starts_with($rel, 'public/') && substr_count($rel, '/') === 1) {
                $units[] = $rel;
            }
        }

        return array_values(array_unique($units));
    }

    private function revertJournal(array $journal, string $backupDir): void
    {
        $entries = (array) ($journal['entries'] ?? []);
        foreach (array_reverse($entries) as $entry) {
            $rel = (string) ($entry['rel'] ?? '');
            if ($rel === '') {
                continue;
            }
            $live = $this->resolveTargetPath($rel);
            $backup = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

            if (!empty($entry['had_staged']) && file_exists($live)) {
                is_dir($live) ? $this->removeDirectory($live) : @unlink($live);
            }
            if (!empty($entry['had_live']) && file_exists($backup)) {
                $this->movePath($backup, $live);
            }
        }

        $this->removeDirectory($backupDir);
    }

    private function resolveTargetPath(string $rel): string
    {
        if (str_starts_with($rel, 'public/')) {
            $sub = substr($rel, 7);
            return $this->publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
        }
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }

    private function movePath(string $src, string $dst): void
    {
        if (!file_exists($src)) {
            return;
        }
        $this->ensureDir(dirname($dst));

        // Rename-first with brief Windows retry for transient file locks
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if (@rename($src, $dst)) {
                return;
            }
            usleep(15000 * ($attempt + 1));
        }

        // Fallback: recursive copy + delete
        if (is_dir($src)) {
            $this->copyDirectory($src, $dst);
            $this->removeDirectory($src);
        } else {
            if (!@copy($src, $dst)) {
                throw new UpdateException('update_io_failed', "Failed to copy {$src} to {$dst}");
            }
            if (!@unlink($src)) {
                throw new UpdateException('update_io_failed', "Failed to remove source {$src} after copy");
            }
        }
    }

    private function copyDirectory(string $src, string $dst): void
    {
        $this->ensureDir($dst);
        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $item;
            $to = $dst . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from)) {
                $this->copyDirectory($from, $to);
            } else {
                if (!@copy($from, $to)) {
                    throw new UpdateException('update_io_failed', "Failed to copy {$from} to {$to}");
                }
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            if (is_file($dir)) {
                @unlink($dir);
            }
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new UpdateException('update_not_writable', "Cannot create directory: {$dir}");
        }
    }

    private function clearFrameworkCaches(): void
    {
        $cacheDir = $this->root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
                @unlink($file);
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function prunePrevious(int $keep): void
    {
        if ($keep < 0 || !is_dir($this->previousDir)) {
            return;
        }
        $dirs = [];
        foreach (scandir($this->previousDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $this->previousDir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                $dirs[] = ['name' => $entry, 'path' => $full, 'mtime' => @filemtime($full) ?: 0];
            }
        }
        usort($dirs, fn (array $a, array $b) => strcmp($b['name'], $a['name']) ?: ($b['mtime'] <=> $a['mtime']));
        foreach (array_slice($dirs, $keep) as $old) {
            $this->removeDirectory($old['path']);
        }
    }

    private function setMaintenance(bool $down): void
    {
        if ($this->maintenanceCallback !== null) {
            ($this->maintenanceCallback)($down);
            return;
        }

        $cmd = $down ? 'down' : 'up';
        $artisan = $this->root . DIRECTORY_SEPARATOR . 'artisan';
        if ($this->migrationRunner->canUseProcOpen() && is_file($artisan)) {
            $phpBin = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
            $proc = @proc_open(
                [$phpBin, $artisan, $cmd],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $this->root
            );
            if (is_resource($proc)) {
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                return;
            }
        }

        $artisanClass = 'Illuminate\\Support\\Facades\\Artisan';
        if (class_exists($artisanClass)) {
            $artisanClass::call($cmd);
        }
    }

    private function preloadUpdaterClasses(): void
    {
        class_exists(UpdateException::class);
        class_exists(UpdateStatus::class);
        class_exists(Package::class);
        class_exists(MigrationRunner::class);
        class_exists(self::class);
    }

    private function resolveConfig(?array $override): array
    {
        if ($override !== null) {
            return $override;
        }
        if (function_exists('config')) {
            $cfg = config('updater');
            if (is_array($cfg)) {
                return $cfg;
            }
        }
        return require __DIR__ . '/../../config/updater.php';
    }
}
