<?php
/** Real multipart HTTP requests against copied APIs and the synthetic workflow database. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$GLOBALS['billing_http_test']=static function(mysqli $db,string $database,string $code): void {
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
        $config=$root.'/Api/Db_connection/db_connect.php';$text=file_get_contents($config);$text=str_replace("\$dbname = 'onazul_hris_db';","\$dbname = '$database';",$text);file_put_contents($config,$text);
        check(str_contains($text,"\$dbname = '$database';"),'HTTP fixture uses disposable database');
        foreach(['attendance_pdo.php','hris_pdo.php'] as $helper) {
            $path=$root.'/Api/Helpers/'.$helper;if(is_file($path))file_put_contents($path,str_replace('dbname=onazul_hris_db;','dbname='.$database.';',file_get_contents($path)));
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
        // A legacy finalized run has saved payroll but no itemized billing snapshot.
        $legacyCode=client_billing_run_code('2026-09-26','2026-10-10',1,30,1).'-LEGACY';
        fixture($db,'payroll_runs',['RunCode'=>$legacyCode,'BillingRefNo'=>client_billing_billing_ref($legacyCode),'CompanyID'=>1,'SiteID'=>1,'SiteSupervisorID'=>30,'PeriodStart'=>'2026-09-26','PeriodEnd'=>'2026-10-10','CutoffLabel'=>'Synthetic legacy cutoff','CurrentStatus'=>'Payroll Released','Status'=>'Draft','AdminOverheadPct'=>10,'VatPct'=>12]);
        $db->query('UPDATE payroll SET HolidayPay=0,TotalDaysWorked=2 WHERE EmployeeID IN(1,2)');
        $db->query('UPDATE employee SET daily_rate=99999,hourly_rate=9999 WHERE EmployeeID IN(1,2)');
        $beforeLegacy=$db->query('SELECT * FROM payroll ORDER BY Payroll_ID')->fetch_all(MYSQLI_ASSOC);
        [$status,$body]=$http('Accounting/export_client_billing.php?run_id='.urlencode($legacyCode));
        if($status!==200)throw new RuntimeException('Legacy export '.$status.': '.$body);
        check(str_starts_with($body,'PK'),'HTTP legacy finalized billing returns genuine XLSX');
        $legacyPath=$root.'/legacy.xlsx';file_put_contents($legacyPath,$body);$legacyBook=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($legacyPath);$legacySheet=$legacyBook->getSheet(0);
        foreach($beforeLegacy as $index=>$pay){if((int)$pay['EmployeeID']>2)continue;$r=$index+6;check(abs((float)$legacySheet->getCell('AD'.$r)->getCalculatedValue()-(float)$pay['GrossPay'])<0.005 && abs((float)$legacySheet->getCell('AM'.$r)->getCalculatedValue()-(float)$pay['NetPay'])<0.005,'HTTP legacy saved gross and net preserved for employee '.$pay['EmployeeID']);}
        check(str_contains($legacySheet->getComment('AA6')->getText()->getPlainText(),'Recovered from stored finalized payroll'),'legacy recovery limitation is disclosed in workbook');$legacyBook->disconnectWorksheets();
        check($beforeLegacy===$db->query('SELECT * FROM payroll ORDER BY Payroll_ID')->fetch_all(MYSQLI_ASSOC),'legacy export leaves all saved payroll unchanged');
        [$status,$body]=$http('Accounting/export_client_billing.php?ref='.urlencode(client_billing_billing_ref($legacyCode)));check($status===200 && str_starts_with($body,'PK'),'HTTP table billing reference resolves the same legacy run');
        [$status]=$http('Accounting/export_client_billing.php?run_id='.urlencode($legacyCode),'employee');check($status===403,'HTTP employee denied billing XLSX');
        [$status,$body,$json]=$http('Accounting/payroll_workflow_context.php');check($status===200 && $json['csrf_token']===$csrf,'HTTP CSRF context');
        [$status,$body,$json]=$http('Accounting/get_payroll_run_details.php?run_id='.urlencode($code));if($status!==200 || !is_array($json))throw new RuntimeException('Details response '.$status.': '.$body);check($json['employee_count']===2,'HTTP run details exact roster');
        check(count(array_filter($json['employees'],static fn($e)=>$e['employee_id']===3))===0,'HTTP details exclude foreign company/site');
        [$status]=$http('Accounting/get_payroll_run_details.php?run_id='.urlencode($code),'employee');check($status===403,'HTTP employee denied accounting details');
        [$status,$body]=$http('Accounting/export_client_billing.php?run_id='.urlencode($code));check($status===200 && str_starts_with($body,'PK'),'HTTP billing XLSX export');
        $workbook=$root.'/returned.xlsx';file_put_contents($workbook,$body);$upload=static fn()=>new CURLFile($workbook,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','returned.xlsx');
        [$status,$body,$json]=$http('Accounting/import_client_billing.php','accounting',['action'=>'preview','run_id'=>$code,'workbook'=>$upload()]);check($status===200 && $json['preview']['changed_employees']===0,'HTTP unchanged workbook preview');
        [$status]=$http('Accounting/import_client_billing.php','accounting',['action'=>'preview','run_id'=>$code,'workbook'=>$upload()],false);check($status===403,'HTTP missing CSRF rejected');
        [$status]=$http('Accounting/import_client_billing.php','employee',['action'=>'preview','run_id'=>$code,'workbook'=>$upload()]);check($status===403,'HTTP employee import rejected');
        $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($workbook);$sheet=$book->getSheet(0);$sheet->setCellValue('O6',(float)$sheet->getCell('O6')->getValue()+100);(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($workbook);$book->disconnectWorksheets();
        [$status,$body,$json]=$http('Accounting/import_client_billing.php','accounting',['action'=>'preview','run_id'=>$code,'workbook'=>$upload()]);check($status===200 && $json['preview']['changed_employees']===1,'HTTP multipart preview finds one changed employee');
        $token=$json['preview_token'];[$status,$body,$json]=$http('Accounting/import_client_billing.php','accounting',['action'=>'confirm','preview_token'=>$token,'reason'=>'Real HTTP synthetic correction']);check($status===200 && $json['changed_employees']===1,'HTTP confirmation updates one employee');
        [$status,$body,$json]=$http('Accounting/import_client_billing.php','accounting',['action'=>'preview','run_id'=>$code,'workbook'=>$upload()]);check($status===200 && $json['already_confirmed'],'HTTP re-import creates no duplicate');
        [$status,$body,$json]=$http('Employee/get_employee_payroll_history.php?payroll_id=1','employee');if($status!==200 || !isset($json['payslip']['net_pay']))throw new RuntimeException('Payslip response '.$status.': '.$body);check((float)$json['payslip']['net_pay']===(float)$db->query('SELECT NetPay FROM payroll WHERE Payroll_ID=1')->fetch_row()[0],'HTTP own historical payslip shows revised amount');
        [$status]=$http('Employee/get_employee_payroll_history.php?payroll_id=2','employee');check($status===404,'HTTP payslip IDOR rejected');
        [$status,$body,$json]=$http('Accounting/get_payroll_runs.php');check($status===200 && $json['success'],'HTTP accounting list supports site and snapshot filters');
        $before=(int)$db->query('SELECT COUNT(*) n FROM payroll_billing_exports')->fetch_assoc()['n'];
        [$status]=$http('Accounting/export_client_billing.php?archive=1&run_id='.urlencode($code));
        check($status===302 && (int)$db->query('SELECT COUNT(*) n FROM payroll_billing_exports')->fetch_assoc()['n']===$before,'HTTP archive download does not recompute or create export');
    } finally {
        if(is_resource($process)){proc_terminate($process);proc_close($process);}
        $resolved=realpath($root);$temp=realpath(sys_get_temp_dir());
        if($resolved && $temp && str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR) && str_starts_with(basename($resolved),'codex-billing-http-')) {
            $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($items as $item){$path=$item->getPathname();if(!str_starts_with($path,$resolved.DIRECTORY_SEPARATOR))throw new RuntimeException('Unsafe test cleanup path.');if($item->isDir()&&!$item->isLink())rmdir($path);else unlink($path);}rmdir($resolved);
        }
    }
};
require __DIR__.'/workflow_test.php';
