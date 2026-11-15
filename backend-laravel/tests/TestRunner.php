<?php
declare(strict_types=1);

namespace PHPUnit\Framework;

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4));
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relative . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

if (!class_exists(TestCase::class)) {
    abstract class TestCase
    {
        public static int $assertions = 0;
        protected ?string $expectedException = null;
        protected ?string $expectedExceptionMessage = null;

        protected function setUp(): void
        {
        }

        protected function tearDown(): void
        {
        }

        public function expectException(string $exceptionClass): void
        {
            $this->expectedException = $exceptionClass;
        }

        public function expectExceptionMessage(string $message): void
        {
            $this->expectedExceptionMessage = $message;
        }

        public function getExpectedException(): ?string
        {
            return $this->expectedException;
        }

        public function getExpectedExceptionMessage(): ?string
        {
            return $this->expectedExceptionMessage;
        }

        public function assertTrue(mixed $condition, string $message = ''): void
        {
            self::$assertions++;
            if ($condition !== true) {
                throw new \AssertionError($message ?: 'Failed asserting that condition is true.');
            }
        }

        public function assertFalse(mixed $condition, string $message = ''): void
        {
            self::$assertions++;
            if ($condition !== false) {
                throw new \AssertionError($message ?: 'Failed asserting that condition is false.');
            }
        }

        public function assertSame(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if ($expected !== $actual) {
                $exp = var_export($expected, true);
                $act = var_export($actual, true);
                throw new \AssertionError($message ?: "Failed asserting that {$act} is identical to {$exp}.");
            }
        }

        public function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if ($expected === $actual) {
                throw new \AssertionError($message ?: 'Failed asserting that values are not identical.');
            }
        }

        public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if ($expected != $actual) {
                $exp = var_export($expected, true);
                $act = var_export($actual, true);
                throw new \AssertionError($message ?: "Failed asserting that {$act} equals {$exp}.");
            }
        }

        public function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if ($expected == $actual) {
                throw new \AssertionError($message ?: 'Failed asserting that values do not equal.');
            }
        }

        public function assertCount(int $expectedCount, mixed $countable, string $message = ''): void
        {
            self::$assertions++;
            $count = is_countable($countable) ? count($countable) : 0;
            if ($count !== $expectedCount) {
                throw new \AssertionError($message ?: "Failed asserting that count {$count} matches expected {$expectedCount}.");
            }
        }

        public function assertNotNull(mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if ($actual === null) {
                throw new \AssertionError($message ?: 'Failed asserting that value is not null.');
            }
        }

        public function assertNull(mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if ($actual !== null) {
                throw new \AssertionError($message ?: 'Failed asserting that value is null.');
            }
        }

        public function assertIsArray(mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if (!is_array($actual)) {
                throw new \AssertionError($message ?: 'Failed asserting that value is an array.');
            }
        }

        public function assertIsInt(mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if (!is_int($actual)) {
                throw new \AssertionError($message ?: 'Failed asserting that value is an integer.');
            }
        }

        public function assertIsString(mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if (!is_string($actual)) {
                throw new \AssertionError($message ?: 'Failed asserting that value is a string.');
            }
        }

        public function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
        {
            self::$assertions++;
            if (!str_contains($haystack, $needle)) {
                throw new \AssertionError($message ?: "Failed asserting that '{$haystack}' contains '{$needle}'.");
            }
        }

        public function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
        {
            self::$assertions++;
            if (str_contains($haystack, $needle)) {
                throw new \AssertionError($message ?: "Failed asserting that string does not contain '{$needle}'.");
            }
        }

        public function assertFileExists(string $filename, string $message = ''): void
        {
            self::$assertions++;
            if (!file_exists($filename)) {
                throw new \AssertionError($message ?: "Failed asserting that file '{$filename}' exists.");
            }
        }

        public function assertFileDoesNotExist(string $filename, string $message = ''): void
        {
            self::$assertions++;
            if (file_exists($filename)) {
                throw new \AssertionError($message ?: "Failed asserting that file '{$filename}' does not exist.");
            }
        }

        public function assertNotFalse(mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if ($actual === false) {
                throw new \AssertionError($message ?: 'Failed asserting that value is not false.');
            }
        }

        public function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
        {
            self::$assertions++;
            if (!preg_match($pattern, $string)) {
                throw new \AssertionError($message ?: "Failed asserting that '{$string}' matches pattern '{$pattern}'.");
            }
        }

        public function assertArrayHasKey(string|int $key, mixed $array, string $message = ''): void
        {
            self::$assertions++;
            if (!is_array($array) || !array_key_exists($key, $array)) {
                throw new \AssertionError($message ?: "Failed asserting that array has key '{$key}'.");
            }
        }

        public function assertNotEmpty(mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if (empty($actual)) {
                throw new \AssertionError($message ?: 'Failed asserting that value is not empty.');
            }
        }

        public function assertEmpty(mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if (!empty($actual)) {
                throw new \AssertionError($message ?: 'Failed asserting that value is empty.');
            }
        }

        public function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if (!($actual > $expected)) {
                throw new \AssertionError($message ?: "Failed asserting that {$actual} is greater than {$expected}.");
            }
        }

        public function assertGreaterThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if (!($actual >= $expected)) {
                throw new \AssertionError($message ?: "Failed asserting that {$actual} is greater than or equal to {$expected}.");
            }
        }

        public function assertLessThan(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if (!($actual < $expected)) {
                throw new \AssertionError($message ?: "Failed asserting that {$actual} is less than {$expected}.");
            }
        }

        public function assertLessThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
        {
            self::$assertions++;
            if (!($actual <= $expected)) {
                throw new \AssertionError($message ?: "Failed asserting that {$actual} is less than or equal to {$expected}.");
            }
        }
    }
}
