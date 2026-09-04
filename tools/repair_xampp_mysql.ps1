# Repair XAMPP MariaDB: Aria logs + corrupted mysql system database
# Safe for user databases (onazul_hris_db, etc.) — only replaces data\mysql\
$ErrorActionPreference = 'Stop'

$mysqlData = 'C:\xampp\mysql\data'
$mysqlBin  = 'C:\xampp\mysql\bin'
$freshDir  = 'C:\xampp\mysql\data_fresh_install'
$ts        = Get-Date -Format 'yyyyMMdd_HHmmss'

Write-Host '=== XAMPP MySQL Repair ===' -ForegroundColor Cyan

Write-Host '[1/6] Stopping MySQL...'
cmd /c "taskkill /F /IM mysqld.exe >nul 2>&1"
Start-Sleep -Seconds 2
if (Test-Path "$mysqlData\mysql.pid") { Remove-Item "$mysqlData\mysql.pid" -Force }

Write-Host '[2/6] Removing Aria log files...'
cmd /c "del /F /Q `"$mysqlData\aria_log_control`" 2>nul"
cmd /c "del /F /Q `"$mysqlData\aria_log.*`" 2>nul"

Write-Host '[3/6] Backing up mysql system folder...'
$backup = "$mysqlData\mysql_backup_$ts"
if (Test-Path "$mysqlData\mysql") {
    Copy-Item "$mysqlData\mysql" $backup -Recurse -Force
    Remove-Item "$mysqlData\mysql" -Recurse -Force
    Write-Host "      Backup: $backup"
}

Write-Host '[4/6] Creating fresh mysql system database...'
if (Test-Path $freshDir) { Remove-Item $freshDir -Recurse -Force }
New-Item -ItemType Directory -Path $freshDir | Out-Null
$installDb = Join-Path $mysqlBin 'mysql_install_db.exe'
if (-not (Test-Path $installDb)) { throw "mysql_install_db.exe not found" }
& $installDb --datadir="$freshDir"
if ($LASTEXITCODE -ne 0) { throw "mysql_install_db failed" }
Move-Item "$freshDir\mysql" "$mysqlData\mysql"
Remove-Item $freshDir -Recurse -Force

Write-Host '[5/6] Removing conflicting InnoDB stats files (recreated on start)...'
foreach ($f in @('innodb_table_stats.ibd', 'innodb_index_stats.ibd')) {
    $p = Join-Path "$mysqlData\mysql" $f
    if (Test-Path $p) { Remove-Item $p -Force; Write-Host "      Removed $f" }
}

Write-Host '[6/6] Starting MySQL...'
Start-Process -FilePath "$mysqlBin\mysqld.exe" -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -WindowStyle Hidden
Start-Sleep -Seconds 8

$logTail = Get-Content "$mysqlData\mysql_error.log" -Tail 8
$logTail | ForEach-Object { Write-Host $_ }

$result = & "$mysqlBin\mysql.exe" -u root -e "SELECT VERSION() AS version; SHOW DATABASES LIKE 'onazul_hris_db';" 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host ''
    Write-Host 'MySQL repair SUCCESS.' -ForegroundColor Green
    $result
} else {
    Write-Host ''
    Write-Host 'MySQL started but connection failed. Check mysql_error.log' -ForegroundColor Yellow
    $result
    exit 1
}
