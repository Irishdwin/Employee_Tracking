<?php
require_once __DIR__.'/AuditLogger.php';
// REMOTE_ADDR deliberately ignores untrusted forwarded headers. Atomic fixed windows.
function auth_rate_limit(mysqli $db, string $key, int $limit, int $seconds, bool $lockOnThreshold=false): int {
    $key=hash('sha256',$key); $now=time(); $expiry=$now+$seconds;
    $db->begin_transaction();
    try {
        $s=$db->prepare('INSERT INTO auth_rate_limits(BucketHash,Attempts,ExpiresAt) VALUES (?,0,?) ON DUPLICATE KEY UPDATE BucketHash=VALUES(BucketHash)');$s->bind_param('si',$key,$expiry);$s->execute();
        $s=$db->prepare('SELECT Attempts,ExpiresAt FROM auth_rate_limits WHERE BucketHash=? FOR UPDATE');$s->bind_param('s',$key);$s->execute();$r=$s->get_result()->fetch_assoc();
        $n=(int)$r['Attempts'];$end=(int)$r['ExpiresAt']; if($end<=$now){$n=0;$end=$expiry;}
        $wait=$n >= $limit ? max(1,$end-$now):0; if(!$wait){$n++;if($lockOnThreshold && $n >= $limit){$end=$expiry;$wait=$seconds;}}
        $s=$db->prepare('UPDATE auth_rate_limits SET Attempts=?,ExpiresAt=? WHERE BucketHash=?');$s->bind_param('iis',$n,$end,$key);$s->execute();$db->commit();return $wait;
    } catch(Throwable $e){$db->rollback();throw $e;}
}
function auth_password_login(mysqli $db,string $identifier,string $password,?string $requiredRole=null): array {
    $invalid=['success'=>false,'message'=>'Invalid username or password.','code'=>'invalid_credentials','retry_after'=>0];
    $locked=function(int $wait){return ['success'=>false,'message'=>'Too many unsuccessful login attempts. Please try again later or use Forgot Password.','code'=>'login_locked','retry_after'=>$wait];};
    $wait=auth_rate_limit($db,'login-ip:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'),60,300);if($wait)return $locked($wait);
    $db->begin_transaction();
    try {
        $s=$db->prepare("SELECT u.UsersID AS user_id,u.Email AS email,u.Password AS password_hash,u.FullName AS full_name,r.RoleName AS role,u.IsActive,u.FailedLoginAttempts,UNIX_TIMESTAMP(u.LoginLockedUntil) AS locked_until FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE LOWER(TRIM(u.Email))=LOWER(TRIM(?)) OR EXISTS(SELECT 1 FROM employee e WHERE e.Email=u.Email AND TRIM(e.EmployeeNumber)=TRIM(?)) LIMIT 1 FOR UPDATE");
        $s->bind_param('ss',$identifier,$identifier);$s->execute();$r=$s->get_result()->fetch_assoc();
        if(!$r){$db->commit();$wait=auth_rate_limit($db,'login-unknown:'.strtolower(trim($identifier)),5,300,true);if($wait)return $locked($wait);password_verify($password,'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');AuditLogger::log($db,'Auth','Failed Login','Password authentication rejected.');return $wait?$locked($wait):$invalid;}
        $uid=(int)$r['user_id'];$now=time();$end=(int)$r['locked_until'];
        if($end>$now){$db->commit();return $locked($end-$now);}
        $count=$end?0:(int)$r['FailedLoginAttempts'];$stored=(string)$r['password_hash'];
        $modern=!empty(password_get_info($stored)['algo']);
        $valid=$stored!=='' && ($modern?password_verify($password,$stored):hash_equals($stored,$password));
        $valid=$valid && (int)$r['IsActive']===1 && ($requiredRole===null || $r['role']===$requiredRole);
        if($valid){$hash=$modern?$stored:password_hash($password,PASSWORD_DEFAULT);$s=$db->prepare('UPDATE users SET Password=?,LocalPasswordConfigured=1,FailedLoginAttempts=0,LoginLockedUntil=NULL WHERE UsersID=?');$s->bind_param('si',$hash,$uid);$s->execute();$db->commit();return ['success'=>true,'user'=>$r];}
        $count++;$end=$count>=5?$now+300:null;
        $s=$db->prepare('UPDATE users SET FailedLoginAttempts=?,LoginLockedUntil=FROM_UNIXTIME(?) WHERE UsersID=?');$s->bind_param('iii',$count,$end,$uid);$s->execute();$db->commit();
        AuditLogger::log($db,'Auth',$end?'Temporary Lockout':'Failed Login','Password authentication rejected.',(string)$uid);
        return $end?$locked(300):$invalid;
    }catch(Throwable $e){$db->rollback();throw $e;}
}
