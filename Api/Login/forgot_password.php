<?php
require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/send_mail.php';
require_once __DIR__ . '/../Helpers/password_policy.php';
require_once __DIR__ . '/../Helpers/employee_password_history.php';

require_once __DIR__.'/../Helpers/auth_password_protection.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

function fp_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// fp_validate_password() now delegates to the shared, authoritative
// validate_strong_password() in Api/Helpers/password_policy.php, which also
// rejects common/predictable passwords (not just format-only checks).
function fp_validate_password(string $password): ?string {
    return validate_strong_password($password);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fp_json(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$action = trim((string)($_POST['action'] ?? ''));

// Settings resets are bound to the signed-in Employee's registered account.
if(in_array($_POST['context'] ?? '',['employee_settings','teamlead_settings'],true)) {
    $isTeamLead=$_POST['context']==='teamlead_settings';
    $allowedRoles=$isTeamLead?['TeamLead','Team Lead']:['Employee'];
    $userId=(int)($_SESSION['user_id'] ?? 0);
    $csrf=$_POST['csrf_token'] ?? null;
    if(!$userId || !in_array($_SESSION['role'] ?? '',$allowedRoles,true) || !is_string($csrf) || !isset($_SESSION['employee_password_csrf']) || !hash_equals($_SESSION['employee_password_csrf'],$csrf))
        fp_json(['success'=>false,'message'=>'Reopen Settings and try again.'],403);
    $roleCondition=$isTeamLead ? "r.RoleName IN ('TeamLead','Team Lead')" : "r.RoleName='Employee' AND EXISTS(SELECT 1 FROM employee e WHERE LOWER(TRIM(e.Email))=LOWER(TRIM(u.Email)))";
    $stmt=$conn->prepare("SELECT u.Email FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND {$roleCondition}");
    $stmt->bind_param('i',$userId);$stmt->execute();$account=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$account)fp_json(['success'=>false,'message'=>'Employee account is unavailable.'],403);
    $_POST['email']=$account['Email'];
}


if ($action === 'send_code') {
    $emailRaw = trim((string)($_POST['email'] ?? ''));
    $email = strtolower($emailRaw);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fp_json(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
    }

    $now=time();
    $ip=$_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $wait=auth_rate_limit($conn,'reset-ip:'.$ip,10,3600);
    $emailWait=auth_rate_limit($conn,'reset-email:'.$email,1,60);
    $hourWait=auth_rate_limit($conn,'reset-email-hour:'.$email,3,3600);
    if($wait || $emailWait || $hourWait)fp_json(['success'=>true,'message'=>'If the account information is valid, password reset instructions will be sent.']);
    $stmt=$conn->prepare('SELECT UsersID FROM users WHERE LOWER(TRIM(Email))=? AND IsActive=1 LIMIT 1');
    $stmt->bind_param('s',$email);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();$stmt->close();
    $code = (string)random_int(100000, 999999);
    $subject = 'Onazul Password Reset Code';
    $body = "Your Onazul password reset code is: {$code}\n\n"
        . "This code expires in 10 minutes.\n"
        . "If you did not request this, please ignore this email.";

    $secret=bin2hex(random_bytes(32));$requestHash=hash('sha256',$secret);$uid=$user?(int)$user['UsersID']:null;
    $codeHash=password_hash($code,PASSWORD_DEFAULT);$expires=$now+600;
    $conn->begin_transaction();
    try {
        if($uid){
            $q=$conn->prepare('SELECT UsersID FROM users WHERE UsersID=? FOR UPDATE');$q->bind_param('i',$uid);$q->execute();$q->get_result()->free();$q->close();
            $q=$conn->prepare('UPDATE password_reset_requests SET Consumed=1 WHERE UserID=?');$q->bind_param('i',$uid);$q->execute();
        }
        $q=$conn->prepare('INSERT INTO password_reset_requests(RequestHash,UserID,Email,CodeHash,ExpiresAt) VALUES (?,?,?,?,?)');$q->bind_param('sissi',$requestHash,$uid,$email,$codeHash,$expires);$q->execute();$conn->commit();
    }catch(Throwable $e){$conn->rollback();fp_json(['success'=>false,'message'=>'Unable to process request. Try again later.'],503);}
    $_SESSION['forgot_password_otp']=['email'=>$email,'user_id'=>$uid,'request_secret'=>$secret,'verified'=>false,'expires_at'=>$expires];
    AuditLogger::log($conn,'Auth','Password Reset Requested','Password reset request processed.');
    // Finish the generic response before SMTP to avoid an account-existence timing signal.
    session_write_close();ignore_user_abort(true);
    $response=json_encode(['success'=>true,'message'=>'If the account information is valid, password reset instructions will be sent.']);
    header('Content-Length: '.strlen($response));echo $response;
    if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();
    else {while(ob_get_level()>0)ob_end_flush();flush();}
    if($user){$mailRes=send_mail_smtp($email,$subject,$body,true);if(empty($mailRes['sent']))error_log('Password reset email delivery failed.');}
    exit;

}

if ($action === 'verify_code') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $code = trim((string)($_POST['code'] ?? ''));
    if ($email === '' || $code === '') {
        fp_json(['success' => false, 'message' => 'Email and code are required.'], 400);
    }
    if (!preg_match('/\A[0-9]{6}\z/D', $code)) {
        fp_json(['success' => false, 'message' => 'Enter the 6-digit code.'], 400);
    }

    $otp=$_SESSION['forgot_password_otp'] ?? [];
    $requestHash=hash('sha256',(string)($otp['request_secret'] ?? ''));
    $conn->begin_transaction();
    try {
        $q=$conn->prepare('SELECT * FROM password_reset_requests WHERE RequestHash=? FOR UPDATE');$q->bind_param('s',$requestHash);$q->execute();$record=$q->get_result()->fetch_assoc();
        $valid=$record && !$record['Consumed'] && (int)$record['ExpiresAt']>time() && (int)$record['Attempts']<5 && $record['Email']===$email;
        if(!$valid){$conn->rollback();fp_json(['success'=>false,'message'=>'Invalid or expired code. Request a new code.'],400);}
        $ok=password_verify($code,$record['CodeHash']) && $record['UserID']!==null;
        $q=$conn->prepare('UPDATE password_reset_requests SET Attempts=Attempts+1,Verified=? WHERE RequestHash=?');$flag=$ok?1:0;$q->bind_param('is',$flag,$requestHash);$q->execute();$conn->commit();
        if(!$ok)fp_json(['success'=>false,'message'=>'Invalid or expired code.'],400);
        session_regenerate_id(true);$otp['verified']=true;$otp['verified_at']=time();$_SESSION['forgot_password_otp']=$otp;
        fp_json(['success'=>true,'message'=>'Code verified. You can now set a new password.']);
    }catch(Throwable $e){$conn->rollback();fp_json(['success'=>false,'message'=>'Unable to verify code.'],503);}
}

