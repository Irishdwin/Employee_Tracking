<?php
// Session must start before any output (session_start runs inside session_init.php).
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']);
    exit();
}

require_once __DIR__ . '/../config/applicant_terms_signing.php';

$raw = file_get_contents('php://input');
$input = $raw ? (json_decode($raw, true) ?: []) : [];

$userId = 0;

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])
    && strcasecmp(trim((string)$_SESSION['role']), 'Applicant') === 0) {
    $userId = (int)$_SESSION['user_id'];
} else {
    $email = isset($input['email']) ? trim((string)$input['email']) : '';
    $uidIn = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $sigIn = isset($input['sig']) ? trim((string)$input['sig']) : '';

    if ($email !== '' && $uidIn > 0 && $sigIn !== '' && applicant_terms_verify_sig($email, $uidIn, $sigIn)) {
        $stmt = $conn->prepare("
            SELECT u.UsersID, u.FullName, u.Email, r.RoleName
            FROM users u
            INNER JOIN roles r ON u.RoleID = r.RolesID
            WHERE u.UsersID = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $uidIn);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        $roleName = isset($row['RoleName']) ? trim((string)$row['RoleName']) : '';
        if ($row && strcasecmp($roleName, 'Applicant') === 0) {
            $dbEmail = isset($row['Email']) ? trim((string)$row['Email']) : '';
            $emNorm = function_exists('mb_strtolower') ? mb_strtolower($dbEmail, 'UTF-8') : strtolower($dbEmail);
            $inNorm = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
            if ($emNorm === $inNorm) {
                $_SESSION['user_id'] = $uidIn;
                $_SESSION['role'] = 'Applicant';
                $_SESSION['full_name'] = isset($row['FullName']) ? (string)$row['FullName'] : '';
                $userId = $uidIn;
            }
        }
    }
}

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

function ensureApplicantColumn(mysqli $conn, string $column, string $definition): void
{
    if (columnExists($conn, 'applicants', $column)) {
        return;
    }
    $conn->query('ALTER TABLE applicants ADD COLUMN ' . $definition);
}

ensureApplicantColumn($conn, 'RegistrationTermsAccepted', 'RegistrationTermsAccepted tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'RegistrationTermsAcceptedAt', 'RegistrationTermsAcceptedAt datetime DEFAULT NULL');
ensureApplicantColumn($conn, 'ProfileStatus', 'ProfileStatus tinyint NOT NULL DEFAULT 0');

$upd = $conn->prepare('
    UPDATE applicants
    SET RegistrationTermsAccepted = 1,
        RegistrationTermsAcceptedAt = NOW(),
        ProfileStatus = CASE WHEN ProfileStatus < 1 THEN 1 ELSE ProfileStatus END
    WHERE UserID = ?
');
$upd->bind_param('i', $userId);
$upd->execute();
$upd->close();

$_SESSION['applicant_policy_accepted'] = true;
$_SESSION['applicant_registration_terms_accepted'] = true;

echo json_encode([
    'success' => true,
    'message' => 'Registration terms saved.',
]);
exit();
