<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Guards against two regressions:
 *  - the /demo credentials page being reachable unless explicitly enabled;
 *  - AI and export paths presenting placeholder content as if it were real output.
 */
class HonestOutputAndDemoGateTest extends TestCase
{
    /** Phrases from the former hardcoded placeholder content. */
    private const PLACEHOLDER_PHRASES = [
        'Executive synthesis: The discussion focused on',
        'Core meeting overview',
        'Verify Bluetooth Low Energy device discovery',
        'Engineering Lead',
        'Cross-platform audio ingest and speaker diarization verified',
        'Verify BLE GATT sync',
        'The team aligned on concrete deliverables',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        // Make sure a developer .env key can never route these tests to a real provider.
        foreach (['OPENAI_API_KEY', 'GEMINI_API_KEY', 'ANTHROPIC_API_KEY', 'OPENROUTER_API_KEY'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }

    // --- /demo gate ---------------------------------------------------------

    public function test_demo_page_is_not_found_by_default(): void
    {
        config(['app.demo_login_enabled' => false]);

        $this->get('/demo')->assertNotFound();
        $this->get('/login')->assertOk()->assertDontSee('Demo Accounts');
    }

    public function test_demo_page_is_served_only_when_explicitly_enabled(): void
    {
        config(['app.demo_login_enabled' => true]);

        $this->get('/demo')->assertOk();
        $this->get('/login')->assertOk()->assertSee('Demo Accounts');
    }

    // --- builtin engine -----------------------------------------------------

    public function test_builtin_summary_is_derived_only_from_transcript(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $transcript = $this->createTranscript($workspace, $user, [
            'content_text' => "Maria: The warehouse lease renewal is due in March.\nTom: Budget for the lease was approved yesterday.",
        ]);
        $this->actingAsWebUser($user);

        $res = $this->postJson("/app/api/transcripts/{$transcript->id}/summary", ['provider_id' => 'builtin']);

        $res->assertOk()->assertJsonPath('provider_used', 'builtin');
        $this->assertStringContainsString('warehouse lease renewal', $res->json('summary_text'));
        $this->assertStringContainsString('no AI model used', $res->json('summary_text'));
        $this->assertNoPlaceholders($res->getContent());
    }

    public function test_builtin_action_items_are_empty_when_none_are_present(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $transcript = $this->createTranscript($workspace, $user, [
            'content_text' => "Maria: Nice weather today.\nTom: Indeed it is.",
        ]);
        $this->actingAsWebUser($user);

        $res = $this->postJson("/app/api/transcripts/{$transcript->id}/action-items");

        $res->assertOk()->assertJsonPath('total_count', 0);
        $this->assertNoPlaceholders($res->getContent());
    }

    public function test_builtin_action_items_use_transcript_speaker_and_no_invented_due_date(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $transcript = $this->createTranscript($workspace, $user, [
            'content_text' => 'Maria: I will send the signed contract to legal.',
        ]);
        $this->actingAsWebUser($user);

        $res = $this->postJson("/app/api/transcripts/{$transcript->id}/action-items");

        $res->assertOk()
            ->assertJsonPath('action_items.0.assignee', 'Maria')
            ->assertJsonPath('action_items.0.due_date', null);
    }

    public function test_builtin_ask_reports_insufficient_evidence_when_nothing_matches(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $transcript = $this->createTranscript($workspace, $user, [
            'content_text' => 'Maria: The warehouse lease renewal is due in March.',
        ]);
        $this->actingAsWebUser($user);

        $res = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/ask", [
            'question' => 'What did they decide about hiring engineers?',
            'transcript_ids' => [$transcript->id],
            'provider_id' => 'builtin',
        ]);

        $res->assertOk()
            ->assertJsonPath('has_sufficient_evidence', false)
            ->assertJsonPath('citations', []);
        $this->assertNoPlaceholders($res->getContent());
    }

    public function test_builtin_ask_cites_matching_passage(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $transcript = $this->createTranscript($workspace, $user, [
            'title' => 'Ops Sync',
            'content_text' => "Maria: The warehouse lease renewal is due in March.\nTom: Lunch was good.",
        ]);
        $this->actingAsWebUser($user);

        $res = $this->postJson("/app/api/workspaces/{$workspace->id}/ai/ask", [
            'question' => 'When is the warehouse lease renewal due?',
            'transcript_ids' => [$transcript->id],
            'provider_id' => 'builtin',
        ]);

        $res->assertOk()
            ->assertJsonPath('has_sufficient_evidence', true)
            ->assertJsonPath('citations.0.transcript_id', $transcript->id)
            ->assertJsonPath('citations.0.speaker', 'Maria');
    }

    // --- exports ------------------------------------------------------------

