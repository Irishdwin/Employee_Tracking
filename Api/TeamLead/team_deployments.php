<?php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = trim((string)($_SESSION['role'] ?? ''));
$allowedRoles = ['TeamLead', 'Team Lead', 'Admin', 'HR'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

function json_resp($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$teamLeadName = $_SESSION['full_name'] ?? 'Team Lead';

$nameStmt = $conn->prepare("SELECT FullName FROM users WHERE UsersID = ? LIMIT 1");
if ($nameStmt) {
    $nameStmt->bind_param("i", $userId);
    $nameStmt->execute();
    $nameRes = $nameStmt->get_result();
    if ($nameRes && ($row = $nameRes->fetch_assoc())) {
        if (!empty($row['FullName'])) $teamLeadName = $row['FullName'];
    }
    $nameStmt->close();
}

$deployments = [];
$depSql = "SELECT d.DeploymentID, d.EmployeeID, d.Position, d.StartDate, d.EndDate, d.Location,
                  e.EmployeeNumber, CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                  c.CompanyID, c.Name AS CompanyName
           FROM deployment d
           INNER JOIN employee e ON d.EmployeeID = e.EmployeeID
           INNER JOIN clientcompany c ON d.CompanyID = c.CompanyID
           WHERE d.SiteSuperVisorID = ?
           ORDER BY d.StartDate DESC, d.DeploymentID DESC";
$depStmt = $conn->prepare($depSql);
if (!$depStmt) {
    json_resp(['success' => false, 'message' => 'Failed to prepare deployment query.'], 500);
}
$depStmt->bind_param("i", $userId);
$depStmt->execute();
$depRes = $depStmt->get_result();
while ($row = $depRes->fetch_assoc()) {
    $deployments[] = [
        'deployment_id' => (int)$row['DeploymentID'],
        'employee_id' => (int)$row['EmployeeID'],
        'employee_name' => $row['EmployeeName'] ?? '',
        'employee_number' => $row['EmployeeNumber'] ?? '',
        'company_id' => (int)$row['CompanyID'],
        'company_name' => $row['CompanyName'] ?? '',
        'position' => $row['Position'] ?? '',
        'location' => $row['Location'] ?? '',
        'start_date' => $row['StartDate'],
        'end_date' => $row['EndDate'],
    ];
}
$depStmt->close();

$sites = [];
$siteSql = "SELECT DISTINCT c.CompanyID, c.Name AS CompanyName, COALESCE(NULLIF(TRIM(d.Location), ''), '') AS Location
            FROM deployment d
            INNER JOIN clientcompany c ON d.CompanyID = c.CompanyID
            WHERE d.SiteSuperVisorID = ?
            ORDER BY c.Name ASC, Location ASC";
$siteStmt = $conn->prepare($siteSql);
if ($siteStmt) {
    $siteStmt->bind_param("i", $userId);
    $siteStmt->execute();
    $siteRes = $siteStmt->get_result();
    while ($row = $siteRes->fetch_assoc()) {
        $sites[] = [
            'company_id' => (int)$row['CompanyID'],
            'company_name' => $row['CompanyName'] ?? '',
            'location' => $row['Location'] ?? '',
        ];
    }
    $siteStmt->close();
}

// Team Leads assigned on client creation (clientcompany.UserID) may have no HR deployment rows yet;
// still allow job requests and related flows for their company.
$seenCompanyIds = [];
foreach ($sites as $s) {
    $seenCompanyIds[(int)$s['company_id']] = true;
}
$ccStmt = $conn->prepare('SELECT CompanyID, Name AS CompanyName, COALESCE(NULLIF(TRIM(Address), \'\'), \'\') AS Addr FROM clientcompany WHERE UserID = ?');
if ($ccStmt) {
    $ccStmt->bind_param('i', $userId);
    $ccStmt->execute();
    $ccRes = $ccStmt->get_result();
    while ($row = $ccRes->fetch_assoc()) {
        $cid = (int)($row['CompanyID'] ?? 0);
        if ($cid <= 0 || isset($seenCompanyIds[$cid])) {
            continue;
        }
        $seenCompanyIds[$cid] = true;
        $sites[] = [
            'company_id' => $cid,
            'company_name' => $row['CompanyName'] ?? '',
            'location' => $row['Addr'] ?? '',
        ];
    }
    $ccStmt->close();
}
usort($sites, function ($a, $b) {
    return strcasecmp($a['company_name'] ?? '', $b['company_name'] ?? '');
});

json_resp([
    'success' => true,
    'team_lead_name' => $teamLeadName,
    'deployments' => $deployments,
    'sites' => $sites,
]);
