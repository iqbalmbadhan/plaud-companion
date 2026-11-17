<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Reset an account's password from the command line (recovery tool).
 *   php artisan plaud:set-password you@example.com
 *   php artisan plaud:set-password you@example.com --password="NewPass123!"
 *
 * Uses the app's own hasher so the result verifies at login. Ends all other sessions.
 */
class SetPassword extends Command
{
    protected $signature = 'plaud:set-password {email : Email of an existing account} {--password= : New password (prompted securely if omitted)}';

    protected $description = 'Reset an account password (and sign that user out everywhere)';

    public function handle(TokenService $tokens): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?? '');
        if ($password === '') {
            $password = (string) $this->secret('New password (min 8 characters)');
            $confirm = (string) $this->secret('Confirm new password');
            if ($password !== $confirm) {
                $this->error('Passwords did not match.');

                return self::FAILURE;
            }
        }
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user->password_hash = $tokens->hashPassword($password);
        $user->save();
        $tokens->revokeAllSessions($user);

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => $user->id,
            'action' => 'user.password_reset_cli',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'metadata' => ['via' => 'artisan'],
            'created_at' => now(),
        ]);

        $this->info("Password updated for {$email}. All other sessions were signed out.");

        return self::SUCCESS;
    }
}
