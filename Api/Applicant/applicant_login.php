<?php
/**
 * POST JSON or form: email, password
 * Applicant-only password login for the job wall auth modal (no captcha).
 */
require_once __DIR__ . '/../Common/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$raw = file_get_contents('php://input');
$input = is_array($raw ? json_decode($raw, true) : null) ? json_decode($raw, true) : [];
$email = trim((string)($input['email'] ?? $_POST['email'] ?? ''));
$password = (string)($input['password'] ?? $_POST['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

require_once __DIR__.'/../Helpers/auth_password_protection.php';
try{$auth=auth_password_login($conn,$email,$password,'Applicant');}catch(Throwable $e){error_log('Password authentication unavailable.');http_response_code(503);echo json_encode(['success'=>false,'message'=>'Unable to sign in. Please try again later.']);exit;}
if(!$auth['success']){http_response_code($auth['retry_after']?429:401);header('Cache-Control: no-store');echo json_encode($auth);exit;}
$row=$auth['user'];
session_regenerate_id(true);
AuditLogger::log($conn,'Auth','Login','Password authentication succeeded.',(string)$row['user_id']);
$userId = (int)$row['user_id'];
$configured = $conn->prepare('UPDATE users SET LocalPasswordConfigured=1 WHERE UsersID=?');
$configured->bind_param('i', $userId);
$configured->execute();
$configured->close();
$fullName = trim((string)$row['full_name']);

$_SESSION['user_id'] = $userId;
unset($_SESSION['verified_google_login']);
            $_SESSION['last_activity'] = time(); // A successful login starts a fresh inactivity cycle.
$_SESSION['email'] = (string)$row['email'];
$_SESSION['role'] = 'Applicant';
$_SESSION['full_name'] = $fullName;

$profileCompleted = false;
$profileStatus = 0;

$appStmt = $conn->prepare(
    'SELECT ProfileDetailsCompleted, ProfileVisibilityCompleted, BirthDate, ProfileStatus
     FROM applicants WHERE UserID = ? LIMIT 1'
);
if ($appStmt) {
    $appStmt->bind_param('i', $userId);
    $appStmt->execute();
    $appRow = $appStmt->get_result()->fetch_assoc();
    $appStmt->close();
    if ($appRow) {
        $profileStatus = isset($appRow['ProfileStatus']) ? (int)$appRow['ProfileStatus'] : 0;
        $detailsDone = isset($appRow['ProfileDetailsCompleted']) ? (int)$appRow['ProfileDetailsCompleted'] === 1 : false;
        $visibilityDone = isset($appRow['ProfileVisibilityCompleted']) ? (int)$appRow['ProfileVisibilityCompleted'] === 1 : false;
        $birthDateRaw = $appRow['BirthDate'] ?? null;
        $eligible = false;
        if ($birthDateRaw) {
            try {
                $birth = new DateTimeImmutable($birthDateRaw);
                $today = new DateTimeImmutable('today');
                if ($birth <= $today) {
                    $eligible = $today->diff($birth)->y >= 18;
                }
            } catch (Throwable $e) {
                $eligible = false;
            }
        }
        $profileCompleted = $detailsDone && $visibilityDone && $birthDateRaw && $eligible;
    }
}

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'full_name' => $fullName,
    'email' => (string)$row['email'],
    'profile_completed' => $profileCompleted,
    'profile_status' => $profileStatus,
    'status' => $profileCompleted ? 'complete' : 'incomplete',
]);
