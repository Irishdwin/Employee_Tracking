<?php
/**
 * Google OAuth configuration (sample).
 * Copy this file to google_oauth.php and paste your Google Cloud credentials.
 */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$hostRaw = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$redirectUri = $protocol . '://' . $hostRaw . '/Employee_Tracking/Html/Applicant/applicant_landing.html';

define('GOOGLE_CLIENT_ID', 'PASTE_YOUR_GOOGLE_CLIENT_ID_HERE');
define('GOOGLE_CLIENT_SECRET', 'PASTE_YOUR_GOOGLE_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URI', $redirectUri);

return [
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => $redirectUri,
];
