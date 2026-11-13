<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdditiveMigrationsTest extends TestCase
{
    private const ALLOW_ANNOTATION = '@allow-destructive-migration';

    /**
     * Scan migration PHP source for forbidden destructive operations unless explicitly annotated.
     *
     * @return list<string> List of forbidden operations found without allow annotation.
     */
    public static function findDisallowedOperations(string $source): array
    {
        if (stripos($source, self::ALLOW_ANNOTATION) !== false) {
            return [];
        }

        // Strip PHP comments so commented-out examples do not trigger false positives.
        $stripped = preg_replace('/\/\*[\s\S]*?\*\//', '', $source) ?? $source;
        $stripped = preg_replace('/\/\/.*$/m', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/#.*$/m', '', $stripped) ?? $stripped;

        $found = [];
        if (preg_match_all('/\b(dropColumn|dropTable|renameColumn)\s*\(/i', $stripped, $matches)) {
            foreach ($matches[1] as $op) {
                $found[] = $op;
            }
        }

        return array_values(array_unique($found));
    }

    public function test_all_database_migrations_are_additive(): void
    {
        $migrationsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $files = is_dir($migrationsDir) ? (glob($migrationsDir . DIRECTORY_SEPARATOR . '*.php') ?: []) : [];
        sort($files);

        $violations = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $this->assertNotFalse($contents, "Unable to read migration file: {$file}");

            $disallowed = self::findDisallowedOperations($contents);
            if ($disallowed !== []) {
                $violations[] = basename($file) . ': ' . implode(', ', $disallowed)
                    . ' (add // ' . self::ALLOW_ANNOTATION . ': <reason> if intentionally destructive)';
            }
        }

        $this->assertSame([], $violations, "Non-additive migrations detected:\n" . implode("\n", $violations));
    }

    public function test_scanner_allows_additive_and_annotated_migrations(): void
    {
        $additive = <<<'PHP'
<?php
Schema::create('workspaces', function (Blueprint $table) {
    $table->id();
    $table->string('name');
});
PHP;
        $this->assertSame([], self::findDisallowedOperations($additive));

        $annotated = <<<'PHP'
<?php
// @allow-destructive-migration: removing deprecated column after backfill
Schema::table('workspaces', function (Blueprint $table) {
    $table->dropColumn('legacy_slug');
});
PHP;
        $this->assertSame([], self::findDisallowedOperations($annotated));
    }

    public function test_scanner_flags_unannotated_destructive_operations(): void
    {
        $dropCol = "<?php\n\$table->dropColumn('old_col');\n";
        $this->assertSame(['dropColumn'], self::findDisallowedOperations($dropCol));

        $dropTbl = "<?php\nSchema::dropTable('old_table');\n";
        $this->assertSame(['dropTable'], self::findDisallowedOperations($dropTbl));

        $renameCol = "<?php\n\$table->renameColumn('old_name', 'new_name');\n";
        $this->assertSame(['renameColumn'], self::findDisallowedOperations($renameCol));
    }
}
