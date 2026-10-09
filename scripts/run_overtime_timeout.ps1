$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
$phpPath = 'C:\xampp\php\php.exe'
$workerPath = Join-Path $projectPath 'Api\Jobs\overtime_auto_timeout.php'
# XAMPP may be intentionally stopped. Skip this minute without opening a console.
$client = New-Object System.Net.Sockets.TcpClient
try {
    $connection = $client.ConnectAsync('127.0.0.1', 3306)
    if (!$connection.Wait(1500) -or !$client.Connected) { exit 0 }
} catch { exit 0 } finally { $client.Dispose() }
$logDirectory = Join-Path $env:LOCALAPPDATA 'Onazul'
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
$logPath = Join-Path $logDirectory 'overtime-timeout.log'
# Retain the latest run; do not accumulate employee data or an unbounded log.
try {
    & $phpPath $workerPath *> $logPath
    exit $LASTEXITCODE
} catch {
    $_.Exception.Message | Set-Content -LiteralPath $logPath
    exit 1
}
