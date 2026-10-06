<?php
require_once __DIR__ . '/../Common/session_init.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Common/NotificationHelper.php';
require_once __DIR__ . '/../Helpers/client_company_status.php';
require_once __DIR__ . '/../Helpers/service_agreement_notify_helper.php';
require_once __DIR__ . '/../Helpers/contract_expiry_helper.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

header('Content-Type: application/json');

service_agreement_contract_type_ensure_schema($conn);

function json_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

$baseUploadDir = dirname(__DIR__, 2) . '/uploads/service_agreements/';
if (!is_dir($baseUploadDir)) {
    mkdir($baseUploadDir, 0755, true);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
    $agreementId = isset($_GET['agreement_id']) ? (int)$_GET['agreement_id'] : 0;

    if ($agreementId > 0) {
        $stmt = $conn->prepare("SELECT s.*, c.Name AS CompanyName FROM serviceagreement s LEFT JOIN clientcompany c ON s.ClientCompanyID = c.CompanyID WHERE s.ServiceAgreementID = ?");
        $stmt->bind_param("i", $agreementId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        if (!$row) json_response(['success' => false, 'message' => 'Agreement not found.'], 404);
        json_response(['success' => true, 'agreement' => $row]);
    }

    if ($companyId > 0) {
        // Unchanged: existing "agreements for one company" lookup (used by
        // the client company view/edit modal) — not paginated, since that
        // caller expects the full list for a single company, not a page of
        // the overall Admin table.
        $stmt = $conn->prepare(
            "SELECT s.ServiceAgreementID, s.ClientCompanyID, s.ContractNumber, s.ContractStartDate, s.ContractEndDate, s.SignedDocumentPath, s.Status, s.CreatedDate, c.Name AS CompanyName
             FROM serviceagreement s
             LEFT JOIN clientcompany c ON s.ClientCompanyID = c.CompanyID
             WHERE s.ClientCompanyID = ?
             ORDER BY s.CreatedDate DESC"
        );
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $list = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $list[] = $row;
            }
        }
        json_response(['success' => true, 'agreements' => $list]);
    }

    // ST-500: search + status filter + pagination for the main Admin
    // Service Agreements list (no company_id given). `Status` is the real
    // DB enum('Active','Expired') column already used for the Status badge
    // in the UI, so the filter matches it exactly.
    $search = trim($_GET['search'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $whereClauses = [];
    $types = '';
    $bindValues = [];

    if ($search !== '') {
        $whereClauses[] = '(s.ContractNumber LIKE ? OR c.Name LIKE ?)';
        $searchTerm = "%{$search}%";
        array_push($bindValues, $searchTerm, $searchTerm);
        $types .= 'ss';
    }

    if ($statusFilter === 'Active' || $statusFilter === 'Expired') {
        $whereClauses[] = 's.Status = ?';
        $bindValues[] = $statusFilter;
        $types .= 's';
    }

    $whereSql = $whereClauses ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

    $countStmt = $conn->prepare(
        "SELECT COUNT(*) FROM serviceagreement s LEFT JOIN clientcompany c ON s.ClientCompanyID = c.CompanyID {$whereSql}"
    );
    if (!empty($bindValues)) {
        $countStmt->bind_param($types, ...$bindValues);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    $listSql = "SELECT s.ServiceAgreementID, s.ClientCompanyID, s.ContractNumber, s.ContractStartDate, s.ContractEndDate, s.SignedDocumentPath, s.Status, s.CreatedDate, c.Name AS CompanyName
                FROM serviceagreement s
                LEFT JOIN clientcompany c ON s.ClientCompanyID = c.CompanyID
                {$whereSql}
                ORDER BY s.CreatedDate DESC
                LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($listSql);
    $paginationTypes = $types . 'ii';
    $paginationValues = array_merge($bindValues, [$limit, $offset]);
    $stmt->bind_param($paginationTypes, ...$paginationValues);
    $stmt->execute();
    $result = $stmt->get_result();

    $list = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $list[] = $row;
        }
    }
    json_response([
        'success' => true,
        'agreements' => $list,
        'pagination' => [
            'total' => $totalRecords,
            'page' => $page,
            'limit' => $limit,
            'pages' => max(1, (int)ceil($totalRecords / $limit)),
        ],
    ]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $clientCompanyId = (int)($_POST['client_company_id'] ?? 0);
        $contractNumber = trim($_POST['contract_number'] ?? '');
        $contractStart = trim($_POST['contract_start_date'] ?? '');
        $contractEnd = trim($_POST['contract_end_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');
        $notes = trim($_POST['notes'] ?? '');
        $contractType = service_agreement_contract_type_normalize($_POST['contract_type'] ?? 'Open');

        if ($clientCompanyId <= 0 || $contractNumber === '' || $contractStart === '') {
            json_response(['success' => false, 'message' => 'Client company, contract number, and start date are required.'], 400);
        }
        if (!in_array($status, ['Active', 'Expired'])) $status = 'Active';

        // File upload required
        $signedPath = '';
        if (!empty($_FILES['signed_document']['name']) && $_FILES['signed_document']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['signed_document']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowed)) {
                json_response(['success' => false, 'message' => 'Only PDF, JPG, and PNG files are allowed.'], 400);
            }
            $filename = 'sa_' . $clientCompanyId . '_' . time() . '.' . $ext;
            $targetPath = $baseUploadDir . $filename;
            if (move_uploaded_file($_FILES['signed_document']['tmp_name'], $targetPath)) {
                $signedPath = 'uploads/service_agreements/' . $filename;
            }
        }
        if ($signedPath === '') {
            json_response(['success' => false, 'message' => 'Upload of the signed Service Agreement document is required before creation.'], 400);
        }

        $userId = (int)$_SESSION['user_id'];
        $contractEndVal = $contractEnd !== '' ? $contractEnd : null;
        $stmt = $conn->prepare("INSERT INTO serviceagreement (ClientCompanyID, ContractNumber, ContractStartDate, ContractEndDate, SignedDocumentPath, Status, ContractType, CreatedBy, Notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssis", $clientCompanyId, $contractNumber, $contractStart, $contractEndVal, $signedPath, $status, $contractType, $userId, $notes);
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            $stmt->close();
            $wasPendingStmt = $conn->prepare("SELECT IsActive FROM clientcompany WHERE CompanyID = ? LIMIT 1");
            $wasPendingStmt->bind_param('i', $clientCompanyId);
            $wasPendingStmt->execute();
            $wasPendingRow = $wasPendingStmt->get_result()->fetch_assoc();
            $wasPendingStmt->close();
            $wasPending = (int)($wasPendingRow['IsActive'] ?? 0) !== 1;

            sync_client_company_active_status($conn, $clientCompanyId);

            if ($status === 'Active' && $wasPending) {
                $leadStmt = $conn->prepare("SELECT UserID, Name FROM clientcompany WHERE CompanyID = ? LIMIT 1");
                $leadStmt->bind_param("i", $clientCompanyId);
                $leadStmt->execute();
                $crow = $leadStmt->get_result()->fetch_assoc();
                $leadStmt->close();
                if ($crow) {
                    $tlUid = (int)($crow['UserID'] ?? 0);
                    $cname = trim((string)($crow['Name'] ?? ''));
                    if ($tlUid > 0) {
                        insert_system_notification(
                            $conn,
                            $tlUid,
                            'company_now_active',
                            'Client company is Active',
                            ($cname !== '' ? '"' . $cname . '" is now Active.' : 'Your client company is now Active.') .
                            ' You can request job orders and work with endorsed applicants.',
                            'client_company',
                            $clientCompanyId
                        );
                    }
                }
            }

            $companyName = '';
            $cnStmt = $conn->prepare('SELECT Name FROM clientcompany WHERE CompanyID = ? LIMIT 1');
            if ($cnStmt) {
                $cnStmt->bind_param('i', $clientCompanyId);
                $cnStmt->execute();
                $cnRow = $cnStmt->get_result()->fetch_assoc();
                $cnStmt->close();
                $companyName = trim((string)($cnRow['Name'] ?? ''));
            }

            $documentName = $contractNumber !== ''
                ? $contractNumber
                : basename($signedPath);

            $notifyResult = notify_service_agreement_uploaded(
                $conn,
                (int)$id,
                $documentName,
                $contractStart,
                $contractEndVal,
                $companyName !== '' ? $companyName : null
            );

            AuditLogger::log($conn, 'Service Agreement', 'Approve', "Service Agreement #{$contractNumber} uploaded and approved for {$companyName}.", (string)$id);

            json_response([
                'success' => true,
                'message' => 'Service Agreement created successfully.',
                'service_agreement_id' => $id,
                'notified_count' => $notifyResult['notified_count'],
                'websocket_broadcast' => $notifyResult['websocket_broadcast'],
            ]);
        }
        json_response(['success' => false, 'message' => 'Database error: ' . $conn->error], 500);
    }

    if ($action === 'update_status') {
        $id = (int)($_POST['agreement_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if ($id <= 0 || !in_array($status, ['Active', 'Expired'])) {
            json_response(['success' => false, 'message' => 'Invalid request.'], 400);
        }
        $companyStmt = $conn->prepare("SELECT ClientCompanyID, ContractNumber FROM serviceagreement WHERE ServiceAgreementID = ? LIMIT 1");
        $companyStmt->bind_param('i', $id);
        $companyStmt->execute();
        $companyRow = $companyStmt->get_result()->fetch_assoc();
        $companyStmt->close();
        $clientCompanyId = (int)($companyRow['ClientCompanyID'] ?? 0);
        $contractNumber = $companyRow['ContractNumber'] ?? "Agreement #{$id}";

        $stmt = $conn->prepare("UPDATE serviceagreement SET Status = ? WHERE ServiceAgreementID = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            $stmt->close();
            if ($clientCompanyId > 0) {
                sync_client_company_active_status($conn, $clientCompanyId);
            }

            AuditLogger::log($conn, 'Service Agreement', 'Update Status', "Updated status of Service Agreement #{$contractNumber} to '{$status}'.", (string)$id);

            json_response(['success' => true, 'message' => 'Status updated.']);
        }
        json_response(['success' => false, 'message' => 'Update failed.'], 500);
    }
}
json_response(['success' => false, 'message' => 'Invalid request.'], 400);