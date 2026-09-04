<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Employee') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

$email = $_SESSION['email'] ?? '';
$u = $conn->query("SELECT Email FROM users WHERE UsersID = " . (int)$_SESSION['user_id'] . " LIMIT 1");
if ($u && $row = $u->fetch_assoc()) $email = $row['Email'];

$eStmt = $conn->prepare("SELECT EmployeeID FROM employee WHERE Email = ? AND IsActive = 1 LIMIT 1");
$eStmt->bind_param("s", $email);
$eStmt->execute();
$emp = $eStmt->get_result()->fetch_assoc();
$eStmt->close();
if (!$emp) {
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit();
}
$empId = (int)$emp['EmployeeID'];

/** Working days between two dates (excludes weekends and holidays) */
function workingDaysBetween($start, $end, $conn) {
    $holidays = [];
    $h = $conn->query("SELECT HolidayDate FROM holidays WHERE HolidayDate BETWEEN '$start' AND '$end'");
    if ($h) while ($r = $h->fetch_assoc()) $holidays[] = $r['HolidayDate'];
    $count = 0;
    $current = new DateTime($start);
    $endDt = new DateTime($end);
    while ($current <= $endDt) {
        $dow = $current->format('N');
        $dateStr = $current->format('Y-m-d');
        if ($dow < 6 && !in_array($dateStr, $holidays)) $count++;
        $current->modify('+1 day');
    }
    return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT rr.ResignationID, rr.SubmissionDate, rr.EffectiveDate, rr.ResignationType, rr.Reason, rr.CreatedAt,
            COALESCE(s.Name, 'Pending') AS Status
            FROM resignationrequest rr
            LEFT JOIN status s ON rr.StatusID = s.StatusID
            WHERE rr.EmployeeID = ?
            ORDER BY rr.CreatedAt DESC");
    $stmt->bind_param("i", $empId);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($r = $res->fetch_assoc()) {
        $r['WorkingDaysRemaining'] = null;
        if ($r['EffectiveDate'] && $r['Status'] !== 'Resigned') {
            $today = date('Y-m-d');
            if ($r['EffectiveDate'] >= $today) {
                $r['WorkingDaysRemaining'] = workingDaysBetween($today, $r['EffectiveDate'], $conn);
            }
        }
        $list[] = $r;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'resignations' => $list]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $effectiveDate = trim($_POST['effective_date'] ?? '');
    $resignationType = trim($_POST['resignation_type'] ?? 'Voluntary');
    $reason = trim($_POST['reason'] ?? '');

    if ($effectiveDate === '') {
        echo json_encode(['success' => false, 'message' => 'Effective date is required']);
        exit();
    }

    $submissionDate = date('Y-m-d');
    $workingDays = workingDaysBetween($submissionDate, $effectiveDate, $conn);
    if ($workingDays < 30) {
        echo json_encode(['success' => false, 'message' => 'Effective date must be at least 30 working days from today. You have ' . $workingDays . ' working days.']);
        exit();
    }

    $chk = $conn->query("SELECT rr.ResignationID FROM resignationrequest rr LEFT JOIN status s ON rr.StatusID = s.StatusID WHERE rr.EmployeeID = $empId AND (s.Name IS NULL OR s.Name != 'Resigned')");
    if ($chk && $chk->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You already have an active resignation request']);
        exit();
    }

    $statusId = null;
    $s = $conn->query("SELECT StatusID FROM status WHERE Name = 'Resignation Pending' LIMIT 1");
    if ($s && $row = $s->fetch_assoc()) $statusId = (int)$row['StatusID'];
    if (!$statusId) {
        $conn->query("INSERT INTO status (Name) VALUES ('Resignation Pending')");
        $statusId = $conn->insert_id;
    }

    $ins = $conn->prepare("INSERT INTO resignationrequest (EmployeeID, SubmissionDate, EffectiveDate, ResignationType, Reason, StatusID) VALUES (?, ?, ?, ?, ?, ?)");
    $ins->bind_param("issssi", $empId, $submissionDate, $effectiveDate, $resignationType, $reason, $statusId);
    if ($ins->execute()) {
        $ins->close();
        $conn->query("INSERT INTO system_logs (UserID, Action, ActionDescription, TableAffected, RecordID) VALUES (" . (int)$_SESSION['user_id'] . ", 'Resignation Submitted', 'Employee submitted resignation', 'resignationrequest', " . $conn->insert_id . ")");
        echo json_encode(['success' => true, 'message' => 'Resignation request submitted. HR has been notified. 30 working days policy applies.']);
    } else {
        $ins->close();
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}
