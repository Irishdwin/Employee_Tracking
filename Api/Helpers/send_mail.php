<?php
/**
 * Send email via Gmail SMTP.
 * Edit Api/config/mail_config.php with your Gmail and App Password.
 *
 * @param string $to Recipient email
 * @param string $subject Subject line
 * @param string $bodyPlain Plain text body
 * @return array ['sent' => bool, 'error' => string|null]
 */
function send_mail_smtp($to, $subject, $bodyPlain) {
    $configPath = __DIR__ . '/../config/mail_config.php';
    if (!is_file($configPath)) {
        return ['sent' => false, 'error' => 'Edit Api/config/mail_config.php with your Gmail and App Password.'];
    }
    $config = require $configPath;
    if (empty($config['smtp_user']) || $config['smtp_user'] === 'your_email@gmail.com' || empty($config['smtp_pass']) || $config['smtp_pass'] === 'your_16_char_app_password') {
        return ['sent' => false, 'error' => 'Edit Api/config/mail_config.php with your Gmail and App Password.'];
    }

    $libDir = __DIR__ . '/../Lib/PHPMailer/PHPMailer';
    if (is_file($libDir . '/PHPMailer.php')) {
        require_once $libDir . '/Exception.php';
        require_once $libDir . '/SMTP.php';
        require_once $libDir . '/PHPMailer.php';
    } elseif (is_file(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
    } else {
        return ['sent' => false, 'error' => 'Mail library not found.'];
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $smtpPass = (string)($config['smtp_pass'] ?? '');
        // Gmail app passwords are often copied with spaces; normalize safely.
        $smtpPass = preg_replace('/\s+/', '', $smtpPass);

        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port       = (int) $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        // Helps local Windows/XAMPP environments with CA bundle issues.
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $bodyPlain;
        $mail->isHTML(false);

        $mail->send();
        return ['sent' => true, 'error' => null];
    } catch (\Throwable $e) {
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send email via Gmail SMTP (HTML).
 *
 * @param string $to
 * @param string $subject
 * @param string $bodyHtml
 * @param string $bodyAlt Fallback plain text
 * @return array ['sent' => bool, 'error' => string|null]
 */
function send_mail_smtp_html($to, $subject, $bodyHtml, $bodyAlt = '') {
    $configPath = __DIR__ . '/../config/mail_config.php';
    if (!is_file($configPath)) {
        return ['sent' => false, 'error' => 'Edit Api/config/mail_config.php with your Gmail and App Password.'];
    }
    $config = require $configPath;
    if (empty($config['smtp_user']) || $config['smtp_user'] === 'your_email@gmail.com' || empty($config['smtp_pass']) || $config['smtp_pass'] === 'your_16_char_app_password') {
        return ['sent' => false, 'error' => 'Edit Api/config/mail_config.php with your Gmail and App Password.'];
    }

    $libDir = __DIR__ . '/../Lib/PHPMailer/PHPMailer';
    if (is_file($libDir . '/PHPMailer.php')) {
        require_once $libDir . '/Exception.php';
        require_once $libDir . '/SMTP.php';
        require_once $libDir . '/PHPMailer.php';
    } elseif (is_file(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
    } else {
        return ['sent' => false, 'error' => 'Mail library not found.'];
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $smtpPass = (string)($config['smtp_pass'] ?? '');
        $smtpPass = preg_replace('/\s+/', '', $smtpPass);

        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port       = (int) $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyAlt !== '' ? $bodyAlt : strip_tags($bodyHtml);
        $mail->isHTML(true);

        $mail->send();
        return ['sent' => true, 'error' => null];
    } catch (\Throwable $e) {
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}
