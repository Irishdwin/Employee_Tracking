<?php
/**
 * GET ?classification_id=X — active sub-classifications under a classification,
 * for the applicant profile's Preferred Sub-classification dropdown.
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

$classificationId = isset($_GET['classification_id']) ? (int)$_GET['classification_id'] : 0;
if ($classificationId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'classification_id is required.']);
    exit;
}

try {
    $pdo = hris_pdo();
    job_categories_ensure_schema($pdo);

    $subs = job_sub_classifications_fetch_by_classification($pdo, $classificationId, true);
    $data = [];
    foreach ($subs as $s) {
        $data[] = [
            'id' => $s['id'],
            'name' => $s['sub_classification_name'],
        ];
    }

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_sub_classifications: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load sub-classifications.']);
}