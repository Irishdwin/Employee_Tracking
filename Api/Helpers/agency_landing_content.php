<?php
/** Public landing content is plain text and explicitly allowlisted. */
const AGENCY_LANDING_FIELDS = [
 'company_name'=>150,'tagline'=>180,'about'=>3000,'mission'=>1000,'applicant_guidance'=>2000,
 'office_address'=>500,'office_hours'=>300,'website'=>500,'facebook'=>500,
];
function agency_landing_defaults(): array { return array_merge(array_fill_keys(array_keys(AGENCY_LANDING_FIELDS),''),['company_name'=>'Onazul Manpower Services','tagline'=>'Your next step starts here.','about'=>'Explore available roles with Onazul Manpower Services. Create your applicant profile, keep your documents up to date, and follow your application from submission to the next step.']); }
function agency_landing_validate(array $input): array {
 $values=[]; foreach(AGENCY_LANDING_FIELDS as $field=>$limit){if(!array_key_exists($field,$input))continue;if(!is_string($input[$field]))throw new DomainException('Enter valid text for '.str_replace('_',' ',$field).'.');$v=trim($input[$field]);if(mb_strlen($v)>$limit)throw new DomainException(str_replace('_',' ',$field).' exceeds '.$limit.' characters.');if(in_array($field,['website','facebook'],true)&&$v!==''&&(!filter_var($v,FILTER_VALIDATE_URL)||strtolower((string)parse_url($v,PHP_URL_SCHEME))!=='https'))throw new DomainException('Website and Facebook links must use a valid HTTPS URL.');$values[$field]=$v; }return $values;
}
function agency_landing_read(mysqli $conn): array {
 $values=agency_landing_defaults();$result=$conn->query("SELECT SettingKey,SettingValue FROM systemsettings WHERE Category='agency_contact' AND SettingKey LIKE 'agency_landing_%'");while($row=$result->fetch_assoc()){$field=substr($row['SettingKey'],strlen('agency_landing_'));if(array_key_exists($field,AGENCY_LANDING_FIELDS))$values[$field]=(string)$row['SettingValue'];}return $values;
}
function agency_landing_save(mysqli $conn,array $values,int $userId): void {
 foreach($values as $field=>$value){if(!array_key_exists($field,AGENCY_LANDING_FIELDS))continue;$key='agency_landing_'.$field;$check=$conn->prepare('SELECT SettingID FROM systemsettings WHERE SettingKey=? LIMIT 1');$check->bind_param('s',$key);$check->execute();$exists=$check->get_result()->fetch_assoc();$check->close();
 if($exists){$s=$conn->prepare('UPDATE systemsettings SET SettingValue=?,UpdatedBy=? WHERE SettingKey=?');$s->bind_param('sis',$value,$userId,$key);}else{$s=$conn->prepare("INSERT INTO systemsettings (SettingKey,SettingValue,SettingType,Category,IsEditable,UpdatedBy) VALUES (?,?,'text','agency_contact',1,?)");$s->bind_param('ssi',$key,$value,$userId);}$s->execute();$s->close(); }
}
