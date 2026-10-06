<?php
/**
 * GET  — list job_categories (alphabetical)
 * POST — action=add (default, backward compatible with the original
 *        "category_name only" body) | action=edit | action=toggle_active
 *        edit/toggle_active are Admin-only; add stays open to Admin/HR/TeamLead
 *        (unchanged — this is the same endpoint the job-order "+ Add New
 *        Category" quick-add modal already uses).
 */
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../Helpers/job_categories_lookup.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = trim((string)$_SESSION['role']);
$allowed = ['Admin', 'HR', 'TeamLead', 'Team Lead'];
if (!in_array($role, $allowed, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = hris_pdo();
    job_categories_ensure_schema($pdo);

    if ($method === 'GET') {
        $categories = job_categories_fetch_all($pdo);
        echo json_encode([
            'success' => true,
            'categories' => $categories,
        ]);
        exit();
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $action = trim((string)($input['action'] ?? 'add'));

        if ($action === 'edit' || $action === 'toggle_active') {
            if ($role !== 'Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Only an Admin can edit or deactivate categories.']);
                exit();
            }
        }

        if ($action === 'edit') {
            $id = (int)($input['id'] ?? 0);
            $name = (string)($input['category_name'] ?? '');
            $result = job_category_update($pdo, $id, $name);
            echo json_encode($result);
            exit();
        }

        if ($action === 'toggle_active') {
            $id = (int)($input['id'] ?? 0);
            $active = !empty($input['is_active']);
            $result = job_category_set_active($pdo, $id, $active);
            echo json_encode($result);
            exit();
        }

        // action === 'add' (default) — unchanged from the original behavior.
        $name = job_category_sanitize_name((string)($input['category_name'] ?? ''));
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit();
        }

        $existing = job_category_find_by_name($pdo, $name);
        if ($existing) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'That category already exists.',
                'category' => $existing,
            ]);
            exit();
        }

        $stmt = $pdo->prepare('INSERT INTO job_categories (category_name) VALUES (?)');
        $stmt->execute([$name]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Category saved successfully.',
            'category' => [
                'id' => $newId,
                'category_name' => $name,
            ],
        ]);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
    error_log('manage_categories: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error managing categories.']);
}