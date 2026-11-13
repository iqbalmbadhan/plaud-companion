#!/usr/bin/env php
<?php

require_once __DIR__ . '/lib/release-signing.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/sign-release.php <zip> <private-key-file> [--kind=full|partial] [--min-version=yyyy.MM.dd.HHmm] [--version=yyyy.MM.dd.HHmm]\n");
    exit(2);
}

$zipPath = $argv[1];
$privateKeyFile = $argv[2];
$kind = 'full';
$minVersion = null;
$version = gmdate('Y.m.d.Hi');

foreach (array_slice($argv, 3) as $arg) {
    if (str_starts_with($arg, '--kind=')) {
        $kind = substr($arg, 7);
    } elseif (str_starts_with($arg, '--min-version=')) {
        $minVersion = substr($arg, 14);
    } elseif (str_starts_with($arg, '--version=')) {
        $version = substr($arg, 10);
    }
}

try {
    $pkg = signReleaseZip($zipPath, $privateKeyFile, $version, $kind, $minVersion);
    fwrite(STDOUT, "Signed & verified {$pkg->kind} release v{$pkg->version} (" . count($pkg->files) . " files)\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
