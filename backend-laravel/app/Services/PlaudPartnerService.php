<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the PLAUD Embedded auth API (docs.plaud.ai/plaud-embedded/auth-api-overview).
 * Partner token: client_id/secret -> 1h bearer, cached encrypted with APP_KEY.
 * User token: partner token -> 24h token the mobile SDK uses to bind and sync the user's device.
 * Tokens are never logged.
 */
class PlaudPartnerService
{
    private const PARTNER_CACHE_KEY = 'plaud.partner_token';

    public function configured(): bool
    {
        return filled(config('plaud.client_id')) && filled(config('plaud.client_secret'));
    }

    /** @return array{access_token: string, expires_in: int, domain: string} */
    public function issueUserToken(User $user): array
    {
        if (!$this->configured()) {
            throw new RuntimeException('PLAUD partner credentials are not configured.');
        }

        $response = Http::timeout((int) config('plaud.timeout'))
            ->withToken($this->partnerToken())
            ->acceptJson()
            ->post(config('plaud.base_url') . '/developer/api/open/partner/users/access-token', [
                'user_id' => $user->id,
                'expires_in' => (int) config('plaud.user_token_ttl'),
            ]);

        if ($response->status() === 401) {
            Cache::forget(self::PARTNER_CACHE_KEY); // partner token revoked early; next call re-authenticates
        }
        $token = $response->json('access_token');
        if (!$response->successful() || !is_string($token) || $token === '') {
            throw new RuntimeException("PLAUD user token request failed (HTTP {$response->status()}).");
        }

        return [
            'access_token' => $token,
            'expires_in' => (int) ($response->json('expires_in') ?? config('plaud.user_token_ttl')),
            'domain' => (string) parse_url((string) config('plaud.base_url'), PHP_URL_HOST),
        ];
    }

    private function partnerToken(): string
    {
        $cached = Cache::get(self::PARTNER_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return decrypt($cached);
        }

        $response = Http::timeout((int) config('plaud.timeout'))
            ->withBasicAuth((string) config('plaud.client_id'), (string) config('plaud.client_secret'))
            ->asForm()
            ->acceptJson()
            ->post(config('plaud.base_url') . '/developer/api/oauth/partner/access-token');

        $token = $response->json('access_token');
        if (!$response->successful() || !is_string($token) || $token === '') {
            throw new RuntimeException("PLAUD partner authentication failed (HTTP {$response->status()}).");
        }

        $ttl = max(60, (int) ($response->json('expires_in') ?? 3600) - 300);
        Cache::put(self::PARTNER_CACHE_KEY, encrypt($token), $ttl);

        return $token;
    }
}
