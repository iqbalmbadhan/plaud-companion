Set-StrictMode -Version Latest

$script:FreshInstallZipName = 'plaud-companion-install-fresh.zip'
$script:FullUpdateZipName = 'plaud-companion-update-full.zip'
$script:PatchUpdateZipName = 'plaud-companion-update-patch.zip'
$script:LastBuildManifestName = 'last-build.txt'

$script:AllowCodeDirs = @(
    'app',
    'bootstrap',
    'config',
    'database',
    'lang',
    'resources',
    'routes',
    'vendor'
)

$script:AllowRootFiles = @(
    'artisan',
    'composer.json',
    'composer.lock',
    'VERSION'
)

$script:AllowPublicDirs = @(
    'assets',
    'build'
)

$script:PublicKeyExceptionRelPath = 'config/updater-public.key'

$script:ForbiddenRelPatterns = @(
    '^\.env(\..+)?$',
    '^storage(/|$)',
    '^bootstrap/cache(/|$)',
    '^\.git(/|$)',
    '^auth\.json$',
    '^install\.php$',
    '^public/index\.php$',
    '^public/\.htaccess$',
    '^\.htaccess$',
    '^\.user\.ini$',
    '^.*install[-_]?token.*$',
    '^(?!vendor/).*\.(sql|pem|key|bak)$'
)

function Get-RepoRoot {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ScriptDir
    )
    $resolved = Resolve-Path (Join-Path $ScriptDir '..')
    return $resolved.Path
}

function Get-NextMonotonicVersion {
    param(
        [Parameter(Mandatory = $true)]
        [string]$VersionFilePath,
        [string]$VersionOverride = ''
    )

    if ($VersionOverride -ne '') {
        if ($VersionOverride -notmatch '^\d{4}\.\d{2}\.\d{2}\.\d{4}$') {
            throw "Invalid version format '$VersionOverride'. Expected yyyy.MM.dd.HHmm."
        }
        return $VersionOverride
    }

    $utcNowStr = [DateTime]::UtcNow.ToString('yyyy.MM.dd.HHmm')
    if (-not (Test-Path -LiteralPath $VersionFilePath)) {
        return $utcNowStr
    }

    $existingRaw = (Get-Content -LiteralPath $VersionFilePath -Raw -ErrorAction SilentlyContinue)
    if ($null -eq $existingRaw) {
        return $utcNowStr
    }
    $existing = $existingRaw.Trim()
    if ($existing -notmatch '^\d{4}\.\d{2}\.\d{2}\.\d{4}$') {
        return $utcNowStr
    }

    if ([string]::CompareOrdinal($utcNowStr, $existing) -gt 0) {
        return $utcNowStr
    }

    $parsedExisting = [DateTime]::ParseExact(
        $existing,
        'yyyy.MM.dd.HHmm',
        [System.Globalization.CultureInfo]::InvariantCulture
    )
    return $parsedExisting.AddMinutes(1).ToString('yyyy.MM.dd.HHmm')
}

function Assert-PublicKeyPresent {
    param(
        [Parameter(Mandatory = $true)]
        [string]$BackendDir
    )

    $keyPath = Join-Path $BackendDir 'config\updater-public.key'
    if (-not (Test-Path -LiteralPath $keyPath)) {
        throw "Missing required public key file: $keyPath. Generate keys with scripts/updater-keys.php first."
    }
    $keyContent = (Get-Content -LiteralPath $keyPath -Raw -ErrorAction SilentlyContinue)
    if ($null -eq $keyContent -or $keyContent.Trim() -eq '') {
        throw "Public key file is empty: $keyPath. Paste the base64 ed25519 public key from scripts/updater-keys.php."
    }
}

