<?php

namespace App\Support\Update;

use Closure;

class MigrationRunner
{
    public function __construct(
        private readonly string $rootPath,
        private readonly bool $freshProcess = true,
        private readonly ?Closure $processRunner = null,
        private readonly ?Closure $inProcessRunner = null,
    ) {
    }

    public function canUseProcOpen(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $disabled = (string) ini_get('disable_functions');
        if ($disabled === '') {
            return true;
        }
        $list = array_map('trim', explode(',', strtolower($disabled)));
        return !in_array('proc_open', $list, true);
    }

    /**
     * Run database migrations either in a fresh PHP process or in-process Artisan fallback.
     *
     * @return array{ran: bool, mode: string, exit_code: int, output: string}
     */
    public function run(): array
    {
        if ($this->freshProcess && $this->canUseProcOpen()) {
            return $this->runFreshProcess();
        }

        return $this->runInProcess();
    }

    public static function resolveCliBinary(): ?string
    {
        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            $base = strtolower(basename(PHP_BINARY));
            if (!str_contains($base, 'fpm') && !str_contains($base, 'cgi')) {
                return PHP_BINARY;
            }
        }

        $candidates = ['php', '/usr/bin/php', '/usr/local/bin/php'];
        if (defined('PHP_VERSION')) {
            $short = substr(PHP_VERSION, 0, 3);
            $candidates[] = "/usr/bin/php{$short}";
        }

        foreach ($candidates as $cand) {
            if ($cand === 'php') {
                return 'php';
            }
            if (is_executable($cand)) {
                return $cand;
            }
        }

        return null;
    }

    private function runFreshProcess(): array
    {
        if ($this->processRunner !== null) {
            $res = ($this->processRunner)($this->rootPath);
            $exitCode = (int) ($res['exit_code'] ?? 1);
            $output = (string) ($res['output'] ?? '');
            if ($exitCode !== 0) {
                return $this->runInProcess();
            }
            return [
                'ran' => true,
                'mode' => 'proc_open',
                'exit_code' => 0,
                'output' => $output,
            ];
        }

        $artisan = $this->rootPath . DIRECTORY_SEPARATOR . 'artisan';
        if (!is_file($artisan)) {
            return $this->runInProcess();
        }

        $phpBin = self::resolveCliBinary();
        if ($phpBin === null || str_contains(strtolower($phpBin), 'fpm')) {
            return $this->runInProcess();
        }

        $cmd = [$phpBin, $artisan, 'migrate', '--force', '--no-interaction'];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmd, $descriptors, $pipes, $this->rootPath);
        if (!is_resource($proc)) {
            // If proc_open failed at runtime (e.g., restricted host), fall back to in-process Artisan.
            return $this->runInProcess();
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);
        $combined = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));

        if ($exitCode !== 0) {
            // If sub-process exited with non-zero (e.g. php-fpm usage error), fall back to in-process Artisan
            return $this->runInProcess();
        }

        return [
            'ran' => true,
            'mode' => 'proc_open',
            'exit_code' => 0,
            'output' => $combined,
        ];
    }

    private function runInProcess(): array
    {
        if ($this->inProcessRunner !== null) {
            $res = ($this->inProcessRunner)($this->rootPath);
            $exitCode = (int) ($res['exit_code'] ?? 1);
            $output = (string) ($res['output'] ?? '');
            if ($exitCode !== 0) {
                throw new UpdateException('update_migrate_failed', "Database migration failed (exit {$exitCode}): {$output}");
            }
            return [
                'ran' => true,
                'mode' => 'in_process',
                'exit_code' => 0,
                'output' => $output,
            ];
        }

        $artisanClass = 'Illuminate\\Support\\Facades\\Artisan';
        if (!class_exists($artisanClass)) {
            throw new UpdateException('update_migrate_failed', 'Artisan facade is unavailable for in-process migration.');
        }

        try {
            $exitCode = (int) $artisanClass::call('migrate', [
                '--force' => true,
                '--no-interaction' => true,
            ]);
            $output = trim((string) $artisanClass::output());
        } catch (\Throwable $e) {
            throw new UpdateException('update_migrate_failed', 'In-process migration threw: ' . $e->getMessage(), [], 422, $e);
        }

        if ($exitCode !== 0) {
            throw new UpdateException('update_migrate_failed', "Database migration failed (exit {$exitCode}): {$output}");
        }

        return [
            'ran' => true,
            'mode' => 'in_process',
            'exit_code' => 0,
            'output' => $output,
        ];
    }
}
