<?php
/**
 * Admin Holidays management.
 *
 * GET  ?year=YYYY (optional) — list all holidays, optionally filtered by year
 * POST action=add | edit | delete   (Admin-only)
 *
 * Reuses the existing `holidays` table (see Api/Helpers/holidays_helper.php
 * for why this is the existing table, not a new one). Server-side validation
 * only — the pay multiplier and holiday definition always come from the
 * database, never trusted from the request body, per the project's payroll
 * security rules.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn
require_once __DIR__ . '/../Helpers/holidays_helper.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    holidays_ensure_schema($conn);

    if ($method === 'GET') {
        $year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;
        $holidays = holidays_fetch_all($conn, $year);
        echo json_encode([
            'success' => true,
            'holidays' => $holidays,
            'allowed_types' => holidays_allowed_types(),
        ]);
        exit();
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $action = trim((string)($input['action'] ?? ''));

        if ($action === 'add') {
            $result = holidays_create($conn, $input);
            echo json_encode($result);
            exit();
        }

        if ($action === 'edit') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'A valid holiday id is required.']);
                exit();
            }
            $result = holidays_update($conn, $id, $input);
            echo json_encode($result);
            exit();
        }

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'A valid holiday id is required.']);
                exit();
            }
            $result = holidays_delete($conn, $id);
            echo json_encode($result);
            exit();
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
} catch (Throwable $e) {
    error_log('manage_holidays: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error managing holidays.']);
}