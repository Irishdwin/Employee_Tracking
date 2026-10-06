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
require_once __DIR__ . '/../Helpers/job_hierarchy.php';

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

if ($method === 'GET') {
    // Company list for the "Create Job Order" dropdown.
    if (isset($_GET['list']) && $_GET['list'] === 'companies') {
        $res = $conn->query("SELECT CompanyID, Name FROM clientcompany WHERE IsActive = 1 ORDER BY Name ASC");
        $companies = [];
        if ($res) { while ($row = $res->fetch_assoc()) $companies[] = $row; }
        json_resp(['success' => true, 'companies' => $companies]);
    }

    // Whether the selected client has an active, signed Service Agreement.
    // Reuses the exact same helper/business rule already used when actually
    // posting a Job Order below — not a second implementation of the rule.
    if (isset($_GET['check_agreement'])) {
        $companyId = (int)($_GET['company_id'] ?? 0);
        $agreementIds = $companyId > 0 ? getActiveAgreementsForCompany($conn, $companyId) : [];
        if (empty($agreementIds)) {
            json_resp(['success' => true, 'can_create' => false, 'agreements' => []]);
        }
        $placeholders = implode(',', array_fill(0, count($agreementIds), '?'));
        $types = str_repeat('i', count($agreementIds));
        $stmt = $conn->prepare("SELECT ServiceAgreementID, ContractNumber, ContractStartDate, ContractEndDate FROM serviceagreement WHERE ServiceAgreementID IN ($placeholders)");
        $stmt->bind_param($types, ...$agreementIds);
        $stmt->execute();
        $res = $stmt->get_result();
        $list = [];
        while ($row = $res->fetch_assoc()) $list[] = $row;
        $stmt->close();
        json_resp(['success' => true, 'can_create' => true, 'agreements' => $list]);
    }

    // Single Job Order, for the Edit modal.
    if (isset($_GET['job_order_id'])) {
        $jid = (int)$_GET['job_order_id'];
        $stmt = $conn->prepare("SELECT jo.*, c.Name AS CompanyName FROM joborder jo INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID WHERE jo.JobOrderID = ? LIMIT 1");
        $stmt->bind_param("i", $jid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) json_resp(['success' => false, 'message' => 'Job Order not found.'], 404);
        json_resp(['success' => true, 'job_order' => $row]);
    }

    // Default: the actual posted Job Orders list (joborder table), the one
    // the Admin dashboard's "Job Orders" table renders. This was previously
    // querying job_order_requests instead (a copy of that endpoint's GET),
    // which is why an existing, valid, published Job Order — already visible
    // on the Applicant Job Wall — never appeared here: the frontend reads
    // `d.job_orders`, which this handler never returned.
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 0; // 0 = bare call, no pagination (matches Html/HR/job_orders.html's unpaginated use of this same endpoint)

    $where = "WHERE 1=1";
    $listParams = [];
    $listTypes = '';
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where .= " AND (jo.Position LIKE ? OR c.Name LIKE ? OR jo.Location LIKE ?)";
        $listParams[] = $like; $listParams[] = $like; $listParams[] = $like;
        $listTypes .= 'sss';
    }
    if ($statusFilter === 'Open' || $statusFilter === 'Closed') {
        $where .= " AND jo.Status = ?";
        $listParams[] = $statusFilter;
        $listTypes .= 's';
    }

    $totalCount = 0;
    $countSql = "SELECT COUNT(*) AS cnt FROM joborder jo INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID $where";
    if (!empty($listParams)) {
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param($listTypes, ...$listParams);
        $countStmt->execute();
        $totalCount = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $countStmt->close();
    } else {
        $countRes = $conn->query($countSql);
        $totalCount = $countRes ? (int)($countRes->fetch_assoc()['cnt'] ?? 0) : 0;
    }

    $sql = "SELECT jo.JobOrderID, jo.CompanyID, c.Name AS CompanyName, jo.Position, jo.RequiredCount, jo.Location, jo.WorkSchedule, jo.Status, jo.RequestDate, jo.CreatedAt
            FROM joborder jo
            INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
            $where
            ORDER BY jo.CreatedAt DESC";
    $params = $listParams;
    $types = $listTypes;
    if ($limit > 0) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = ($page - 1) * $limit;
        $types .= 'ii';
    }

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $jobOrders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $jobOrders[] = $row;
        }
    }

    $resp = ['success' => true, 'job_orders' => $jobOrders];
    if ($limit > 0) {
        $resp['pagination'] = [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => (int)ceil($totalCount / $limit),
        ];
    }
    json_resp($resp);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    $action = $input['action'] ?? $_POST['action'] ?? '';

    $userId = (int)$_SESSION['user_id'];
    $now = date('Y-m-d H:i:s');

    if ($action === 'create') {
        try { $hierarchy=jh_validate($conn,jh_id($input['category_id']??null),jh_id($input['classification_id']??null),jh_id($input['sub_classification_id']??null)); }
        catch(DomainException $e){json_resp(['success'=>false,'message'=>$e->getMessage()],400);}
        $companyId = (int)($_POST['company_id'] ?? 0);
        $serviceAgreementId = (int)($_POST['service_agreement_id'] ?? 0);
        $position = trim((string)($_POST['position'] ?? ''));
        $requiredCount = (int)($_POST['required_count'] ?? 0);
        $location = trim((string)($_POST['deployment_site'] ?? ''));
        $workSchedule = trim((string)($_POST['work_schedule'] ?? ''));

        if ($companyId <= 0 || $position === '' || $requiredCount <= 0) {
            json_resp(['success' => false, 'message' => 'Client, position, and required workers are required.'], 400);
        }

        // Same Service Agreement rule already enforced everywhere else in
        // this workflow (job_order_requests.php's approve action, and the
        // check_agreement lookup above) — not a second, separate rule.
        $validAgreements = getActiveAgreementsForCompany($conn, $companyId);
        if (empty($validAgreements) || !in_array($serviceAgreementId, $validAgreements, true)) {
            json_resp(['success' => false, 'message' => 'No active Service Agreement with an uploaded signed document exists for the selected client.'], 400);
        }

        $requestDate = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO joborder (CompanyID, ServiceAgreementID, Position, RequiredCount, Location, WorkSchedule, RequestDate, CreatedBy, category_id,classification_id,sub_classification_id,JobCategory, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,?,?,?, 'Open')");
        $stmt->bind_param("iisisssiiiis", $companyId, $serviceAgreementId, $position, $requiredCount, $location, $workSchedule, $requestDate, $userId,$hierarchy['category_id'],$hierarchy['classification_id'],$hierarchy['sub_classification_id'],$hierarchy['category_name']);
        if (!$stmt->execute()) {
            $err = $conn->error;
            $stmt->close();
            json_resp(['success' => false, 'message' => 'Failed to create Job Order: ' . $err], 500);
        }
        $jobOrderId = $conn->insert_id;
        $stmt->close();
        json_resp(['success' => true, 'message' => 'Job Order created successfully.', 'job_order_id' => $jobOrderId]);
    } elseif ($action === 'update') {
        try { $hierarchy=jh_validate($conn,jh_id($input['category_id']??null),jh_id($input['classification_id']??null),jh_id($input['sub_classification_id']??null)); }
        catch(DomainException $e){json_resp(['success'=>false,'message'=>$e->getMessage()],400);}
        $jobOrderId = (int)($_POST['job_order_id'] ?? 0);
        $position = trim((string)($_POST['position'] ?? ''));
        $requiredCount = (int)($_POST['required_count'] ?? 0);
        $location = trim((string)($_POST['deployment_site'] ?? ''));
        $workSchedule = trim((string)($_POST['work_schedule'] ?? ''));
        $status = ($_POST['status'] ?? 'Open') === 'Closed' ? 'Closed' : 'Open';

        if ($jobOrderId <= 0 || $position === '' || $requiredCount <= 0) {
            json_resp(['success' => false, 'message' => 'Position and required workers are required.'], 400);
        }

        // A consolidated order has separate placement classifications. Updating its header
        // must not silently relabel unrelated placements. A single placement follows its header.
        $details=jh_rows($conn,'SELECT detail_id,category_id,classification_id,sub_classification_id FROM job_order_details WHERE JobOrderID=?','i',[$jobOrderId]);
        if(count($details)>1 && !array_filter($details,fn($d)=>(int)$d['category_id']===(int)$hierarchy['category_id']&&(int)$d['classification_id']===(int)$hierarchy['classification_id']&&(int)$d['sub_classification_id']===(int)$hierarchy['sub_classification_id']))json_resp(['success'=>false,'message'=>'Choose a hierarchy already assigned to a placement in this consolidated order.'],400);
        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE joborder SET Position = ?, RequiredCount = ?, Location = ?, WorkSchedule = ?, Status = ?,category_id=?,classification_id=?,sub_classification_id=?,JobCategory=? WHERE JobOrderID = ?");
        $stmt->bind_param("sisssiiisi", $position, $requiredCount, $location, $workSchedule, $status,$hierarchy['category_id'],$hierarchy['classification_id'],$hierarchy['sub_classification_id'],$hierarchy['category_name'], $jobOrderId);
        if (!$stmt->execute()) {
            $err = $conn->error;
            $stmt->close();
            json_resp(['success' => false, 'message' => 'Failed to update Job Order: ' . $err], 500);
        }
        $stmt->close();
        if(count($details)===1){$s=$conn->prepare('UPDATE job_order_details SET category_id=?,classification_id=?,sub_classification_id=?,job_category=? WHERE JobOrderID=?');$s->bind_param('iiisi',$hierarchy['category_id'],$hierarchy['classification_id'],$hierarchy['sub_classification_id'],$hierarchy['category_name'],$jobOrderId);$s->execute();$s->close();}
        $conn->commit();
        json_resp(['success' => true, 'message' => 'Job Order updated successfully.']);
    } else {
        json_resp(['success' => false, 'message' => 'Invalid action.'], 400);
    }
} else {
    json_resp(['success' => false, 'message' => 'Invalid request method.'], 405);
}
