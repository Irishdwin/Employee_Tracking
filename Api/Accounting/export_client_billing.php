<?php
/**
 * GET — export client payroll billing spreadsheet (.xlsx) for a payroll run.
 * Uses PhpOffice\PhpSpreadsheet to match the client billing template layout.
 *
 * Query: run_id=PR-2026-07B-C5  OR  ref=BR-2026-07B-C5
 */
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../Common/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/client_billing_helper.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PhpSpreadsheet is not installed. Run: composer require phpoffice/phpspreadsheet';
    exit();
}
require_once $autoload;
require_once __DIR__ . '/Lib/client_billing_spreadsheet.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized — please sign in as Accounting staff.';
    exit();
}

$role = trim((string)$_SESSION['role']);
if (!in_array($role, ['Accounting', 'Admin'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden — Accounting role required.';
    exit();
}

$runId = trim((string)($_GET['run_id'] ?? ''));
$billingRef = trim((string)($_GET['ref'] ?? ''));
if ($runId === '' && $billingRef === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing ref or run_id parameter.';
    exit();
}

if ($runId === '' && $billingRef !== '') {
    $runId = client_billing_run_code_from_ref($billingRef);
}

try {
    $export = client_billing_build_export($conn, $runId);
    $spreadsheet = client_billing_render_spreadsheet($export);

    $clientName = (string)$export['client_name'];
    $safeClient = preg_replace('/[^\w\-]+/', '_', $clientName) ?: 'Client';
    $billingRefNo = client_billing_billing_ref($runId);
    $filename = 'Client_Billing_' . $safeClient . '_' . $billingRefNo . '.xlsx';

    client_billing_mark_exported($conn, $runId, (float)$export['totals']['grand_total']);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
    exit();
} catch (Throwable $e) {
    error_log('export_client_billing: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to generate billing export: ' . $e->getMessage();
}