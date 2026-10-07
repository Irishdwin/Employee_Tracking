<?php
require_once __DIR__.'/../Common/session_init.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
function employee_profile_reply(array $data,int $code=200): void {http_response_code($code);echo json_encode($data);exit;}
if(empty($_SESSION['user_id'])||!in_array($_SESSION['role']??'',['TeamLead','Team Lead'],true))employee_profile_reply(['success'=>false,'message'=>'Team Lead login required.'],403);
require __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/psgc_address.php';
$uid=(int)$_SESSION['user_id'];
$_SESSION['employee_profile_csrf']??=bin2hex(random_bytes(32));
try {
 $q=$conn->prepare("SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND r.RoleName IN ('TeamLead','Team Lead')");
 $q->bind_param('i',$uid);$q->execute();$authorized=$q->get_result()->fetch_assoc();$q->close();
 if(!$authorized)employee_profile_reply(['success'=>false,'message'=>'Team Lead account unavailable.'],403);
 $row=array_fill_keys(['MobileNumber','BirthDate','Gender','Address','HomeLocation','StreetAddress','Region','StateProvince','City','Barangay','PostalCode','AddressPSGC'],'');
 $q=$conn->prepare('SELECT ProfileJSON FROM teamlead_personal_profiles WHERE UserID=?');$q->bind_param('i',$uid);$q->execute();$saved=$q->get_result()->fetch_assoc();$q->close();
 if($saved)$row=array_merge($row,json_decode($saved['ProfileJSON'],true)?:[]);
 elseif($conn->query("SHOW TABLES LIKE 'employee_profiles'")->num_rows) { $q=$conn->prepare('SELECT address,birth_date FROM employee_profiles WHERE user_id=?');$q->bind_param('i',$uid);$q->execute();$p=$q->get_result()->fetch_assoc();$q->close();if($p){$row['Address']=$p['address'];$row['BirthDate']=$p['birth_date'];} }
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
 $row['MobileNumber']=$mobile;$row['BirthDate']=$birth;$row['Gender']=$gender;
 if($address){
  $row['StreetAddress']=$street;$row['PostalCode']=$postal;$row['AddressPSGC']=json_encode($address['psgc']);
  $row['Region']=$address['region'];$row['StateProvince']=$address['state_province'];$row['City']=$address['city'];$row['Barangay']=$address['barangay'];
  $row['Address']=implode(', ',array_filter([$street,$row['Barangay'],$row['City'],$row['StateProvince'],$postal,'Philippines']));
  $row['HomeLocation']=implode(', ',array_filter([$row['City'],$row['StateProvince'],'Philippines']));
 }
 $conn->begin_transaction();$json=json_encode($row);
 $save=$conn->prepare('INSERT INTO teamlead_personal_profiles (UserID,ProfileJSON) VALUES (?,?) ON DUPLICATE KEY UPDATE ProfileJSON=VALUES(ProfileJSON)');$save->bind_param('is',$uid,$json);$save->execute();$save->close();
 $conn->commit();
 require_once __DIR__.'/../Helpers/AuditLogger.php';AuditLogger::log($conn,'TeamLead','Profile Update','Team Lead updated their personal profile.',(string)$uid);
 employee_profile_reply(['success'=>true,'message'=>'Your profile has been updated successfully.']);
}catch(Throwable $e){$conn->rollback();error_log('Employee profile operation failed.');employee_profile_reply(['success'=>false,'message'=>'Unable to save your profile. Please try again.'],500);}
