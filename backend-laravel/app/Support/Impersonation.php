<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A developer working as another user (e.g. the non-technical owner) in the web app.
 *
 * Rules: developers only; never another developer or a suspended user; requires a reason
 * and the developer's password; ends after MINUTES; admin/developer pages are blocked
 * while active; every audit entry written meanwhile carries impersonator_user_id.
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonation';

    public const MINUTES = 30;

    /** @return array{impersonator_id: string, target_id: string, started_at: int, expires_at: int, reason: string}|null */
    public static function current(): ?array
    {
        if (!app()->bound('request')) {
            return null;
        }
        $request = request();
        if (!$request->hasSession()) {
            return null;
        }
        $data = $request->session()->get(self::SESSION_KEY);

        return is_array($data) ? $data : null;
    }

    public static function active(): bool
    {
        return self::current() !== null;
    }

    public static function impersonatorId(): ?string
    {
        return self::current()['impersonator_id'] ?? null;
    }

    public static function expired(): bool
    {
        $data = self::current();

        return $data !== null && now()->getTimestamp() >= (int) $data['expires_at'];
    }

    public static function start(Request $request, User $developer, User $target, string $reason): void
    {
        self::audit($developer->id, null, 'impersonation.started', $target, ['reason' => $reason, 'minutes' => self::MINUTES]);

        Auth::guard('web')->login($target);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'impersonator_id' => $developer->id,
            'target_id' => $target->id,
            'started_at' => now()->getTimestamp(),
            'expires_at' => now()->addMinutes(self::MINUTES)->getTimestamp(),
            'reason' => $reason,
        ]);
        app(TokenService::class)->markSessionAuthenticated($request);
    }

    /**
     * Ends impersonation and signs the developer back in. Returns false when the developer
     * account can no longer be restored (then the session is fully signed out).
     */
    public static function stop(Request $request, string $why = 'ended'): bool
    {
        $data = self::current();
        if ($data === null) {
            return false;
        }

        $request->session()->forget(self::SESSION_KEY);
        $target = User::query()->find($data['target_id']);
        $developer = User::query()->find($data['impersonator_id']);
        self::audit($data['impersonator_id'], null, 'impersonation.ended', $target, [
            'why' => $why,
            'duration_seconds' => max(0, now()->getTimestamp() - (int) $data['started_at']),
        ]);

        if ($developer === null || $developer->status !== 'active' || !$developer->isDeveloper()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return false;
        }

        Auth::guard('web')->login($developer);
        $request->session()->regenerate();
        app(TokenService::class)->markSessionAuthenticated($request);

        return true;
    }

    /** @param array<string, mixed> $metadata */
    private static function audit(string $actorId, ?string $workspaceId, string $action, ?User $target, array $metadata): void
    {
        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'actor_user_id' => $actorId,
            'impersonator_user_id' => null,
            'action' => $action,
            'resource_type' => 'user',
            'resource_id' => $target?->id,
            'metadata' => array_merge(['target_email' => $target?->email], $metadata),
            'created_at' => now(),
        ]);
    }
}
