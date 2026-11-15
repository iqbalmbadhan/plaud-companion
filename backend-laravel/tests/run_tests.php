<?php
declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';

$testFiles = $argv[1] ?? null;
if ($testFiles) {
    $files = [__DIR__ . '/Unit/' . basename($testFiles)];
} else {
    $files = glob(__DIR__ . '/Unit/*Test.php') ?: [];
}

$totalPassed = 0;
$totalFailed = 0;
$startTotal = microtime(true);

echo "Running PHPUnit Unit Tests for backend-laravel...\n";
echo str_repeat('-', 70) . "\n";

foreach ($files as $file) {
    if (!file_exists($file)) {
        continue;
    }
    require_once $file;

    $baseName = basename($file, '.php');
    $className = "Tests\\Unit\\{$baseName}";
    if (!class_exists($className)) {
        continue;
    }

    $ref = new ReflectionClass($className);
    if ($ref->isAbstract()) {
        continue;
    }

    $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
    $testMethods = array_filter($methods, fn(ReflectionMethod $m) => str_starts_with($m->getName(), 'test'));

    echo "Suite: {$baseName} (" . count($testMethods) . " tests)\n";

    foreach ($testMethods as $method) {
        $testName = $method->getName();
        $instance = new $className();

        try {
            $setup = new ReflectionMethod($className, 'setUp');
            $setup->invoke($instance);

            $caught = null;
            try {
                $method->invoke($instance);
            } catch (\Throwable $e) {
                $caught = $e;
            }

            $expectedClass = $instance->getExpectedException();
            $expectedMsg = $instance->getExpectedExceptionMessage();

            if ($expectedClass !== null) {
                if ($caught === null) {
                    throw new \AssertionError("Failed asserting that exception of type '{$expectedClass}' was thrown.");
                }
                if (!($caught instanceof $expectedClass)) {
                    throw new \AssertionError("Failed asserting that exception of type '" . get_class($caught) . "' matches expected '{$expectedClass}'.");
                }
                if ($expectedMsg !== null && !str_contains($caught->getMessage(), $expectedMsg)) {
                    throw new \AssertionError("Failed asserting that exception message '{$caught->getMessage()}' contains '{$expectedMsg}'.");
                }
            } elseif ($caught !== null) {
                throw $caught;
            }

            $teardown = new ReflectionMethod($className, 'tearDown');
            $teardown->invoke($instance);

            $totalPassed++;
            echo "  [PASS] {$testName}\n";
        } catch (\Throwable $e) {
            $totalFailed++;
            echo "  [FAIL] {$testName}\n";
            echo "         " . $e->getMessage() . "\n";
            echo "         at " . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }
    echo "\n";
}

$elapsed = round(microtime(true) - $startTotal, 3);
$totalAss = \PHPUnit\Framework\TestCase::$assertions;
echo str_repeat('-', 70) . "\n";
echo "Tests: " . ($totalPassed + $totalFailed) . ", Passed: {$totalPassed}, Failed: {$totalFailed}, Assertions: {$totalAss}, Time: {$elapsed}s\n";

if ($totalFailed > 0) {
    exit(1);
}
exit(0);
