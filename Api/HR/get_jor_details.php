<?php
/**
 * GET — Nested deployment rows for a consolidated Job Order Request (HR/Admin preview).
 * Query: jor_id (maps to job_order_requests.RequestID)
 */
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$allowedRoles = ['Admin', 'HR'];
if (!in_array((string)$_SESSION['role'], $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/hris_pdo.php';
require_once __DIR__ . '/../Helpers/jor_multi_position.php';

$jorId = isset($_GET['jor_id']) ? (int)$_GET['jor_id'] : 0;
if ($jorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid jor_id.']);
    exit;
}

try {
    jor_multi_ensure_schema($conn);
    $pdo = hris_pdo();

    $parentStmt = $pdo->prepare("
        SELECT
            r.RequestID,
            r.CompanyID,
            r.Position,
            r.RequiredCount,
            r.Location,
            r.WorkSchedule,
            r.Remarks,
            r.Status,
            r.CreatedAt,
            c.Name AS company_name,
            u.FullName AS requested_by_name
        FROM job_order_requests r
        INNER JOIN clientcompany c ON r.CompanyID = c.CompanyID
        LEFT JOIN users u ON r.RequestedBy = u.UsersID
        WHERE r.RequestID = ?
        LIMIT 1
    ");
    $parentStmt->execute([$jorId]);
    $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$parent) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Job order request not found.']);
        exit;
    }

    $detailStmt = $pdo->prepare("
        SELECT
            d.detail_id,
            d.position_title,
            COALESCE(c.category_name, d.job_category, '') AS category_name,
            d.required_workers,
            d.deployment_site,
            d.work_schedule
        FROM job_order_details d
        LEFT JOIN job_categories c ON d.category_id = c.id
        WHERE d.RequestID = :jor_id
        ORDER BY d.detail_id ASC
    ");
    $detailStmt->execute(['jor_id' => $jorId]);
    $rows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    $positions = [];
    foreach ($rows as $row) {
        $positions[] = [
            'detail_id' => (int)$row['detail_id'],
            'position_title' => (string)$row['position_title'],
            'category_name' => (string)$row['category_name'],
            'required_workers' => (int)$row['required_workers'],
            'deployment_site' => (string)$row['deployment_site'],
            'work_schedule' => (string)$row['work_schedule'],
        ];
    }

    if (empty($positions)) {
        $positions[] = [
            'detail_id' => 0,
            'position_title' => (string)$parent['Position'],
            'category_name' => '',
            'required_workers' => (int)$parent['RequiredCount'],
            'deployment_site' => (string)($parent['Location'] ?? ''),
            'work_schedule' => (string)($parent['WorkSchedule'] ?? ''),
        ];
    }

    echo json_encode([
        'success' => true,
        'jor_id' => (int)$parent['RequestID'],
        'company_id' => (int)$parent['CompanyID'],
        'company_name' => (string)$parent['company_name'],
        'requested_by' => (string)($parent['requested_by_name'] ?? ''),
        'created_at' => (string)$parent['CreatedAt'],
        'status' => (string)$parent['Status'],
        'remarks' => (string)($parent['Remarks'] ?? ''),
        'positions' => $positions,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_jor_details: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load job order request details.']);
}
