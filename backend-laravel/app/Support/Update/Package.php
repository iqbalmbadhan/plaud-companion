<?php

namespace App\Support\Update;

use ZipArchive;

class Package
{
    public readonly string $version;
    public readonly string $kind;
    public readonly ?string $minVersion;
    public readonly string $builtAt;
    /** @var array<string, array{path: string, size: int, sha256: string}> */
    public readonly array $files;
    /** @var list<string> */
    public readonly array $removed;
    public readonly string $zipPath;

    private function __construct(
        string $zipPath,
        string $version,
        string $kind,
        ?string $minVersion,
        string $builtAt,
        array $files,
        array $removed,
    ) {
        $this->zipPath = $zipPath;
        $this->version = $version;
        $this->kind = $kind;
        $this->minVersion = $minVersion;
        $this->builtAt = $builtAt;
        $this->files = $files;
        $this->removed = $removed;
    }

    public static function enabled(?array $config = null): bool
    {
        $cfg = self::resolveConfig($config);
        return self::decodePublicKey((string) ($cfg['public_key'] ?? '')) !== null;
    }

    public static function open(string $zipPath, ?array $config = null): self
    {
        $cfg = self::resolveConfig($config);
        $publicKey = self::decodePublicKey((string) ($cfg['public_key'] ?? ''));
        if ($publicKey === null) {
            throw new UpdateException('update_disabled', 'Updater public key is not configured.', [], 503);
        }

        if (!is_file($zipPath)) {
            throw new UpdateException('update_bad_zip', 'Release zip file is missing or unreadable.');
        }

        $maxZipBytes = ((int) ($cfg['max_zip_mb'] ?? 150)) * 1024 * 1024;
        $zipSize = filesize($zipPath);
        if ($zipSize === false || $zipSize <= 0 || $zipSize > $maxZipBytes) {
            throw new UpdateException('update_too_big', 'Release zip exceeds maximum allowed size.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new UpdateException('update_bad_zip', 'File is not a valid zip archive.');
        }

        try {
            $maxEntries = (int) ($cfg['max_entries'] ?? 25000);
            if ($zip->numFiles > $maxEntries) {
                throw new UpdateException('update_too_many_entries', 'Archive contains too many entries.');
            }

            // 1. Read ONLY the two update entries first and verify ed25519 signature BEFORE trusting manifest.
            $manifestBytes = $zip->getFromName('update/manifest.json');
            $sigBase64 = $zip->getFromName('update/manifest.sig');
            if (!is_string($manifestBytes) || !is_string($sigBase64)) {
                throw new UpdateException('update_bad_signature', 'Missing update manifest or signature.');
            }

            $sigBytes = base64_decode(trim($sigBase64), true);
            if ($sigBytes === false || strlen($sigBytes) !== SODIUM_CRYPTO_SIGN_BYTES) {
                throw new UpdateException('update_bad_signature', 'Malformed ed25519 signature.');
            }

            if (!sodium_crypto_sign_verify_detached($sigBytes, $manifestBytes, $publicKey)) {
                throw new UpdateException('update_bad_signature', 'Signature verification failed.');
            }

            // 2. Parse manifest strictly.
            $manifest = json_decode($manifestBytes, true);
            if (!is_array($manifest)) {
                throw new UpdateException('update_bad_manifest', 'Manifest JSON is invalid.');
            }

            $version = $manifest['version'] ?? null;
            $kind = $manifest['kind'] ?? null;
            $minVersion = $manifest['min_version'] ?? null;
            $builtAt = $manifest['built_at'] ?? '';
            $payloadHash = $manifest['sha256'] ?? null;
            $rawFiles = $manifest['files'] ?? null;
            $rawRemoved = $manifest['removed'] ?? [];

            if (!is_string($version) || !preg_match('/^\d{4}\.\d{2}\.\d{2}\.\d{4}$/', $version)) {
                throw new UpdateException('update_bad_manifest', 'Invalid manifest version format.');
            }
            if (!is_string($kind) || !in_array($kind, ['full', 'partial'], true)) {
                throw new UpdateException('update_bad_manifest', 'Invalid manifest kind.');
            }
            if ($kind === 'partial') {
                if (!is_string($minVersion) || !preg_match('/^\d{4}\.\d{2}\.\d{2}\.\d{4}$/', $minVersion)) {
                    throw new UpdateException('update_bad_manifest', 'Partial update requires valid min_version.');
                }
            } elseif ($minVersion !== null && (!is_string($minVersion) || !preg_match('/^\d{4}\.\d{2}\.\d{2}\.\d{4}$/', $minVersion))) {
                throw new UpdateException('update_bad_manifest', 'Invalid min_version format.');
            }
            if (!is_string($payloadHash) || !preg_match('/^[a-f0-9]{64}$/', $payloadHash)) {
                throw new UpdateException('update_bad_manifest', 'Invalid manifest payload sha256.');
            }
            if (!is_array($rawFiles) || !is_array($rawRemoved)) {
                throw new UpdateException('update_bad_manifest', 'Manifest files or removed list is invalid.');
            }

            $filesByPath = [];
            $lines = [];
            foreach ($rawFiles as $row) {
                if (!is_array($row)) {
                    throw new UpdateException('update_bad_manifest', 'Invalid file entry in manifest.');
                }
                $p = $row['path'] ?? null;
                $s = $row['size'] ?? null;
                $h = $row['sha256'] ?? null;
                if (
                    !is_string($p) ||
                    !is_int($s) || $s < 0 ||
                    !is_string($h) || !preg_match('/^[a-f0-9]{64}$/', $h)
                ) {
                    throw new UpdateException('update_bad_manifest', 'Invalid file metadata in manifest.');
                }
                self::assertSafeAllowedPath($p, $cfg);
                if (isset($filesByPath[$p])) {
                    throw new UpdateException('update_bad_manifest', 'Duplicate file path in manifest.');
                }
                $filesByPath[$p] = ['path' => $p, 'size' => $s, 'sha256' => $h];
                $lines[] = $p . "\t" . $h;
            }

            sort($lines, SORT_STRING);
            $computedPayloadHash = hash('sha256', implode("\n", $lines));
            if (!hash_equals($payloadHash, $computedPayloadHash)) {
                throw new UpdateException('update_bad_manifest', 'Manifest payload hash mismatch.');
            }

            $removedPaths = [];
            foreach ($rawRemoved as $rem) {
                if (!is_string($rem)) {
                    throw new UpdateException('update_bad_manifest', 'Invalid removed path.');
                }
                self::assertSafeAllowedPath($rem, $cfg);
                $removedPaths[] = $rem;
            }

            // 3. Verify every zip entry against path rules, zip-bomb limits, signed list, and SHA-256.
            $maxUnpackedBytes = ((int) ($cfg['max_unpacked_mb'] ?? 450)) * 1024 * 1024;
            $maxSingleBytes = ((int) ($cfg['max_single_file_mb'] ?? 50)) * 1024 * 1024;
            $totalDeclaredUnpacked = 0;
            $totalStreamedUnpacked = 0;
            $seenFiles = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new UpdateException('update_bad_zip', 'Failed to inspect zip entry.');
                }
                $entryName = (string) $stat['name'];

                // Check for Unix symlink in external attributes (S_IFLNK = 0120000 = 0xA000)
                $opsys = 0;
                $extAttr = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $extAttr)) {
                    $unixMode = ($extAttr >> 16) & 0xF000;
                    if ($opsys === ZipArchive::OPSYS_UNIX && $unixMode === 0xA000) {
                        throw new UpdateException('update_forbidden_path', "Symlink entry refused: {$entryName}");
                    }
                }

