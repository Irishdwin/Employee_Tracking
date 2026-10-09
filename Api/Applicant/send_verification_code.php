<?php
/**
 * POST JSON { "email": "..." }
 * Sends a 4-digit verification code for applicant registration.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Helpers/hris_pdo.php';

header('Content-Type: application/json; charset=utf-8');

function reg_code_json(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reg_code_json(false, 'Invalid request method.');
}

$raw = file_get_contents('php://input');
$input = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
$email = isset($input['email']) ? trim((string)$input['email']) : trim((string)($_POST['email'] ?? ''));

if ($email === '' || (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-z0-9][a-z0-9._%+\-]*@gmail\.com$/i',$email))) {
    reg_code_json(false, 'A valid Gmail address is required.');
}

try {
    $pdo = hris_pdo();
    $stmt = $pdo->prepare('SELECT UsersID FROM users WHERE Email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        reg_code_json(false, 'This email is already registered. Please log in instead.');
    }
} catch (Throwable $e) {
    error_log('send_verification_code db: ' . $e->getMessage());
    reg_code_json(false, 'Service temporarily unavailable. Please try again later.');
}

$code = (string) random_int(1000, 9999);
$expiresAt = time() + 600;

$_SESSION['verification_code'] = $code;
$_SESSION['verification_email'] = $email;
$_SESSION['verification_expires'] = $expiresAt;

// Legacy keys used by older handlers.
$_SESSION['otp_code'] = $code;
$_SESSION['otp_email'] = $email;
$_SESSION['otp_time'] = time();
$_SESSION['applicant_verify'] = [
    $email => ['code' => $code, 'expires' => $expiresAt],
];

$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$isLocal = ($host === 'localhost' || str_starts_with($host, '127.0.0.1') || str_starts_with($host, 'localhost:'));

$subject = 'Your Onazul Applicant Verification Code';
$body = "Your verification code for applicant registration is: {$code}\n\n";
$body .= "This code expires in 10 minutes. Do not share it with anyone.\n\n";
$body .= "If you did not request this, please ignore this email.\n";
$body .= "— Onazul Workforce Suite\n";

$mailSent = false;
$mailError = '';

require_once __DIR__ . '/../Helpers/send_mail.php';
$result = send_mail_smtp($email, $subject, $body);
$mailSent = (bool)($result['sent'] ?? false);
if (!$mailSent) {
    $mailError = (string)($result['error'] ?? 'Unknown SMTP error');
    error_log('Applicant verification email failed: ' . $mailError);
}

$logDir = dirname(__DIR__, 2) . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
@file_put_contents(
    $logDir . '/verification_codes.log',
    date('Y-m-d H:i:s') . " | {$email} | {$code}\n",
    FILE_APPEND | LOCK_EX
);

if (!$mailSent && !$isLocal) {
    if (strpos($mailError, 'Edit Api/config/mail_config.php') !== false) {
        reg_code_json(false, 'Gmail is not configured. Update Api/config/mail_config.php with your Gmail App Password.');
    }
    reg_code_json(false, 'We could not send the verification email right now. Please try again in a minute.');
}

$message = $mailSent
    ? 'A 4-digit verification code has been sent to your email. Please enter it below.'
    : 'Verification code generated for local testing. Check the response or logs/verification_codes.log.';

$extra = [];
if ($isLocal) {
    $extra['dev_code'] = $code;
}

reg_code_json(true, $message, $extra);
