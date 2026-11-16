<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\TokenService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileAndPasswordResetTest extends TestCase
{
    public function test_unauthenticated_user_accessing_profile_is_redirected_to_login(): void
    {
        $response = $this->get('/profile');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_profile_with_stats_and_workspaces(): void
    {
        $user = $this->createUser([
            'display_name' => 'Alice Profile Tester',
            'email' => 'alice@example.com',
        ]);
        $workspace = $this->createWorkspace($user, ['name' => 'Alice Innovation Lab']);
        $this->createTranscript($workspace, $user, ['title' => 'Sample Strategy Call']);

        $response = $this->actingAsWebUser($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('Alice Profile Tester');
        $response->assertSee('alice@example.com');
        $response->assertSee('Alice Innovation Lab');
        $response->assertSee('My Profile &amp; Security', false);
        $response->assertSee('Save Profile Changes');
        $response->assertSee('Update Password');
        $response->assertSee('Developed by');
    }

    public function test_authenticated_user_can_fetch_profile_json_via_api(): void
    {
        $user = $this->createUser([
            'display_name' => 'API Tester',
            'email' => 'api_tester@example.com',
        ]);
        $workspace = $this->createWorkspace($user, ['name' => 'API HQ']);

        $response = $this->actingAsWebUser($user)->getJson('/app/api/profile');

        $response->assertOk();
        $response->assertJsonPath('user.display_name', 'API Tester');
        $response->assertJsonPath('user.email', 'api_tester@example.com');
        $response->assertJsonPath('user.initials', 'AT');
        $response->assertJsonPath('metrics.transcripts_count', 0);
        $response->assertJsonPath('workspaces.0.workspace.name', 'API HQ');
    }

    public function test_authenticated_user_can_update_display_name(): void
    {
        $user = $this->createUser([
            'display_name' => 'Original Name',
        ]);

        $response = $this->actingAsWebUser($user)->post('/profile', [
            'display_name' => 'Updated Display Name',
        ]);

        $response->assertSessionHas('status');
        $this->assertSame('Updated Display Name', $user->fresh()->display_name);

        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'action' => 'user.profile_updated',
            'resource_type' => 'User',
            'resource_id' => $user->id,
        ]);
    }

    public function test_updating_display_name_validates_input(): void
    {
        $user = $this->createUser();

        $response = $this->actingAsWebUser($user)->post('/profile', [
            'display_name' => 'A', // min 2 characters required
        ]);

        $response->assertSessionHasErrors(['display_name']);
    }

    public function test_in_session_password_change_requires_valid_current_password(): void
    {
        $user = $this->createUser(['password' => 'InitialPassword123!']);
        $originalHash = $user->password_hash;

        $response = $this->actingAsWebUser($user)->post('/profile/password', [
            'current_password' => 'WrongPassword999!',
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertSame($originalHash, $user->fresh()->password_hash);
    }

    public function test_in_session_password_change_succeeds_and_updates_hash_and_rotates_remember_token(): void
    {
        $user = $this->createUser(['password' => 'InitialPassword123!']);
        $initialRemember = $user->remember_token;

        $response = $this->actingAsWebUser($user)->post('/profile/password', [
            'current_password' => 'InitialPassword123!',
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response->assertSessionHas('status', 'Your password has been changed successfully. You were signed out on all other devices.');

        $fresh = $user->fresh();
        $this->assertTrue(app(TokenService::class)->verifyPassword('NewSecretPassword123!', $fresh->password_hash));
        $this->assertNotEquals($initialRemember, $fresh->remember_token);

        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'action' => 'user.password_changed',
            'resource_type' => 'User',
            'resource_id' => $user->id,
        ]);
    }

    public function test_in_session_password_change_requires_password_confirmation(): void
    {
        $user = $this->createUser(['password' => 'InitialPassword123!']);

        $response = $this->actingAsWebUser($user)->post('/profile/password', [
            'current_password' => 'InitialPassword123!',
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_guest_can_view_forgot_password_form(): void
    {
        $response = $this->get('/forgot-password');
        $response->assertOk();
        $response->assertSee('Account Recovery &amp; Password Reset', false);
        $response->assertSee('Send Password Reset Link');
        $response->assertSee('Developed by');
    }

    public function test_forgot_password_generates_hashed_token_in_database_with_60_minute_lifetime(): void
    {
        $user = $this->createUser(['email' => 'reset_me@example.com']);

        $response = $this->post('/forgot-password', [
            'email' => 'reset_me@example.com',
        ]);

        $response->assertSessionHas('status');

        $record = DB::table('password_reset_tokens')->where('email', 'reset_me@example.com')->first();
        $this->assertNotNull($record, 'A password_reset_tokens record must be created.');
        $this->assertSame(64, strlen($record->token), 'Stored token must be SHA-256 hash (64 hex characters).');
        $this->assertNotNull($record->created_at);
    }

    public function test_forgot_password_flashes_dev_reset_url_in_local_or_array_mailer(): void
    {
        $user = $this->createUser(['email' => 'dev_test@example.com']);

        $response = $this->post('/forgot-password', [
            'email' => 'dev_test@example.com',
        ]);

        $response->assertSessionHas('dev_reset_url');
        $devUrl = session('dev_reset_url');
        $this->assertStringContainsString('/reset-password/', $devUrl);
        $this->assertStringContainsString('dev_test%40example.com', $devUrl);
    }

    public function test_suspended_account_cannot_request_password_reset(): void
    {
        $user = $this->createSuspendedUser(['email' => 'suspended_user@example.com']);

        $response = $this->post('/forgot-password', [
            'email' => 'suspended_user@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('suspended', session('errors')->first('email'));

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'suspended_user@example.com',
        ]);
    }

    public function test_guest_can_view_reset_password_form_with_token(): void
    {
        $response = $this->get('/reset-password/sample-test-token?email=user@example.com');
        $response->assertOk();
        $response->assertSee('Set New Password');
        $response->assertSee('Reset Password &amp; Continue', false);
        $response->assertSee('sample-test-token');
        $response->assertSee('user@example.com');
    }

    public function test_reset_password_with_expired_token_is_rejected(): void
    {
        $user = $this->createUser(['email' => 'expired_token@example.com']);
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $hashedToken,
            'created_at' => Carbon::now()->subMinutes(61), // Expired 61 minutes ago
        ]);

        $response = $this->post('/reset-password', [
            'token' => $plainToken,
            'email' => $user->email,
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('expired', session('errors')->first('email'));

        // Expired token should be deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_reset_password_with_valid_token_updates_password_deletes_token_and_logs_audit(): void
    {
        $user = $this->createUser([
            'email' => 'valid_reset@example.com',
            'password' => 'OldPassword123!',
        ]);
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $hashedToken,
            'created_at' => Carbon::now()->subMinutes(10), // Created 10 minutes ago
        ]);

        $response = $this->post('/reset-password', [
            'token' => $plainToken,
            'email' => $user->email,
            'password' => 'NewFreshPassword123!',
            'password_confirmation' => 'NewFreshPassword123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');

        // Token consumed and removed
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);

        // Password hash updated
        $fresh = $user->fresh();
        $this->assertTrue(app(TokenService::class)->verifyPassword('NewFreshPassword123!', $fresh->password_hash));

        // Audit event logged
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'action' => 'user.password_reset',
            'resource_type' => 'User',
            'resource_id' => $user->id,
        ]);

        // User can now log in with the new password
        $loginResponse = $this->post('/login', [
            'email' => 'valid_reset@example.com',
            'password' => 'NewFreshPassword123!',
        ]);
        $loginResponse->assertRedirect('/app');
    }

    public function test_suspended_account_cannot_execute_password_reset(): void
    {
        $user = $this->createSuspendedUser(['email' => 'suspended_reset@example.com']);
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $hashedToken,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->post('/reset-password', [
            'token' => $plainToken,
            'email' => $user->email,
            'password' => 'NewPasswordAttempt123!',
            'password_confirmation' => 'NewPasswordAttempt123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('suspended', session('errors')->first('email'));
    }

    public function test_reset_password_rejects_mismatched_password_confirmation(): void
    {
        $user = $this->createUser(['email' => 'mismatch@example.com']);
        $plainToken = Str::random(64);

        $response = $this->post('/reset-password', [
            'token' => $plainToken,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'MismatchedPassword123!',
        ]);

        $response->assertSessionHasErrors(['password']);
    }
}
