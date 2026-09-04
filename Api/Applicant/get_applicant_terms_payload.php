<?php
/**
 * Returns email, user_id, and sig for applicant_terms.html when the URL lacks
 * query params but the user already has an Applicant session (e.g. after Google fix).
 */
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (strcasecmp(trim((string)$_SESSION['role']), 'Applicant') !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Service unavailable']);
    exit();
}

require_once __DIR__ . '/../config/applicant_terms_signing.php';

$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT Email FROM users WHERE UsersID = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row || empty($row['Email'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User email not found']);
    exit();
}

$email = trim((string)$row['Email']);
$sig = applicant_terms_make_sig($email, $userId);

echo json_encode([
    'success' => true,
    'email' => $email,
    'user_id' => $userId,
    'sig' => $sig,
]);
exit();
