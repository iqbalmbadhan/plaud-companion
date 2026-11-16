<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ErrorLog;
use App\Models\User;
use App\Services\ErrorLogger;
use App\Services\TokenService;
use App\Support\Impersonation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DeveloperRoleTest extends TestCase
{
    private function developer(array $attrs = []): User
    {
        return $this->createUser(array_merge(['display_name' => 'Dev', 'is_developer' => true, 'is_super_admin' => true], $attrs));
    }

    private function startImpersonation(User $dev, User $target, string $reason = 'Helping with the board export'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAsWebUser($dev)->postJson("/settings/developer/api/users/{$target->id}/impersonate", [
            'reason' => $reason,
            'password' => 'Password123!',
        ]);
    }

    // ---------------------------------------------------------------- access

    public function test_developer_endpoints_require_developer(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createUser();

        $this->actingAsWebUser($admin)->getJson('/settings/developer/api/workspaces')->assertStatus(403);
        $this->actingAsWebUser($user)->getJson('/settings/developer/api/errors')->assertStatus(403);
        $this->actingAsWebUser($this->developer())->getJson('/settings/developer/api/workspaces')->assertOk();
    }

    public function test_developer_has_super_admin_powers(): void
    {
        $dev = $this->developer(['is_super_admin' => false]);
        $this->actingAsWebUser($dev)->get('/settings/users')->assertOk();
        $this->actingAsWebUser($dev)->get('/settings/updates')->assertOk();
    }

    public function test_installer_flag_and_artisan_command_create_developers(): void
    {
        $user = $this->createUser(['email' => 'owner@example.com']);
        $this->assertSame(0, Artisan::call('plaud:make-developer', ['email' => 'owner@example.com']));
        $fresh = $user->fresh();
        $this->assertTrue($fresh->isDeveloper());
        $this->assertTrue($fresh->is_super_admin);
        $this->assertSame(1, Artisan::call('plaud:make-developer', ['email' => 'missing@example.com']));
    }

    // ---------------------------------------------------------- impersonation

    public function test_impersonation_requires_password_and_reason(): void
    {
        $dev = $this->developer();
        $boss = $this->createUser(['display_name' => 'Boss']);

        $this->actingAsWebUser($dev)->postJson("/settings/developer/api/users/{$boss->id}/impersonate", ['reason' => 'Help with export', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonPath('error', 'invalid_password');
        $this->actingAsWebUser($dev)->postJson("/settings/developer/api/users/{$boss->id}/impersonate", ['reason' => 'x', 'password' => 'Password123!'])
            ->assertStatus(422);
        $this->assertFalse(Impersonation::active());
    }

    public function test_cannot_impersonate_self_developers_or_suspended_users(): void
    {
        $dev = $this->developer();
        $otherDev = $this->developer();
        $suspended = $this->createSuspendedUser();

        $this->startImpersonation($dev, $dev)->assertStatus(403);
        $this->startImpersonation($dev, $otherDev)->assertStatus(403);
        $this->startImpersonation($dev, $suspended)->assertStatus(403);
    }

    public function test_super_admin_cannot_impersonate(): void
    {
        $admin = $this->createSuperAdmin();
        $boss = $this->createUser();
        $this->startImpersonation($admin, $boss)->assertStatus(403);
    }

    public function test_full_impersonation_journey_is_audited(): void
    {
        $dev = $this->developer();
        $boss = $this->createUser(['display_name' => 'Morgan Boss']);
        $workspace = $this->createWorkspace($boss);

        $this->startImpersonation($dev, $boss)->assertOk()->assertJsonPath('redirect', route('app.dashboard'));
        $this->assertSame($boss->id, auth()->id());
        $this->assertSame($dev->id, Impersonation::impersonatorId());

        // Working as the boss: their pages and data are available, with the banner.
        $this->get('/app')->assertOk()->assertSee('is working as', false)->assertSee('Morgan Boss');
        $this->postJson("/app/api/workspaces/{$workspace->id}/projects", ['name' => 'Board pack'])->assertCreated();

        // Every audit entry written now names the developer.
        $event = AuditEvent::query()->where('action', 'like', '%')->orderByDesc('created_at')->get()
            ->firstWhere('impersonator_user_id', $dev->id);
        $this->assertNotNull($event);
        $started = AuditEvent::query()->where('action', 'impersonation.started')->first();
        $this->assertSame($dev->id, $started->actor_user_id);
        $this->assertSame('Helping with the board export', $started->metadata['reason']);

        // Admin and developer tools are closed; the boss's password cannot be changed.
        $this->get('/settings/users')->assertStatus(403);
        $this->getJson('/settings/developer/api/workspaces')->assertStatus(403);
        $this->postJson('/profile/password', ['current_password' => 'x', 'password' => 'NewPassword1!', 'password_confirmation' => 'NewPassword1!'])
            ->assertStatus(403);

        // End the session: back to the developer.
        $this->post('/impersonation/stop')->assertRedirect(route('settings.users'));
        $this->assertSame($dev->id, auth()->id());
        $this->assertFalse(Impersonation::active());
        $this->assertTrue(AuditEvent::query()->where('action', 'impersonation.ended')->where('actor_user_id', $dev->id)->exists());
        $this->get('/settings/users')->assertOk();
    }

    public function test_impersonation_expires_after_thirty_minutes(): void
    {
        $dev = $this->developer();
        $boss = $this->createUser();
        $this->startImpersonation($dev, $boss)->assertOk();

        $this->travel(Impersonation::MINUTES + 1)->minutes();
        $this->get('/app')->assertRedirect(route('settings.users'));
        $this->assertSame($dev->id, auth()->id());
        $ended = AuditEvent::query()->where('action', 'impersonation.ended')->first();
        $this->assertSame('expired', $ended->metadata['why']);
    }

    public function test_sign_out_while_impersonating_returns_to_developer(): void
    {
        $dev = $this->developer();
        $boss = $this->createUser();
        $this->startImpersonation($dev, $boss)->assertOk();

        $this->post('/logout')->assertRedirect(route('settings.users'));
        $this->assertSame($dev->id, auth()->id());
    }

    public function test_impersonation_ends_if_target_is_suspended(): void
    {
        $dev = $this->developer();
        $boss = $this->createUser();
        $this->startImpersonation($dev, $boss)->assertOk();
        $boss->update(['status' => 'suspended']);

        $this->get('/app')->assertRedirect(route('settings.users'));
        $this->assertSame($dev->id, auth()->id());
    }

    // ------------------------------------------------------- document lists

    public function test_workspace_list_never_contains_transcript_text(): void
    {
        $dev = $this->developer();
        $boss = $this->createUser();
        $ws = $this->createWorkspace($boss, ['name' => 'Executive Office']);
        $this->createTranscript($ws, $boss, ['title' => 'Board Prep', 'content_text' => 'SECRET-ACQUISITION-TARGET is Contoso.']);

        $res = $this->actingAsWebUser($dev)->getJson('/settings/developer/api/workspaces')->assertOk();
        $res->assertJsonPath('workspaces.0.name', 'Executive Office');
        $res->assertJsonPath('workspaces.0.documents.0.title', 'Board Prep');
        $res->assertJsonPath('workspaces.0.documents_count', 1);
        $this->assertStringNotContainsString('SECRET-ACQUISITION-TARGET', $res->getContent());
    }

    // ------------------------------------------------------------ debug log

    public function test_browser_errors_are_recorded_sanitized(): void
    {
        $user = $this->createUser();
        $this->actingAsWebUser($user)->postJson('/app/api/client-errors', [
            'message' => 'TypeError: x is undefined; Authorization: Bearer abc.def.ghi token=supersecret',
            'url' => 'https://plaud.example/app?token=abc#detail',
            'line' => 12, 'column' => 4,
            'stack' => 'at render (app.js:12:4) sk-or-v1-0123456789abcdef',
        ])->assertStatus(201)->assertJsonStructure(['reference']);

        $log = ErrorLog::query()->first();
        $this->assertSame('browser', $log->source);
        $this->assertSame('/app:12:4', $log->location);
        $this->assertStringNotContainsString('supersecret', $log->message);
        $this->assertStringNotContainsString('abc.def.ghi', $log->message);
        $this->assertStringNotContainsString('sk-or-v1-0123456789abcdef', $log->context['stack']);
        $this->assertSame($user->id, $log->user_id);
    }

    public function test_server_errors_get_a_reference_shown_to_the_user(): void
    {
        Route::middleware('web')->get('/test-only/boom', static function (): void {
            throw new \RuntimeException('Boom with api key=ABC123SECRET');
        });

        $res = $this->getJson('/test-only/boom');
        $res->assertStatus(500);
        $reference = $res->headers->get('X-Error-Reference');
        $this->assertNotEmpty($reference);
        $res->assertJsonPath('reference', $reference);

        $log = ErrorLog::query()->where('reference', $reference)->first();
        $this->assertSame('server', $log->source);
        $this->assertSame(\RuntimeException::class, $log->exception_class);
        $this->assertStringNotContainsString('ABC123SECRET', $log->message);
        $this->assertStringContainsString('DeveloperRoleTest.php', $log->location);
    }

    public function test_sql_errors_never_store_bound_values(): void
    {
        $e = new \Illuminate\Database\QueryException('sqlite', 'insert into transcripts values (?)', ['CONFIDENTIAL MEETING TEXT'], new \Exception('constraint failed'));
        app(ErrorLogger::class)->recordException($e);
        $log = ErrorLog::query()->first();
        $this->assertStringStartsWith('Database query failed', $log->message);
        $this->assertStringNotContainsString('CONFIDENTIAL', $log->message);
    }

    public function test_developer_can_read_and_clear_the_debug_log(): void
    {
        $dev = $this->developer();
        app(ErrorLogger::class)->recordBrowser(['message' => 'Broken button'], request());

        $this->actingAsWebUser($dev)->getJson('/settings/developer/api/errors')->assertOk()->assertJsonPath('errors.0.message', 'Broken button');
        $this->actingAsWebUser($dev)->getJson('/settings/developer/api/errors?source=server')->assertOk()->assertJsonCount(0, 'errors');
        $this->actingAsWebUser($dev)->deleteJson('/settings/developer/api/errors')->assertOk()->assertJsonPath('deleted', 1);
        $this->assertSame(0, ErrorLog::query()->count());
    }

    // ------------------------------------------------------- user controls

    public function test_super_admin_cannot_touch_developer_accounts(): void
    {
        $admin = $this->createSuperAdmin();
        $dev = $this->developer();

        $this->actingAsWebUser($admin)->postJson("/settings/users/api/{$dev->id}/toggle-status")->assertStatus(403);
        $this->actingAsWebUser($admin)->postJson("/settings/users/api/{$dev->id}/toggle-admin")->assertStatus(403);
        $this->actingAsWebUser($admin)->postJson("/settings/users/api/{$dev->id}/sign-out-everywhere")->assertStatus(403);
        $this->assertSame('active', $dev->fresh()->status);

        $list = $this->actingAsWebUser($admin)->getJson('/settings/users/api/list')->json('users');
        $this->assertFalse(collect($list)->firstWhere('id', $dev->id)['can_manage']);
    }

    public function test_last_active_developer_is_protected(): void
    {
        $dev = $this->developer();
        $other = $this->developer();

        // Two developers: one may suspend the other...
        $this->actingAsWebUser($dev)->postJson("/settings/users/api/{$other->id}/toggle-status")->assertOk();
        // ...but the remaining active developer cannot be removed by anyone.
        $third = $this->developer();
        $other->update(['status' => 'active']);
        $this->actingAsWebUser($third)->postJson("/settings/users/api/{$dev->id}/toggle-status")->assertOk();
        $this->actingAsWebUser($dev->fresh()->status === 'active' ? $dev : $third)->postJson("/settings/developer/api/users/{$dev->id}/toggle-developer");
        $this->assertGreaterThanOrEqual(1, User::query()->where('is_developer', true)->where('status', 'active')->count());

        $solo = $this->developer(['email' => 'solo@example.com']);
        User::query()->where('id', '!=', $solo->id)->update(['is_developer' => false]);
        $admin = $this->createSuperAdmin();
        $this->actingAsWebUser($admin)->deleteJson("/settings/developer/api/users/{$solo->id}")->assertStatus(403);
    }

    public function test_suspending_signs_the_user_out_everywhere(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createUser();
        $this->actingAsWebUser($admin)->postJson("/settings/users/api/{$user->id}/toggle-status")->assertOk();
        $this->assertNotNull($user->fresh()->sessions_valid_after);
    }

    public function test_sign_out_everywhere_and_password_reset(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createUser(['email' => 'boss@example.com']);
        $token = app(TokenService::class)->issueTokenPair($user)['access_token'];

        $this->travel(2)->seconds();
        $this->actingAsWebUser($admin)->postJson("/settings/users/api/{$user->id}/sign-out-everywhere")->assertOk();
        $this->assertNotNull($user->fresh()->sessions_valid_after);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/v1/auth/me')->assertStatus(401);

        // Testing uses the array mailer outside production, so the link is returned for the developer.
        $res = $this->actingAsWebUser($admin)->postJson("/settings/users/api/{$user->id}/password-reset")->assertOk();
        $this->assertStringContainsString('/reset-password/', $res->json('reset_url'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'boss@example.com']);
        $this->assertTrue(AuditEvent::query()->where('action', 'user.password_reset_sent')->exists());
    }

    public function test_developer_deletes_user_who_can_no_longer_sign_in(): void
    {
        $dev = $this->developer();
        $user = $this->createUser(['email' => 'gone@example.com']);

        $this->actingAsWebUser($dev)->deleteJson("/settings/developer/api/users/{$dev->id}")->assertStatus(400);
        $this->actingAsWebUser($dev)->deleteJson("/settings/developer/api/users/{$user->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        auth()->guard('web')->logout();
        $this->post('/login', ['email' => 'gone@example.com', 'password' => 'Password123!'])->assertSessionHasErrors('email');
    }

    public function test_developer_grants_and_removes_developer_role(): void
    {
        $dev = $this->developer();
        $user = $this->createUser();

        $this->actingAsWebUser($dev)->postJson("/settings/developer/api/users/{$user->id}/toggle-developer")->assertOk()->assertJsonPath('is_developer', true);
        $this->assertTrue($user->fresh()->is_super_admin);
        $this->actingAsWebUser($dev)->postJson("/settings/users/api/{$user->id}/toggle-admin")->assertStatus(400);
        $this->actingAsWebUser($dev)->postJson("/settings/developer/api/users/{$user->id}/toggle-developer")->assertOk()->assertJsonPath('is_developer', false);
        $this->actingAsWebUser($dev)->postJson("/settings/developer/api/users/{$dev->id}/toggle-developer")->assertStatus(400);
    }

    public function test_users_page_does_not_put_user_names_in_inline_javascript(): void
    {
        $view = file_get_contents(resource_path('views/settings/users.blade.php'));
        $this->assertStringNotContainsString("onclick=\"toggleStatus('\${u.id}', '\${escapeHtml", $view);
        $this->assertDoesNotMatchRegularExpression('/onclick="[^"]*\$\{escapeHtml\(u\.display_name\)/', $view);

        $dev = $this->developer();
        $this->createUser(['display_name' => "x'),alert(1),('"]);
        $this->actingAsWebUser($dev)->get('/settings/users')->assertOk()->assertSee('Debug log')->assertSee('Workspaces &amp; documents', false);
    }
}
