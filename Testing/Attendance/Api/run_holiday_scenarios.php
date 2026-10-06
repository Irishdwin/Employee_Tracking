<?php
/** Local Admin-only disposable-schema integration test. Accepts no overrides. */
declare(strict_types=1);
require_once __DIR__.'/_testing_guard.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'POST required.']);exit; }
if (!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)) {
    http_response_code(403);echo json_encode(['success'=>false,'message'=>'Controlled holiday tests are available on the local development server only.']);exit;
}
if (!empty($_SERVER['HTTP_ORIGIN']) && parse_url($_SERVER['HTTP_ORIGIN'],PHP_URL_HOST)!==parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST)) {
    http_response_code(403);echo json_encode(['success'=>false,'message'=>'Same-origin request required.']);exit;
}
session_write_close();set_time_limit(180);
// Under apache2handler PHP_BINARY is httpd.exe, not a PHP interpreter.
// Resolve the CLI from this installation; never accept an executable from HTTP input.
$candidates=[PHP_BINDIR.'/php.exe',dirname(php_ini_loaded_file()?:__FILE__).'/php.exe'];
if(PHP_SAPI==='cli'||PHP_SAPI==='cli-server')array_unshift($candidates,PHP_BINARY);
if(PHP_OS_FAMILY!=='Windows')$candidates[]=PHP_BINDIR.'/php';
$phpCli=null;
foreach(array_unique($candidates) as $candidate){
    if(is_file($candidate) && in_array(strtolower(basename($candidate)),['php.exe','php'],true)){$phpCli=realpath($candidate);break;}
}
if(!$phpCli){http_response_code(503);echo json_encode(['success'=>false,'message'=>'PHP CLI executable was not found beside the loaded PHP configuration.','stage'=>'launch']);exit;}
$errorLog=sys_get_temp_dir().'/onazul-holiday-runner-errors.log';
$command=[$phpCli,__DIR__.'/../holiday_e2e_runner.php'];
if(!empty($employeeMode)){
    require_once __DIR__.'/_selection_context.php';
    $body=json_decode(file_get_contents('php://input'),true)??[];
    $context=[];foreach(['company_id','site_id','team_lead_id','employee_id','deployment_id'] as $key)$context[$key]=(int)($body[$key]??0);
    $context['date']=(string)($body['date']??'');$context['scenario']=(string)($body['scenario']??'');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$context['date'])||date('Y-m-d',strtotime($context['date']))!==$context['date']||!in_array($context['scenario'],['A','B','C','D','E','F','G','H'],true))testing_context_fail('Select a supported worked scenario and valid test date.');
    testing_employee_context($conn,$context,$context['employee_id'],$context['date']);
    $command[]='--employee-context='.json_encode($context);
}
$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$errorLog,'a']],$pipes);
if (!is_resource($process)) { http_response_code(503);echo json_encode(['success'=>false,'message'=>'Could not launch the controlled holiday runner.']);exit; }
fclose($pipes[0]);$text=stream_get_contents($pipes[1]);fclose($pipes[1]);$exitCode=proc_close($process);
$result=json_decode($text,true);
if (!is_array($result)) {
    error_log('[Holiday Testing] Invalid runner JSON; exit='.$exitCode.'; CLI='.$phpCli.'; stderr='.$errorLog.'; JSON error='.json_last_error_msg());
    http_response_code(500);echo json_encode(['success'=>false,'message'=>'Controlled runner failed before returning JSON. Check onazul-holiday-runner-errors.log in the PHP temporary directory.','stage'=>'runner_output','exit_code'=>$exitCode,'json_error'=>json_last_error_msg()]);exit;
}
if (empty($result['success'])) http_response_code(500);
echo json_encode($result,JSON_UNESCAPED_SLASHES);
