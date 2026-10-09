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
        $sourceSite=site_for_lead($live,(int)$employeeInput['team_lead_id'],(int)$employeeInput['site_id']);
        $sourceDeployment=site_owned_employee($live,(int)$employeeInput['team_lead_id'],(int)$employeeInput['employee_id'],(int)$employeeInput['site_id']);
        if(!$sourceDeployment||(int)$sourceDeployment['DeploymentID']!==(int)$employeeInput['deployment_id']||(int)$sourceSite['CompanyID']!==(int)$employeeInput['company_id'])throw new RuntimeException('The selected production deployment context has changed. Reload the rate card.');
        $rawRates=site_query($live,'SELECT daily_rate,hourly_rate,BasicSalary,monthly_salary_credit FROM employee WHERE EmployeeID=? AND IsActive=1','i',[(int)$employeeInput['employee_id']])->fetch_assoc();
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

    $checks=[];
    function check_history(string $name,bool $ok): void {global $checks;$checks[$name]=$ok;if(!$ok)throw new RuntimeException('Failed: '.$name);}
    function history_http(string $base,string $session,string $file,array $input,bool $post=false): array {
      $url=$base.'/Testing/Attendance/Api/'.$file.($post?'':'?'.http_build_query($input));
      $ctx=stream_context_create(['http'=>['method'=>$post?'POST':'GET','ignore_errors'=>true,'timeout'=>30,'header'=>"Cookie: PHPSESSID=$session\r\nContent-Type: application/json\r\n",'content'=>$post?json_encode($input):'']]);
      $json=json_decode((string)file_get_contents($url,false,$ctx),true);
      if(!is_array($json))throw new RuntimeException('Non-JSON response: '.$file);return $json;
    }
    $lead=fixture_insert($db,'users',['RoleID'=>4,'FullName'=>'TEST History Lead','Email'=>'history-lead@example.invalid','Password'=>'TEST-DISABLED','IsActive'=>1]);
    $company=fixture_insert($db,'clientcompany',['Name'=>'TEST History Company','Address'=>'TEST site','UserID'=>$lead,'PayrollType'=>'B','IsActive'=>1]);
    $oldSite=fixture_insert($db,'deployment_sites',['CompanyID'=>$company,'Name'=>'TEST previous site','Address'=>'TEST old address','TeamLeadID'=>$lead,'IsActive'=>1]);
    $site=fixture_insert($db,'deployment_sites',['CompanyID'=>$company,'Name'=>'TEST current site','Address'=>'TEST new address','TeamLeadID'=>$lead,'IsActive'=>1]);
    $employee=fixture_insert($db,'employee',['FirstName'=>'TEST Historical','LastName'=>'Employee','Email'=>'history-employee@example.invalid','EmployeeNumber'=>'TEST-HISTORY','IsActive'=>1,'daily_rate'=>1000,'hourly_rate'=>125,'BasicSalary'=>26000,'monthly_salary_credit'=>26000]);
    $day=fn(string $relative)=>date('Y-m-d',strtotime($relative));
    $old=fixture_insert($db,'deployment',['EmployeeID'=>$employee,'CompanyID'=>$company,'SiteID'=>$oldSite,'SiteSuperVisorID'=>$lead,'StartDate'=>$day('-100 days'),'EndDate'=>$day('-15 days'),'StatusID'=>5,'TimeIn'=>'09:00','TimeOut'=>'18:00','BreakDuration'=>60,'GracePeriod'=>10,'WorkingDays'=>'Mon,Tue,Wed,Thu,Fri,Sat,Sun']);
    $new=fixture_insert($db,'deployment',['EmployeeID'=>$employee,'CompanyID'=>$company,'SiteID'=>$site,'SiteSuperVisorID'=>$lead,'StartDate'=>$day('-14 days'),'StatusID'=>5,'TimeIn'=>'08:00','TimeOut'=>'17:00','BreakDuration'=>60,'GracePeriod'=>5,'WorkingDays'=>'Mon,Tue,Wed,Thu,Fri,Sat,Sun']);
    $preview=function(string $date)use($base,$adminSession,$employee){return history_http($base,$adminSession,'get_employee_payroll_context.php',['resolve'=>1,'employee_id'=>$employee,'date'=>$date]);};
    $save=function(string $date,array $extra=[])use($preview,$base,$adminSession,$employee,$old){$p=$preview($date);if(empty($p['success']))return $p;return history_http($base,$adminSession,'save_attendance.php',array_merge($p['context'],['action'=>'create','employee_id'=>$employee,'date'=>$date,'time_in'=>(int)$p['context']['deployment_id']===$old?'09:00':'08:00','time_out'=>(int)$p['context']['deployment_id']===$old?'18:00':'17:00'],$extra),true);};
    foreach(['yesterday'=>'-1 day','last_week'=>'-7 days','last_month'=>'-1 month','previous_cutoff'=>'-40 days'] as $name=>$relative){$date=$day($relative);$x=$save($date);check_history('insert_'.$name,!empty($x['success']));}
    $p=$preview($day('-1 month'));check_history('previous_deployment_site',(int)$p['context']['deployment_id']===$old&&(int)$p['context']['site_id']===$oldSite);
    $context=$p['context'];
    $x=history_http($base,$adminSession,'get_employees.php',array_merge($context,['history'=>1]));check_history('historical_employee_selectable',in_array($employee,array_map('intval',array_column($x['employees'],'employee_id')),true));
    foreach(['regular'=>'Regular Holiday','special'=>'Special Non-working Day'] as $name=>$type){$date=$day($name==='regular'?'-20 days':'-21 days');holidays_create($db,['name'=>'TEST past '.$name,'date'=>$date,'type'=>$type,'pay_multiplier'=>$name==='regular'?2:1.3,'is_nationwide'=>true]);$x=$save($date);check_history('insert_historical_'.$name.'_holiday',!empty($x['success']));$p=$preview($date);$h=history_http($base,$adminSession,'get_holiday_for_date.php',array_merge($p['context'],['employee_id'=>$employee,'date'=>$date]));check_history('resolve_historical_'.$name.'_holiday',count($h['holidays'])===1&&$h['payroll_period']['start']<=$date&&$h['payroll_period']['end']>=$date);}
    $entryDate=$day('-29 days');fixture_insert($db,'team_schedule_entries',['DeploymentID'=>$old,'EntryKind'=>'Dated','ScheduleDate'=>$entryDate,'ScheduleType'=>'Rotating','TimeIn'=>'22:00','TimeOut'=>'06:00','BreakDuration'=>30,'GracePeriod'=>7,'WorkingDays'=>'Mon,Tue,Wed,Thu,Fri,Sat,Sun','ShiftName'=>'TEST historic night','UpdatedBy'=>$lead]);
    $p=$preview($entryDate);$h=history_http($base,$adminSession,'get_holiday_for_date.php',array_merge($p['context'],['employee_id'=>$employee,'date'=>$entryDate]));check_history('dated_historical_schedule',$h['schedule']['time_in']==='22:00:00'&&(int)$h['schedule']['break_duration']===30&&(int)$h['schedule']['grace_period']===7);
    $x=$save($entryDate,['time_in'=>'22:00','time_out'=>'06:00']);check_history('overnight_historical_insert',!empty($x['success']));$att=$db->query('SELECT TimeIn,TimeOut,EvidenceDeploymentID FROM attendance WHERE AttendanceID='.(int)$x['attendance_id'])->fetch_assoc();check_history('overnight_next_day_and_evidence_deployment',substr($att['TimeOut'],0,10)===$day('-28 days')&&(int)$att['EvidenceDeploymentID']===$old);
    $nightHoliday=$day('-28 days');fixture_insert($db,'team_schedule_entries',['DeploymentID'=>$old,'EntryKind'=>'Dated','ScheduleDate'=>$nightHoliday,'ScheduleType'=>'Rotating','TimeIn'=>'22:00','TimeOut'=>'06:00','BreakDuration'=>30,'GracePeriod'=>7,'WorkingDays'=>'Mon,Tue,Wed,Thu,Fri,Sat,Sun','ShiftName'=>'TEST holiday night','UpdatedBy'=>$lead]);
    holidays_create($db,['name'=>'TEST overnight holiday','date'=>$nightHoliday,'type'=>'Regular Holiday','pay_multiplier'=>2,'is_nationwide'=>true]);
    $x=$save($nightHoliday,['time_in'=>'22:00','time_out'=>'06:00']);check_history('overnight_holiday_attendance_saved_for_review',!empty($x['success'])&&str_contains($x['payroll_review'],'Accounting review'));
    $reviewAtt=$db->query('SELECT HolidayID,IsHolidayWork,HolidayPayRate FROM attendance WHERE AttendanceID='.(int)$x['attendance_id'])->fetch_assoc();check_history('overnight_holiday_no_unconfirmed_pay',!empty($reviewAtt['HolidayID'])&&(int)$reviewAtt['IsHolidayWork']===1&&$reviewAtt['HolidayPayRate']===null);
    $context=$preview($day('-40 days'))['context'];$beforeRow=$db->query("SELECT * FROM attendance WHERE EmployeeID=$employee AND Date='".$day('-40 days')."'")->fetch_assoc();$x=$save($day('-40 days'),['time_in'=>'10:00']);$afterRow=$db->query("SELECT * FROM attendance WHERE EmployeeID=$employee AND Date='".$day('-40 days')."'")->fetch_assoc();check_history('duplicate_create_rejected_unchanged',empty($x['success'])&&str_contains($x['message'],'already exists')&&$beforeRow===$afterRow);
    $x=history_http($base,$adminSession,'save_attendance.php',array_merge($context,['action'=>'update','attendance_id'=>$beforeRow['AttendanceID'],'date'=>$day('-40 days'),'time_in'=>'09:30','time_out'=>'18:00']),true);check_history('explicit_edit_uses_historical_grace',!empty($x['success'])&&(int)$x['minutes_late']===30);
    $x=history_http($base,$adminSession,'save_attendance.php',array_merge($context,['employee_id'=>$employee,'date'=>$day('-101 days'),'time_in'=>'09:00','time_out'=>'18:00']),true);check_history('reject_before_deployment_start',empty($x['success'])&&str_contains($x['message'],'No deployment'));
    $db->query("UPDATE deployment SET EndDate='".$day('-1 day')."' WHERE DeploymentID=$new");$x=$preview(date('Y-m-d'));check_history('reject_after_deployment_end',empty($x['success'])&&str_contains($x['message'],'No deployment'));$db->query("UPDATE deployment SET EndDate=NULL WHERE DeploymentID=$new");
    $x=history_http($base,$adminSession,'save_attendance.php',array_merge($preview($day('-35 days'))['context'],['deployment_id'=>$new,'employee_id'=>$employee,'date'=>$day('-35 days'),'time_in'=>'09:00','time_out'=>'18:00']),true);check_history('reject_wrong_historical_deployment',empty($x['success'])&&str_contains($x['message'],'context'));
    $x=history_http($base,$accountingSession,'save_attendance.php',[],true);check_history('reject_non_admin',empty($x['success'])&&str_contains($x['message'],'Admin'));$x=history_http($base,'','get_employee_payroll_context.php',['resolve'=>1,'employee_id'=>$employee,'date'=>$day('-35 days')]);check_history('reject_anonymous',empty($x['success']));
    $submittedDate=$day('-5 days');fixture_insert($db,'dtr_submissions',['EmployeeID'=>$employee,'DeploymentID'=>$new,'CutoffStartDate'=>$submittedDate,'CutoffEndDate'=>$submittedDate,'SubmittedBy'=>$lead,'StatusID'=>(int)$db->query("SELECT StatusID FROM status WHERE Name='Approved' LIMIT 1")->fetch_row()[0]]);$x=$save($submittedDate);check_history('submitted_cutoff_prevents_missing_row_insertion',empty($x['success'])&&str_contains($x['message'],'submitted DTR'));check_history('locked_context_shown',$preview($submittedDate)['locked']);
    $finalDate=$day('-45 days');$finalized=(int)$db->query("SELECT StatusID FROM status WHERE Name='Finalized'")->fetch_row()[0];fixture_insert($db,'payroll',['EmployeeID'=>$employee,'PayPeriodStart'=>$finalDate,'PayPeriodEnd'=>$finalDate,'StatusID'=>$finalized,'PayslipGenerated'=>1]);$x=$save($finalDate);check_history('finalized_payroll_protected',empty($x['success'])&&str_contains($x['message'],'finalized payroll'));
    $linkedDate=$day('-40 days');$db->query("UPDATE attendance SET DTRSubmissionID=(SELECT DTR_SubmissionID FROM dtr_submissions LIMIT 1) WHERE EmployeeID=$employee AND Date='$linkedDate'");$x=history_http($base,$adminSession,'delete_attendance.php',array_merge($context,['attendance_id'=>$beforeRow['AttendanceID']]),true);check_history('submitted_attendance_delete_protected',empty($x['success'])&&str_contains($x['message'],'locked'));
    $p=$preview($day('-1 month'));$records=history_http($base,$adminSession,'get_employee_attendance_list.php',array_merge($p['context'],['employee_id'=>$employee,'date'=>$day('-1 month')]));check_history('historical_records_and_schedule_returned',!empty($records['success'])&&$records['schedule']['time_in']==='09:00'&&(int)$records['context']['deployment_id']===$old&&count($records['records'])>=4);
    check_history('test_changes_audited',(int)$db->query("SELECT COUNT(*) FROM system_logs WHERE module='Attendance Testing'")->fetch_row()[0]>=8);
    $db->query("UPDATE clientcompany SET IsActive=0 WHERE CompanyID=$company");$db->query("UPDATE deployment_sites SET IsActive=0 WHERE SiteID=$oldSite");$db->query("UPDATE employee SET IsActive=0 WHERE EmployeeID=$employee");
    $archivedDate=$day('-55 days');$p=$preview($archivedDate);check_history('archived_historical_context',!empty($p['success'])&&(int)$p['context']['site_id']===$oldSite);
    $x=$save($archivedDate);check_history('archived_historical_insert',!empty($x['success']));
    $x=history_http($base,$adminSession,'get_sites.php',['history'=>1,'company_id'=>$company]);check_history('archived_site_selectable',in_array($oldSite,array_map('intval',array_column($x['sites'],'site_id')),true));
    $x=history_http($base,$adminSession,'get_employees.php',array_merge($p['context'],['history'=>1]));check_history('inactive_historical_employee_selectable',in_array($employee,array_map('intval',array_column($x['employees'],'employee_id')),true));
    $after=fixture_snapshot($live);check_history('production_unchanged',$before===$after);
    $result=['success'=>true,'checks'=>$checks,'production_unchanged'=>$before===$after,'fixture_database'=>$testDb,'fixture_directory'=>$root,'production_before'=>$before,'production_after'=>$after];
} catch (Throwable $e) {
    error_log('[Holiday Testing] '.$e->getMessage());
    $result=['success'=>false,'message'=>$e->getMessage(),'stage'=>'controlled_scenario','partial_results'=>$checks??[],'fixture_database'=>$testDb,'fixture_directory'=>$root,'intentional_cleanup_test'=>$verifyFailureCleanup];
    if(isset($before)){$result['production_before']=$before;$result['production_after']=fixture_snapshot($live);$result['production_unchanged']=$before===$result['production_after'];}
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    // Drop only the random schema created by THIS invocation, never the connected database.
    if ($created && $testDb!==$sourceDb && preg_match('/^codex_holiday_[a-f0-9]{12}$/D',$testDb)) {
        $live->query('DROP DATABASE `'.$testDb.'`'); $result['cleanup']='PASS: disposable database removed; no test records remain';
    }
    flock($lock,LOCK_UN);fclose($lock);
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

