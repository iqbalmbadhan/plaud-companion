<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\PlaudAccountLink;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Connect my Plaud account": signs the user in to Plaud with OAuth (PKCE, dynamic
 * client registration) and imports their recordings through Plaud's hosted MCP server
 * (https://mcp.plaud.ai/mcp — tools list_files, get_transcript, get_note).
 *
 * Works with a normal Plaud app account; no partner approval required.
 * Tokens are stored encrypted and never logged. Transcript text is treated as data.
 */
class PlaudAccountService
{
    public const SOURCE = 'plaud';

    // ---------------------------------------------------------------- OAuth

    /** @return array{url: string, state: string, verifier: string} */
    public function authorizationRequest(string $redirectUri): array
    {
        $verifier = Str::random(64);
        $state = Str::random(40);
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId($redirectUri),
            'redirect_uri' => $redirectUri,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'state' => $state,
            'resource' => config('plaud.mcp_url'),
        ]);

        return ['url' => config('plaud.mcp_auth_base') . '/authorize?' . $query, 'state' => $state, 'verifier' => $verifier];
    }

    public function completeAuthorization(User $user, string $workspaceId, string $code, string $verifier, string $redirectUri): PlaudAccountLink
    {
        $tokens = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $this->clientId($redirectUri),
            'code_verifier' => $verifier,
            'resource' => config('plaud.mcp_url'),
        ]);

        $link = PlaudAccountLink::query()->firstOrNew(['user_id' => $user->id]);
        if (!$link->exists) {
            $link->id = (string) Str::uuid();
        }
        $link->fill([
            'workspace_id' => $workspaceId,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'status' => 'connected',
            'last_error' => null,
        ])->save();

        $this->audit($user->id, $workspaceId, 'integration.plaud_account_connected');

        return $link;
    }

    public function disconnect(User $user): void
    {
        $link = PlaudAccountLink::query()->where('user_id', $user->id)->first();
        if ($link !== null) {
            $this->audit($user->id, $link->workspace_id, 'integration.plaud_account_disconnected');
            $link->delete();
        }
    }

    private function clientId(string $redirectUri): string
    {
        $existing = DB::table('plaud_oauth_clients')->where('redirect_uri', $redirectUri)->value('client_id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $response = Http::timeout((int) config('plaud.timeout'))->acceptJson()->post(config('plaud.mcp_auth_base') . '/register', [
            'client_name' => 'PLAUD Companion',
            'redirect_uris' => [$redirectUri],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]);
        $clientId = $response->json('client_id');
        if (!$response->successful() || !is_string($clientId) || $clientId === '') {
            throw new RuntimeException("Plaud client registration failed (HTTP {$response->status()}).");
        }
        DB::table('plaud_oauth_clients')->insert([
            'redirect_uri' => $redirectUri, 'client_id' => $clientId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $clientId;
    }

    /** @return array<string, mixed> */
    private function tokenRequest(array $form): array
    {
        $response = Http::timeout((int) config('plaud.timeout'))->asForm()->acceptJson()
            ->post(config('plaud.mcp_auth_base') . '/token', $form);
        $token = $response->json('access_token');
        if (!$response->successful() || !is_string($token) || $token === '') {
            throw new RuntimeException("Plaud sign-in failed (HTTP {$response->status()}).");
        }

        return $response->json();
    }

    private function freshAccessToken(PlaudAccountLink $link): string
    {
        if ($link->expires_at === null || $link->expires_at->isAfter(now()->addMinute())) {
            return $link->access_token;
        }
        if (blank($link->refresh_token)) {
            throw new PlaudReconnectRequired();
        }
        try {
            $tokens = $this->tokenRequest([
                'grant_type' => 'refresh_token',
                'refresh_token' => $link->refresh_token,
                'client_id' => $this->clientId(route('plaud.account.callback')),
                'resource' => config('plaud.mcp_url'),
            ]);
        } catch (RuntimeException) {
            throw new PlaudReconnectRequired();
        }
        $link->fill([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $link->refresh_token,
            'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ])->save();

        return $link->access_token;
    }

    // ---------------------------------------------------------------- Sync

    /** Imports up to plaud.sync_batch new recordings. @return array{imported: int, remaining: bool, waiting: int} */
    public function sync(PlaudAccountLink $link): array
    {
        try {
            $mcp = new PlaudMcpSession(config('plaud.mcp_url'), $this->freshAccessToken($link));
            $files = $this->listFiles($mcp);

            $known = Transcript::withTrashed()
                ->where('workspace_id', $link->workspace_id)
                ->where('external_source', self::SOURCE)
                ->whereIn('external_id', array_map(fn ($f) => (string) $f['id'], $files))
                ->pluck('external_id')->all();

            $imported = 0;
            $waiting = 0;
            $batch = (int) config('plaud.sync_batch', 10);
            $pending = array_values(array_filter($files, fn ($f) => !in_array((string) $f['id'], $known, true)));
            foreach (array_slice($pending, 0, $batch) as $file) {
                if ($this->importFile($mcp, $link, $file)) {
                    $imported++;
                } else {
                    $waiting++; // Plaud has not finished transcribing yet; picked up next time.
                }
            }

            $link->fill([
                'status' => 'connected',
                'last_error' => null,
                'last_synced_at' => now(),
                'imported_count' => $link->imported_count + $imported,
            ])->save();

            return ['imported' => $imported, 'remaining' => count($pending) > $batch, 'waiting' => $waiting];
        } catch (PlaudReconnectRequired) {
            $link->fill(['status' => 'reconnect_needed', 'last_error' => 'Plaud sign-in expired. Please connect again.'])->save();
            throw new PlaudReconnectRequired();
        } catch (RuntimeException $e) {
            $link->fill(['last_error' => Str::limit($e->getMessage(), 250)])->save();
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> newest recordings first */
    private function listFiles(PlaudMcpSession $mcp): array
    {
        $result = $mcp->callJson('list_files', ['page' => 1, 'page_size' => 50]);
        $items = $result['data'] ?? $result['files'] ?? (array_is_list($result) ? $result : []);

        return array_values(array_filter($items, fn ($f) => is_array($f) && isset($f['id'])));
    }

    /** @param array<string, mixed> $file */
    private function importFile(PlaudMcpSession $mcp, PlaudAccountLink $link, array $file): bool
    {
        $fileId = (string) $file['id'];
        $segments = [];
        $cursor = null;
        for ($page = 0; $page < 40; $page++) {
            $args = ['file_id' => $fileId, 'limit' => 500] + ($cursor ? ['cursor' => $cursor] : []);
            $chunk = $mcp->callJson('get_transcript', $args, allowText: true);
            if (!is_array($chunk)) {
                break; // "not available yet" text
            }
            $rows = $chunk['segments'] ?? (array_is_list($chunk) ? $chunk : []);
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $segments[] = $row;
                }
            }
            $cursor = $chunk['next_cursor'] ?? null;
            if (!$cursor) {
                break;
            }
        }

        $normalized = $this->normalizeSegments($segments, (int) ($file['duration'] ?? 0));
        if ($normalized === []) {
            return false;
        }

        $notes = $mcp->callJson('get_note', ['file_id' => $fileId], allowText: true);
        $summary = is_array($notes) ? $this->notesToMarkdown($notes) : null;

        $content = implode("\n", array_map(fn ($s) => "{$s['speaker']}: {$s['text']}", $normalized));
        $title = trim((string) ($file['name'] ?? '')) ?: 'Plaud recording';

        DB::transaction(function () use ($link, $file, $fileId, $normalized, $content, $title, $summary) {
            $transcript = Transcript::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $link->workspace_id,
                'created_by_user_id' => $link->user_id,
                'title' => Str::limit($title, 250, ''),
                'description' => $this->describe($file, $normalized),
                'source_type' => 'plaud_account',
                'recorded_at' => $this->timestamp($file['start_at'] ?? $file['created_at'] ?? null),
                'language' => null,
                'status' => 'processed',
                'content_text' => $content,
                'content_hash' => hash('sha256', $content),
                'external_source' => self::SOURCE,
                'external_id' => $fileId,
                'summary_markdown' => $summary,
            ]);
            foreach ($normalized as $i => $s) {
                TranscriptSegment::create([
                    'id' => (string) Str::uuid(),
                    'transcript_id' => $transcript->id,
                    'sequence_number' => $i + 1,
                    'speaker_label' => $s['speaker'],
                    'start_ms' => $s['start_ms'],
                    'end_ms' => $s['end_ms'],
                    'text' => $s['text'],
                ]);
            }
        });

        return true;
    }

    /**
     * Plaud segment fields vary by block version; accept the common spellings.
     * @param list<array<string, mixed>> $rows
     * @return list<array{speaker: string, text: string, start_ms: int, end_ms: int}>
     */
    public function normalizeSegments(array $rows, int $durationMs = 0): array
    {
        $out = [];
        foreach ($rows as $row) {
            $text = trim((string) ($row['content'] ?? $row['text'] ?? $row['sentence'] ?? ''));
            if ($text === '') {
                continue;
            }
            $out[] = [
                'speaker' => Str::limit(trim((string) ($row['speaker'] ?? $row['speaker_name'] ?? $row['original_speaker'] ?? '')) ?: 'Speaker', 100, ''),
                'text' => $text,
                'start_ms' => (int) round((float) ($row['start_time'] ?? $row['start'] ?? $row['start_ms'] ?? 0)),
                'end_ms' => (int) round((float) ($row['end_time'] ?? $row['end'] ?? $row['end_ms'] ?? 0)),
            ];
        }
        // Some blocks use seconds: if every time is tiny compared with the recording length, scale up.
        $maxEnd = $out === [] ? 0 : max(array_column($out, 'end_ms'));
        if ($maxEnd > 0 && $durationMs > 0 && $maxEnd * 100 < $durationMs) {
            foreach ($out as &$s) {
                $s['start_ms'] *= 1000;
                $s['end_ms'] *= 1000;
            }
            unset($s);
        }

        return $out;
    }

    /** @param array<int|string, mixed> $notes */
    private function notesToMarkdown(array $notes): ?string
    {
        $parts = [];
        foreach (array_is_list($notes) ? $notes : [$notes] as $note) {
            if (is_array($note) && is_string($note['data_content'] ?? null) && trim($note['data_content']) !== '') {
                $parts[] = trim($note['data_content']);
            }
        }

        return $parts === [] ? null : implode("\n\n---\n\n", $parts);
    }

    /** @param list<array{speaker: string}> $segments */
    private function describe(array $file, array $segments): string
    {
        $minutes = (int) round(((int) ($file['duration'] ?? 0)) / 60000);
        $speakers = count(array_unique(array_column($segments, 'speaker')));

        return trim(($minutes > 0 ? "{$minutes} min · " : '') . "{$speakers} speaker" . ($speakers === 1 ? '' : 's') . ' · Imported from Plaud');
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if (is_numeric($value)) {
            $n = (float) $value;

            return CarbonImmutable::createFromTimestamp($n > 1e12 ? $n / 1000 : $n);
        }
        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function audit(string $userId, string $workspaceId, string $action): void
    {
        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'actor_user_id' => $userId,
            'action' => $action,
            'resource_type' => 'integration',
            'resource_id' => 'plaud',
            'metadata' => [],
            'created_at' => now(),
        ]);
    }
}
