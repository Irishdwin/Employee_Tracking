<?php
/**
 * POST — Accounting clicks "Compute Payroll" on a Team Lead DTR submission batch
 * (Step 3 of DTR Submission -> Review -> Compute Payroll -> Review Payroll -> Export).
 *
 * This does NOT introduce a second/competing payroll calculation. The actual per-
 * employee math (basic pay, OT, SSS/PhilHealth/Pag-IBIG, net pay, ...) already lives
 * in payroll_compute_period() / client_billing_fetch_employees_for_run() and is
 * reused as-is by the existing Payroll Processing tab and export_client_billing.php.
 *
 * What this endpoint actually does:
 *   1. Resolves the CompanyID for the given Team Lead + cutoff batch (via deployment).
 *   2. Ensures the matching `payroll_runs` row exists (reuses the existing
 *      client_billing_sync_runs()/client_billing_get_run() helpers — no duplicate
 *      run-creation logic).
 *   3. Advances that run's CurrentStatus to "Computed" so the Accounting dashboard
 *      can show it as ready for the Payroll Review / Export steps, and links the
 *      DTR batch back to that run.
 *
 * Body (JSON or form): tl_id, cutoff_start (YYYY-MM-DD), cutoff_end (YYYY-MM-DD)
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = trim((string)$_SESSION['role']);
if (!in_array($role, ['Accounting', 'Admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/client_billing_helper.php';
require_once __DIR__.'/../Helpers/payroll_finalization.php';

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$body = is_array($json) ? $json : $_POST;

$tlId = (int)($body['tl_id'] ?? 0);
$siteId = (int)($body['site_id'] ?? 0);
$cutoffStart = trim((string)($body['cutoff_start'] ?? ''));
$cutoffEnd = trim((string)($body['cutoff_end'] ?? ''));

if ($tlId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffEnd)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'tl_id, cutoff_start, and cutoff_end are required.']);
    exit();
}

try {
    payroll_require_accounting($conn,true);
    // Resolve the company for this batch: every dtr_submissions row for this TL+cutoff
    // should share one CompanyID via its DeploymentID. Reject ambiguous batches rather
    // than guessing which company to compute for.
    $companyStmt = $conn->prepare("
        SELECT DISTINCT d.CompanyID,ds.SiteID
        FROM dtr_submissions ds
        INNER JOIN deployment d ON d.DeploymentID = ds.DeploymentID
        WHERE ds.SiteSupervisorID = ? AND ds.CutoffStartDate = ? AND ds.CutoffEndDate = ? AND (?=0 OR ds.SiteID=?)
    ");
    $companyStmt->bind_param('issii', $tlId, $cutoffStart, $cutoffEnd,$siteId,$siteId);
    $companyStmt->execute();
    $companyRows = $companyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $companyStmt->close();

    if (empty($companyRows)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No DTR submissions found for this Team Lead and cutoff.']);
        exit();
    }
    if (count($companyRows) > 1) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This batch spans more than one company deployment; cannot compute a single payroll run.']);
        exit();
    }
    $companyId = (int)$companyRows[0]['CompanyID'];
    $siteId = (int)$companyRows[0]['SiteID'];

    $lockedPayroll=payroll_query($conn,'SELECT p.Payroll_ID FROM payroll p JOIN dtr_submissions ds ON ds.EmployeeID=p.EmployeeID AND ds.CutoffStartDate=p.PayPeriodStart AND ds.CutoffEndDate=p.PayPeriodEnd WHERE ds.SiteSupervisorID=? AND ds.SiteID=? AND ds.CutoffStartDate=? AND ds.CutoffEndDate=? LIMIT 1','iiss',[$tlId,$siteId,$cutoffStart,$cutoffEnd])->get_result()->fetch_assoc();
    if($lockedPayroll)throw new RuntimeException('This cutoff already has saved payroll. View its billing export and published payslips; do not recompute finalized payroll.',409);

    require_once __DIR__.'/../Helpers/dtr_classification.php';
    $pending=payroll_query($conn,'SELECT Remarks FROM dtr_submissions WHERE SiteSupervisorID=? AND SiteID=? AND CutoffStartDate=? AND CutoffEndDate=?','iiss',[$tlId,$siteId,$cutoffStart,$cutoffEnd])->get_result();
    while($dtr=$pending->fetch_assoc()){if(!empty(dtr_classification_decode($dtr['Remarks'])['requires_review']))throw new InvalidArgumentException('Resolve and verify the submitted DTR Accounting review before computing payroll.');}

    // Ensure payroll_runs rows exist for every company/cutoff pair that currently has
    // DTR submissions (reuses the existing auto-sync — no duplicate creation logic).
    client_billing_sync_runs($conn);

    $employeeCountStmt = $conn->prepare("
        SELECT COUNT(DISTINCT ds.EmployeeID) AS c
        FROM dtr_submissions ds
        INNER JOIN deployment d ON d.DeploymentID = ds.DeploymentID
        WHERE ds.SiteSupervisorID = ? AND ds.CutoffStartDate = ? AND ds.CutoffEndDate = ? AND (?=0 OR ds.SiteID=?)
    ");
    $employeeCountStmt->bind_param('issii', $tlId, $cutoffStart, $cutoffEnd,$siteId,$siteId);
    $employeeCountStmt->execute();
    $employeeCount = (int)($employeeCountStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $employeeCountStmt->close();

    $runCode = client_billing_run_code($cutoffStart, $cutoffEnd, $companyId, $tlId,$siteId);
    $run = client_billing_get_run($conn, $runCode);

    if (!$run) {
        // client_billing_sync_runs() only auto-creates company+cutoff runs, so a
        // TL-scoped run has to be created here the first time this Team Lead's
        // batch is computed. Seed it from the company-level run for this same
        // cutoff so CutoffLabel/Status stay consistent with the rest of the app.
        $baseRun = client_billing_get_run($conn, client_billing_run_code($cutoffStart, $cutoffEnd, $companyId));
        $cutoffLabel = $baseRun['CutoffLabel'] ?? (date('M j, Y', strtotime($cutoffStart)) . ' – ' . date('M j, Y', strtotime($cutoffEnd)));
        $baseStatus = $baseRun['Status'] ?? 'Approved';

        $insStmt = $conn->prepare(
            'INSERT INTO payroll_runs
                (RunCode, CompanyID, SiteSupervisorID, PeriodStart, PeriodEnd, CutoffLabel, Status, CurrentStatus)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE SiteSupervisorID = VALUES(SiteSupervisorID)'
        );
        if ($insStmt) {
            $currentStatus = 'Computed';
            $insStmt->bind_param(
                'siisssss',
                $runCode,
                $companyId,
                $tlId,
                $cutoffStart,
                $cutoffEnd,
                $cutoffLabel,
                $baseStatus,
                $currentStatus
            );
            $insStmt->execute();
            $insStmt->close();
        }
        $run = client_billing_get_run($conn, $runCode);
    }

    if (!$run) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Payroll run could not be created for this batch.']);
        exit();
    }

    // Persist which Team Lead this run belongs to, so the export can scope the
    // employee roster to that TL's deployed team even when it is called with
    // only a run_id.
    $tlStmt = $conn->prepare('UPDATE payroll_runs SET SiteSupervisorID = ? WHERE RunCode = ?');
    if ($tlStmt) {
        $tlStmt->bind_param('is', $tlId, $runCode);
        $tlStmt->execute();
        $tlStmt->close();
    }

    payroll_query($conn,'UPDATE payroll_runs SET SiteID=? WHERE RunCode=?','is',[$siteId,$runCode]);
    $preview=client_billing_build_export($conn,$runCode);
    if(count($preview['rows'])!==$employeeCount)throw new RuntimeException('Verify every employee DTR before computing payroll.',409);
    $userId = (int)$_SESSION['user_id'];
    client_billing_update_run_status($conn, $runCode, 'Computed', 'Computed from DTR submission batch (Team Lead ID ' . $tlId . ').', null, $userId);

    $exportBaseline=(int)(payroll_query($conn,'SELECT COALESCE(MAX(ExportID),0) id FROM payroll_billing_exports WHERE PayrollRunID=?','i',[(int)$run['PayrollRunID']])->get_result()->fetch_assoc()['id']);
    payroll_query($conn,"INSERT INTO payroll_status_history(PayrollRunID,Status,Remarks,UpdatedBy) VALUES(?,'Payroll Computed',?,?)",'isi',[(int)$run['PayrollRunID'],'Computed from DTR submission batch; review billing export before finalizing; export_after_id='.$exportBaseline,$userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Payroll computed for ' . $employeeCount . ' employee(s).',
        'run_id' => $runCode,
        'billing_ref_no' => client_billing_billing_ref($runCode),
        'employee_count' => $employeeCount,
        'company_id' => $companyId,
    ]);
} catch (Throwable $e) {
    error_log('compute_payroll_for_batch: ' . $e->getMessage());
    payroll_api_error($e);
}