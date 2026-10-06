<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../Api/Helpers/employee_payroll_helper.php';
require __DIR__.'/../../Api/Helpers/dtr_classification.php';
date_default_timezone_set('Asia/Manila');
$c=new mysqli('127.0.0.1','root','','onazul_hris_db');$p=new PDO('mysql:host=127.0.0.1;dbname=onazul_hris_db;charset=utf8mb4','root','');
$employee=$c->query('SELECT daily_rate,hourly_rate FROM employee WHERE EmployeeID=77')->fetch_assoc();
$rows=$p->query("SELECT date,time_in,break_time,time_out,total_hours_rendered FROM daily_time_records WHERE employee_id=77 AND date BETWEEN '2026-09-26' AND '2026-10-10' ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);$audit=[];
foreach($rows as $row){$date=$row['date'];$schedule=payroll_resolve_deployment_schedule($c,77,$date);$classification=dtr_classification_from_payroll(payroll_compute_period($c,$p,77,(float)$employee['daily_rate'],(float)$employee['hourly_rate'],0,0,$date,$date,null,null,47));$audit[]=array_merge($row,['schedule'=>$schedule,'classification'=>$classification]);}
file_put_contents(__DIR__.'/actual_employee_audit.json',json_encode(['employee_id'=>77,'deployment_id'=>47,'cutoff_start'=>'2026-09-26','cutoff_end'=>'2026-10-10','rows'=>$audit],JSON_PRETTY_PRINT));echo 'PASS actual employee 77 audit: '.count($audit).' recorded dates'.PHP_EOL;