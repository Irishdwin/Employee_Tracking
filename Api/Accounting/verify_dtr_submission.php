<?php
/** Existing employee/batch verification endpoint; totals use production payroll. */
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/dtr_verification.php';
require_once __DIR__.'/../Helpers/attendance_pdo.php';
header('Content-Type: application/json');header('Cache-Control: no-store');
try {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('POST required.',405);
    payroll_require_accounting($conn,true);
    $body=json_decode(file_get_contents('php://input'),true)??$_POST;
    if(!is_array($body))throw new RuntimeException('Invalid request.',400);
    $conn->begin_transaction();$rows=dtr_verification_targets($conn,$body);$count=0;$pdo=attendance_pdo($conn);
    foreach($rows as $row){
        if($row['ReviewDate'] && $row['ReviewedBy'])continue;
        dtr_verification_unlocked($conn,$row);$rates=payroll_employee_rates($row);
        if(!$rates['valid'])throw new InvalidArgumentException('Employee salary rate is invalid.');
        $computed=payroll_compute_period($conn,$pdo,(int)$row['EmployeeID'],$rates['daily_rate'],$rates['hourly_rate'],(float)$row['allowance'],(float)$row['monthly_salary_credit'],$row['CutoffStartDate'],$row['CutoffEndDate'],null,null,(int)$row['DeploymentID']);
        $c=dtr_classification_from_payroll($computed);$remarks='DTR_CLASSIFICATION_V1:'.json_encode($c,JSON_THROW_ON_ERROR);$reviewer=(int)$_SESSION['user_id'];$id=(int)$row['DTR_SubmissionID'];
        $q=$conn->prepare('UPDATE dtr_submissions SET TotalHours=?,OvertimeHours=?,TotalOvertimeHours=?,Remarks=?,ReviewedBy=?,ReviewDate=NOW() WHERE DTR_SubmissionID=? AND ReviewDate IS NULL');$q->bind_param('dddsii',$c['regular_hours'],$c['ordinary_overtime_hours'],$c['overtime_hours'],$remarks,$reviewer,$id);$q->execute();$count+=$q->affected_rows;
        if(!AuditLogger::log($conn,'DTR verification','Verify','Verified employee #'.$row['EmployeeID'].' at site #'.$row['SiteID'].'; cutoff '.$row['CutoffStartDate'].' to '.$row['CutoffEndDate'],(string)$id))throw new RuntimeException('Verification audit failed.');
    }
    if(!$count)throw new RuntimeException('This DTR is already verified.',409);
    $conn->commit();echo json_encode(['success'=>true,'verified_count'=>$count,'message'=>'DTR verified successfully. This employee is now eligible for payroll computation, subject to the remaining payroll requirements.']);
}catch(Throwable $e){
    $conn->rollback();$status=$e instanceof InvalidArgumentException?422:((in_array($e->getCode(),[400,401,403,404,405,409,422],true))?$e->getCode():500);http_response_code($status);
    $message=$status===500?'Failed to verify DTR.':$e->getMessage();if($status===422 && str_contains(strtolower($message),'holiday'))$message='Verification cannot be completed. Please resolve the holiday eligibility review first. '.$message;
    if($status===500)error_log('verify_dtr_submission: '.$e->getMessage());echo json_encode(['success'=>false,'message'=>$message]);
}
