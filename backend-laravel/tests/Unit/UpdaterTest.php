<?php

namespace Tests\Unit;

use App\Services\Updater;
use App\Support\Update\MigrationRunner;
use App\Support\Update\Package;
use App\Support\Update\UpdateException;
use App\Support\Update\UpdateStatus;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class UpdaterTest extends TestCase
{
    private string $tmpDir;
    private string $publicKeyB64;
    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();
        $baseTmp = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'testing';
        $this->tmpDir = $baseTmp . DIRECTORY_SEPARATOR . 'plaud_updater_test_' . bin2hex(random_bytes(4));
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

    public function test_update_status_atomic_write_and_lock_contention(): void
    {
        $workDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'work';
        $status = new UpdateStatus($workDir);

        $initial = $status->read();
        $this->assertSame('idle', $initial['state']);

        $written = $status->write([
            'state' => 'running',
            'step' => 'verify',
            'from' => '2026.11.14.0100',
            'version' => '2026.11.14.0200',
            'params' => ['allow_downgrade' => false],
        ]);
        $this->assertSame('running', $written['state']);
        $this->assertSame('verify', $written['step']);

        $reRead = $status->read();
        $this->assertSame('running', $reRead['state']);
        $this->assertSame('2026.11.14.0200', $reRead['version']);

        // No leftover temp files
        $leftovers = glob($workDir . DIRECTORY_SEPARATOR . 'status.json.tmp.*') ?: [];
        $this->assertCount(0, $leftovers);

        // Non-blocking lock contention check
        $lock1 = $status->acquireLock();
        $this->assertTrue($status->isLocked());
        try {
            $this->expectUpdateCode('update_locked', fn () => $status->acquireLock());
        } finally {
            $status->releaseLock($lock1);
        }
        $this->assertFalse($status->isLocked());
    }

    public function test_full_apply_happy_path_on_temp_app_tree(): void
    {
        $env = $this->createTempAppTree('2026.11.14.0100');
        $zipPath = $this->buildSignedZip([
            'VERSION' => '2026.11.14.0200',
            'artisan' => "#!/usr/bin/env php\n<?php echo 'v2';\n",
            'composer.json' => '{"name":"plaud/v2"}',
            'app/Services/Example.php' => "<?php return 'v2';\n",
            'bootstrap/app.php' => "<?php return 'boot-v2';\n",
            'config/app.php' => "<?php return ['v' => 2];\n",
            'public/assets/app.js' => 'console.log("v2");',
            'vendor/autoload.php' => "<?php // vendor v2\n",
        ], '2026.11.14.0200', 'full');

        // Seed bootstrap/cache/*.php that should be cleared on finish
        file_put_contents($env['root'] . '/bootstrap/cache/config.php', '<?php return [];');
        file_put_contents($env['root'] . '/bootstrap/cache/.gitignore', "*\n!.gitignore\n");

        $maintenanceLog = [];
        $safetyCalled = false;
        $migrateCalled = false;

        $updater = $this->makeUpdater(
            $env,
            safetyCallback: function () use (&$safetyCalled): void {
                $safetyCalled = true;
            },
            maintenanceCallback: function (bool $down) use (&$maintenanceLog): void {
                $maintenanceLog[] = $down ? 'down' : 'up';
            },
            inProcessMigrate: function () use (&$migrateCalled): array {
                $migrateCalled = true;
                return ['exit_code' => 0, 'output' => 'Migrated.'];
            },
        );

        $result = $updater->apply($zipPath);

        $this->assertTrue($safetyCalled);
        $this->assertTrue($migrateCalled);
        $this->assertSame(['down', 'up'], $maintenanceLog);
        $this->assertSame('ok', $result['state']);
        $this->assertSame('2026.11.14.0200', $result['version']);
        $this->assertTrue($result['migrations_ran']);
        $this->assertFalse($result['rolled_back']);

        // Verify live tree updated
        $this->assertSame('2026.11.14.0200', trim((string) file_get_contents($env['root'] . '/VERSION')));
        $this->assertSame("<?php return 'v2';\n", file_get_contents($env['root'] . '/app/Services/Example.php'));
        $this->assertSame('console.log("v2");', file_get_contents($env['root'] . '/public/assets/app.js'));
        $this->assertSame("<?php // vendor v2\n", file_get_contents($env['root'] . '/vendor/autoload.php'));

        // bootstrap/cache/*.php cleared, .gitignore preserved
        $this->assertFileDoesNotExist($env['root'] . '/bootstrap/cache/config.php');
        $this->assertFileExists($env['root'] . '/bootstrap/cache/.gitignore');

        // Stage & zip cleaned up
        $this->assertFileDoesNotExist($zipPath);
        $stageDirs = glob($env['work'] . '/stage/*') ?: [];
        $this->assertCount(0, $stageDirs);

        // Previous backup exists and last.json written
        $prev = $updater->previous();
        $this->assertNotNull($prev);
        $this->assertSame('2026.11.14.0100', $prev['version']);
        $this->assertFileExists($env['work'] . '/last.json');
    }

    public function test_partial_apply_min_version_removed_files_and_vendor_untouched(): void
    {
        $env = $this->createTempAppTree('2026.11.14.0100');
        file_put_contents($env['root'] . '/app/Obsolete.php', "<?php // remove me\n");
        $originalVendor = file_get_contents($env['root'] . '/vendor/autoload.php');

        // 1. min_version higher than current -> refused
        $tooHighMinZip = $this->buildSignedZip([
            'VERSION' => '2026.11.14.0200',
            'app/Services/Example.php' => "<?php return 'v2';\n",
        ], '2026.11.14.0200', 'partial', '2026.11.14.0150');

        $updater = $this->makeUpdater($env);
        $this->expectUpdateCode('update_min_version', fn () => $updater->apply($tooHighMinZip));

        // 2. Valid partial update with removed file and vendor untouched
        $partialZip = $this->buildSignedZip(
            [
                'VERSION' => '2026.11.14.0200',
                'app/Services/Example.php' => "<?php return 'partial-v2';\n",
                'app/NewFeature.php' => "<?php return 'new';\n",
            ],
            '2026.11.14.0200',
            'partial',
            '2026.11.14.0100',
            removed: ['app/Obsolete.php'],
        );

        $res = $updater->apply($partialZip);
        $this->assertSame('ok', $res['state']);
        $this->assertSame("<?php return 'partial-v2';\n", file_get_contents($env['root'] . '/app/Services/Example.php'));
        $this->assertSame("<?php return 'new';\n", file_get_contents($env['root'] . '/app/NewFeature.php'));
        $this->assertFileDoesNotExist($env['root'] . '/app/Obsolete.php');
        $this->assertSame($originalVendor, file_get_contents($env['root'] . '/vendor/autoload.php'));
    }

    public function test_failure_at_stage_swap_and_migrate_rolls_back_byte_identical(): void
    {
        // A. Failure at stage (via safetyCallback or bad zip) leaves tree untouched
        $envA = $this->createTempAppTree('2026.11.14.0100');
        $snapA = $this->snapshotTree($envA['root']);
        $zipA = $this->buildSignedZip([
            'VERSION' => '2026.11.14.0200',
            'app/Services/Example.php' => "<?php return 'v2';\n",
        ], '2026.11.14.0200');

        $updaterA = $this->makeUpdater(
            $envA,
            safetyCallback: function (): void {
                throw new UpdateException('update_safety_failed', 'Pre-update backup failed.');
            },
        );
        $this->expectUpdateCode('update_safety_failed', fn () => $updaterA->apply($zipA));
        $this->assertSame($snapA, $this->snapshotTree($envA['root']));

        // B. Failure mid-swap rolls back byte-identical and exits maintenance
        $envB = $this->createTempAppTree('2026.11.14.0100');
        $snapB = $this->snapshotTree($envB['root']);
        $zipB = $this->buildSignedZip([
            'VERSION' => '2026.11.14.0200',
            'app/Services/Example.php' => "<?php return 'v2';\n",
            'config/app.php' => "<?php return ['v' => 2];\n",
        ], '2026.11.14.0200');

        $maintB = [];
        $updaterB = $this->makeUpdater(
            $envB,
            maintenanceCallback: function (bool $down) use (&$maintB): void {
                $maintB[] = $down ? 'down' : 'up';
            },
            swapHook: function (string $phase): void {
                if ($phase === 'after_swap') {
                    throw new RuntimeException('Simulated disk error mid-swap');
                }
            },
        );
        $this->expectUpdateCode('update_swap_failed', fn () => $updaterB->apply($zipB));
        $this->assertSame($snapB, $this->snapshotTree($envB['root']));
        $this->assertSame(['down', 'up'], $maintB);
        $statusB = (new UpdateStatus($envB['work']))->read();
        $this->assertSame('failed', $statusB['state']);
        $this->assertTrue($statusB['rolled_back']);

        // C. Failure at migrate rolls back byte-identical and exits maintenance
        $envC = $this->createTempAppTree('2026.11.14.0100');
        $snapC = $this->snapshotTree($envC['root']);
        $zipC = $this->buildSignedZip([
            'VERSION' => '2026.11.14.0200',
            'app/Services/Example.php' => "<?php return 'v2';\n",
        ], '2026.11.14.0200');

        $maintC = [];
        $updaterC = $this->makeUpdater(
            $envC,
            maintenanceCallback: function (bool $down) use (&$maintC): void {
                $maintC[] = $down ? 'down' : 'up';
            },
            inProcessMigrate: function (): array {
                return ['exit_code' => 1, 'output' => 'SQLSTATE[42S01]: Base table already exists'];
            },
        );
        $this->expectUpdateCode('update_migrate_failed', fn () => $updaterC->apply($zipC));
        $this->assertSame($snapC, $this->snapshotTree($envC['root']));
        $this->assertSame(['down', 'up'], $maintC);
        $statusC = (new UpdateStatus($envC['work']))->read();
        $this->assertSame('failed', $statusC['state']);
        $this->assertTrue($statusC['rolled_back']);
    }

    public function test_failed_rollback_stays_in_maintenance(): void
    {
        $env = $this->createTempAppTree('2026.11.14.0100');
        $zipPath = $this->buildSignedZip([
            'VERSION' => '2026.11.14.0200',
            'app/Services/Example.php' => "<?php return 'v2';\n",
        ], '2026.11.14.0200');

        $maint = [];
        $updater = $this->makeUpdater(
            $env,
            maintenanceCallback: function (bool $down) use (&$maint): void {
                $maint[] = $down ? 'down' : 'up';
            },
            inProcessMigrate: fn (): array => ['exit_code' => 1, 'output' => 'Migration failed'],
            swapHook: function (string $phase): void {
                if ($phase === 'before_revert') {
                    throw new RuntimeException('Simulated I/O failure during revert');
                }
            },
        );

        $this->expectUpdateCode('update_rollback_failed', fn () => $updater->apply($zipPath));
        $this->assertSame(['down'], $maint); // Never called 'up'!
        $status = (new UpdateStatus($env['work']))->read();
        $this->assertSame('failed', $status['state']);
        $this->assertFalse($status['rolled_back']);
    }

    public function test_downgrade_and_same_version_refusal_and_override(): void
    {
        $env = $this->createTempAppTree('2026.11.14.0200');
        $sameZip = $this->buildSignedZip(['VERSION' => '2026.11.14.0200'], '2026.11.14.0200');
        $olderZip = $this->buildSignedZip([
            'VERSION' => '2026.11.14.0100',
            'app/Services/Example.php' => "<?php return 'v1';\n",
        ], '2026.11.14.0100');

        $updater = $this->makeUpdater($env);
        $this->expectUpdateCode('update_downgrade_refused', fn () => $updater->apply($sameZip, false));
        $this->expectUpdateCode('update_downgrade_refused', fn () => $updater->apply($olderZip, false));

        // Explicit override succeeds
        $res = $updater->apply($olderZip, true);
        $this->assertSame('ok', $res['state']);
        $this->assertSame('2026.11.14.0100', trim((string) file_get_contents($env['root'] . '/VERSION')));
    }

    public function test_manual_rollback_restores_previous_tree(): void
    {
        $env = $this->createTempAppTree('2026.11.14.0100');
        $snapV1 = $this->snapshotTree($env['root']);

        $zipPath = $this->buildSignedZip([
            'VERSION' => '2026.11.14.0200',
            'app/Services/Example.php' => "<?php return 'v2';\n",
            'config/app.php' => "<?php return ['v' => 2];\n",
        ], '2026.11.14.0200');

        $updater = $this->makeUpdater($env);
        $updater->apply($zipPath);
        $this->assertSame('2026.11.14.0200', trim((string) file_get_contents($env['root'] . '/VERSION')));

        $rb = $updater->rollback();
        $this->assertSame('ok', $rb['state']);
        $this->assertTrue($rb['rolled_back']);
        $this->assertSame($snapV1, $this->snapshotTree($env['root']));
        $this->assertNull($updater->previous());
    }

    private function createTempAppTree(string $version): array
    {
        $base = $this->tmpDir . DIRECTORY_SEPARATOR . 'app_' . bin2hex(random_bytes(3));
        $root = $base . DIRECTORY_SEPARATOR . 'root';
        $public = $root . DIRECTORY_SEPARATOR . 'public';
        $work = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'updates';
        $previous = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'updates';

        foreach ([
            $root . '/app/Services',
            $root . '/bootstrap/cache',
            $root . '/config',
            $root . '/database/migrations',
            $root . '/lang',
            $root . '/resources',
            $root . '/routes',
            $root . '/vendor',
            $public . '/assets',
            $work,
            $previous,
        ] as $dir) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($root . '/VERSION', $version . "\n");
        file_put_contents($root . '/artisan', "#!/usr/bin/env php\n<?php echo 'v1';\n");
        file_put_contents($root . '/composer.json', '{"name":"plaud/v1"}');
        file_put_contents($root . '/app/Services/Example.php', "<?php return 'v1';\n");
        file_put_contents($root . '/bootstrap/app.php', "<?php return 'boot-v1';\n");
        file_put_contents($root . '/config/app.php', "<?php return ['v' => 1];\n");
        file_put_contents($root . '/vendor/autoload.php', "<?php // vendor v1\n");
        file_put_contents($public . '/assets/app.js', 'console.log("v1");');

        return [
            'root' => $root,
            'public' => $public,
            'work' => $work,
            'previous' => $previous,
        ];
    }

    private function makeUpdater(
        array $env,
        ?\Closure $safetyCallback = null,
        ?\Closure $maintenanceCallback = null,
        ?\Closure $inProcessMigrate = null,
        ?\Closure $swapHook = null,
    ): Updater {
        $defaults = require __DIR__ . '/../../config/updater.php';
        $cfg = array_merge($defaults, [
            'root' => $env['root'],
            'public' => $env['public'],
            'work' => $env['work'],
            'previous' => $env['previous'],
            'public_key' => $this->publicKeyB64,
            'fresh_process' => false,
        ]);

        $status = new UpdateStatus($env['work']);
        $migrator = new MigrationRunner(
            $env['root'],
            false,
            null,
            $inProcessMigrate ?? static fn (): array => ['exit_code' => 0, 'output' => 'Nothing to migrate.'],
        );

        return new Updater(
            $cfg,
            $status,
            $migrator,
            $safetyCallback,
            $maintenanceCallback ?? static function (bool $down): void {},
            $swapHook,
        );
    }

    private function buildSignedZip(
        array $entries,
        string $version,
        string $kind = 'full',
        ?string $minVersion = null,
        array $removed = [],
    ): string {
        $zipPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'pkg_' . bin2hex(random_bytes(3)) . '.zip';
        $manifestJson = Package::buildManifestJson($entries, $version, $kind, $minVersion, null, $removed);
        $sig = base64_encode(sodium_crypto_sign_detached($manifestJson, $this->secretKey));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        foreach ($entries as $path => $bytes) {
            $zip->addFromString($path, $bytes);
        }
        $zip->addFromString('update/manifest.json', $manifestJson);
        $zip->addFromString('update/manifest.sig', $sig);
        $zip->close();

        return $zipPath;
    }

    private function snapshotTree(string $root): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (str_starts_with($rel, 'storage/')) {
                continue;
            }
            $files[$rel] = sha1_file($file->getPathname());
        }
        ksort($files);
        return $files;
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
