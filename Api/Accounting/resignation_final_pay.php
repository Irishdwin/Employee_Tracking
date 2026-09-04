<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!in_array($_SESSION['role'], ['Admin', 'Accounting'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT rr.ResignationID, rr.EmployeeID, rr.SubmissionDate, rr.EffectiveDate, rr.ResignationType, rr.FinalPayAmount, rr.FinalPay, rr.QuitclaimDocumentPath,
            COALESCE(s.Name, 'Pending') AS Status,
            CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName, e.EmployeeNumber, e.BasicSalary
            FROM resignationrequest rr
            INNER JOIN employee e ON rr.EmployeeID = e.EmployeeID
            LEFT JOIN status s ON rr.StatusID = s.StatusID
            WHERE s.Name IN ('Clearance Complete', 'Final Pay Ready', 'Quitclaim Ready', 'Resigned')
            ORDER BY rr.EffectiveDate DESC";
    $res = $conn->query($sql);
    $list = [];
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;
    echo json_encode(['success' => true, 'resignations' => $list]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'compute_final_pay') {
        $id = (int)($_POST['resignation_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }

        $chk = $conn->query("SELECT rr.*, e.BasicSalary, s.Name AS Status FROM resignationrequest rr INNER JOIN employee e ON rr.EmployeeID = e.EmployeeID LEFT JOIN status s ON rr.StatusID = s.StatusID WHERE rr.ResignationID = $id");
        if (!$chk || $chk->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Not found']);
            exit();
        }
        $row = $chk->fetch_assoc();
        if ($row['Status'] !== 'Clearance Complete') {
            echo json_encode(['success' => false, 'message' => 'Clearance must be complete first']);
            exit();
        }

        $basic = (float)($row['BasicSalary'] ?? 0);
        $proRata = $basic / 2;
        $sss = round($basic * 0.05 / 2, 2);
        $ph = round(min(max($basic, 10000), 100000) * 0.025 / 2, 2);
        $pag = round(min($basic * 0.02 / 2, 100), 2);
        $cashAdvance = 0;
        $ca = $conn->query("SELECT COALESCE(SUM(RemainingBalance),0) AS tot FROM cashadvance WHERE EmployeeID = " . (int)$row['EmployeeID'] . " AND COALESCE(Approval,'')='Approved' AND RemainingBalance > 0");
        if ($ca && $r = $ca->fetch_assoc()) $cashAdvance = (float)$r['tot'];
        $finalPay = $proRata - $sss - $ph - $pag - $cashAdvance;
        if ($finalPay < 0) $finalPay = 0;

        $details = json_encode(['pro_rata' => $proRata, 'sss' => $sss, 'philhealth' => $ph, 'pagibig' => $pag, 'cash_advance' => $cashAdvance]);

        $statusId = null;
        $s = $conn->query("SELECT StatusID FROM status WHERE Name = 'Final Pay Ready' LIMIT 1");
        if ($s && $r = $s->fetch_assoc()) $statusId = (int)$r['StatusID'];
        if (!$statusId) {
            $conn->query("INSERT INTO status (Name) VALUES ('Final Pay Ready')");
            $statusId = $conn->insert_id;
        }

        $conn->query("UPDATE resignationrequest SET FinalPayAmount = $finalPay, FinalPayComputationDetails = '" . $conn->real_escape_string($details) . "', FinalPay = $finalPay, ProcessedBy = $userId, StatusID = $statusId WHERE ResignationID = $id");
        echo json_encode(['success' => true, 'message' => 'Final pay computed: ₱' . number_format($finalPay, 2), 'amount' => $finalPay]);
        exit();
    }

    if ($action === 'upload_quitclaim') {
        $id = (int)($_POST['resignation_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }

        $path = '';
        if (!empty($_FILES['quitclaim_file']['name'])) {
            $dir = __DIR__ . '/../../uploads/quitclaims/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = pathinfo($_FILES['quitclaim_file']['name'], PATHINFO_EXTENSION);
            $path = 'uploads/quitclaims/' . $id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['quitclaim_file']['tmp_name'], __DIR__ . '/../../' . $path);
        }
        if ($path === '') {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            exit();
        }

        $statusId = null;
        $s = $conn->query("SELECT StatusID FROM status WHERE Name = 'Quitclaim Ready' LIMIT 1");
        if ($s && $r = $s->fetch_assoc()) $statusId = (int)$r['StatusID'];
        if (!$statusId) {
            $conn->query("INSERT INTO status (Name) VALUES ('Quitclaim Ready')");
            $statusId = $conn->insert_id;
        }

        $conn->query("UPDATE resignationrequest SET QuitclaimDocumentPath = '" . $conn->real_escape_string($path) . "', QuitclaimSignedDate = CURDATE(), StatusID = $statusId WHERE ResignationID = $id");
        echo json_encode(['success' => true, 'message' => 'Quitclaim document uploaded']);
        exit();
    }
}
