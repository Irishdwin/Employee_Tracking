<?php
if(PHP_SAPI!=='cli')exit;
$name=$argv[1]??'';if(!preg_match('/\Acodex_sunday_[a-f0-9]+\z/D',$name))exit(1);
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$conn=new mysqli('127.0.0.1','root','',$name);$conn->set_charset('utf8mb4');
$_SESSION=['user_id'=>101,'role'=>'Employee'];$_GET=['payroll_id'=>(int)$argv[2]];
$path=__DIR__.'/../../Api/Employee/get_employee_payroll_history.php';$source=file_get_contents($path);
$source=str_replace("require_once __DIR__ . '/../Common/session_init.php';",'', $source);
$source=str_replace("require __DIR__ . '/../Db_connection/db_connect.php';",'', $source);
$source=str_replace('__DIR__',var_export(dirname(realpath($path)),true),$source);
eval(substr($source,5));
