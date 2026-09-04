<?php
ob_start();
require_once __DIR__ . '/../Common/session_init.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Common/NotificationHelper.php';
require_once __DIR__ . '/../Helpers/send_mail.php';
require_once __DIR__ . '/../Helpers/client_company_status.php';
require_once __DIR__ . '/../Helpers/service_agreement_notify_helper.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';
require_once __DIR__ . '/../Helpers/payroll_type_helper.php';
require_once __DIR__ . '/../Helpers/contract_expiry_helper.php';

payroll_type_ensure_schema($conn);
service_agreement_contract_type_ensure_schema($conn);

header('Content-Type: application/json');

function json_response($data, int $status = 200): void {
    http_response_code($status);
    ob_clean();
    echo json_encode($data);
    exit();
}

$baseUploadDir = dirname(__DIR__, 2) . '/uploads/service_agreements/';
if (!is_dir($baseUploadDir)) {
    mkdir($baseUploadDir, 0755, true);
}

function check_global_email_exists(mysqli $conn, string $email, int $excludeUserId = 0): bool {
    $email = strtolower(trim($email));
    if ($excludeUserId > 0) {
        $stmt = $conn->prepare('SELECT UsersID FROM users WHERE LOWER(Email) = ? AND UsersID <> ? LIMIT 1');
        $stmt->bind_param('si', $email, $excludeUserId);
    } else {
        $stmt = $conn->prepare('SELECT UsersID FROM users WHERE LOWER(Email) = ? LIMIT 1');
        $stmt->bind_param('s', $email);
    }
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = ($res && $res->num_rows > 0);
        $stmt->close();
        return $exists;
    }
    return false;
}

function normalize_ph_contact_number(string $number): ?string {
    $number = trim($number);
    if (!preg_match('/^\+639\d{9}$/', $number)) {
        return null;
    }
    return $number;
}

