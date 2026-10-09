$ErrorActionPreference = 'Stop'
$taskName = 'Onazul Overtime Automatic Timeout'
$projectPath = Split-Path -Parent $PSScriptRoot
$phpPath = 'C:\xampp\php\php.exe'
$workerPath = Join-Path $projectPath 'Api\Jobs\overtime_auto_timeout.php'
if (!(Test-Path -LiteralPath $phpPath) -or !(Test-Path -LiteralPath $workerPath)) { throw 'PHP or overtime worker not found.' }
$launcherPath = Join-Path $PSScriptRoot 'run_overtime_timeout_hidden.vbs'
if (!(Test-Path -LiteralPath $launcherPath)) { throw 'Hidden overtime launcher not found.' }
$action = New-ScheduledTaskAction -Execute "$env:SystemRoot\System32\wscript.exe" -Argument ('"' + $launcherPath + '"') -WorkingDirectory $projectPath
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -Hidden -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description 'Closes accepted overtime 20 minutes after approved end using the existing Onazul notifications and Messenger.' -Force | Select-Object TaskName,State
