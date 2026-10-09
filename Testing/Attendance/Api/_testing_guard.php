<?php
/**
 * DEVELOPMENT / TESTING ONLY — Attendance Testing Tool auth guard.
 *
 * Reuses the project's EXISTING session bootstrap (Api/Common/session_init.php)
 * and DB connection (Api/Db_connection/db_connect.php) rather than duplicating
 * them — this file does not modify either production file, it only includes
 * them. Every endpoint under /Testing/Attendance/Api/ requires this file
 * first and gets a ready-to-use mysqli $conn.
 *
 * Restricted to the 'Admin' role. This tool writes directly into the real
 * `attendance` and `daily_time_records` tables, so it is intentionally NOT
 * open to Employee/TeamLead/Accounting sessions.
 */
declare(strict_types=1);

// Never expose this write tool on a public deployment, even to an Admin session.
if (PHP_SAPI !== 'cli' && (!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'], true) || !preg_match('/\A(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?\z/i', $_SERVER['HTTP_HOST']??''))) {
    http_response_code(404);header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'Attendance testing is available only on the local development server.']);exit;
}

require_once __DIR__ . '/../../../Api/Common/session_init.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden — the Attendance Testing Tool is Admin-only (development/testing use).',
    ]);
    exit();
}

require __DIR__ . '/../../../Api/Db_connection/db_connect.php'; // provides mysqli $conn

$testingAdmin=$conn->prepare("SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND r.RoleName='Admin'");
$testingUser=(int)$_SESSION['user_id'];$testingAdmin->bind_param('i',$testingUser);$testingAdmin->execute();
if(!$testingAdmin->get_result()->fetch_assoc()){http_response_code(403);echo json_encode(['success'=>false,'message'=>'An active Admin account is required for attendance testing.']);exit;}
$testingAdmin->close();