    public function test_export_without_ai_sections_contains_only_transcript(): void
    {
        Storage::fake('local');
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $transcript = $this->createTranscript($workspace, $user, [
            'title' => 'Plain Export',
            'content_text' => 'Maria: The warehouse lease renewal is due in March.',
        ]);
        $this->actingAsWebUser($user);

        $res = $this->postJson("/app/api/workspaces/{$workspace->id}/exports", [
            'transcript_ids' => [$transcript->id],
            'format' => 'both',
            'template' => 'clean_transcript',
            'include_ai_summary' => false,
            'include_action_items' => false,
        ]);
        $res->assertCreated()->assertJsonPath('status', 'completed');

        [$docx, $pdf] = $this->readExportDocuments($res->json('id'), 'Plain-Export');
        $this->assertStringNotContainsString('Executive Summary', $docx);
        $this->assertStringNotContainsString('Action Items', $docx);
        $this->assertStringNotContainsString('SUMMARY', $pdf);
        $this->assertStringContainsString('warehouse lease renewal', $docx);
        $this->assertNoPlaceholders($docx . $pdf);
    }

    public function test_export_ai_sections_use_provider_output_for_this_transcript(): void
    {
        Storage::fake('local');
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => '{"summary_text": "Lease renewal discussed for March.", "bullet_points": ["Renewal due March"]}']]]])
                ->push(['choices' => [['message' => ['content' => '{"action_items": [{"task": "Send contract to legal", "assignee": "Maria", "due_date": "Friday", "status": "pending"}]}']]]]),
        ]);

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createProviderConnection($workspace, $user, 'openai', [
            'api_key_encrypted' => 'sk-test-key',
            'default_model' => 'gpt-4o-mini',
        ]);
        $transcript = $this->createTranscript($workspace, $user, [
            'title' => 'AI Export',
            'content_text' => 'Maria: I will send the contract to legal by Friday.',
        ]);
        $this->actingAsWebUser($user);

        $res = $this->postJson("/app/api/workspaces/{$workspace->id}/exports", [
            'transcript_ids' => [$transcript->id],
            'format' => 'docx',
            'include_ai_summary' => true,
            'include_action_items' => true,
        ]);
        $res->assertCreated()->assertJsonPath('status', 'completed');

        [$docx] = $this->readExportDocuments($res->json('id'), 'AI-Export', ['docx']);
        $this->assertStringContainsString('Lease renewal discussed for March.', $docx);
        $this->assertStringContainsString('Send contract to legal', $docx);
        $this->assertStringContainsString('Maria', $docx);
        $this->assertNoPlaceholders($docx);
    }

    public function test_export_reports_ai_failure_instead_of_inventing_content(): void
    {
        Storage::fake('local');
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'outage'], 500)]);

        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createProviderConnection($workspace, $user, 'openai', [
            'api_key_encrypted' => 'sk-test-key',
            'default_model' => 'gpt-4o-mini',
        ]);
        $transcript = $this->createTranscript($workspace, $user, [
            'title' => 'Failing Export',
            'content_text' => 'Maria: The warehouse lease renewal is due in March.',
        ]);
        $this->actingAsWebUser($user);

        $res = $this->postJson("/app/api/workspaces/{$workspace->id}/exports", [
            'transcript_ids' => [$transcript->id],
            'format' => 'both',
            'template' => 'meeting_minutes',
        ]);
        $res->assertCreated();

        [$docx, $pdf] = $this->readExportDocuments($res->json('id'), 'Failing-Export');
        $this->assertStringContainsString('AI summary unavailable', $docx);
        $this->assertStringContainsString('AI action items unavailable', $pdf);
        $this->assertStringNotContainsString('sk-test-key', $docx . $pdf);
        $this->assertNoPlaceholders($docx . $pdf);

        $manifest = json_decode($this->readArchiveEntry($res->json('id'), 'manifest.json'), true);
        $this->assertNotEmpty($manifest['ai_warnings']);
        $this->assertSame($transcript->id, $manifest['ai_warnings'][0]['transcript_id']);
    }

    // --- helpers ------------------------------------------------------------

    /**
     * @param list<string> $formats
     * @return list<string> document.xml text for docx, raw bytes for pdf, in the order given
     */
    private function readExportDocuments(string $exportId, string $slug, array $formats = ['docx', 'pdf']): array
    {
        $out = [];
        foreach ($formats as $fmt) {
            $bytes = $this->readArchiveEntry($exportId, "{$slug}.{$fmt}");
            if ($fmt === 'docx') {
                $tmp = tempnam(sys_get_temp_dir(), 'docx_test_');
                file_put_contents($tmp, $bytes);
                $zip = new ZipArchive();
                $this->assertTrue($zip->open($tmp) === true, 'Exported DOCX must be a valid ZIP package.');
                $xml = (string) $zip->getFromName('word/document.xml');
                $zip->close();
                @unlink($tmp);
                $out[] = $xml;
            } else {
                $this->assertStringStartsWith('%PDF-', $bytes);
                $out[] = $bytes;
            }
        }

        return $out;
    }

    private function readArchiveEntry(string $exportId, string $entry): string
    {
        $response = $this->get("/app/api/exports/{$exportId}/download");
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'export_test_');
        file_put_contents($tmp, $response->getContent());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true, 'Export archive must be a valid ZIP.');
        $content = $zip->getFromName($entry);
        $zip->close();
        @unlink($tmp);

        $this->assertNotFalse($content, "Archive entry {$entry} must exist.");

        return (string) $content;
    }

    private function assertNoPlaceholders(string $haystack): void
    {
        foreach (self::PLACEHOLDER_PHRASES as $phrase) {
            $this->assertStringNotContainsString($phrase, $haystack, "Placeholder text leaked into output: {$phrase}");
        }
    }
}
