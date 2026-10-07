<?php
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
require __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/client_billing_helper.php';
require_once __DIR__.'/../Helpers/billing_revision_helper.php';
require_once __DIR__.'/../../vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');
$stored=null;
try {
    if($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);exit;}
    $actor=payroll_require_accounting($conn,true);
    if(($_POST['action'] ?? '')==='confirm') {
        echo json_encode(billing_confirm_revision($conn,(string)($_POST['preview_token'] ?? ''),(string)($_POST['reason'] ?? ''),$actor));exit;
    }
    if(($_POST['action'] ?? '')!=='preview') throw new InvalidArgumentException('Choose Preview or Confirm.');
    $run=client_billing_get_run($conn,(string)($_POST['run_id'] ?? ''));
    if(!$run) throw new RuntimeException('Payroll run not found.',404);
    $file=$_FILES['workbook'] ?? null;
    if(!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']>10*1024*1024 || strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='xlsx' || !is_uploaded_file($file['tmp_name']))
        throw new InvalidArgumentException('Choose an XLSX workbook of at most 10 MB.');
    billing_validate_zip($file['tmp_name']);
    $reader=new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();$reader->setReadDataOnly(false);
    $book=$reader->load($file['tmp_name']);
    $meta=$book->getSheetByName('_ONAZUL_EXPORT');
    if(!$meta) throw new InvalidArgumentException('This workbook has no verified export identity. Export a new workbook from this system.');
    $manifest=json_decode((string)$meta->getCell('A1')->getValue(),true,32,JSON_THROW_ON_ERROR);
    $export=payroll_query($conn,'SELECT * FROM payroll_billing_exports WHERE ExportToken=? AND PayrollRunID=?','si',[(string)($manifest['token'] ?? ''),(int)$run['PayrollRunID']])->get_result()->fetch_assoc();
    if(!$export) throw new InvalidArgumentException('This workbook belongs to a different or unknown payroll run.');
    $snapshot=json_decode($export['SnapshotJson'],true,512,JSON_THROW_ON_ERROR);
    $changes=billing_detect_changes($book,$snapshot,$manifest);$book->disconnectWorksheets();
    $hash=hash_file('sha256',$file['tmp_name']);
    $existing=payroll_query($conn,'SELECT * FROM payroll_billing_revisions WHERE PayrollRunID=? AND ExportID=? AND FileHash=?','iis',[(int)$run['PayrollRunID'],(int)$export['ExportID'],$hash])->get_result()->fetch_assoc();
    $preview=['changes'=>$changes,'changed_employees'=>count(array_unique(array_column($changes,'employee_id'))),'unchanged_employees'=>count($snapshot['rows'])-count(array_unique(array_column($changes,'employee_id'))),'invalid_rows'=>0,'billing_ref'=>client_billing_billing_ref($run['RunCode']),'client'=>$run['client_name'],'site'=>$snapshot['rows'][0]['area_site'] ?? $run['DeploymentSite'],'cutoff'=>$run['CutoffLabel'],'export_id'=>(int)$export['ExportID']];
    if($existing && $existing['ConfirmedAt']) {
        echo json_encode(['success'=>true,'already_confirmed'=>true,'preview'=>$preview,'message'=>'This revision was already confirmed. No additional adjustments were created.']);exit;
    }
    $impacts=[];
    if($changes) {
        foreach($snapshot['rows'] as $row) {
            if(!$row['payroll_id']) throw new RuntimeException('Finalize payroll and export a fresh workbook before revising amounts.',409);
            $p=payroll_query($conn,'SELECT * FROM payroll WHERE Payroll_ID=?','i',[(int)$row['payroll_id']])->get_result()->fetch_assoc();
            if(!$p || billing_fingerprint($p)!==$row['payroll_fingerprint']) throw new RuntimeException('Payroll changed after export. Export and preview a fresh workbook.',409);
            $targets=[];foreach($changes as $c)if($c['employee_id']===(int)$row['employee_db_id'])$targets[$c['component']]=$c['new'];
            if($targets) { $updated=billing_apply_components($row,$targets);$impacts[]=['employee_name'=>$row['employee_name'],'employee_number'=>$row['employee_id'],'gross_before'=>$row['gross_payroll'],'gross_after'=>$updated['gross_payroll'],'deductions_before'=>$row['total_ee_deductions'],'deductions_after'=>$updated['total_ee_deductions'],'net_before'=>$row['net_salary'],'net_after'=>$updated['net_salary'],'invoice_before'=>$row['grand_total_invoice'],'invoice_after'=>$updated['grand_total_invoice']]; }
        }
    }
    $preview['impacts']=$impacts;
    $token=$existing ? $existing['PreviewToken'] : bin2hex(random_bytes(32));
    if(!$existing && $changes) {
        $relative='uploads/payroll_billing/import_'.bin2hex(random_bytes(20)).'.xlsx';
        $stored=dirname(__DIR__,2).'/'.$relative;
        billing_storage();if(!move_uploaded_file($file['tmp_name'],$stored))throw new RuntimeException('Unable to retain source workbook.');
        payroll_query($conn,'INSERT INTO payroll_billing_revisions (PayrollRunID,ExportID,FileHash,PreviewToken,PreviewJson,FilePath,CreatedBy,ChangedEmployees) VALUES(?,?,?,?,?,?,?,?)','iissssii',[(int)$run['PayrollRunID'],(int)$export['ExportID'],$hash,$token,billing_json($preview),$relative,$actor,$preview['changed_employees']]);
        $stored=null;
    }
    echo json_encode(['success'=>true,'preview_token'=>$changes ? $token : null,'preview'=>$preview,'message'=>$changes ? 'Review the differences before confirming.' : 'No payroll changes found.']);
} catch(JsonException $e) {
    if($stored)@unlink($stored);http_response_code(422);echo json_encode(['success'=>false,'message'=>'Invalid workbook identity. Export a fresh workbook.']);
} catch(Throwable $e) {if($stored)@unlink($stored);payroll_api_error($e);}
