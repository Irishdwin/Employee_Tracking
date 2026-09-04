<?php
/**
 * GET — Retrieve active duty schedule for the logged-in employee.
 */
require_once __DIR__ . '/../Common/session_init.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php';

$userId = (int)$_SESSION['user_id'];

try {
    // Get employee ID
    $empStmt = $conn->prepare("SELECT EmployeeID FROM employee WHERE Email = (SELECT Email FROM users WHERE UsersID = ?) LIMIT 1");
    $empStmt->bind_param("i", $userId);
    $empStmt->execute();
    $empRes = $empStmt->get_result()->fetch_assoc();
    $empStmt->close();

    if (!$empRes) {
        echo json_encode(['success' => false, 'message' => 'Employee profile not found.']);
        exit();
    }
    $employeeId = (int)$empRes['EmployeeID'];

    // Fetch deployment schedule info
    $depStmt = $conn->prepare("
        SELECT d.TimeIn, d.TimeOut, d.BreakDuration, d.GracePeriod, d.WorkingDays, d.ShiftName, c.Name as CompanyName, d.Location
        FROM deployment d
        LEFT JOIN clientcompany c ON d.CompanyID = c.CompanyID
        WHERE d.EmployeeID = ? AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        ORDER BY d.StartDate DESC LIMIT 1
    ");
    $depStmt->bind_param("i", $employeeId);
    $depStmt->execute();
    $deployment = $depStmt->get_result()->fetch_assoc();
    $depStmt->close();

    echo json_encode([
        'success' => true,
        'deployment' => $deployment ?: [
            'TimeIn' => '08:00:00',
            'TimeOut' => '17:00:00',
            'BreakDuration' => 60,
            'GracePeriod' => 15,
            'WorkingDays' => 'Mon-Fri',
            'ShiftName' => 'Regular Shift',
            'CompanyName' => '—',
            'Location' => '—'
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit();