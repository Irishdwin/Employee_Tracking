<?php
/**
 * GET ?category_id=X — active classifications under a job category,
 * for the applicant profile's Preferred Classification dropdown.
 *
 * Mirrors get_sub_classifications.php. Reuses the existing
 * job_classifications_fetch_by_category() helper (already used by the
 * Admin-only Api/Common/manage_classifications.php) instead of duplicating
 * the query — that endpoint can't be reused directly here because it
 * requires $_SESSION['role'] === 'Admin', which an Applicant session fails.
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

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
if ($categoryId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'category_id is required.']);
    exit;
}

try {
    $pdo = hris_pdo();
    job_categories_ensure_schema($pdo);

    $classifications = job_classifications_fetch_by_category($pdo, $categoryId, true);
    $data = [];
    foreach ($classifications as $c) {
        $data[] = [
            'id' => $c['id'],
            'name' => $c['classification_name'],
            'is_default' => $c['is_default'],
        ];
    }

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_classifications: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load classifications.']);
}