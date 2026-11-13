param(
    [Parameter(Mandatory = $true)]
    [string]$PrivateKeyFile,
    [string]$MinVersion = '',
    [string]$BaselineFile = '',
    [string]$Version = '',
    [string]$OutputDir = '',
    [switch]$SkipBump
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'lib\release-names.ps1')

if (-not (Test-Path -LiteralPath $PrivateKeyFile)) {
    throw "Private key file not found: $PrivateKeyFile"
}

$repoRoot = Get-RepoRoot -ScriptDir $PSScriptRoot
$backendDir = Join-Path $repoRoot 'backend-laravel'
$resolvedOutDir = if ([string]::IsNullOrWhiteSpace($OutputDir)) { Join-Path $repoRoot 'dist' } else { $OutputDir }
$resolvedBaseline = if ([string]::IsNullOrWhiteSpace($BaselineFile)) { Join-Path $resolvedOutDir $script:LastBuildManifestName } else { $BaselineFile }

$baselineData = Read-LastBuildManifest -ManifestPath $resolvedBaseline
$effectiveMinVer = if ($MinVersion -ne '') { $MinVersion } else { $baselineData.Version }
if ($effectiveMinVer -notmatch '^\d{4}\.\d{2}\.\d{2}\.\d{4}$') {
    throw "A valid MinVersion (yyyy.MM.dd.HHmm) is required (pass -MinVersion or ensure '# version=...' is in $resolvedBaseline)."
}

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

Write-Host "Building PLAUD Companion partial patch v$releaseVer (min_version=$effectiveMinVer)..."

$updateStaging = $null
$patchStaging = $null
try {
    $updateStaging = New-CleanComposerStaging -BackendDir $backendDir -VersionStr $releaseVer -Mode 'update'
    $currentHashes = Get-StagingFileHashes -StagingDir $updateStaging
    $baselineFiles = $baselineData.Files

    $patchStaging = Join-Path ([System.IO.Path]::GetTempPath()) ('plaud_patch_' + [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $patchStaging -Force | Out-Null

    $changedCount = 0
    foreach ($relPath in ($currentHashes.Keys | Sort-Object)) {
        $isChanged = (-not $baselineFiles.ContainsKey($relPath)) -or ($baselineFiles[$relPath] -cne $currentHashes[$relPath]) -or ($relPath -ceq 'VERSION')
        if ($isChanged) {
            $srcPath = Join-Path $updateStaging ($relPath -replace '/', '\')
            $destPath = Join-Path $patchStaging ($relPath -replace '/', '\')
            $destParent = Split-Path -Parent $destPath
            if (-not (Test-Path -LiteralPath $destParent)) {
                New-Item -ItemType Directory -Path $destParent -Force | Out-Null
            }
            Copy-Item -LiteralPath $srcPath -Destination $destPath -Force
            $changedCount++
        }
    }

    $removedList = New-Object System.Collections.Generic.List[string]
    foreach ($oldRel in ($baselineFiles.Keys | Sort-Object)) {
        if (-not $currentHashes.ContainsKey($oldRel)) {
            $removedList.Add($oldRel)
        }
    }

    Assert-StagingClean -StagingDir $patchStaging -Mode 'update'

    $patchZipPath = Join-Path $resolvedOutDir $script:PatchUpdateZipName
    New-ZipFromDirectory -SourceDir $patchStaging -ZipOutputPath $patchZipPath

    $signingLib = (Join-Path $PSScriptRoot 'lib\release-signing.php') -replace '\\', '/'
    $removedJson = ConvertTo-Json -InputObject @($removedList) -Compress
    $phpSignCode = @'
require_once $argv[1];
$removed = json_decode($argv[6], true);
if (!is_array($removed)) { $removed = []; }
$pkg = signReleaseZip($argv[2], $argv[3], $argv[4], "partial", $argv[5], $removed);
fwrite(STDOUT, "Signed & verified partial release v{$pkg->version} (" . count($pkg->files) . " files, " . count($pkg->removed) . " removed)\n");
'@

    & php -r $phpSignCode $signingLib $patchZipPath $PrivateKeyFile $releaseVer $effectiveMinVer $removedJson
    if ($LASTEXITCODE -ne 0) {
        throw "Partial release signing failed with exit code $LASTEXITCODE."
    }

    Write-Host "Created signed partial update: $patchZipPath ($changedCount changed/added, $($removedList.Count) removed)"
} finally {
    if ($null -ne $updateStaging -and (Test-Path -LiteralPath $updateStaging)) {
        Remove-Item -LiteralPath $updateStaging -Recurse -Force -ErrorAction SilentlyContinue
    }
    if ($null -ne $patchStaging -and (Test-Path -LiteralPath $patchStaging)) {
        Remove-Item -LiteralPath $patchStaging -Recurse -Force -ErrorAction SilentlyContinue
    }
}
