<?php
require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

if (isset($_SESSION['user_id'])) {
    AuditLogger::log($conn, 'Auth', 'Logout', "User logged out.");
}

$wantsJson = false;
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $wantsJson = true;
}
if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    $wantsJson = true;
}

// Clear session variables
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

if ($wantsJson) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => '/Employee_Tracking/index.html']);
    exit();
}

header('Location: /Employee_Tracking/index.html');
exit();
?>

