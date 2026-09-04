<?php
/**
 * Email setup diagnostic – open in browser: http://localhost/Employee_Tracking/Api/config/check_email_setup.php
 * Shows whether Composer/PHPMailer and Gmail config are OK and can send a test email.
 */
session_start();
$projectRoot = realpath(__DIR__ . '/../..');
$autoload = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'mail_config.php';

$step1 = is_file($autoload);
$step2 = is_file($configPath);
$config = $step2 ? require $configPath : [];
$step3 = $step2 && !empty($config['smtp_user']) && $config['smtp_user'] !== 'your_email@gmail.com'
    && !empty($config['smtp_pass']) && $config['smtp_pass'] !== 'your_16_char_app_password';

$testResult = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email']) && $step1 && $step3) {
    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'send_mail.php';
    $to = filter_var(trim($_POST['test_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: $config['smtp_user'];
    $result = send_mail_smtp($to, 'Onazul test email', "This is a test. If you received this, email is working.\n\n— Onazul");
    $testResult = $result['sent']
        ? '<div class="alert alert-success">Test email sent to ' . htmlspecialchars($to) . '. Check your inbox.</div>'
        : '<div class="alert alert-danger">Send failed: ' . htmlspecialchars($result['error'] ?? 'unknown') . '</div>';
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email setup check – Onazul</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width: 600px;">
    <h1 class="h4 mb-4">Email setup diagnostic</h1>
    <p class="text-muted small">Use this page to see why verification codes are not sent to Gmail and to send a test email.</p>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">1. Composer &amp; PHPMailer</h2>
            <?php if ($step1): ?>
                <p class="text-success mb-0">OK – <code>vendor/autoload.php</code> found.</p>
            <?php else: ?>
                <p class="text-danger mb-2">Missing – run Composer install in the project folder.</p>
                <ol class="small mb-0">
                    <li>Download Composer: <a href="https://getcomposer.org/download/" target="_blank">getcomposer.org/download</a></li>
                    <li>Open Command Prompt or PowerShell and run:</li>
                </ol>
                <pre class="bg-dark text-light p-2 rounded small mt-2">cd c:\xampp\htdocs\Employee_Tracking
composer install</pre>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">2. Config file</h2>
            <?php if ($step2): ?>
                <p class="text-success mb-0">OK – <code>Api/config/mail_config.php</code> exists.</p>
            <?php else: ?>
                <p class="text-danger mb-0">Missing – copy <code>mail_config.sample.php</code> to <code>mail_config.php</code> in this folder.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">3. Gmail credentials</h2>
            <?php if ($step3): ?>
                <p class="text-success mb-0">OK – Gmail address and App Password are set in <code>mail_config.php</code>.</p>
            <?php else: ?>
                <p class="text-danger mb-2">Not set – edit <code>Api/config/mail_config.php</code> and set:</p>
                <ul class="small mb-0">
                    <li><code>smtp_user</code> = your Gmail (e.g. you@gmail.com)</li>
                    <li><code>smtp_pass</code> = 16-character App Password (from Google Account → Security → App passwords)</li>
                    <li><code>from_email</code> = same Gmail</li>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($step1 && $step3): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Send test email</h2>
            <?php echo $testResult; ?>
            <form method="post" class="d-flex gap-2">
                <input type="email" name="test_email" class="form-control form-control-sm" placeholder="Send to this email" value="<?php echo htmlspecialchars($config['smtp_user'] ?? ''); ?>">
                <button type="submit" class="btn btn-primary btn-sm">Send test</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <p class="small text-muted mt-3"><a href="../../Html/Applicant/applicant_landing.html">← Back to Job Wall / sign in</a></p>
</div>
</body>
</html>
