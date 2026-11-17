# PLAUD Companion - the one program behind "PLAUD.bat".
# Double-click PLAUD.bat. It checks this PC, shows ONE plan of what it will do, asks ONE question,
# then does all of it: finds PHP + MySQL, creates the database, sets up .env, runs migrations,
# starts the app and opens the browser.
#
#   PLAUD.bat              do everything (the normal way)
#   PLAUD.bat check        only look - no changes made
#   PLAUD.bat stop         stop the running server
#   PLAUD.bat -Yes         skip the confirmation prompt (for scripts/CI)
param(
    [Parameter(Position = 0)][ValidateSet('', 'check', 'stop')][string]$Mode = '',
    [switch]$Yes
)
$ErrorActionPreference = 'Continue'
$here        = $PSScriptRoot
$root        = Split-Path -Parent $here   # repo root
$app         = "$root\backend-laravel"    # Laravel application root
$port        = 8000
$dbName      = 'plaud_companion'
$xampp       = 'C:\xampp'
$xamppUrl    = 'https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/8.5.12/xampp-windows-x64-8.5.12-0-VS17-installer.exe/download'
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
function Stop-Plaud {
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
        Where-Object { $_.CommandLine -match "-S (127\.0\.0\.1|0\.0\.0\.0):$port" -or $_.CommandLine -like "*artisan serve*" } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

# ---------------------------------------------------------------- stop command
if ($Mode -eq 'stop') {
    Stop-Plaud
    Say 'PLAUD Companion server stopped.'
    exit 0
}

# ---------------------------------------------------------------- 1. look
$php        = Find-Php
$ext        = @(); if ($php) { $ext = Missing-Ext $php }
$ini        = "$xampp\php\php.ini"
$myini      = "$xampp\mysql\bin\my.ini"
$userPath   = [Environment]::GetEnvironmentVariable('Path', 'User')
$xamppPhp   = $php -and ($php -like "$xampp\*")
$dbWasUp    = Listening 3306
$running    = Listening $port
$envFile    = "$app\.env"
$envExists  = Test-Path $envFile

$plan = New-Object System.Collections.Generic.List[string]
if (-not $php) { $plan.Add('Download and install XAMPP 8.5 (PHP + MariaDB + phpMyAdmin, ~200 MB). Windows asks for permission once.') }
if ($xamppPhp) {
    $iniText = if (Test-Path $ini) { Get-Content $ini -Raw } else { '' }
    if ($ext.Count -or ($iniText -and $iniText -notmatch 'date\.timezone\s*=')) {
        $plan.Add("Enable required PHP extensions in php.ini$(if ($ext.Count) { ' — currently off: ' + ($ext -join ', ') })")
    }
    $add = @("$xampp\php", "$xampp\mysql\bin") | Where-Object { ($userPath -split ';') -notcontains $_ }
    if ($add.Count) { $plan.Add('Add PHP and MySQL tools to PATH') }
}
if (-not $dbWasUp) { $plan.Add('Start the MySQL/MariaDB database') }
if (-not $envExists) { $plan.Add("Create $app\.env from .env.example and generate APP_KEY") }
if (-not $running) { $plan.Add("Start PLAUD Companion at http://localhost:$port and open it in your browser") }

Say ''
Say '  PLAUD COMPANION'
Say '  ==============='
Say ('  PHP 8.4+  : ' + $(if ($php) { $php } else { 'missing' }))
Say ('  Database  : ' + $(if ($dbWasUp) { 'running' } else { 'not running' }))
Say ('  .env      : ' + $(if ($envExists) { 'found' } else { 'missing (will create)' }))
Say ('  Server    : ' + $(if ($running) { "running at http://localhost:$port" } else { 'not running' }))

if ($Mode -eq 'check') {
    Say ''
    if ($plan.Count) { Say '  PLAUD.bat would do:'; $plan | ForEach-Object { Say "   - $_" } }
    else             { Say '  Everything is ready.' }
    exit 0
}

# ---------------------------------------------------------------- already running: just open it
if ($running -and $plan.Count -eq 0) {
    Say ''
    if ($Yes -or (Read-Host '  Already running. Open it? (Y = open, N = stop it) [Y]') -match '^(|[yY])$') {
        Start-Process "http://localhost:$port"
    } else {
        Stop-Plaud; Say '  Stopped.'
    }
    exit 0
}

# ---------------------------------------------------------------- 2. one plan, one question
Say ''
if ($plan.Count) {
    Say '  This will:'
    $plan | ForEach-Object { Say "   - $_" }
    Say ''
}
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
    if ($add.Count) {
        [Environment]::SetEnvironmentVariable('Path', (($userPath.TrimEnd(';') + ';' + ($add -join ';')).TrimStart(';')), 'User')
        $env:Path += ';' + ($add -join ';')
        Say '  PATH updated (new windows see it)'
    }
}

