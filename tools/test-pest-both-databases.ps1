param([string]$MysqlDatabase = '')
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($MysqlDatabase)) {
    $MysqlDatabase = 'laravix_test_pest_' + (Get-Date -Format 'yyyyMMddHHmmss')
}
if ($MysqlDatabase -notmatch '^laravix_test_[a-zA-Z0-9_]+$') {
    throw 'Use a dedicated MySQL database name beginning with laravix_test_.'
}

$mysqlBin = Join-Path $env:LOCALAPPDATA 'laravix/mysql-8.4.13-winx64/bin'
$mysql = Join-Path $mysqlBin 'mysql.exe'
if (-not (Test-Path $mysql)) {
    throw "MySQL client not found at $mysql. Start/install the local MySQL instance as described in docs/mysql-test-runs.md."
}

$failed = $false
Write-Host '=== Full Pest suite: SQLite ==='
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:CACHE_STORE = 'array'
$env:QUEUE_CONNECTION = 'sync'
& php artisan test --compact
if ($LASTEXITCODE -ne 0) {
    Write-Host "FAIL: SQLite Pest suite exited with code $LASTEXITCODE." -ForegroundColor Red
    $failed = $true
}

Write-Host "=== Prepare dedicated MySQL database: $MysqlDatabase ==="
& $mysql --no-defaults --host=127.0.0.1 --port=33360 --user=root -e "CREATE DATABASE IF NOT EXISTS $MysqlDatabase"
if ($LASTEXITCODE -ne 0) {
    throw 'Cannot connect to MySQL at 127.0.0.1:33360 or create the dedicated test database. Start MySQL with pwsh -File .\local-mysql.ps1.'
}

Write-Host "=== Full Pest suite: MySQL ($MysqlDatabase) ==="
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '33360'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''
$env:DB_DATABASE = $MysqlDatabase
& php artisan test --compact
if ($LASTEXITCODE -ne 0) {
    Write-Host "FAIL: MySQL Pest suite exited with code $LASTEXITCODE. Database retained for diagnosis: $MysqlDatabase" -ForegroundColor Red
    $failed = $true
}

if ($failed) { exit 1 }
Write-Host 'PASS: the full Pest suite passed against SQLite and MySQL.'
