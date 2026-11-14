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
        $this->assertSame('/app', $json['start_url']);
        $this->assertNotEmpty($json['icons'], 'manifest.json must define app icons');
    }

    public function test_pwa_service_worker_and_offline_page_exist(): void
    {
        $swPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'sw.js';
        $this->assertFileExists($swPath, 'Service Worker sw.js must exist in public directory.');

        $offlinePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'offline.html';
        $this->assertFileExists($offlinePath, 'Offline fallback page offline.html must exist.');

        $swContent = file_get_contents($swPath);
        $this->assertStringContainsString('plaud-companion-v1', $swContent);
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
        $this->assertStringContainsString('toggleStatus', $content);
        $this->assertStringContainsString('toggleAdmin', $content);
        $this->assertStringContainsString('Total Accounts', $content);
        $this->assertStringContainsString('Suspended Accounts', $content);
    }
}
