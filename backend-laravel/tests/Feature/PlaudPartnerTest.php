<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Services\TokenService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaudPartnerTest extends TestCase
{
    private function bearer(): array
    {
        $user = $this->createUser();

        return [$user, ['Authorization' => 'Bearer ' . app(TokenService::class)->issueTokenPair($user)['access_token']]];
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/plaud/user-token')->assertStatus(401);
    }

    public function test_reports_not_configured_without_calling_plaud(): void
    {
        config(['plaud.client_id' => null, 'plaud.client_secret' => null]);
        Http::fake();
        [, $headers] = $this->bearer();

        $this->postJson('/api/v1/plaud/user-token', [], $headers)
            ->assertStatus(503)->assertJson(['error' => 'plaud_not_configured']);
        Http::assertNothingSent();
    }

    public function test_issues_user_token_via_partner_token_and_caches_partner_token(): void
    {
        config(['plaud.client_id' => 'cid', 'plaud.client_secret' => 'csecret', 'plaud.base_url' => 'https://plaud.test']);
        Http::fake([
            'plaud.test/developer/api/oauth/partner/access-token' => Http::response(['access_token' => 'partner-tok', 'expires_in' => 3600]),
            'plaud.test/developer/api/open/partner/users/access-token' => Http::response(['access_token' => 'user-tok', 'expires_in' => 86400]),
        ]);
        [$user, $headers] = $this->bearer();

        $this->postJson('/api/v1/plaud/user-token', [], $headers)
            ->assertOk()
            ->assertJson(['access_token' => 'user-tok', 'expires_in' => 86400, 'domain' => 'plaud.test'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/v1/plaud/user-token', [], $headers)->assertOk();

        Http::assertSentCount(3); // partner token fetched once, user token twice
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/oauth/partner/access-token')
            && $r->hasHeader('Authorization', 'Basic ' . base64_encode('cid:csecret')));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/users/access-token')
            && $r->hasHeader('Authorization', 'Bearer partner-tok')
            && $r['user_id'] === $user->id && $r['expires_in'] === 86400);

        $audit = AuditEvent::query()->where('action', 'plaud.user_token_issued')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('user-tok', json_encode($audit->metadata));
    }

    public function test_upstream_failure_returns_502_without_leaking_details(): void
    {
        config(['plaud.client_id' => 'cid', 'plaud.client_secret' => 'csecret', 'plaud.base_url' => 'https://plaud.test']);
        Http::fake(['*' => Http::response(['error' => 'invalid_client'], 401)]);
        [, $headers] = $this->bearer();

        $res = $this->postJson('/api/v1/plaud/user-token', [], $headers)->assertStatus(502);
        $this->assertStringNotContainsString('csecret', $res->getContent());
    }
}
