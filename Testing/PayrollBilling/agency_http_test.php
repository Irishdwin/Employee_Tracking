<?php
/** Synthetic database only. No production employee/payroll data is copied or changed. */
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../Api/Helpers/client_billing_helper.php';
require_once __DIR__.'/../../Api/Helpers/billing_revision_helper.php';
require_once __DIR__.'/../../Api/Helpers/employee_calendar_holidays.php';
require_once __DIR__.'/../../Api/Helpers/leave_pay_notice.php';
require_once __DIR__.'/../../Api/Accounting/Lib/client_billing_spreadsheet.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('127.0.0.1','root','');$db->set_charset('utf8mb4');
$name='codex_billing_test_'.bin2hex(random_bytes(5));$results=[];$files=[];
function check(bool $ok,string $test): void {global $results;if(!$ok)throw new RuntimeException('FAIL: '.$test);$results[]=$test;file_put_contents(__DIR__.'/progress.json',json_encode($results,JSON_PRETTY_PRINT));}
function rejected(callable $fn,string $test): void {try{$fn();}catch(Throwable $e){check(true,$test);return;}check(false,$test);}
/** Populate only required schema columns with harmless synthetic values. */
function fixture(mysqli $db,string $table,array $values): int {
    foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC) as $c) {
        if(array_key_exists($c['Field'],$values) || $c['Null']==='YES' || $c['Default']!==null || str_contains($c['Extra'],'auto_increment'))continue;
        $type=strtolower($c['Type']);$values[$c['Field']]=preg_match('/int|decimal|float|double/',$type)?1:(str_starts_with($type,'date')?'2026-10-01':(str_starts_with($type,'enum')?explode("'",$type)[1]:'Synthetic'));
    }
    $fields=array_keys($values);$stmt=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$fields).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')');$args=array_values($values);$stmt->bind_param(str_repeat('s',count($args)),...$args);$stmt->execute();return (int)$db->insert_id;
}
try {
    $db->query('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$db->select_db($name);
    foreach(['users','roles','employee','clientcompany','deployment','deployment_sites','dtr_submissions','payroll','status','notifications','payroll_adjustments','payroll_audit_log','payroll_runs','payroll_status_history','payroll_group_status_history','holidays','payroll_run_members','payroll_billing_exports','payroll_billing_revisions','payroll_billing_adjustment_sources','holiday_deployment_sites'] as $table)$db->query('CREATE TABLE `'.$table.'` LIKE onazul_hris_db.`'.$table.'`');
    fixture($db,'roles',['RolesID'=>1,'RoleName'=>'Accounting']);fixture($db,'roles',['RolesID'=>2,'RoleName'=>'Employee']);
    fixture($db,'users',['UsersID'=>10,'RoleID'=>1,'Email'=>'accounting@synthetic.invalid','IsActive'=>1]);
    fixture($db,'users',['UsersID'=>20,'RoleID'=>2,'Email'=>'one@synthetic.invalid','IsActive'=>1]);
    fixture($db,'users',['UsersID'=>21,'RoleID'=>2,'Email'=>'two@synthetic.invalid','IsActive'=>1]);
    fixture($db,'users',['UsersID'=>22,'RoleID'=>2,'Email'=>'foreign@synthetic.invalid','IsActive'=>1]);
    fixture($db,'users',['UsersID'=>30,'RoleID'=>1,'Email'=>'lead@synthetic.invalid','IsActive'=>1]);
    fixture($db,'status',['StatusID'=>1,'Name'=>'Finalized']);
    fixture($db,'clientcompany',['CompanyID'=>1,'Name'=>'Synthetic Client','PayrollType'=>'B']);fixture($db,'clientcompany',['CompanyID'=>2,'Name'=>'Foreign Client','PayrollType'=>'B']);
    fixture($db,'deployment_sites',['SiteID'=>1,'CompanyID'=>1,'Name'=>'Site One','Address'=>'Cagayan de Oro City, Misamis Oriental']);fixture($db,'deployment_sites',['SiteID'=>2,'CompanyID'=>2,'Name'=>'Other Site','Address'=>'Iligan City']);
    $start='2026-09-26';$end='2026-10-10';$rows=[];
    for($i=1;$i<=3;$i++) {
        fixture($db,'employee',['EmployeeID'=>$i,'EmployeeNumber'=>'TEST'.$i,'Email'=>['one','two','foreign'][$i-1].'@synthetic.invalid','FirstName'=>'Employee','LastName'=>(string)$i,'IsActive'=>1]);
        fixture($db,'deployment',['DeploymentID'=>$i,'EmployeeID'=>$i,'CompanyID'=>$i===3?2:1,'SiteID'=>$i===3?2:1,'Location'=>'Site One','StartDate'=>'2026-01-01','EndDate'=>null]);
        fixture($db,'dtr_submissions',['DTR_SubmissionID'=>$i,'EmployeeID'=>$i,'DeploymentID'=>$i,'SiteID'=>$i===3?2:1,'SiteSupervisorID'=>30,'CutoffStartDate'=>$start,'CutoffEndDate'=>$end,'ReviewedBy'=>10,'ReviewDate'=>'2026-10-01']);
        fixture($db,'payroll',['Payroll_ID'=>$i,'EmployeeID'=>$i,'DTR_SubmissionID'=>$i,'PayPeriodStart'=>$start,'PayPeriodEnd'=>$end,'StatusID'=>1,'BasicSalary'=>1000,'Allowances'=>100,'GrossPay'=>1100,'TotalDeductions'=>100,'SSSDeduction'=>50,'PhilHealthDeduction'=>30,'PagIBIGDeduction'=>20,'NetPay'=>1000]);
        if($i===3)continue;
        $rows[]=['employee_db_id'=>$i,'employee_id'=>'TEST'.$i,'employee_name'=>'Employee '.$i,'designation'=>'Worker','area_site'=>'Site One','dtr_submission_id'=>$i,'site_id'=>1,'daily_rate'=>500,'days_worked'=>2,'basic_total'=>1000,'allowances'=>100,'gross_salary'=>1000,'gross_payroll'=>1100,'total_ee_deductions'=>100,'other_ee_deductions'=>0,'net_salary'=>1000,'sss_ee'=>50,'philhealth_ee'=>30,'pagibig_ee'=>20,'total_er_dues'=>50,'thirteenth_month_accrual'=>80,'admin_overhead_pct'=>10,'vat_pct'=>12,'admin_overhead_amount'=>123,'vat_on_overhead'=>14.76,'subtotal_labor_cost'=>1230,'grand_total_invoice'=>1367.76,'sil_accrual'=>0];
    }
    $code=client_billing_run_code($start,$end,1,30,1);
    fixture($db,'payroll_runs',['PayrollRunID'=>1,'RunCode'=>$code,'BillingRefNo'=>client_billing_billing_ref($code),'CompanyID'=>1,'SiteID'=>1,'SiteSupervisorID'=>30,'PeriodStart'=>$start,'PeriodEnd'=>$end,'CutoffLabel'=>'Sep 26 – Oct 10, 2026','CurrentStatus'=>'Payroll Computed','Status'=>'Draft']);
    $run=$db->query('SELECT * FROM payroll_runs WHERE PayrollRunID=1')->fetch_assoc();
    $db->begin_transaction();$snapshot=billing_prepare_snapshot($db,['rows'=>$rows,'run'=>$run,'client_name'=>'Synthetic Client']);$db->commit();
    check(count($snapshot['rows'])===2,'stable two-employee roster');
    $source=realpath(__DIR__.'/../..');$root=sys_get_temp_dir().'/codex-billing-http-'.bin2hex(random_bytes(8));mkdir($root,0700,true);
    $process=null;
    try {
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source.'/Api',FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file) {
            if(!$file->isFile())continue;
            $relative=substr($file->getPathname(),strlen($source.'/Api')+1);$destination=$root.'/Api/'.$relative;
            if(!is_dir(dirname($destination)))mkdir(dirname($destination),0700,true);copy($file->getPathname(),$destination);
        }
        mkdir($root.'/Templates',0700,true);copy($source.'/Templates/SAMPLE.xlsx',$root.'/Templates/SAMPLE.xlsx');mkdir($root.'/sessions',0700,true);
        $config=$root.'/Api/Db_connection/db_connect.php';$text=file_get_contents($config);$text=str_replace("\$dbname = 'onazul_hris_db';","\$dbname = '$name';",$text);file_put_contents($config,$text);
        check(str_contains($text,"\$dbname = '$name';"),'HTTP fixture uses disposable database');
        foreach(['attendance_pdo.php','hris_pdo.php'] as $helper) {
            $path=$root.'/Api/Helpers/'.$helper;if(is_file($path))file_put_contents($path,str_replace('dbname=onazul_hris_db;','dbname='.$name.';',file_get_contents($path)));
        }
        foreach(['export_client_billing.php','import_client_billing.php'] as $endpoint) {
            $path=$root.'/Api/Accounting/'.$endpoint;$text=file_get_contents($path);
            foreach(["__DIR__ . '/../../vendor/autoload.php'","__DIR__.'/../../vendor/autoload.php'"] as $match)$text=str_replace($match,var_export($source.'/vendor/autoload.php',true),$text);
            file_put_contents($path,$text);
        }
        $path=$root.'/Api/Common/session_init.php';file_put_contents($path,"<?php\nini_set('session.save_path',".var_export($root.'/sessions',true).");\n".substr(file_get_contents($path),5));
        $csrf=bin2hex(random_bytes(32));$sessions=[];
        foreach(['accounting'=>[10,'Accounting'],'employee'=>[20,'Employee'],'foreign'=>[22,'Employee']] as $label=>$user) {
            $sid='bill'.bin2hex(random_bytes(12));$sessions[$label]=$sid;
            file_put_contents($root.'/sessions/sess_'.$sid,'user_id|i:'.$user[0].';role|s:'.strlen($user[1]).':"'.$user[1].'";payroll_csrf|s:64:"'.$csrf.'";last_activity|i:'.time().';');
        }
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException($error);
        $address=stream_socket_get_name($socket,false);fclose($socket);
        $process=proc_open([PHP_BINARY,'-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$root);
        if(!is_resource($process))throw new RuntimeException('Unable to start isolated PHP server.');
        fclose($pipes[0]);
        for($attempt=0;$attempt<50;$attempt++){if($probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1)){fclose($probe);break;}usleep(100000);}
        $http=static function(string $endpoint,string $viewer='accounting',?array $post=null,bool $token=true) use($address,$sessions,$csrf): array {
            $curl=curl_init('http://'.$address.'/Api/'.$endpoint);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIE=>'PHPSESSID='.$sessions[$viewer],CURLOPT_TIMEOUT=>180]);
            if($post!==null){curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,$post);if($token)curl_setopt($curl,CURLOPT_HTTPHEADER,['X-Payroll-CSRF: '.$csrf]);}
            $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);$error=curl_error($curl);curl_close($curl);if($body===false)throw new RuntimeException($error);return [$status,$body,json_decode($body,true)];
        };

        $before=$db->query('SELECT * FROM payroll ORDER BY Payroll_ID')->fetch_all(MYSQLI_ASSOC);
        [$status,$body]=$http('Accounting/export_client_billing.php?run_id='.urlencode($code));
        check($status===200 && str_starts_with($body,'PK'),'Authenticated XLSX download');
        $path=$root.'/agency_http.xlsx';file_put_contents($path,$body);
        $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$sheet=$book->getSheet(0);
        check($book->getSheetCount()===3,'Main agency sheet, reconciliation and signed manifest');
        check($sheet->getCell('AU6')->getValue()==='=36.5*(F6+H6)','Actual agency overhead formula');
        check(abs((float)$sheet->getCell('AU6')->getCalculatedValue()-73)<0.001,'Agency overhead independent calculation');
        check((float)$book->getSheetByName('Payroll Reconciliation')->getCell('C5')->getValue()===1100.0,'Original saved gross shown');
        check($before===$db->query('SELECT * FROM payroll ORDER BY Payroll_ID')->fetch_all(MYSQLI_ASSOC),'Export leaves saved payroll unchanged');
        check(!str_contains(json_encode($sheet->toArray()),'Foreign'),'Other client excluded');
        $grand=(float)$sheet->getCell('C37')->getCalculatedValue();
        $invoice=(float)$db->query('SELECT TotalInvoice FROM payroll_runs WHERE PayrollRunID=1')->fetch_assoc()['TotalInvoice'];
        check(abs($grand-$invoice)<0.005,'Run stores actual agency invoice total');
        [$status]=$http('Accounting/export_client_billing.php?run_id='.urlencode($code),'employee');check($status===403,'Employee export denied');
        [$status]=$http('Accounting/export_client_billing.php?run_id='.urlencode($code).'&ref=INVALID');check($status===400,'Mismatched billing reference denied');
        $exportRow=$db->query('SELECT * FROM payroll_billing_exports ORDER BY ExportID DESC LIMIT 1')->fetch_assoc();
        $signed=json_decode($exportRow['SnapshotJson'],true);$manifest=json_decode((string)$book->getSheetByName('_ONAZUL_EXPORT')->getCell('A1')->getValue(),true);
        check(billing_detect_changes($book,$signed,$manifest)===[],'Unchanged actual download imports safely');
        $book->getSheet(0)->setCellValue('O6',120);
        $changes=billing_detect_changes($book,$signed,$manifest);
        check(count($changes)===1 && $changes[0]['component']==='allowances' && $changes[0]['difference']==20,'Billing allowance correction retains review semantics');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
        $upload=new CURLFile($path,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','agency.xlsx');
        [$status,$body,$preview]=$http('Accounting/import_client_billing.php','accounting',['action'=>'preview','run_id'=>$code,'workbook'=>$upload]);
        check($status===200 && $preview['preview']['changed_employees']===1,'Real multipart import preview succeeds');
        $token=$preview['preview_token'];$beforeDtr=$db->query('CHECKSUM TABLE dtr_submissions')->fetch_assoc()['Checksum'];
        [$status,$body,$confirmed]=$http('Accounting/import_client_billing.php','accounting',['action'=>'confirm','preview_token'=>$token,'reason'=>'Synthetic reviewed allowance correction']);
        check($status===200 && $confirmed['changed_employees']===1,'Reviewed correction confirms through existing API');
        $after=$db->query('SELECT * FROM payroll WHERE Payroll_ID=1')->fetch_assoc();
        check((float)$after['GrossPay']===1120.0 && (float)$after['NetPay']===1020.0,'Only reviewed delta changes saved payroll, not agency variance');
        check($before[1]===$db->query('SELECT * FROM payroll WHERE Payroll_ID=2')->fetch_assoc(),'Unchanged employee payroll untouched');
        check($beforeDtr===$db->query('CHECKSUM TABLE dtr_submissions')->fetch_assoc()['Checksum'],'Reviewed correction leaves locked DTR unchanged');
        [$status,$body,$repeated]=$http('Accounting/import_client_billing.php','accounting',['action'=>'confirm','preview_token'=>$token,'reason'=>'Replay']);
        check($status===200 && $repeated['already_confirmed'],'Review confirmation remains idempotent');
        $book->disconnectWorksheets();
        echo json_encode(['success'=>true,'checks'=>count($results),'tests'=>$results,'download'=>$path],JSON_PRETTY_PRINT);
    } finally {if(is_resource($process)){proc_terminate($process);proc_close($process);}}
} catch(Throwable $e) {$failed=true;fwrite(STDERR,$e->getMessage().PHP_EOL.$e->getTraceAsString().PHP_EOL);}
finally {if(preg_match('/^codex_billing_test_[a-f0-9]{10}$/',$name))$db->query('DROP DATABASE `'.$name.'`');}
if(isset($failed))exit(1);
