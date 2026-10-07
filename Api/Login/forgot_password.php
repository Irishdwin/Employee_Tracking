<?php
require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/send_mail.php';
require_once __DIR__ . '/../Helpers/password_policy.php';
require_once __DIR__ . '/../Helpers/employee_password_history.php';

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

    $existing = $_SESSION['forgot_password_otp'] ?? null;
    $now = time();
    if (is_array($existing) && isset($existing['last_sent_at']) && ($now - (int)$existing['last_sent_at']) < 45) {
        $waitSec = 45 - ($now - (int)$existing['last_sent_at']);
        fp_json(['success' => false, 'message' => 'Please wait ' . $waitSec . 's before sending another code.'], 429);
    }

    $stmt = $conn->prepare("SELECT UsersID FROM users WHERE LOWER(TRIM(Email)) = LOWER(TRIM(?)) LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        fp_json(['success' => false, 'message' => 'Email is not registered.'], 404);
    }

    $code = (string)random_int(100000, 999999);
    $subject = 'Onazul Password Reset Code';
    $body = "Your Onazul password reset code is: {$code}\n\n"
        . "This code expires in 10 minutes.\n"
        . "If you did not request this, please ignore this email.";

    $mailRes = send_mail_smtp($email, $subject, $body);
    if (empty($mailRes['sent'])) {
        $mailErr = trim((string)($mailRes['error'] ?? 'Unable to send email.'));
        fp_json(['success' => false, 'message' => $mailErr], 500);
    }

    $_SESSION['forgot_password_otp'] = [
        'email' => $email,
        'user_id' => (int)$user['UsersID'],
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => $now + 600,
        'attempts' => 0,
        'verified' => false,
        'last_sent_at' => $now,
    ];

    fp_json(['success' => true, 'message' => 'A 6-digit code was sent to your email.']);
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

    $otp = $_SESSION['forgot_password_otp'] ?? null;
    if (!is_array($otp) || empty($otp['email']) || empty($otp['code_hash']) || empty($otp['expires_at'])) {
        fp_json(['success' => false, 'message' => 'No active reset request. Send a code first.'], 400);
    }
    if (strtolower((string)$otp['email']) !== $email) {
        fp_json(['success' => false, 'message' => 'This code belongs to a different email.'], 400);
    }
    if (time() > (int)$otp['expires_at']) {
        unset($_SESSION['forgot_password_otp']);
        fp_json(['success' => false, 'message' => 'Code expired. Send a new one.'], 400);
    }
    $attempts = (int)($otp['attempts'] ?? 0);
    if ($attempts >= 5) {
        unset($_SESSION['forgot_password_otp']);
        fp_json(['success' => false, 'message' => 'Too many invalid attempts. Send a new code.'], 429);
    }
    if (!password_verify($code, (string)$otp['code_hash'])) {
        $otp['attempts'] = $attempts + 1;
        $_SESSION['forgot_password_otp'] = $otp;
        fp_json(['success' => false, 'message' => 'Invalid code.'], 400);
    }

    $otp['verified'] = true;
    $otp['verified_at'] = time();
    $_SESSION['forgot_password_otp'] = $otp;
    fp_json(['success' => true, 'message' => 'Code verified. You can now set a new password.']);
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
    $policyErr = fp_validate_password($newPassword);
    if ($policyErr !== null) {
        fp_json(['success' => false, 'message' => $policyErr], 400);
    }

    $otp = $_SESSION['forgot_password_otp'] ?? null;
    if (!is_array($otp) || empty($otp['verified']) || strtolower((string)($otp['email'] ?? '')) !== $email) {
        fp_json(['success' => false, 'message' => 'Please verify your reset code first.'], 400);
    }
    if (!empty($otp['verified_at']) && (time() - (int)$otp['verified_at']) > 1800) {
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
        if(in_array($user['RoleName'],['Employee','TeamLead','Team Lead'],true) && employee_password_reused($conn,$uid,$newPassword,(string)$user['Password'])) {
            $conn->rollback();fp_json(['success'=>false,'message'=>'You have already used this password. Choose a new password you have never used before.'],422);
        }
        $hash=password_hash($newPassword,PASSWORD_DEFAULT);
        if(in_array($user['RoleName'],['Employee','TeamLead','Team Lead'],true))employee_password_record($conn,$uid,(string)$user['Password'],$hash);
        $upd=$conn->prepare('UPDATE users SET Password=?,LocalPasswordConfigured=1 WHERE UsersID=?');
        $upd->bind_param('si',$hash,$uid);$upd->execute();$upd->close();$conn->commit();
    } catch(Throwable $e) {
        $conn->rollback();error_log('Password reset failed.');
        fp_json(['success'=>false,'message'=>'Unable to update password. Please try again.'],500);
    }

    unset($_SESSION['forgot_password_otp']);
    fp_json(['success' => true, 'message' => 'Password reset successful. You can now login.']);
}

fp_json(['success' => false, 'message' => 'Invalid action.'], 400);

