<?php
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
require __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/payroll_workflow.php';
try {
    payroll_require_accounting($conn);
    $revision=(int)($_GET['revision_id'] ?? 0);$export=(int)($_GET['export_id'] ?? 0);
    $row=$revision ? payroll_query($conn,'SELECT FilePath FROM payroll_billing_revisions WHERE RevisionID=?','i',[$revision])->get_result()->fetch_assoc() : payroll_query($conn,'SELECT FilePath FROM payroll_billing_exports WHERE ExportID=?','i',[$export])->get_result()->fetch_assoc();
    $root=realpath(dirname(__DIR__,2).'/uploads/payroll_billing');$path=$row ? realpath(dirname(__DIR__,2).'/'.$row['FilePath']) : false;
    if(!$root || !$path || !str_starts_with($path,$root.DIRECTORY_SEPARATOR) || !is_file($path))throw new RuntimeException('Archived workbook not found.',404);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="Billing_'.($revision?'Revision_'.$revision:'Export_'.$export).'.xlsx"');readfile($path);
} catch(Throwable $e) {header('Content-Type: application/json');payroll_api_error($e);}