function generate_next_contract_number(mysqli $conn): string {
    $year = date('Y');
    $prefix = 'SA-' . $year . '-';
    $stmt = $conn->prepare("SELECT ContractNumber FROM serviceagreement WHERE ContractNumber LIKE ? ORDER BY ServiceAgreementID DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $nextNum = 1;
    if ($res && $row = $res->fetch_assoc()) {
        $lastNumStr = str_replace($prefix, '', $row['ContractNumber']);
        if (is_numeric($lastNumStr)) {
            $nextNum = (int)$lastNumStr + 1;
        }
    }
    $stmt->close();
    return $prefix . str_pad((string)$nextNum, 6, '0', STR_PAD_LEFT);
}

function create_team_lead_user_account(mysqli $conn, string $tlFullName, string $tlEmail, string $tlPassword, string $tlPasswordConfirm): array {
    $tlFullName = trim($tlFullName);
    $tlEmail = strtolower(trim($tlEmail));
    if ($tlFullName === '' || $tlEmail === '' || $tlPassword === '') {
        json_response(['success' => false, 'message' => 'Team Lead full name, email, and password are required.'], 400);
    }
    if (!preg_match('/^[A-Z0-9._%+\-]+@gmail\.com$/i', $tlEmail)) {
        json_response(['success' => false, 'message' => 'Team Lead email must be a valid Gmail address.'], 400);
    }

    if (check_global_email_exists($conn, $tlEmail)) {
        json_response(['success' => false, 'message' => 'This Gmail address is already registered. Please use another Gmail account.'], 409);
    }

    if ($tlPassword !== $tlPasswordConfirm) {
        json_response(['success' => false, 'message' => 'Team Lead passwords do not match.'], 400);
    }
    $passwordPolicyError = validate_strong_password($tlPassword);
    if ($passwordPolicyError !== null) {
        json_response(['success' => false, 'message' => $passwordPolicyError], 400);
    }

    $roleStmt = $conn->prepare("SELECT RolesID FROM roles WHERE RoleName IN ('TeamLead', 'Team Lead') ORDER BY RolesID LIMIT 1");
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    if (!$roleRow) {
        json_response(['success' => false, 'message' => 'Team Lead role is not configured in the database.'], 500);
    }
    $roleId = (int)$roleRow['RolesID'];
    $hashedPassword = password_hash($tlPassword, PASSWORD_DEFAULT);

    $userIns = $conn->prepare('INSERT INTO users (RoleID, FullName, Email, Password, IsActive) VALUES (?, ?, ?, ?, 1)');
    $userIns->bind_param('isss', $roleId, $tlFullName, $tlEmail, $hashedPassword);
    if (!$userIns->execute()) {
        $err = $conn->error;
        $userIns->close();
        json_response(['success' => false, 'message' => 'Could not create Team Lead account: ' . $err], 500);
    }
    $newUserId = (int)$conn->insert_id;
    $userIns->close();

    return [$newUserId, $tlFullName];
}

function validate_strong_password(string $password): ?string {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Password must include at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must include at least one special character.';
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['check_email'])) {
        $checkEmail = trim((string)($_GET['check_email'] ?? ''));
        $exists = check_global_email_exists($conn, $checkEmail);
        json_response(['success' => true, 'exists' => $exists]);
    }

    if (isset($_GET['list']) && $_GET['list'] === 'team_leads') {
        $editingCompanyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
        $q = trim((string)($_GET['q'] ?? ''));
        $searchLike = '%' . $q . '%';

        if ($editingCompanyId > 0) {
            $sql = "SELECT u.UsersID, u.FullName, u.Email
                    FROM users u
                    INNER JOIN roles r ON u.RoleID = r.RolesID
                    WHERE r.RoleName IN ('TeamLead', 'Team Lead')
                      AND u.IsActive = 1
                      AND (? = '' OR u.FullName LIKE ? OR u.Email LIKE ?)
                      AND NOT EXISTS (
                        SELECT 1 FROM clientcompany cc
                        WHERE cc.IsActive = 1
                          AND cc.UserID IS NOT NULL
                          AND cc.UserID = u.UsersID
                          AND cc.CompanyID <> ?
                      )
                    ORDER BY u.FullName";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $q, $searchLike, $searchLike, $editingCompanyId);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $sql = "SELECT u.UsersID, u.FullName, u.Email
                    FROM users u
                    INNER JOIN roles r ON u.RoleID = r.RolesID
                    WHERE r.RoleName IN ('TeamLead', 'Team Lead')
                      AND u.IsActive = 1
                      AND (? = '' OR u.FullName LIKE ? OR u.Email LIKE ?)
                      AND NOT EXISTS (
                        SELECT 1 FROM clientcompany cc
                        WHERE cc.IsActive = 1
                          AND cc.UserID IS NOT NULL
                          AND cc.UserID = u.UsersID
                      )
                    ORDER BY u.FullName";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $q, $searchLike, $searchLike);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        $teamLeads = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $teamLeads[] = $row;
            }
        }
        if ($editingCompanyId > 0) {
            $assignStmt = $conn->prepare(
                "SELECT u.UsersID, u.FullName, u.Email
                 FROM clientcompany cc
                 INNER JOIN users u ON u.UsersID = cc.UserID
                 WHERE cc.CompanyID = ? AND cc.UserID IS NOT NULL
                 LIMIT 1"
            );
            if ($assignStmt) {
                $assignStmt->bind_param('i', $editingCompanyId);
                $assignStmt->execute();
                $assigned = $assignStmt->get_result()->fetch_assoc();
                $assignStmt->close();
                if ($assigned) {
                    $aid = (int)($assigned['UsersID'] ?? 0);
                    $found = false;
                    foreach ($teamLeads as $tl) {
                        if ((int)($tl['UsersID'] ?? 0) === $aid) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        array_unshift($teamLeads, $assigned);
                    }
                }
            }
        }
        json_response(['success' => true, 'team_leads' => $teamLeads]);
    }

    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;

    if ($companyId > 0) {
        $stmt = $conn->prepare(
            "SELECT c.CompanyID, c.CompanyTypeID, c.Name, c.Address, c.ContactPerson, c.ContactNumber,
                    c.Industry, c.IsActive, c.UserID, c.PayrollType,
                    u.FullName AS TeamLeadFullName, u.Email AS TeamLeadEmail
             FROM clientcompany c
             LEFT JOIN users u ON c.UserID = u.UsersID
             WHERE c.CompanyID = ?"
        );
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
        $stmt->close();
        if (!$company) {
            json_response(['success' => false, 'message' => 'Client company not found.'], 404);
        }
        sync_client_company_active_status($conn, $companyId);
        $refreshStmt = $conn->prepare(
            "SELECT c.CompanyID, c.CompanyTypeID, c.Name, c.Address, c.ContactPerson, c.ContactNumber,
                    c.Industry, c.IsActive, c.UserID, c.PayrollType,
                    u.FullName AS TeamLeadFullName, u.Email AS TeamLeadEmail
             FROM clientcompany c
             LEFT JOIN users u ON c.UserID = u.UsersID
             WHERE c.CompanyID = ?"
        );
        $refreshStmt->bind_param('i', $companyId);
        $refreshStmt->execute();
        $company = $refreshStmt->get_result()->fetch_assoc();
        $refreshStmt->close();
        $teamLead = null;
        $uid = (int)($company['UserID'] ?? 0);
        if ($uid > 0) {
            $teamLead = [
                'UsersID' => $uid,
                'FullName' => $company['TeamLeadFullName'] ?? $company['ContactPerson'] ?? '',
                'Email' => $company['TeamLeadEmail'] ?? '',
            ];
        }
        unset($company['TeamLeadFullName'], $company['TeamLeadEmail']);
        json_response(['success' => true, 'company' => $company, 'team_lead' => $teamLead]);
    }

    sync_all_client_companies_active_status($conn);

    $search = trim($_GET['search'] ?? '');
    $agreementExistsSql = "
        EXISTS (
            SELECT 1
            FROM serviceagreement sa
            WHERE sa.ClientCompanyID = c.CompanyID
              AND sa.Status = 'Active'
              AND COALESCE(NULLIF(TRIM(sa.SignedDocumentPath), ''), '') <> ''
        ) AS HasActiveAgreement";
    $sql = "SELECT c.CompanyID, c.Name, c.Address, c.ContactPerson, c.ContactNumber, c.Industry, c.IsActive, c.PayrollType, {$agreementExistsSql}
            FROM clientcompany c
            ORDER BY c.Name";
    if ($search !== '') {
        $searchTerm = "%{$search}%";
        $sql = "SELECT c.CompanyID, c.Name, c.Address, c.ContactPerson, c.ContactNumber, c.Industry, c.IsActive, c.PayrollType, {$agreementExistsSql}
                FROM clientcompany c
                WHERE c.Name LIKE ? OR c.ContactPerson LIKE ? OR c.Industry LIKE ?
                ORDER BY c.Name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    $companies = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $companies[] = $row;
        }
    }
    json_response(['success' => true, 'companies' => $companies]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $rawContactNumber = trim($_POST['contact_number'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $payrollType = payroll_type_normalize($_POST['payroll_type'] ?? 'B');
        $tlFullName = trim($_POST['tl_full_name'] ?? '');
        $tlEmail = strtolower(trim($_POST['tl_email'] ?? ''));
        $tlPassword = (string)($_POST['tl_password'] ?? '');
        $tlPasswordConfirm = (string)($_POST['tl_password_confirm'] ?? '');

        $agreementStart = trim($_POST['agreement_start_date'] ?? '');
        $agreementEnd = trim($_POST['agreement_end_date'] ?? '');
        $agreementStatus = trim($_POST['agreement_status'] ?? 'Active');
        $agreementContractType = service_agreement_contract_type_normalize($_POST['agreement_contract_type'] ?? 'Open');
        $agreementNotes = trim($_POST['agreement_notes'] ?? '');

        if ($name === '') {
            json_response(['success' => false, 'message' => 'Company name is required.'], 400);
        }
        if ($rawContactNumber === '') {
            json_response(['success' => false, 'message' => 'Contact number is required.'], 400);
        }

        $contactNumber = normalize_ph_contact_number($rawContactNumber);
        if ($contactNumber === null) {
            json_response([
                'success' => false, 
                'message' => 'Please enter a valid Philippine mobile number starting with +63 followed by 10 digits starting with 9 (e.g., +639123456789).'
            ], 400);
        }

        if (check_global_email_exists($conn, $tlEmail)) {
            json_response(['success' => false, 'message' => 'This Gmail address is already registered. Please use another Gmail account.'], 409);
        }

        if ($agreementStart === '') {
            json_response(['success' => false, 'message' => 'Contract Start Date is required.'], 400);
        }

        $signedPath = '';
        if (!empty($_FILES['signed_document']['name']) && $_FILES['signed_document']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['signed_document']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowed)) {
                json_response(['success' => false, 'message' => 'Only PDF, JPG, and PNG files are allowed.'], 400);
            }
            $filename = 'sa_client_' . time() . '.' . $ext;
            $targetPath = $baseUploadDir . $filename;
            if (move_uploaded_file($_FILES['signed_document']['tmp_name'], $targetPath)) {
                $signedPath = 'uploads/service_agreements/' . $filename;
            }
        }
        if ($signedPath === '') {
            json_response(['success' => false, 'message' => 'Signed Service Agreement document upload is required.'], 400);
        }

        $conn->begin_transaction();

        try {
            [$newUserId, $contactPerson] = create_team_lead_user_account(
                $conn,
                $tlFullName,
                $tlEmail,
                $tlPassword,
                $tlPasswordConfirm
            );

            $stmt = $conn->prepare('INSERT INTO clientcompany (Name, Address, ContactPerson, ContactNumber, Industry, UserID, PayrollType, IsActive) VALUES (?, ?, ?, ?, ?, ?, ?, 0)');
            $stmt->bind_param('sssssis', $name, $address, $contactPerson, $contactNumber, $industry, $newUserId, $payrollType);
            if (!$stmt->execute()) {
                throw new Exception('Could not create client company: ' . $conn->error);
            }
            $companyId = (int)$conn->insert_id;
            $stmt->close();

            $contractNumber = generate_next_contract_number($conn);
            $adminUserId = (int)$_SESSION['user_id'];
            $contractEndVal = $agreementEnd !== '' ? $agreementEnd : null;

            $saStmt = $conn->prepare("INSERT INTO serviceagreement (ClientCompanyID, ContractNumber, ContractStartDate, ContractEndDate, SignedDocumentPath, Status, ContractType, CreatedBy, Notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $saStmt->bind_param("issssssis", $companyId, $contractNumber, $agreementStart, $contractEndVal, $signedPath, $agreementStatus, $agreementContractType, $adminUserId, $agreementNotes);
            if (!$saStmt->execute()) {
                throw new Exception('Could not create service agreement: ' . $conn->error);
            }
            $agreementId = (int)$saStmt->insert_id;
            $saStmt->close();

            sync_client_company_active_status($conn, $companyId);

            $conn->commit();

            AuditLogger::log($conn, 'Company', 'Create', "Client Company '{$name}' was registered with Service Agreement #{$contractNumber}.", (string)$companyId);

            insert_system_notification(
                $conn,
                $newUserId,
                'team_lead_company_assigned',
                'Welcome — Team Lead account created',
                'Your Team Lead login is ' . $tlEmail . '. You are assigned to ' . $name . '. Your service agreement contract number is ' . $contractNumber . '.',
                'client_company',
                $companyId
            );

            $notifyResult = notify_service_agreement_uploaded(
                $conn,
                $agreementId,
                $contractNumber,
                $agreementStart,
                $contractEndVal,
                $name
            );

            json_response([
                'success' => true,
                'message' => 'Client company registered and Service Agreement created successfully.',
                'company_id' => $companyId,
                'team_lead_user_id' => $newUserId,
                'service_agreement_id' => $agreementId,
                'contract_number' => $contractNumber,
                'notified_count' => $notifyResult['notified_count'],
                'websocket_broadcast' => $notifyResult['websocket_broadcast'],
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            if ($signedPath !== '' && file_exists(dirname(__DIR__, 2) . '/' . $signedPath)) {
                @unlink(dirname(__DIR__, 2) . '/' . $signedPath);
            }
            json_response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    } elseif ($action === 'update') {
        $companyId = (int)($_POST['company_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $rawContactNumber = trim($_POST['contact_number'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $payrollType = payroll_type_normalize($_POST['payroll_type'] ?? 'B');
        $tlFullName = trim($_POST['tl_full_name'] ?? '');
        $tlEmail = strtolower(trim($_POST['tl_email'] ?? ''));
        $tlPassword = (string)($_POST['tl_password'] ?? '');
        $tlPasswordConfirm = (string)($_POST['tl_password_confirm'] ?? '');

        if ($companyId <= 0) {
            json_response(['success' => false, 'message' => 'Client company id is required.'], 400);
        }
        if ($name === '') {
            json_response(['success' => false, 'message' => 'Company name is required.'], 400);
        }

        $contactNumber = null;
        if ($rawContactNumber !== '') {
            $contactNumber = normalize_ph_contact_number($rawContactNumber);
            if ($contactNumber === null) {
                json_response([
                    'success' => false, 
                    'message' => 'Please enter a valid Philippine mobile number starting with +63 followed by 10 digits starting with 9 (e.g., +639123456789).'
                ], 400);
            }
        }

        $existsStmt = $conn->prepare("SELECT CompanyID, UserID, ContactPerson FROM clientcompany WHERE CompanyID = ? LIMIT 1");
        $existsStmt->bind_param("i", $companyId);
        $existsStmt->execute();
        $companyExists = $existsStmt->get_result()->fetch_assoc();
        $existsStmt->close();
        if (!$companyExists) {
            json_response(['success' => false, 'message' => 'Client company not found.'], 404);
        }
        $previousTeamLeadUserId = (int)($companyExists['UserID'] ?? 0);

        $wantsNewTeamLead = $tlFullName !== '' || $tlEmail !== '' || $tlPassword !== '' || $tlPasswordConfirm !== '';
        if ($wantsNewTeamLead) {
            [$contactUserId, $contactPerson] = create_team_lead_user_account(
                $conn,
                $tlFullName,
                $tlEmail,
                $tlPassword,
                $tlPasswordConfirm
            );
        } else {
            $contactUserId = $previousTeamLeadUserId;
            if ($contactUserId <= 0) {
                json_response(['success' => false, 'message' => 'Create a Team Lead account below (name, Gmail, and password are required).'], 400);
            }
            $leadStmt = $conn->prepare("SELECT u.FullName
                FROM users u
                INNER JOIN roles r ON u.RoleID = r.RolesID
                WHERE u.UsersID = ? AND u.IsActive = 1 AND r.RoleName IN ('TeamLead', 'Team Lead')
                LIMIT 1");
            $leadStmt->bind_param("i", $contactUserId);
            $leadStmt->execute();
            $lead = $leadStmt->get_result()->fetch_assoc();
            $leadStmt->close();
            if (!$lead) {
                json_response(['success' => false, 'message' => 'This company has no valid Team Lead. Create a new Team Lead account below.'], 400);
            }
            $contactPerson = $lead['FullName'];
        }

        $busyStmt = $conn->prepare("SELECT CompanyID FROM clientcompany WHERE IsActive = 1 AND UserID = ? AND CompanyID <> ? LIMIT 1");
        $busyStmt->bind_param("ii", $contactUserId, $companyId);
        $busyStmt->execute();
        $busy = $busyStmt->get_result()->fetch_assoc();
        $busyStmt->close();
        if ($busy) {
            json_response(['success' => false, 'message' => 'This Team Lead is already assigned to an active company.'], 409);
        }

        $stmt = $conn->prepare("UPDATE clientcompany
            SET Name = ?, Address = ?, ContactPerson = ?, ContactNumber = ?, Industry = ?, UserID = ?, PayrollType = ?
            WHERE CompanyID = ?");
        $stmt->bind_param("sssssssi", $name, $address, $contactPerson, $contactNumber, $industry, $contactUserId, $payrollType, $companyId);
        if ($stmt->execute()) {
            $stmt->close();

            AuditLogger::log($conn, 'Company', 'Update', "Client Company '{$name}' was updated.", (string)$companyId);

            if ($contactUserId !== $previousTeamLeadUserId) {
                insert_system_notification(
                    $conn,
                    $contactUserId,
                    'team_lead_company_assigned',
                    'Assigned as Team Lead',
                    'You are now the Team Lead contact for ' . $name . '. When the client is Active, you can request job orders and work with endorsed applicants.',
                    'client_company',
                    $companyId
                );
            }
            json_response(['success' => true, 'message' => 'Client company updated successfully.']);
        }
        json_response(['success' => false, 'message' => 'Database error: ' . $conn->error], 500);
    }
}
json_response(['success' => false, 'message' => 'Invalid action.'], 400);