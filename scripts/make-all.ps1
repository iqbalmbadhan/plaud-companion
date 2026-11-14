param(
    [string]$PrivateKeyFile = '',
    [string]$Version = '',
    [string]$OutputDir = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'lib\release-names.ps1')

$PrivateKeyFile = Resolve-PrivateKeyFile -PrivateKeyFile $PrivateKeyFile

$repoRoot = Get-RepoRoot -ScriptDir $PSScriptRoot
$resolvedOutDir = if ([string]::IsNullOrWhiteSpace($OutputDir)) { Join-Path $repoRoot 'dist' } else { $OutputDir }
$baselinePath = Join-Path $resolvedOutDir $script:LastBuildManifestName

$priorBaselineCopy = $null
if (Test-Path -LiteralPath $baselinePath) {
    $priorBaselineCopy = Join-Path ([System.IO.Path]::GetTempPath()) ('plaud_prev_build_' + [Guid]::NewGuid().ToString('N') + '.txt')
    Copy-Item -LiteralPath $baselinePath -Destination $priorBaselineCopy -Force
}

try {
    $releaseArgs = @{
        PrivateKeyFile = $PrivateKeyFile
        OutputDir      = $resolvedOutDir
    }
    if ($Version -ne '') {
        $releaseArgs['Version'] = $Version
    }

    & (Join-Path $PSScriptRoot 'make-release.ps1') @releaseArgs

    $patchBaseline = if ($null -ne $priorBaselineCopy) { $priorBaselineCopy } else { $baselinePath }
    & (Join-Path $PSScriptRoot 'make-partial.ps1') `
        -PrivateKeyFile $PrivateKeyFile `
        -BaselineFile $patchBaseline `
        -OutputDir $resolvedOutDir `
        -SkipBump
} finally {
    if ($null -ne $priorBaselineCopy -and (Test-Path -LiteralPath $priorBaselineCopy)) {
        Remove-Item -LiteralPath $priorBaselineCopy -Force -ErrorAction SilentlyContinue
    }
}
