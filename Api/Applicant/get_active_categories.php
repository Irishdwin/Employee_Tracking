<?php
/**
 * GET — active job categories for applicant profile / registration forms.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Helpers/job_categories_lookup.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    $pdo = hris_pdo();
    job_categories_ensure_schema($pdo);

    $stmt = $pdo->query('
        SELECT id, category_name
        FROM job_categories
        WHERE is_active = 1
        ORDER BY category_name ASC
    ');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categories = [];
    foreach ($rows as $row) {
        $categories[] = [
            'id' => (int)$row['id'],
            'category_name' => (string)$row['category_name'],
            'name' => (string)$row['category_name'],
        ];
    }

    echo json_encode([
        'success' => true,
        'categories' => $categories,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_active_categories: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load job categories.']);
}