<?php
/** Field errors only: existing authoritative hierarchy validation still runs before saving. */
function applicant_profile_errors(array $input): array {
    $errors=[];
    if(!isset($input['gender'])||!is_string($input['gender'])||!in_array($input['gender'],['Male','Female','Other','Prefer not to say'],true))$errors['gender']='Please select your sex.';
    $value=fn($key)=>isset($input[$key]) && is_scalar($input[$key]) ? trim((string)$input[$key]) : '';
    foreach(['first_name'=>'First Name','last_name'=>'Last Name','middle_name'=>'Middle Name'] as $key=>$label){
        $v=$value($key);
        if($v==='' && $key!=='middle_name')$errors[$key]="$label is required.";
        elseif($v!=='' && (!preg_match("/^[A-Za-zÑñ\\s.'\\-]+$/u",$v) || ($key!=='middle_name' && mb_strlen($v)<2)))$errors[$key]="$label contains an invalid name.";
    }
    $birth=$value('birth_date');$date=DateTimeImmutable::createFromFormat('!Y-m-d',$birth);
    if($birth==='')$errors['birth_date']='Date of Birth is required.';
    elseif(!$date || $date->format('Y-m-d')!==$birth || $date>new DateTimeImmutable('today'))$errors['birth_date']='Please enter a valid Date of Birth.';
    elseif($date->diff(new DateTimeImmutable('today'))->y<18)$errors['birth_date']='You must be at least 18 years old to register.';
    $street=$value('street_address');$zip=$value('postal_code');
    if($street==='')$errors['street_address']='Street Address is required.';
    elseif(mb_strlen($street)>255 || preg_match('/[\x00-\x1f<>]/u',$street))$errors['street_address']='Please enter a valid Street Address (maximum 255 characters).';
    if($zip==='')$errors['postal_code']='Postal / ZIP Code is required.';
    elseif(!preg_match('/^\d{4}$/',$zip))$errors['postal_code']='Please enter a valid four-digit Postal / ZIP Code.';
    foreach(['category_id'=>['job_category','Job Category'],'classification_id'=>['preferred_classification','Classification'],'sub_classification_id'=>['preferred_sub_classification','Sub-classification']] as $key=>$pair){
        if($value($key)==='' && $value($pair[0])==='')$errors[$key]='Please select a '.$pair[1].'.';
    }
    if((int)($input['has_recent_experience']??0)===1){
        foreach(['job_title'=>'Job Title','company_name'=>'Company Name'] as $key=>$label)if($value($key)==='')$errors[$key]="$label is required when you have experience.";
        $keys=['experience_start_month','experience_start_year'];
        if(empty($input['still_in_role']))$keys=array_merge($keys,['experience_end_month','experience_end_year']);
        foreach($keys as $key)if(!$value($key))$errors[$key]='Please select your experience '.(str_contains($key,'start')?'start ':'end ').(str_contains($key,'month')?'month.':'year.');
    }
    require_once __DIR__.'/psgc_address.php';
    $codes=is_array($input['address_psgc']??null)?$input['address_psgc']:[];
    $node=function($key)use($codes){$v=$codes[$key]??'';return is_string($v)?psgc_record($v):null;};
    $r=$node('region');$p=$node('province');$c=$node('city');$b=$node('barangay');
    if(!$r || $r['type']!=='region')$errors['region']='Please select a Region.';
    if(!$c || !in_array($c['type'],['municipality','city','component_city','highly_urbanized_city','independent_component_city'],true))$errors['city']='Please select a City / Municipality.';
    elseif($r && $c['region']!==$r['id'])$errors['city']='The City / Municipality does not belong to the selected Region.';
    if($c && $c['province'] && (!$p || $p['id']!==$c['province']))$errors['state_province']='Please select the Province / geographic path for this City / Municipality.';
    elseif($c && !$c['province'] && !empty($codes['province']))$errors['state_province']='This City / Municipality does not require an administrative Province.';
    if(!$b || $b['type']!=='barangay')$errors['barangay']='Please select a Barangay.';
    elseif($c && $b['city']!==$c['id'])$errors['barangay']='The Barangay does not belong to the selected City / Municipality.';
    return $errors;
}
