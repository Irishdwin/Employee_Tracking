<?php
require __DIR__.'/../../Api/Db_connection/db_connect.php';
require __DIR__.'/../../Api/Helpers/applicant_profile_validation.php';
require __DIR__.'/../../Api/Helpers/psgc_address.php';
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
$b=array_values(array_filter(psgc_items('barangays','1030500000'),fn($n)=>preg_match('/^Barangay 10\b/',$n['name'])))[0];
$p=['first_name'=>'Juan','last_name'=>'Dela Cruz','birth_date'=>'2000-01-01','street_address'=>'phase 1','postal_code'=>'9000','category_id'=>'1','classification_id'=>'1','sub_classification_id'=>'11','address_psgc'=>['region'=>'1000000000','province'=>'','city'=>'1030500000','barangay'=>$b['id']]];
verify(applicant_profile_errors($p)===[],'complete CDO required fields valid');
$p['postal_code']='';verify(array_keys(applicant_profile_errors($p))===['postal_code'],'only Postal empty marks only Postal');
$p['street_address']='';$p['address_psgc']['barangay']='';$errors=applicant_profile_errors($p);verify(count($errors)===3 && isset($errors['postal_code'],$errors['street_address'],$errors['barangay']),'all three missing fields reported');
$p['postal_code']='9000';$p['street_address']='phase 1';$p['address_psgc']['barangay']=$b['id'];$p['address_psgc']['region']='1300000000';$errors=applicant_profile_errors($p);verify(isset($errors['city']),'invalid PSGC mapped to City field');
$manila=psgc_items('barangays','1380600000')[0];$p['address_psgc']=['region'=>'1300000000','province'=>'','city'=>'1380600000','barangay'=>$manila['id']];verify(applicant_profile_errors($p)===[],'NCR does not require fake Province');
