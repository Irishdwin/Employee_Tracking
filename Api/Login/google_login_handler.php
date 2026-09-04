<?php
/**
 * Unified Google Sign-In handler with RBAC routing.
 * POST JSON: { "code": "...", "redirect_uri": "...", "state": "..." }
 */
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$raw = file_get_contents('php://input');
$input = $raw ? (json_decode($raw, true) ?: []) : [];

$code = isset($input['code']) ? trim((string)$input['code']) : '';
if ($code === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing authorization code.']);
    exit();
}

try {
    $cfg = require __DIR__ . '/../config/google_oauth.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Google OAuth config missing.']);
    exit();
}

require_once __DIR__ . '/../config/google_oauth_redirect_validate.php';
require_once __DIR__ . '/../Helpers/google_auth_rbac.php';

$clientRedirect = isset($input['redirect_uri']) ? trim((string)$input['redirect_uri']) : '';
$cfgRedirectUri = isset($cfg['redirect_uri']) ? (string)$cfg['redirect_uri'] : '';

if ($clientRedirect !== '') {
    if (!google_oauth_redirect_uri_is_allowed($clientRedirect)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid redirect_uri for token exchange.']);
        exit();
    }
    $redirectUri = $clientRedirect;
} else {
    $redirectUri = $cfgRedirectUri;
}

if ($redirectUri === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Google OAuth redirect URI is not configured.']);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
} catch (Throwable $e) {
    error_log('google_login_handler db: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']);
    exit();
}

try {
    $claims = google_oauth_exchange_code($code, $redirectUri, $cfg);
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}

$email = isset($claims['email']) ? trim((string)$claims['email']) : '';
$fullName = isset($claims['name']) ? trim((string)$claims['name']) : (string)($claims['given_name'] ?? '');
$emailVerified = isset($claims['email_verified']) ? (bool)$claims['email_verified'] : false;

if ($email === '' || !$emailVerified) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Email is missing or not verified by Google.']);
    exit();
}

try {
    $result = google_rbac_process_verified_email($conn, $email, $fullName, $redirectUri);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();
