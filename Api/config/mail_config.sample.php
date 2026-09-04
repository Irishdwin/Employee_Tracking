<?php
/**
 * Gmail SMTP configuration (sample).
 * Copy this file to mail_config.php and fill in your Gmail and App Password.
 *
 * To get a Gmail App Password:
 * 1. Go to https://myaccount.google.com/
 * 2. Security → 2-Step Verification (enable if not already)
 * 3. Security → App passwords → Generate a 16-character app password for "Mail"
 * 4. Use that password below (no spaces).
 */
return [
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_port'     => 587,
    'smtp_secure'   => 'tls',
    'smtp_user'     => 'your_email@gmail.com',
    'smtp_pass'     => 'your_16_char_app_password',
    'from_email'    => 'your_email@gmail.com',
    'from_name'     => 'Onazul Workforce Suite',
];
