# PLAUD Companion - the one program behind "PLAUD.bat".
# Double-click PLAUD.bat. It checks this PC, deploys the app to XAMPP, starts Apache + MySQL,
# runs migrations and opens the browser.
#
#   PLAUD.bat              do everything (the normal way)
#   PLAUD.bat check        only look - no changes made
#   PLAUD.bat stop         stop Apache and MySQL
#   PLAUD.bat -Yes         skip the confirmation prompt (for scripts/CI)
param(
    [Parameter(Position = 0)][ValidateSet('', 'check', 'stop')][string]$Mode = '',
    [switch]$Yes
)
$ErrorActionPreference = 'Continue'
$here        = $PSScriptRoot
$root        = Split-Path -Parent $here   # repo root
$app         = "$root\backend-laravel"    # Laravel application root
$xampp       = 'C:\xampp'
$htdocs      = "$xampp\htdocs\plaud-companion"
$xamppUrl    = 'https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/8.5.12/xampp-windows-x64-8.5.12-0-VS17-installer.exe/download'
$appUrl      = 'http://localhost/plaud-companion/public'
$dbName      = 'plaud_companion'
$need        = 'bcmath', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'sodium', 'zip', 'gd', 'curl', 'fileinfo'

function Say($t) { Write-Host $t }
function Listening($p) { [bool](Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue) }
function PhpOk($exe) {
    if (-not $exe -or -not (Test-Path $exe)) { return $false }
    $id = & $exe -r 'echo PHP_VERSION_ID;' 2>$null
    return ($id -and [int]$id -ge 80400)
}
function Find-Php {
    foreach ($c in @(
        "$xampp\php\php.exe",
        (Get-Command php -ErrorAction SilentlyContinue).Source
    )) { if (PhpOk $c) { return $c } }
    return $null
}
function Missing-Ext($php) {
    $loaded = (& $php -m 2>$null) | ForEach-Object { $_.Trim().ToLower() }
    return @($need | Where-Object { $loaded -notcontains $_ })
}
function Backup($file) {
    $b = "$file.bak-" + (Get-Date -Format 'yyyyMMdd')
    if (-not (Test-Path $b)) { Copy-Item $file $b }
}
function Start-Apache {
    $httpd = "$xampp\apache\bin\httpd.exe"
    if (-not (Test-Path $httpd)) { return $false }
    Start-Process -FilePath $httpd -WindowStyle Hidden
    for ($i = 0; $i -lt 20 -and -not (Listening 80); $i++) { Start-Sleep 1 }
    return (Listening 80)
}
function Start-MySql {
    $mysqld = "$xampp\mysql\bin\mysqld.exe"
    if (-not (Test-Path $mysqld)) { return $false }
    Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=$xampp\mysql\bin\my.ini", '--standalone' -WindowStyle Hidden
    for ($i = 0; $i -lt 30 -and -not (Listening 3306); $i++) { Start-Sleep 1 }
    return (Listening 3306)
}

# ---------------------------------------------------------------- stop command
if ($Mode -eq 'stop') {
    # Stop Apache
    Get-Process -Name 'httpd' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    # Stop MySQL
    Get-Process -Name 'mysqld' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Say 'PLAUD Companion stopped (Apache + MySQL).'
    exit 0
}

# ---------------------------------------------------------------- 1. look
$php        = Find-Php
$ext        = @(); if ($php) { $ext = Missing-Ext $php }
$ini        = "$xampp\php\php.ini"
$xamppPhp   = $php -and ($php -like "$xampp\*")
$dbWasUp    = Listening 3306
$apacheUp   = Listening 80
$envFile    = "$app\.env"
$envExists  = Test-Path $envFile

$plan = New-Object System.Collections.Generic.List[string]
if (-not $php) { $plan.Add('Download and install XAMPP 8.5 (PHP + MariaDB + Apache, ~200 MB). Windows asks for permission once.') }
if ($xamppPhp) {
    $iniText = if (Test-Path $ini) { Get-Content $ini -Raw } else { '' }
    if ($ext.Count -or ($iniText -and $iniText -notmatch 'date\.timezone\s*=')) {
        $plan.Add("Enable required PHP extensions in php.ini$(if ($ext.Count) { ' — currently off: ' + ($ext -join ', ') })")
    }
    $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
    $add = @("$xampp\php", "$xampp\mysql\bin") | Where-Object { ($userPath -split ';') -notcontains $_ }
    if ($add.Count) { $plan.Add('Add PHP and MySQL tools to PATH') }
}
if (-not $dbWasUp) { $plan.Add('Start MySQL/MariaDB') }
if (-not $apacheUp) { $plan.Add('Start Apache (port 80)') }
if (-not $envExists) { $plan.Add("Create $app\.env from .env.example and generate APP_KEY") }
$plan.Add("Deploy app to $htdocs")
$plan.Add("Run database migrations")

Say ''
Say '  PLAUD COMPANION'
Say '  ==============='
Say ('  PHP 8.4+  : ' + $(if ($php) { $php } else { 'missing' }))
Say ('  MySQL     : ' + $(if ($dbWasUp) { 'running' } else { 'not running' }))
Say ('  Apache    : ' + $(if ($apacheUp) { 'running -- ' + $appUrl } else { 'not running' }))
Say ('  .env      : ' + $(if ($envExists) { 'found' } else { 'missing (will create)' }))

if ($Mode -eq 'check') {
    Say ''
    if ($plan.Count) { Say '  PLAUD.bat would do:'; $plan | ForEach-Object { Say "   - $_" } }
    else             { Say '  Everything is ready.' }
    exit 0
}

