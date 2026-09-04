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

$stmt = $conn->prepare(
    "SELECT
        u.UsersID AS user_id,
        u.Email AS email,
        u.Password AS password_hash,
        u.FullName AS full_name,
        r.RoleName AS role,
        CASE WHEN u.IsActive = 1 THEN 'Active' ELSE 'Inactive' END AS status
     FROM users u
     INNER JOIN roles r ON u.RoleID = r.RolesID
     WHERE LOWER(TRIM(u.Email)) = LOWER(TRIM(?))
     LIMIT 1"
);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Incorrect email or password.']);
    exit;
}

if (strcasecmp(trim((string)$row['role']), 'Applicant') !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This email is registered for a different account type.']);
    exit;
}

if ($row['status'] !== 'Active') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Account is not active. Please contact support.']);
    exit;
}

$storedPassword = (string)($row['password_hash'] ?? '');
$passwordValid = false;
if ($storedPassword !== '') {
    $hashInfo = password_get_info($storedPassword);
    if (!empty($hashInfo['algo'])) {
        $passwordValid = password_verify($password, $storedPassword);
    } else {
        $passwordValid = hash_equals($storedPassword, $password);
        if ($passwordValid) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upgradeStmt = $conn->prepare('UPDATE users SET Password = ? WHERE UsersID = ?');
            $upgradeStmt->bind_param('si', $newHash, $row['user_id']);
            $upgradeStmt->execute();
            $upgradeStmt->close();
        }
    }
}

if (!$passwordValid) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Incorrect email or password.']);
    exit;
}

$userId = (int)$row['user_id'];
$fullName = trim((string)$row['full_name']);

$_SESSION['user_id'] = $userId;
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
