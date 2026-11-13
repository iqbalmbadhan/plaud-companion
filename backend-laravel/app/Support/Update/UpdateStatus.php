<?php

namespace App\Support\Update;

class UpdateStatus
{
    private const VALID_STATES = ['idle', 'running', 'ok', 'failed'];

    public function __construct(
        private readonly string $workDir,
    ) {
    }

    public function workDir(): string
    {
        return $this->workDir;
    }

    public function statusPath(): string
    {
        return $this->workDir . DIRECTORY_SEPARATOR . 'status.json';
    }

    public function lastPath(): string
    {
        return $this->workDir . DIRECTORY_SEPARATOR . 'last.json';
    }

    public function lockPath(): string
    {
        return $this->workDir . DIRECTORY_SEPARATOR . 'update.lock';
    }

    public static function defaultStatus(): array
    {
        return [
            'state' => 'idle',
            'step' => null,
            'from' => null,
            'version' => null,
            'error' => null,
            'params' => [],
            'rolled_back' => false,
            'migrations_ran' => false,
            'started_at' => null,
            'finished_at' => null,
            'summary' => null,
        ];
    }

    public function read(): array
    {
        $path = $this->statusPath();
        if (!is_file($path)) {
            return self::defaultStatus();
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return self::defaultStatus();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::defaultStatus();
        }
        return array_merge(self::defaultStatus(), $decoded);
    }

    public function write(array $status): array
    {
        $merged = array_merge(self::defaultStatus(), $status);
        $state = (string) ($merged['state'] ?? 'idle');
        if (!in_array($state, self::VALID_STATES, true)) {
            throw new UpdateException('update_invalid_state', "Invalid update state: {$state}");
        }
        $this->writeJsonAtomic($this->statusPath(), $merged);
        return $merged;
    }

    public function patch(array $fields): array
    {
        return $this->write(array_merge($this->read(), $fields));
    }

    public function readLast(): ?array
    {
        $path = $this->lastPath();
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function writeLast(array $data): void
    {
        $this->writeJsonAtomic($this->lastPath(), $data);
    }

    public function writeJsonAtomic(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new UpdateException('update_not_writable', "Cannot create directory: {$dir}");
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new UpdateException('update_io_failed', 'Failed to encode JSON payload.');
        }

        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            throw new UpdateException('update_not_writable', "Cannot open temp file for atomic write: {$tmp}");
        }

        try {
            $written = fwrite($fh, $json);
            fflush($fh);
            fclose($fh);
            $fh = null;

            if ($written === false || $written < strlen($json)) {
                @unlink($tmp);
                throw new UpdateException('update_io_failed', "Incomplete write to temp file: {$tmp}");
            }

            if (!$this->atomicRename($tmp, $path)) {
                @unlink($tmp);
                throw new UpdateException('update_io_failed', "Failed to atomically rename {$tmp} to {$path}");
            }
        } finally {
            if (is_resource($fh)) {
                @fclose($fh);
            }
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Acquire a non-blocking exclusive lock for update/rollback operations.
     *
     * @return resource
     */
    public function acquireLock()
    {
        if (!is_dir($this->workDir) && !@mkdir($this->workDir, 0775, true) && !is_dir($this->workDir)) {
            throw new UpdateException('update_not_writable', "Cannot create lock directory: {$this->workDir}");
        }

        $lockFile = $this->lockPath();
        $fh = @fopen($lockFile, 'c+');
        if ($fh === false) {
            throw new UpdateException('update_not_writable', "Cannot open lock file: {$lockFile}");
        }

        if (!@flock($fh, LOCK_EX | LOCK_NB)) {
            @fclose($fh);
            throw new UpdateException('update_locked', 'Another update or rollback is already in progress.', [], 409);
        }

        @ftruncate($fh, 0);
        @fwrite($fh, json_encode([
            'pid' => getmypid() ?: null,
            'acquired_at' => gmdate('c'),
        ]));
        @fflush($fh);

        return $fh;
    }

    /**
     * Release a lock handle previously returned by acquireLock().
     *
     * @param resource $handle
     */
    public function releaseLock($handle): void
    {
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    public function withLock(callable $fn): mixed
    {
        $handle = $this->acquireLock();
        try {
            return $fn();
        } finally {
            $this->releaseLock($handle);
        }
    }

    public function isLocked(): bool
    {
        $lockFile = $this->lockPath();
        if (!is_file($lockFile)) {
            return false;
        }
        $fh = @fopen($lockFile, 'c+');
        if ($fh === false) {
            return false;
        }
        $locked = !@flock($fh, LOCK_EX | LOCK_NB);
        if (!$locked) {
            @flock($fh, LOCK_UN);
        }
        @fclose($fh);
        return $locked;
    }

    private function atomicRename(string $tmp, string $target): bool
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if (@rename($tmp, $target)) {
                return true;
            }
            usleep(10000 * ($attempt + 1));
        }
        return false;
    }
}