if ($action === 'reset_password') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($email === '' || $newPassword === '' || $confirmPassword === '') {
        fp_json(['success' => false, 'message' => 'Email, new password, and confirm password are required.'], 400);
    }
    if ($newPassword !== $confirmPassword) {
        fp_json(['success' => false, 'message' => 'Passwords do not match.'], 400);
    }
    if(strlen($newPassword)>72 || strpos($newPassword,"\0")!==false)fp_json(['success'=>false,'message'=>'Password must be no longer than 72 bytes and contain no null characters.'],400);
    $policyErr = fp_validate_password($newPassword);
    if ($policyErr !== null) {
        fp_json(['success' => false, 'message' => $policyErr], 400);
    }

    $otp = $_SESSION['forgot_password_otp'] ?? null;
    if (!is_array($otp) || empty($otp['verified']) || strtolower((string)($otp['email'] ?? '')) !== $email) {
        fp_json(['success' => false, 'message' => 'Please verify your reset code first.'], 400);
    }
    if (time() >= (int)($otp['expires_at'] ?? 0)) {
        unset($_SESSION['forgot_password_otp']);
        fp_json(['success' => false, 'message' => 'Verification expired. Start again.'], 400);
    }

    $conn->begin_transaction();
    try {
        $stmt=$conn->prepare("SELECT u.UsersID,u.Password,r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE LOWER(TRIM(u.Email))=LOWER(TRIM(?)) LIMIT 1 FOR UPDATE");
        $stmt->bind_param('s',$email);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();$stmt->close();
        if(!$user || (int)$user['UsersID']!==(int)($otp['user_id'] ?? 0)) {
            $conn->rollback();fp_json(['success'=>false,'message'=>'Please verify your reset code again.'],400);
        }
        $uid=(int)$user['UsersID'];
        $requestHash=hash('sha256',(string)($otp['request_secret'] ?? ''));
        $q=$conn->prepare('SELECT * FROM password_reset_requests WHERE RequestHash=? FOR UPDATE');$q->bind_param('s',$requestHash);$q->execute();$record=$q->get_result()->fetch_assoc();
        if(!$record || !$record['Verified'] || $record['Consumed'] || (int)$record['ExpiresAt']<=time() || (int)$record['UserID']!==$uid || $record['Email']!==$email){$conn->rollback();fp_json(['success'=>false,'message'=>'Verification expired. Start again.'],400);}

        if(in_array($user['RoleName'],['Employee','TeamLead','Team Lead'],true) && employee_password_reused($conn,$uid,$newPassword,(string)$user['Password'])) {
            $conn->rollback();fp_json(['success'=>false,'message'=>'You have already used this password. Choose a new password you have never used before.'],422);
        }
        $hash=password_hash($newPassword,PASSWORD_DEFAULT);
        if(in_array($user['RoleName'],['Employee','TeamLead','Team Lead'],true))employee_password_record($conn,$uid,(string)$user['Password'],$hash);
        $upd=$conn->prepare('UPDATE users SET Password=?,LocalPasswordConfigured=1,FailedLoginAttempts=0,LoginLockedUntil=NULL WHERE UsersID=?');
        $upd->bind_param('si',$hash,$uid);$upd->execute();$upd->close();$q=$conn->prepare('UPDATE password_reset_requests SET Consumed=1 WHERE UserID=?');$q->bind_param('i',$uid);$q->execute();$conn->commit();
    } catch(Throwable $e) {
        $conn->rollback();error_log('Password reset failed.');
        fp_json(['success'=>false,'message'=>'Unable to update password. Please try again.'],500);
    }

    unset($_SESSION['forgot_password_otp']);
    AuditLogger::log($conn,'Auth','Password Reset Completed','Password changed after email verification.',(string)$uid);
    session_regenerate_id(true);session_write_close();
    $mailRes=send_mail_smtp($email,'Onazul password changed','Your Onazul password was changed. If this was not you, contact the agency immediately.',true);
    if(empty($mailRes['sent']))error_log('Password change notification delivery failed.');
    fp_json(['success' => true, 'message' => 'Password reset successful. You can now login.']);
}

fp_json(['success' => false, 'message' => 'Invalid action.'], 400);

