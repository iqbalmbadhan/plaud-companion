<?php

namespace Tests;

use App\Models\AuditEvent;
use App\Models\ProviderConnection;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

if (!class_exists(\Mockery::class) && file_exists(dirname(__DIR__) . '/vendor/mockery/mockery/library/Mockery.php')) {
    require_once dirname(__DIR__) . '/vendor/mockery/mockery/library/Mockery.php';
}

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $simulateInstalled = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->simulateInstalled) {
            $this->ensureInstalled();
        }

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    /**
     * Ensure storage/app/installed.lock exists to prevent redirect to /install.
     */
    protected function ensureInstalled(): void
    {
        $lockFile = storage_path('app/installed.lock');
        $dir = dirname($lockFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_file($lockFile)) {
            file_put_contents($lockFile, 'installed');
        }
    }

    /**
     * Remove installed.lock for tests testing installer preflight/wizard.
     */
    protected function ensureUninstalled(): void
    {
        $lockFile = storage_path('app/installed.lock');
        if (is_file($lockFile)) {
            @unlink($lockFile);
        }
    }

    /**
     * Create an active user with hashed password.
     */
    protected function createUser(array $attributes = []): User
    {
        $rawPassword = $attributes['password'] ?? 'Password123!';
        unset($attributes['password']);

        $tokenService = app(TokenService::class);
        $passwordHash = $tokenService->hashPassword($rawPassword);

        return User::create(array_merge([
            'id' => (string) Str::uuid(),
            'email' => 'user_' . Str::random(8) . '@example.com',
            'display_name' => 'Test User',
            'password_hash' => $passwordHash,
            'status' => 'active',
            'is_super_admin' => false,
            'email_verified_at' => now(),
        ], $attributes));
    }

    /**
     * Create a super-administrator user.
     */
    protected function createSuperAdmin(array $attributes = []): User
    {
        return $this->createUser(array_merge([
            'display_name' => 'Super Admin',
            'is_super_admin' => true,
        ], $attributes));
    }

    /**
     * Create a suspended user.
     */
    protected function createSuspendedUser(array $attributes = []): User
    {
        return $this->createUser(array_merge([
            'display_name' => 'Suspended User',
            'status' => 'suspended',
        ], $attributes));
    }

    /**
     * Create a workspace owned by the given user.
     */
    protected function createWorkspace(User $owner, array $attributes = []): Workspace
    {
        $workspace = Workspace::create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => ($attributes['name'] ?? $owner->display_name . "'s Workspace"),
            'type' => 'personal',
            'owner_user_id' => $owner->id,
        ], $attributes));

        WorkspaceMember::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $workspace;
    }

    /**
     * Create a sample transcript in a workspace.
     */
    protected function createTranscript(Workspace $workspace, User $creator, array $attributes = []): Transcript
    {
        $content = $attributes['content_text'] ?? 'Meeting notes from PLAUD Note recording.';
        return Transcript::create(array_merge([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $creator->id,
            'title' => 'Project Brainstorming',
            'source_type' => 'manual_upload',
            'status' => 'imported',
            'content_text' => $content,
            'content_hash' => hash('sha256', $content),
            'language' => 'en',
            'recorded_at' => now(),
        ], $attributes));
    }

    /**
     * Create a configured provider connection.
     */
    protected function createProviderConnection(
        Workspace $workspace,
        User $creator,
        string $provider = 'openrouter',
        array $attributes = []
    ): ProviderConnection {
        return ProviderConnection::create(array_merge([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'provider' => $provider,
            'api_key_encrypted' => 'mock-encrypted-key',
            'auth_mode' => 'api_key',
            'enabled' => true,
            'default_model' => 'openai/gpt-4o',
            'created_by_user_id' => $creator->id,
        ], $attributes));
    }

    /**
     * Authenticate user for Web routes (session guard).
     */
    protected function actingAsWebUser(User $user): static
    {
        return $this->actingAs($user, 'web');
    }

    /**
     * Authenticate user for API routes by injecting Bearer JWT into headers.
     */
    protected function withApiToken(User $user): static
    {
        $tokenService = app(TokenService::class);
        $pair = $tokenService->issueTokenPair($user);
        return $this->withHeader('Authorization', 'Bearer ' . $pair['access_token']);
    }

    protected function tearDown(): void
    {
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }

        parent::tearDown();
    }
}
