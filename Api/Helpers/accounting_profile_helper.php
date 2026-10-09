<?php
declare(strict_types=1);
require_once __DIR__.'/payroll_workflow.php';
function accounting_profile_name(mysqli $conn,int $actor): string {
    $row=payroll_query($conn,'SELECT FullName FROM users WHERE UsersID=?','i',[$actor])->get_result()->fetch_assoc();
    if(!$row || trim($row['FullName'])==='')throw new InvalidArgumentException('Set your full name in My Account before exporting billing.');
    return trim($row['FullName']);
}
function accounting_profile_save(mysqli $conn,int $actor,array $input): string {
    foreach(['user_id','UserID','UsersID','accounting_user_id'] as $key)if(isset($input[$key]) && (string)$input[$key] !== (string)$actor)throw new RuntimeException('You can only edit your own profile.',403);
    $name=$input['full_name']??null;
    if(!is_string($name) || trim($name)==='' || mb_strlen(trim($name))>100 || preg_match('/[\x00-\x1F\x7F]/u',$name))throw new InvalidArgumentException('Enter a full name of 1–100 characters.');
    $name=trim($name);payroll_query($conn,'UPDATE users SET FullName=? WHERE UsersID=?','si',[$name,$actor]);return $name;
}
