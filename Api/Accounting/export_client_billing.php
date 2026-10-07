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
require_once __DIR__.'/../Helpers/billing_revision_helper.php';

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
    payroll_require_accounting($conn);
    $run=client_billing_get_run($conn,$runId);
    if(!$run) throw new InvalidArgumentException('Payroll run not found.');
    // History download is a stored artifact, never a fresh payroll calculation.
    if(($_GET['archive'] ?? '')==='1') {
        $latest=payroll_query($conn,'SELECT ExportID FROM payroll_billing_exports WHERE PayrollRunID=? ORDER BY ExportID DESC LIMIT 1','i',[(int)$run['PayrollRunID']])->get_result()->fetch_assoc();
        if(!$latest) throw new InvalidArgumentException('No archived export exists for this legacy run. Create an original export first.');
        header('Location: download_billing_file.php?export_id='.(int)$latest['ExportID']);exit;
    }
    $path=null;
    try {
        $members=payroll_query($conn,'SELECT SnapshotJson FROM payroll_run_members WHERE PayrollRunID=? ORDER BY EmployeeID','i',[(int)$run['PayrollRunID']])->get_result()->fetch_all(MYSQLI_ASSOC);
        $export=$members ? ['run'=>$run,'client_name'=>$run['client_name'],'rows'=>array_map(static fn($m)=>json_decode($m['SnapshotJson'],true,512,JSON_THROW_ON_ERROR),$members)] : client_billing_build_export($conn,$runId);
        // build_export's schema checks run before the atomic snapshot transaction.
        $conn->begin_transaction();
        $locked=payroll_query($conn,'SELECT * FROM payroll_runs WHERE PayrollRunID=? FOR UPDATE','i',[(int)$run['PayrollRunID']])->get_result()->fetch_assoc();
        if($locked['AdminOverheadPct']!==$run['AdminOverheadPct'] || $locked['VatPct']!==$run['VatPct'])throw new RuntimeException('Billing settings changed. Please retry export.',409);
        $export=billing_prepare_snapshot($conn,$export);
        $spreadsheet=client_billing_render_spreadsheet($export);
        $token=bin2hex(random_bytes(32));
        $snapshot=$export;$snapshot['token']=$token;$snapshot['sheet_title']=$spreadsheet->getSheet(0)->getTitle();
        $snapshot['cells']=billing_capture_cells($spreadsheet,$snapshot);
        $snapshot['column_values']=billing_capture_values($spreadsheet,$snapshot);
        billing_add_manifest($spreadsheet,$snapshot,$token);
        $relative='uploads/payroll_billing/export_'.$token.'.xlsx';
        billing_storage();$path=dirname(__DIR__,2).'/'.$relative;
        (new Xlsx($spreadsheet))->save($path);
        payroll_query($conn,'INSERT INTO payroll_billing_exports (PayrollRunID,ExportToken,BaselineHash,SnapshotJson,FilePath,CreatedBy) VALUES(?,?,?,?,?,?)','issssi',[(int)$run['PayrollRunID'],$token,hash('sha256',billing_json($snapshot)),billing_json($snapshot),$relative,(int)$_SESSION['user_id']]);
        client_billing_mark_exported($conn,$runId,(float)$export['totals']['grand_total']);
        $conn->commit();
    } catch(Throwable $e) {$conn->rollback();if($path)@unlink($path);throw $e;}
    $filename='Client_Billing_'.client_billing_billing_ref($runId).'.xlsx';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    readfile($path);
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
    exit();
} catch (Throwable $e) {
    error_log('export_client_billing: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e instanceof InvalidArgumentException || $e->getCode()===409 ? $e->getMessage() : 'Failed to generate billing export. Contact Accounting support.';
}
