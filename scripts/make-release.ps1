param(
    [string]$PrivateKeyFile = '',
    [string]$Version = '',
    [string]$OutputDir = '',
    [switch]$FreshOnly,
    [switch]$SkipBump
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'lib\release-names.ps1')

if (-not $FreshOnly -and [string]::IsNullOrWhiteSpace($PrivateKeyFile)) {
    throw 'PrivateKeyFile is required to sign plaud-companion-update-full.zip (or pass -FreshOnly).'
}

if (-not $FreshOnly -and -not (Test-Path -LiteralPath $PrivateKeyFile)) {
    throw "Private key file not found: $PrivateKeyFile"
}

$repoRoot = Get-RepoRoot -ScriptDir $PSScriptRoot
$backendDir = Join-Path $repoRoot 'backend-laravel'
$resolvedOutDir = if ([string]::IsNullOrWhiteSpace($OutputDir)) { Join-Path $repoRoot 'dist' } else { $OutputDir }
$versionFile = Join-Path $backendDir 'VERSION'

if ($SkipBump -and (Test-Path -LiteralPath $versionFile)) {
    $releaseVer = (Get-Content -LiteralPath $versionFile -Raw).Trim()
    if ($releaseVer -notmatch '^\d{4}\.\d{2}\.\d{2}\.\d{4}$') {
        $releaseVer = Get-NextMonotonicVersion -VersionFilePath $versionFile -VersionOverride $Version
        Set-Content -LiteralPath $versionFile -Value ($releaseVer + "`n") -NoNewline -Encoding UTF8
    }
} else {
    $releaseVer = Get-NextMonotonicVersion -VersionFilePath $versionFile -VersionOverride $Version
    Set-Content -LiteralPath $versionFile -Value ($releaseVer + "`n") -NoNewline -Encoding UTF8
}

Write-Host "Building PLAUD Companion release v$releaseVer..."

$freshStaging = $null
$updateStaging = $null
try {
    # 1. Unsigned fresh installer package
    $freshStaging = New-CleanComposerStaging -BackendDir $backendDir -VersionStr $releaseVer -Mode 'fresh'
    $freshZipPath = Join-Path $resolvedOutDir $script:FreshInstallZipName
    New-ZipFromDirectory -SourceDir $freshStaging -ZipOutputPath $freshZipPath
    Write-Host "Created unsigned fresh installer: $freshZipPath"

    if (-not $FreshOnly) {
        # 2. Signed full update package
        $updateStaging = New-CleanComposerStaging -BackendDir $backendDir -VersionStr $releaseVer -Mode 'update'
        $fullZipPath = Join-Path $resolvedOutDir $script:FullUpdateZipName
        New-ZipFromDirectory -SourceDir $updateStaging -ZipOutputPath $fullZipPath

        $signScript = Join-Path $PSScriptRoot 'sign-release.php'
        & php $signScript $fullZipPath $PrivateKeyFile '--kind=full' "--version=$releaseVer"
        if ($LASTEXITCODE -ne 0) {
            throw "sign-release.php failed with exit code $LASTEXITCODE."
        }
        Write-Host "Created signed full update: $fullZipPath"

        # 3. Record baseline hashes for subsequent partial patches
        $hashes = Get-StagingFileHashes -StagingDir $updateStaging
        $manifestPath = Join-Path $resolvedOutDir $script:LastBuildManifestName
        Write-LastBuildManifest -ManifestPath $manifestPath -VersionStr $releaseVer -HashMap $hashes
        Write-Host "Wrote build baseline: $manifestPath ($($hashes.Count) files)"
    }
} finally {
    if ($null -ne $freshStaging -and (Test-Path -LiteralPath $freshStaging)) {
        Remove-Item -LiteralPath $freshStaging -Recurse -Force -ErrorAction SilentlyContinue
    }
    if ($null -ne $updateStaging -and (Test-Path -LiteralPath $updateStaging)) {
        Remove-Item -LiteralPath $updateStaging -Recurse -Force -ErrorAction SilentlyContinue
    }
}
