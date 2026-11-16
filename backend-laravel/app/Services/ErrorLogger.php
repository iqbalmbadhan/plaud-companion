<?php

namespace App\Services;

use App\Models\ErrorLog;
use App\Support\Impersonation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Developer debug log. Records enough to diagnose a problem (what failed, where, for whom)
 * without storing transcript text, request bodies, SQL bindings, tokens or secrets.
 */
class ErrorLogger
{
    public const RETENTION_DAYS = 30;

    public const REQUEST_ATTRIBUTE = 'error_reference';

    public function recordException(Throwable $e): ?string
    {
        $request = app()->bound('request') ? request() : null;

        return $this->store([
            'source' => 'server',
            'exception_class' => get_class($e),
            // SQL errors can echo bound values (transcript text), so keep only the SQLSTATE code.
            'message' => $e instanceof QueryException
                ? 'Database query failed (SQLSTATE ' . $e->getCode() . ').'
                : self::sanitize($e->getMessage()),
            'location' => Str::limit(self::relativePath($e->getFile()) . ':' . $e->getLine(), 250, ''),
            'status_code' => 500,
            'context' => ['route' => $request?->route()?->getName()],
        ], $request);
    }

    /** @param array{message: string, url?: ?string, line?: ?int, column?: ?int, stack?: ?string} $data */
    public function recordBrowser(array $data, Request $request): ?string
    {
        $path = parse_url((string) ($data['url'] ?? ''), PHP_URL_PATH) ?: null;

        return $this->store([
            'source' => 'browser',
            'exception_class' => null,
            'message' => self::sanitize($data['message']),
            'location' => $path ? Str::limit($path . ':' . (int) ($data['line'] ?? 0) . ':' . (int) ($data['column'] ?? 0), 250, '') : null,
            'status_code' => null,
            'context' => ['stack' => self::sanitize((string) ($data['stack'] ?? ''), 2000)],
        ], $request);
    }

    public static function sanitize(string $text, int $limit = 500): string
    {
        $text = preg_replace('/(Bearer\s+)[A-Za-z0-9_\-\.=]+/i', '$1[REDACTED]', $text) ?? $text;
        $text = preg_replace('/(key=|token=|password=|secret=)[^&\s"\']+/i', '$1[REDACTED]', $text) ?? $text;
        $text = preg_replace('/(x-(?:goog-)?api-key:\s*)\S+/i', '$1[REDACTED]', $text) ?? $text;
        $text = preg_replace('/\b(sk|sk-or|sk-ant)-[A-Za-z0-9_\-]{8,}/i', '[REDACTED]', $text) ?? $text;
        $text = preg_replace('/eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/', '[REDACTED_JWT]', $text) ?? $text;

        return Str::limit(trim($text), $limit, '…');
    }

    /** @param array<string, mixed> $fields */
    private function store(array $fields, ?Request $request): ?string
    {
        try {
            $reference = 'ERR-' . strtoupper(Str::random(8));
            ErrorLog::create(array_merge($fields, [
                'id' => (string) Str::uuid(),
                'reference' => $reference,
                'level' => 'error',
                'method' => $request?->method(),
                'path' => $request ? Str::limit('/' . ltrim($request->path(), '/'), 250, '') : null,
                'user_id' => $request?->user()?->id,
                'impersonator_user_id' => Impersonation::impersonatorId(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
                'created_at' => now(),
            ]));
            $request?->attributes->set(self::REQUEST_ATTRIBUTE, $reference);

            if (random_int(1, 50) === 1) {
                ErrorLog::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
            }

            return $reference;
        } catch (Throwable) {
            // Never let the error logger cause a second failure (e.g. table not migrated yet).
            return null;
        }
    }

    private static function relativePath(string $file): string
    {
        $base = rtrim(base_path(), '/\\');

        return str_starts_with($file, $base) ? ltrim(substr($file, strlen($base)), '/\\') : basename($file);
    }
}
