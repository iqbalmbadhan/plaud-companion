<?php

namespace Tests\Unit;

use App\Support\Update\Package;
use App\Support\Update\UpdateException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class PackageTest extends TestCase
{
    private string $tmpDir;
    private string $publicKeyB64;
    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();
        $baseTmp = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'testing';
        $this->tmpDir = $baseTmp . DIRECTORY_SEPARATOR . 'plaud_pkg_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);

        $kp = sodium_crypto_sign_keypair();
        $this->publicKeyB64 = base64_encode(sodium_crypto_sign_publickey($kp));
        $this->secretKey = sodium_crypto_sign_secretkey($kp);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
        parent::tearDown();
    }

    private function baseConfig(array $overrides = []): array
    {
        $defaults = require __DIR__ . '/../../config/updater.php';
        $defaults['public_key'] = $this->publicKeyB64;
        return array_merge($defaults, $overrides);
    }

    private function buildSignedZip(
        array $entries,
        string $version = '2026.11.14.0230',
        string $kind = 'full',
        ?string $minVersion = null,
        ?string $secretKeyOverride = null,
        ?callable $tamperZip = null,
    ): string {
        $zipPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'pkg_' . bin2hex(random_bytes(3)) . '.zip';
        $manifestJson = Package::buildManifestJson($entries, $version, $kind, $minVersion);
        $sig = base64_encode(sodium_crypto_sign_detached($manifestJson, $secretKeyOverride ?? $this->secretKey));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        foreach ($entries as $path => $bytes) {
            $zip->addFromString($path, $bytes);
        }
        $zip->addFromString('update/manifest.json', $manifestJson);
        $zip->addFromString('update/manifest.sig', $sig);
        $zip->close();

        if ($tamperZip !== null) {
            $tamperZip($zipPath);
        }

        return $zipPath;
    }

    public function test_valid_full_zip_passes_including_public_key_exception(): void
    {
        $zipPath = $this->buildSignedZip([
            'VERSION' => '2026.11.14.0230',
            'app/Version.php' => "<?php return '2026.11.14.0230';\n",
            'config/updater-public.key' => $this->publicKeyB64 . "\n",
            'public/assets/app.js' => 'console.log(1);',
        ]);

        $pkg = Package::open($zipPath, $this->baseConfig());
        $this->assertSame('2026.11.14.0230', $pkg->version);
        $this->assertSame('full', $pkg->kind);
        $this->assertCount(4, $pkg->files);
    }

    public function test_disabled_when_public_key_missing(): void
    {
        $cfg = $this->baseConfig(['public_key' => '']);
        $this->assertFalse(Package::enabled($cfg));

        $zipPath = $this->buildSignedZip(['VERSION' => '2026.11.14.0230']);
        $this->expectUpdateCode('update_disabled', fn () => Package::open($zipPath, $cfg));
    }

    public function test_wrong_key_is_refused(): void
    {
        $otherKp = sodium_crypto_sign_keypair();
        $zipPath = $this->buildSignedZip(
            ['VERSION' => '2026.11.14.0230'],
            secretKeyOverride: sodium_crypto_sign_secretkey($otherKp),
        );
        $this->expectUpdateCode('update_bad_signature', fn () => Package::open($zipPath, $this->baseConfig()));
    }

    public function test_missing_sig_is_refused(): void
    {
        $zipPath = $this->buildSignedZip(
            ['VERSION' => '2026.11.14.0230'],
            tamperZip: function (string $p): void {
                $z = new ZipArchive();
                $z->open($p);
                $z->deleteName('update/manifest.sig');
                $z->close();
            },
        );
        $this->expectUpdateCode('update_bad_signature', fn () => Package::open($zipPath, $this->baseConfig()));
    }

    public function test_tampered_manifest_is_refused(): void
    {
        $zipPath = $this->buildSignedZip(
            ['VERSION' => '2026.11.14.0230'],
            tamperZip: function (string $p): void {
                $z = new ZipArchive();
                $z->open($p);
                $raw = (string) $z->getFromName('update/manifest.json');
                $z->addFromString('update/manifest.json', str_replace('2026.11.14.0230', '2099.01.01.0000', $raw));
                $z->close();
            },
        );
        $this->expectUpdateCode('update_bad_signature', fn () => Package::open($zipPath, $this->baseConfig()));
    }

    public function test_tampered_file_is_refused_with_bad_hash(): void
    {
        $zipPath = $this->buildSignedZip(
            ['VERSION' => '2026.11.14.0230'],
            tamperZip: function (string $p): void {
                $z = new ZipArchive();
                $z->open($p);
                $z->addFromString('VERSION', '9999.01.01.0000');
                $z->close();
            },
        );
        $this->expectUpdateCode('update_bad_hash', fn () => Package::open($zipPath, $this->baseConfig()));
    }

    public function test_extra_unlisted_file_is_refused(): void
    {
        $zipPath = $this->buildSignedZip(
            ['VERSION' => '2026.11.14.0230'],
            tamperZip: function (string $p): void {
                $z = new ZipArchive();
                $z->open($p);
                $z->addFromString('app/Backdoor.php', '<?php');
                $z->close();
            },
        );
        $this->expectUpdateCode('update_unlisted_file', fn () => Package::open($zipPath, $this->baseConfig()));
    }

    public function test_bad_kind_and_bad_version_are_refused(): void
    {
        $badKind = $this->buildSignedZip(['VERSION' => '2026.11.14.0230'], kind: 'nightly');
        $this->expectUpdateCode('update_bad_manifest', fn () => Package::open($badKind, $this->baseConfig()));

        $badVer = $this->buildSignedZip(['VERSION' => '1.0.0'], version: '1.0.0');
        $this->expectUpdateCode('update_bad_manifest', fn () => Package::open($badVer, $this->baseConfig()));
    }

    public function test_traversal_and_forbidden_paths_are_refused(): void
    {
        $badPaths = [
            '../evil.php',
            'app/../.env',
            '/etc/passwd',
            'C:\\evil.php',
            '.env',
            '.env.production',
            'storage/app/x.txt',
            'config/secret.key',
            'install.php',
            'public/index.php',
            '.git/HEAD',
            'auth.json',
        ];

        foreach ($badPaths as $bad) {
            $zipPath = $this->buildSignedZip([$bad => 'x']);
            $this->expectUpdateCode('update_forbidden_path', fn () => Package::open($zipPath, $this->baseConfig()));
        }
    }

    public function test_zip_bomb_and_too_many_entries_are_refused(): void
    {
        $bigZip = $this->buildSignedZip(['app/Big.bin' => str_repeat('A', 2048)]);
        $cfgLowCap = $this->baseConfig(['max_unpacked_mb' => 0]);
        $this->expectUpdateCode('update_too_big', fn () => Package::open($bigZip, $cfgLowCap));

        $manyZip = $this->buildSignedZip([
            'app/A.php' => 'a',
            'app/B.php' => 'b',
        ]);
        $cfgLowEntries = $this->baseConfig(['max_entries' => 3]); // 2 files + 2 update entries = 4 > 3
        $this->expectUpdateCode('update_too_many_entries', fn () => Package::open($manyZip, $cfgLowEntries));
    }

    private function expectUpdateCode(string $expectedCode, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected UpdateException with code {$expectedCode}, none thrown.");
        } catch (UpdateException $e) {
            $this->assertSame($expectedCode, $e->errorCode, "Unexpected errorCode: {$e->errorCode} ({$e->getMessage()})");
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
