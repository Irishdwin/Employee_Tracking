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
    $lead=fixture_insert($db,'users',['RoleID'=>4,'FullName'=>'TEST Late DTR Lead','Email'=>'late-dtr-lead@example.invalid','Password'=>'DISABLED','IsActive'=>1]);
    $leadSession=fixture_session($root.'/sessions',$lead,'TeamLead');
    $company=fixture_insert($db,'clientcompany',['Name'=>'TEST Late DTR','Address'=>'TEST','UserID'=>$lead,'PayrollType'=>'A','IsActive'=>1]);
    $site=fixture_insert($db,'deployment_sites',['CompanyID'=>$company,'Name'=>'TEST','Address'=>'TEST','TeamLeadID'=>$lead,'IsActive'=>1]);
    $employee=fixture_insert($db,'employee',['FirstName'=>'TEST','LastName'=>'Late DTR','EmployeeNumber'=>'TEST-LATE-DTR','IsActive'=>1,'daily_rate'=>1000,'hourly_rate'=>125,'monthly_salary_credit'=>26000]);
    $date=(new DateTimeImmutable('first day of previous month'))->format('Y-m-d');
    $period=payroll_type_period_for_company($db,$company,$date);
    $deployment=fixture_insert($db,'deployment',['EmployeeID'=>$employee,'CompanyID'=>$company,'SiteID'=>$site,'SiteSuperVisorID'=>$lead,'StartDate'=>$date,'StatusID'=>5,'AllowDTRSubmission'=>1,'TimeIn'=>'08:00','TimeOut'=>'17:00','BreakDuration'=>60,'WorkingDays'=>'Mon-Sun']);
    fixture_request($base.'/Testing/Attendance/Api/save_attendance.php',$adminSession,['company_id'=>$company,'site_id'=>$site,'team_lead_id'=>$lead,'deployment_id'=>$deployment,'employee_id'=>$employee,'date'=>$date,'time_in'=>'08:00','time_out'=>'17:00']);
    $review=fixture_request($base.'/Api/TeamLead/get_dtr_cutoff_review.php?site_id='.$site.'&cutoff_ref='.$date,$leadSession);
    $obligations=site_dtr_obligations($db,$lead);
    $remindersCorrect=count($obligations)>0;foreach($obligations as $obligation){$remindersCorrect=$remindersCorrect && $obligation['reminder_date']===(new DateTimeImmutable($obligation['period']['end']))->modify('+1 day')->format('Y-m-d');}
    $submitted=fixture_request($base.'/Api/TeamLead/submit_dtr.php',$leadSession,['site_id'=>$site,'employee_ids'=>[$employee],'cutoff_start'=>$period['start'],'cutoff_end'=>$period['end']]);
    $account=fixture_request($base.'/Api/Accounting/get_submitted_dtrs.php?employee_id='.$employee.'&cutoff_start='.$period['start'].'&cutoff_end='.$period['end'],$accountingSession);
    $repeat=fixture_request($base.'/Api/TeamLead/submit_dtr.php',$leadSession,['site_id'=>$site,'employee_ids'=>[$employee],'cutoff_start'=>$period['start'],'cutoff_end'=>$period['end']]);
    $checks=['reminders_start_after_cutoff'=>$remindersCorrect,'overdue_warning'=>$review['is_overdue'] && !empty($review['submission_warning']),'overdue_hours'=>(float)$review['employees'][0]['total_regular_hours']===8.0,'overdue_submission_allowed'=>in_array($employee,$submitted['submitted'],true),'accounting_receives'=>$account['submission']!==null,'duplicate_prevented'=>count($repeat['submitted'])===0,'no_auto_payroll'=>(int)$db->query('SELECT COUNT(*) FROM payroll')->fetch_row()[0]===0,'production_unchanged'=>$before===fixture_snapshot($live)];
    if(in_array(false,$checks,true))throw new RuntimeException(json_encode($checks));
    $result=['success'=>true,'checks'=>$checks,'cutoff'=>[$period['start'],$period['end']]];
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
