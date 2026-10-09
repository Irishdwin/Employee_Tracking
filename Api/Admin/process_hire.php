<?php
/**
 * Admin: hire applicant (convert to employee) — POST JSON.
 * Uses mysqli transaction via Api/Helpers/hire_applicant.php
 */
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strcasecmp((string)$_SESSION['role'], 'Admin') !== 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/hire_applicant.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';
require_once __DIR__ . '/../Common/NotificationHelper.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;
$sssNumber = isset($input['sss_number']) ? trim((string)$input['sss_number']) : '';
$philhealthNumber = isset($input['philhealth_number']) ? trim((string)$input['philhealth_number']) : '';
$pagibigNumber = isset($input['pagibig_number']) ? trim((string)$input['pagibig_number']) : '';
$dailyRate = isset($input['daily_rate']) ? $input['daily_rate'] : 0;
$allowance = isset($input['allowance']) ? $input['allowance'] : 0;

$result = hireApplicantFromApplication(
    $conn,
    $applicationId,
    (int)$_SESSION['user_id'],
    $sssNumber,
    $philhealthNumber,
    $pagibigNumber,
    is_numeric($dailyRate) ? (float)$dailyRate : 0,
    is_numeric($allowance) ? (float)$allowance : 0,
    $input['sunday_duty_allowance'] ?? '0'
);

if (is_array($result) && !empty($result['success']) && !empty($result['employee_id'])) {
    $empId = (int)$result['employee_id'];

    // Audit Log Entry
    $empNameQuery = $conn->query("SELECT CONCAT(FirstName, ' ', LastName) AS emp_name FROM employee WHERE EmployeeID = " . $empId);
    $empName = ($empNameQuery && $r = $empNameQuery->fetch_assoc()) ? $r['emp_name'] : "Employee #{$empId}";
    AuditLogger::log($conn, 'Employee', 'Hire', "Employee {$empName} was hired and converted successfully.", (string)$empId);

    $tlStmt = $conn->prepare("
        SELECT d.SiteSuperVisorID AS UserID, CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName, c.Name AS CompanyName
        FROM deployment d
        INNER JOIN employee e ON d.EmployeeID = e.EmployeeID
        INNER JOIN clientcompany c ON d.CompanyID = c.CompanyID
        WHERE d.EmployeeID = ? AND d.SiteSuperVisorID IS NOT NULL
        ORDER BY d.DeploymentID DESC LIMIT 1
    ");
    if ($tlStmt) {
        $tlStmt->bind_param("i", $empId);
        $tlStmt->execute();
        $tlRow = $tlStmt->get_result()->fetch_assoc();
        $tlStmt->close();
        if ($tlRow && !empty($tlRow['UserID'])) {
            $tlUserId = (int)$tlRow['UserID'];
            $empName = $tlRow['EmployeeName'] ?? 'Employee';
            $compName = $tlRow['CompanyName'] ?? 'Company';
            $msgText = "New employee deployed under your team:\n" . $empName . "\nClient: " . $compName;
            insert_system_notification($conn, $tlUserId, 'deployed_team', 'New Employee Deployed', $msgText, 'employee', $empId);
        }
    }
}

echo json_encode($result);
