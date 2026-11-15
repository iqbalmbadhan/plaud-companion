<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AiServiceTest extends TestCase
{
    private string $aiServiceSource;
    private string $routesSource;
    private string $bladeSource;

    protected function setUp(): void
    {
        parent::setUp();
        $base = dirname(__DIR__, 2);
        $aiServicePath = $base . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'AiService.php';
        $routesPath = $base . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
        $bladePath = $base . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'workspace.blade.php';

        $this->assertFileExists($aiServicePath);
        $this->assertFileExists($routesPath);
        $this->assertFileExists($bladePath);

        $this->aiServiceSource = (string) file_get_contents($aiServicePath);
        $this->routesSource = (string) file_get_contents($routesPath);
        $this->bladeSource = (string) file_get_contents($bladePath);
    }

    public function test_ai_service_defines_exactly_five_unique_providers(): void
    {
        // Assert PROVIDERS constant defines exactly 5 distinct connectors
        $this->assertMatchesRegularExpression('/private\s+const\s+PROVIDERS\s*=\s*\[([\s\S]*?)\];/m', $this->aiServiceSource);
        
        $expectedProviders = ['openrouter', 'gemini', 'anthropic', 'openai', 'builtin'];
        foreach ($expectedProviders as $provider) {
            $this->assertStringContainsString("'{$provider}' => [", $this->aiServiceSource);
        }

        // Verify deduplication: ensure the duplicate append at the end of listConnectors() is removed
        $this->assertStringNotContainsString("'tag' => 'Active Fallback'", $this->aiServiceSource);
        
        // Extract listConnectors method body and verify single mapping loop and no hardcoded builtin duplicate
        $this->assertMatchesRegularExpression('/public\s+function\s+listConnectors[\s\S]*?return\s+\$out;\s*\}/m', $this->aiServiceSource);
        preg_match('/public\s+function\s+listConnectors[\s\S]*?return\s+\$out;\s*\}/m', $this->aiServiceSource, $m);
        $listMethod = $m[0] ?? '';
        
        preg_match_all("/'provider_id'\s*=>\s*\\\$pid/", $listMethod, $matches);
        $this->assertCount(1, $matches[0], 'listConnectors should only have one unified loop mapping providers');
        $this->assertStringNotContainsString("'provider_id' => 'builtin'", $listMethod, 'listConnectors must not have hardcoded duplicate builtin connector');
    }

    public function test_builtin_provider_has_rich_description_and_online_mask(): void
    {
        $this->assertStringContainsString(
            'Embedded local neural engine for real-time diarization parsing, executive summaries, and action matrix synthesis.',
            $this->aiServiceSource
        );
        $this->assertStringContainsString(
            "\$maskedKey = 'Embedded Engine (Online)';",
            $this->aiServiceSource
        );
    }

    public function test_gemini_provider_has_updated_model_matrix(): void
    {
        $this->assertStringContainsString('gemini-2.0-flash', $this->aiServiceSource);
        $this->assertStringContainsString('gemini-2.5-flash', $this->aiServiceSource);
        $this->assertStringContainsString('gemini-1.5-flash', $this->aiServiceSource);
    }

    public function test_openrouter_connector_exposes_easy_connect_url(): void
    {
        $this->assertStringContainsString('https://openrouter.ai/auth?callback_url=', $this->aiServiceSource);
        $this->assertStringContainsString("'easy_connect_url' => \$easyConnectUrl", $this->aiServiceSource);
    }

    public function test_connector_schema_contains_all_required_contract_fields(): void
    {
        $requiredFields = [
            'provider_id',
            'provider',
            'name',
            'description',
            'is_configured',
            'connected',
            'is_active',
            'default_model',
            'active_model',
            'available_models',
            'models',
            'masked_key',
            'setup_help_url',
            'easy_connect_url',
            'auth_type',
            'tag',
        ];

        foreach ($requiredFields as $field) {
            $this->assertStringContainsString("'{$field}' =>", $this->aiServiceSource, "Connector schema must include '{$field}'");
        }
    }

    public function test_ping_provider_is_public_and_measures_hrtime_latency(): void
    {
        $this->assertMatchesRegularExpression(
            '/public\s+function\s+pingProvider\s*\(/',
            $this->aiServiceSource,
            'pingProvider must have public visibility for testing and verification'
        );

        $this->assertStringContainsString('hrtime(true)', $this->aiServiceSource);
        $this->assertStringContainsString('1_000_000', $this->aiServiceSource);
    }

    public function test_ping_provider_error_handling_redacts_credentials(): void
    {
        $this->assertStringContainsString('[REDACTED]', $this->aiServiceSource);
        $this->assertStringContainsString('str_replace($apiKey, \'[REDACTED]\'', $this->aiServiceSource);
        $this->assertStringContainsString('Bearer', $this->aiServiceSource);
        $this->assertStringContainsString('sk-', $this->aiServiceSource);
    }

    public function test_gemini_passes_api_key_via_header_instead_of_url_query(): void
    {
        $this->assertStringContainsString("'x-goog-api-key' => \$apiKey", $this->aiServiceSource);
        $this->assertStringNotContainsString(':generateContent?key=', $this->aiServiceSource);
    }

    public function test_routes_register_workspace_alias_endpoints_for_summary_and_action_items(): void
    {
        $this->assertStringContainsString("Route::post('/workspaces/{workspaceId}/transcripts/{transcriptId}/summary'", $this->routesSource);
        $this->assertStringContainsString("Route::post('/workspaces/{workspaceId}/transcripts/{transcriptId}/action-items'", $this->routesSource);
        $this->assertStringContainsString("Route::post('/transcripts/{transcriptId}/summary'", $this->routesSource);
        $this->assertStringContainsString("Route::post('/transcripts/{transcriptId}/action-items'", $this->routesSource);
    }

    public function test_workspace_view_renders_easy_connect_button(): void
    {
        $this->assertStringContainsString('c.easy_connect_url', $this->bladeSource);
        $this->assertStringContainsString('1-Click Connect ↗', $this->bladeSource);
    }
}
