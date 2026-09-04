<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['HR', 'Admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];

function workingDaysBetween($start, $end, $conn) {
    $holidays = [];
    $h = $conn->query("SELECT HolidayDate FROM holidays WHERE HolidayDate BETWEEN '$start' AND '$end'");
    if ($h) while ($r = $h->fetch_assoc()) $holidays[] = $r['HolidayDate'];
    $count = 0;
    $current = new DateTime($start);
    $endDt = new DateTime($end);
    while ($current <= $endDt) {
        if ($current->format('N') < 6 && !in_array($current->format('Y-m-d'), $holidays)) $count++;
        $current->modify('+1 day');
    }
    return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $sql = "SELECT rr.ResignationID, rr.EmployeeID, rr.SubmissionDate, rr.EffectiveDate, rr.ResignationType, rr.Reason, rr.ClearanceIssuedDate, rr.QuitclaimDocumentPath,
                COALESCE(s.Name, 'Pending') AS Status,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName, e.EmployeeNumber
                FROM resignationrequest rr
                INNER JOIN employee e ON rr.EmployeeID = e.EmployeeID
                LEFT JOIN status s ON rr.StatusID = s.StatusID
                ORDER BY rr.SubmissionDate DESC";
        $res = $conn->query($sql);
        $list = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $r['WorkingDaysRemaining'] = null;
                if ($r['EffectiveDate'] && $r['Status'] !== 'Resigned') {
                    $today = date('Y-m-d');
                    if ($r['EffectiveDate'] >= $today) $r['WorkingDaysRemaining'] = workingDaysBetween($today, $r['EffectiveDate'], $conn);
                }
                $list[] = $r;
            }
        }
        echo json_encode(['success' => true, 'resignations' => $list]);
        exit();
    }

    if ($action === 'clearance' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $emp = $conn->query("SELECT CONCAT(e.FirstName,' ',e.LastName) AS EmployeeName FROM resignationrequest rr INNER JOIN employee e ON rr.EmployeeID = e.EmployeeID WHERE rr.ResignationID = $id")->fetch_assoc();
        $items = $conn->query("SELECT ci.ClearanceItemID, ci.ItemName, ci.IsCleared, ci.ClearedDate, ci.Remarks FROM clearance_items ci WHERE ci.ResignationID = $id ORDER BY ci.ClearanceItemID");
        $list = [];
        if ($items) while ($r = $items->fetch_assoc()) $list[] = $r;
        echo json_encode(['success' => true, 'items' => $list, 'employeeName' => $emp['EmployeeName'] ?? 'Employee']);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'issue_clearance') {
        $id = (int)($_POST['resignation_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }

        $chk = $conn->query("SELECT StatusID, s.Name FROM resignationrequest rr LEFT JOIN status s ON rr.StatusID = s.StatusID WHERE ResignationID = $id");
        if (!$chk || $chk->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Not found']);
            exit();
        }
        $row = $chk->fetch_assoc();
        if ($row['Name'] !== 'Resignation Pending') {
            echo json_encode(['success' => false, 'message' => 'Clearance already issued or invalid status']);
            exit();
        }

        $statusId = null;
        $s = $conn->query("SELECT StatusID FROM status WHERE Name = 'Clearance Issued' LIMIT 1");
        if ($s && $r = $s->fetch_assoc()) $statusId = (int)$r['StatusID'];
        if (!$statusId) {
            $conn->query("INSERT INTO status (Name) VALUES ('Clearance Issued')");
            $statusId = $conn->insert_id;
        }

        $defaultItems = ['ID/Company ID', 'Uniform/Assets', 'DTR/Time Records', 'Payslips', 'SSS/PhilHealth/Pag-IBIG', 'Department Clearance', 'HR Clearance'];
        foreach ($defaultItems as $item) {
            $conn->query("INSERT INTO clearance_items (ResignationID, ItemName) VALUES ($id, '" . $conn->real_escape_string($item) . "')");
        }

        $conn->query("UPDATE resignationrequest SET StatusID = $statusId, ClearanceIssuedDate = NOW(), ClearanceIssuedBy = $userId WHERE ResignationID = $id");

        $statusInProgress = null;
        $s2 = $conn->query("SELECT StatusID FROM status WHERE Name = 'Clearance In Progress' LIMIT 1");
        if ($s2 && $r = $s2->fetch_assoc()) $statusInProgress = (int)$r['StatusID'];
        if ($statusInProgress) {
            $conn->query("UPDATE resignationrequest SET StatusID = $statusInProgress WHERE ResignationID = $id");
        }

        echo json_encode(['success' => true, 'message' => 'Exit Clearance issued. Track progress below.']);
        exit();
    }

    if ($action === 'clear_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $resignId = (int)($_POST['resignation_id'] ?? 0);
        if ($itemId <= 0 || $resignId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit();
        }
        $conn->query("UPDATE clearance_items SET IsCleared = 1, ClearedDate = NOW(), ClearedBy = $userId WHERE ClearanceItemID = $itemId AND ResignationID = $resignId");

        $total = $conn->query("SELECT COUNT(*) AS c FROM clearance_items WHERE ResignationID = $resignId")->fetch_assoc()['c'];
        $cleared = $conn->query("SELECT COUNT(*) AS c FROM clearance_items WHERE ResignationID = $resignId AND IsCleared = 1")->fetch_assoc()['c'];
        if ($cleared >= $total) {
            $sid = $conn->query("SELECT StatusID FROM status WHERE Name = 'Clearance Complete' LIMIT 1")->fetch_assoc();
            if ($sid) $conn->query("UPDATE resignationrequest SET StatusID = " . $sid['StatusID'] . " WHERE ResignationID = $resignId");
        }
        echo json_encode(['success' => true, 'message' => 'Item cleared']);
        exit();
    }
}
