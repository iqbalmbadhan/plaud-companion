<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

class TokenService
{
    public function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($password, $algo);
        if ($hash === false) {
            throw new RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issueTokenPair(User $user): array
    {
        $accessTtlMinutes = max(5, (int) config('app.access_token_ttl_minutes', 60));
        $refreshTtlMinutes = max(60, (int) config('app.refresh_token_ttl_minutes', 10080));
        $expiresIn = $accessTtlMinutes * 60;

        $accessToken = $this->encodeJwt([
            'sub' => $user->id,
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + $expiresIn,
            'jti' => (string) Str::uuid(),
        ]);

        $refreshToken = $this->encodeJwt([
            'sub' => $user->id,
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + ($refreshTtlMinutes * 60),
            'jti' => (string) Str::uuid(),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => $expiresIn,
        ];
    }

    public function resolveUserFromBearer(string $token): ?User
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        // 1. Try HMAC-SHA256 JWT
        if (substr_count($token, '.') === 2) {
            $payload = $this->decodeJwt($token);
            if ($payload !== null && ($payload['type'] ?? '') === 'access' && !empty($payload['sub'])) {
                return User::query()->where('id', (string) $payload['sub'])->first();
            }
        }

        // 2. Fallback to Sanctum personal access token
        if (class_exists(PersonalAccessToken::class)) {
            $pat = PersonalAccessToken::findToken($token);
            if ($pat && $pat->tokenable instanceof User) {
                return $pat->tokenable;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeJwt(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $expectedSig = $this->base64UrlEncode(
            hash_hmac('sha256', $headerB64.'.'.$payloadB64, $this->signingSecret(), true)
        );

        if (!hash_equals($expectedSig, $sigB64)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($payloadB64);
        if ($payloadJson === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJwt(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerB64 = $this->base64UrlEncode((string) json_encode($header, JSON_THROW_ON_ERROR));
        $payloadB64 = $this->base64UrlEncode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $headerB64.'.'.$payloadB64, $this->signingSecret(), true);

        return $headerB64.'.'.$payloadB64.'.'.$this->base64UrlEncode($signature);
    }

    private function signingSecret(): string
    {
        $jwtSecret = (string) config('app.jwt_secret', '');
        if ($jwtSecret !== '') {
            return $jwtSecret;
        }

        $appKey = (string) config('app.key', '');
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        if ($appKey !== '') {
            return $appKey;
        }

        throw new RuntimeException('Signing secret is not configured (JWT_SECRET_KEY or APP_KEY).');
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
