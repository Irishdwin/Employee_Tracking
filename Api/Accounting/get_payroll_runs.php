<?php
/**
 * GET/POST — list active/archived payroll runs and update payroll status tracking for Accounting dashboard.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/client_billing_helper.php';

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

try { payroll_require_accounting($conn,$_SERVER['REQUEST_METHOD']==='POST'); } catch(Throwable $e) {payroll_api_error($e);exit;}
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'update_status') {
        $runCode = trim((string)($_POST['run_id'] ?? ''));
        $newStatus = trim((string)($_POST['status'] ?? ''));
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        $fileInfo = $_FILES['attachment'] ?? null;
        $userId = (int)$_SESSION['user_id'];

        if ($runCode === '' || $newStatus === '') {
            echo json_encode(['success' => false, 'message' => 'Run code and status are required.']);
            exit();
        }

        try { $success = client_billing_update_run_status($conn, $runCode, $newStatus, $remarks, $fileInfo, $userId);
        } catch(Throwable $e) {payroll_api_error($e);exit;}
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Payroll status updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Payroll run not found or failed to update.']);
        }
        exit();
    }
}

if (isset($_GET['history'])) {
    $runCode = trim((string)($_GET['run_id'] ?? ''));
    $run = client_billing_get_run($conn, $runCode);
    if (!$run) {
        echo json_encode(['success' => false, 'message' => 'Payroll run not found.']);
        exit();
    }
    $history = client_billing_get_status_history($conn, (int)$run['PayrollRunID']);
    echo json_encode(['success' => true, 'history' => $history]);
    exit();
}

try {
    $split = client_billing_list_runs_split($conn);
    echo json_encode([
        'success' => true,
        'csrf_token' => payroll_csrf_token(),
        'active_runs' => $split['active_runs'],
        'history_runs' => $split['history_runs'],
        'runs' => array_merge($split['active_runs'], $split['history_runs']),
        'clients' => $split['clients'],
        'current_period' => payroll_kinsenas_period(),
        'current_period_type_a' => payroll_type_period('A'),
        'current_period_type_b' => payroll_type_period('B'),
        'export_base' => '../../Api/Accounting/export_client_billing.php',
    ]);
} catch (Throwable $e) {
    error_log('get_payroll_runs: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load payroll runs.']);
}