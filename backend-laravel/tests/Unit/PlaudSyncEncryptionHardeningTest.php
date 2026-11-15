<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PlaudSyncEncryptionHardeningTest extends TestCase
{
    private string $plaudSyncSource;
    private string $providerConnectionSource;
    private string $migrationSource;
    private string $mobileAiServiceSource;

    protected function setUp(): void
    {
        parent::setUp();
        $backendDir = dirname(__DIR__, 2);
        $projectDir = dirname($backendDir);

        $plaudSyncPath = $backendDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'PlaudSyncService.php';
        $providerConnectionPath = $backendDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'ProviderConnection.php';
        $migrationPath = $backendDir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2026_11_13_000003_create_exports_audits_and_providers_tables.php';
        $mobileAiPath = $projectDir . DIRECTORY_SEPARATOR . 'mobile' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'ai_service.dart';

        $this->assertFileExists($plaudSyncPath);
        $this->assertFileExists($providerConnectionPath);
        $this->assertFileExists($migrationPath);
        $this->assertFileExists($mobileAiPath);

        $this->plaudSyncSource = (string) file_get_contents($plaudSyncPath);
        $this->providerConnectionSource = (string) file_get_contents($providerConnectionPath);
        $this->migrationSource = (string) file_get_contents($migrationPath);
        $this->mobileAiServiceSource = (string) file_get_contents($mobileAiPath);
    }

    public function test_plaud_sync_service_has_zero_raw_db_table_queries_on_provider_connections(): void
    {
        // Enforce that DB::table('provider_connections') is completely eliminated from PlaudSyncService
        $this->assertStringNotContainsString(
            "DB::table('provider_connections')",
            $this->plaudSyncSource,
            'PlaudSyncService must not perform raw DB queries on provider_connections to avoid encryption bypass'
        );
        $this->assertStringNotContainsString(
            'DB::table("provider_connections")',
            $this->plaudSyncSource
        );
    }

    public function test_plaud_sync_service_imports_and_uses_eloquent_provider_connection(): void
    {
        $this->assertStringContainsString('use App\Models\ProviderConnection;', $this->plaudSyncSource);
        $this->assertStringContainsString('ProviderConnection::updateOrCreate(', $this->plaudSyncSource);
        $this->assertStringContainsString('ProviderConnection::query()', $this->plaudSyncSource);
    }

    public function test_provider_connection_model_has_hardened_encryption_attribute(): void
    {
        $this->assertStringContainsString('apiKeyEncrypted(): Attribute', $this->providerConnectionSource);
        $this->assertStringContainsString('Crypt::encryptString', $this->providerConnectionSource);
        $this->assertStringContainsString('Crypt::decryptString', $this->providerConnectionSource);
        $this->assertStringContainsString('DecryptException', $this->providerConnectionSource);
    }

    public function test_provider_connection_hides_encrypted_key_from_serialization(): void
    {
        $this->assertMatchesRegularExpression(
            '/protected\s+\$hidden\s*=\s*\[[\s\S]*?\'api_key_encrypted\'[\s\S]*?\];/m',
            $this->providerConnectionSource,
            'api_key_encrypted must be hidden from JSON serialization'
        );
    }

    public function test_database_migration_defines_api_key_encrypted_column_as_text(): void
    {
        $this->assertStringContainsString(
            "\$table->text('api_key_encrypted')->nullable();",
            $this->migrationSource,
            'Migration must define api_key_encrypted as text to accommodate AES-256 payload'
        );
    }

    public function test_mobile_ai_service_has_no_direct_upstream_vendor_calls(): void
    {
        $disallowedUrls = [
            'api.openai.com',
            'openrouter.ai/api',
            'generativelanguage.googleapis.com',
            'api.anthropic.com',
        ];

        foreach ($disallowedUrls as $url) {
            $this->assertStringNotContainsString(
                $url,
                $this->mobileAiServiceSource,
                "Mobile client must not call upstream '{$url}' directly"
            );
        }

        // Verify that _callLlm is completely removed
        $this->assertStringNotContainsString(
            '_callLlm',
            $this->mobileAiServiceSource,
            'Direct LLM network helper _callLlm must be removed from mobile code'
        );
    }

    public function test_mobile_ai_service_immediately_clears_api_keys_and_routes_through_proxy(): void
    {
        // Enforce immediate wiping of in-memory API key
        $this->assertStringContainsString('config.apiKey = null;', $this->mobileAiServiceSource);
        
        // Enforce backend proxy endpoints
        $this->assertStringContainsString('_postBackend', $this->mobileAiServiceSource);
        $this->assertStringContainsString('/api/v1/workspaces/', $this->mobileAiServiceSource);
        $this->assertStringContainsString('/api/v1/transcripts/', $this->mobileAiServiceSource);
    }
}
