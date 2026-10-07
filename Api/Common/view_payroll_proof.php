<?php
 declare(strict_types=1);
 require_once __DIR__.'/session_init.php';
 require __DIR__.'/../Db_connection/db_connect.php';
 require_once __DIR__.'/../Helpers/payroll_workflow.php';
 try {
     if(empty($_SESSION['user_id']))throw new RuntimeException('Sign in required.',403);
     $id=(int)($_GET['id'] ?? 0);$source=($_GET['source'] ?? '')==='run' ? 'run' : 'group';
     $actor=payroll_query($conn,'SELECT r.RoleName role,u.IsActive FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=?','i',[(int)$_SESSION['user_id']])->get_result()->fetch_assoc();
     if(!$actor || !(int)$actor['IsActive'])throw new RuntimeException('Sign in required.',403);
     if($source==='run') {
         $row=payroll_query($conn,'SELECT r.*,h.AttachmentPath path FROM payroll_status_history h JOIN payroll_runs r ON r.PayrollRunID=h.PayrollRunID WHERE h.HistoryID=?','i',[$id])->get_result()->fetch_assoc();
     } else {
         $row=payroll_query($conn,'SELECT *,ProofImagePath path FROM payroll_group_status_history WHERE PayrollStatusHistoryID=?','i',[$id])->get_result()->fetch_assoc();
     }
     if(!$row || !$row['path'])throw new RuntimeException('Proof not found.',404);
     $allowed=in_array($actor['role'],['Accounting','Admin'],true);
     if(!$allowed && $actor['role']==='Employee') {
         $start=$source==='run'?$row['PeriodStart']:$row['CutoffStartDate'];$end=$source==='run'?$row['PeriodEnd']:$row['CutoffEndDate'];
         $lead=(int)($row['SiteSupervisorID'] ?? 0);$site=$source==='run'?(int)($row['SiteID'] ?? 0):0;
         $allowed=(bool)payroll_query($conn,'SELECT ds.DTR_SubmissionID FROM dtr_submissions ds JOIN deployment d ON d.DeploymentID=ds.DeploymentID AND d.EmployeeID=ds.EmployeeID JOIN employee e ON e.EmployeeID=ds.EmployeeID JOIN users u ON LOWER(TRIM(u.Email))=LOWER(TRIM(e.Email)) WHERE u.UsersID=? AND d.CompanyID=? AND ds.CutoffStartDate=? AND ds.CutoffEndDate=? AND (?=0 OR ds.SiteSupervisorID=?) AND (?=0 OR ds.SiteID=?) LIMIT 1','iissiiii',[(int)$_SESSION['user_id'],(int)$row['CompanyID'],$start,$end,$lead,$lead,$site,$site])->get_result()->fetch_assoc();
     }
     if(!$allowed)throw new RuntimeException('You cannot view this payroll proof.',403);
     $root=dirname(__DIR__,2);$path=realpath($root.'/'.$row['path']);$permitted=false;
     foreach(['payroll_status_proof','payroll_proofs'] as $folder) {
         $base=realpath($root.'/uploads/'.$folder);
         if($base && $path && str_starts_with($path,$base.DIRECTORY_SEPARATOR))$permitted=true;
     }
     if(!$permitted || !is_file($path))throw new RuntimeException('Proof not found.',404);
     $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));$mimes=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf'];
     if(!isset($mimes[$ext]))throw new RuntimeException('Unsupported proof.',404);
     header('Content-Type: '.$mimes[$ext]);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Disposition: inline; filename="payroll-proof.'.$ext.'"');readfile($path);
 } catch(Throwable $e) {http_response_code(in_array($e->getCode(),[403,404],true)?$e->getCode():500);echo $e->getCode() ? $e->getMessage() : 'Unable to load payroll proof.';}
