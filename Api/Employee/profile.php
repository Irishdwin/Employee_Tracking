<?php
require_once __DIR__.'/../Common/session_init.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
function employee_profile_reply(array $data,int $code=200): void {http_response_code($code);echo json_encode($data);exit;}
if(empty($_SESSION['user_id'])||($_SESSION['role']??'')!=='Employee')employee_profile_reply(['success'=>false,'message'=>'Employee login required.'],403);
require __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/psgc_address.php';
$uid=(int)$_SESSION['user_id'];
$_SESSION['employee_profile_csrf']??=bin2hex(random_bytes(32));
try {
 $q=$conn->prepare("SELECT a.ApplicantID,a.MobileNumber,a.BirthDate,a.Gender,a.Address,a.HomeLocation,a.StreetAddress,a.Region,a.StateProvince,a.City,a.Barangay,a.PostalCode,a.AddressPSGC FROM applicants a JOIN users u ON u.UsersID=a.UserID JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND r.RoleName='Employee' AND EXISTS(SELECT 1 FROM employee e WHERE LOWER(TRIM(e.Email))=LOWER(TRIM(u.Email)) AND e.IsActive=1)");
 $q->bind_param('i',$uid);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
 if(!$row)employee_profile_reply(['success'=>false,'message'=>'Linked Employee profile not found.'],403);
 if($_SERVER['REQUEST_METHOD']==='GET') {
  $codes=json_decode((string)$row['AddressPSGC'],true);
  $profile=['mobile_number'=>$row['MobileNumber'],'birth_date'=>$row['BirthDate'],'gender'=>$row['Gender'],'address'=>$row['Address'],'street_address'=>$row['StreetAddress'],'region'=>$row['Region'],'state_province'=>$row['StateProvince'],'city'=>$row['City'],'barangay'=>$row['Barangay'],'postal_code'=>$row['PostalCode'],'address_psgc'=>$codes,'address_selector_province'=>psgc_selector_groups()[$codes['city']??'']??($codes['province']??'')];
  employee_profile_reply(['success'=>true,'profile'=>$profile,'csrf_token'=>$_SESSION['employee_profile_csrf']]);
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')employee_profile_reply(['success'=>false,'message'=>'Method not allowed.'],405);
 $input=json_decode(file_get_contents('php://input'),true);
 if(!is_array($input))employee_profile_reply(['success'=>false,'message'=>'Invalid request.'],400);
 foreach(array_keys($input)as$key)if(!in_array($key,['csrf_token','mobile_number','birth_date','gender','update_address','address_psgc','street_address','postal_code'],true))employee_profile_reply(['success'=>false,'message'=>'This profile field cannot be edited.'],400);
 if(!is_string($input['csrf_token']??null)||!hash_equals($_SESSION['employee_profile_csrf'],$input['csrf_token']))employee_profile_reply(['success'=>false,'message'=>'Reopen Edit Profile and try again.'],403);
 $errors=[];
 foreach(['mobile_number','birth_date','gender','street_address','postal_code']as$key)if(isset($input[$key])&&!is_string($input[$key]))$errors[$key]='Invalid value.';
 if($errors)employee_profile_reply(['success'=>false,'message'=>'Please correct the highlighted fields.','errors'=>$errors],422);
 $mobile=trim($input['mobile_number']??'');$birth=trim($input['birth_date']??'');$gender=trim($input['gender']??'');
 if(!preg_match('/^(09\d{9}|\+639\d{9})$/',$mobile))$errors['mobile_number']='Use 09 followed by 9 digits or +639 followed by 9 digits.';
 if($birth!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$birth);if(!$date||$date->format('Y-m-d')!==$birth||$date>new DateTimeImmutable('today')||$date->diff(new DateTimeImmutable('today'))->y<18)$errors['birth_date']='Enter a valid birth date (18 years or older).';}
 if(!in_array($gender,['','Male','Female','Other','Prefer not to say'],true))$errors['gender']='Select a valid gender.';
 $address=null;
 if(($input['update_address']??false)===true) {
  $street=trim($input['street_address']??'');$postal=trim($input['postal_code']??'');
  if($street===''||mb_strlen($street)>255)$errors['street_address']='Enter a street address up to 255 characters.';
  if(!preg_match('/^\d{4}$/',$postal))$errors['postal_code']='Enter a 4-digit ZIP code.';
  try{$address=psgc_validate_address(is_array($input['address_psgc']??null)?$input['address_psgc']:[]);}catch(DomainException $e){$errors['address_psgc']=$e->getMessage();}
 }
 if($errors)employee_profile_reply(['success'=>false,'message'=>'Please correct the highlighted fields.','errors'=>$errors],422);
 $applicant=(int)$row['ApplicantID'];$birth=$birth!==''?$birth:null;$gender=$gender!==''?$gender:null;
 $conn->begin_transaction();
 $save=$conn->prepare('UPDATE applicants SET MobileNumber=?,BirthDate=?,Gender=? WHERE ApplicantID=? AND UserID=?');$save->bind_param('sssii',$mobile,$birth,$gender,$applicant,$uid);$save->execute();$save->close();
 if($address){
  $region=$address['region'];$province=$address['state_province'];$city=$address['city'];$barangay=$address['barangay'];$codes=json_encode($address['psgc']);$country='Philippines';
  $full=implode(', ',array_filter([$street,$barangay,$city,$province,$postal,$country]));$home=mb_substr(implode(', ',array_filter([$city,$province,$country])),0,150);
  $save=$conn->prepare('UPDATE applicants SET StreetAddress=?,Region=?,StateProvince=?,City=?,Barangay=?,PostalCode=?,Country=?,Address=?,HomeLocation=?,AddressPSGC=? WHERE ApplicantID=? AND UserID=?');
  $save->bind_param('ssssssssssii',$street,$region,$province,$city,$barangay,$postal,$country,$full,$home,$codes,$applicant,$uid);$save->execute();$save->close();
 }
 $conn->commit();
 require_once __DIR__.'/../Helpers/AuditLogger.php';AuditLogger::log($conn,'Employee','Profile Update','Employee updated their personal profile.',(string)$uid);
 employee_profile_reply(['success'=>true,'message'=>'Your profile has been updated successfully.']);
}catch(Throwable $e){$conn->rollback();error_log('Employee profile operation failed.');employee_profile_reply(['success'=>false,'message'=>'Unable to save your profile. Please try again.'],500);}
