<?php

namespace Tests\Feature;

use App\Services\TokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A password change or reset must end every other session and invalidate API tokens.
 */
class SessionRevocationTest extends TestCase
{
    public function test_password_change_keeps_current_session_and_records_revocation(): void
    {
        $user = $this->createUser();

        $response = $this->actingAsWebUser($user)
            ->withSession([TokenService::SESSION_AUTH_AT => time() - 60])
            ->post('/profile/password', [
                'current_password' => 'Password123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', fn (string $s): bool => str_contains($s, 'signed out on all other devices'));
        $this->assertNotNull($user->fresh()->sessions_valid_after);

        // The browser that changed the password is still signed in.
        $this->get('/app')->assertOk();
    }

    public function test_session_started_before_revocation_is_signed_out(): void
    {
        $user = $this->createUser();
        app(TokenService::class)->revokeAllSessions($user);

        $this->actingAsWebUser($user->fresh())
            ->withSession([TokenService::SESSION_AUTH_AT => time() - 120])
            ->get('/app')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn (string $s): bool => str_contains($s, 'Please sign in again'));
    }

    public function test_old_session_gets_401_on_json_endpoints(): void
    {
        $user = $this->createUser();
        app(TokenService::class)->revokeAllSessions($user);

        $this->actingAsWebUser($user->fresh())
            ->withSession([TokenService::SESSION_AUTH_AT => time() - 120])
            ->getJson('/app/api/workspaces')
            ->assertStatus(401);
    }

    public function test_session_without_sign_in_time_is_signed_out_after_revocation(): void
    {
        // Sessions created before this release have no sign-in time recorded.
        $user = $this->createUser();
        app(TokenService::class)->revokeAllSessions($user);

        $this->actingAsWebUser($user->fresh())->get('/app')->assertRedirect(route('login'));
    }

    public function test_users_never_revoked_are_unaffected(): void
    {
        $user = $this->createUser();

        $this->actingAsWebUser($user)->get('/app')->assertOk();
    }

    public function test_jwt_issued_before_revocation_is_rejected_and_new_one_works(): void
    {
        $user = $this->createUser();
        $tokens = app(TokenService::class);
        $old = $tokens->issueTokenPair($user)['access_token'];

        $this->travel(5)->seconds();
        $tokens->revokeAllSessions($user);

        $this->withHeader('Authorization', 'Bearer ' . $old)->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->travel(1)->seconds();
        $new = $tokens->issueTokenPair($user->fresh())['access_token'];
        $this->withHeader('Authorization', 'Bearer ' . $new)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_personal_access_tokens_are_deleted(): void
    {
        $user = $this->createUser();
        $plain = $user->createToken('mobile')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $plain)->getJson('/api/v1/auth/me')->assertOk();

        app(TokenService::class)->revokeAllSessions($user);

        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $plain)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_remember_token_is_rotated(): void
    {
        $user = $this->createUser();
        $user->setRememberToken('old-remember-token');
        $user->save();

        app(TokenService::class)->revokeAllSessions($user);

        $this->assertNotSame('old-remember-token', $user->fresh()->getRememberToken());
    }

    public function test_password_reset_revokes_all_sessions(): void
    {
        $user = $this->createUser(['email' => 'reset-me@example.com']);
        $plain = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset-me@example.com',
            'token' => hash('sha256', $plain),
            'created_at' => now(),
        ]);

        $this->post('/reset-password', [
            'token' => $plain,
            'email' => 'reset-me@example.com',
            'password' => 'BrandNew789!',
            'password_confirmation' => 'BrandNew789!',
        ])->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->sessions_valid_after);

        $this->actingAsWebUser($user->fresh())
            ->withSession([TokenService::SESSION_AUTH_AT => time() - 60])
            ->get('/app')
            ->assertRedirect(route('login'));
    }

    public function test_login_records_sign_in_time_so_new_session_works_after_revocation(): void
    {
        $user = $this->createUser(['email' => 'fresh-login@example.com']);
        app(TokenService::class)->revokeAllSessions($user);
        $this->travel(1)->seconds();

        $this->post('/login', ['email' => 'fresh-login@example.com', 'password' => 'Password123!'])
            ->assertRedirect();
        $this->assertNotNull(session(TokenService::SESSION_AUTH_AT));

        $this->get('/app')->assertOk();
    }

    public function test_updater_page_requires_a_valid_session(): void
    {
        $admin = $this->createSuperAdmin();
        app(TokenService::class)->revokeAllSessions($admin);

        $this->actingAsWebUser($admin->fresh())
            ->withSession([TokenService::SESSION_AUTH_AT => time() - 60])
            ->get('/settings/updates')
            ->assertRedirect(route('login'));
    }
}
