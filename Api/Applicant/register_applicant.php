<?php
/**
 * POST JSON or form: email, verification_code, mobile_number, password, confirm_password
 * Creates applicant account after OTP verification.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Helpers/hris_pdo.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';
require_once __DIR__ . '/../Helpers/password_policy.php';

header('Content-Type: application/json; charset=utf-8');

function reg_clear_verification_session(): void
{
    unset(
        $_SESSION['verification_code'],
        $_SESSION['verification_email'],
        $_SESSION['verification_expires'],
        $_SESSION['otp_code'],
        $_SESSION['otp_email'],
        $_SESSION['otp_time'],
        $_SESSION['applicant_verify']
    );
}

function reg_json(bool $success, string $message, array $extra = [], int $http = 200): void
{
    http_response_code($http);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_SESSION['user_id'], $_SESSION['role']) && $_SESSION['role'] === 'Applicant') {
    reg_json(true, 'Already logged in.', [
        'logged_in' => true,
        'redirect' => '../../Html/Applicant/applicant_profile_master.html#profile',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reg_json(false, 'Invalid request method.', [], 405);
}

$raw = file_get_contents('php://input');
$input = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
if (!is_array($input) || $input === []) {
    $input = $_POST;
}

$email = trim((string)($input['email'] ?? ''));
$verificationCode = trim((string)($input['verification_code'] ?? $input['otp_code'] ?? ''));
$mobile = trim((string)($input['mobile_number'] ?? $input['mobile'] ?? ''));
$password = (string)($input['password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? '');

$fieldErrors = [];

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fieldErrors['email'] = 'A valid email address is required.';
}

if ($verificationCode === '' || !preg_match('/^\d{4}$/', $verificationCode)) {
    $fieldErrors['verification_code'] = 'Please enter the 4-digit verification code.';
} else {
    $sessionEmail = (string)($_SESSION['verification_email'] ?? $_SESSION['otp_email'] ?? '');
    $sessionCode = (string)($_SESSION['verification_code'] ?? $_SESSION['otp_code'] ?? '');
    $sessionExpires = (int)($_SESSION['verification_expires'] ?? 0);
    if ($sessionExpires <= 0 && isset($_SESSION['otp_time'])) {
        $sessionExpires = (int)$_SESSION['otp_time'] + 600;
    }

    if ($sessionEmail === '' || $sessionCode === '') {
        $fieldErrors['verification_code'] = 'No verification code was sent. Click "Send code" first.';
    } elseif (time() > $sessionExpires) {
        reg_clear_verification_session();
        $fieldErrors['verification_code'] = 'Verification code has expired. Click "Resend" to get a new code.';
    } elseif (strcasecmp($sessionEmail, $email) !== 0) {
        $fieldErrors['email'] = 'This email does not match the address that received the code.';
    } elseif ($sessionCode !== $verificationCode) {
        $fieldErrors['verification_code'] = 'Invalid verification code. Please check and try again.';
    }
}

if ($mobile === '') {
    $fieldErrors['mobile_number'] = 'Mobile number is required.';
} elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $mobile)) {
    $fieldErrors['mobile_number'] = 'Enter a valid mobile number.';
}

if ($password === '') {
    $fieldErrors['password'] = 'Password is required.';
} else {
    $passwordPolicyError = validate_strong_password($password);
    if ($passwordPolicyError !== null) {
        $fieldErrors['password'] = $passwordPolicyError;
    }
}

if ($confirmPassword === '') {
    $fieldErrors['confirm_password'] = 'Please confirm your password.';
} elseif ($password !== $confirmPassword) {
    $fieldErrors['confirm_password'] = 'Passwords do not match.';
}

if ($fieldErrors !== []) {
    reg_json(false, reset($fieldErrors) ?: 'Please fix the highlighted fields.', ['field_errors' => $fieldErrors], 422);
}

$fullName = trim((string)($input['full_name'] ?? ''));
if ($fullName === '' || strlen($fullName) < 3) {
    $localPart = strstr($email, '@', true) ?: 'Applicant';
    $fullName = ucwords(str_replace(['.', '_', '-'], ' ', $localPart));
    if (strlen($fullName) < 3) {
        $fullName = 'Applicant User';
    }
}

try {
    $pdo = hris_pdo();

    $dup = $pdo->prepare('SELECT UsersID FROM users WHERE Email = ? LIMIT 1');
    $dup->execute([$email]);
    if ($dup->fetchColumn()) {
        reg_json(false, 'This email is already registered. Please log in instead.', ['field_errors' => ['email' => 'This email is already registered.']], 409);
    }

    $roleStmt = $pdo->prepare("SELECT RolesID FROM roles WHERE RoleName = 'Applicant' LIMIT 1");
    $roleStmt->execute();
    $roleId = (int)$roleStmt->fetchColumn();
    if ($roleId <= 0) {
        $pdo->prepare("INSERT INTO roles (RoleName, Description) VALUES ('Applicant', 'Applicant role (auto-created)')")
            ->execute();
        $roleId = (int)$pdo->lastInsertId();
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $isActive = 1;

    $pdo->beginTransaction();

    $userStmt = $pdo->prepare(
        'INSERT INTO users (RoleID, FullName, Email, Password, IsActive, LocalPasswordConfigured) VALUES (?, ?, ?, ?, ?, 1)'
    );
    $userStmt->execute([$roleId, $fullName, $email, $hashedPassword, $isActive]);
    $newUserId = (int)$pdo->lastInsertId();

    $appStmt = $pdo->prepare(
        'INSERT INTO applicants (UserID, FullName, Email, MobileNumber, ProfileCompleted) VALUES (?, ?, ?, ?, 0)'
    );
    $appStmt->execute([$newUserId, $fullName, $email, $mobile]);

    $pdo->commit();

    reg_clear_verification_session();

    $_SESSION['user_id'] = $newUserId;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'Applicant';
    $_SESSION['full_name'] = $fullName;

    // Insert Audit Trail Log
    AuditLogger::log($pdo, 'Applicant', 'Registration', "Applicant {$fullName} registered an account.", (string)$newUserId);

    reg_json(true, 'Your applicant account has been created successfully.', [
        'logged_in' => true,
        'full_name' => $fullName,
        'email' => $email,
        'profile_completed' => false,
        'status' => 'incomplete',
        'redirect' => '../../Html/Applicant/applicant_profile_master.html#profile',
    ], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('register_applicant: ' . $e->getMessage());
    reg_json(false, 'An unexpected error occurred while creating your account. Please try again.', [], 500);
}