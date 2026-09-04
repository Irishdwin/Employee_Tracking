<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
$allowedRoles = ['Admin', 'HR'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/job_categories.php';
require_once __DIR__ . '/../Helpers/job_categories_lookup.php';
require_once __DIR__ . '/../Helpers/jor_multi_position.php';

function json_resp($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

/**
 * @param array<int, array<string,mixed>> $details
 * @param array<string,mixed> $parentRow
 * @return array{position:string,required_count:int,location:string,work_schedule:string}
 */
function jor_hr_list_summary(array $details, array $parentRow): array
{
    if (empty($details)) {
        return [
            'position' => (string)$parentRow['Position'],
            'required_count' => (int)$parentRow['RequiredCount'],
            'location' => (string)($parentRow['Location'] ?? ''),
            'work_schedule' => (string)($parentRow['WorkSchedule'] ?? ''),
        ];
    }

    $first = $details[0];
    $position = (string)($first['position_title'] ?? $parentRow['Position']);
    $extra = count($details) - 1;
    if ($extra > 0) {
        $position .= ' (+' . $extra . ' more)';
    }

    $totalWorkers = 0;
    $sites = [];
    $schedules = [];
    foreach ($details as $d) {
        $totalWorkers += (int)($d['required_workers'] ?? 0);
        $site = trim((string)($d['deployment_site'] ?? ''));
        if ($site !== '') {
            $sites[$site] = true;
        }
        $sched = trim((string)($d['work_schedule'] ?? ''));
        if ($sched !== '') {
            $schedules[$sched] = true;
        }
    }

    $siteKeys = array_keys($sites);
    $schedKeys = array_keys($schedules);

    return [
        'position' => $position,
        'required_count' => $totalWorkers > 0 ? $totalWorkers : (int)$parentRow['RequiredCount'],
        'location' => count($siteKeys) > 1 ? 'Multiple sites' : ($siteKeys[0] ?? (string)($parentRow['Location'] ?? '')),
        'work_schedule' => count($schedKeys) > 1 ? 'Multiple schedules' : ($schedKeys[0] ?? (string)($parentRow['WorkSchedule'] ?? '')),
    ];
}

function getActiveAgreementsForCompany($conn, $companyId) {
    $stmt = $conn->prepare("SELECT ServiceAgreementID FROM serviceagreement
        WHERE ClientCompanyID = ? AND Status = 'Active'
        AND SignedDocumentPath IS NOT NULL AND SignedDocumentPath != ''
        ORDER BY ContractEndDate DESC");
    if (!$stmt) return [];
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = (int)$row['ServiceAgreementID'];
    $stmt->close();
    return $list;
}

$method = $_SERVER['REQUEST_METHOD'];

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
} catch (Throwable $e) {}
try {
    jor_multi_ensure_schema($conn);
} catch (Throwable $e) {}

if ($method === 'GET') {
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $sql = "SELECT r.RequestID, r.RequestedBy, r.CompanyID, r.Position, r.JobCategory, r.RequiredCount, r.Location, r.WorkSchedule, r.Remarks, r.Status, r.CreatedAt, r.JobOrderID,
            c.Name AS CompanyName,
            u.FullName AS RequestedByName
            FROM job_order_requests r
            INNER JOIN clientcompany c ON r.CompanyID = c.CompanyID
            LEFT JOIN users u ON r.RequestedBy = u.UsersID
            WHERE 1=1";
    $params = [];
    $types = '';
    if ($statusFilter === 'Pending' || $statusFilter === 'Approved' || $statusFilter === 'Rejected') {
        $sql .= " AND r.Status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    $sql .= " ORDER BY r.CreatedAt DESC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $rawRows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rawRows[] = $row;
        }
    }

    $detailsByRequest = [];
    if (!empty($rawRows)) {
        $ids = array_map(static function ($r) {
            return (int)$r['RequestID'];
        }, $rawRows);
        $ids = array_values(array_unique(array_filter($ids)));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $detailSql = "SELECT RequestID, position_title, required_workers, deployment_site, work_schedule
                          FROM job_order_details
                          WHERE RequestID IN ($placeholders)
                          ORDER BY RequestID ASC, detail_id ASC";
            $detailStmt = $conn->prepare($detailSql);
            if ($detailStmt) {
                $detailStmt->bind_param($types, ...$ids);
                $detailStmt->execute();
                $detailRes = $detailStmt->get_result();
                while ($d = $detailRes->fetch_assoc()) {
                    $rid = (int)$d['RequestID'];
                    if (!isset($detailsByRequest[$rid])) {
                        $detailsByRequest[$rid] = [];
                    }
                    $detailsByRequest[$rid][] = $d;
                }
                $detailStmt->close();
            }
        }
    }

    $requests = [];
    foreach ($rawRows as $row) {
        $requestId = (int)$row['RequestID'];
        $details = $detailsByRequest[$requestId] ?? [];
        $summary = jor_hr_list_summary($details, $row);
        $requests[] = [
            'request_id'     => $requestId,
            'jor_id'         => $requestId,
            'company_id'     => (int)$row['CompanyID'],
            'company_name'   => $row['CompanyName'],
            'position'       => $summary['position'],
            'job_category'   => $row['JobCategory'] ?? '',
            'required_count' => $summary['required_count'],
            'location'       => $summary['location'],
            'work_schedule'  => $summary['work_schedule'],
            'remarks'        => $row['Remarks'],
            'status'         => $row['Status'],
            'created_at'     => $row['CreatedAt'],
            'requested_by'   => $row['RequestedByName'],
            'job_order_id'   => $row['JobOrderID'] ? (int)$row['JobOrderID'] : null,
            'deployment_count' => count($details),
        ];
    }
    json_resp(['success' => true, 'requests' => $requests]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    $action = $input['action'] ?? $_POST['action'] ?? '';
    $requestId = (int)($input['request_id'] ?? $_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        json_resp(['success' => false, 'message' => 'Invalid request ID.'], 400);
    }

    $userId = (int)$_SESSION['user_id'];
    $now = date('Y-m-d H:i:s');

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE job_order_requests SET Status = 'Rejected', ProcessedBy = ?, ProcessedAt = ? WHERE RequestID = ? AND Status = 'Pending'");
        $stmt->bind_param("isi", $userId, $now, $requestId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();

            $jorNotifStmt = $conn->prepare("
                INSERT INTO notifications (UserID, NotificationType, Title, Message, RelateedModule, RelatedRecord, IsRead, Priority)
                SELECT 
                    jor.RequestedBy,
                    'job_order_request',
                    'Job Order Request Rejected',
                    CONCAT('Your Job Order Request for ', c.Name, ' has been rejected.'),
                    'job_order_request',
                    jor.RequestID,
                    0,
                    'Normal'
                FROM job_order_requests jor
                INNER JOIN clientcompany c ON jor.CompanyID = c.CompanyID
                WHERE jor.RequestID = ?
            ");
            if ($jorNotifStmt) {
                $jorNotifStmt->bind_param("i", $requestId);
                $jorNotifStmt->execute();
                $jorNotifStmt->close();
            }

            json_resp(['success' => true, 'message' => 'Request rejected.']);
        }
        if ($stmt) $stmt->close();
        json_resp(['success' => false, 'message' => 'Failed to reject request.'], 400);
    } elseif ($action === 'approve') {
        $req = $conn->prepare("SELECT RequestID, CompanyID, Position, JobCategory, category_id, RequiredCount, Location, WorkSchedule FROM job_order_requests WHERE RequestID = ? AND Status = 'Pending' LIMIT 1");
        $req->bind_param("i", $requestId);
        $req->execute();
        $row = $req->get_result()->fetch_assoc();
        $req->close();
        if (!$row) {
            $statusRow = null;
            try {
                $st2 = $conn->prepare("SELECT Status FROM job_order_requests WHERE RequestID = ? LIMIT 1");
                $st2->bind_param("i", $requestId);
                $st2->execute();
                $res2 = $st2->get_result();
                $statusRow = $res2 ? $res2->fetch_assoc() : null;
                $st2->close();
            } catch (Throwable $e) {}

            $currentStatus = $statusRow && isset($statusRow['Status']) ? (string)$statusRow['Status'] : '';
            $msg = 'Request not found or already processed.';
            if ($currentStatus !== '') $msg .= ' Current status: ' . $currentStatus . '.';
            json_resp(['success' => false, 'message' => $msg], 400);
        }

        $companyId = (int)$row['CompanyID'];
        $agreements = getActiveAgreementsForCompany($conn, $companyId);
        if (empty($agreements)) {
            json_resp(['success' => false, 'message' => 'Cannot post Job Order: No active Service Agreement with uploaded document exists for this client.'], 400);
        }
        $serviceAgreementId = $agreements[0];
        $details = jor_fetch_details_for_request($conn, $requestId);

        if (!empty($details)) {
            $first = $details[0];
            $position = (string)$first['position_title'];
            $requiredCount = 0;
            foreach ($details as $d) {
                $requiredCount += (int)$d['required_workers'];
            }
            $location = count($details) === 1
                ? (string)$first['deployment_site']
                : 'Multiple sites';
            $workSchedule = (string)$first['work_schedule'];
            $categoryId = (int)($first['category_id'] ?? 0);
            $jobCategory = (string)($first['job_category'] ?? '');
        } else {
            $position = (string)$row['Position'];
            $requiredCount = (int)$row['RequiredCount'];
            $location = $row['Location'] ?? '';
            $workSchedule = $row['WorkSchedule'] ?? '';
            $categoryId = 0;
            $jobCategory = '';
        }

        $pdo = hris_pdo();
        job_categories_ensure_schema($pdo);

        $resolved = null;
        if ($categoryId > 0) {
            $resolved = job_category_find_by_id($pdo, $categoryId);
        }
        if (!$resolved && $jobCategory !== '') {
            $resolved = job_category_resolve_input($pdo, 0, $jobCategory);
        }
        if (!$resolved && !empty($row['category_id'])) {
            $resolved = job_category_find_by_id($pdo, (int)$row['category_id']);
        }
        if (!$resolved) {
            $jobCategoryRaw = isset($row['JobCategory']) ? (string)$row['JobCategory'] : $jobCategory;
            $resolved = job_category_resolve_input($pdo, 0, $jobCategoryRaw);
        }
        if (!$resolved) {
            $resolved = job_category_infer_from_text($pdo, $position);
        }
        if (!$resolved) {
            json_resp(job_category_invalid_response($position), 400);
        }

        $categoryId = (int)$resolved['id'];
        $jobCategory = $resolved['category_name'];
        $requestDate = date('Y-m-d');

        $hasJobCategory = false;
        $cols = $conn->query("SHOW COLUMNS FROM joborder LIKE 'JobCategory'");
        if ($cols && $cols->num_rows > 0) $hasJobCategory = true;
        if (!$hasJobCategory) {
            try { $conn->query("ALTER TABLE joborder ADD COLUMN JobCategory VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
        }

        $hasWorkSchedule = false;
        $cols = $conn->query("SHOW COLUMNS FROM joborder LIKE 'WorkSchedule'");
        if ($cols && $cols->num_rows > 0) $hasWorkSchedule = true;

        $hasCatId = false;
        try {
            $cc = $conn->query("SHOW COLUMNS FROM joborder LIKE 'category_id'");
            $hasCatId = ($cc && $cc->num_rows > 0);
        } catch (Throwable $e) {}
        if (!$hasCatId) {
            try { $conn->query("ALTER TABLE joborder ADD COLUMN category_id INT UNSIGNED DEFAULT NULL"); $hasCatId = true; } catch (Throwable $e) {}
        }

        if ($hasWorkSchedule && $hasCatId) {
            $ins = $conn->prepare("INSERT INTO joborder (CompanyID, ServiceAgreementID, Position, category_id, RequiredCount, Location, WorkSchedule, JobCategory, RequestDate, CreatedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("iisiissssi", $companyId, $serviceAgreementId, $position, $categoryId, $requiredCount, $location, $workSchedule, $jobCategory, $requestDate, $userId);
        } elseif ($hasWorkSchedule) {
            $ins = $conn->prepare("INSERT INTO joborder (CompanyID, ServiceAgreementID, Position, RequiredCount, Location, WorkSchedule, JobCategory, RequestDate, CreatedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("iisissssi", $companyId, $serviceAgreementId, $position, $requiredCount, $location, $workSchedule, $jobCategory, $requestDate, $userId);
        } elseif ($hasCatId) {
            $ins = $conn->prepare("INSERT INTO joborder (CompanyID, ServiceAgreementID, Position, category_id, RequiredCount, Location, JobCategory, RequestDate, CreatedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("iisiisssi", $companyId, $serviceAgreementId, $position, $categoryId, $requiredCount, $location, $jobCategory, $requestDate, $userId);
        } else {
            $ins = $conn->prepare("INSERT INTO joborder (CompanyID, ServiceAgreementID, Position, RequiredCount, Location, JobCategory, RequestDate, CreatedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("iisisssi", $companyId, $serviceAgreementId, $position, $requiredCount, $location, $jobCategory, $requestDate, $userId);
        }
        if (!$ins->execute()) {
            $err = $conn->error;
            $ins->close();
            json_resp(['success' => false, 'message' => 'Failed to create Job Order: ' . $err], 500);
        }
        $jobOrderId = $conn->insert_id;
        $ins->close();

        if (!empty($details)) {
            $link = $conn->prepare('UPDATE job_order_details SET JobOrderID = ? WHERE RequestID = ?');
            if ($link) {
                $link->bind_param('ii', $jobOrderId, $requestId);
                $link->execute();
                $link->close();
            }
        }

        $upd = $conn->prepare("UPDATE job_order_requests SET Status = 'Approved', ProcessedBy = ?, ProcessedAt = ?, JobOrderID = ? WHERE RequestID = ?");
        $upd->bind_param("isii", $userId, $now, $jobOrderId, $requestId);
        if ($upd->execute()) {
            $upd->close();

            $jorNotifStmt = $conn->prepare("
                INSERT INTO notifications (UserID, NotificationType, Title, Message, RelateedModule, RelatedRecord, IsRead, Priority)
                SELECT 
                    jor.RequestedBy,
                    'job_order_request',
                    'Job Order Request Approved',
                    CONCAT('Your Job Order Request for ', c.Name, ' has been approved.'),
                    'job_order',
                    ?,
                    0,
                    'Normal'
                FROM job_order_requests jor
                INNER JOIN clientcompany c ON jor.CompanyID = c.CompanyID
                WHERE jor.RequestID = ?
            ");
            if ($jorNotifStmt) {
                $jorNotifStmt->bind_param("ii", $jobOrderId, $requestId);
                $jorNotifStmt->execute();
                $jorNotifStmt->close();
            }

            json_resp(['success' => true, 'message' => 'Job Order posted successfully.', 'job_order_id' => $jobOrderId]);
        }
        if ($upd) $upd->close();
        json_resp(['success' => false, 'message' => 'Failed to update request.'], 500);
    } else {
        json_resp(['success' => false, 'message' => 'Invalid action.'], 400);
    }
} else {
    json_resp(['success' => false, 'message' => 'Invalid request method.'], 405);
}