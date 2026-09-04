<?php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$raw = file_get_contents('php://input');
$input = $raw ? (json_decode($raw, true) ?: []) : [];

$email = isset($input['email']) ? trim((string)$input['email']) : trim($_POST['email'] ?? '');
$emailKey = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
$otpCode = isset($input['otp_code']) ? trim((string)$input['otp_code']) : trim($_POST['otp_code'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit();
}

if ($otpCode === '' || !preg_match('/^\d{6}$/', $otpCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter the 6-digit verification code.']);
    exit();
}

if (!isset($_SESSION['signin_verify'][$emailKey])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No sign-in code was requested for this email.']);
    exit();
}

$record = $_SESSION['signin_verify'][$emailKey];
$code = isset($record['code']) ? (string)$record['code'] : '';
$expires = isset($record['expires']) ? (int)$record['expires'] : 0;

if ($expires <= 0 || time() > $expires) {
    unset($_SESSION['signin_verify'][$emailKey]);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Your code has expired. Please request a new one.']);
    exit();
}

if ($code !== $otpCode) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please check and try again.']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

function safe_mb_strtolower(string $s): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($s, 'UTF-8');
    }
    return strtolower($s);
}

function columnExistsVs(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

function ensureApplicantColumnVs(mysqli $conn, string $column, string $definition): void
{
    try {
        if (columnExistsVs($conn, 'applicants', $column)) {
            return;
        }
        $conn->query('ALTER TABLE applicants ADD COLUMN ' . $definition);
    } catch (Throwable $e) {
        error_log('ensureApplicantColumnVs: ' . $e->getMessage());
    }
}

try {
    ensureApplicantColumnVs($conn, 'BirthDate', 'BirthDate date DEFAULT NULL');
    ensureApplicantColumnVs($conn, 'ProfileDetailsCompleted', 'ProfileDetailsCompleted tinyint(1) DEFAULT 0');
    ensureApplicantColumnVs($conn, 'ProfileVisibilityCompleted', 'ProfileVisibilityCompleted tinyint(1) DEFAULT 0');
    ensureApplicantColumnVs($conn, 'ProfileStatus', 'ProfileStatus tinyint NOT NULL DEFAULT 0 COMMENT \'0=new,1=terms accepted,2=profile setup complete\'');
    ensureApplicantColumnVs($conn, 'RegistrationTermsAccepted', 'RegistrationTermsAccepted tinyint(1) DEFAULT 0');

    // Backfill ProfileStatus from existing data (safe to run repeatedly).
    @$conn->query("UPDATE applicants SET ProfileStatus = 2 WHERE ProfileDetailsCompleted = 1 AND ProfileVisibilityCompleted = 1 AND BirthDate IS NOT NULL AND ProfileStatus < 2");
    @$conn->query("UPDATE applicants SET ProfileStatus = 1 WHERE COALESCE(RegistrationTermsAccepted,0) = 1 AND ProfileStatus < 2 AND (ProfileDetailsCompleted = 0 OR ProfileVisibilityCompleted = 0 OR BirthDate IS NULL)");

    unset($_SESSION['signin_verify'][$emailKey]);

    $roleStmt = $conn->prepare("SELECT RolesID FROM roles WHERE LOWER(TRIM(RoleName)) = 'applicant' LIMIT 1");
    if (!$roleStmt) {
        throw new RuntimeException('Could not prepare role query.');
    }
    $roleStmt->execute();
    $roleRes = $roleStmt->get_result();
    $roleRow = $roleRes->fetch_assoc();
    $roleStmt->close();

    if (!$roleRow) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Applicant role is not configured in the database (roles table).']);
        exit();
    }
    $applicantRoleId = (int)$roleRow['RolesID'];

    $userStmt = $conn->prepare("
        SELECT u.UsersID, u.FullName, u.Email, r.RoleName
        FROM users u
        INNER JOIN roles r ON u.RoleID = r.RolesID
        WHERE LOWER(TRIM(u.Email)) = LOWER(TRIM(?))
        LIMIT 1
    ");
    if (!$userStmt) {
        throw new RuntimeException('Could not prepare user query.');
    }
    $userStmt->bind_param('s', $email);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    $userRow = $userRes->fetch_assoc();
    $userStmt->close();

    $isNewUser = !$userRow;

    $userId = null;
    $fullName = null;
    $roleName = null;

    if ($userRow) {
        $userId = (int)$userRow['UsersID'];
        $fullName = $userRow['FullName'] ?? '';
        $roleName = $userRow['RoleName'] ?? '';
    }

    if (!$userId) {
        $prefix = explode('@', $email)[0] ?: $email;
        $placeholderName = trim(str_replace(['.', '_', '-'], ' ', $prefix));
        $placeholderName = ucwords(safe_mb_strtolower($placeholderName));
        if ($placeholderName === '') {
            $placeholderName = 'Applicant';
        }

        $randomPassword = bin2hex(random_bytes(10));
        $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);

        $insertUser = $conn->prepare("
            INSERT INTO users (RoleID, FullName, Email, Password, IsActive)
            VALUES (?, ?, ?, ?, 1)
        ");
        if (!$insertUser) {
            throw new RuntimeException('Could not prepare user insert.');
        }
        $insertUser->bind_param('isss', $applicantRoleId, $placeholderName, $email, $hashedPassword);
        $insertUser->execute();
        $userId = (int)$insertUser->insert_id;
        $insertUser->close();

        $fullName = $placeholderName;
        $roleName = 'Applicant';
    } else {
        if (strcasecmp(trim((string)$roleName), 'Applicant') !== 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'This email is registered for a different account type.']);
            exit();
        }
    }

    $appCheck = $conn->prepare('SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1');
    if (!$appCheck) {
        throw new RuntimeException('Could not prepare applicant check.');
    }
    $appCheck->bind_param('i', $userId);
    $appCheck->execute();
    $appRes = $appCheck->get_result();
    $appRow = $appRes->fetch_assoc();
    $appCheck->close();

    if (!$appRow) {
        $mobile = null;
        $profileCompleted = 0;
        $insertApplicant = $conn->prepare('
            INSERT INTO applicants (UserID, FullName, Email, MobileNumber, ProfileCompleted, ProfileStatus)
            VALUES (?, ?, ?, ?, ?, 0)
        ');
        if (!$insertApplicant) {
            throw new RuntimeException('Could not prepare applicant insert.');
        }
        $insertApplicant->bind_param('isssi', $userId, $fullName, $email, $mobile, $profileCompleted);
        $insertApplicant->execute();
        $insertApplicant->close();
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = 'Applicant';
    $_SESSION['full_name'] = $fullName;

    require_once __DIR__ . '/../config/applicant_terms_signing.php';

    $routeStatus = 'existing';

    if ($isNewUser) {
        $sig = applicant_terms_make_sig($email, $userId);
        $redirect = 'Html/Applicant/applicant_terms.html?email=' . rawurlencode($email)
            . '&uid=' . $userId
            . '&sig=' . rawurlencode($sig);
        $routeStatus = 'new';
    } else {
        $chk = $conn->prepare('
            SELECT BirthDate, ProfileDetailsCompleted, ProfileVisibilityCompleted,
                   ProfileStatus, RegistrationTermsAccepted
            FROM applicants
            WHERE UserID = ?
            LIMIT 1
        ');
        $chk->bind_param('i', $userId);
        $chk->execute();
        $cr = $chk->get_result()->fetch_assoc();
        $chk->close();

        $profileComplete = false;
        $profStatus = ($cr && isset($cr['ProfileStatus'])) ? (int)$cr['ProfileStatus'] : 0;
        $termsAccepted = ($cr && isset($cr['RegistrationTermsAccepted'])) ? (int)$cr['RegistrationTermsAccepted'] === 1 : false;

        if ($cr) {
            $bd = $cr['BirthDate'] ?? null;
            $dDone = isset($cr['ProfileDetailsCompleted']) ? (int)$cr['ProfileDetailsCompleted'] === 1 : false;
            $vDone = isset($cr['ProfileVisibilityCompleted']) ? (int)$cr['ProfileVisibilityCompleted'] === 1 : false;
            $eligible = false;
            if ($bd) {
                try {
                    $birth = new DateTimeImmutable($bd);
                    $today = new DateTimeImmutable('today');
                    if ($birth <= $today) {
                        $eligible = $today->diff($birth)->y >= 18;
                    }
                } catch (Throwable $e) {
                    $eligible = false;
                }
            }
            $profileComplete = $dDone && $vDone && $bd && $eligible;
        }

        if ($profileComplete || $profStatus >= 2) {
            if (!$profileComplete && $profStatus >= 2) {
                $profileComplete = true;
            }
            if ($profStatus < 2) {
                $fix = $conn->prepare('UPDATE applicants SET ProfileStatus = 2 WHERE UserID = ?');
                $fix->bind_param('i', $userId);
                $fix->execute();
                $fix->close();
            }
            $redirect = 'applicant_landing.html';
            $routeStatus = 'existing';
        } elseif ($termsAccepted || $profStatus >= 1) {
            $redirect = 'Html/Applicant/applicant_profile_master.html#profile';
            $routeStatus = 'incomplete';
        } else {
            $sig = applicant_terms_make_sig($email, $userId);
            $redirect = 'Html/Applicant/applicant_terms.html?email=' . rawurlencode($email)
                . '&uid=' . $userId
                . '&sig=' . rawurlencode($sig);
            $routeStatus = 'new';
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Verification successful.',
        'redirect' => $redirect,
        'status' => $routeStatus,
    ]);
    exit();
} catch (Throwable $e) {
    $code = (int)$e->getCode();
    $msg = $e->getMessage();
    if ($code === 1062 || stripos($msg, 'Duplicate') !== false) {
        error_log('verify_signin_code duplicate: ' . $msg);
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This email is already registered. Try signing in again or use a different email.']);
        exit();
    }
    if (strpos($msg, 'mysqli') !== false || (class_exists('mysqli_sql_exception', false) && $e instanceof mysqli_sql_exception)) {
        error_log('verify_signin_code mysqli: ' . $msg);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not complete sign-in. Please try again.']);
        exit();
    }
    error_log('verify_signin_code: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not complete sign-in. Please try again.']);
    exit();
}
