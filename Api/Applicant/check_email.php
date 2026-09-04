<?php
/**
 * POST JSON { "email": "..." } or GET ?email=...
 * Checks if an email address already exists in the system users table.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Helpers/hris_pdo.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$input = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
$email = trim((string)($input['email'] ?? $_GET['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => true,
        'exists' => false,
        'message' => 'Invalid email format.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = hris_pdo();
    $stmt = $pdo->prepare('SELECT UsersID FROM users WHERE Email = ? LIMIT 1');
    $stmt->execute([$email]);
    $exists = (bool)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'exists' => $exists,
        'message' => $exists ? 'This Gmail address is already registered. Please use another Gmail.' : 'Email is available.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('check_email error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'exists' => false,
        'message' => 'Database error while checking email.'
    ], JSON_UNESCAPED_UNICODE);
}