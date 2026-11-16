<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_manifest_uses_relative_urls_and_png_icons_that_exist(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true);

        $this->assertSame('app', $manifest['start_url']);
        $this->assertSame('./', $manifest['scope']);
        $purposes = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertStringStartsNotWith('/', $icon['src'], 'Icons must be relative so sub-folder installs work.');
            $this->assertFileExists(public_path($icon['src']));
            if ($icon['type'] === 'image/png') {
                $purposes[$icon['sizes'] . ':' . $icon['purpose']] = true;
            }
        }
        $this->assertArrayHasKey('192x192:any', $purposes);
        $this->assertArrayHasKey('512x512:any', $purposes);
        $this->assertArrayHasKey('512x512:maskable', $purposes);
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
    }

    public function test_service_worker_never_caches_pages_or_api_responses(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("plaud-companion-v2", $sw);
        // Navigations go to the network with only the offline page as fallback.
        $this->assertMatchesRegularExpression("/req\.mode === 'navigate'\) \{\s*event\.respondWith\(fetch\(req\)\.catch\(\(\) => caches\.match\(OFFLINE_URL\)\)\);/", $sw);
        $this->assertStringNotContainsString("'/app/api'", $sw);
        $this->assertStringNotContainsString("caches.match('/", $sw, 'Paths must resolve against the registration scope.');
    }

    public function test_login_page_is_installable(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('apple-touch-icon.png', $html);
        $this->assertStringContainsString('serviceWorker.register', $html);
    }
}