function Resolve-PrivateKeyFile {
    param(
        [string]$PrivateKeyFile = ''
    )

    $defaultPath = Join-Path $env:USERPROFILE '.plaud-companion\plaud-companion.updater-key'
    $candidate = if ([string]::IsNullOrWhiteSpace($PrivateKeyFile)) { $defaultPath } else { $PrivateKeyFile.Trim() }

    if (-not (Test-Path -LiteralPath $candidate)) {
        if ($candidate -match '^[A-Za-z0-9+/]{40,}={0,2}$') {
            throw (
                "You passed a base64 key string ('$candidate'), not a file path.`n" +
                "  -PrivateKeyFile must be the path to your PRIVATE .updater-key file outside the repo (default: $defaultPath).`n" +
                "  Note: backend-laravel\config\updater-public.key contains the PUBLIC key; keep the PRIVATE key file outside the repo.`n" +
                "  If you haven't generated it yet, run: php scripts/updater-keys.php `"$defaultPath`""
            )
        }
        throw (
            "Private key file not found: $candidate`n" +
            "  Pass -PrivateKeyFile <path-to-private.updater-key> or generate one at the default path:`n" +
            "  php scripts/updater-keys.php `"$defaultPath`""
        )
    }

    return (Resolve-Path -LiteralPath $candidate).Path
}

function Test-UpdateRelPathAllowed {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelPath
    )

    $norm = ($RelPath -replace '\\', '/').TrimStart('/')
    if ($norm -eq '') {
        return $false
    }

    if ($norm -ceq $script:PublicKeyExceptionRelPath) {
        return $true
    }

    foreach ($pattern in $script:ForbiddenRelPatterns) {
        if ($norm -imatch $pattern) {
            return $false
        }
    }

    foreach ($rootFile in $script:AllowRootFiles) {
        if ($norm -ceq $rootFile) {
            return $true
        }
    }

    foreach ($codeDir in $script:AllowCodeDirs) {
        if ($norm.StartsWith($codeDir + '/', [System.StringComparison]::Ordinal)) {
            return $true
        }
    }

    foreach ($pubDir in $script:AllowPublicDirs) {
        if ($norm.StartsWith('public/' + $pubDir + '/', [System.StringComparison]::Ordinal)) {
            return $true
        }
    }

    return $false
}

function Assert-StagingClean {
    param(
        [Parameter(Mandatory = $true)]
        [string]$StagingDir,
        [ValidateSet('update', 'fresh', 'partial')]
        [string]$Mode = 'update'
    )

    if ($Mode -ne 'partial') {
        $pubKeyFile = Join-Path $StagingDir 'config\updater-public.key'
        if (-not (Test-Path -LiteralPath $pubKeyFile)) {
            throw 'Allow-list check failed: config/updater-public.key is missing from staging.'
        }
    }

    $envFile = Join-Path $StagingDir '.env'
    if (Test-Path -LiteralPath $envFile) {
        throw 'Security check failed: .env must never be packaged in a release.'
    }

    $storageDir = Join-Path $StagingDir 'storage'
    if ($Mode -ne 'fresh' -and (Test-Path -LiteralPath $storageDir)) {
        throw 'Security check failed: storage/ must never be packaged in an update release.'
    }

    $rootLen = $StagingDir.TrimEnd('\', '/').Length + 1
    $allFiles = Get-ChildItem -LiteralPath $StagingDir -Recurse -File -Force
    foreach ($fileItem in $allFiles) {
        $rel = ($fileItem.FullName.Substring($rootLen) -replace '\\', '/')

        if ($rel -imatch '^\.env(\..+)?$' -and -not ($Mode -eq 'fresh' -and $rel -ceq '.env.example')) {
            throw "Security check failed: forbidden env file '$rel' in staging."
        }
        if ($rel -imatch '^storage(/|$)' -and -not ($Mode -eq 'fresh' -and $rel -cmatch '^storage/.+/\.gitkeep$')) {
            throw "Security check failed: forbidden storage path '$rel' in staging."
        }
        if ($rel -imatch '^(?!vendor/).*\.(key|pem|sql|bak)$' -and $rel -cne $script:PublicKeyExceptionRelPath) {
            throw "Security check failed: potential secret/key file '$rel' in staging."
        }

        if ($Mode -eq 'update' -or $Mode -eq 'partial') {
            if (-not (Test-UpdateRelPathAllowed -RelPath $rel)) {
                throw "Allow-list check failed: '$rel' is not allowed in an update zip."
            }
        }
    }
}

function Copy-TreeFiltered {
    param(
        [Parameter(Mandatory = $true)]
        [string]$SourceDir,
        [Parameter(Mandatory = $true)]
        [string]$DestDir
    )

    if (-not (Test-Path -LiteralPath $SourceDir)) {
        return
    }
    New-Item -ItemType Directory -Path $DestDir -Force | Out-Null
    Copy-Item -Path (Join-Path $SourceDir '*') -Destination $DestDir -Recurse -Force
}

function New-CleanComposerStaging {
    param(
        [Parameter(Mandatory = $true)]
        [string]$BackendDir,
        [Parameter(Mandatory = $true)]
        [string]$VersionStr,
        [ValidateSet('update', 'fresh')]
        [string]$Mode = 'update'
    )

    Assert-PublicKeyPresent -BackendDir $BackendDir

    $stagingRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('plaud_stage_' + [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $stagingRoot -Force | Out-Null

    try {
        foreach ($codeDir in $script:AllowCodeDirs) {
            if ($codeDir -eq 'vendor') {
                continue
            }
            $srcSub = Join-Path $BackendDir $codeDir
            if (Test-Path -LiteralPath $srcSub) {
                Copy-TreeFiltered -SourceDir $srcSub -DestDir (Join-Path $stagingRoot $codeDir)
            }
        }

        # Never carry bootstrap/cache/*.php into staging
        $bootCacheDir = Join-Path $stagingRoot 'bootstrap\cache'
        if (Test-Path -LiteralPath $bootCacheDir) {
            Remove-Item -LiteralPath $bootCacheDir -Recurse -Force
        }

        foreach ($pubDir in $script:AllowPublicDirs) {
            $srcPub = Join-Path $BackendDir (Join-Path 'public' $pubDir)
            if (Test-Path -LiteralPath $srcPub) {
                Copy-TreeFiltered -SourceDir $srcPub -DestDir (Join-Path $stagingRoot (Join-Path 'public' $pubDir))
            }
        }

        foreach ($rootFile in $script:AllowRootFiles) {
            if ($rootFile -eq 'VERSION') {
                continue
            }
            $srcFile = Join-Path $BackendDir $rootFile
            if (Test-Path -LiteralPath $srcFile) {
                Copy-Item -LiteralPath $srcFile -Destination (Join-Path $stagingRoot $rootFile) -Force
            }
        }

        if ($Mode -eq 'fresh') {
            $freshExtras = @('.env.example', 'public\index.php', 'public\.htaccess')
            foreach ($extraRel in $freshExtras) {
                $extraSrc = Join-Path $BackendDir $extraRel
                if (Test-Path -LiteralPath $extraSrc) {
                    $extraDest = Join-Path $stagingRoot $extraRel
                    $extraDestDir = Split-Path -Parent $extraDest
                    if (-not (Test-Path -LiteralPath $extraDestDir)) {
                        New-Item -ItemType Directory -Path $extraDestDir -Force | Out-Null
                    }
                    Copy-Item -LiteralPath $extraSrc -Destination $extraDest -Force
                }
            }
        }

        Set-Content -LiteralPath (Join-Path $stagingRoot 'VERSION') -Value ($VersionStr + "`n") -NoNewline -Encoding ASCII

        # Build clean production vendor in temp staging
        Push-Location $stagingRoot
        try {
            $caBundle = Join-Path $env:USERPROFILE '.plaud-companion\ca-bundle.crt'
            $phpIniArgs = @()
            if ((Test-Path -LiteralPath $caBundle) -and [string]::IsNullOrWhiteSpace($env:COMPOSER_CAFILE)) {
                $phpIniArgs = @('-d', "curl.cainfo=$caBundle", '-d', "openssl.cafile=$caBundle")
            }

            $composerCmd = Get-Command composer -ErrorAction SilentlyContinue
            if ($null -ne $composerCmd) {
                & $composerCmd.Source install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts
            } else {
                $composerPharCandidates = @(
                    (Join-Path $BackendDir 'composer.phar'),
                    (Join-Path (Split-Path -Parent $BackendDir) 'composer.phar'),
                    (Join-Path $env:USERPROFILE 'composer.phar')
                )
                $composerPhar = $composerPharCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
                if ($null -eq $composerPhar) {
                    throw (
                        "Composer is required on PATH (or as composer.phar in repo root / user profile) to build clean --no-dev vendor directory.`n" +
                        "Install Composer (https://getcomposer.org/download/) and ensure 'composer' is on PATH."
                    )
                }
                & php @phpIniArgs $composerPhar install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts
            }
            if ($LASTEXITCODE -ne 0) {
                throw "composer install failed with exit code $LASTEXITCODE."
            }
        } finally {
            Pop-Location
        }

        # Clean any stray cache/storage created during build
        foreach ($stray in @('bootstrap\cache', 'storage', '.env')) {
            $strayPath = Join-Path $stagingRoot $stray
            if (Test-Path -LiteralPath $strayPath) {
                Remove-Item -LiteralPath $strayPath -Recurse -Force
            }
        }

        if ($Mode -eq 'fresh') {
            $skeletonDirs = @(
                'storage\app\private',
                'storage\app\public',
                'storage\app\updates',
                'storage\framework\cache\data',
                'storage\framework\sessions',
                'storage\framework\views',
                'storage\logs',
                'bootstrap\cache'
            )
            foreach ($skel in $skeletonDirs) {
                $skelPath = Join-Path $stagingRoot $skel
                New-Item -ItemType Directory -Path $skelPath -Force | Out-Null
                Set-Content -LiteralPath (Join-Path $skelPath '.gitkeep') -Value '' -NoNewline -Encoding ASCII
            }
        }

        Assert-StagingClean -StagingDir $stagingRoot -Mode $Mode
        return $stagingRoot
    } catch {
        if (Test-Path -LiteralPath $stagingRoot) {
            Remove-Item -LiteralPath $stagingRoot -Recurse -Force -ErrorAction SilentlyContinue
        }
        throw
    }
}

function New-ZipFromDirectory {
    param(
        [Parameter(Mandatory = $true)]
        [string]$SourceDir,
        [Parameter(Mandatory = $true)]
        [string]$ZipOutputPath
    )

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $outDir = Split-Path -Parent $ZipOutputPath
    if (-not (Test-Path -LiteralPath $outDir)) {
        New-Item -ItemType Directory -Path $outDir -Force | Out-Null
    }
    if (Test-Path -LiteralPath $ZipOutputPath) {
        Remove-Item -LiteralPath $ZipOutputPath -Force
    }

    $rootLen = $SourceDir.TrimEnd('\', '/').Length + 1
    $files = Get-ChildItem -LiteralPath $SourceDir -Recurse -File -Force | Sort-Object FullName

    $zipStream = [System.IO.File]::Open($ZipOutputPath, [System.IO.FileMode]::CreateNew)
    try {
        $archive = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create, $false)
        try {
            foreach ($fileObj in $files) {
                $entryName = ($fileObj.FullName.Substring($rootLen) -replace '\\', '/')
                [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $archive,
                    $fileObj.FullName,
                    $entryName,
                    [System.IO.Compression.CompressionLevel]::Optimal
                ) | Out-Null
            }
        } finally {
            $archive.Dispose()
        }
    } finally {
        $zipStream.Dispose()
    }
}

function Get-StagingFileHashes {
    param(
        [Parameter(Mandatory = $true)]
        [string]$StagingDir
    )

    $rootLen = $StagingDir.TrimEnd('\', '/').Length + 1
    $map = @{}
    $files = Get-ChildItem -LiteralPath $StagingDir -Recurse -File -Force | Sort-Object FullName
    foreach ($fileObj in $files) {
        $rel = ($fileObj.FullName.Substring($rootLen) -replace '\\', '/')
        $hashObj = Get-FileHash -LiteralPath $fileObj.FullName -Algorithm SHA256
        $map[$rel] = $hashObj.Hash.ToLowerInvariant()
    }
    return $map
}

function Write-LastBuildManifest {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ManifestPath,
        [Parameter(Mandatory = $true)]
        [string]$VersionStr,
        [Parameter(Mandatory = $true)]
        [hashtable]$HashMap
    )

    $lines = New-Object System.Collections.Generic.List[string]
    $lines.Add("# version=$VersionStr")
    foreach ($relKey in ($HashMap.Keys | Sort-Object)) {
        $lines.Add("$($HashMap[$relKey])  $relKey")
    }
    $outDir = Split-Path -Parent $ManifestPath
    if (-not (Test-Path -LiteralPath $outDir)) {
        New-Item -ItemType Directory -Path $outDir -Force | Out-Null
    }
    Set-Content -LiteralPath $ManifestPath -Value $lines -Encoding UTF8
}

function Read-LastBuildManifest {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ManifestPath
    )

    if (-not (Test-Path -LiteralPath $ManifestPath)) {
        throw "Baseline manifest not found: $ManifestPath. Run make-release.ps1 first."
    }

    $baseVersion = ''
    $hashMap = @{}
    foreach ($line in (Get-Content -LiteralPath $ManifestPath -Encoding UTF8)) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '') {
            continue
        }
        if ($trimmed.StartsWith('# version=')) {
            $baseVersion = $trimmed.Substring(10).Trim()
            continue
        }
        if ($trimmed.StartsWith('#')) {
            continue
        }
        if ($trimmed -match '^([a-f0-9]{64})\s+(.+)$') {
            $hashMap[$Matches[2].Trim()] = $Matches[1]
        }
    }

    return @{
        Version = $baseVersion
        Files   = $hashMap
    }
}
