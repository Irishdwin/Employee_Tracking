<?php
/**
 * POST — Accounting updates a payroll GROUP's status ONCE. This inserts
 * exactly one row into payroll_status_history, keyed by
 * (SiteSupervisorID, CompanyID, CutoffStartDate, CutoffEndDate) — never a
 * per-employee loop. Every employee whose payroll traces back to this
 * group (via payroll.DTR_SubmissionID -> dtr_submissions -> this same key)
 * sees the update automatically the next time they load their Payroll
 * History, with no per-employee write involved.
 *
 * Body (multipart/form-data, since a file may be attached):
 *   site_supervisor_id   Team Lead (users.UsersID)
 *   company_id
 *   cutoff_start          YYYY-MM-DD
 *   cutoff_end            YYYY-MM-DD
 *   status                 one of payroll_status_allowed_values()
 *   remarks                optional
 *   proof_image             optional file (jpg/jpeg/png/pdf, max 10MB)
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$role = trim((string)$_SESSION['role']);
if (!in_array($role, ['Accounting', 'Admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden — only Accounting may update payroll status.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/payroll_status_helper.php';

payroll_status_ensure_schema($conn);

$accountingUserId = (int)$_SESSION['user_id'];

$siteSupervisorId = (int)($_POST['site_supervisor_id'] ?? 0);
$companyId = (int)($_POST['company_id'] ?? 0);
$cutoffStart = trim((string)($_POST['cutoff_start'] ?? ''));
$cutoffEnd = trim((string)($_POST['cutoff_end'] ?? ''));
$status = trim((string)($_POST['status'] ?? ''));
$remarks = trim((string)($_POST['remarks'] ?? ''));

if ($siteSupervisorId <= 0 || $companyId <= 0
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffStart)
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffEnd)
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'site_supervisor_id, company_id, cutoff_start, and cutoff_end are all required.']);
    exit();
}
if (!in_array($status, payroll_status_allowed_values(), true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit();
}

// Confirm this exact Team Lead + Company + Cutoff combination is a real,
// existing payroll group before writing anything against it.
if (!payroll_status_group_exists($conn, $siteSupervisorId, $companyId, $cutoffStart, $cutoffEnd)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No submitted payroll group matches this Team Lead, company, and cutoff period.']);
    exit();
}

// ---- Secure proof image upload (optional) ----
$proofPath = null;
if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['proof_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Proof image upload failed.']);
        exit();
    }

    $maxSize = 10 * 1024 * 1024; // 10MB, same limit as Api/HR/upload_contract.php
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Proof image exceeds the 10MB limit.']);
        exit();
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, PDF.']);
        exit();
    }

    // Verify the ACTUAL file content, not just the claimed extension/name —
    // stops a renamed .php (or anything else) from slipping through with a
    // .jpg name. This is stricter than the existing upload_contract.php
    // precedent, per this feature's explicit security requirements.
    $allowedMimes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'application/pdf' => ['pdf'],
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($realMime === false || !isset($allowedMimes[$realMime]) || !in_array($ext, $allowedMimes[$realMime], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File content does not match its extension — upload rejected.']);
        exit();
    }

    $uploadDir = __DIR__ . '/../../uploads/payroll_status_proof/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    // .htaccess safety net in case this ever sits under a web-executable PHP
    // context — belt-and-suspenders on top of the extension/MIME checks
    // above, which already make a .php upload impossible to reach here.
    $htaccessPath = $uploadDir . '.htaccess';
    if (!file_exists($htaccessPath)) {
        @file_put_contents($htaccessPath, "php_flag engine off\nAddHandler cgi-script .php .phtml .php3 .php4 .php5 .pl .py .cgi\nOptions -ExecCGI\n");
    }

    // Safe, unpredictable filename — never the original client-supplied name.
    $safeName = 'proof_' . $siteSupervisorId . '_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $uploadDir . $safeName;

    // Defensive: destination must resolve to inside $uploadDir (blocks any
    // path-traversal attempt even though $safeName is entirely server-generated).
    $realUploadDir = realpath($uploadDir);
    if ($realUploadDir === false || strpos(realpath(dirname($destPath)) ?: '', $realUploadDir) !== 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Upload path validation failed.']);
        exit();
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save proof image.']);
        exit();
    }

    $proofPath = 'uploads/payroll_status_proof/' . $safeName;
}

try {
    $insStmt = $conn->prepare("
        INSERT INTO payroll_status_history
            (SiteSupervisorID, CompanyID, CutoffStartDate, CutoffEndDate, Status, Remarks, ProofImagePath, UpdatedBy, CreatedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insStmt->bind_param(
        'iisssssi',
        $siteSupervisorId,
        $companyId,
        $cutoffStart,
        $cutoffEnd,
        $status,
        $remarks,
        $proofPath,
        $accountingUserId
    );
    $insStmt->execute();
    $newId = (int)$conn->insert_id;
    $insStmt->close();

    echo json_encode([
        'success' => true,
        'payroll_status_history_id' => $newId,
        'status' => $status,
        'message' => 'Payroll status updated for the entire team. All employees in this payroll group will see this update.',
    ]);
} catch (Throwable $e) {
    error_log('update_payroll_group_status: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update payroll status.']);
}
