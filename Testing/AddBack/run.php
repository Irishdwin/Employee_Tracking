<?php
/** CLI-only production workflow tests. Only random disposable schema/files are mutated. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','stderr'); date_default_timezone_set('Asia/Manila');
require __DIR__.'/../../Api/Db_connection/db_connect.php';
$live=$conn; $sourceDb=$dbname; $testDb='codex_addback_'.bin2hex(random_bytes(6));
$root=sys_get_temp_dir().'/onazul-addback-'.bin2hex(random_bytes(8)); $server=null; $created=false; $result=['success'=>false];
function ab_copy(string $from,string $to): void {
    if (!is_dir($to)) mkdir($to,0700,true);
    foreach (new DirectoryIterator($from) as $f) { if ($f->isDot()) continue; $dest=$to.'/'.$f->getFilename(); if ($f->isDir()) ab_copy($f->getPathname(),$dest); else copy($f->getPathname(),$dest); }
}
function ab_insert(mysqli $db,string $table,array $row): int {
    $keys=implode(',',array_map(fn($key)=>'`'.$key.'`',array_keys($row)));
    $q=$db->prepare('INSERT INTO `'.$table.'`('.$keys.') VALUES('.implode(',',array_fill(0,count($row),'?')).')'); $values=array_values($row); $q->bind_param(str_repeat('s',count($values)),...$values); $q->execute(); return (int)$db->insert_id;
}
function ab_session(string $root,int $user,string $role): string {
    ini_set('session.save_path',$root.'/sessions'); ini_set('session.use_cookies','0'); ini_set('session.cache_limiter','');
    $id=bin2hex(random_bytes(16)); session_id($id); session_start(); $_SESSION=['user_id'=>$user,'role'=>$role,'last_activity'=>time()]; session_write_close(); return $id;
}
function ab_http(string $url,string $session,?array $body=null,int $expected=200): array {
    $ctx=stream_context_create(['http'=>['method'=>$body===null?'GET':'POST','timeout'=>40,'ignore_errors'=>true,'header'=>"Cookie: PHPSESSID=$session\r\nContent-Type: application/json\r\n",'content'=>$body===null?'':json_encode($body)]]);
    $text=file_get_contents($url,false,$ctx); preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m); $code=(int)($m[1]??0);
    $data=json_decode((string)$text,true);
    if ($code!==$expected || ($expected===200 && is_array($data) && empty($data['success']))) throw new RuntimeException('HTTP '.$code.' expected '.$expected.' at '.$url.': '.substr((string)$text,0,400));
    return $data??['bytes'=>strlen((string)$text),'status'=>$code];
}
function ab_assert(bool $pass,string $name): void { if (!$pass) throw new RuntimeException('Failed: '.$name); }
function ab_snapshot(mysqli $db): array { $out=[]; foreach (['employee','attendance','daily_time_records','dtr_submissions','payroll','payroll_adjustments','add_back_requests','add_back_evidence','notifications','salary_rates','holidays'] as $t) $out[$t]=$db->query('CHECKSUM TABLE `'.$t.'`')->fetch_assoc()['Checksum']; return $out; }
try {
    $before=ab_snapshot($live); $live->query('CREATE DATABASE `'.$testDb.'` CHARACTER SET utf8mb4'); $created=true;
    $db=new mysqli($servername,$username,$password,$testDb); $db->set_charset('utf8mb4'); $db->query("SET time_zone='+08:00'"); $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($live->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetch_all() as $table) $db->query($live->query('SHOW CREATE TABLE `'.$table[0].'`')->fetch_row()[1]);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    foreach (['roles','status'] as $t) foreach ($live->query('SELECT * FROM `'.$t.'`')->fetch_all(MYSQLI_ASSOC) as $row) ab_insert($db,$t,$row);
    ab_copy(__DIR__.'/../../Api',$root.'/Api'); mkdir($root.'/sessions',0700,true); mkdir($root.'/uploads/attendance_photos',0700,true); mkdir($root.'/uploads/add_back_evidence',0700,true);
    $path=$root.'/Api/Common/session_init.php'; file_put_contents($path,"<?php\nini_set('session.save_path',".var_export($root.'/sessions',true).");\n".substr(file_get_contents($path),5));
    $path=$root.'/Api/Db_connection/db_connect.php'; file_put_contents($path,str_replace("\$dbname = '$sourceDb';","\$dbname = '$testDb';",file_get_contents($path)));
    foreach (['attendance_pdo.php','hris_pdo.php'] as $file) { $path=$root.'/Api/Helpers/'.$file; file_put_contents($path,str_replace('dbname='.$sourceDb.';','dbname='.$testDb.';',file_get_contents($path))); }
    require $root.'/Api/Helpers/attendance_pdo.php'; require $root.'/Api/Helpers/add_back.php'; require $root.'/Api/Helpers/add_back_payroll.php'; require $root.'/Api/Helpers/client_billing_helper.php'; require $root.'/Api/Helpers/geofence_helper.php';
    $pdo=attendance_pdo(); ab_assert($pdo->query('SELECT DATABASE()')->fetchColumn()===$testDb,'PDO isolated');
    $accounting=ab_insert($db,'users',['RoleID'=>3,'FullName'=>'TEST Accounting','Email'=>'ab-accounting@example.invalid','Password'=>'TEST-DISABLED']);
    $lead=ab_insert($db,'users',['RoleID'=>4,'FullName'=>'TEST Lead','Email'=>'ab-lead@example.invalid','Password'=>'TEST-DISABLED']);
    $acctSession=ab_session($root,$accounting,'Accounting'); $leadSession=ab_session($root,$lead,'TeamLead');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); $address=stream_socket_get_name($socket,false); fclose($socket);
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes); fclose($pipes[0]); usleep(300000); $base='http://'.$address; $api=$base.'/Api/Common/add_back_requests.php';
    $image=imagecreatetruecolor(4,4); imagepng($image,$root.'/uploads/attendance_photos/test.png'); imagedestroy($image);
    $todayPeriod=payroll_kinsenas_period(date('Y-m-d')); $date=date('Y-m-d',strtotime($todayPeriod['start'].' -11 days')); $workPeriod=payroll_kinsenas_period($date);
    $results=[]; $security=[];
    foreach (['normal'=>250,'regular_holiday'=>500,'holiday_rest_day'=>650,'incomplete'=>null,'rejected'=>null,'missing_rate'=>null] as $case=>$expected) {
        $company=ab_insert($db,'clientcompany',['Name'=>'TEST '.$case,'UserID'=>$lead,'PayrollType'=>'B']);
        $site=ab_insert($db,'deployment_sites',['CompanyID'=>$company,'Name'=>'TEST site','Address'=>'TEST','TeamLeadID'=>$lead]);
        $employee=ab_insert($db,'employee',['FirstName'=>'TEST','LastName'=>$case,'Email'=>'ab-'.$case.'@example.invalid','EmployeeNumber'=>'AB-'.$case,'daily_rate'=>1000,'hourly_rate'=>125,'monthly_salary_credit'=>0]);
        $user=ab_insert($db,'users',['RoleID'=>6,'FullName'=>'TEST '.$case,'Email'=>'ab-'.$case.'@example.invalid','Password'=>'TEST-DISABLED']); $session=ab_session($root,$user,'Employee');
        $dep=ab_insert($db,'deployment',['EmployeeID'=>$employee,'CompanyID'=>$company,'SiteID'=>$site,'SiteSuperVisorID'=>$lead,'StartDate'=>'2026-01-01','Location'=>'TEST site','WorkingDays'=>$case==='holiday_rest_day'?'Sun':'Mon,Tue,Wed,Thu,Fri,Sat,Sun']);
        $a=ab_insert($db,'attendance',['EmployeeID'=>$employee,'Date'=>$date,'TimeIn'=>$date.' 08:00:00','TimeOut'=>$case==='incomplete'?null:$date.' 15:00:00','clockin_photo'=>'uploads/attendance_photos/test.png','clockout_photo'=>$case==='incomplete'?'uploads/attendance_photos/missing.png':'uploads/attendance_photos/test.png','TotalHours'=>$case==='incomplete'?null:7,'EvidenceDeploymentID'=>$dep]);
        ab_insert($db,'daily_time_records',['employee_id'=>$employee,'date'=>$date,'time_in'=>$date.' 08:00:00','time_out'=>$case==='incomplete'?null:$date.' 15:00:00','total_hours_rendered'=>$case==='incomplete'?null:7,'status'=>$case==='incomplete'?'Clocked In':'Clocked Out']);
        $historical=ab_insert($db,'dtr_submissions',['EmployeeID'=>$employee,'DeploymentID'=>$dep,'SiteID'=>$site,'SiteSupervisorID'=>$lead,'CutoffStartDate'=>$workPeriod['start'],'CutoffEndDate'=>$workPeriod['end'],'TotalHours'=>6,'SubmittedBy'=>$lead,'ReviewedBy'=>$accounting,'ReviewDate'=>date('Y-m-d H:i:s'),'StatusID'=>7]);
        ab_insert($db,'payroll',['EmployeeID'=>$employee,'PayPeriodStart'=>$workPeriod['start'],'PayPeriodEnd'=>$workPeriod['end'],'GrossPay'=>750,'NetPay'=>750,'DTR_SubmissionID'=>$historical,'StatusID'=>9]);
        if ($case!=='missing_rate') ab_insert($db,'salary_rates',['EmployeeID'=>$employee,'DeploymentID'=>$dep,'CompanyID'=>$company,'EffectiveDate'=>'2026-01-01','DailyRate'=>1000,'HourlyRate'=>125]);
        $db->query('DELETE FROM holidays');
        if (str_contains($case,'holiday')) ab_insert($db,'holidays',['HolidayName'=>'TEST regular holiday','HolidayDate'=>$date,'HolidayType'=>'Regular Holiday','PayMultiplier'=>2,'Year'=>(int)substr($date,0,4)]);
        $original=$db->query("SELECT * FROM attendance WHERE AttendanceID=$a")->fetch_assoc(); $originalDtr=$db->query("SELECT * FROM dtr_submissions WHERE DTR_SubmissionID=$historical")->fetch_assoc();
        $body=['work_date'=>$date,'duty_start'=>$date.'T08:00','duty_end'=>$date.'T17:00','reason'=>'TEST two missing duty hours'];
        $id=ab_http($api,$session,$body)['request_id'];
        $pending=ab_http($api.'?request_id='.$id,$session)['request']; ab_assert($pending['StatusName']==='Pending','Pending status');
        if ($case==='normal') {
            $normalSession=$session; $normalAttendance=$a; $normalRequest=$id;
            ab_http($api,$session,$body,409); ab_http($api,$session,$body+['amount'=>900000],400);
            ab_http($api,$leadSession,['request_id'=>$id,'decision'=>'approve'],403);
            ab_http($base.'/Api/Common/attendance_evidence.php?attendance_id='.$a.'&type=clockin&file=../../index.html',$session,null,400);
            ab_http($api,$acctSession,['request_id'=>$id,'decision'=>'approve','amount'=>1],400);
            ab_http($api,$acctSession,['request_id'=>$id,'decision'=>'reject'],422);
            $ownReviewerSession=ab_session($root,$user,'Accounting');
            ab_http($api,$ownReviewerSession,['request_id'=>$id,'decision'=>'approve'],403);
            $security['self_approval']='PASS';
            $ev=$pending['evidence'][0]; ab_http($base.'/Api/Common/attendance_evidence.php?evidence_id='.$ev['EvidenceID'].'&type=clockin&download=1',$session);
            $security['amount_tampering']='PASS'; $security['non_accounting_review']='PASS'; $security['path_traversal']='PASS'; $security['owned_evidence_download']='PASS';
        } else {
            ab_http($base.'/Api/Common/attendance_evidence.php?attendance_id='.$normalAttendance.'&type=clockin',$session,null,403);
            ab_http($api.'?request_id='.$normalRequest,$session,null,403); $security['cross_employee_evidence_and_request']='PASS';
        }
        if ($case==='missing_rate') { ab_http($api,$acctSession,['request_id'=>$id,'decision'=>'approve'],422); ab_assert((int)$db->query("SELECT COUNT(*) n FROM payroll_adjustments WHERE AddBackRequestID=$id")->fetch_assoc()['n']===0,'No unverified historical rate payment'); $results[$case]='PASS approval blocked'; continue; }
        if ($case==='rejected') { ab_http($api,$acctSession,['request_id'=>$id,'decision'=>'reject','remarks'=>'Evidence insufficient for requested duty']); ab_assert((int)$db->query("SELECT COUNT(*) n FROM payroll_adjustments WHERE AddBackRequestID=$id")->fetch_assoc()['n']===0,'Rejected no ledger'); $results[$case]='PASS no ledger'; continue; }
        $approved=ab_http($api,$acctSession,['request_id'=>$id,'decision'=>'approve','remarks'=>'TEST verified recorded evidence']);
        if ($expected!==null) ab_assert(abs($approved['amount']-$expected)<0.01,$case.' engine amount');
        ab_http($api,$acctSession,['request_id'=>$id,'decision'=>'approve'],409); ab_http($api,$session,$body,409);
        ab_assert($original===$db->query("SELECT * FROM attendance WHERE AttendanceID=$a")->fetch_assoc(),'Original attendance unchanged');
        ab_assert($originalDtr===$db->query("SELECT * FROM dtr_submissions WHERE DTR_SubmissionID=$historical")->fetch_assoc(),'Locked DTR unchanged');
        $r=ab_http($api.'?request_id='.$id,$session)['request']; $start=$r['TargetStart']; $end=$r['TargetEnd'];
        ab_assert($start===$todayPeriod['start'] && $end===$todayPeriod['end'],'Current open payment target');
        $newDtr=ab_insert($db,'dtr_submissions',['EmployeeID'=>$employee,'DeploymentID'=>$dep,'SiteID'=>$site,'SiteSupervisorID'=>$lead,'CutoffStartDate'=>$start,'CutoffEndDate'=>$end,'SubmittedBy'=>$lead,'ReviewedBy'=>$accounting,'ReviewDate'=>date('Y-m-d H:i:s'),'StatusID'=>7]);
        $final=ab_http($base.'/Api/Accounting/finalize_payroll_batch.php',$acctSession,['tl_id'=>$lead,'cutoff_start'=>$start,'cutoff_end'=>$end]);
        $pay=$db->query("SELECT * FROM payroll WHERE EmployeeID=$employee AND PayPeriodStart='$start' AND PayPeriodEnd='$end'")->fetch_assoc();
        ab_assert(abs((float)$pay['GrossPay']-(float)$approved['amount'])<0.01,'Payroll gross includes once');
        ab_http($base.'/Api/Accounting/finalize_payroll_batch.php',$acctSession,['tl_id'=>$lead,'cutoff_start'=>$start,'cutoff_end'=>$end]);
        ab_assert((int)$db->query("SELECT COUNT(*) n FROM payroll_adjustments WHERE AddBackRequestID=$id")->fetch_assoc()['n']===1,'Exactly one adjustment');
        $slip=ab_http($base.'/Api/Employee/get_employee_payroll_history.php?payroll_id='.$pay['Payroll_ID'],$session);
        ab_assert(abs($slip['payslip']['earnings']['add_back']-$approved['amount'])<0.01,'Payslip separate line');
        $run=client_billing_run_code($start,$end,$company); ab_insert($db,'payroll_runs',['RunCode'=>$run,'CompanyID'=>$company,'PeriodStart'=>$start,'PeriodEnd'=>$end,'SiteSupervisorID'=>$lead]);
        $export=client_billing_build_export($db,$run); ab_assert(count($export['rows'])===1,'Export selected context'); ab_assert(abs($export['rows'][0]['addback']-$approved['amount'])<0.01,'Exporter Add Back amount');
        if ($case==='normal' && !in_array('--fast',$argv,true)) {
            mkdir($root.'/Templates',0700,true); copy(__DIR__.'/../../Templates/SAMPLE.xlsx',$root.'/Templates/SAMPLE.xlsx');
            require __DIR__.'/../../vendor/autoload.php'; require $root.'/Api/Accounting/Lib/client_billing_spreadsheet.php';
            $sheet=client_billing_render_spreadsheet($export)->getActiveSheet(); ab_assert(abs((float)$sheet->getCell('Q6')->getValue()-250)<0.01,'Workbook Q6 pesos');
            ab_assert(abs((float)$sheet->getCell('AB6')->getValue()-(float)$pay['GrossPay'])<0.01,'Workbook gross matches payroll');
            ab_assert(add_back_payroll_total($pdo,$employee,$dep+999,$start,$end)===0.0,'No cross deployment');
        }
        $results[$case]=['PASS'=>true,'requested_hours'=>$r['RequestedHours'],'approved_amount'=>$approved['amount'],'gross'=>$pay['GrossPay'],'payroll_id'=>$pay['Payroll_ID']];
    }
    // Exercise the actual clock endpoint with synthetic GPS/photo data in this fixture only.
    $clockCompany=ab_insert($db,'clientcompany',['Name'=>'TEST Clock Evidence','UserID'=>$lead]);
    $clockSite=ab_insert($db,'deployment_sites',['CompanyID'=>$clockCompany,'Name'=>'TEST Clock site','TeamLeadID'=>$lead,'registered_latitude'=>8.43,'registered_longitude'=>124.65,'site_registered_at'=>date('Y-m-d H:i:s'),'registered_by_tl_id'=>$lead]);
    $clockEmployee=ab_insert($db,'employee',['FirstName'=>'TEST','LastName'=>'Clock','Email'=>'ab-clock@example.invalid','EmployeeNumber'=>'AB-CLOCK']);
    $clockUser=ab_insert($db,'users',['RoleID'=>6,'FullName'=>'TEST Clock','Email'=>'ab-clock@example.invalid','Password'=>'TEST-DISABLED']);
    $clockDep=ab_insert($db,'deployment',['EmployeeID'=>$clockEmployee,'CompanyID'=>$clockCompany,'SiteID'=>$clockSite,'SiteSuperVisorID'=>$lead,'StartDate'=>'2026-01-01','StatusID'=>5,'Location'=>'TEST Clock site']);
    $clockSession=ab_session($root,$clockUser,'Employee');
    $clockBody=['latitude'=>8.43,'longitude'=>124.65,'accuracy'=>5,'selfie'=>'data:image/png;base64,'.base64_encode(file_get_contents($root.'/uploads/attendance_photos/test.png'))];
    ab_http($base.'/Api/Employee/clock_in_out_geofence.php',$clockSession,$clockBody+['action'=>'clock_in']);
    usleep(1100000);
    ab_http($base.'/Api/Employee/clock_in_out_geofence.php',$clockSession,$clockBody+['action'=>'clock_out']);
    $clock=$db->query("SELECT * FROM attendance WHERE EmployeeID=$clockEmployee")->fetch_assoc();
    ab_assert((int)$clock['EvidenceDeploymentID']===$clockDep && !empty($clock['server_timestamp']),'Captured deployment and server timestamp');
    ab_assert(evidence_file($clock['clockout_photo'])['mime']==='image/png','Clock Out filename is preserved as a string');
    ab_http($base.'/Api/Common/attendance_evidence.php?attendance_id='.$clock['AttendanceID'].'&type=clockout&download=1',$clockSession);
    ab_insert($db,'dtr_submissions',['EmployeeID'=>$clockEmployee,'DeploymentID'=>$clockDep,'SiteID'=>$clockSite,'SiteSupervisorID'=>$lead,'CutoffStartDate'=>$todayPeriod['start'],'CutoffEndDate'=>$todayPeriod['end'],'SubmittedBy'=>$lead]);
    ab_http($base.'/Api/Employee/clock_in_out_geofence.php',$clockSession,$clockBody+['action'=>'clock_in'],409);
    ab_assert($clock===$db->query("SELECT * FROM attendance WHERE EmployeeID=$clockEmployee")->fetch_assoc(),'Submitted clock records cannot change');
    $security['clock_in_out_evidence_and_submitted_lock']='PASS';
    if (date('H:i')>='17:01') {
        // Today's rate is a server snapshot, not a guessed historical rate.
        $today=date('Y-m-d');
        $db->query("UPDATE employee SET daily_rate=1000,hourly_rate=125 WHERE EmployeeID=$clockEmployee");
        $db->query("UPDATE deployment SET WorkingDays='Mon,Tue,Wed,Thu,Fri,Sat,Sun' WHERE DeploymentID=$clockDep");
        $db->query("UPDATE attendance SET TimeIn='$today 08:00:00',TimeOut='$today 15:00:00',TotalHours=7 WHERE EmployeeID=$clockEmployee");
        $db->query("UPDATE daily_time_records SET time_in='$today 08:00:00',time_out='$today 15:00:00',total_hours_rendered=7,status='Clocked Out' WHERE employee_id=$clockEmployee");
        $todayRequest=ab_http($api,$clockSession,['work_date'=>$today,'duty_start'=>$today.'T08:00','duty_end'=>$today.'T17:00','reason'=>'TEST current-date missing hours'])['request_id'];
        $todayApproved=ab_http($api,$acctSession,['request_id'=>$todayRequest,'decision'=>'approve','remarks'=>'TEST current date verified']);
        ab_assert(abs($todayApproved['amount']-250)<0.01,'Completed duty uses Philippine time and server current rate snapshot');
        $results['current_rate_snapshot']='PASS: no browser rate; completed duty accepted in Philippine time';
    }
    $boundary='AddBackTestBoundary'; $multipart="--$boundary\r\nContent-Disposition: form-data; name=\"proof\"; filename=\"invalid.txt\"\r\nContent-Type: text/plain\r\n\r\nTEST unsupported upload\r\n--$boundary--\r\n";
    $context=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'timeout'=>10,'header'=>"Cookie: PHPSESSID=$normalSession\r\nContent-Type: multipart/form-data; boundary=$boundary\r\n",'content'=>$multipart]]);
    file_get_contents($api,false,$context); ab_assert(str_contains($http_response_header[0]??'','415'),'Unsupported additional upload rejected'); $security['unsupported_additional_upload']='PASS';
    ab_assert(saveBase64Selfie('data:image/jpeg;base64,'.base64_encode('<?php echo 1; ?>'),999,'clock_in')===null,'Unsupported selfie payload');
    try { evidence_file('uploads/attendance_photos/../../index.html'); throw new RuntimeException('Traversal accepted'); } catch (DomainException $e) { $security['storage_containment']='PASS'; }
    $security['unsupported_image']='PASS'; $security['duplicate_request_approval_payment']='PASS';
    $after=ab_snapshot($live); ab_assert($before===$after,'Production data unchanged');
    $result=['success'=>true,'results'=>$results,'security'=>$security,'production_unchanged'=>true,'fixture_directory'=>$root,'production_before'=>$before,'production_after'=>$after];
    if (in_array('--browser',$argv,true)) {
        foreach (['Html','Js','Css'] as $directory) ab_copy(__DIR__.'/../../'.$directory,$root.'/'.$directory);
        // Local, disposable fixture identities only. Never installed in the production project.
        $bootstrap='<?php ini_set("session.save_path",'.var_export($root.'/sessions',true).'); session_start(); $role=($_GET["role"]??"")==="Accounting"?"Accounting":"Employee"; $_SESSION=["user_id"=>$role==="Accounting"?'.$accounting.':'.(int)$db->query("SELECT UsersID FROM users WHERE Email='ab-normal@example.invalid'")->fetch_assoc()['UsersID'].',"role"=>$role,"last_activity"=>time()]; header("Location: /Html/".($role==="Accounting"?"Accounting/accounting_dashboard.html":"Employee/employee_dashboard.html"));';
        file_put_contents($root.'/browser.php',$bootstrap);
        file_put_contents(__DIR__.'/browser_fixture.json',json_encode(['url'=>$base.'/browser.php','done'=>$root.'/browser_done','fixture'=>$root],JSON_PRETTY_PRINT));
        for ($seconds=0;$seconds<300 && !is_file($root.'/browser_done');$seconds++) sleep(1);
        unlink($root.'/browser.php');
    }
} catch (Throwable $e) { $result['error']=$e->getMessage(); $result['trace']=$e->getTraceAsString(); $result['fixture_directory']=$root; }
finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($created && $testDb!==$sourceDb && preg_match('/^codex_addback_[a-f0-9]{12}$/D',$testDb)) { $live->query('DROP DATABASE `'.$testDb.'`'); $result['cleanup']='Disposable database removed'; }
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit($result['success']?0:1);
