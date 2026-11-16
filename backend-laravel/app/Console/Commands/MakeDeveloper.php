<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Promote an existing account to developer (for installations made before the role existed).
 *   php artisan plaud:make-developer you@example.com
 */
class MakeDeveloper extends Command
{
    protected $signature = 'plaud:make-developer {email : Email of an existing active account}';

    protected $description = 'Give an existing account the developer role (updates, user management, impersonation, debug log)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }
        if ($user->status !== 'active') {
            $this->error("{$email} is suspended. Reactivate it first.");

            return self::FAILURE;
        }

        $user->is_developer = true;
        $user->is_super_admin = true;
        $user->save();

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => null,
            'action' => 'user.developer_granted',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'metadata' => ['target_email' => $user->email, 'via' => 'artisan'],
            'created_at' => now(),
        ]);

        $this->info("{$email} is now a developer.");

        return self::SUCCESS;
    }
}
