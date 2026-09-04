<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!in_array($_SESSION['role'], ['Admin', 'HR'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_resigned') {
        $id = (int)($_POST['resignation_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }

        $chk = $conn->query("SELECT rr.EmployeeID, s.Name AS Status FROM resignationrequest rr LEFT JOIN status s ON rr.StatusID = s.StatusID WHERE rr.ResignationID = $id");
        if (!$chk || $chk->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Not found']);
            exit();
        }
        $row = $chk->fetch_assoc();
        if ($row['Status'] !== 'Quitclaim Ready') {
            echo json_encode(['success' => false, 'message' => 'Quitclaim must be uploaded first']);
            exit();
        }

        $empId = (int)$row['EmployeeID'];
        $statusId = $conn->query("SELECT StatusID FROM status WHERE Name = 'Resigned' LIMIT 1")->fetch_assoc();
        $statusId = $statusId ? (int)$statusId['StatusID'] : null;
        if (!$statusId) {
            $conn->query("INSERT INTO status (Name) VALUES ('Resigned')");
            $statusId = $conn->insert_id;
        }

        $conn->query("UPDATE resignationrequest SET StatusID = $statusId, CompletedDate = CURDATE(), ApprovedBy = $userId WHERE ResignationID = $id");
        $conn->query("UPDATE employee SET IsActive = 0 WHERE EmployeeID = $empId");
        $conn->query("UPDATE deployment SET EndDate = CURDATE() WHERE EmployeeID = $empId AND EndDate IS NULL");

        $dtrCount = $conn->query("SELECT COUNT(*) AS c FROM attendance WHERE EmployeeID = $empId")->fetch_assoc()['c'];
        $payCount = $conn->query("SELECT COUNT(*) AS c FROM payroll p INNER JOIN employee e ON p.EmployeeID = e.EmployeeID WHERE p.EmployeeID = $empId")->fetch_assoc()['c'];
        $meta = json_encode(['attendance_records' => (int)$dtrCount, 'payroll_records' => $payCount, 'archived_at' => date('Y-m-d H:i:s')]);

        $conn->query("INSERT INTO resignation_archive (ResignationID, EmployeeID, ArchiveMetadata, ArchivedBy) VALUES ($id, $empId, '" . $conn->real_escape_string($meta) . "', $userId)");

        echo json_encode(['success' => true, 'message' => 'Marked as Resigned. 201 File archived. Employee deactivated.']);
        exit();
    }
}
