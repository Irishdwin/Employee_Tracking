<?php
declare(strict_types=1);
/** Caller must hold the users row lock until the password update commits. */
function employee_password_reused(mysqli $db,int $user,string $candidate,string $current): bool {
    if($current!=='' && (!empty(password_get_info($current)['algo']) ? password_verify($candidate,$current) : hash_equals($current,$candidate)))return true;
    $stmt=$db->prepare('SELECT PasswordHash FROM employee_password_history WHERE UserID=?');
    $stmt->bind_param('i',$user);$stmt->execute();$rows=$stmt->get_result();
    while($row=$rows->fetch_assoc())if(password_verify($candidate,$row['PasswordHash'])){$stmt->close();return true;}
    $stmt->close();return false;
}
function employee_password_record(mysqli $db,int $user,string $current,string $nextHash): void {
    $hashes=[$nextHash];
    if($current!=='')$hashes[]=!empty(password_get_info($current)['algo'])?$current:password_hash($current,PASSWORD_DEFAULT);
    $stmt=$db->prepare('INSERT IGNORE INTO employee_password_history (UserID,PasswordHash) VALUES(?,?)');
    foreach($hashes as $hash){$stmt->bind_param('is',$user,$hash);$stmt->execute();}
    $stmt->close();
}
