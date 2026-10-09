<?php
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
require_once __DIR__ . '/../Helpers/application_endorsement.php';
try { ae_authorize($conn,(int)$_SESSION['user_id'],['Admin','HR']); } catch (DomainException $e) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }
require_once __DIR__ . '/../Helpers/AuditLogger.php';
require_once __DIR__ . '/../Helpers/work_site_geofence.php';

header('Content-Type: application/json');

function json_resp($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if(($_GET['list']??'')==='sites') {
        $company=(int)($_GET['company_id']??0);
        json_resp(['success'=>true,'sites'=>site_query($conn,'SELECT SiteID,Name,Address,TeamLeadID FROM deployment_sites WHERE CompanyID=? AND IsActive=1','i',[$company])->fetch_all(MYSQLI_ASSOC)]);
    }

    if (isset($_GET['list']) && $_GET['list'] === 'employees') {
        $res = $conn->query("SELECT e.EmployeeID, e.EmployeeNumber, e.FirstName, e.LastName, e.Email, e.Position FROM employee e WHERE e.IsActive = 1 ORDER BY e.LastName, e.FirstName");
        $employees = [];
        if ($res) while ($row = $res->fetch_assoc()) $employees[] = $row;
        json_resp(['success' => true, 'employees' => $employees]);
    }
    if (isset($_GET['list']) && $_GET['list'] === 'companies') {
        $res = $conn->query("SELECT CompanyID, Name FROM clientcompany WHERE IsActive = 1 ORDER BY Name");
        $companies = [];
        if ($res) while ($row = $res->fetch_assoc()) $companies[] = $row;
        json_resp(['success' => true, 'companies' => $companies]);
    }
    if (isset($_GET['list']) && $_GET['list'] === 'team_leads') {
        $res = $conn->query("SELECT u.UsersID, u.FullName, u.Email FROM users u INNER JOIN roles r ON u.RoleID = r.RolesID WHERE r.RoleName = 'TeamLead' AND u.IsActive = 1 ORDER BY u.FullName");
        $teamLeads = [];
        if ($res) while ($row = $res->fetch_assoc()) $teamLeads[] = $row;
        json_resp(['success' => true, 'team_leads' => $teamLeads]);
    }

    $sql = "SELECT d.DeploymentID, d.EmployeeID, d.CompanyID, d.Position, d.StartDate, d.EndDate, d.Location, d.SiteSuperVisorID, d.TimeIn, d.TimeOut, d.BreakDuration, d.GracePeriod, d.WorkingDays, d.ShiftName, c.Name AS CompanyName, CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName, e.EmployeeNumber, u.FullName AS TeamLeadName FROM deployment d INNER JOIN employee e ON d.EmployeeID = e.EmployeeID INNER JOIN clientcompany c ON d.CompanyID = c.CompanyID LEFT JOIN users u ON d.SiteSuperVisorID = u.UsersID ORDER BY d.StartDate DESC";
    require_once __DIR__ . '/../Helpers/list_query_page.php';
    $sql = str_replace('d.DeploymentID,', "d.DeploymentID, CASE WHEN d.EndDate IS NULL OR d.EndDate >= CURDATE() THEN 'Active' ELSE 'Ended' END AS DeploymentState,", $sql);
    [$sql,$types,$values,$pagination]=list_query_page($conn,$sql,['DeploymentID','EmployeeName','EmployeeNumber','CompanyName','Location','Position','TeamLeadName','EmployeeID'],['company'=>'CompanyName','site'=>'Location','team_lead'=>'TeamLeadName','state'=>'DeploymentState']);
    $stmt=$conn->prepare($sql); if($types!=='')$stmt->bind_param($types,...$values); $stmt->execute(); $result=$stmt->get_result();
    $deployments = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $deployments[] = [
                'deployment_id' => (int)$row['DeploymentID'],
                'employee_id' => (int)$row['EmployeeID'],
                'employee_name' => $row['EmployeeName'],
                'employee_number' => $row['EmployeeNumber'],
                'company_id' => (int)$row['CompanyID'],
                'company_name' => $row['CompanyName'],
                'position' => $row['Position'],
                'start_date' => $row['StartDate'],
                'end_date' => $row['EndDate'],
                'location' => $row['Location'],
                'team_lead_id' => $row['SiteSuperVisorID'] ? (int)$row['SiteSuperVisorID'] : null,
                'team_lead_name' => $row['TeamLeadName'],
                'time_in' => $row['TimeIn'] ?? '08:00:00',
                'time_out' => $row['TimeOut'] ?? '17:00:00',
                'break_duration' => (int)($row['BreakDuration'] ?? 60),
                'grace_period' => (int)($row['GracePeriod'] ?? 5),
                'working_days' => $row['WorkingDays'] ?? 'Mon-Fri',
                'shift_name' => $row['ShiftName'] ?? 'Regular Shift'
            ];
        }
    }
    json_resp(['success' => true, 'deployments' => $deployments, 'pagination'=>$pagination]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $companyId = (int)($_POST['company_id'] ?? 0);
        $position = trim($_POST['position'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $teamLeadId = isset($_POST['team_lead_id']) && $_POST['team_lead_id'] !== '' ? (int)$_POST['team_lead_id'] : null;
        $siteId=(int)($_POST['site_id']??0);
        $site=site_query($conn,'SELECT * FROM deployment_sites WHERE SiteID=? AND CompanyID=? AND IsActive=1','ii',[$siteId,$companyId])->fetch_assoc();
        if(!$site)json_resp(['success'=>false,'message'=>'Select an active deployment site for this company.'],422);
        $teamLeadId=$site['TeamLeadID']?(int)$site['TeamLeadID']:null;$location=substr((string)$site['Address'],0,150);
        $timeIn = trim($_POST['time_in'] ?? '08:00:00');
        $timeOut = trim($_POST['time_out'] ?? '17:00:00');
        $breakDuration = (int)($_POST['break_duration'] ?? 60);
        $gracePeriod = (int)($_POST['grace_period'] ?? 5);
        $workingDays = trim($_POST['working_days'] ?? 'Mon-Fri');
        $shiftName = trim($_POST['shift_name'] ?? 'Regular Shift');

        if ($employeeId <= 0 || $companyId <= 0 || $position === '' || $startDate === '') {
            json_resp(['success' => false, 'message' => 'Employee, Company, Position, and Start Date are required.'], 400);
        }

        $check = $conn->prepare("SELECT DeploymentID FROM deployment WHERE EmployeeID = ? AND (EndDate IS NULL OR EndDate >= CURDATE()) LIMIT 1");
        $check->bind_param("i", $employeeId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $check->close();
            json_resp(['success' => false, 'message' => 'Employee is already deployed. End the current deployment before assigning to a new client.'], 400);
        }
        $check->close();

        $statusId = null;
        $statusRes = $conn->query("SELECT StatusID FROM status WHERE Name = 'Active' LIMIT 1");
        if ($statusRes && $row = $statusRes->fetch_assoc()) $statusId = (int)$row['StatusID'];

        $endDateParam = $endDate !== '' ? $endDate : null;

        // Ensure schedule columns exist in deployment
        $conn->query("ALTER TABLE deployment ADD COLUMN IF NOT EXISTS TimeIn TIME DEFAULT '08:00:00'");
        $conn->query("ALTER TABLE deployment ADD COLUMN IF NOT EXISTS TimeOut TIME DEFAULT '17:00:00'");
        $conn->query("ALTER TABLE deployment ADD COLUMN IF NOT EXISTS BreakDuration INT UNSIGNED DEFAULT 60");
        $conn->query("ALTER TABLE deployment ADD COLUMN IF NOT EXISTS GracePeriod INT UNSIGNED DEFAULT 5");
        $conn->query("ALTER TABLE deployment ADD COLUMN IF NOT EXISTS WorkingDays VARCHAR(100) DEFAULT 'Mon-Fri'");
        $conn->query("ALTER TABLE deployment ADD COLUMN IF NOT EXISTS ShiftName VARCHAR(100) DEFAULT 'Regular Shift'");

        $insert = $conn->prepare("INSERT INTO deployment (SiteID, EmployeeID, CompanyID, Position, Location, StartDate, EndDate, StatusID, SiteSuperVisorID, TimeIn, TimeOut, BreakDuration, GracePeriod, WorkingDays, ShiftName) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param("iiissssiissiiss", $siteId, $employeeId, $companyId, $position, $location, $startDate, $endDateParam, $statusId, $teamLeadId, $timeIn, $timeOut, $breakDuration, $gracePeriod, $workingDays, $shiftName);

        if ($insert->execute()) {
            $depId = $conn->insert_id;
            $insert->close();

            $infoQuery = $conn->query("
                SELECT CONCAT(e.FirstName, ' ', e.LastName) AS emp_name, c.Name AS comp_name 
                FROM employee e, clientcompany c 
                WHERE e.EmployeeID = " . (int)$employeeId . " AND c.CompanyID = " . (int)$companyId
            );
            $info = ($infoQuery) ? $infoQuery->fetch_assoc() : [];
            $empName = $info['emp_name'] ?? 'Employee';
            $compName = $info['comp_name'] ?? 'Client Company';

            AuditLogger::log($conn, 'Deployment', 'Deploy', "Employee {$empName} deployed to {$compName} with schedule {$shiftName}.", (string)$depId);

            json_resp(['success' => true, 'message' => 'Deployment created successfully.', 'deployment_id' => $depId]);
        }
        json_resp(['success' => false, 'message' => 'Database error: ' . $conn->error], 500);
    }
    if ($action === 'end') {
        $deploymentId = (int)($_POST['deployment_id'] ?? 0);
        $endDate = trim($_POST['end_date'] ?? date('Y-m-d'));
        if ($deploymentId <= 0) json_resp(['success' => false, 'message' => 'Invalid deployment ID.'], 400);
        $update = $conn->prepare("UPDATE deployment SET EndDate = ? WHERE DeploymentID = ?");
        $update->bind_param("si", $endDate, $deploymentId);
        if ($update->execute()) {
            $update->close();
            AuditLogger::log($conn, 'Deployment', 'End Deployment', "Deployment #{$deploymentId} was ended.", (string)$deploymentId);
            json_resp(['success' => true, 'message' => 'Deployment ended.']);
        }
        json_resp(['success' => false, 'message' => 'Database error: ' . $conn->error], 500);
    }
}

json_resp(['error' => 'Invalid request'], 400);