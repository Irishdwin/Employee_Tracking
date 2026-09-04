# One-time project restructure: moves + path rewrites
$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot

function Ensure-Dir($path) {
    if (-not (Test-Path $path)) { New-Item -ItemType Directory -Path $path -Force | Out-Null }
}

function Move-FileSafe($from, $to) {
    if (-not (Test-Path $from)) { return }
    $fromFull = (Resolve-Path $from).Path
    $toParent = Split-Path $to -Parent
    Ensure-Dir $toParent
    if (Test-Path $to) {
        $toFull = (Resolve-Path $to).Path
        if ($fromFull -ieq $toFull) { return }
    }
    if (Test-Path $to) { Remove-Item $to -Force }
    Move-Item $from $to -Force
    Write-Host "Moved: $from -> $to"
}

Ensure-Dir "$Root\Js\Shared"
Ensure-Dir "$Root\tools"
Ensure-Dir "$Root\Html\Common"
Ensure-Dir "$Root\Api\Accounting\Lib"

# Backend -> Api/Accounting
Move-FileSafe "$Root\Backend\export_client_billing.php" "$Root\Api\Accounting\export_client_billing.php"
Move-FileSafe "$Root\Backend\Lib\client_billing_spreadsheet.php" "$Root\Api\Accounting\Lib\client_billing_spreadsheet.php"

# Js/Teamlead -> Js/TeamLead
Get-ChildItem "$Root\Js\Teamlead" -File -ErrorAction SilentlyContinue | ForEach-Object {
    Move-FileSafe $_.FullName "$Root\Js\TeamLead\$($_.Name)"
}

# Loose JS -> Js/Shared
$sharedJs = @(
    'unified_google_signin.js','role_guard.js','hydrate_user_profile.js','common_logout_confirm.js',
    'profile_view_switch.js','messenger_rbac.js','ow_messenger.js','ow_notifications.js',
    'ow_collapsible_sidebar.js','ow_job_categories.js','ow_system_events.js','ow_tailwind_theme.js',
    'ow_tailwind_cdn_warn_shim.js'
)
foreach ($f in $sharedJs) {
    Move-FileSafe "$Root\Js\$f" "$Root\Js\Shared\$f"
}

# Loose API -> Api/Common
$commonApi = @(
    'fetch_messages.php','messages_messages.php','messages_send.php','messages_threads.php',
    'notifications.php','search_jobs.php','session_init.php'
)
foreach ($f in $commonApi) {
    Move-FileSafe "$Root\Api\$f" "$Root\Api\Common\$f"
}

# Root HTML moves
Move-FileSafe "$Root\applicant_landing.html" "$Root\Html\Applicant\applicant_landing.html"
Move-FileSafe "$Root\forgot_password.html" "$Root\Html\Login\forgot_password.html"
Move-FileSafe "$Root\Html\notifications.html" "$Root\Html\Common\notifications.html"

# Root applicant_register.html is legacy; Html/Applicant version is canonical
if (Test-Path "$Root\applicant_register.html") {
    Remove-Item "$Root\applicant_register.html" -Force
    Write-Host "Removed legacy root applicant_register.html"
}

# Index.html lowercase (Windows: Index.html and index.html are the same file — do not delete)
if (Test-Path "$Root\Index.html") {
    Write-Host "index.html already present (case-insensitive); skip rename"
} elseif (-not (Test-Path "$Root\index.html")) {
    Write-Warning "index.html missing — restore manually"
}

# Batch files -> tools
@('fix_mysql_plugin.bat','fix_xampp_mysql.bat','install_composer_and_mail.bat') | ForEach-Object {
    Move-FileSafe "$Root\$_" "$Root\tools\$_"
}

# Remove duplicate/empty folders
@(
    "$Root\Api\Apllicant",
    "$Root\Api\Teamleads",
    "$Root\Html\Teamleads",
    "$Root\Js\Teamlead",
    "$Root\Backend"
) | ForEach-Object {
    if (Test-Path $_) {
        Remove-Item $_ -Recurse -Force -ErrorAction SilentlyContinue
        Write-Host "Removed: $_"
    }
}

Write-Host "File moves complete."
