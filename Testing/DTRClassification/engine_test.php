<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Run only against the existing disposable fixture database; no production writes.
require __DIR__.'/../../Api/Helpers/employee_payroll_helper.php';
require __DIR__.'/../../Api/Helpers/dtr_classification.php';
date_default_timezone_set('Asia/Manila');
$c=new mysqli('127.0.0.1','root','','codex_multisite_20261005_r2');
$p=new PDO('mysql:host=127.0.0.1;dbname=codex_multisite_20261005_r2;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function compute($start='2026-10-01',$end='2026-10-01'){global $c,$p;return dtr_classification_from_payroll(payroll_compute_period($c,$p,4,1000,125,0,26000,$start,$end));}
function check($ok,$name,$data){if(!$ok)throw new RuntimeException($name.': '.json_encode($data));echo 'PASS '.$name.' '.json_encode($data).PHP_EOL;}
$c->query("DELETE FROM overtime WHERE EmployeeID=4 AND OvertimeDate BETWEEN '2026-10-01' AND '2026-10-10'");
$p->exec("DELETE FROM daily_time_records WHERE employee_id=4 AND date BETWEEN '2026-10-01' AND '2026-10-10'");
$p->exec("INSERT INTO daily_time_records(employee_id,date,time_in,time_out,total_hours_rendered,status) VALUES(4,'2026-10-01','2026-10-01 08:00:00','2026-10-01 17:00:00',8,'Clocked Out')");
$r=compute();check($r['regular_hours']==8&&$r['overtime_hours']==0&&$r['holiday_work_hours']==0,'normal 8-hour day',$r);
$p->exec("UPDATE daily_time_records SET total_hours_rendered=9,time_out='2026-10-01 18:00:00' WHERE employee_id=4 AND date='2026-10-01'");
$r=compute();check($r['regular_hours']==8&&$r['overtime_hours']==0,'9 hours without approved overtime',$r);
$c->query("INSERT INTO overtime(EmployeeID,DeploymentID,OvertimeDate,OTStartTime,OTEndTime,OvertimeHours,StatusID) VALUES(4,4,'2026-10-01','17:00','18:00',1,4)");
$r=compute();check($r['overtime_hours']==0,'pending overtime excluded',$r);
$c->query("UPDATE overtime SET StatusID=7 WHERE EmployeeID=4 AND OvertimeDate='2026-10-01'");
$r=compute();check($r['regular_hours']==8&&$r['overtime_hours']==1,'9 hours with 1 approved overtime counted once',$r);
foreach(['Regular Holiday','Special Non-working Day'] as $type){
$c->query("INSERT INTO holidays(HolidayName,HolidayDate,HolidayType,PayMultiplier,IsNationwide,Year) VALUES('DTR isolated fixture','2026-10-01','$type',".($type==='Regular Holiday'?'2':'1.3').",1,2026)");$id=$c->insert_id;
$r=compute();check($r['holiday_work_hours']==8&&$r['holiday_overtime_hours']==1&&$r['overtime_hours']==1&&$r['ordinary_overtime_hours']==0&&$r['holiday_breakdown'][0]['type']===$type,$type.' worked plus approved OT',$r);
$p->exec("UPDATE daily_time_records SET total_hours_rendered=10,time_out='2026-10-01 19:00:00' WHERE employee_id=4 AND date='2026-10-01'");$c->query("UPDATE overtime SET OvertimeHours=2 WHERE EmployeeID=4 AND OvertimeDate='2026-10-01'");
$r=compute();check($r['holiday_work_hours']==8&&$r['holiday_overtime_hours']==2&&$r['overtime_hours']==2,'holiday 10-hour duty + approved 2 counted once',$r);
$c->query("DELETE FROM holidays WHERE HolidayID=$id");
$p->exec("UPDATE daily_time_records SET total_hours_rendered=9,time_out='2026-10-01 18:00:00' WHERE employee_id=4 AND date='2026-10-01'");$c->query("UPDATE overtime SET OvertimeHours=1 WHERE EmployeeID=4 AND OvertimeDate='2026-10-01'");
}
$c->query("INSERT INTO holidays(HolidayName,HolidayDate,HolidayType,PayMultiplier,IsNationwide,Year) VALUES('Unworked fixture','2026-10-02','Regular Holiday',2,1,2026)");$id=$c->insert_id;
$r=compute('2026-10-02','2026-10-02');check($r['holiday_work_hours']==0,'unworked holiday never invents duty',$r);
$c->query("DELETE FROM holidays WHERE HolidayID=$id");
$r=compute('2026-10-02','2026-10-02');check($r['holiday_work_hours']==0&&$r['overtime_hours']==0,'following normal day and cutoff isolation',$r);
$r=compute('2026-10-01','2026-10-02');check($r['regular_hours']==8&&$r['overtime_hours']==1,'multi-day aggregation',$r);
check(dtr_classification_decode('DTR_CLASSIFICATION_V1:'.json_encode($r))==$r,'snapshot round trip',$r);
check(dtr_classification_decode('Legacy remarks')===null,'legacy snapshot remains unknown',[]);
$p->exec("INSERT INTO daily_time_records(employee_id,date,time_in,time_out,total_hours_rendered,status) VALUES(4,'2026-10-03','2026-10-03 08:00:00','2026-10-03 19:00:00',10,'Clocked Out') ON DUPLICATE KEY UPDATE total_hours_rendered=10,time_out='2026-10-03 19:00:00'");
$c->query("DELETE FROM overtime WHERE EmployeeID=4 AND OvertimeDate='2026-10-03'");
$c->query("INSERT INTO overtime(EmployeeID,DeploymentID,OvertimeDate,OTStartTime,OTEndTime,OvertimeHours,StatusID) VALUES(4,4,'2026-10-03','17:00','19:00',2,7)");
$c->query("INSERT INTO holidays(HolidayName,HolidayDate,HolidayType,PayMultiplier,IsNationwide,Year) VALUES('Rest holiday fixture','2026-10-03','Regular Holiday',2,1,2026)");$first=$c->insert_id;
$r=compute('2026-10-03','2026-10-03');check($r['holiday_work_hours']==8&&$r['holiday_overtime_hours']==2&&$r['holiday_breakdown'][0]['is_rest_day']===true,'rest day plus holiday classification',$r);
$c->query("INSERT INTO holidays(HolidayName,HolidayDate,HolidayType,PayMultiplier,IsNationwide,Year) VALUES('Double holiday fixture','2026-10-03','Regular Holiday',2,1,2026)");$second=$c->insert_id;
$r=compute('2026-10-03','2026-10-03');check($r['holiday_work_hours']==8&&$r['holiday_overtime_hours']==2&&$r['holiday_breakdown'][0]['is_double_holiday']===true&&$r['holiday_breakdown'][0]['is_rest_day']===true,'double regular holiday classified once',$r);
$c->query("DELETE FROM holidays WHERE HolidayID IN($first,$second)");
$c->query("DELETE FROM overtime WHERE EmployeeID=4 AND OvertimeDate='2026-10-03'");
$p->exec("DELETE FROM daily_time_records WHERE employee_id=4 AND date='2026-10-03'");
$c->query("INSERT INTO overtime(EmployeeID,DeploymentID,OvertimeDate,OvertimeHours,StatusID) VALUES(4,6,'2026-10-01',2,7)");$foreign=$c->insert_id;
$scoped=dtr_classification_from_payroll(payroll_compute_period($c,$p,4,1000,125,0,26000,'2026-10-01','2026-10-01',null,null,4));check($scoped['overtime_hours']==1,'other deployment approved overtime excluded',$scoped);
$c->query("DELETE FROM overtime WHERE OvertimeID=$foreign");
file_put_contents(__DIR__.'/engine_results.txt','All isolated production-engine assertions passed.');
// Leave the isolated ordinary-day fixture for HTTP submission testing.
?>