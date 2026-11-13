<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

require_once dirname(__DIR__, 3) . '/scripts/lib/release-signing.php';

class ReleaseSigningTest extends TestCase
{
    private string $outsideDir;
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 3);
        $this->outsideDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plaud_key_test_' . bin2hex(random_bytes(4));
        if (!is_dir($this->outsideDir)) {
            @mkdir($this->outsideDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outsideDir)) {
            foreach (scandir($this->outsideDir) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    @unlink($this->outsideDir . DIRECTORY_SEPARATOR . $f);
                }
            }
            @rmdir($this->outsideDir);
        }
        parent::tearDown();
    }

    public function test_key_tool_refuses_in_repo_path(): void
    {
        $inRepo = $this->repoRoot . DIRECTORY_SEPARATOR . 'backend-laravel' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'testing' . DIRECTORY_SEPARATOR . 'bad.key';
        $this->expectException(RuntimeException::class);
        generateUpdaterKeypair($inRepo, $this->repoRoot);
    }

    public function test_key_tool_generates_and_refuses_overwrite(): void
    {
        // Use workspace-local temp if sys_get_temp_dir is sandbox-restricted on Windows,
        // while pointing repoRoot at a subfolder so key is still "outside" that repoRoot.
        $base = dirname(__DIR__, 2) . '/storage/framework/testing/key_sandbox_' . bin2hex(random_bytes(3));
        $fakeRepo = $base . '/repo';
        $keyFile = $base . '/keys/test.updater-key';
        mkdir($fakeRepo, 0777, true);

        try {
            $pubB64 = generateUpdaterKeypair($keyFile, $fakeRepo);
            $rawPub = base64_decode($pubB64, true);
            $this->assertTrue($rawPub !== false && strlen($rawPub) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
            $this->assertTrue(is_file($keyFile));

            // Refuses overwrite
            $threw = false;
            try {
                generateUpdaterKeypair($keyFile, $fakeRepo);
            } catch (RuntimeException $e) {
                $threw = str_contains($e->getMessage(), 'overwrite');
            }
            $this->assertTrue($threw);
        } finally {
            @unlink($keyFile);
            @rmdir($base . '/keys');
            @rmdir($fakeRepo);
            @rmdir($base);
        }
    }

    public function test_sign_release_and_package_open_round_trip(): void
    {
        $base = dirname(__DIR__, 2) . '/storage/framework/testing/sign_rt_' . bin2hex(random_bytes(3));
        $fakeRepo = $base . '/repo';
        $keyFile = $base . '/keys/test.updater-key';
        $zipPath = $base . '/release.zip';
        mkdir($fakeRepo, 0777, true);

        try {
            $pubB64 = generateUpdaterKeypair($keyFile, $fakeRepo);

            $z = new ZipArchive();
            $this->assertTrue($z->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
            $z->addFromString('VERSION', '2026.11.14.0450');
            $z->addFromString('config/updater-public.key', $pubB64 . "\n");
            $z->addFromString('app/Hello.php', "<?php echo 'hi';\n");
            $z->close();

            $pkg = signReleaseZip($zipPath, $keyFile, '2026.11.14.0450', 'partial', '2026.11.14.0400', ['app/Old.php']);
            $this->assertSame('2026.11.14.0450', $pkg->version);
            $this->assertSame('partial', $pkg->kind);
            $this->assertSame('2026.11.14.0400', $pkg->minVersion);
            $this->assertSame(['app/Old.php'], $pkg->removed);
            $this->assertCount(3, $pkg->files);
        } finally {
            @unlink($zipPath);
            @unlink($keyFile);
            @rmdir($base . '/keys');
            @rmdir($fakeRepo);
            @rmdir($base);
        }
    }
}
