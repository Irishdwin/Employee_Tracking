<?php
/**
 * GET  ?category_id=X — list classifications for that category (includes inactive)
 * POST action=add | edit | toggle_active | set_default   (Admin-only)
 */
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../Helpers/job_categories_lookup.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = hris_pdo();
    job_categories_ensure_schema($pdo);

    if ($method === 'GET') {
        $categoryId = (int)($_GET['category_id'] ?? 0);
        if ($categoryId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'category_id is required.']);
            exit();
        }
        $classifications = job_classifications_fetch_by_category($pdo, $categoryId, false);
        echo json_encode(['success' => true, 'classifications' => $classifications]);
        exit();
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;
        $action = trim((string)($input['action'] ?? 'add'));

        if ($action === 'add') {
            $result = job_classification_create(
                $pdo,
                (int)($input['category_id'] ?? 0),
                (string)($input['classification_name'] ?? ''),
                !empty($input['is_default'])
            );
            echo json_encode($result);
            exit();
        }

        if ($action === 'edit') {
            $result = job_classification_update($pdo, (int)($input['id'] ?? 0), (string)($input['classification_name'] ?? ''));
            echo json_encode($result);
            exit();
        }

        if ($action === 'toggle_active') {
            $result = job_classification_set_active($pdo, (int)($input['id'] ?? 0), !empty($input['is_active']));
            echo json_encode($result);
            exit();
        }

        if ($action === 'set_default') {
            $result = job_classification_set_default($pdo, (int)($input['id'] ?? 0));
            echo json_encode($result);
            exit();
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
    error_log('manage_classifications: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error managing classifications.']);
}