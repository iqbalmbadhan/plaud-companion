#!/usr/bin/env php
<?php

require_once __DIR__ . '/lib/release-signing.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/updater-keys.php <private-key-file>\n");
    exit(2);
}

$repoRoot = dirname(__DIR__);
$privateKeyFile = $argv[1];

try {
    $publicKeyB64 = generateUpdaterKeypair($privateKeyFile, $repoRoot);
    fwrite(STDOUT, "Private key saved outside repo (mode 0600): {$privateKeyFile}\n");
    fwrite(STDOUT, "Public key (copy into backend-laravel/config/updater-public.key):\n{$publicKeyB64}\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