                // Directory entries
                if (str_ends_with($entryName, '/')) {
                    continue;
                }

                // Allow the two signed metadata entries
                if ($entryName === 'update/manifest.json' || $entryName === 'update/manifest.sig') {
                    continue;
                }

                self::assertSafeAllowedPath($entryName, $cfg);

                if (!isset($filesByPath[$entryName])) {
                    throw new UpdateException('update_unlisted_file', "Unlisted file in zip: {$entryName}");
                }

                $declaredSize = (int) ($stat['size'] ?? 0);
                if ($declaredSize < 0 || $declaredSize > $maxSingleBytes) {
                    throw new UpdateException('update_too_big', "Entry exceeds single-file limit: {$entryName}");
                }
                $totalDeclaredUnpacked += $declaredSize;
                if ($totalDeclaredUnpacked > $maxUnpackedBytes) {
                    throw new UpdateException('update_too_big', 'Archive unpacked size exceeds limit.');
                }

                $expected = $filesByPath[$entryName];
                if ($declaredSize !== $expected['size']) {
                    throw new UpdateException('update_bad_hash', "Declared size mismatch for {$entryName}");
                }

                // Stream entry bytes to guard against spoofed central-directory size and verify SHA-256
                $stream = $zip->getStream($entryName);
                if ($stream === false) {
                    throw new UpdateException('update_bad_zip', "Unable to read zip entry: {$entryName}");
                }
                $hashCtx = hash_init('sha256');
                $actualBytes = 0;
                try {
                    while (!feof($stream)) {
                        $chunk = fread($stream, 65536);
                        if ($chunk === false) {
                            throw new UpdateException('update_bad_zip', "Stream read error on {$entryName}");
                        }
                        $len = strlen($chunk);
                        $actualBytes += $len;
                        $totalStreamedUnpacked += $len;
                        if ($actualBytes > $maxSingleBytes || $totalStreamedUnpacked > $maxUnpackedBytes) {
                            throw new UpdateException('update_too_big', 'Streamed unpacked size exceeds limit.');
                        }
                        hash_update($hashCtx, $chunk);
                    }
                } finally {
                    fclose($stream);
                }

