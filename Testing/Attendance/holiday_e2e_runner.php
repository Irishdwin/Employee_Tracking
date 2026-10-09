<?php
/** CLI-only, disposable-schema integration runner. No production record writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Keep stdout exclusively JSON; diagnostics belong in the launcher's stderr log.
ini_set('display_errors','stderr');
require __DIR__ . '/../../Api/Db_connection/db_connect.php';
$live = $conn;
$sourceDb = $dbname;
$testDb = 'codex_holiday_' . bin2hex(random_bytes(6));
$root = sys_get_temp_dir() . '/onazul-holiday-' . bin2hex(random_bytes(8));
$server = null; $created = false; $result = ['success'=>false];
 $employeeInput=null;foreach($argv??[] as $argument)if(str_starts_with($argument,'--employee-context='))$employeeInput=json_decode(substr($argument,19),true);
$verifyFailureCleanup=in_array('--verify-failure-cleanup',$argv??[],true);
$lock = fopen(sys_get_temp_dir().'/onazul-holiday-e2e.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX|LOCK_NB)) { echo json_encode(['success'=>false,'message'=>'A holiday test is already running.']); exit(1); }
function fixture_copy(string $from, string $to): void {
    if (!is_dir($to)) mkdir($to, 0700, true);
    foreach (new DirectoryIterator($from) as $file) {
        if ($file->isDot()) continue;
        $target=$to.'/'.$file->getFilename();
        if ($file->isDir()) fixture_copy($file->getPathname(),$target);
        else copy($file->getPathname(),$target);
    }
}
function fixture_insert(mysqli $db, string $table, array $values): int {
    $columns=implode(',',array_map(static fn($key)=>'`'.$key.'`',array_keys($values)));
    $stmt=$db->prepare('INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.implode(',',array_fill(0,count($values),'?')).')');
    $args=array_values($values); $stmt->bind_param(str_repeat('s',count($args)),...$args); $stmt->execute();
    return (int)$db->insert_id;
}
function fixture_session(string $directory, int $uid, string $role): string {
    ini_set('session.save_path',$directory); ini_set('session.use_cookies','0'); ini_set('session.cache_limiter','');
    $id=bin2hex(random_bytes(16)); session_id($id);session_start();
    $_SESSION=['user_id'=>$uid,'role'=>$role,'last_activity'=>time(),'payroll_csrf'=>'fixture-holiday-csrf'];session_write_close(); return $id;
}
function fixture_request(string $url, string $session, ?array $body=null): array {
    $context=stream_context_create(['http'=>['method'=>$body===null?'GET':'POST','timeout'=>30,'ignore_errors'=>true,
        'header'=>"Cookie: PHPSESSID=$session\r\nContent-Type: application/json\r\nX-Payroll-CSRF: fixture-holiday-csrf\r\n",'content'=>$body===null?'':json_encode($body)]]);
    $text=@file_get_contents($url,false,$context);
    if($text===false)throw new RuntimeException('Fixture HTTP request failed: '.$url.'; '.(error_get_last()['message']??'No HTTP response.'));
    $data=json_decode((string)$text,true);
    if (!is_array($data) || empty($data['success'])) throw new RuntimeException($url.': '.($data['message']??substr((string)$text,0,250)).' '.($data['error_details']??''));
    return $data;
}
function fixture_snapshot(mysqli $db): array {
    $out=[];
    foreach (['holidays','employee','attendance','daily_time_records','overtime','dtr_submissions','payroll','payroll_runs'] as $table) {
        $out[$table]=$db->query('CHECKSUM TABLE `'.$table.'`')->fetch_assoc()['Checksum'];
    }
    return $out;
}
try {
    $before=fixture_snapshot($live);
    $live->query('CREATE DATABASE `'.$testDb.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'); $created=true;
    $db=new mysqli($servername,$username,$password,$testDb); $db->set_charset('utf8mb4');$db->query("SET time_zone='+08:00'");
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    $tables=$live->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
    while ($row=$tables->fetch_row()) {
        $name=$row[0]; $ddl=$live->query('SHOW CREATE TABLE `'.$name.'`')->fetch_row()[1];
        $db->query($ddl);
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    // Only non-personal role/status reference values are copied. All people are synthetic.
    foreach (['roles','status'] as $table) {
        foreach ($live->query('SELECT * FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC) as $row) fixture_insert($db,$table,$row);
    }
    fixture_copy(__DIR__.'/../../Api',$root.'/Api');
    fixture_copy(__DIR__.'/Api',$root.'/Testing/Attendance/Api');
    mkdir($root.'/sessions',0700,true);
    // Session storage is isolated too; do not rely on Windows php.ini path parsing.
    $sessionFile=$root.'/Api/Common/session_init.php';
    file_put_contents($sessionFile,"<?php\nini_set('session.save_path', ".var_export($root.'/sessions',true).");\n".substr(file_get_contents($sessionFile),5));
    $fixtureConfig=file_get_contents($root.'/Api/Db_connection/db_connect.php');
    file_put_contents($root.'/Api/Db_connection/db_connect.php',str_replace("\$dbname = '$sourceDb';","\$dbname = '$testDb';",$fixtureConfig));
    foreach (['attendance_pdo.php','hris_pdo.php'] as $file) {
        $path=$root.'/Api/Helpers/'.$file;
        file_put_contents($path,str_replace(['dbname='.$sourceDb.';',"'".$sourceDb."'"],['dbname='.$testDb.';',"'".$testDb."'"],file_get_contents($path)));
    }
    // All computation below loads the copied current production helpers with isolated connections.
    require $root.'/Api/Helpers/employee_payroll_helper.php';
    require $root.'/Api/Helpers/dtr_classification.php';
    require $root.'/Api/Helpers/attendance_pdo.php';
    require $root.'/Api/Helpers/client_billing_helper.php';
    $pdo=attendance_pdo();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn()!==$testDb) throw new RuntimeException('PDO isolation assertion failed.');
    $sourceContext=null;$daily=1000.0;$hourly=125.0;$rawRates=['daily_rate'=>1000,'hourly_rate'=>125,'BasicSalary'=>26000,'monthly_salary_credit'=>26000];
    if($employeeInput){
        require $root.'/Api/Helpers/employee_payroll_rates.php';
        require_once __DIR__.'/Api/_selection_context.php';
        $sourceSite=testing_lead_site($live,$employeeInput);
        require_once __DIR__.'/Api/_selection_context.php';
        $sourceDeployment=testing_employee_context($live,$employeeInput,(int)$employeeInput['employee_id'],$employeeInput['date']);
        if(!$sourceDeployment||(int)$sourceDeployment['DeploymentID']!==(int)$employeeInput['deployment_id']||(int)$sourceSite['CompanyID']!==(int)$employeeInput['company_id'])throw new RuntimeException('The selected production deployment context has changed. Reload the rate card.');
        $rawRates=site_query($live,'SELECT daily_rate,hourly_rate,BasicSalary,monthly_salary_credit FROM employee WHERE EmployeeID=?','i',[(int)$employeeInput['employee_id']])->fetch_assoc();
        $resolvedRates=payroll_employee_rates($rawRates?:[],true);
        if(!$resolvedRates['valid'])throw new RuntimeException('No valid payroll rate was found for this employee. Check employee.daily_rate, hourly_rate and BasicSalary.');
        $daily=$resolvedRates['daily_rate'];$hourly=$resolvedRates['hourly_rate'];
        $sourceSchedule=payroll_resolve_deployment_schedule($live,(int)$employeeInput['employee_id'],$employeeInput['date']);
        $sourcePeriod=payroll_type_period_for_company($live,(int)$sourceSite['CompanyID'],$employeeInput['date']);
        $sourceContext=['employee_id'=>(int)$employeeInput['employee_id'],'deployment_id'=>(int)$sourceDeployment['DeploymentID'],'company_id'=>(int)$sourceSite['CompanyID'],'site_id'=>(int)$sourceSite['SiteID'],'team_lead_id'=>(int)$employeeInput['team_lead_id'],'payroll_type'=>$sourceSite['PayrollType'],'rates'=>$resolvedRates,'schedule'=>$sourceSchedule,'period'=>$sourcePeriod];
    }
    $admin=fixture_insert($db,'users',['RoleID'=>1,'FullName'=>'TEST Holiday Admin','Email'=>'holiday-admin@example.invalid','Password'=>'TEST-DISABLED','IsActive'=>1]);
    $accounting=fixture_insert($db,'users',['RoleID'=>3,'FullName'=>'TEST Holiday Accounting','Email'=>'holiday-accounting@example.invalid','Password'=>'TEST-DISABLED','IsActive'=>1]);
    $adminSession=fixture_session($root.'/sessions',$admin,'Admin');
    $accountingSession=fixture_session($root.'/sessions',$accounting,'Accounting');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes);
    if (!is_resource($server)) throw new RuntimeException('Could not start the isolated PHP server.');
    fclose($pipes[0]);usleep(300000);$base='http://'.$address;
    // Expected amounts are fixed acceptance fixtures from the user's requirements, not a second calculator.
    $cases=[
        ['A','Normal Day 8h',null,8,0,false,null,1000],
        ['B','Regular Holiday 8h','Regular Holiday',8,0,false,null,2000],
        ['C','Regular Holiday 8h + 2h OT','Regular Holiday',10,2,false,null,2650],
        ['D','Special Holiday 8h','Special Non-working Day',8,0,false,null,1300],
        ['E','Special Holiday 8h + 2h OT','Special Non-working Day',10,2,false,null,1722.50],
        ['F','Regular Holiday + Rest Day 8h','Regular Holiday',8,0,true,null,2600],
        ['G','Special Holiday + Rest Day 8h','Special Non-working Day',8,0,true,null,1500],
        ['H','Double Regular Holiday 8h','double',8,0,false,null,3000],
        ['I','Unworked Regular Holiday Eligible','Regular Holiday',0,0,false,true,1000],
        ['J','Unworked Regular Holiday Ineligible','Regular Holiday',0,0,false,false,0],
        ['K','Unworked Special Holiday','Special Non-working Day',0,0,false,null,0],
        ['L','Eligibility skips preceding holiday and rest days','Regular Holiday',0,0,false,'skip',1000],
        ['M','Unworked Double Regular Holiday Eligible','double',0,0,false,true,2000],
    ];
    $rows=[];$date='2026-10-01';$start='2026-10-01';$end='2026-10-15';
    if($employeeInput){$cases=array_values(array_filter($cases,static fn($case)=>$case[0]===$employeeInput['scenario']));$date=$employeeInput['date'];$start=$sourcePeriod['start'];$end=$sourcePeriod['end'];}
    foreach ($cases as [$id,$label,$type,$hours,$ot,$rest,$eligible,$expected]) {
        $db->query('UPDATE attendance SET HolidayID=NULL'); // Previous case evidence is already captured below.
        $db->query('UPDATE payroll SET HolidayID=NULL');
        $db->query('DELETE FROM holidays'); // This disposable schema contains TEST holidays only.
        $lead=fixture_insert($db,'users',['RoleID'=>4,'FullName'=>'TEST Holiday Lead '.$id,'Email'=>'holiday-lead-'.$id.'@example.invalid','Password'=>'TEST-DISABLED','IsActive'=>1]);
        $leadSession=fixture_session($root.'/sessions',$lead,'TeamLead');
        $company=fixture_insert($db,'clientcompany',['Name'=>'TEST Holiday Company '.$id,'Address'=>'TEST isolated work site','UserID'=>$lead,'PayrollType'=>$sourceContext['payroll_type']??'A','IsActive'=>1]);
        $site=fixture_insert($db,'deployment_sites',['CompanyID'=>$company,'Name'=>'TEST Holiday Site '.$id,'Address'=>'TEST isolated site','TeamLeadID'=>$lead,'IsActive'=>1]);
        $employee=fixture_insert($db,'employee',['FirstName'=>'TEST Holiday','LastName'=>$id,'Email'=>'holiday-'.$id.'@example.invalid','EmployeeNumber'=>'TEST-HOL-'.$id,'Position'=>'TEST','IsActive'=>1,'daily_rate'=>$rawRates['daily_rate'],'hourly_rate'=>$rawRates['hourly_rate'],'BasicSalary'=>$rawRates['BasicSalary'],'monthly_salary_credit'=>$rawRates['monthly_salary_credit'],'allowance'=>0]);
        $employeeUser=fixture_insert($db,'users',['RoleID'=>6,'FullName'=>'TEST Holiday Employee '.$id,'Email'=>'holiday-'.$id.'@example.invalid','Password'=>'TEST-DISABLED','IsActive'=>1]);
        $employeeSession=fixture_session($root.'/sessions',$employeeUser,'Employee');
        $days=$rest?'Mon,Tue,Wed,Fri':'Mon-Fri';
        if ($eligible==='skip') $days='Mon,Tue,Thu,Fri';
        $normalIn='08:00';$normalOut='17:00';$otOut='19:00';$break=60;
        if($sourceContext){
            $days=$sourceSchedule['working_days'];$break=(int)$sourceSchedule['break_duration'];
            $scheduled=payroll_is_scheduled_day($date,payroll_scheduled_weekdays($days));
            if($rest===$scheduled)throw new RuntimeException('The scenario rest-day classification does not match the selected employee schedule. Select a matching date/scenario.');
            $normalIn=substr($sourceSchedule['time_in'],0,5);
            $normalOut=date('H:i',strtotime('2000-01-01 '.$normalIn)+(8*60+$break)*60);$otOut=date('H:i',strtotime('2000-01-01 '.$normalOut)+7200);
        }
        $deployment=fixture_insert($db,'deployment',['EmployeeID'=>$employee,'CompanyID'=>$company,'SiteID'=>$site,'SiteSuperVisorID'=>$lead,'StartDate'=>'2026-08-01','StatusID'=>5,'AllowDTRSubmission'=>1,'TimeIn'=>$normalIn,'TimeOut'=>$sourceContext?substr($sourceSchedule['time_out'],0,5):$normalOut,'BreakDuration'=>$break,'GracePeriod'=>$sourceContext?(int)$sourceSchedule['grace_period']:5,'WorkingDays'=>$days]);
        if($verifyFailureCleanup)throw new RuntimeException('Intentional CLI-only fixture failure to verify finally cleanup.');
        if ($id==='A' && !$sourceContext) {
            $sameBranch=fixture_insert($db,'deployment_sites',['CompanyID'=>$company,'Name'=>'TEST second branch','Address'=>'TEST','TeamLeadID'=>$lead,'IsActive'=>1]);
            $otherLead=fixture_insert($db,'users',['RoleID'=>4,'FullName'=>'TEST Other Lead','Email'=>'other-lead@example.invalid','Password'=>'TEST-DISABLED','IsActive'=>1]);
            $otherBranch=fixture_insert($db,'deployment_sites',['CompanyID'=>$company,'Name'=>'TEST other lead branch','Address'=>'TEST','TeamLeadID'=>$otherLead,'IsActive'=>1]);
            $inactiveBranch=fixture_insert($db,'deployment_sites',['CompanyID'=>$company,'Name'=>'TEST inactive branch','Address'=>'TEST','TeamLeadID'=>$lead,'IsActive'=>0]);
            $branchEmployee=fixture_insert($db,'employee',['FirstName'=>'TEST','LastName'=>'Branch','EmployeeNumber'=>'TEST-BRANCH','IsActive'=>1]);
            $branchDeployment=fixture_insert($db,'deployment',['EmployeeID'=>$branchEmployee,'CompanyID'=>$company,'SiteID'=>$sameBranch,'SiteSuperVisorID'=>$otherLead,'StartDate'=>'2026-08-01','StatusID'=>5]);
            foreach (['ended','future','inactive'] as $invalid) {
                $badEmployee=fixture_insert($db,'employee',['FirstName'=>'TEST','LastName'=>$invalid,'EmployeeNumber'=>'TEST-'.$invalid,'IsActive'=>$invalid==='inactive'?0:1]);
                fixture_insert($db,'deployment',['EmployeeID'=>$badEmployee,'CompanyID'=>$company,'SiteID'=>$site,'SiteSuperVisorID'=>$lead,'StartDate'=>$invalid==='future'?'2099-01-01':'2026-08-01','EndDate'=>$invalid==='ended'?'2026-09-01':null,'StatusID'=>5]);
            }
            $query='?company_id='.$company.'&site_id='.$site.'&team_lead_id='.$lead;
            $selectedLeads=fixture_request($base.'/Testing/Attendance/Api/get_teamleads.php'.$query,$adminSession)['team_leads'];
            $secondLeads=fixture_request($base.'/Testing/Attendance/Api/get_teamleads.php?company_id='.$company.'&site_id='.$sameBranch,$adminSession)['team_leads'];
            $otherLeads=fixture_request($base.'/Testing/Attendance/Api/get_teamleads.php?company_id='.$company.'&site_id='.$otherBranch,$adminSession)['team_leads'];
            $selectedEmployees=fixture_request($base.'/Testing/Attendance/Api/get_employees.php'.$query,$adminSession)['employees'];
            $secondEmployees=fixture_request($base.'/Testing/Attendance/Api/get_employees.php?company_id='.$company.'&site_id='.$sameBranch.'&team_lead_id='.$lead,$adminSession)['employees'];
            $activeSites=fixture_request($base.'/Testing/Attendance/Api/get_sites.php?company_id='.$company,$adminSession)['sites'];
            $missingRate=fixture_request($base.'/Testing/Attendance/Api/get_employee_payroll_context.php?company_id='.$company.'&site_id='.$sameBranch.'&team_lead_id='.$lead.'&employee_id='.$branchEmployee.'&deployment_id='.$branchDeployment.'&date='.$date,$adminSession);
            $selectionChecks=[
                'missing_rate_rejected_without_fallback'=>!$missingRate['rates']['valid']&&str_contains($missingRate['message'],'No valid payroll rate'),
                'assigned_lead_without_employee_dependency'=>(int)$selectedLeads[0]['team_lead_id']===$lead,
                'same_lead_multiple_branches'=>(int)$secondLeads[0]['team_lead_id']===$lead,
                'different_branch_different_lead'=>(int)$otherLeads[0]['team_lead_id']===$otherLead,
                'inactive_site_excluded'=>!in_array($inactiveBranch,array_map('intval',array_column($activeSites,'site_id')),true),
                'ended_future_inactive_employees_excluded'=>array_map('intval',array_column($selectedEmployees,'employee_id'))===[$employee],
                'branch_employee_isolation'=>array_map('intval',array_column($secondEmployees,'employee_id'))===[$branchEmployee]
            ];
            if(in_array(false,$selectionChecks,true))throw new RuntimeException('Selector regression failed: '.json_encode($selectionChecks));
        }
        if ($type) {
            $holidayType=$type==='double'?'Regular Holiday':$type;
            $holiday=holidays_create($db,['name'=>'TEST '.$id.' holiday','date'=>$date,'type'=>$holidayType,'pay_multiplier'=>$holidayType==='Regular Holiday'?2:1.3,'is_nationwide'=>true,'description'=>'Disposable E2E fixture only']);
            if (empty($holiday['success']) && empty($holiday['ok']) && empty($holiday['id'])) throw new RuntimeException('Holiday fixture: '.json_encode($holiday));
            if ($type==='double') holidays_create($db,['name'=>'TEST '.$id.' second holiday','date'=>$date,'type'=>'Regular Holiday','pay_multiplier'=>2,'is_nationwide'=>true]);
        }
        if ($eligible===true || $eligible==='skip') {
            $previous=$eligible==='skip'?'2026-09-28':'2026-09-30';
            if ($eligible==='skip') holidays_create($db,['name'=>'TEST preceding non-working holiday','date'=>'2026-09-29','type'=>'Regular Holiday','pay_multiplier'=>2,'is_nationwide'=>true]);
            fixture_request($base.'/Testing/Attendance/Api/save_attendance.php',$adminSession,['company_id'=>$company,'site_id'=>$site,'team_lead_id'=>$lead,'deployment_id'=>$deployment,'employee_id'=>$employee,'date'=>$previous,'time_in'=>'08:00','time_out'=>'17:00','remarks'=>'TEST preceding attendance']);
        }
        if ($eligible===false) fixture_insert($db,'daily_time_records',['employee_id'=>$employee,'date'=>'2026-09-30','status'=>'Absent']);
        if ($hours) fixture_request($base.'/Testing/Attendance/Api/save_attendance.php',$adminSession,['company_id'=>$company,'site_id'=>$site,'team_lead_id'=>$lead,'deployment_id'=>$deployment,'employee_id'=>$employee,'date'=>$date,'time_in'=>$normalIn,'time_out'=>$ot?$otOut:$normalOut,'remarks'=>'TEST '.$id.' controlled attendance']);
        if ($ot) fixture_request($base.'/Testing/Attendance/Api/save_test_overtime.php',$adminSession,['action'=>'create','company_id'=>$company,'site_id'=>$site,'team_lead_id'=>$lead,'deployment_id'=>$deployment,'employee_id'=>$employee,'date'=>$date,'ot_start'=>$normalOut,'ot_end'=>$otOut]);
        $payroll=payroll_compute_period($db,$pdo,$employee,$daily,$hourly,0,(float)$rawRates['monthly_salary_credit'],$start,$end,null,null,$deployment);
        $classification=dtr_classification_from_payroll($payroll);
        $review=fixture_request($base.'/Api/TeamLead/get_dtr_cutoff_review.php?site_id='.$site.'&cutoff_ref='.$date,$leadSession);
        $reviewEmployee=$review['employees'][0]??[];
        fixture_request($base.'/Api/TeamLead/submit_dtr.php',$leadSession,['employee_ids'=>[$employee],'site_id'=>$site,'cutoff_start'=>$start,'cutoff_end'=>$end]);
        $account=fixture_request($base.'/Api/Accounting/get_submitted_dtrs.php?employee_id='.$employee.'&cutoff_start='.$start.'&cutoff_end='.$end,$accountingSession);
        $verification=fixture_request($base.'/Api/Accounting/verify_dtr_submission.php',$accountingSession,['tl_id'=>$lead,'site_id'=>$site,'cutoff_start'=>$start,'cutoff_end'=>$end]);
        $run=fixture_request($base.'/Api/Accounting/compute_payroll_for_batch.php',$accountingSession,['tl_id'=>$lead,'site_id'=>$site,'cutoff_start'=>$start,'cutoff_end'=>$end]);
        $billing=client_billing_build_export($db,$run['run_id']);
        $billingRow=$billing['rows'][0]??[];
        $exportRow=fixture_insert($db,'payroll_billing_exports',['PayrollRunID'=>$billing['run']['PayrollRunID'],'ExportToken'=>bin2hex(random_bytes(32)),'BaselineHash'=>hash('sha256',json_encode($billing)),'SnapshotJson'=>json_encode($billing),'FilePath'=>'fixture-reviewed.xlsx','CreatedBy'=>$accounting]);
        $finalized=fixture_request($base.'/Api/Accounting/finalize_payroll_batch.php',$accountingSession,['tl_id'=>$lead,'site_id'=>$site,'cutoff_start'=>$start,'cutoff_end'=>$end,'export_id'=>$exportRow,'billing_reviewed'=>true]);
        $storedPayroll=$db->query("SELECT BasicSalary,HolidayPay,OvertimePay,GrossPay FROM payroll WHERE EmployeeID=$employee AND PayPeriodStart='$start' AND PayPeriodEnd='$end'")->fetch_assoc();
        $employeeResponse=fixture_request($base.'/Api/Employee/get_employee_payroll.php',$employeeSession);
        $payslipPeriodMatches=($employeeResponse['period']['start']??null)===$start && ($employeeResponse['period']['end']??null)===$end;
        $e=$payroll['earnings'];$actual=(float)$payroll['gross_pay'];
        $wantedDuty=$type && $hours?8:0;
        $expectedPremium=['A'=>0,'B'=>1000,'C'=>1000,'D'=>300,'E'=>300,'F'=>1600,'G'=>500,'H'=>2000,'I'=>1000,'J'=>0,'K'=>0,'L'=>1000,'M'=>2000][$id];
        $expectedOt=['C'=>650,'E'=>422.50][$id]??0;
        if($sourceContext){
            // Independent normalized A-K acceptance references; never fed into the payroll engine.
            $expectedPremium=round($expectedPremium*$daily/1000,2);$expectedOt=round($expectedOt*$hourly/125,2);
            $expected=round(($hours?$daily:0)+$expectedPremium+$expectedOt,2);
        }
        $attendance=$db->query("SELECT HolidayID,IsHolidayWork,HolidayPayRate,TotalHours,IsRestDay FROM attendance WHERE EmployeeID=$employee AND Date='$date'")->fetch_assoc();
        $checks=[
            'economic_total'=>abs($actual-$expected)<0.005,
            'holiday_hours'=>$classification['holiday_work_hours']==$wantedDuty,
            'holiday_ot_hours'=>$classification['holiday_overtime_hours']==$ot,
            'ordinary_ot_not_duplicated'=>$classification['ordinary_overtime_hours']==0,
            'team_lead_hours'=>($reviewEmployee['holiday_pay_hours']??null)===$classification['holiday_work_hours'] || (float)($reviewEmployee['holiday_pay_hours']??-1)==$classification['holiday_work_hours'],
            'team_lead_overtime'=>(float)($reviewEmployee['total_overtime_hours']??-1)==(float)$classification['overtime_hours'],
            'accounting_snapshot'=>($account['submission']['classification']??[])==$classification,
            'accounting_verified'=>($verification['verified_count']??0)==1,
            'accounting_rate'=>abs((float)($billingRow['daily_rate']??-1)-$daily)<0.0001,
            'accounting_basic'=>(float)($billingRow['basic_total']??-1)==(float)$e['basic_pay'],
            'accounting_holiday'=>(float)($billingRow['holiday_duty']??0)+(float)($billingRow['special_holiday_pay']??0)==(float)$e['holiday_pay'],
            'accounting_holiday_ot'=>(float)($billingRow['holiday_ot_amount']??0)+(float)($billingRow['special_holiday_ot_amount']??0)==(float)$e['holiday_ot_pay'],
            'accounting_economic_total'=>abs((float)($billingRow['gross_payroll']??-1)-$actual)<0.005,
            'basic_component'=>(float)$e['basic_pay']===($hours?$daily:0.0),
            'holiday_component'=>abs((float)$e['holiday_pay']-$expectedPremium)<0.005,
            'holiday_ot_component'=>abs((float)$e['holiday_ot_pay']-$expectedOt)<0.005,
            'holiday_type'=>!$wantedDuty || ($classification['holiday_breakdown'][0]['type']??'')===($type==='double'?'Regular Holiday':$type),
            'rest_day_context'=>!$wantedDuty || ($classification['holiday_breakdown'][0]['is_rest_day']??null)===$rest,
            'attendance_holiday_metadata'=>!$hours || (!$type ? empty($attendance['HolidayID']) : !empty($attendance['HolidayID']) && (int)$attendance['IsHolidayWork']===1),
            'finalized_payroll'=>count($finalized['finalized']??[])===1 && empty($finalized['errors']),
            'stored_gross'=>abs((float)($storedPayroll['GrossPay']??-1)-$actual)<0.005,
            'stored_holiday'=>abs((float)($storedPayroll['HolidayPay']??-1)-$expectedPremium)<0.005,
            'stored_overtime'=>abs((float)($storedPayroll['OvertimePay']??-1)-$expectedOt)<0.005,
            'employee_payroll_when_cutoff_matches'=>!$payslipPeriodMatches || abs((float)($employeeResponse['payroll']['gross_pay']??-1)-$actual)<0.005,
            'double_counting'=>abs($actual-($e['basic_pay']+$e['holiday_pay']+$e['holiday_ot_pay']))<0.005,
        ];
        $rows[]=['id'=>$id,'scenario'=>$label,'holiday_type'=>$type,'daily_rate'=>$daily,'hourly_rate'=>$hourly,'reference_earnings'=>['basic_pay'=>$hours?$daily:0,'holiday_pay'=>$expectedPremium,'holiday_ot_pay'=>$expectedOt],'hours_worked'=>$hours,'holiday_ot_hours'=>$ot,'rest_day'=>$rest,'eligibility'=>$eligible,'expected'=>$expected,'actual'=>$actual,'difference'=>round($actual-$expected,2),'status'=>in_array(false,$checks,true)?'FAIL':'PASS','checks'=>$checks,'earnings'=>$e,'classification'=>$classification,'attendance'=>$attendance,'team_lead'=>$reviewEmployee,'accounting_submission'=>$account['submission'],'accounting_payroll'=>$billingRow,'stored_payroll'=>$storedPayroll,'payslip_status'=>$payslipPeriodMatches?'VERIFIED current cutoff':'NOT VERIFIED: employee endpoint serves a different current cutoff','payslip'=>$payslipPeriodMatches?($employeeResponse['payslip']??null):null,'run'=>$run];
    }
    $guardChecks=[];
    if (!$sourceContext) {
        $compute=static fn()=>payroll_compute_period($db,$pdo,$employee,$daily,$hourly,0,(float)$rawRates['monthly_salary_credit'],$start,$end,null,null,$deployment);
        $mustReview=static function(callable $operation,string $message): bool {
            try {$operation();return false;} catch(InvalidArgumentException $error) {return str_contains($error->getMessage(),$message);}
        };
        $db->query("DELETE FROM daily_time_records WHERE employee_id=$employee");
        $guardChecks['missing_preceding_attendance_requires_review']=$mustReview($compute,'verify attendance');
        $approved=(int)$db->query("SELECT StatusID FROM status WHERE Name='Approved' LIMIT 1")->fetch_row()[0];
        fixture_insert($db,'leaves',['EmployeeID'=>$employee,'StartDate'=>'2026-09-30','EndDate'=>'2026-09-30','LeaveType'=>'Paid leave','StatusID'=>$approved]);
        $guardChecks['approved_leave_needs_paid_status_review']=$mustReview($compute,'confirm whether approved leave');
        $db->query("DELETE FROM leaves WHERE EmployeeID=$employee");
        // CLI-only invalid-evidence fixtures after finalization: public testing APIs correctly reject these locked writes.
        fixture_insert($db,'daily_time_records',['employee_id'=>$employee,'date'=>$date,'time_in'=>$date.' 08:00:00','time_out'=>$date.' 17:00:00','status'=>'Clocked Out']);
        $db->query("UPDATE daily_time_records SET time_out=NULL WHERE employee_id=$employee AND date='$date'");
        $guardChecks['incomplete_holiday_attendance_requires_review']=$mustReview($compute,'clock-out');
        $db->query("UPDATE daily_time_records SET time_out='$date 17:00:00' WHERE employee_id=$employee AND date='$date'");
        fixture_insert($db,'overtime',['EmployeeID'=>$employee,'DeploymentID'=>$deployment,'OvertimeDate'=>$date,'OTStartTime'=>'17:00','OTEndTime'=>'19:00','OvertimeHours'=>2,'RequestedBy'=>$admin,'ApprovedBy'=>$admin,'StatusID'=>$approved,'Remarks'=>'CLI-only disposable review guard fixture']);
        $guardChecks['unconfirmed_double_holiday_overtime_requires_review']=$mustReview($compute,'not confirmed');
        $db->query("UPDATE overtime SET StatusID=(SELECT StatusID FROM status WHERE Name='Pending' LIMIT 1) WHERE EmployeeID=$employee");
        $unapproved=$compute();
        $guardChecks['unapproved_holiday_overtime_not_paid']=(float)$unapproved['earnings']['holiday_ot_pay']===0.0;
        $db->query("UPDATE daily_time_records SET time_in='$date 18:00:00',time_out='2026-10-02 03:00:00' WHERE employee_id=$employee AND date='$date'");
        $guardChecks['overnight_holiday_requires_date_allocation_review']=$mustReview($compute,'Overnight holiday');
        $db->query("UPDATE daily_time_records SET time_in='$date 08:00:00',time_out='$date 12:00:00' WHERE employee_id=$employee AND date='$date'");
        $partial=$compute();
        $guardChecks['partial_double_holiday_premium']=abs((float)$partial['earnings']['holiday_pay']-750.0)<0.005;
        $guardChecks['partial_double_holiday_total']=abs((float)$partial['earnings']['basic_pay']+(float)$partial['earnings']['holiday_pay']-(float)$partial['late_undertime_deduction']-1125.0)<0.02;
        $differentRate=payroll_compute_period($db,$pdo,$employee,800,100,0,20800,$start,$end,null,null,$deployment);
        $guardChecks['different_salary_rate_not_hardcoded']=abs((float)$differentRate['earnings']['holiday_pay']-600.0)<0.005;
        $db->query("UPDATE deployment SET WorkingDays='Mon,Tue,Wed,Fri' WHERE DeploymentID=$deployment");
        $guardChecks['double_holiday_rest_day_requires_confirmation']=$mustReview($compute,'confirmation of the client pay rule');
        $lockedPayslip=fixture_request($base.'/Api/Employee/get_employee_payroll.php',$employeeSession);
        $guardChecks['finalized_payslip_uses_stored_amount_after_review_guard']=!empty($lockedPayslip['payslip']['is_finalized']) && abs((float)$lockedPayslip['payslip']['gross_pay']-2000.0)<0.005;
        $db->query('DELETE FROM holidays ORDER BY HolidayID DESC LIMIT 1');
        $db->query("UPDATE overtime SET StatusID=$approved WHERE EmployeeID=$employee");
        $guardChecks['unconfirmed_rest_day_overtime_requires_review']=$mustReview($compute,'not confirmed');
        $db->query("UPDATE holidays SET HolidayType='Unclassified'");
        $guardChecks['ambiguous_holiday_type_requires_review']=$mustReview($compute,'classification');
        if(in_array(false,$guardChecks,true))throw new RuntimeException('Holiday review guards failed: '.json_encode($guardChecks));
    }
    $after=fixture_snapshot($live);
    $result=['success'=>true,'employee_context'=>$sourceContext,'mode'=>$sourceContext?'selected_employee':'synthetic','source'=>'Current production PHP helpers and HTTP endpoints; disposable schema only','fixture_database'=>$testDb,'fixture_directory'=>$root,'scenario_date'=>$date,'cutoff'=>[$start,$end],'selection_checks'=>$selectionChecks??[],'production_unchanged'=>$before===$after,'production_before'=>$before,'production_after'=>$after,'results'=>$rows,'production_files_sha256'=>[
        'employee_payroll_helper.php'=>hash_file('sha256',__DIR__.'/../../Api/Helpers/employee_payroll_helper.php'),
        'submit_dtr.php'=>hash_file('sha256',__DIR__.'/../../Api/TeamLead/submit_dtr.php'),
        'compute_payroll_for_batch.php'=>hash_file('sha256',__DIR__.'/../../Api/Accounting/compute_payroll_for_batch.php')]];
    $result['review_guard_checks']=$guardChecks;
} catch (Throwable $e) {
    error_log('[Holiday Testing] '.$e->getMessage());
    $result=['success'=>false,'message'=>$e->getMessage(),'stage'=>'controlled_scenario','partial_results'=>$rows??[],'fixture_database'=>$testDb,'fixture_directory'=>$root,'intentional_cleanup_test'=>$verifyFailureCleanup];
    if(isset($before)){$result['production_unchanged']=$before===fixture_snapshot($live);}
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    // Drop only the random schema created by THIS invocation, never the connected database.
    if ($created && $testDb!==$sourceDb && preg_match('/^codex_holiday_[a-f0-9]{12}$/D',$testDb)) {
        $live->query('DROP DATABASE `'.$testDb.'`'); $result['cleanup']='PASS: disposable database removed; no test records remain';
    }
    flock($lock,LOCK_UN);fclose($lock);
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
