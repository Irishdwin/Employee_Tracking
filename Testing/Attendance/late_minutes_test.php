<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/Api/_late_minutes.php';
require __DIR__.'/../../Api/Helpers/employee_payroll_helper.php';
date_default_timezone_set('Asia/Manila');$checks=0;
$schedule=['time_in'=>'09:00:00','time_out'=>'18:00:00','grace_period'=>10,'break_minutes'=>60,'working_days'=>'Mon,Tue,Wed,Thu,Fri'];
foreach([0=>0,10=>0,11=>11,30=>30,90=>90] as $input=>$expected){
 $time=testing_late_time_in($input,$schedule);$day=payroll_timesheet_day_status(['time_in'=>'2026-10-09 '.$time.':00','time_out'=>'2026-10-09 18:00:00','break_time'=>null,'total_hours_rendered'=>null],'2026-10-09',$schedule,'2026-10-10');
 if($day['late_minutes']!==$expected)throw new RuntimeException('Late minutes mismatch for '.$input);$checks++;
}
foreach([-1,'1.5','abc',1440,[],true] as $bad){try{testing_late_time_in($bad,$schedule);throw new RuntimeException('Invalid value accepted');}catch(InvalidArgumentException $e){$checks++;}}
try{testing_late_time_in(121,['time_in'=>'22:00:00']);throw new RuntimeException('Next-day clock-in accepted');}catch(InvalidArgumentException $e){$checks++;}
echo $checks." checks passed: actual late minutes, grace boundaries and invalid inputs. No database writes.\n";
