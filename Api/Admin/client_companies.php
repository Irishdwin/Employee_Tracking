<?php
ob_start();
require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Helpers/password_policy.php';

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
require_once __DIR__ . '/../Helpers/work_site_geofence.php';

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

/** Saved with the assignment transaction so successful assignments always have an alert. */
function client_company_notify_assigned_lead(mysqli $conn, int $leadId, string $companyName, int $companyId, ?string $siteName = null, ?int $siteId = null): void {
    $siteAssignment = $siteId !== null;
    $message = 'Admin assigned you as Team Lead for ' . $companyName;
    if ($siteName !== null && $siteName !== '') $message .= ' — ' . $siteName;
    $message .= '. Open Deployment Sites to view your assignment.';
    if (!insert_system_notification($conn, $leadId,
        $siteAssignment ? 'team_lead_site_assigned' : 'team_lead_company_assigned',
        $siteAssignment ? 'New deployment site assignment' : 'New client company assignment',
        $message, $siteAssignment ? 'deployment_site' : 'client_company',
        $siteAssignment ? $siteId : $companyId)) {
        throw new RuntimeException('Unable to save the Team Lead assignment notification.');
    }
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

// validate_strong_password() now lives in Api/Helpers/password_policy.php
// (required at the top of this file) so all account-creation endpoints
// share the same policy, including common/predictable-password rejection.

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (($_GET['list'] ?? '') === 'sites') {
        $company=(int)($_GET['company_id']??0);
        $sites=site_query($conn,"SELECT s.*,u.FullName TeamLeadName,
          (SELECT COUNT(DISTINCT d.EmployeeID) FROM deployment d JOIN employee e ON e.EmployeeID=d.EmployeeID AND e.IsActive=1
           WHERE d.SiteID=s.SiteID AND ".site_current_deployment_sql().") EmployeeCount
          FROM deployment_sites s LEFT JOIN users u ON u.UsersID=s.TeamLeadID WHERE s.CompanyID=? ORDER BY s.Name",'i',[$company])->fetch_all(MYSQLI_ASSOC);
        json_response(['success'=>true,'sites'=>$sites]);
    }

    if (isset($_GET['check_email'])) {
        $checkEmail = trim((string)($_GET['check_email'] ?? ''));
        $exists = check_global_email_exists($conn, $checkEmail);
        json_response(['success' => true, 'exists' => $exists]);
    }

    if (isset($_GET['list']) && $_GET['list'] === 'team_leads') {
        $editingCompanyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
        $q = trim((string)($_GET['q'] ?? ''));
        $searchLike = '%' . $q . '%';

        // Accounts have no phone column; reuse the latest saved company contact for this lead.
        $contactLookup = "(SELECT cc.ContactNumber FROM clientcompany cc WHERE cc.UserID=u.UsersID
            AND NULLIF(TRIM(cc.ContactNumber),'') IS NOT NULL ORDER BY cc.CompanyID DESC LIMIT 1) AS ContactNumber";
        $stmt=$conn->prepare("SELECT u.UsersID,u.FullName,u.Email, {$contactLookup} FROM users u JOIN roles r ON r.RolesID=u.RoleID
            WHERE u.IsActive=1 AND r.RoleName IN ('TeamLead','Team Lead') AND (?='' OR u.FullName LIKE ? OR u.Email LIKE ?) ORDER BY u.FullName");
        $stmt->bind_param('sss',$q,$searchLike,$searchLike);$stmt->execute();$result=$stmt->get_result();
        $teamLeads = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $teamLeads[] = $row;
            }
        }
        if ($editingCompanyId > 0) {
            $assignStmt = $conn->prepare(
                "SELECT u.UsersID, u.FullName, u.Email, {$contactLookup}
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

    // ST-500: search + status filter + pagination for the Admin Client
    // Companies list. `status` filters on IsActive, which
    // sync_all_client_companies_active_status() above keeps in exact sync
    // with HasActiveAgreement below — so filtering on IsActive matches
    // exactly what the "Status" badge in the UI already shows
    // (Active = has a signed active agreement, Pending = does not).
    $search = trim($_GET['search'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    // Capped at 1000 (not the usual 100) so populateClientSelect() in
    // admin_dashboard.js — the "Upload Agreement" company dropdown, which
    // needs every company, not one page of them — can request the full
    // list with ?limit=1000 without this endpoint needing a second code path.
    $limit = max(1, min(1000, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $agreementExistsSql = "
        EXISTS (
            SELECT 1
            FROM serviceagreement sa
            WHERE sa.ClientCompanyID = c.CompanyID
              AND sa.Status = 'Active'
              AND COALESCE(NULLIF(TRIM(sa.SignedDocumentPath), ''), '') <> ''
        ) AS HasActiveAgreement";

    $whereClauses = [];
    $types = '';
    $bindValues = [];

    if ($search !== '') {
        $whereClauses[] = '(c.Name LIKE ? OR c.ContactPerson LIKE ? OR c.Industry LIKE ?)';
        $searchTerm = "%{$search}%";
        array_push($bindValues, $searchTerm, $searchTerm, $searchTerm);
        $types .= 'sss';
    }

    if ($statusFilter === 'Active') {
        $whereClauses[] = 'c.IsActive = 1';
    } elseif ($statusFilter === 'Pending') {
        $whereClauses[] = 'c.IsActive = 0';
    }

    $whereSql = $whereClauses ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM clientcompany c {$whereSql}");
    if (!empty($bindValues)) {
        $countStmt->bind_param($types, ...$bindValues);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    $sql = "SELECT c.CompanyID, c.Name, c.Address, c.ContactPerson, c.ContactNumber, c.Industry, c.IsActive, c.PayrollType, {$agreementExistsSql}
            FROM clientcompany c
            {$whereSql}
            ORDER BY c.Name
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $paginationTypes = $types . 'ii';
    $paginationValues = array_merge($bindValues, [$limit, $offset]);
    $stmt->bind_param($paginationTypes, ...$paginationValues);
    $stmt->execute();
    $result = $stmt->get_result();

    $companies = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $companies[] = $row;
        }
    }
    json_response([
        'success' => true,
        'companies' => $companies,
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
    if ($action === 'save_site') {
        try {
            $company=(int)($_POST['company_id']??0);$siteId=(int)($_POST['site_id']??0);
            $previousSiteLead = null;
            $siteName=trim((string)($_POST['site_name']??''));$siteAddress=trim((string)($_POST['site_address']??''));
            if(!$company || $siteName==='' || strlen($siteName)>150)throw new InvalidArgumentException('Company and site name (up to 150 characters) are required.');
            if(!site_query($conn,'SELECT CompanyID FROM clientcompany WHERE CompanyID=?','i',[$company])->num_rows)throw new InvalidArgumentException('Company not found.');
            $conn->begin_transaction();
            $lead=(int)($_POST['team_lead_id']??0);
            if(($_POST['lead_mode']??'existing')==='new') {
                [$lead,$leadName]=create_team_lead_user_account($conn,trim($_POST['tl_full_name']??''),trim($_POST['tl_email']??''),$_POST['tl_password']??'',$_POST['tl_password_confirm']??'');
            }
            if($lead && !site_query($conn,"SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND r.RoleName IN ('TeamLead','Team Lead')",'i',[$lead])->num_rows)throw new InvalidArgumentException('Choose an active Team Lead.');
            $lead=$lead?:null;$active=isset($_POST['is_active'])?(int)$_POST['is_active']:1;
            if(!in_array($active,[0,1],true))throw new InvalidArgumentException('Invalid site status.');
            if(!$active && site_query($conn,'SELECT d.DeploymentID FROM deployment d WHERE d.SiteID=? AND '.site_current_deployment_sql(),'i',[$siteId])->num_rows)throw new InvalidArgumentException('End active deployments before disabling this site.');
            if($siteId) {
                $existingSite=site_query($conn,'SELECT SiteID,TeamLeadID FROM deployment_sites WHERE SiteID=? AND CompanyID=? FOR UPDATE','ii',[$siteId,$company])->fetch_assoc();
                if(!$existingSite)throw new InvalidArgumentException('Site does not belong to this company.');
                $previousSiteLead=(int)($existingSite['TeamLeadID']??0);
                $st=$conn->prepare('UPDATE deployment_sites SET Name=?,Address=?,TeamLeadID=?,IsActive=? WHERE SiteID=? AND CompanyID=?');
                $st->bind_param('ssiiii',$siteName,$siteAddress,$lead,$active,$siteId,$company);
            }else {
                $st=$conn->prepare('INSERT INTO deployment_sites (CompanyID,Name,Address,TeamLeadID,IsActive) VALUES (?,?,?,?,?)');
                $st->bind_param('issii',$company,$siteName,$siteAddress,$lead,$active);
            }
            $st->execute();if(!$siteId)$siteId=(int)$conn->insert_id;$st->close();
            // Keep legacy consumers aligned on current rows only; historical supervisor snapshots remain.
            $st=$conn->prepare('UPDATE deployment d SET SiteSuperVisorID=? WHERE SiteID=? AND '.site_current_deployment_sql());
            $st->bind_param('ii',$lead,$siteId);$st->execute();$st->close();
            if ($lead && (int)$lead !== (int)$previousSiteLead) {
                $companyRow=site_query($conn,'SELECT Name FROM clientcompany WHERE CompanyID=?','i',[$company])->fetch_assoc();
                client_company_notify_assigned_lead($conn, (int)$lead, $companyRow['Name'], $company, $siteName, $siteId);
            }
            AuditLogger::log($conn,'Deployment Site','Assign','Updated site '.$siteName.'; Team Lead '.($lead?:'removed'),(string)$siteId);
            $conn->commit();json_response(['success'=>true,'site_id'=>$siteId,'message'=>'Deployment site saved.']);
        }catch(Throwable $e){$conn->rollback();json_response(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Unable to save deployment site.'],422);}
    }

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

        if (empty($_POST['team_lead_id']) && check_global_email_exists($conn, $tlEmail)) {
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
            if (!empty($_POST['team_lead_id'])) {
                $newUserId=(int)$_POST['team_lead_id'];
                $lead=site_query($conn,"SELECT u.FullName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND r.RoleName IN ('TeamLead','Team Lead')",'i',[$newUserId])->fetch_assoc();
                if(!$lead)throw new InvalidArgumentException('Select an active Team Lead.');
                $contactPerson=$lead['FullName'];
            } else [$newUserId, $contactPerson] = create_team_lead_user_account(
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
            $siteName=trim($_POST['site_name']??'')?:$name;
            $siteAddress=trim($_POST['site_address']??'')?:$address;
            $siteStmt=$conn->prepare('INSERT INTO deployment_sites (CompanyID,Name,Address,TeamLeadID,LegacyDefault) VALUES (?,?,?,?,1)');
            $siteStmt->bind_param('issi',$companyId,$siteName,$siteAddress,$newUserId);$siteStmt->execute();$siteStmt->close();

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
            client_company_notify_assigned_lead($conn, $newUserId, $name, $companyId, $siteName);

            $conn->commit();

            AuditLogger::log($conn, 'Company', 'Create', "Client Company '{$name}' was registered with Service Agreement #{$contractNumber}.", (string)$companyId);



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

        $selectedTeamLeadId=(int)($_POST['team_lead_id']??0);
        $conn->begin_transaction();
        try {
        $wantsNewTeamLead = $tlFullName !== '' || $tlEmail !== '' || $tlPassword !== '' || $tlPasswordConfirm !== '';
        if ($selectedTeamLeadId > 0) {
            if ($wantsNewTeamLead) throw new InvalidArgumentException('Choose an existing Team Lead or create a new account, not both.');
            $lead=site_query($conn,"SELECT u.FullName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND r.RoleName IN ('TeamLead','Team Lead')",'i',[$selectedTeamLeadId])->fetch_assoc();
            if (!$lead) throw new InvalidArgumentException('Select an active Team Lead account.');
            $contactUserId=$selectedTeamLeadId;
            $contactPerson=$lead['FullName'];
        } elseif ($wantsNewTeamLead) {
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

        $stmt = $conn->prepare("UPDATE clientcompany
            SET Name = ?, Address = ?, ContactPerson = ?, ContactNumber = ?, Industry = ?, UserID = ?, PayrollType = ?
            WHERE CompanyID = ?");
        $stmt->bind_param("sssssssi", $name, $address, $contactPerson, $contactNumber, $industry, $contactUserId, $payrollType, $companyId);
        if ($stmt->execute()) {
            $stmt->close();

            if ($contactUserId !== $previousTeamLeadUserId) {
                $affectedSites=site_query($conn,'SELECT SiteID FROM deployment_sites WHERE CompanyID=? AND (TeamLeadID=? OR (LegacyDefault=1 AND TeamLeadID IS NULL)) FOR UPDATE','ii',[$companyId,$previousTeamLeadUserId])->fetch_all(MYSQLI_ASSOC);
                foreach ($affectedSites as $site) {
                    $siteId=(int)$site['SiteID'];
                    $siteUpdate=$conn->prepare('UPDATE deployment_sites SET TeamLeadID=? WHERE SiteID=?');
                    $siteUpdate->bind_param('ii',$contactUserId,$siteId);$siteUpdate->execute();$siteUpdate->close();
                    $deploymentUpdate=$conn->prepare('UPDATE deployment d SET SiteSuperVisorID=? WHERE d.SiteID=? AND '.site_current_deployment_sql());
                    $deploymentUpdate->bind_param('ii',$contactUserId,$siteId);$deploymentUpdate->execute();$deploymentUpdate->close();
                }
            }

            AuditLogger::log($conn, 'Company', 'Update', "Client Company '{$name}' was updated.", (string)$companyId);

            if ($contactUserId !== $previousTeamLeadUserId) {
                client_company_notify_assigned_lead($conn, $contactUserId, $name, $companyId);
            }
            $conn->commit();
            json_response(['success' => true, 'message' => 'Client company updated successfully.']);
        }
        $conn->rollback();
        json_response(['success' => false, 'message' => 'Database error: ' . $conn->error], 500);
        } catch (Throwable $e) {
            $conn->rollback();
            json_response(['success'=>false,'message'=>$e instanceof InvalidArgumentException ? $e->getMessage() : 'Unable to update client company.'], $e instanceof InvalidArgumentException ? 422 : 500);
        }
    }
}
json_response(['success' => false, 'message' => 'Invalid action.'], 400);