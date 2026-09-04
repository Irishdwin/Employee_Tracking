<?php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = trim((string)($_SESSION['role'] ?? ''));
if (!in_array($role, ['TeamLead', 'Team Lead'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/job_categories.php';
require_once __DIR__ . '/../Helpers/job_categories_lookup.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

function json_resp($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function company_has_active_agreement(mysqli $conn, int $companyId): bool {
    if ($companyId <= 0) return false;
    $sql = "SELECT ServiceAgreementID
            FROM serviceagreement
            WHERE ClientCompanyID = ?
              AND Status = 'Active'
              AND COALESCE(NULLIF(TRIM(SignedDocumentPath), ''), '') <> ''
            ORDER BY ServiceAgreementID DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $has = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $has;
}

$conn->query("CREATE TABLE IF NOT EXISTS job_order_requests (
    RequestID INT(11) NOT NULL AUTO_INCREMENT,
    RequestedBy INT(11) NOT NULL,
    CompanyID INT(11) NOT NULL,
    Position VARCHAR(100) NOT NULL,
    JobCategory VARCHAR(255) DEFAULT NULL,
    RequiredCount INT(11) NOT NULL DEFAULT 1,
    Location VARCHAR(150) DEFAULT NULL,
    WorkSchedule VARCHAR(100) DEFAULT NULL,
    Remarks TEXT DEFAULT NULL,
    Status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    ProcessedBy INT(11) DEFAULT NULL,
    ProcessedAt DATETIME DEFAULT NULL,
    JobOrderID INT(11) DEFAULT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (RequestID),
    KEY fk_jor_requested (RequestedBy),
    KEY fk_jor_company (CompanyID),
    KEY fk_jor_processed (ProcessedBy),
    KEY idx_jor_joborder (JobOrderID),
    CONSTRAINT fk_jor_requested FOREIGN KEY (RequestedBy) REFERENCES users (UsersID),
    CONSTRAINT fk_jor_company FOREIGN KEY (CompanyID) REFERENCES clientcompany (CompanyID),
    CONSTRAINT fk_jor_processed FOREIGN KEY (ProcessedBy) REFERENCES users (UsersID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    $cols = $conn->query("SHOW COLUMNS FROM job_order_requests LIKE 'JobCategory'");
    if ($cols && $cols->num_rows === 0) {
        $conn->query("ALTER TABLE job_order_requests ADD COLUMN JobCategory VARCHAR(255) DEFAULT NULL");
    }
    $cid = $conn->query("SHOW COLUMNS FROM job_order_requests LIKE 'category_id'");
    if ($cid && $cid->num_rows === 0) {
        $conn->query("ALTER TABLE job_order_requests ADD COLUMN category_id INT UNSIGNED DEFAULT NULL");
    }
} catch (Throwable $e) {}
try {
    $pdoBoot = hris_pdo();
    job_categories_ensure_schema($pdoBoot);
} catch (Throwable $e) {}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $conn->prepare("
        SELECT
            r.RequestID,
            r.CompanyID,
            c.Name AS CompanyName,
            r.Position,
            r.JobCategory,
            r.category_id,
            jc.category_name AS job_category_name,
            r.RequiredCount,
            r.Location,
            r.WorkSchedule,
            r.Remarks,
            r.Status,
            r.CreatedAt
        FROM job_order_requests r
        INNER JOIN clientcompany c ON r.CompanyID = c.CompanyID
        LEFT JOIN job_categories jc ON jc.id = r.category_id
        WHERE r.RequestedBy = ?
        ORDER BY r.CreatedAt DESC
    ");
    if (!$stmt) json_resp(['success' => false, 'message' => 'Failed to prepare query.'], 500);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $positionName = trim((string)($r['Position'] ?? ''));
        if ($positionName === '0') {
            $positionName = '';
        }
        $rows[] = [
            'request_id' => (int)$r['RequestID'],
            'company_id' => (int)$r['CompanyID'],
            'company_name' => $r['CompanyName'] ?? '',
            'position' => $positionName,
            'position_name' => $positionName,
            'job_category' => trim((string)($r['job_category_name'] ?? $r['JobCategory'] ?? '')),
            'category_id' => isset($r['category_id']) ? (int)$r['category_id'] : 0,
            'required_count' => (int)$r['RequiredCount'],
            'location' => $r['Location'] ?? '',
            'work_schedule' => $r['WorkSchedule'] ?? '',
            'remarks' => $r['Remarks'] ?? '',
            'status' => $r['Status'] ?? 'Pending',
            'created_at' => $r['CreatedAt'],
        ];
    }
    $stmt->close();
    json_resp(['success' => true, 'requests' => $rows]);
}

if ($method === 'POST') {
    $companyId = (int)($_POST['company_id'] ?? 0);
    $position = trim($_POST['position'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? $_POST['job_category'] ?? 0);
    $categoryName = trim((string)($_POST['job_category_name'] ?? ''));
    $requiredCount = (int)($_POST['required_count'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $workSchedule = trim($_POST['work_schedule'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    $pdo = hris_pdo();
    job_categories_ensure_schema($pdo);
    $resolved = job_category_resolve_input($pdo, $categoryId, $categoryName);
    if (!$resolved && $categoryId <= 0) {
        $resolved = job_category_resolve_input($pdo, 0, trim((string)($_POST['job_category'] ?? '')));
    }
    if (!$resolved && $position !== '') {
        $resolved = job_category_infer_from_text($pdo, $position);
    }

    if ($companyId <= 0 || $position === '' || $requiredCount <= 0 || !$resolved) {
        $payload = job_category_invalid_response($position);
        if ($companyId <= 0 || $position === '' || $requiredCount <= 0) {
            $payload['message'] = 'Company, position, job category, and number of workers are required.';
        }
        json_resp($payload, 400);
    }

    $categoryId = (int)$resolved['id'];
    $jobCategory = $resolved['category_name'];

    $checkDep = $conn->prepare('SELECT DeploymentID FROM deployment WHERE SiteSuperVisorID = ? AND CompanyID = ? LIMIT 1');
    if (!$checkDep) json_resp(['success' => false, 'message' => 'Failed to validate assignment.'], 500);
    $checkDep->bind_param('ii', $userId, $companyId);
    $checkDep->execute();
    $assigned = $checkDep->get_result()->num_rows > 0;
    $checkDep->close();
    if (!$assigned) {
        $checkCc = $conn->prepare('SELECT CompanyID FROM clientcompany WHERE CompanyID = ? AND UserID = ? LIMIT 1');
        if (!$checkCc) json_resp(['success' => false, 'message' => 'Failed to validate assignment.'], 500);
        $checkCc->bind_param('ii', $companyId, $userId);
        $checkCc->execute();
        $assigned = $checkCc->get_result()->num_rows > 0;
        $checkCc->close();
    }
    if (!$assigned) {
        json_resp(['success' => false, 'message' => 'You can only request job orders for companies assigned to you.'], 403);
    }

    if (!company_has_active_agreement($conn, $companyId)) {
        json_resp([
            'success' => false,
            'message' => 'Your company needs to upload an active service agreement before requesting a job order.',
            'error_code' => 'NO_ACTIVE_SERVICE_AGREEMENT',
        ], 403);
    }

    $hasCatId = false;
    try {
        $cc = $conn->query("SHOW COLUMNS FROM job_order_requests LIKE 'category_id'");
        $hasCatId = ($cc && $cc->num_rows > 0);
    } catch (Throwable $e) {}

    if ($hasCatId) {
        $ins = $conn->prepare("INSERT INTO job_order_requests (RequestedBy, CompanyID, Position, category_id, JobCategory, RequiredCount, Location, WorkSchedule, Remarks, Status)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        if (!$ins) json_resp(['success' => false, 'message' => 'Failed to create request.'], 500);
        $ins->bind_param("iisisisss", $userId, $companyId, $position, $categoryId, $jobCategory, $requiredCount, $location, $workSchedule, $remarks);
    } else {
        $ins = $conn->prepare("INSERT INTO job_order_requests (RequestedBy, CompanyID, Position, JobCategory, RequiredCount, Location, WorkSchedule, Remarks, Status)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        if (!$ins) json_resp(['success' => false, 'message' => 'Failed to create request.'], 500);
        $ins->bind_param("iisissss", $userId, $companyId, $position, $jobCategory, $requiredCount, $location, $workSchedule, $remarks);
    }
    if (!$ins->execute()) {
        $ins->close();
        json_resp(['success' => false, 'message' => 'Failed to save request.'], 500);
    }
    $newId = (int)$conn->insert_id;
    $ins->close();

    AuditLogger::log($conn, 'Job Order', 'Submit Request', "Submitted Job Order Request #{$newId} for '{$position}' ({$requiredCount} worker/s).", (string)$newId);

    json_resp(['success' => true, 'message' => 'Job order request submitted.', 'request_id' => $newId]);
}

json_resp(['success' => false, 'message' => 'Invalid request method.'], 405);