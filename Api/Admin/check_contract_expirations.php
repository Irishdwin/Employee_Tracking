<?php
/**
 * GET/POST — Runs the 30-day contract-expiry reminder check for both
 * employment contracts and service agreements. Safe to call repeatedly
 * (idempotent — dedup logic lives in run_contract_expiry_check()).
 *
 * Called once from Js/Admin/admin_dashboard.js on dashboard load, the same
 * way loadCommandCenterOverview()/AdminSidebarBadges.poll() already run on
 * load — this project has no OS-level cron, so "check on Admin login" is
 * the existing convention for this kind of periodic housekeeping.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'HR'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/contract_expiry_helper.php';

try {
    $result = run_contract_expiry_check($conn);
    echo json_encode(['success' => true] + $result);
} catch (Throwable $e) {
    error_log('check_contract_expirations: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to run contract expiry check.']);
}
