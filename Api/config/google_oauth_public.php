<?php
header('Content-Type: application/json');

try {
    $cfg = require __DIR__ . '/google_oauth.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Google config missing.']);
    exit();
}

$clientId = isset($cfg['client_id']) ? (string)$cfg['client_id'] : '';
$redirectUri = isset($cfg['redirect_uri']) ? (string)$cfg['redirect_uri'] : '';

if ($clientId === '' || strcasecmp($clientId, 'PASTE_YOUR_GOOGLE_CLIENT_ID_HERE') === 0) {
    echo json_encode(['success' => false, 'message' => 'Google sign-in is not configured. Update Api/config/google_oauth.php.']);
    exit();
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$hostRaw = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$origin = $protocol . '://' . $hostRaw;

$unifiedCallbackUri = $origin . '/Employee_Tracking/Html/Login/unified_google_callback.html';
$applicantLandingUri = $origin . '/Employee_Tracking/Html/Applicant/applicant_landing.html';
$indexUri = $origin . '/Employee_Tracking/index.html';

echo json_encode([
    'success' => true,
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'unified_callback_uri' => $unifiedCallbackUri,
    'recommended_redirect_uris' => [
        $unifiedCallbackUri,
        $applicantLandingUri,
    ],
    'recommended_javascript_origins' => [
        $origin,
    ],
    'registered_redirect_uris' => [
        $unifiedCallbackUri,
        $applicantLandingUri,
        $indexUri,
        str_replace('localhost', '127.0.0.1', $unifiedCallbackUri),
        str_replace('localhost', '127.0.0.1', $applicantLandingUri),
    ],
    'hint' => 'For index.html login, register unified_callback_uri exactly in Google Cloud Console → Credentials → Authorized redirect URIs. For applicant job wall, register applicant_landing.html. Origins: add ' . $origin . ' under Authorized JavaScript origins.',
]);
