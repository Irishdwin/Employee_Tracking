<?php
/**
 * DEVELOPMENT / TESTING ONLY. GET ?company_id=X&team_lead_id=Y
 * Lists employees currently deployed under this company + Team Lead pair.
 */
declare(strict_types=1);

require_once __DIR__ . '/_testing_guard.php';

$companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$teamLeadId = isset($_GET['team_lead_id']) ? (int)$_GET['team_lead_id'] : 0;

if ($companyId <= 0 || $teamLeadId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'company_id and team_lead_id are required.']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT e.EmployeeID, e.EmployeeNumber, CONCAT(e.FirstName, ' ', e.LastName) AS employee_name, d.DeploymentID
        FROM deployment d
        INNER JOIN employee e ON e.EmployeeID = d.EmployeeID AND e.IsActive = 1
        WHERE d.CompanyID = ? AND d.SiteSuperVisorID = ?
          AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        ORDER BY employee_name ASC
    ");
    $stmt->bind_param('ii', $companyId, $teamLeadId);
    $stmt->execute();
    $res = $stmt->get_result();

    $employees = [];
    while ($row = $res->fetch_assoc()) {
        $employees[] = [
            'employee_id' => (int)$row['EmployeeID'],
            'employee_number' => $row['EmployeeNumber'],
            'name' => $row['employee_name'],
            'deployment_id' => (int)$row['DeploymentID'],
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'employees' => $employees]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load employees.']);
}
