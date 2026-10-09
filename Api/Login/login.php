<?php
require_once __DIR__ . '/../Common/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';
header('Cache-Control: no-store');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $wantsJson = false;
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $wantsJson = true;
    }
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        $wantsJson = true;
    }

    $json_fail = function ($message, $httpCode = 400, $code = null) use ($wantsJson) {
        if ($wantsJson) {
            http_response_code($httpCode);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message, 'code' => $code]);
            exit();
        }
        $msg = urlencode($message);
        header("Location: ../../index.html?error={$msg}");
        exit();
    };

    $json_success = function ($redirectUrl) use ($wantsJson) {
        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
            exit();
        }
        header("Location: " . $redirectUrl);
        exit();
    };
    
    // Validate and consume this page's server-held challenge before authentication.
    require_once __DIR__.'/captcha_helper.php';
    $captcha=login_captcha_validate($_POST['captcha_challenge_id'] ?? null,$_POST['captcha_input'] ?? null,true);
    if(!$captcha['valid'])$json_fail($captcha['message'],400,'captcha_'.$captcha['code']);

    // 2. AUTHENTICATION
    // Accept email or employee number from the shared login field.
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($username === '' || $password === '') {
        $json_fail("Username and password are required.", 400);
    }

    require_once __DIR__.'/../Helpers/auth_password_protection.php';
    try{$auth=auth_password_login($conn,$username,$password);}catch(Throwable $e){error_log('Password authentication unavailable.');$json_fail('Unable to sign in. Please try again later.',503,'auth_unavailable');}
    if(!$auth['success']) {
        if($wantsJson){http_response_code($auth['retry_after']?429:401);header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode($auth);exit;}
        $json_fail($auth['message'],401,$auth['code']);
    }
    $row=$auth['user'];
    session_regenerate_id(true); // Preserve fixation protection after credential verification.
    $configured = $conn->prepare('UPDATE users SET LocalPasswordConfigured=1 WHERE UsersID=?');
    $configured->bind_param('i', $row['user_id']);
    $configured->execute();
    $configured->close();
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['full_name'] = $row['full_name'];
    unset($_SESSION['verified_google_login']);
    $_SESSION['last_activity'] = time(); // starts the idle-timeout clock fresh on login

    // Audit Trail Logger
    require_once __DIR__ . '/../Helpers/AuditLogger.php';
    AuditLogger::log($conn, 'Auth', 'Login', "User {$row['full_name']} logged in successfully.", (string)$row['user_id']);

    // RBAC Redirection (organized by role folders)
    // NOTE: Admin dashboard HTML is at /Html/Admin/, HR at /Html/HR/
    $redirects = [
        'Admin'      => '/Employee_Tracking/Html/Admin/admin_dashboard.html',
        'HR'         => '/Employee_Tracking/Html/HR/hr_dashboard.html',
        'Accounting' => '/Employee_Tracking/Html/Accounting/accounting_dashboard.html',
        'TeamLead'   => '/Employee_Tracking/Html/TeamLead/team_lead_dashboard.html',
        'Applicant'  => '/Employee_Tracking/Html/Applicant/applicant_landing.html',
        'Employee'   => '/Employee_Tracking/Html/Employee/employee_dashboard.html'
    ];

    $json_success($redirects[$row['role']] ?? '../index.html');

}
