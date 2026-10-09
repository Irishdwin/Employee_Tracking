<?php
declare(strict_types=1);
require __DIR__.'/session_init.php';
require __DIR__.'/../Db_connection/db_connect.php';
require __DIR__.'/../Helpers/attendance_pdo.php';
require __DIR__.'/../Helpers/employee_salary_helper.php';
require __DIR__.'/../Helpers/AuditLogger.php';
header('Content-Type: application/json');header('Cache-Control: no-store');
try {
 $pdo=attendance_pdo($conn);$user=(int)($_SESSION['user_id']??0);
 $q=$pdo->prepare('SELECT r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1');$q->execute([$user]);
 if(!in_array($q->fetchColumn(),['HR','Admin'],true))throw new DomainException('Only HR/Admin can record documented salary history.',403);
 if(empty($_SESSION['add_back_csrf']))$_SESSION['add_back_csrf']=bin2hex(random_bytes(32));
 $post=$_SERVER['REQUEST_METHOD']==='POST';
 if(!$post && $_SERVER['REQUEST_METHOD']!=='GET')throw new DomainException('Method not allowed.',405);
 $body=$post?json_decode(file_get_contents('php://input'),true):$_GET;
 if(!is_array($body))throw new DomainException('Invalid request.',400);
 if($post && !hash_equals($_SESSION['add_back_csrf'],(string)($_SERVER['HTTP_X_ADDBACK_CSRF']??'')))throw new DomainException('Refresh the page and retry.',403);
 $q=$pdo->prepare('SELECT r.EmployeeID,r.DeploymentID,r.CompanyID,r.WorkDate,e.FirstName,e.LastName,d.StartDate,d.EndDate,c.Name CompanyName FROM add_back_requests r JOIN employee e ON e.EmployeeID=r.EmployeeID JOIN deployment d ON d.DeploymentID=r.DeploymentID AND d.EmployeeID=r.EmployeeID AND d.CompanyID=r.CompanyID JOIN clientcompany c ON c.CompanyID=r.CompanyID WHERE r.RequestID=?');$q->execute([(int)($body['request_id']??0)]);$r=$q->fetch();
 if(!$r)throw new DomainException('Add Back request not found.',404);
 if($post){
  $config=employee_salary_config_from_input($body['daily_rate']??null,0);
  if(!$config['success'])throw new DomainException($config['message'],422);
  $start=(string)($body['effective_date']??'');$end=trim((string)($body['end_date']??''));
  foreach(array_filter([$start,$end]) as $date){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d || $d->format('Y-m-d')!==$date)throw new DomainException('Valid effective dates required.',422);}
  if(!$start || $start>$r['WorkDate'] || ($end && $end<$r['WorkDate']) || ($r['StartDate'] && $start<$r['StartDate']) || ($r['EndDate'] && (!$end || $end>$r['EndDate'])))throw new DomainException('Rate dates must cover the requested work date and stay within this deployment.',422);
  $reason=trim((string)($body['reason']??''));if(strlen($reason)<10 || strlen($reason)>2000)throw new DomainException('Explain the documented salary basis (10–2,000 characters).',422);
  $pdo->beginTransaction();$q=$pdo->prepare('SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE');$q->execute([$r['EmployeeID']]);
  $q=$pdo->prepare("SELECT SalaryRatesID FROM salary_rates WHERE EmployeeID=? AND DeploymentID=? AND CompanyID=? AND IsActive=1 AND EffectiveDate<=? AND (EndDate IS NULL OR EndDate>=?)");$q->execute([$r['EmployeeID'],$r['DeploymentID'],$r['CompanyID'],$end?:'9999-12-31',$start]);
  if($q->fetch())throw new DomainException('An active rate already overlaps these dates. Historical rates are not overwritten by this action.',409);
  $q=$pdo->prepare('INSERT INTO salary_rates(EmployeeID,DeploymentID,CompanyID,EffectiveDate,EndDate,DailyRate,HourlyRate,IsActive,CreatedBy) VALUES(?,?,?,?,?,?,?,1,?)');$q->execute([$r['EmployeeID'],$r['DeploymentID'],$r['CompanyID'],$start,$end?:null,$config['daily_rate'],$config['hourly_rate'],$user]);$id=(int)$pdo->lastInsertId();
  if(!AuditLogger::log($pdo,'Salary history','Create','Add Back salary rate #'.$id.'; effective '.$start.'; '.$reason,(string)$r['EmployeeID']))throw new RuntimeException('Salary audit failed.');
  $pdo->commit();
 }
 echo json_encode(['success'=>true,'context'=>$r,'csrf_token'=>$_SESSION['add_back_csrf'],'message'=>$post?'Documented salary history recorded. Accounting can refresh the Add Back review.':null]);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();http_response_code($e instanceof DomainException?($e->getCode()?:422):500);if(!$e instanceof DomainException)error_log('salary history: '.$e->getMessage());echo json_encode(['success'=>false,'message'=>$e instanceof DomainException?$e->getMessage():'Salary history could not be saved.']);}
