<?php
/**
 * GET  ?classification_id=X — list sub-classifications (includes inactive)
 * POST action=add | edit | toggle_active   (Admin-only)
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
        $classificationId = (int)($_GET['classification_id'] ?? 0);
        if ($classificationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'classification_id is required.']);
            exit();
        }
        $subs = job_sub_classifications_fetch_by_classification($pdo, $classificationId, false);
        echo json_encode(['success' => true, 'sub_classifications' => $subs]);
        exit();
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;
        $action = trim((string)($input['action'] ?? 'add'));

        if ($action === 'add') {
            $result = job_sub_classification_create(
                $pdo,
                (int)($input['classification_id'] ?? 0),
                (string)($input['sub_classification_name'] ?? '')
            );
            echo json_encode($result);
            exit();
        }

        if ($action === 'edit') {
            $result = job_sub_classification_update($pdo, (int)($input['id'] ?? 0), (string)($input['sub_classification_name'] ?? ''));
            echo json_encode($result);
            exit();
        }

        if ($action === 'toggle_active') {
            $result = job_sub_classification_set_active($pdo, (int)($input['id'] ?? 0), !empty($input['is_active']));
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
    error_log('manage_sub_classifications: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error managing sub-classifications.']);
}