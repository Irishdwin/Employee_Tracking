<?php
// Handle CORS preflight before auth (OPTIONS doesn't send credentials)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$allowedRoles = ['Admin', 'HR'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/job_categories.php';
require_once __DIR__ . '/../Helpers/job_categories_lookup.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

header('Content-Type: application/json');

function json_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function getActiveAgreementsForCompany($conn, $companyId) {
    $stmt = $conn->prepare("SELECT ServiceAgreementID, ContractNumber, ContractStartDate, ContractEndDate, Status, SignedDocumentPath
        FROM serviceagreement
        WHERE ClientCompanyID = ? AND Status = 'Active'
        AND SignedDocumentPath IS NOT NULL AND SignedDocumentPath != ''
        ORDER BY ContractEndDate DESC");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    $stmt->close();
    return $list;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['list']) && $_GET['list'] === 'companies') {
        $res = $conn->query("SELECT CompanyID, Name FROM clientcompany WHERE IsActive = 1 ORDER BY Name");
        $companies = [];
        if ($res) while ($row = $res->fetch_assoc()) $companies[] = $row;
        json_response(['success' => true, 'companies' => $companies]);
    }

    $checkSa = isset($_GET['check_agreement']) ? (int)$_GET['company_id'] : 0;
    if ($checkSa > 0) {
        $agreements = getActiveAgreementsForCompany($conn, $checkSa);
        if (empty($agreements)) {
            json_response(['success' => false, 'can_create' => false, 'message' => 'No active Service Agreement with uploaded document found for this client. Please ensure the Admin has registered a client, created a Service Agreement, uploaded the signed document, and set status to Active before creating Job Orders.']);
        }
        json_response(['success' => true, 'can_create' => true, 'agreements' => $agreements]);
    }

    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
    $sql = "SELECT j.JobOrderID, j.CompanyID, j.Position, j.RequiredCount, j.Location, j.WorkSchedule, j.TimeIn, j.TimeOut, j.BreakDuration, j.GracePeriod, j.WorkingDays, j.ShiftName, j.RequestDate, j.CreatedAt, c.Name AS CompanyName, s.ContractNumber AS ServiceAgreementRef
            FROM joborder j
            LEFT JOIN clientcompany c ON j.CompanyID = c.CompanyID
            LEFT JOIN serviceagreement s ON j.ServiceAgreementID = s.ServiceAgreementID
            ORDER BY j.CreatedAt DESC";
    if ($companyId > 0) {
        $stmt = $conn->prepare("SELECT j.JobOrderID, j.CompanyID, j.Position, j.RequiredCount, j.Location, j.WorkSchedule, j.TimeIn, j.TimeOut, j.BreakDuration, j.GracePeriod, j.WorkingDays, j.ShiftName, j.RequestDate, j.CreatedAt, c.Name AS CompanyName, s.ContractNumber AS ServiceAgreementRef
            FROM joborder j LEFT JOIN clientcompany c ON j.CompanyID = c.CompanyID LEFT JOIN serviceagreement s ON j.ServiceAgreementID = s.ServiceAgreementID WHERE j.CompanyID = ? ORDER BY j.CreatedAt DESC");
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    $orders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) $orders[] = $row;
    }
    json_response(['success' => true, 'job_orders' => $orders]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $companyId = (int)($_POST['company_id'] ?? 0);
        $serviceAgreementId = (int)($_POST['service_agreement_id'] ?? 0);
        $position = trim($_POST['position'] ?? '');
        $requiredCount = (int)($_POST['required_count'] ?? 0);
        $deploymentSite = trim($_POST['deployment_site'] ?? '');
        $workSchedule = trim($_POST['work_schedule'] ?? '');
        $timeIn = trim($_POST['time_in'] ?? '08:00:00');
        $timeOut = trim($_POST['time_out'] ?? '17:00:00');
        $breakDuration = (int)($_POST['break_duration'] ?? 60);
        $gracePeriod = (int)($_POST['grace_period'] ?? 5);
        $workingDays = trim($_POST['working_days'] ?? 'Mon-Fri');
        $shiftName = trim($_POST['shift_name'] ?? 'Regular Shift');

        $pdo = hris_pdo();
        job_categories_ensure_schema($pdo);
        $categoryId = (int)($_POST['category_id'] ?? $_POST['job_category'] ?? 0);
        $resolved = job_category_resolve_input($pdo, $categoryId, trim((string)($_POST['job_category_name'] ?? '')));
        if (!$resolved && $categoryId <= 0) {
            $resolved = job_category_resolve_input($pdo, 0, trim((string)($_POST['job_category'] ?? '')));
        }
        if (!$resolved && $position !== '') {
            $resolved = job_category_infer_from_text($pdo, $position);
        }

        if ($companyId <= 0 || $position === '' || $requiredCount < 1 || !$resolved) {
            $payload = job_category_invalid_response($position);
            if ($companyId <= 0 || $position === '' || $requiredCount < 1) {
                $payload['message'] = 'Client company, position/role, job category, and number of required workers are required.';
            }
            json_response($payload, 400);
        }

        $categoryId = (int)$resolved['id'];
        $jobCategory = $resolved['category_name'];

        $agreements = getActiveAgreementsForCompany($conn, $companyId);
        if (empty($agreements)) {
            json_response(['success' => false, 'message' => 'Cannot create Job Order: No active Service Agreement with uploaded signed document exists for this client.'], 400);
        }

        $useSaId = $serviceAgreementId > 0 ? $serviceAgreementId : $agreements[0]['ServiceAgreementID'];
        $found = false;
        foreach ($agreements as $a) {
            if ((int)$a['ServiceAgreementID'] === $useSaId) { $found = true; break; }
        }
        if (!$found) $useSaId = $agreements[0]['ServiceAgreementID'];

        $userId = (int)$_SESSION['user_id'];
        $requestDate = date('Y-m-d');

        // Ensure schedule columns exist
        $conn->query("ALTER TABLE joborder ADD COLUMN IF NOT EXISTS TimeIn TIME DEFAULT '08:00:00'");
        $conn->query("ALTER TABLE joborder ADD COLUMN IF NOT EXISTS TimeOut TIME DEFAULT '17:00:00'");
        $conn->query("ALTER TABLE joborder ADD COLUMN IF NOT EXISTS BreakDuration INT UNSIGNED DEFAULT 60");
        $conn->query("ALTER TABLE joborder ADD COLUMN IF NOT EXISTS GracePeriod INT UNSIGNED DEFAULT 5");
        $conn->query("ALTER TABLE joborder ADD COLUMN IF NOT EXISTS WorkingDays VARCHAR(100) DEFAULT 'Mon-Fri'");
        $conn->query("ALTER TABLE joborder ADD COLUMN IF NOT EXISTS ShiftName VARCHAR(100) DEFAULT 'Regular Shift'");

        $stmt = $conn->prepare('INSERT INTO joborder (CompanyID, ServiceAgreementID, Position, category_id, RequiredCount, Location, WorkSchedule, TimeIn, TimeOut, BreakDuration, GracePeriod, WorkingDays, ShiftName, JobCategory, RequestDate, CreatedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iisiisssssiisssi', $companyId, $useSaId, $position, $categoryId, $requiredCount, $deploymentSite, $workSchedule, $timeIn, $timeOut, $breakDuration, $gracePeriod, $workingDays, $shiftName, $jobCategory, $requestDate, $userId);

        if ($stmt->execute()) {
            $id = $conn->insert_id;
            $stmt->close();

            AuditLogger::log($conn, 'Job Order', 'Create', "Created Job Order #{$id} for '{$position}' with schedule {$shiftName} ({$timeIn} - {$timeOut}).", (string)$id);

            json_response(['success' => true, 'message' => 'Job Order created successfully.', 'job_order_id' => $id]);
        }
        json_response(['success' => false, 'message' => 'Database error: ' . $conn->error], 500);
    }
}
json_response(['success' => false, 'message' => 'Invalid request.'], 400);