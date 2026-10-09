<?php
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/dtr_verification.php';
require_once __DIR__.'/../Helpers/attendance_pdo.php';
header('Content-Type: application/json');header('Cache-Control: no-store');
try{
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('POST required.',405);
    payroll_require_accounting($conn,true);$body=json_decode(file_get_contents('php://input'),true);
    if(!is_array($body)||(int)($body['employee_id']??0)<=0||(int)($body['submission_id']??0)<=0)throw new RuntimeException('Select an individual submitted DTR.',400);
    $conn->begin_transaction();$rows=dtr_verification_targets($conn,$body);$row=$rows[0];
    if($row['ReviewDate'])throw new RuntimeException('Verified DTR review cannot be changed.',409);
    dtr_verification_unlocked($conn,$row);$pdo=attendance_pdo($conn);$reviews=dtr_holiday_reviews($conn,$pdo,$row);$context=null;
    foreach($reviews as $review)if($review['holiday_date']===($body['holiday_date']??''))$context=$review;
    if(!$context||!$context['preceding_workday'])throw new RuntimeException('No applicable unworked regular holiday review was found.',422);
    if(!hash_equals($context['fingerprint'],(string)($body['fingerprint']??'')))throw new RuntimeException('Attendance or leave information changed. Refresh and inspect the review again.',409);
    if($context['decision'] && $context['decision']['outcome']!=='unresolved')throw new RuntimeException('A resolved review outcome is already recorded for this evidence.',409);
    $outcome=(string)($body['outcome']??'');$basis=(string)($body['basis']??'');$reason=trim((string)($body['reason']??''));
    if(!in_array($outcome,['eligible','ineligible','unresolved'],true)||strlen($reason)<20||strlen($reason)>2000)throw new RuntimeException('Select an outcome and provide a documentary explanation (20–2,000 characters).',422);
    if($outcome==='eligible'){
        $attendance=$context['attendance'];$worked=!empty($attendance['time_in'])&&!empty($attendance['time_out'])&&!in_array(strtolower((string)$attendance['status']),['absent','rejected','leave','on leave'],true);
        if(!(($basis==='attendance'&&$worked)||($basis==='paid_leave'&&$context['approved_leaves'])||($basis==='approved_correction'&&$context['approved_corrections'])))throw new RuntimeException('Eligibility requires completed attendance, documented paid approved leave, or an approved attendance correction.',422);
    }elseif($outcome==='ineligible'){
        if($basis!=='documented_unpaid_absence')throw new RuntimeException('Confirm documented unpaid absence after checking leave and corrections.',422);
        $attendance=$context['attendance'];
        if((!empty($attendance['time_in'])&&!empty($attendance['time_out'])&&!in_array(strtolower((string)$attendance['status']),['absent','rejected','leave','on leave'],true)) || $context['approved_corrections'])throw new RuntimeException('Recorded attendance or an approved correction conflicts with unpaid absence. Resolve the source discrepancy before deciding.',422);
    }
    $value=['version'=>1,'fingerprint'=>$context['fingerprint'],'outcome'=>$outcome,'basis'=>$basis,'reason'=>$reason,'holiday_date'=>$context['holiday_date'],'preceding_workday'=>$context['preceding_workday'],'submission_id'=>(int)$row['DTR_SubmissionID']];
    $ref=$row['EmployeeID'].':'.$context['deployment_id'].':'.$context['holiday_date'];
    if(!AuditLogger::log($conn,'Holiday eligibility','Review',json_encode($value,JSON_THROW_ON_ERROR),$ref))throw new RuntimeException('Holiday review audit failed.');
    $conn->commit();echo json_encode(['success'=>true,'message'=>'Holiday review recorded. Retry DTR verification after every required review is resolved.']);
}catch(Throwable $e){$conn->rollback();$status=in_array($e->getCode(),[400,401,403,404,405,409,422],true)?$e->getCode():500;http_response_code($status);if($status===500)error_log('holiday review: '.$e->getMessage());echo json_encode(['success'=>false,'message'=>$status===500?'Holiday review could not be saved.':$e->getMessage()]);}