                $actualHash = hash_final($hashCtx);
                if ($actualBytes !== $expected['size'] || !hash_equals($expected['sha256'], $actualHash)) {
                    throw new UpdateException('update_bad_hash', "Checksum mismatch for {$entryName}");
                }

                $seenFiles[$entryName] = true;
            }

            if (count($seenFiles) !== count($filesByPath)) {
                throw new UpdateException('update_bad_manifest', 'Manifest lists files missing from zip archive.');
            }

            return new self(
                $zipPath,
                $version,
                $kind,
                is_string($minVersion) ? $minVersion : null,
                is_string($builtAt) ? $builtAt : '',
                $filesByPath,
                $removedPaths,
            );
        } finally {
            $zip->close();
        }
    }

    public static function assertSafeAllowedPath(string $path, ?array $config = null): void
    {
        $cfg = self::resolveConfig($config);

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new UpdateException('update_forbidden_path', "Unsafe path characters: {$path}");
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            throw new UpdateException('update_forbidden_path', "Absolute path forbidden: {$path}");
        }

        $segments = explode('/', $path);
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..') {
                throw new UpdateException('update_forbidden_path', "Invalid path segment in: {$path}");
            }
        }

        $publicKeyException = strtolower((string) ($cfg['public_key_exception'] ?? 'config/updater-public.key'));
        $normalizedLower = strtolower($path);

        if ($normalizedLower !== $publicKeyException) {
            foreach ((array) ($cfg['forbidden'] ?? []) as $pattern) {
                if (preg_match($pattern, $path)) {
                    throw new UpdateException('update_forbidden_path', "Forbidden path: {$path}");
                }
            }
        }

        // Top-level allow-list: code_dirs/**, root_files, public/<public_dirs>/**, public/* (single file)
        $top = $segments[0];
        $codeDirs = (array) ($cfg['code_dirs'] ?? []);
        $rootFiles = (array) ($cfg['root_files'] ?? []);
        $publicDirs = (array) ($cfg['public_dirs'] ?? []);

        if (count($segments) === 1 && in_array($top, $rootFiles, true)) {
            return;
        }
        if (count($segments) >= 2 && in_array($top, $codeDirs, true)) {
            return;
        }
        if ($top === 'public') {
            if (count($segments) === 2) {
                return; // single file directly under public/ (not in forbidden)
            }
            if (count($segments) >= 3 && in_array($segments[1], $publicDirs, true)) {
                return;
            }
        }

        throw new UpdateException('update_forbidden_path', "Path outside allow-list: {$path}");
    }

    public static function buildManifestJson(
        array $entries,
        string $version,
        string $kind = 'full',
        ?string $minVersion = null,
        ?string $builtAt = null,
        array $removed = [],
    ): string {
        $files = [];
        $lines = [];
        foreach ($entries as $path => $bytes) {
            $sha = hash('sha256', $bytes);
            $files[] = [
                'path' => $path,
                'size' => strlen($bytes),
                'sha256' => $sha,
            ];
            $lines[] = $path . "\t" . $sha;
        }
        usort($files, fn (array $a, array $b) => strcmp($a['path'], $b['path']));
        sort($lines, SORT_STRING);

        $manifest = [
            'version' => $version,
            'built_at' => $builtAt ?? gmdate('c'),
            'kind' => $kind,
            'min_version' => $minVersion,
            'files' => $files,
            'removed' => array_values($removed),
            'sha256' => hash('sha256', implode("\n", $lines)),
        ];

        return (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private static function decodePublicKey(string $b64): ?string
    {
        $trimmed = trim($b64);
        if ($trimmed === '') {
            return null;
        }
        $decoded = base64_decode($trimmed, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }
        return $decoded;
    }

    private static function resolveConfig(?array $override): array
    {
        if ($override !== null) {
            return $override;
        }
        if (function_exists('config')) {
            $cfg = config('updater');
            if (is_array($cfg)) {
                return $cfg;
            }
        }
        return require __DIR__ . '/../../../config/updater.php';
    }
}
