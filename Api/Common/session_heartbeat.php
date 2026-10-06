<?php
/**
 * POST/GET — Refreshes the session's idle-timeout clock.
 *
 * Called by Js/Shared/role_guard.js's activity listener ONLY in response to
 * real user interaction (throttled client-side, at most once every 60s) —
 * deliberately NOT wired into ordinary background polling endpoints, so
 * that leaving a dashboard tab open and walking away still times out on
 * schedule even while badge/notification polling keeps running in it.
 */
declare(strict_types=1);

require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No active session.']);
    exit();
}

$_SESSION['last_activity'] = time();

echo json_encode([
    'success' => true,
    'authenticated' => true,
    'timeout_seconds' => SESSION_IDLE_TIMEOUT_SECONDS,
    'remaining_seconds' => SESSION_IDLE_TIMEOUT_SECONDS,
]);
