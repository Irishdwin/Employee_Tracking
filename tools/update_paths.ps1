# Bulk path updates after project restructure
$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot

$ext = @('*.html', '*.js', '*.php', '*.css', '*.bat')
$files = Get-ChildItem -Path $Root -Recurse -Include $ext -File |
    Where-Object { $_.FullName -notmatch '\\vendor\\' }

function Replace-InFiles($files, [string]$old, [string]$new) {
    if ($old -eq $new) { return }
    $count = 0
    foreach ($f in $files) {
        $text = [IO.File]::ReadAllText($f.FullName)
        if ($text.Contains($old)) {
            $updated = $text.Replace($old, $new)
            [IO.File]::WriteAllText($f.FullName, $updated)
            $count++
        }
    }
    if ($count -gt 0) { Write-Host "Replaced '$old' in $count file(s)" }
}

# PHP session bootstrap
Replace-InFiles $files "__DIR__ . '/../session_init.php'" "__DIR__ . '/../Common/session_init.php'"
Replace-InFiles $files '__DIR__ . "/../session_init.php"' '__DIR__ . "/../Common/session_init.php"'

# Accounting export
Replace-InFiles $files '../../Backend/export_client_billing.php' '../../Api/Accounting/export_client_billing.php'

# Shared JS (dashboard HTML under Html/*)
$sharedScripts = @(
    'role_guard.js','hydrate_user_profile.js','common_logout_confirm.js','profile_view_switch.js',
    'messenger_rbac.js','ow_messenger.js','ow_notifications.js','ow_collapsible_sidebar.js',
    'ow_job_categories.js','ow_system_events.js','ow_tailwind_theme.js','ow_tailwind_cdn_warn_shim.js',
    'unified_google_signin.js'
)
foreach ($s in $sharedScripts) {
    Replace-InFiles $files "../../Js/$s" "../../Js/Shared/$s"
    Replace-InFiles $files "../Js/$s" "../Js/Shared/$s"
}

# Common API endpoints
Replace-InFiles $files '../../Api/notifications.php' '../../Api/Common/notifications.php'
Replace-InFiles $files '../../Api/messages_threads.php' '../../Api/Common/messages_threads.php'
Replace-InFiles $files '../../Api/messages_messages.php' '../../Api/Common/messages_messages.php'
Replace-InFiles $files '../../Api/messages_send.php' '../../Api/Common/messages_send.php'
Replace-InFiles $files '../../Api/fetch_messages.php' '../../Api/Common/fetch_messages.php'
Replace-InFiles $files '../../Api/search_jobs.php' '../../Api/Common/search_jobs.php'
Replace-InFiles $files '../Api/notifications.php' '../Api/Common/notifications.php'

# Applicant landing (moved under Html/Applicant) — absolute URL paths only
Replace-InFiles $files '/Employee_Tracking/applicant_landing.html' '/Employee_Tracking/Html/Applicant/applicant_landing.html'

# Notifications page
Replace-InFiles $files '../../Html/notifications.html' '../../Html/Common/notifications.html'

# role_guard applicant dashboard (from Html/* subfolders)
$rg = Join-Path $Root 'Js\Shared\role_guard.js'
if (Test-Path $rg) {
    $t = [IO.File]::ReadAllText($rg)
    $t = $t.Replace("applicant: '../../applicant_landing.html'", "applicant: '../../Html/Applicant/applicant_landing.html'")
    $t = $t.Replace("applicant: 'applicant_landing.html'", "applicant: '../../Html/Applicant/applicant_landing.html'")
    [IO.File]::WriteAllText($rg, $t)
}

# Applicant PHP redirects (relative from Api/Applicant)
Replace-InFiles $files "'../../applicant_landing.html'" "'../../Html/Applicant/applicant_landing.html'"
Replace-InFiles $files '"../../applicant_landing.html"' '"../../Html/Applicant/applicant_landing.html"'

# login.php applicant redirect
$loginPhp = Join-Path $Root 'Api\Login\login.php'
if (Test-Path $loginPhp) {
    $t = [IO.File]::ReadAllText($loginPhp)
    $t = $t.Replace("'/Employee_Tracking/applicant_landing.html'", "'/Employee_Tracking/Html/Applicant/applicant_landing.html'")
    [IO.File]::WriteAllText($loginPhp, $t)
}

# google oauth config paths
Replace-InFiles $files '/Employee_Tracking/applicant_landing.html' '/Employee_Tracking/Html/Applicant/applicant_landing.html'

# Fix export_client_billing.php requires (was Backend layout)
$exportFile = Join-Path $Root 'Api\Accounting\export_client_billing.php'
if (Test-Path $exportFile) {
    $t = [IO.File]::ReadAllText($exportFile)
    $t = $t.Replace("__DIR__ . '/../Api/session_init.php'", "__DIR__ . '/../Common/session_init.php'")
    $t = $t.Replace("require __DIR__ . '/../Api/Db_connection/db_connect.php'", "require __DIR__ . '/../Db_connection/db_connect.php'")
    $t = $t.Replace("__DIR__ . '/../Api/Helpers/client_billing_helper.php'", "__DIR__ . '/../Helpers/client_billing_helper.php'")
    $t = $t.Replace("__DIR__ . '/../vendor/autoload.php'", "__DIR__ . '/../../vendor/autoload.php'")
    [IO.File]::WriteAllText($exportFile, $t)
    Write-Host 'Fixed Api/Accounting/export_client_billing.php paths'
}

# Applicant landing internal asset paths (now in Html/Applicant/)
$landing = Join-Path $Root 'Html\Applicant\applicant_landing.html'
if (Test-Path $landing) {
    $t = [IO.File]::ReadAllText($landing)
    $t = $t.Replace('src="Images/', 'src="../../Images/')
    $t = $t.Replace('href="Css/', 'href="../../Css/')
    $t = $t.Replace('src="Js/', 'src="../../Js/')
    $t = $t.Replace("href=""index.html""", 'href="../../index.html"')
    $t = $t.Replace('href="Html/Applicant/', 'href="')
    $t = $t.Replace('Html/Applicant/applicant_google_callback.html', 'applicant_google_callback.html')
    $t = $t.Replace('Html/Common/notifications.html', '../Common/notifications.html')
    $t = $t.Replace('/Employee_Tracking/Html/Applicant/Html/Applicant/', '/Employee_Tracking/Html/Applicant/')
    [IO.File]::WriteAllText($landing, $t)
    Write-Host 'Fixed Html/Applicant/applicant_landing.html paths'
}

# Login forgot_password.html assets
$fp = Join-Path $Root 'Html\Login\forgot_password.html'
if (Test-Path $fp) {
    $t = [IO.File]::ReadAllText($fp)
    $t = $t.Replace('href="Css/login.css"', 'href="../../Css/login.css"')
    $t = $t.Replace('href="index.html"', 'href="../../index.html"')
    if ($t -match 'Html/Login/Html/Login/') {
        $t = $t.Replace('Html/Login/Html/Login/forgot_password.html', 'Html/Login/forgot_password.html')
    }
    [IO.File]::WriteAllText($fp, $t)
    Write-Host 'Fixed Html/Login/forgot_password.html paths'
}

Write-Host 'Path updates complete.'
