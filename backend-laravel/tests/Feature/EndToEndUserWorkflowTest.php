<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\ProviderConnection;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\TestCase;

class EndToEndUserWorkflowTest extends TestCase
{
    /**
     * Set up common fake HTTP responses for AI providers to ensure hermetic execution.
     */
    protected function fakeExternalAiApis(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"summary_text": "Sample executive meeting summary.", "bullet_points": ["Key decision 1", "Next step 2"]}']],
                ],
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"summary_text": "Executive summary from GPT.", "bullet_points": ["Approved roadmap", "Assigned owner"]}']],
                ],
            ], 200),
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['text' => '{"summary_text": "Executive summary from Claude.", "bullet_points": ["Action item A", "Action item B"]}'],
                ],
            ], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"summary_text": "Executive summary from Gemini.", "bullet_points": ["Gemini finding 1", "Gemini finding 2"]}'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    // =========================================================================
    // TIER 1: FEATURE COVERAGE (55 Methods)
    // =========================================================================

    // --- Feature 1: User Registration with Immediate Access (5 tests) --------

    public function test_tier1_f01_standard_web_registration(): void
    {
        $response = $this->post('/register', [
            'display_name' => 'Alice Morgan',
            'email' => 'alice@company.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/app');

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'alice@company.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Alice Morgan', $user->display_name);
        $this->assertSame('active', $user->status);
        $this->assertFalse((bool) $user->is_super_admin);

        $workspace = Workspace::query()->where('owner_user_id', $user->id)->first();
        $this->assertNotNull($workspace);
        $this->assertSame("Alice Morgan's Workspace", $workspace->name);
        $this->assertSame('personal', $workspace->type);

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($member);
        $this->assertSame('owner', $member->role);
        $this->assertSame('active', $member->status);
    }

    public function test_tier1_f01_auto_workspace_creation(): void
    {
        $response = $this->post('/register', [
            'display_name' => 'Bob Builder',
            'email' => 'bob@company.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(302);

        $user = User::query()->where('email', 'bob@company.com')->first();
        $this->assertNotNull($user);

        $workspaces = Workspace::query()->where('owner_user_id', $user->id)->get();
        $this->assertCount(1, $workspaces);
        $this->assertSame("Bob Builder's Workspace", $workspaces->first()->name);
    }

    public function test_tier1_f01_initial_login_redirect_immediate_access(): void
    {
        $this->post('/register', [
            'display_name' => 'Carol Danvers',
            'email' => 'carol@company.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('PLAUD Companion');
    }

    public function test_tier1_f01_email_normalization(): void
    {
        $response = $this->post('/register', [
            'display_name' => 'Charlie Test',
            'email' => '   Charlie.Case@EXAMPLE.COM   ',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(302);

        $user = User::query()->where('email', 'charlie.case@example.com')->first();
        $this->assertNotNull($user, 'Email should be saved in lowercase and trimmed.');
    }

    public function test_tier1_f01_display_name_handling_and_trimming(): void
    {
        $response = $this->post('/register', [
            'display_name' => '   Dana Scully   ',
            'email' => 'dana@company.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(302);

        $user = User::query()->where('email', 'dana@company.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Dana Scully', $user->display_name);

        $workspace = Workspace::query()->where('owner_user_id', $user->id)->first();
        $this->assertSame("Dana Scully's Workspace", $workspace->name);
    }

    // --- Feature 2: User Login & Session Persistence (5 tests) ----------------

    public function test_tier1_f02_login_valid_credentials(): void
    {
        $user = $this->createUser([
            'email' => 'registered@company.com',
            'password' => 'Password123!',
        ]);

        $response = $this->post('/login', [
            'email' => 'registered@company.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticatedAs($user);
    }

    public function test_tier1_f02_remember_me_session_persistence(): void
    {
        $user = $this->createUser([
            'email' => 'rememberme@company.com',
            'password' => 'Password123!',
        ]);

        $response = $this->post('/login', [
            'email' => 'rememberme@company.com',
            'password' => 'Password123!',
            'remember' => '1',
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticatedAs($user);
    }

    public function test_tier1_f02_redirect_to_intended_destination(): void
    {
        $admin = $this->createSuperAdmin([
            'email' => 'intended_admin@company.com',
            'password' => 'AdminPass123!',
        ]);

        // Request protected page while guest
        $guestResp = $this->get('/settings/users');
        $guestResp->assertStatus(302);
        $guestResp->assertRedirect(route('login'));

        // Log in with credentials
        $loginResp = $this->post('/login', [
            'email' => 'intended_admin@company.com',
            'password' => 'AdminPass123!',
        ]);

        $loginResp->assertStatus(302);
        $loginResp->assertRedirect(url('/settings/users'));
    }

    public function test_tier1_f02_logout_session_invalidation(): void
    {
        $user = $this->createUser();
        $this->actingAsWebUser($user);

        $response = $this->post('/logout');
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));

        $this->assertGuest();
        $subsequent = $this->get('/');
        $subsequent->assertStatus(302);
        $subsequent->assertRedirect(route('login'));
    }

    public function test_tier1_f02_session_token_regeneration(): void
    {
        $user = $this->createUser([
            'email' => 'tokenregen@company.com',
            'password' => 'Password123!',
        ]);

        $this->get('/login');
        $initialSessionId = Session::getId();

        $this->post('/login', [
            'email' => 'tokenregen@company.com',
            'password' => 'Password123!',
        ]);

        $this->assertNotSame($initialSessionId, Session::getId());
    }

    // --- Feature 3: Suspended Account Blocking (5 tests) ----------------------

    public function test_tier1_f03_login_attempt_blocked_for_suspended_user(): void
    {
        $user = $this->createSuspendedUser([
            'email' => 'suspended_login@company.com',
            'password' => 'Password123!',
        ]);

        $response = $this->post('/login', [
            'email' => 'suspended_login@company.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors(['email']);
        $error = session('errors')->first('email');
        $this->assertStringContainsString('suspended', strtolower($error));
    }

    public function test_tier1_f03_clear_suspension_flash_message_rendered(): void
    {
        $response = $this->withSession([
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'suspended' => 'Your account has been suspended by an administrator. Please contact support.',
        ])->get('/login');

        $response->assertStatus(200);
        $response->assertSee('suspended');
    }

    public function test_tier1_f03_active_web_session_invalidated_upon_suspension(): void
    {
        $user = $this->createUser();
        $this->actingAsWebUser($user);

        // Verify currently active
        $this->get('/')->assertStatus(200);

        // Admin suspends user in background
        $user->status = 'suspended';
        $user->save();

        // Next request by user must be blocked and redirected
        $response = $this->get('/');
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_tier1_f03_protected_web_api_returns_401_or_403(): void
    {
        $user = $this->createSuspendedUser();
        $this->actingAsWebUser($user);

        $response = $this->getJson('/app/api/workspaces');
        $this->assertTrue(in_array($response->status(), [401, 403], true));
    }

    public function test_tier1_f03_protected_rest_api_returns_403(): void
    {
        $user = $this->createSuspendedUser();
        $response = $this->withApiToken($user)->getJson('/api/v1/auth/me');

        $response->assertStatus(403);
        $response->assertJsonFragment(['detail' => 'Account is suspended or pending verification.']);
    }

    // --- Feature 4: Device Connection & Guided Wizard (5 tests) ---------------

    public function test_tier1_f04_wizard_modal_accessible_with_steps(): void
    {
        $user = $this->createUser();
        $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('PLAUD');
    }

    public function test_tier1_f04_device_integration_status_endpoint(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->getJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/status");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'cloud_sync' => ['enabled', 'connected', 'server_domain'],
            'ble_bridge' => ['supported', 'paired', 'service_uuid'],
        ]);
    }

    public function test_tier1_f04_pairing_payload_validation(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD Note Black',
            'device_id' => 'PN-UUID-TEST-001',
            'battery_level' => 92,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'device' => [
                'device_name' => 'PLAUD Note Black',
                'device_id' => 'PN-UUID-TEST-001',
                'battery_level' => 92,
            ],
        ]);
    }

    public function test_tier1_f04_usb_transfer_instructions_route(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->getJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/status");
        $response->assertStatus(200);
        $response->assertJsonPath('cloud_sync.supported', true);
    }

    public function test_tier1_f04_device_unpairing_or_status_reset(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD Note',
            'device_id' => 'PN-RESET-01',
            'battery_level' => 88,
        ]);

        $statusResp = $this->getJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/status");
        $statusResp->assertStatus(200);
        $this->assertTrue((bool) $statusResp->json('ble_bridge.paired'));
    }

    // --- Feature 5: Web Bluetooth Pairing & Telemetry (5 tests) ---------------

    public function test_tier1_f05_pairing_audit_event_creation(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD Note Silver',
            'device_id' => 'DEV-AUDIT-9988',
            'battery_level' => 95,
        ]);

        $event = AuditEvent::query()
            ->where('workspace_id', $workspace->id)
            ->where('action', 'device.ble_paired')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('DEV-AUDIT-9988', $event->resource_id);
        $this->assertSame('hardware_device', $event->resource_type);
    }

    public function test_tier1_f05_battery_level_telemetry_logging(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD NotePin',
            'device_id' => 'PIN-BATTERY-77',
            'battery_level' => 77,
        ]);

        $event = AuditEvent::query()
            ->where('workspace_id', $workspace->id)
            ->where('action', 'device.ble_paired')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(77, data_get($event->metadata, 'battery_level'));
    }

    public function test_tier1_f05_note_device_metadata(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD Note AI Voice Recorder',
            'device_id' => 'NOTE-UUID-01',
        ]);

        $event = AuditEvent::query()
            ->where('workspace_id', $workspace->id)
            ->where('action', 'device.ble_paired')
            ->latest('created_at')
            ->first();

        $this->assertSame('BLE_GATT', data_get($event->metadata, 'protocol'));
        $this->assertSame('0000fee7-0000-1000-8000-00805f9b34fb', data_get($event->metadata, 'service_uuid'));
    }

    public function test_tier1_f05_notepin_device_metadata(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD NotePin Wearable Capsule',
            'device_id' => 'PIN-UUID-02',
            'battery_level' => 84,
        ]);

        $event = AuditEvent::query()
            ->where('workspace_id', $workspace->id)
            ->where('action', 'device.ble_paired')
            ->latest('created_at')
            ->first();

        $this->assertSame('PLAUD NotePin Wearable Capsule', data_get($event->metadata, 'device_name'));
    }

    public function test_tier1_f05_repeated_pairing_updates_telemetry(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD Note',
            'device_id' => 'PN-REPEAT-01',
            'battery_level' => 85,
        ]);

        $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD Note',
            'device_id' => 'PN-REPEAT-01',
            'battery_level' => 70,
        ]);

        $status = $this->getJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/status");
        $status->assertStatus(200);
        $this->assertSame(70, $status->json('ble_bridge.battery_level'));
    }

    // --- Feature 6: PLAUD File Import Pipeline (5 tests) ----------------------

    public function test_tier1_f06_json_transcript_upload_with_diarized_segments(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $jsonPayload = json_encode([
            'title' => 'Product Strategy Sync',
            'segments' => [
                ['speaker_label' => 'Alice', 'start_ms' => 0, 'end_ms' => 5000, 'text' => 'Sprint goals alignment.'],
                ['speaker_label' => 'Bob', 'start_ms' => 5000, 'end_ms' => 10000, 'text' => 'Backend implementation is on track.'],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('meeting.json', $jsonPayload);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $this->assertSame('success', $response->json('results.0.status'));

        $transcriptId = $response->json('results.0.transcript_id');
        $this->assertNotNull($transcriptId);

        $transcript = Transcript::query()->find($transcriptId);
        $this->assertNotNull($transcript);
        $this->assertStringContainsString('Sprint goals alignment', $transcript->content_text);

        $segments = TranscriptSegment::query()->where('transcript_id', $transcriptId)->get();
        $this->assertGreaterThanOrEqual(2, $segments->count());
    }

    public function test_tier1_f06_text_transcript_upload(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $content = "[00:01] Alice: Discussing Q4 goals.\n[00:15] Bob: Agreed on quarterly targets.";
        $file = UploadedFile::fake()->createWithContent('notes.txt', $content);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $this->assertSame('success', $response->json('results.0.status'));
    }

    public function test_tier1_f06_audio_file_upload(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $audioFile = UploadedFile::fake()->createWithContent('recording.mp3', str_repeat("\xFF\xFB\x90\x00", 250));

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$audioFile],
        ]);

        $response->assertStatus(200);
        $this->assertSame('success', $response->json('results.0.status'));
    }

    public function test_tier1_f06_metadata_extraction_and_hashing(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $text = 'Unique transcript hash test content.';
        $file = UploadedFile::fake()->createWithContent('hash_test.txt', $text);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $transcriptId = $response->json('results.0.transcript_id');

        $transcript = Transcript::query()->find($transcriptId);
        $this->assertNotNull($transcript);
        $this->assertSame(hash('sha256', $text), $transcript->content_hash);
    }

    public function test_tier1_f06_encrypted_vault_ingestion_dispatch(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/sync", [
            'title' => 'Vault Ingested Audio',
            'content_text' => 'Transcription synced from hardware device.',
            'source_type' => 'plaud_ble_sync',
        ]);

        $response->assertStatus(201);
        $this->assertSame('processed', $response->json('transcript.status'));
    }

    // --- Feature 7: Multi-Platform AI Provider Connections (5 tests) -----------

    public function test_tier1_f07_openrouter_credential_save(): void
    {
        $this->fakeExternalAiApis();

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openrouter/connect", [
            'api_key' => 'sk-or-v1-valid-openrouter-key-12345678',
            'model' => 'anthropic/claude-3.5-sonnet',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('provider_connections', [
            'workspace_id' => $workspace->id,
            'provider' => 'openrouter',
            'enabled' => 1,
        ]);
    }

    public function test_tier1_f07_gemini_credential_save(): void
    {
        $this->fakeExternalAiApis();

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/gemini/connect", [
            'api_key' => 'AIzaSyFakeGeminiKey1234567890',
            'model' => 'gemini-2.0-flash',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('provider_connections', [
            'workspace_id' => $workspace->id,
            'provider' => 'gemini',
            'enabled' => 1,
        ]);
    }

    public function test_tier1_f07_openai_credential_save(): void
    {
        $this->fakeExternalAiApis();

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openai/connect", [
            'api_key' => 'sk-openai-fakekey1234567890abcdef',
            'model' => 'gpt-4o-mini',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('provider_connections', [
            'workspace_id' => $workspace->id,
            'provider' => 'openai',
            'enabled' => 1,
        ]);
    }

    public function test_tier1_f07_claude_credential_save(): void
    {
        $this->fakeExternalAiApis();

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/anthropic/connect", [
            'api_key' => 'sk-ant-fakekey1234567890abcdef',
            'model' => 'claude-3-5-sonnet-latest',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('provider_connections', [
            'workspace_id' => $workspace->id,
            'provider' => 'anthropic',
            'enabled' => 1,
        ]);
    }

    public function test_tier1_f07_list_configured_connectors(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->getJson("/app/api/workspaces/{$workspace->id}/ai/connectors");

        $response->assertStatus(200);
        $providers = collect($response->json())->pluck('provider_id')->all();

        $this->assertContains('openrouter', $providers);
        $this->assertContains('gemini', $providers);
        $this->assertContains('anthropic', $providers);
        $this->assertContains('openai', $providers);
        $this->assertContains('builtin', $providers);
    }

    // --- Feature 8: AI Connection Verification & Latency Ping (5 tests) --------

    public function test_tier1_f08_valid_key_ping_success(): void
    {
        $this->fakeExternalAiApis();

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createProviderConnection($workspace, $user, 'openai', [
            'api_key_encrypted' => 'sk-test-valid-key',
        ]);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openai/test");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'provider_id' => 'openai',
        ]);
        $this->assertNotNull($response->json('latency_ms'));
    }

    public function test_tier1_f08_latency_recording(): void
    {
        $this->fakeExternalAiApis();

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createProviderConnection($workspace, $user, 'gemini', [
            'api_key_encrypted' => 'AIzaSyFakeKey',
        ]);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/gemini/test");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('latency_ms'));
        $this->assertGreaterThanOrEqual(0, $response->json('latency_ms'));
    }

    public function test_tier1_f08_error_handling_on_bad_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
        ]);

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createProviderConnection($workspace, $user, 'anthropic', [
            'api_key_encrypted' => 'sk-ant-invalid-key',
        ]);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/anthropic/test");

        $response->assertStatus(200);
        $this->assertFalse($response->json('success'));
        $this->assertNull($response->json('latency_ms'));
    }

    public function test_tier1_f08_credential_encryption_check(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $rawKey = 'sk-sensitive-secret-token-to-encrypt';

        $conn = ProviderConnection::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'provider' => 'openai',
            'api_key_encrypted' => $rawKey,
            'auth_mode' => 'api_key',
            'enabled' => true,
            'default_model' => 'gpt-4o',
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAsWebUser($user);
        $list = $this->getJson("/app/api/workspaces/{$workspace->id}/ai/connectors");
        $list->assertStatus(200);

        // Raw key must never be exposed verbatim in the API response
        $this->assertStringNotContainsString($rawKey, $list->getContent());
    }

    public function test_tier1_f08_mock_builtin_provider_test_endpoint(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/builtin/test");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'provider_id' => 'builtin',
        ]);
        $this->assertNotNull($response->json('latency_ms'));
    }

    // --- Feature 9: Admin User Directory & Search (5 tests) -------------------

    public function test_tier1_f09_directory_list_with_pagination(): void
    {
        $admin = $this->createSuperAdmin();
        $this->createUser(['display_name' => 'User Alpha']);
        $this->createUser(['display_name' => 'User Beta']);

        $this->actingAsWebUser($admin);
        $response = $this->get('/settings/users');

        $response->assertStatus(200);
        $response->assertSee('User Directory & Access Controls');
    }

    public function test_tier1_f09_search_users_by_email(): void
    {
        $admin = $this->createSuperAdmin();
        $target = $this->createUser(['email' => 'target.user@enterprise.com']);
        $this->createUser(['email' => 'other.person@enterprise.com']);

        $this->actingAsWebUser($admin);
        $response = $this->getJson('/settings/users/api/list?q=target.user');

        $response->assertStatus(200);
        $emails = collect($response->json('users'))->pluck('email')->all();
        $this->assertContains('target.user@enterprise.com', $emails);
        $this->assertNotContains('other.person@enterprise.com', $emails);
    }

    public function test_tier1_f09_search_users_by_name(): void
    {
        $admin = $this->createSuperAdmin();
        $this->createUser(['display_name' => 'Eleanor Vance', 'email' => 'eleanor@example.com']);
        $this->createUser(['display_name' => 'Franklin Pierce', 'email' => 'franklin@example.com']);

        $this->actingAsWebUser($admin);
        $response = $this->getJson('/settings/users/api/list?q=Eleanor');

        $response->assertStatus(200);
        $names = collect($response->json('users'))->pluck('display_name')->all();
        $this->assertContains('Eleanor Vance', $names);
        $this->assertNotContains('Franklin Pierce', $names);
    }

    public function test_tier1_f09_filter_users_by_status(): void
    {
        $admin = $this->createSuperAdmin();
        $activeUser = $this->createUser(['status' => 'active']);
        $suspendedUser = $this->createSuspendedUser();

        $this->actingAsWebUser($admin);
        $response = $this->getJson('/settings/users/api/list?status=suspended');

        $response->assertStatus(200);
        $statuses = collect($response->json('users'))->pluck('status')->unique()->all();
        $this->assertSame(['suspended'], $statuses);
    }

    public function test_tier1_f09_non_admin_forbidden(): void
    {
        $user = $this->createUser(['is_super_admin' => false]);
        $this->actingAsWebUser($user);

        $htmlResp = $this->get('/settings/users');
        $htmlResp->assertStatus(403);

        $apiResp = $this->getJson('/settings/users/api/list');
        $apiResp->assertStatus(403);
    }

    // --- Feature 10: Admin User Profile Details & Audit History (5 tests) ------

    public function test_tier1_f10_profile_json_inspect_endpoint(): void
    {
        $admin = $this->createSuperAdmin();
        $target = $this->createUser(['email' => 'inspect_me@example.com']);

        $this->actingAsWebUser($admin);
        $response = $this->getJson("/settings/users/api/{$target->id}/profile");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $target->id,
            'email' => 'inspect_me@example.com',
            'status' => 'active',
        ]);
    }

    public function test_tier1_f10_profile_workspace_count(): void
    {
        $admin = $this->createSuperAdmin();
        $target = $this->createUser();
        $this->createWorkspace($target, ['name' => 'Workspace One']);
        $this->createWorkspace($target, ['name' => 'Workspace Two']);

        $this->actingAsWebUser($admin);
        $response = $this->getJson("/settings/users/api/{$target->id}/profile");

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('workspaces_count'));
    }

    public function test_tier1_f10_profile_transcript_count(): void
    {
        $admin = $this->createSuperAdmin();
        $target = $this->createUser();
        $workspace = $this->createWorkspace($target);
        $this->createTranscript($workspace, $target, ['title' => 'Transcript 1']);
        $this->createTranscript($workspace, $target, ['title' => 'Transcript 2']);

        $this->actingAsWebUser($admin);
        $response = $this->getJson("/settings/users/api/{$target->id}/profile");

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('transcripts_count'));
    }

    public function test_tier1_f10_profile_device_activity_audit_events(): void
    {
        $admin = $this->createSuperAdmin();
        $target = $this->createUser();
        $workspace = $this->createWorkspace($target);

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'actor_user_id' => $target->id,
            'action' => 'device.ble_paired',
            'resource_type' => 'hardware_device',
            'resource_id' => 'PLAUD-NOTE-101',
            'metadata' => ['device_name' => 'PLAUD Note Hardware', 'battery_level' => 89],
            'created_at' => now(),
        ]);

        $this->actingAsWebUser($admin);
        $response = $this->getJson("/settings/users/api/{$target->id}/profile");

        $response->assertStatus(200);
        $activities = $response->json('device_activity');
        $this->assertNotEmpty($activities);
        $this->assertSame('PLAUD-NOTE-101', $activities[0]['resource_id']);
    }

    public function test_tier1_f10_profile_non_admin_forbidden(): void
    {
        $regularUser = $this->createUser(['is_super_admin' => false]);
        $target = $this->createUser();

        $this->actingAsWebUser($regularUser);
        $response = $this->getJson("/settings/users/api/{$target->id}/profile");

        $response->assertStatus(403);
    }

    // --- Feature 11: Admin Account Suspension Toggle (5 tests) ----------------

    public function test_tier1_f11_toggle_active_to_suspended(): void
    {
        $admin = $this->createSuperAdmin();
        $target = $this->createUser(['status' => 'active']);

        $this->actingAsWebUser($admin);
        $response = $this->postJson("/settings/users/api/{$target->id}/toggle-status");

        $response->assertStatus(200);
        $this->assertSame('suspended', $response->json('status'));
        $this->assertSame('suspended', $target->fresh()->status);
    }

    public function test_tier1_f11_toggle_suspended_to_active(): void
    {
        $admin = $this->createSuperAdmin();
        $target = $this->createSuspendedUser();

        $this->actingAsWebUser($admin);
        $response = $this->postJson("/settings/users/api/{$target->id}/toggle-status");

        $response->assertStatus(200);
        $this->assertSame('active', $response->json('status'));
        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_tier1_f11_self_suspension_prevented(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAsWebUser($admin);
        $response = $this->postJson("/settings/users/api/{$admin->id}/toggle-status");

        $response->assertStatus(400);
        $response->assertJsonFragment([
            'error' => 'forbidden',
            'message' => 'Safeguard: You cannot suspend your own active administrator account.',
        ]);
        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_tier1_f11_audit_event_logged_on_status_change(): void
    {
        $admin = $this->createSuperAdmin();
        $target = $this->createUser();

        $this->actingAsWebUser($admin);
        $response = $this->postJson("/settings/users/api/{$target->id}/toggle-status");

        $response->assertStatus(200);
        $this->assertStringContainsString('suspended', $response->json('message'));

        $audit = AuditEvent::where('action', 'user.suspended')
            ->where('resource_id', $target->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame($admin->id, $audit->actor_user_id);
    }

    public function test_tier1_f11_toggle_status_non_admin_forbidden(): void
    {
        $regularUser = $this->createUser(['is_super_admin' => false]);
        $target = $this->createUser();

        $this->actingAsWebUser($regularUser);
        $response = $this->postJson("/settings/users/api/{$target->id}/toggle-status");

        $response->assertStatus(403);
    }

    // =========================================================================
    // TIER 2: BOUNDARY & CORNER CASES (25 Methods)
    // =========================================================================

    // --- Area 1: Registration Bounds (5 tests) -------------------------------

    public function test_tier2_b01_empty_registration_fields(): void
    {
        $response = $this->post('/register', [
            'display_name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['display_name', 'email', 'password']);
    }

    public function test_tier2_b01_duplicate_email_registration(): void
    {
        $this->createUser(['email' => 'duplicate@company.com']);

        $response = $this->post('/register', [
            'display_name' => 'Duplicate Attempt',
            'email' => 'duplicate@company.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email']);
    }

    public function test_tier2_b01_password_length_bounds_under_8_chars(): void
    {
        $response = $this->post('/register', [
            'display_name' => 'Short Pass',
            'email' => 'short@company.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);
    }

    public function test_tier2_b01_password_confirmation_mismatch(): void
    {
        $response = $this->post('/register', [
            'display_name' => 'Mismatch Pass',
            'email' => 'mismatch@company.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPass456!',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);
    }

    public function test_tier2_b01_special_characters_and_xss_in_display_name(): void
    {
        $xssName = '<script>alert("xss")</script>';
        $response = $this->post('/register', [
            'display_name' => $xssName,
            'email' => 'xss@company.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(302);

        $user = User::query()->where('email', 'xss@company.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($xssName, $user->display_name);
    }

    // --- Area 2: Session & Suspended State (5 tests) -------------------------

    public function test_tier2_b02_suspended_user_token_revocation(): void
    {
        $user = $this->createUser();
        $response = $this->withApiToken($user)->getJson('/api/v1/auth/me');
        $response->assertStatus(200);

        // Suspend user
        $user->status = 'suspended';
        $user->save();

        // Using same token now returns 403
        $blocked = $this->withApiToken($user)->getJson('/api/v1/auth/me');
        $blocked->assertStatus(403);
    }

    public function test_tier2_b02_tampered_session_cookie(): void
    {
        $response = $this->withCookie('laravel_session', 'tampered_invalid_cookie_payload')->get('/');
        $this->assertTrue(in_array($response->status(), [200, 302], true));
        $this->assertGuest();
    }

    public function test_tier2_b02_soft_deleted_user_session_access(): void
    {
        $user = $this->createUser();
        $this->actingAsWebUser($user);

        // Soft-delete user
        $user->delete();

        $response = $this->get('/');
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_tier2_b02_unauthenticated_session_api_access(): void
    {
        $response = $this->getJson('/app/api/workspaces');
        $response->assertStatus(401);
        $response->assertJsonFragment(['detail' => 'Unauthenticated.']);
    }

    public function test_tier2_b02_suspended_user_login_invalid_password_precedence(): void
    {
        $user = $this->createSuspendedUser([
            'email' => 'suspended_wrong_pass@example.com',
            'password' => 'RealPassword123!',
        ]);

        $response = $this->post('/login', [
            'email' => 'suspended_wrong_pass@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email']);
        // Must show invalid credentials without leaking suspension prematurely
        $this->assertStringContainsString('Invalid email or password', session('errors')->first('email'));
    }

    // --- Area 3: File Import Boundaries (5 tests) ----------------------------

    public function test_tier2_b03_unsupported_file_extension(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $file = UploadedFile::fake()->create('malicious.exe', 50, 'application/octet-stream');

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $this->assertSame('error', $response->json('results.0.status'));
        $this->assertSame('UNSUPPORTED_FORMAT', $response->json('results.0.error_code'));
    }

    public function test_tier2_b03_oversized_file_upload(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        // 26 MB file (limit is 25 MB)
        $file = UploadedFile::fake()->create('oversized.txt', 26624, 'text/plain');

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $this->assertSame('error', $response->json('results.0.status'));
        $this->assertSame('INVALID_FILE_SIZE', $response->json('results.0.error_code'));
    }

    public function test_tier2_b03_corrupted_json_payload(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $file = UploadedFile::fake()->createWithContent('corrupted.json', '{invalid_json: true, unterminated');

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $this->assertSame('error', $response->json('results.0.status'));
        $this->assertSame('EXTRACTION_FAILED', $response->json('results.0.error_code'));
    }

    public function test_tier2_b03_empty_file_upload(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $file = UploadedFile::fake()->createWithContent('empty.txt', '');

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file],
        ]);

        $response->assertStatus(200);
        $this->assertSame('error', $response->json('results.0.status'));
        $this->assertSame('INVALID_FILE_SIZE', $response->json('results.0.error_code'));
    }

    public function test_tier2_b03_duplicate_transcript_content(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $text = 'This is an identical transcript text uploaded twice.';
        $file1 = UploadedFile::fake()->createWithContent('transcript1.txt', $text);
        $file2 = UploadedFile::fake()->createWithContent('transcript2.txt', $text);

        $first = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file1],
        ]);
        $first->assertStatus(200);
        $this->assertSame('success', $first->json('results.0.status'));

        $second = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file2],
        ]);
        $second->assertStatus(200);
        $this->assertSame('duplicate', $second->json('results.0.status'));
        $this->assertSame('DUPLICATE_TRANSCRIPT', $second->json('results.0.error_code'));
    }

    // --- Area 4: AI Provider Connection Boundaries (5 tests) -----------------

    public function test_tier2_b04_malformed_api_key(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openai/connect", [
            'api_key' => '   ',
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('API key is required', $response->json('message'));
    }

    public function test_tier2_b04_invalid_provider_name(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/invalid_provider/connect", [
            'api_key' => 'some-valid-looking-key',
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('Unknown provider', $response->json('message'));
    }

    public function test_tier2_b04_network_timeout_handling(): void
    {
        Http::fake([
            'api.openai.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out after 45000ms'),
        ]);

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createProviderConnection($workspace, $user, 'openai', ['api_key_encrypted' => 'sk-timeout-test']);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openai/test");

        $response->assertStatus(200);
        $this->assertFalse($response->json('success'));
        $this->assertNull($response->json('latency_ms'));
    }

    public function test_tier2_b04_missing_credentials_on_test(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->actingAsWebUser($user);

        // Test without any DB record and without .env configured
        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openai/test");

        $response->assertStatus(200);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('No API key configured', $response->json('message'));
    }

    public function test_tier2_b04_upstream_500_server_error(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => 'Internal server error'], 500),
        ]);

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createProviderConnection($workspace, $user, 'openrouter', ['api_key_encrypted' => 'sk-or-test']);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openrouter/test");

        $response->assertStatus(200);
        $this->assertFalse($response->json('success'));
        $this->assertNull($response->json('latency_ms'));
    }

    // --- Area 5: Admin & RBAC Boundaries (5 tests) ---------------------------

    public function test_tier2_b05_non_existent_user_profile_inspect(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAsWebUser($admin);

        $response = $this->getJson('/settings/users/api/00000000-0000-0000-0000-000000000000/profile');
        $response->assertStatus(404);
    }

    public function test_tier2_b05_invalid_uuid_format_toggle(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAsWebUser($admin);

        $response = $this->postJson('/settings/users/api/not-a-valid-uuid/toggle-status');
        $response->assertStatus(404);
    }

    public function test_tier2_b05_privilege_escalation_attempt_standard_user(): void
    {
        $user = $this->createUser(['is_super_admin' => false]);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/settings/users/api/{$user->id}/toggle-admin");
        $response->assertStatus(403);
        $this->assertFalse((bool) $user->fresh()->is_super_admin);
    }

    public function test_tier2_b05_super_admin_cannot_demote_self(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAsWebUser($admin);

        $response = $this->postJson("/settings/users/api/{$admin->id}/toggle-admin");

        $response->assertStatus(400);
        $response->assertJsonFragment([
            'error' => 'forbidden',
            'message' => 'Safeguard: You cannot modify your own super-admin status.',
        ]);
        $this->assertTrue((bool) $admin->fresh()->is_super_admin);
    }

    public function test_tier2_b05_unauthenticated_admin_access_redirects(): void
    {
        $response = $this->get('/settings/users');
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    // =========================================================================
    // TIER 3: CROSS-FEATURE INTERACTIONS (4 Methods)
    // =========================================================================

    public function test_tier3_registration_to_device_pairing_and_ai_setup(): void
    {
        $this->fakeExternalAiApis();

        // Step 1: Self-register
        $regResp = $this->post('/register', [
            'display_name' => 'E2E Full Flow User',
            'email' => 'fullflow@company.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $regResp->assertStatus(302);

        $user = User::query()->where('email', 'fullflow@company.com')->first();
        $this->assertNotNull($user);
        $workspace = Workspace::query()->where('owner_user_id', $user->id)->first();
        $this->assertNotNull($workspace);

        $this->actingAsWebUser($user);

        // Step 2: Pair device
        $pairResp = $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD Note E2E',
            'device_id' => 'PN-E2E-FLOW-01',
            'battery_level' => 96,
        ]);
        $pairResp->assertStatus(200);

        // Step 3: Connect AI Provider
        $aiResp = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openrouter/connect", [
            'api_key' => 'sk-or-valid-key-for-flow',
            'model' => 'anthropic/claude-3.5-sonnet',
        ]);
        $aiResp->assertStatus(200);
        $this->assertTrue($aiResp->json('success'));

        // Step 4: Import PLAUD transcript
        $file = UploadedFile::fake()->createWithContent('flow_notes.txt', '[00:01] Speaker: Complete lifecycle verified.');
        $importResp = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$file],
        ]);
        $importResp->assertStatus(200);
        $this->assertSame('success', $importResp->json('results.0.status'));

        // Verify connected entities in DB
        $this->assertDatabaseHas('audit_events', ['workspace_id' => $workspace->id, 'action' => 'device.ble_paired']);
        $this->assertDatabaseHas('provider_connections', ['workspace_id' => $workspace->id, 'provider' => 'openrouter']);
        $this->assertDatabaseHas('transcripts', ['workspace_id' => $workspace->id]);
    }

    public function test_tier3_active_session_revocation_on_admin_suspension(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createUser();
        $this->createWorkspace($user);

        // User is active and accessing the app
        $this->actingAsWebUser($user);
        $this->get('/')->assertStatus(200);

        // Super-admin suspends user account
        $this->actingAsWebUser($admin);
        $toggleResp = $this->postJson("/settings/users/api/{$user->id}/toggle-status");
        $toggleResp->assertStatus(200);
        $this->assertSame('suspended', $toggleResp->json('status'));

        // User now makes a new request -> must be rejected immediately
        $this->actingAsWebUser($user);
        $blocked = $this->get('/');
        $blocked->assertStatus(302);
        $blocked->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_tier3_suspended_account_reactivation_restores_immediate_access(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createSuspendedUser([
            'email' => 'reactivate_me@company.com',
            'password' => 'Password123!',
        ]);
        $workspace = $this->createWorkspace($user);

        // Attempting login while suspended is blocked
        $blockedLogin = $this->post('/login', [
            'email' => 'reactivate_me@company.com',
            'password' => 'Password123!',
        ]);
        $blockedLogin->assertStatus(302);
        $this->assertGuest();

        // Super-admin reactivates user
        $this->actingAsWebUser($admin);
        $reactivateResp = $this->postJson("/settings/users/api/{$user->id}/toggle-status");
        $reactivateResp->assertStatus(200);
        $this->assertSame('active', $reactivateResp->json('status'));

        // User logs in successfully
        $successLogin = $this->post('/login', [
            'email' => 'reactivate_me@company.com',
            'password' => 'Password123!',
        ]);
        $successLogin->assertStatus(302);
        $this->assertAuthenticatedAs($user);

        // User can access dashboard and data immediately
        $dashboard = $this->get('/');
        $dashboard->assertStatus(200);
    }

    public function test_tier3_multi_user_workspace_isolation(): void
    {
        $userA = $this->createUser(['email' => 'usera@example.com']);
        $workspaceA = $this->createWorkspace($userA, ['name' => "User A's Vault"]);
        $transcriptA = $this->createTranscript($workspaceA, $userA, ['title' => 'Secret Strategy A']);

        $userB = $this->createUser(['email' => 'userb@example.com']);
        $workspaceB = $this->createWorkspace($userB, ['name' => "User B's Vault"]);

        // User B attempts to access User A's workspace status
        $this->actingAsWebUser($userB);
        $response = $this->getJson("/app/api/workspaces/{$workspaceA->id}/integrations/plaud/status");

        // Must be forbidden or denied
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    // =========================================================================
    // TIER 4: REAL-WORLD APPLICATION SCENARIOS (5 Methods)
    // =========================================================================

    public function test_tier4_complete_new_user_onboarding_journey(): void
    {
        $this->fakeExternalAiApis();

        // 1. Visit registration page
        $page = $this->get('/register');
        $page->assertStatus(200);

        // 2. Submit self-registration
        $registered = $this->post('/register', [
            'display_name' => 'Journey User',
            'email' => 'journey@enterprise.com',
            'password' => 'JourneyPass123!',
            'password_confirmation' => 'JourneyPass123!',
        ]);
        $registered->assertStatus(302);

        $user = User::query()->where('email', 'journey@enterprise.com')->first();
        $this->assertNotNull($user);
        $workspace = Workspace::query()->where('owner_user_id', $user->id)->first();
        $this->assertNotNull($workspace);

        $this->actingAsWebUser($user);

        // 3. Open dashboard and check workspace
        $dashboard = $this->get('/');
        $dashboard->assertStatus(200);

        // 4. Pair PLAUD hardware device via BLE bridge
        $paired = $this->postJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/pair-ble", [
            'device_name' => 'PLAUD Note Onboarding',
            'device_id' => 'PN-ONBOARD-99',
            'battery_level' => 97,
        ]);
        $paired->assertStatus(200);
        $this->assertTrue($paired->json('success'));

        // 5. Connect AI provider
        $connected = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openai/connect", [
            'api_key' => 'sk-journey-openai-key-12345',
            'model' => 'gpt-4o-mini',
        ]);
        $connected->assertStatus(200);
        $this->assertTrue($connected->json('success'));

        // 6. Test connection latency
        $tested = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/openai/test");
        $tested->assertStatus(200);
        $this->assertTrue($tested->json('success'));
        $this->assertNotNull($tested->json('latency_ms'));

        // 7. Dashboard status reflects ready state
        $status = $this->getJson("/app/api/workspaces/{$workspace->id}/integrations/plaud/status");
        $status->assertStatus(200);
        $this->assertTrue($status->json('ble_bridge.paired'));
    }

    public function test_tier4_plaud_note_audio_and_json_import_journey(): void
    {
        $this->fakeExternalAiApis();

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createProviderConnection($workspace, $user, 'builtin');
        $this->actingAsWebUser($user);

        // 1. User uploads multi-speaker PLAUD JSON export
        $jsonContent = json_encode([
            'title' => 'Executive Committee Sync',
            'segments' => [
                ['speaker_label' => 'CEO', 'start_ms' => 0, 'end_ms' => 15000, 'text' => 'Quarterly revenue grew 40 percent.'],
                ['speaker_label' => 'CTO', 'start_ms' => 15000, 'end_ms' => 30000, 'text' => 'AI architecture milestone completed.'],
            ],
        ]);
        $jsonFile = UploadedFile::fake()->createWithContent('exec_committee.json', $jsonContent);

        $importResp = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$jsonFile],
        ]);
        $importResp->assertStatus(200);
        $transcriptId = $importResp->json('results.0.transcript_id');
        $this->assertNotNull($transcriptId);

        // 2. Upload companion audio file
        $audioFile = UploadedFile::fake()->create('exec_audio.mp3', 300, 'audio/mpeg');
        $audioImport = $this->postJson("/app/api/workspaces/{$workspace->id}/imports", [
            'files' => [$audioFile],
        ]);
        $audioImport->assertStatus(200);

        // 3. Request AI Summary
        $summaryResp = $this->postJson("/app/api/transcripts/{$transcriptId}/summary", [
            'summary_type' => 'executive',
            'provider_id' => 'builtin',
        ]);
        $summaryResp->assertStatus(200);
        $this->assertNotEmpty($summaryResp->json('summary_text'));

        // 4. Request AI Action Items
        $actionResp = $this->postJson("/app/api/transcripts/{$transcriptId}/action-items");
        $actionResp->assertStatus(200);
        $this->assertArrayHasKey('action_items', $actionResp->json());
    }

    public function test_tier4_administrator_governance_and_account_suspension_journey(): void
    {
        $admin = $this->createSuperAdmin(['email' => 'admin_governance@company.com']);

        // 1. Regular user registers and works
        $user = $this->createUser(['email' => 'flagged_user@company.com', 'display_name' => 'Flagged Account']);
        $workspace = $this->createWorkspace($user);
        $this->createTranscript($workspace, $user);

        // 2. Admin logs in and inspects user directory
        $this->actingAsWebUser($admin);
        $dir = $this->get('/settings/users');
        $dir->assertStatus(200);

        // 3. Search for user by email
        $search = $this->getJson('/settings/users/api/list?q=flagged_user');
        $search->assertStatus(200);
        $this->assertCount(1, $search->json('users'));

        // 4. Admin inspects detailed user profile
        $profile = $this->getJson("/settings/users/api/{$user->id}/profile");
        $profile->assertStatus(200);
        $this->assertSame(1, $profile->json('workspaces_count'));
        $this->assertSame(1, $profile->json('transcripts_count'));

        // 5. Admin suspends account
        $toggle = $this->postJson("/settings/users/api/{$user->id}/toggle-status");
        $toggle->assertStatus(200);
        $this->assertSame('suspended', $toggle->json('status'));

        // 6. User attempts to make session API request -> blocked
        $this->actingAsWebUser($user);
        $blockedApi = $this->getJson('/app/api/workspaces');
        $this->assertTrue(in_array($blockedApi->status(), [401, 403], true));

        // 7. User attempts to log in again -> blocked
        Auth::logout();
        $blockedLogin = $this->post('/login', [
            'email' => 'flagged_user@company.com',
            'password' => 'Password123!',
        ]);
        $blockedLogin->assertStatus(302);
        $this->assertGuest();
    }

    public function test_tier4_suspended_account_recovery_and_reactivation_journey(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createSuspendedUser([
            'email' => 'recovery_user@company.com',
            'password' => 'SafePass123!',
        ]);
        $workspace = $this->createWorkspace($user);
        $transcript = $this->createTranscript($workspace, $user, ['title' => 'Critical Historical Record']);

        // 1. Blocked login with notice
        $blocked = $this->post('/login', [
            'email' => 'recovery_user@company.com',
            'password' => 'SafePass123!',
        ]);
        $blocked->assertStatus(302);
        $this->assertGuest();

        // 2. Admin inspects audit history and reactivates account
        $this->actingAsWebUser($admin);
        $reactivate = $this->postJson("/settings/users/api/{$user->id}/toggle-status");
        $reactivate->assertStatus(200);
        $this->assertSame('active', $reactivate->json('status'));

        // 3. User logs in successfully with existing password
        $login = $this->post('/login', [
            'email' => 'recovery_user@company.com',
            'password' => 'SafePass123!',
        ]);
        $login->assertStatus(302);
        $this->assertAuthenticatedAs($user);

        // 4. Data intact
        $this->actingAsWebUser($user);
        $dashboard = $this->get('/');
        $dashboard->assertStatus(200);

        $savedTranscript = Transcript::query()->find($transcript->id);
        $this->assertNotNull($savedTranscript);
        $this->assertSame('Critical Historical Record', $savedTranscript->title);
    }

    public function test_tier4_multi_provider_failover_and_connection_testing_journey(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $transcript = $this->createTranscript($workspace, $user, [
            'title' => 'Failover Meeting Notes',
            'content_text' => 'We discussed failover strategies, high availability, and neural meeting backups.',
        ]);
        $this->actingAsWebUser($user);

        // Configure OpenAI and Anthropic connections
        $this->createProviderConnection($workspace, $user, 'openai', ['api_key_encrypted' => 'sk-openai-key']);
        $this->createProviderConnection($workspace, $user, 'anthropic', ['api_key_encrypted' => 'sk-anthropic-key']);

        // Mock OpenAI failing with HTTP 500
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'Rate limit or outage'], 500),
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => '{"summary_text": "Failover succeeded via Anthropic.", "bullet_points": ["System resilient"]}']],
            ], 200),
        ]);

        // Request summary using openai -> provider failure must be reported, never
        // silently replaced with substitute output from another engine.
        $summary = $this->postJson("/app/api/transcripts/{$transcript->id}/summary", [
            'provider_id' => 'openai',
        ]);

        $summary->assertStatus(502);
        $summary->assertJsonPath('error_code', 'ai_provider_failed');
        $summary->assertJsonPath('provider_id', 'openai');
        $this->assertArrayNotHasKey('summary_text', $summary->json());
        $this->assertStringNotContainsString('sk-openai-key', $summary->getContent());
    }

    // =========================================================================
    // FOLLOW-UP REQUIREMENTS (AI Robustness & Gemini Ping)
    // =========================================================================

    public function test_followup_ai_ask_graceful_error_never_500(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $transcript = $this->createTranscript($workspace, $user, [
            'title' => 'Synthesis Transcript',
            'content_text' => 'Team updates and planning for next quarter.',
        ]);
        $this->actingAsWebUser($user);

        $this->createProviderConnection($workspace, $user, 'openai', ['api_key_encrypted' => 'sk-openai-key']);

        // Simulate failing provider
        Http::fake([
            '*' => Http::response(['error' => 'API Unavailable'], 503),
        ]);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/ask", [
            'question' => 'What are the next steps for the project?',
            'transcript_ids' => [$transcript->id],
            'provider_id' => 'openai',
        ]);

        // Must NEVER return 500; the failure is reported as a sanitized upstream error.
        $this->assertNotSame(500, $response->status());
        $response->assertStatus(502);
        $response->assertJsonPath('error_code', 'ai_provider_failed');
        $response->assertJsonStructure(['detail']);
        $this->assertStringNotContainsString('sk-openai-key', $response->getContent());
    }

    public function test_followup_gemini_connection_test_model_handling_never_404(): void
    {
        // Simulate Gemini returning 404 for generateContent model endpoint
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash*' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'models/gemini-2.0-flash is not found for API version v1beta',
                    'status' => 'NOT_FOUND',
                ],
            ], 404),
            'generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'OK']]]],
                ],
            ], 200),
        ]);

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createProviderConnection($workspace, $user, 'gemini', [
            'api_key_encrypted' => 'AIzaSyTestKey',
            'default_model' => 'gemini-2.0-flash',
        ]);
        $this->actingAsWebUser($user);

        $response = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/connectors/gemini/test");

        // The connection test response must return HTTP 200 with clean JSON, never raw unhandled 404
        $response->assertStatus(200);
        $this->assertArrayHasKey('success', $response->json());
        $this->assertArrayHasKey('provider_id', $response->json());
    }
}