# ---------------------------------------------------------------- 2. one plan, one question
Say ''
Say '  This will:'
$plan | ForEach-Object { Say "   - $_" }
Say ''
if (-not $Yes) {
    $a = Read-Host '  Go ahead? (Y = yes, N = no) [Y]'
    if ($a -notmatch '^(|[yY])$') { Say '  Nothing was changed.'; exit 0 }
}

# ---------------------------------------------------------------- 3. install XAMPP if needed
if (-not $php) {
    $exe = Join-Path $env:TEMP 'xampp-installer.exe'
    Say '  Downloading XAMPP (~200 MB, please wait)...'
    try { Invoke-WebRequest -Uri $xamppUrl -OutFile $exe -UseBasicParsing } catch { Say "  Download failed: $($_.Exception.Message)" }
    if (Test-Path $exe) {
        Say '  Installing XAMPP to C:\xampp (allow the Windows UAC prompt)...'
        Start-Process -FilePath $exe -ArgumentList '--mode', 'unattended', '--prefix', $xampp -Verb RunAs -Wait
    }
    $php = Find-Php
    if (-not $php) {
        Say '  XAMPP was not installed. Install XAMPP (PHP 8.5) from https://www.apachefriends.org and run PLAUD.bat again.'
        Start-Process 'https://www.apachefriends.org/download.html'
        exit 1
    }
    $ext = Missing-Ext $php
    $xamppPhp = $php -like "$xampp\*"
}

# ---------------------------------------------------------------- 4. php.ini extensions + PATH
if ($xamppPhp) {
    if ((Test-Path $ini) -and ($ext.Count -or ((Get-Content $ini -Raw) -notmatch 'date\.timezone\s*='))) {
        Backup $ini
        $t = Get-Content $ini -Raw
        foreach ($e in $need) { $t = $t -replace "(?m)^;\s*extension=$e\s*$", "extension=$e" }
        if ($t -notmatch 'date\.timezone\s*=') { $t += "`ndate.timezone=UTC" }
        Set-Content -Path $ini -Value $t -NoNewline
        $ext = Missing-Ext $php
        Say ('  PHP settings done' + $(if ($ext.Count) { ' - still off: ' + ($ext -join ', ') + ' (remove the semicolon in php.ini)' } else { '' }))
    }
    $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
    $add = @("$xampp\php", "$xampp\mysql\bin") | Where-Object { ($userPath -split ';') -notcontains $_ }
    if ($add.Count) {
        [Environment]::SetEnvironmentVariable('Path', (($userPath.TrimEnd(';') + ';' + ($add -join ';')).TrimStart(';')), 'User')
        $env:Path += ';' + ($add -join ';')
        Say '  PATH updated (new windows see it)'
    }
}

# ---------------------------------------------------------------- 5. start MySQL
if (-not (Listening 3306)) {
    Say '  Starting MySQL...'
    if (-not (Start-MySql)) {
        Say '  MySQL did not start. Start "MySQL" in the XAMPP control panel and run PLAUD.bat again.'
        exit 1
    }
}

# ---------------------------------------------------------------- 6. create database if needed
$mysql = "$xampp\mysql\bin\mysql.exe"
if (Test-Path $mysql) {
    & $mysql -u root --protocol=tcp -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>$null
}

# ---------------------------------------------------------------- 7. create .env from example
if (-not $envExists) {
    $example = "$app\.env.example"
    if (Test-Path $example) {
        Copy-Item $example $envFile
        $e = Get-Content $envFile -Raw
        $e = $e -replace 'DB_USERNAME=.*', 'DB_USERNAME=root'
        $e = $e -replace 'DB_PASSWORD=.*', 'DB_PASSWORD='
        $e = $e -replace 'APP_URL=.*', "APP_URL=$appUrl"
        Set-Content -Path $envFile -Value $e -NoNewline
        Say '  .env created from .env.example'
        Say '  Generating APP_KEY...'
        & $php "$app\artisan" key:generate --force 2>$null
    } else {
        Say '  Warning: .env.example not found. Create .env manually.'
    }
}
if (Test-Path $envFile) {
    if ((Get-Content $envFile -Raw) -notmatch '^APP_KEY=base64:') {
        Say '  APP_KEY is empty - generating...'
        & $php "$app\artisan" key:generate --force 2>$null
    }
}

# ---------------------------------------------------------------- 8. start Apache
if (-not (Listening 80)) {
    Say '  Starting Apache...'
    if (-not (Start-Apache)) {
        Say '  Apache did not start. Start "Apache" in the XAMPP control panel and run PLAUD.bat again.'
        exit 1
    }
}

# ---------------------------------------------------------------- 9. deploy to htdocs
Say '  Deploying to htdocs...'
$prevEap = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
robocopy $app $htdocs /MIR /XD '.git' 'node_modules' 'storage\logs' /XF '.env' '*.log' /NFL /NDL /NJH /NJS | Out-Null
$ErrorActionPreference = $prevEap

# ---------------------------------------------------------------- 10. run migrations
Say '  Running migrations...'
& $php "$app\artisan" migrate --force --no-interaction 2>&1 | ForEach-Object { Say "    $_" }

# ---------------------------------------------------------------- 11. open browser
$ips = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
    ForEach-Object IPAddress
Say ''
Say "  DONE. Opening $appUrl"
foreach ($ip in $ips) { Say "  On same Wi-Fi:  http://${ip}/plaud-companion/public" }
Say ''
Start-Process $appUrl
if (-not $Yes) { Read-Host '  Press Enter to close this window' | Out-Null }
