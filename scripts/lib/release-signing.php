<?php

require_once __DIR__ . '/../../backend-laravel/app/Support/Update/UpdateException.php';
require_once __DIR__ . '/../../backend-laravel/app/Support/Update/Package.php';

use App\Support\Update\Package;

/**
 * Resolve canonical path for containment checks even when the target file does not exist yet.
 */
function normalizePathForRepoCheck(string $path): string
{
    $norm = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    $parent = dirname($norm);
    $realParent = realpath($parent);
    if ($realParent !== false) {
        $norm = $realParent . DIRECTORY_SEPARATOR . basename($norm);
    }
    return DIRECTORY_SEPARATOR === '\\' ? strtolower($norm) : $norm;
}

function isPathInsideRepo(string $targetPath, string $repoRoot): bool
{
    $realRepo = realpath($repoRoot);
    if ($realRepo === false) {
        return false;
    }
    $normRepo = rtrim(DIRECTORY_SEPARATOR === '\\' ? strtolower($realRepo) : $realRepo, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $normTarget = normalizePathForRepoCheck($targetPath);
    return str_starts_with($normTarget, $normRepo) || rtrim($normTarget, DIRECTORY_SEPARATOR) === rtrim($normRepo, DIRECTORY_SEPARATOR);
}

/**
 * Generate an ed25519 signing keypair outside the repository.
 * Returns base64-encoded public key.
 */
function generateUpdaterKeypair(string $privateKeyFile, string $repoRoot): string
{
    if (isPathInsideRepo($privateKeyFile, $repoRoot)) {
        throw new RuntimeException('Refusing to write private signing key inside the repository.');
    }
    if (file_exists($privateKeyFile)) {
        throw new RuntimeException("Refusing to overwrite existing key file: {$privateKeyFile}");
    }

    $dir = dirname($privateKeyFile);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException("Failed to create key directory: {$dir}");
    }

    $kp = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = sodium_crypto_sign_publickey($kp);

    if (file_put_contents($privateKeyFile, base64_encode($secretKey) . "\n") === false) {
        throw new RuntimeException("Failed to write private key file: {$privateKeyFile}");
    }
    @chmod($privateKeyFile, 0600);

    return base64_encode($publicKey);
}

/**
 * Sign a release zip with an ed25519 private key and verify round-trip with Package::open.
 *
 * @param list<string> $removed
 */
function signReleaseZip(
    string $zipPath,
    string $privateKeyFile,
    string $version,
    string $kind = 'full',
    ?string $minVersion = null,
    array $removed = [],
): Package {
    if (!is_file($zipPath)) {
        throw new RuntimeException("Zip file not found: {$zipPath}");
    }
    if (!is_file($privateKeyFile)) {
        throw new RuntimeException("Private key file not found: {$privateKeyFile}");
    }

    $secretBytes = base64_decode(trim((string) file_get_contents($privateKeyFile)), true);
    if ($secretBytes === false || strlen($secretBytes) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        throw new RuntimeException('Invalid ed25519 private key file.');
    }
    $publicKeyB64 = base64_encode(sodium_crypto_sign_publickey_from_secretkey($secretBytes));

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException("Failed to open zip archive: {$zipPath}");
    }

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (str_ends_with($name, '/') || $name === 'update/manifest.json' || $name === 'update/manifest.sig') {
            continue;
        }
        $bytes = $zip->getFromIndex($i);
        if (!is_string($bytes)) {
            $zip->close();
            throw new RuntimeException("Failed to read zip entry: {$name}");
        }
        $entries[$name] = $bytes;
    }

    $manifestJson = Package::buildManifestJson($entries, $version, $kind, $minVersion, null, $removed);
    $sigB64 = base64_encode(sodium_crypto_sign_detached($manifestJson, $secretBytes));

    $zip->addFromString('update/manifest.json', $manifestJson);
    $zip->addFromString('update/manifest.sig', $sigB64 . "\n");
    $zip->close();

    $cfg = require __DIR__ . '/../../backend-laravel/config/updater.php';
    $cfg['public_key'] = $publicKeyB64;

    // Re-open with Package::open to prove verification passes before returning
    return Package::open($zipPath, $cfg);
}
