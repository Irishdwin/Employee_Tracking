<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
function security_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}
if (empty($_SESSION['user_id']) || strcasecmp((string)($_SESSION['role'] ?? ''), 'Employee') !== 0) {
    security_response(['success'=>false,'message'=>'Employee authentication required.'], 403);
}
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/password_policy.php';
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
    $stmt = $conn->prepare("SELECT u.Password,u.Email,u.LocalPasswordConfigured FROM users u INNER JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND r.RoleName='Employee' AND EXISTS (SELECT 1 FROM employee e WHERE LOWER(TRIM(e.Email))=LOWER(TRIM(u.Email)))" . ($method==='POST' ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i',$userId);
    $stmt->execute();
    $account=$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account) {
        if ($method==='POST') $conn->rollback();
        security_response(['success'=>false,'message'=>'Employee account is unavailable.'],403);
    }
    $otp=$_SESSION['forgot_password_otp']??null;
    $otpAuthorized=is_array($otp) && !empty($otp['verified']) && (int)($otp['user_id']??0)===$userId
        && strcasecmp((string)($otp['email']??''),(string)$account['Email'])===0
        && !empty($otp['verified_at']) && time()-(int)$otp['verified_at']<=600;
    $google=$_SESSION['verified_google_login']??null;
    $googleAuthorized=$account['LocalPasswordConfigured']===null && is_array($google)
        && (int)($google['user_id']??0)===$userId && time()-(int)($google['at']??0)<=600;
    $requiresSetup = ($account['LocalPasswordConfigured'] !== null && (int)$account['LocalPasswordConfigured']===0)
        || $otpAuthorized || $googleAuthorized;
    if ($method==='GET') security_response(['success'=>true,'setup_required'=>$requiresSetup,'state_unknown'=>$account['LocalPasswordConfigured']===null,'email'=>$account['Email'],'csrf_token'=>$_SESSION['employee_password_csrf']]);
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
    if ($error) { $conn->rollback(); security_response(['success'=>false,'message'=>$error],$status); }
    $hash=password_hash($new,PASSWORD_DEFAULT);
    $update=$conn->prepare('UPDATE users SET Password=?, LocalPasswordConfigured=1 WHERE UsersID=?');
    $update->bind_param('si',$hash,$userId);
    $update->execute();
    $update->close();
    $read=$conn->prepare("UPDATE notifications SET IsRead=1 WHERE UserID=? AND NotificationType='employee_password_setup'");
    $read->bind_param('i',$userId); $read->execute(); $read->close();
    $conn->commit();
    unset($_SESSION['employee_password_attempt'],$_SESSION['forgot_password_otp'],$_SESSION['verified_google_login']);
    $_SESSION['employee_password_csrf']=bin2hex(random_bytes(32));
    security_response(['success'=>true,'message'=>$action==='setup'?'Your password has been set successfully.':'Your password has been changed successfully.']);
} catch (Throwable $e) {
    if ($method==='POST') $conn->rollback();
    error_log('Employee password operation failed.');
    security_response(['success'=>false,'message'=>'Unable to update account security. Please try again.'],500);
}
