<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Applicant'
) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

$raw = file_get_contents('php://input');
$input = $raw ? (json_decode($raw, true) ?: []) : [];
$visibility = isset($input['visibility']) ? trim((string)$input['visibility']) : '';

$allowed = ['Public', 'Standard', 'Limited', 'Hidden'];
if ($visibility === '' || !in_array($visibility, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid visibility selection.']);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']);
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
    if (columnExists($conn, 'applicants', $column)) return;
    $conn->query("ALTER TABLE applicants ADD COLUMN " . $definition);
}

ensureApplicantColumn($conn, 'ProfileVisibility', 'ProfileVisibility varchar(20) DEFAULT NULL');
ensureApplicantColumn($conn, 'ProfileVisibilityCompleted', 'ProfileVisibilityCompleted tinyint(1) DEFAULT 0');

try {
    $stmt = $conn->prepare("
        UPDATE applicants
        SET ProfileVisibility = ?, ProfileVisibilityCompleted = 1
        WHERE UserID = ?
    ");
    $stmt->bind_param("si", $visibility, $userId);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save visibility.']);
    exit();
}

echo json_encode([
    'success' => true,
    'redirect' => '../../Html/Applicant/applicant_landing.html'
]);
exit();
?>

