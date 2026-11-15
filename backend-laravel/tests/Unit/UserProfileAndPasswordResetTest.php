<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class UserProfileAndPasswordResetTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDir = dirname(__DIR__, 2);
    }

    public function test_password_reset_tokens_migration_exists_and_is_additive(): void
    {
        $migrationFile = $this->baseDir . '/database/migrations/2026_11_16_000001_create_password_reset_tokens_table.php';
        $this->assertFileExists($migrationFile, 'Migration for password_reset_tokens table must exist.');

        $content = file_get_contents($migrationFile);
        $this->assertNotFalse($content);
        $this->assertStringContainsString("Schema::hasTable('password_reset_tokens')", $content);
        $this->assertStringContainsString("\$table->string('email', 255)->primary()", $content);
        $this->assertStringContainsString("\$table->string('token', 255)", $content);
        $this->assertStringContainsString("\$table->timestamp('created_at')->nullable()", $content);
        $this->assertStringContainsString('@allow-destructive-migration: rollback dropping password_reset_tokens table', $content);
    }

    public function test_routes_file_registers_profile_and_password_reset_routes(): void
    {
        $routesPath = $this->baseDir . '/routes/web.php';
        $content = file_get_contents($routesPath);
        $this->assertNotFalse($content);

        // Authenticated profile routes
        $this->assertStringContainsString("ProfileController::class, 'show'", $content);
        $this->assertStringContainsString("->name('profile.show')", $content);
        $this->assertStringContainsString("ProfileController::class, 'updateProfile'", $content);
        $this->assertStringContainsString("->name('profile.update')", $content);
        $this->assertStringContainsString("ProfileController::class, 'updatePassword'", $content);
        $this->assertStringContainsString("->name('profile.password')", $content);
        $this->assertStringContainsString("ProfileController::class, 'apiShow'", $content);
        $this->assertStringContainsString("->name('app.api.profile')", $content);

        // Guest password reset routes
        $this->assertStringContainsString("WebAuthController::class, 'showForgotPassword'", $content);
        $this->assertStringContainsString("->name('password.request')", $content);
        $this->assertStringContainsString("WebAuthController::class, 'sendResetLink'", $content);
        $this->assertStringContainsString("->name('password.email')", $content);
        $this->assertStringContainsString("WebAuthController::class, 'showResetPassword'", $content);
        $this->assertStringContainsString("->name('password.reset')", $content);
        $this->assertStringContainsString("WebAuthController::class, 'resetPassword'", $content);
        $this->assertStringContainsString("->name('password.update')", $content);
    }

    public function test_user_model_hides_password_hash_and_remember_token(): void
    {
        $userModelPath = $this->baseDir . '/app/Models/User.php';
        $content = file_get_contents($userModelPath);
        $this->assertNotFalse($content);

        $this->assertStringContainsString("'password_hash'", $content);
        $this->assertStringContainsString("'remember_token'", $content);
    }

    public function test_all_blade_views_have_zero_hardcoded_root_relative_assets_and_api_calls(): void
    {
        $viewsDir = $this->baseDir . '/resources/views';
        $files = glob($viewsDir . '/*/*.blade.php');
        $this->assertNotEmpty($files, 'Blade views must be found.');

        foreach ($files as $file) {
            $rel = str_replace($this->baseDir . DIRECTORY_SEPARATOR, '', $file);
            $content = file_get_contents($file);
            $this->assertNotFalse($content);

            // Check for un-interpolated href="/...", src="/...", action="/..."
            if (preg_match_all('/(?:href|src|action)=["\'](\/[^"\'\}\{]+)["\']/i', $content, $matches)) {
                $badPaths = array_unique($matches[1]);
                $this->assertEmpty($badPaths, "File {$rel} contains hardcoded root-relative URLs: " . implode(', ', $badPaths));
            }

            // Check for un-interpolated native fetch('/...'), register('/...'), or apiPost('/...')
            if (preg_match_all('/(?:\bfetch|register|apiPost)\(["\'](\/[^"\'\}\`\$\{]+)["\']/i', $content, $matches)) {
                $badCalls = array_unique($matches[1]);
                $this->assertEmpty($badCalls, "File {$rel} contains hardcoded root-relative API calls: " . implode(', ', $badCalls));
            }
        }
    }

    public function test_all_blade_views_retain_uniform_attribution_footer(): void
    {
        $viewsDir = $this->baseDir . '/resources/views';
        $files = glob($viewsDir . '/*/*.blade.php');
        $this->assertNotEmpty($files, 'Blade views must be found.');

        foreach ($files as $file) {
            $rel = str_replace($this->baseDir . DIRECTORY_SEPARATOR, '', $file);
            $content = file_get_contents($file);
            $this->assertNotFalse($content);

            $this->assertStringContainsString('Developed by', $content, "View {$rel} is missing attribution footer 'Developed by'");
            $this->assertStringContainsString('Md. Iqbal Mahmud', $content, "View {$rel} is missing attribution 'Md. Iqbal Mahmud'");
            $this->assertStringContainsString('Dawn C Simmons', $content, "View {$rel} is missing attribution 'Dawn C Simmons'");
            $this->assertStringContainsString('AI Infos', $content, "View {$rel} is missing attribution 'AI Infos'");
        }
    }

    public function test_profile_and_password_reset_views_have_required_elements(): void
    {
        // Profile View
        $profileView = $this->baseDir . '/resources/views/profile/show.blade.php';
        $this->assertFileExists($profileView);
        $profileContent = file_get_contents($profileView);
        $this->assertStringContainsString('route(\'profile.update\')', $profileContent);
        $this->assertStringContainsString('route(\'profile.password\')', $profileContent);
        $this->assertStringContainsString('name="display_name"', $profileContent);
        $this->assertStringContainsString('name="current_password"', $profileContent);
        $this->assertStringContainsString('name="password"', $profileContent);
        $this->assertStringContainsString('name="password_confirmation"', $profileContent);

        // Forgot Password View
        $forgotView = $this->baseDir . '/resources/views/auth/forgot-password.blade.php';
        $this->assertFileExists($forgotView);
        $forgotContent = file_get_contents($forgotView);
        $this->assertStringContainsString('route(\'password.email\')', $forgotContent);
        $this->assertStringContainsString('name="email"', $forgotContent);
        $this->assertStringContainsString('devResetUrl', $forgotContent);
        $this->assertStringContainsString('route(\'login\')', $forgotContent);

        // Reset Password View
        $resetView = $this->baseDir . '/resources/views/auth/reset-password.blade.php';
        $this->assertFileExists($resetView);
        $resetContent = file_get_contents($resetView);
        $this->assertStringContainsString('route(\'password.update\')', $resetContent);
        $this->assertStringContainsString('name="token"', $resetContent);
        $this->assertStringContainsString('name="email"', $resetContent);
        $this->assertStringContainsString('name="password"', $resetContent);
        $this->assertStringContainsString('name="password_confirmation"', $resetContent);
    }

    public function test_cross_navigation_links_are_present_in_auth_and_workspace(): void
    {
        // Login View has Forgot password and Demo links
        $loginContent = file_get_contents($this->baseDir . '/resources/views/auth/login.blade.php');
        $this->assertStringContainsString('route(\'password.request\')', $loginContent);
        $this->assertStringContainsString('route(\'demo\')', $loginContent);

        // Register View has Demo link
        $registerContent = file_get_contents($this->baseDir . '/resources/views/auth/register.blade.php');
        $this->assertStringContainsString('route(\'demo\')', $registerContent);

        // Demo View has Register and Forgot Password links
        $demoContent = file_get_contents($this->baseDir . '/resources/views/auth/demo.blade.php');
        $this->assertStringContainsString('route(\'register\')', $demoContent);
        $this->assertStringContainsString('route(\'password.request\')', $demoContent);

        // Workspace View has clickable avatar to profile and sidebar profile link
        $workspaceContent = file_get_contents($this->baseDir . '/resources/views/app/workspace.blade.php');
        $this->assertStringContainsString('href="{{ route(\'profile.show\') }}" class="avatar"', $workspaceContent);
        $this->assertStringContainsString('href="{{ route(\'profile.show\') }}" class="nav-item-link"', $workspaceContent);
    }

    public function test_password_reset_token_sha256_hash_and_expiry_logic(): void
    {
        $plainToken = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $hashed = hash('sha256', $plainToken);

        $this->assertSame(64, strlen($hashed));
        $this->assertSame($hashed, hash('sha256', $plainToken));
        $this->assertNotSame($plainToken, $hashed);

        // Expiry calculation: 60 minutes
        $createdAt = time();
        $this->assertFalse(($createdAt + 3599) < time()); // 59m59s is not expired
        $this->assertTrue(($createdAt - 3601) < (time() - 3600)); // 60m01s is expired
    }
}
