<?php
/**
 * GET ?id=X — Streams the proof image for one payroll_status_history row,
 * after confirming the requester is allowed to see it:
 *   - Accounting/Admin: always allowed.
 *   - Employee: only if that history row's payroll group (Team Lead +
 *     Company + Cutoff) matches one of THEIR OWN dtr_submissions rows —
 *     resolved server-side from the session, never trusted from the client.
 *
 * The file itself lives under uploads/payroll_status_proof/ with a
 * server-generated random filename (see update_payroll_group_status.php) —
 * this endpoint is the only sanctioned way to read it, so a guessed/shared
 * direct URL to the uploads folder is not enough on its own to view someone
 * else's proof (still recommend keeping the uploads folder out of direct
 * web access via your webserver config as defense in depth).
 */
declare(strict_types=1);

require_once __DIR__ . '/session_init.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    exit('Unauthorized');
}

require __DIR__ . '/../Db_connection/db_connect.php';

$historyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($historyId <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

$stmt = $conn->prepare("
    SELECT SiteSupervisorID, CompanyID, CutoffStartDate, CutoffEndDate, ProofImagePath
    FROM payroll_status_history
    WHERE PayrollStatusHistoryID = ?
    LIMIT 1
");
$stmt->bind_param('i', $historyId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['ProofImagePath'])) {
    http_response_code(404);
    exit('Not found.');
}

$role = trim((string)$_SESSION['role']);
$authorized = in_array($role, ['Accounting', 'Admin'], true);

if (!$authorized && $role === 'Employee') {
    $userId = (int)$_SESSION['user_id'];
    $check = $conn->prepare("
        SELECT 1
        FROM dtr_submissions ds
        INNER JOIN deployment d ON d.DeploymentID = ds.DeploymentID
        INNER JOIN employee e ON e.EmployeeID = ds.EmployeeID
        INNER JOIN users u ON u.Email = e.Email
        WHERE u.UsersID = ?
          AND ds.SiteSupervisorID = ? AND d.CompanyID = ?
          AND ds.CutoffStartDate = ? AND ds.CutoffEndDate = ?
        LIMIT 1
    ");
    $check->bind_param(
        'iiiss',
        $userId,
        $row['SiteSupervisorID'],
        $row['CompanyID'],
        $row['CutoffStartDate'],
        $row['CutoffEndDate']
    );
    $check->execute();
    $authorized = (bool)$check->get_result()->fetch_assoc();
    $check->close();
}

if (!$authorized) {
    http_response_code(403);
    exit('Forbidden.');
}

$relativePath = $row['ProofImagePath'];
// Defensive: only ever serve a path that is actually inside the expected
// upload folder, never anything constructed from user input.
$baseDir = realpath(__DIR__ . '/../../uploads/payroll_status_proof/');
$fullPath = realpath(__DIR__ . '/../../' . $relativePath);

if ($baseDir === false || $fullPath === false || strpos($fullPath, $baseDir) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found.');
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="payroll-proof-' . $historyId . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
