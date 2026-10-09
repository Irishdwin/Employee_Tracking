<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
function security_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}
if (empty($_SESSION['user_id']) || !in_array((string)($_SESSION['role'] ?? ''), ACCOUNT_SECURITY_ROLES, true)) {
    security_response(['success'=>false,'message'=>'Account authentication required.'], 403);
}
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/password_policy.php';
require_once __DIR__ . '/../Helpers/employee_password_history.php';
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET','POST'], true)) security_response(['success'=>false,'message'=>'Method not allowed.'],405);
$_SESSION['employee_password_csrf'] ??= bin2hex(random_bytes(32));
try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) security_response(['success'=>false,'message'=>'Invalid request.'],400);
        foreach (['UserID','EmployeeID','user_id','employee_id','account_id','role','password_state'] as $key) {
            if (array_key_exists($key,$input)) security_response(['success'=>false,'message'=>'Account identifiers are not accepted.'],400);
        }
        $csrf = $input['csrf_token'] ?? null;
        if (!is_string($csrf) || !hash_equals($_SESSION['employee_password_csrf'],$csrf)) {
            security_response(['success'=>false,'message'=>'Please reopen Settings and try again.'],403);
        }
        $attempt = $_SESSION['employee_password_attempt'] ?? ['count'=>0,'since'=>time()];
        if (time() - $attempt['since'] >= 300) $attempt = ['count'=>0,'since'=>time()];
        if ($attempt['count'] >= 5) security_response(['success'=>false,'message'=>'Too many attempts. Please try again in five minutes.'],429);
        $conn->begin_transaction();
    }
    $roleCondition=ACCOUNT_SECURITY_TEAMLEAD ? "r.RoleName IN ('TeamLead','Team Lead')" : "r.RoleName='Employee' AND EXISTS (SELECT 1 FROM employee e WHERE LOWER(TRIM(e.Email))=LOWER(TRIM(u.Email)))";
    $stmt = $conn->prepare("SELECT u.Password,u.Email,u.LocalPasswordConfigured FROM users u INNER JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND {$roleCondition}" . ($method==='POST' ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i',$userId);
    $stmt->execute();
    $account=$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account) {
        if ($method==='POST') $conn->rollback();
        security_response(['success'=>false,'message'=>'Account is unavailable.'],403);
    }
    $otp=$_SESSION['forgot_password_otp']??null;
    $otpAuthorized=is_array($otp) && !empty($otp['verified']) && (int)($otp['user_id']??0)===$userId
        && strcasecmp((string)($otp['email']??''),(string)$account['Email'])===0
        && !empty($otp['verified_at']) && time()-(int)$otp['verified_at']<=600;
    if($otpAuthorized){
        $requestHash=hash('sha256',(string)($otp['request_secret'] ?? ''));
        $q=$conn->prepare('SELECT UserID,Verified,Consumed,ExpiresAt FROM password_reset_requests WHERE RequestHash=?'.($method==='POST'?' FOR UPDATE':''));
        $q->bind_param('s',$requestHash);$q->execute();$resetRecord=$q->get_result()->fetch_assoc();
        $otpAuthorized=$resetRecord && (int)$resetRecord['UserID']===$userId && $resetRecord['Verified'] && !$resetRecord['Consumed'] && (int)$resetRecord['ExpiresAt']>time();
    }
    $google=$_SESSION['verified_google_login']??null;
    $googleAuthorized=$account['LocalPasswordConfigured']===null && is_array($google)
        && (int)($google['user_id']??0)===$userId && time()-(int)($google['at']??0)<=600;
    $requiresSetup = ($account['LocalPasswordConfigured'] !== null && (int)$account['LocalPasswordConfigured']===0)
        || $otpAuthorized || $googleAuthorized;
    $setupNoticeRequired=$account['LocalPasswordConfigured']!==null && (int)$account['LocalPasswordConfigured']===0;
    if ($method==='GET') {
        $firstPrompt=false;
        if($setupNoticeRequired) {
            require_once __DIR__.'/../Helpers/notification_helper.php';
            $conn->begin_transaction();
            createEmployeePasswordNotification($conn,$userId);
            $notice=$conn->prepare("SELECT 1 FROM notifications WHERE UserID=? AND NotificationType='employee_password_setup' AND IsRead=0 LIMIT 1");
            $notice->bind_param('i',$userId);$notice->execute();$firstPrompt=(bool)$notice->get_result()->num_rows;$notice->close();
            $conn->commit();
        }
        security_response(['success'=>true,'setup_required'=>$requiresSetup,'reset_authorized'=>$otpAuthorized && !$setupNoticeRequired,'setup_notice_required'=>$setupNoticeRequired,'first_visit_prompt'=>$firstPrompt,'state_unknown'=>$account['LocalPasswordConfigured']===null,'email'=>$account['Email'],'csrf_token'=>$_SESSION['employee_password_csrf']]);
    }
    if(($input['action']??'')==='acknowledge_setup_prompt') {
        $showPrompt=false;
        if($setupNoticeRequired) {
            $read=$conn->prepare("UPDATE notifications SET IsRead=1 WHERE UserID=? AND NotificationType='employee_password_setup' AND IsRead=0");
            $read->bind_param('i',$userId);$read->execute();$showPrompt=$read->affected_rows>0;$read->close();
        }
        $conn->commit();
        security_response(['success'=>true,'show_prompt'=>$showPrompt]);
    }

    $action=$input['action']??'';
    $new=$input['new_password']??null;
    $confirm=$input['confirm_password']??null;
    $error=null;
    $status=422;
    if (!in_array($action,['setup','change'],true)) $error='Invalid password action.';
    elseif (($action==='setup')!==$requiresSetup) { $error='Password status changed. Reopen Settings and try again.'; $status=409; }
    elseif (!is_string($new)||!is_string($confirm)) $error='New password and confirmation are required.';
    elseif ($new!==$confirm) $error='New password and confirmation do not match.';
    elseif (strlen($new)>72) $error='Password must be no longer than 72 bytes.';
    elseif (strpos($new,"\0")!==false) $error='Password contains an unsupported character.';
    else $error=validate_strong_password($new);
    if (!$error && $action==='change') {
        $old=$input['old_password']??null;
        $stored=(string)$account['Password'];
        $valid=is_string($old) && $old!=='' && (!empty(password_get_info($stored)['algo']) ? password_verify($old,$stored) : hash_equals($stored,$old));
        if (!$valid) {
            $attempt['count']++;
            $_SESSION['employee_password_attempt']=$attempt;
            $error='Current password is incorrect.';
        }
    }
    if (!$error && employee_password_reused($conn,$userId,$new,(string)$account['Password'])) {
        $conn->rollback();
        security_response(['success'=>false,'code'=>'password_reused','message'=>'You have already used this password. Choose a new password you have never used before.'],422);
    }
    if ($error) { $conn->rollback(); security_response(['success'=>false,'message'=>$error],$status); }
    $hash=password_hash($new,PASSWORD_DEFAULT);
    employee_password_record($conn,$userId,(string)$account['Password'],$hash);
    $update=$conn->prepare('UPDATE users SET Password=?, LocalPasswordConfigured=1,FailedLoginAttempts=0,LoginLockedUntil=NULL WHERE UsersID=?');
    $update->bind_param('si',$hash,$userId);
    $update->execute();
    $update->close();
    $read=$conn->prepare("UPDATE notifications SET IsRead=1 WHERE UserID=? AND NotificationType='employee_password_setup'");
    $read->bind_param('i',$userId); $read->execute(); $read->close();
    $q=$conn->prepare('UPDATE password_reset_requests SET Consumed=1 WHERE UserID=?');$q->bind_param('i',$userId);$q->execute();
    $conn->commit();
    session_regenerate_id(true);
    unset($_SESSION['employee_password_attempt'],$_SESSION['forgot_password_otp'],$_SESSION['verified_google_login']);
    $_SESSION['employee_password_csrf']=bin2hex(random_bytes(32));
    require_once __DIR__.'/AuditLogger.php';
    AuditLogger::log($conn,'Auth',$otpAuthorized?'Password Reset Completed':'Password Changed','Account password updated.',(string)$userId);
    if($otpAuthorized){require_once __DIR__.'/send_mail.php';$mailRes=send_mail_smtp($account['Email'],'Onazul password changed','Your Onazul password was changed. If this was not you, contact the agency immediately.',true);if(empty($mailRes['sent']))error_log('Password change notification delivery failed.');}
    security_response(['success'=>true,'message'=>$action==='setup'?($otpAuthorized && !$setupNoticeRequired?'Your password has been reset successfully.':'Your password has been set successfully.'):'Your password has been changed successfully.']);
} catch (Throwable $e) {
    if ($method==='POST') $conn->rollback();
    error_log('Employee password operation failed.');
    security_response(['success'=>false,'message'=>'Unable to update account security. Please try again.'],500);
}
