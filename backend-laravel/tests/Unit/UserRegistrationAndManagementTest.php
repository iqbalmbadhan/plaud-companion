<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class UserRegistrationAndManagementTest extends TestCase
{
    public function test_pwa_manifest_exists_and_is_valid_json(): void
    {
        $manifestPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'manifest.json';
        $this->assertFileExists($manifestPath, 'PWA manifest.json must exist in public directory.');

        $content = file_get_contents($manifestPath);
        $this->assertNotFalse($content);

        $json = json_decode($content, true);
        $this->assertIsArray($json, 'manifest.json must be valid JSON');
        $this->assertSame('PLAUD Companion', $json['name']);
        $this->assertSame('standalone', $json['display']);
        $this->assertSame('app', $json['start_url']);
        $this->assertNotEmpty($json['icons'], 'manifest.json must define app icons');
    }

    public function test_pwa_service_worker_and_offline_page_exist(): void
    {
        $swPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'sw.js';
        $this->assertFileExists($swPath, 'Service Worker sw.js must exist in public directory.');

        $offlinePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'offline.html';
        $this->assertFileExists($offlinePath, 'Offline fallback page offline.html must exist.');

        $swContent = file_get_contents($swPath);
        $this->assertStringContainsString('plaud-companion-v2', $swContent);
        $this->assertStringContainsString('offline.html', $swContent);
    }

    public function test_routes_file_registers_registration_and_user_management(): void
    {
        $routesPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $content = file_get_contents($routesPath);

        $this->assertStringContainsString("showRegister", $content, 'web.php must register showRegister route.');
        $this->assertStringContainsString("register.post", $content, 'web.php must register register.post route.');
        $this->assertStringContainsString("settings/users", $content, 'web.php must register settings/users route.');
        $this->assertStringContainsString("UserManagementController", $content, 'web.php must wire UserManagementController.');
        $this->assertStringContainsString("apiProfile", $content, 'web.php must wire apiProfile route.');
    }

    public function test_register_view_has_required_form_fields(): void
    {
        $viewPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'register.blade.php';
        $this->assertFileExists($viewPath, 'register.blade.php must exist.');

        $content = file_get_contents($viewPath);
        $this->assertStringContainsString('name="display_name"', $content);
        $this->assertStringContainsString('name="email"', $content);
        $this->assertStringContainsString('name="password"', $content);
        $this->assertStringContainsString('name="password_confirmation"', $content);
        $this->assertStringContainsString('route(\'register.post\')', $content);
    }

    public function test_users_management_view_has_suspend_controls(): void
    {
        $viewPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'users.blade.php';
        $this->assertFileExists($viewPath, 'users.blade.php must exist.');

        $content = file_get_contents($viewPath);
        // Actions use data attributes + one handler (no user names inside inline JavaScript).
        $this->assertStringContainsString('runUserAction', $content);
        $this->assertStringContainsString('/toggle-status', $content);
        $this->assertStringContainsString('/toggle-admin', $content);
        $this->assertStringContainsString('Total Accounts', $content);
        $this->assertStringContainsString('Suspended Accounts', $content);
        $this->assertStringContainsString('profileModal', $content);
        $this->assertStringContainsString('openUserProfile', $content);
        $this->assertStringContainsString('closeProfileModal', $content);
    }

    public function test_registration_controller_provisions_workspace_and_redirects_to_app(): void
    {
        $controllerPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'WebAuthController.php';
        $this->assertFileExists($controllerPath, 'WebAuthController.php must exist.');

        $content = file_get_contents($controllerPath);

        // Verification of workspace auto-provisioning
        $this->assertStringContainsString('Workspace::create', $content, 'Registration must auto-create workspace.');
        $this->assertStringContainsString('WorkspaceMember::create', $content, 'Registration must auto-create workspace member.');
        $this->assertStringContainsString('AuditEvent::create', $content, 'Registration must record audit events.');
        $this->assertStringContainsString("'role' => 'owner'", $content, 'New registrant must be designated as workspace owner.');
        $this->assertStringContainsString("'type' => 'personal'", $content, 'Auto-provisioned workspace must be personal type.');

        // Verification of immediate authentication and session regeneration
        $this->assertStringContainsString('Auth::login($user);', $content, 'User must be authenticated immediately upon registration.');
        $this->assertStringContainsString('$request->session()->regenerate();', $content, 'Session must be regenerated upon registration.');
        $this->assertStringContainsString("'max:255'", $content, 'Password validation must enforce max 255 characters.');

        // Verification of redirect to /app
        $this->assertStringContainsString("return redirect()->to('/app');", $content, 'Registration must redirect to /app.');

        // Verification of showRegisterForm alias
        $this->assertStringContainsString('function showRegisterForm', $content, 'WebAuthController must provide showRegisterForm alias.');
        $this->assertStringContainsString('$this->showRegister($request)', $content, 'showRegisterForm must delegate to showRegister.');
    }

    public function test_ensure_web_authenticated_middleware_revokes_session_for_suspended_users(): void
    {
        $middlewarePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'EnsureWebAuthenticated.php';
        $this->assertFileExists($middlewarePath, 'EnsureWebAuthenticated.php must exist.');

        $content = file_get_contents($middlewarePath);

        // Verification of imports
        $this->assertStringContainsString('use Illuminate\Support\Facades\Auth;', $content, 'Middleware must import Auth facade.');

        // Verification of suspended status check and revocation
        $this->assertStringContainsString("\$user->status !== 'active'", $content, 'Middleware must check user active status.');
        $this->assertStringContainsString('Auth::logout();', $content, 'Middleware must call Auth::logout() on suspended user.');
        $this->assertStringContainsString('$request->session()->invalidate();', $content, 'Middleware must invalidate session on suspended user.');
        $this->assertStringContainsString('$request->session()->regenerateToken();', $content, 'Middleware must regenerate CSRF token on suspended user.');

        // Verification of JSON / API vs Web response
        $this->assertStringContainsString('403', $content, 'Middleware must return 403 HTTP status for suspended API requests.');
        $this->assertStringContainsString("redirect()->route('login')->with('suspended'", $content, 'Middleware must flash suspended error on redirect.');
    }

    public function test_web_auth_controller_login_handles_suspended_accounts(): void
    {
        $controllerPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'WebAuthController.php';
        $this->assertFileExists($controllerPath, 'WebAuthController.php must exist.');

        $content = file_get_contents($controllerPath);

        // Verification of suspension check in login method
        $this->assertStringContainsString("if (\$user->status !== 'active')", $content, 'login() must check user active status.');
        $this->assertStringContainsString("with('suspended'", $content, 'login() must flash suspended message.');
        $this->assertStringContainsString("withErrors(['email' =>", $content, 'login() must set email validation error.');
        $this->assertStringContainsString('$request->session()->invalidate();', $content, 'login() must invalidate session for suspended accounts.');
        $this->assertStringContainsString('$request->session()->regenerateToken();', $content, 'login() must regenerate token for suspended accounts.');
    }

    public function test_login_blade_contains_suspended_session_alert_card(): void
    {
        $viewPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'login.blade.php';
        $this->assertFileExists($viewPath, 'login.blade.php must exist.');

        $content = file_get_contents($viewPath);

        // Verification of CSS classes
        $this->assertStringContainsString('.alert-suspended', $content, 'login.blade.php must contain .alert-suspended CSS styling.');
        $this->assertStringContainsString('.alert-suspended-header', $content, 'login.blade.php must contain .alert-suspended-header CSS styling.');
        $this->assertStringContainsString('.alert-suspended-icon', $content, 'login.blade.php must contain .alert-suspended-icon CSS styling.');

        // Verification of Blade directives and markup
        $this->assertStringContainsString("session('suspended')", $content, 'login.blade.php must check session(\'suspended\').');
        $this->assertStringContainsString('Account Suspended', $content, 'login.blade.php must display "Account Suspended" title.');
        $this->assertStringContainsString('support@plaudcompanion.com', $content, 'login.blade.php must offer support@plaudcompanion.com link.');
        $this->assertStringContainsString('svg class="alert-suspended-icon"', $content, 'login.blade.php must render alert SVG icon.');
    }

    public function test_workspace_name_is_clamped_to_100_characters(): void
    {
        $controllerPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'WebAuthController.php';
        $content = file_get_contents($controllerPath);

        $this->assertStringContainsString("mb_substr(trim(\$validated['display_name']) . \"'s Workspace\", 0, 100)", $content, 'Auto-provisioned workspace name must be clamped to 100 characters.');

        // Verify clamping logic on edge cases
        $longDisplayName = str_repeat('A', 100);
        $clamped = mb_substr(trim($longDisplayName) . "'s Workspace", 0, 100);
        $this->assertSame(100, mb_strlen($clamped), 'Clamped workspace name must have length of 100 characters.');
    }

    public function test_users_table_migration_contains_remember_token(): void
    {
        $migrationPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2026_11_15_000001_add_remember_token_to_users_table.php';
        $this->assertFileExists($migrationPath, 'add_remember_token_to_users_table migration must exist.');

        $content = file_get_contents($migrationPath);
        $this->assertStringContainsString('$table->rememberToken();', $content, 'users table migration must define rememberToken column.');
    }
}
