<?php
/**
 * Header dropdown: name, saved role, location, application count.
 * Saved jobs count is client-only (localStorage); not stored server-side.
 */
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])
    || strcasecmp(trim((string)$_SESSION['role']), 'Applicant') !== 0) {
    echo json_encode([
        'success' => true,
        'logged_in' => false,
    ]);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
} catch (Throwable $e) {
    error_log('applicant_header_stats db: ' . $e->getMessage());
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

ensureApplicantColumn($conn, 'HomeLocation', 'HomeLocation varchar(150) DEFAULT NULL');
ensureApplicantColumn($conn, 'PreferredClassification', 'PreferredClassification varchar(100) DEFAULT NULL');
ensureApplicantColumn($conn, 'JobCategory', 'JobCategory varchar(255) DEFAULT NULL');

$userId = (int)$_SESSION['user_id'];
$sessionName = isset($_SESSION['full_name']) ? trim((string)$_SESSION['full_name']) : '';

$stmt = $conn->prepare("
    SELECT a.ApplicantID,
           a.FullName,
           a.PreferredClassification,
           a.JobCategory,
           a.HomeLocation,
           (SELECT COUNT(*) FROM job_applications ja WHERE ja.ApplicantID = a.ApplicantID) AS applications_count
    FROM applicants a
    WHERE a.UserID = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'full_name' => $sessionName !== '' ? $sessionName : 'Applicant',
        'saved_role' => '',
        'home_location' => '',
        'applications_count' => 0,
    ]);
    exit();
}

$fullName = trim((string)($row['FullName'] ?? ''));
if ($fullName === '') {
    $fullName = $sessionName !== '' ? $sessionName : 'Applicant';
}

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'full_name' => $fullName,
    'saved_role' => trim((string)($row['PreferredClassification'] ?? '')),
    'saved_job_category' => trim((string)($row['JobCategory'] ?? '')),
    'home_location' => trim((string)($row['HomeLocation'] ?? '')),
    'applications_count' => (int)($row['applications_count'] ?? 0),
]);
exit();
