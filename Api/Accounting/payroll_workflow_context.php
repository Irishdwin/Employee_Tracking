<?php
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
require __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/payroll_workflow.php';
header('Content-Type: application/json; charset=utf-8');
try {
    payroll_require_accounting($conn);
    if($_SERVER['REQUEST_METHOD']!=='GET') {http_response_code(405);exit;}
    echo json_encode(['success'=>true,'csrf_token'=>payroll_csrf_token(),'editable_fields'=>['Allowances (O)','Other Deductions (AK)']]);
} catch(Throwable $e) {payroll_api_error($e);}
