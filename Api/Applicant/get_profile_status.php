<?php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

// Always HTTP 200: `logged_in: false` for guests avoids noisy 401s in the browser network panel.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])
    || strcasecmp(trim((string)$_SESSION['role']), 'Applicant') !== 0) {
    echo json_encode([
        'success' => true,
        'logged_in' => false,
        'profile_completed' => false,
        'message' => 'Not signed in as an applicant.',
    ]);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
} catch (Throwable $e) {
    error_log('get_profile_status db: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable']);
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
    $stmt->bind_param("ss", $table, $column);
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
    $conn->query("ALTER TABLE applicants ADD COLUMN " . $definition);
}

$userId = (int)$_SESSION['user_id'];

ensureApplicantColumn($conn, 'BirthDate', 'BirthDate date DEFAULT NULL');
ensureApplicantColumn($conn, 'ProfileDetailsCompleted', 'ProfileDetailsCompleted tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'ProfileVisibilityCompleted', 'ProfileVisibilityCompleted tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'ProfileCompleted', 'ProfileCompleted tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'ProfileStatus', 'ProfileStatus tinyint NOT NULL DEFAULT 0');
ensureApplicantColumn($conn, 'RegistrationTermsAccepted', 'RegistrationTermsAccepted tinyint(1) DEFAULT 0');

$stmt = $conn->prepare("
    SELECT BirthDate,
           ProfileDetailsCompleted,
           ProfileVisibilityCompleted,
           ProfileCompleted,
           ProfileStatus,
           RegistrationTermsAccepted
    FROM applicants
    WHERE UserID = ?
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

$sessionName = isset($_SESSION['full_name']) ? trim((string)$_SESSION['full_name']) : '';

if (!$row) {
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'status' => 'incomplete',
        'profile_completed' => false,
        'profile_status' => 0,
        'registration_terms_accepted' => false,
        'details_completed' => false,
        'visibility_completed' => false,
        'birth_date' => null,
        'eligible_to_work' => false,
        'age' => null,
        'full_name' => $sessionName,
    ]);
    exit();
}

$birthDateRaw = $row['BirthDate'] ?? null;
$detailsDone = isset($row['ProfileDetailsCompleted']) ? (int)$row['ProfileDetailsCompleted'] === 1 : false;
$visibilityDone = isset($row['ProfileVisibilityCompleted']) ? (int)$row['ProfileVisibilityCompleted'] === 1 : false;
$profileStatus = isset($row['ProfileStatus']) ? (int)$row['ProfileStatus'] : 0;
$registrationTermsAccepted = isset($row['RegistrationTermsAccepted']) ? (int)$row['RegistrationTermsAccepted'] === 1 : false;

$eligible = false;
$age = null;
if ($birthDateRaw) {
    try {
        $birth = new DateTimeImmutable($birthDateRaw);
        $today = new DateTimeImmutable('today');
        if ($birth <= $today) {
            $age = $today->diff($birth)->y;
            $eligible = ($age >= 18);
        }
    } catch (Throwable $e) {
        $eligible = false;
    }
}

// Job Wall: full profile = details + visibility onboarding + 18+ with DOB on file.
$profileCompleted = $detailsDone && $visibilityDone && $birthDateRaw && $eligible;
$status = $profileCompleted ? 'complete' : 'incomplete';

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'status' => $status,
    'profile_completed' => $profileCompleted,
    'profile_status' => $profileStatus,
    'registration_terms_accepted' => $registrationTermsAccepted,
    'details_completed' => $detailsDone,
    'visibility_completed' => $visibilityDone,
    'birth_date' => $birthDateRaw,
    'eligible_to_work' => $eligible,
    'age' => $age,
    'full_name' => $sessionName,
]);
exit();