# ---------------------------------------------------------------- 5. start the database
if (-not (Listening 3306)) {
    $mysqld = "$xampp\mysql\bin\mysqld.exe"
    if (Test-Path $mysqld) {
        Say '  Starting the database...'
        Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=$xampp\mysql\bin\my.ini", '--standalone' -WindowStyle Hidden
        for ($i = 0; $i -lt 30 -and -not (Listening 3306); $i++) { Start-Sleep 1 }
    }
    if (-not (Listening 3306)) {
        Say '  The database did not start. Start "MySQL" in the XAMPP control panel and run PLAUD.bat again.'
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
        # Set a sensible local DB config (root / no password / XAMPP default)
        $e = Get-Content $envFile -Raw
        $e = $e -replace 'DB_USERNAME=.*', 'DB_USERNAME=root'
        $e = $e -replace 'DB_PASSWORD=.*', 'DB_PASSWORD='
        $e = $e -replace 'APP_URL=.*', "APP_URL=http://localhost:$port"
        Set-Content -Path $envFile -Value $e -NoNewline
        Say '  .env created from .env.example'
        # Generate APP_KEY
        Say '  Generating APP_KEY...'
        & $php "$app\artisan" key:generate --force 2>$null
    } else {
        Say '  Warning: .env.example not found. Create .env manually.'
    }
}

# Check if APP_KEY is set, generate if empty
if (Test-Path $envFile) {
    $envContent = Get-Content $envFile -Raw
    if ($envContent -notmatch '^APP_KEY=base64:') {
        Say '  APP_KEY is empty - generating...'
        & $php "$app\artisan" key:generate --force 2>$null
    }
}

# ---------------------------------------------------------------- 8. run migrations
Say '  Running migrations...'
& $php "$app\artisan" migrate --force --no-interaction 2>&1 | ForEach-Object { Say "    $_" }

# ---------------------------------------------------------------- 9. start the server
if (-not (Listening $port)) {
    Say "  Starting PLAUD Companion on port $port..."
    $cmd = "`$host.UI.RawUI.WindowTitle='PLAUD Companion (keep this window open)'; Set-Location '$app'; & '$php' artisan serve --host=0.0.0.0 --port=$port"
    Start-Process powershell -WindowStyle Minimized -ArgumentList '-NoExit', '-Command', $cmd
    for ($i = 0; $i -lt 30 -and -not (Listening $port); $i++) { Start-Sleep 1 }
}
if (-not (Listening $port)) {
    Say "  Server did not start. Open the minimized 'PLAUD Companion' window to see why."
    exit 1
}

# ---------------------------------------------------------------- 10. open browser + summary
$ips = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
    ForEach-Object IPAddress
Say ''
Say "  DONE. PLAUD Companion is running:  http://localhost:$port   (opening browser now)"
foreach ($ip in $ips) { Say "  On a device on the same Wi-Fi:  http://${ip}:$port" }
Say ''
Say '  First-time setup:'
Say '    - Sign up at the register page, or sign in if you already have an account.'
Say '    - Database: 127.0.0.1 : 3306  /  plaud_companion  /  root  /  (no password)'
Say ''
Say '  To stop:  PLAUD.bat stop'
Say '  Keep the minimized "PLAUD Companion" window open while you work.'
Start-Process "http://localhost:$port"
if (-not $Yes) { Read-Host '  Press Enter to close this window' | Out-Null }
