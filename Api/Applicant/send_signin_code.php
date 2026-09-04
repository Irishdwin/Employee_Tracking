<?php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$raw = file_get_contents('php://input');
$input = $raw ? (json_decode($raw, true) ?: []) : [];
$email = isset($input['email']) ? trim((string)$input['email']) : trim($_POST['email'] ?? '');
// Session keys must match verify_signin_code.php (case-insensitive email).
$emailKey = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit();
}

// No database needed here — verification uses session only until verify_signin_code.php.

// Generate 6-digit code (100000–999999) valid for 30 minutes
$code = (string)random_int(100000, 999999);
$expiresAt = time() + 1800;

if (!isset($_SESSION['signin_verify'])) $_SESSION['signin_verify'] = [];
$_SESSION['signin_verify'][$emailKey] = [
    'code' => $code,
    'expires' => $expiresAt
];

$subject = 'Your Onazul Sign-in Verification Code';
$fromName = 'Onazul Manpower Services';

$html = '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: Arial, Helvetica, sans-serif; background:#f6f7fb; margin:0; padding:0; }
    .wrap { max-width: 640px; margin: 24px auto; background:#fff; border-radius: 14px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.08); }
    .hdr { background: linear-gradient(135deg, #003366 0%, #0056b3 100%); color:#fff; padding: 22px 24px; text-align:center; }
    .hdr h1 { margin:0; font-size: 18px; letter-spacing: -.01em; }
    .content { padding: 22px 24px 8px; color:#0f172a; }
    .muted { color:#6b7280; font-size: 13px; line-height: 1.5; }
    .code-box { margin: 16px auto 14px; padding: 18px 18px; text-align:center; border-radius: 12px; background:#f0f7ff; border: 2px dashed rgba(0,86,179,.55); }
    .code { font-size: 34px; font-weight: 900; letter-spacing: 0.12em; color:#003366; }
    .warn { background:#fff7ed; border: 1px solid rgba(245,158,11,.35); padding: 12px 14px; border-radius: 12px; margin: 10px 0 14px; }
    .warn b { color:#9a3412; }
    .ftr { padding: 18px 24px; background:#fafafa; color:#6b7280; font-size: 12px; text-align:center; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hdr">
      <h1>' . htmlspecialchars($fromName) . '</h1>
    </div>
    <div class="content">
      <p style="margin:0 0 8px;">Verification code</p>
      <p class="muted" style="margin:0 0 10px;">Use the code below to sign in to your Onazul Manpower Services account.</p>

      <div class="code-box">
        <div class="code">' . htmlspecialchars($code) . '</div>
      </div>

      <div class="warn">
        <b>Do not share this code.</b> Our employees will never ask for it.
      </div>

      <p class="muted" style="margin:0 0 16px;">This code expires in <b>30 minutes</b>.</p>

      <p class="muted" style="margin:0 0 8px;">If you didn’t request this code, you can safely ignore this email.</p>
    </div>
    <div class="ftr">Onazul Workforce Suite</div>
  </div>
</body>
</html>';

$alt = "Your Onazul sign-in verification code is: {$code}. Do not share it. Expires in 30 minutes.";

require_once __DIR__ . '/../Helpers/send_mail.php';
$result = send_mail_smtp_html($email, $subject, $html, $alt);

if (!($result['sent'] ?? false)) {
    $err = (string)($result['error'] ?? 'Unknown SMTP error');
    error_log('send_signin_code mail: ' . $err);
    if (strpos($err, 'Edit Api/config/mail_config.php') !== false) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        echo json_encode([
            'success' => false,
            'message' => 'Gmail SMTP is not configured. Edit Api/config/mail_config.php and use a valid Gmail App Password. (http://' . $host . '/Employee_Tracking/Api/config/check_email_setup.php)'
        ]);
        exit();
    }
    echo json_encode(['success' => false, 'message' => 'We could not send the sign-in email right now. Please try again in a minute.']);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'We sent a sign-in code to your email. Enter it below.'
]);
?>

