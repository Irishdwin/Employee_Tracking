<?php
/**
 * GET ?job_id= | ?job_order_id= | ?jor_id=
 * Returns sub-deployment placement rows for the applicant apply modal (PDO).
 * job_id / job_order_id = joborder.JobOrderID (posted job on the wall).
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json; charset=utf-8');

$jobOrderId = 0;
if (isset($_GET['job_id'])) {
    $jobOrderId = (int)$_GET['job_id'];
} elseif (isset($_GET['job_order_id'])) {
    $jobOrderId = (int)$_GET['job_order_id'];
} elseif (isset($_GET['jor_id'])) {
    $jorId = (int)$_GET['jor_id'];
    if ($jorId > 0) {
        require __DIR__ . '/../Db_connection/db_connect.php';
        require_once __DIR__ . '/../Helpers/jor_multi_position.php';
        jor_multi_ensure_schema($conn);
        $lk = $conn->prepare('SELECT JobOrderID FROM job_order_requests WHERE RequestID = ? AND JobOrderID IS NOT NULL LIMIT 1');
        if ($lk) {
            $lk->bind_param('i', $jorId);
            $lk->execute();
            $row = $lk->get_result()->fetch_assoc();
            $lk->close();
            if ($row && !empty($row['JobOrderID'])) {
                $jobOrderId = (int)$row['JobOrderID'];
            }
        }
        if ($jobOrderId <= 0) {
            $detailStmt = $conn->prepare('SELECT JobOrderID FROM job_order_details WHERE RequestID = ? AND JobOrderID IS NOT NULL LIMIT 1');
            if ($detailStmt) {
                $detailStmt->bind_param('i', $jorId);
                $detailStmt->execute();
                $drow = $detailStmt->get_result()->fetch_assoc();
                $detailStmt->close();
                if ($drow && !empty($drow['JobOrderID'])) {
                    $jobOrderId = (int)$drow['JobOrderID'];
                }
            }
        }
    }
}

if ($jobOrderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'job_id (JobOrderID) is required.']);
    exit;
}

require_once __DIR__ . '/../Helpers/hris_pdo.php';
require_once __DIR__ . '/../Helpers/job_order_wall.php';

try {
    $pdo = hris_pdo();

    require __DIR__ . '/../Db_connection/db_connect.php';
    require_once __DIR__ . '/../Helpers/jor_multi_position.php';
    jor_multi_ensure_schema($conn);

    $openSlotsSql = job_order_has_open_slots_sql();
    $joStmt = $pdo->prepare("
        SELECT jo.JobOrderID, jo.Position, jo.Location, jo.WorkSchedule, jo.RequiredCount, c.Name AS CompanyName
        FROM joborder jo
        INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
        WHERE jo.JobOrderID = ?
          AND c.IsActive = 1
          AND {$openSlotsSql}
        LIMIT 1
    ");
    $joStmt->execute([$jobOrderId]);
    $job = $joStmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Job order not found or no longer open.']);
        exit;
    }

    $placements = job_order_fetch_placement_slots_pdo(
        $pdo,
        $jobOrderId,
        (int)($job['RequiredCount'] ?? 1)
    );

    foreach ($placements as &$placement) {
        $pos = trim((string)($placement['position_title'] ?? $placement['position'] ?? ''));
        $site = trim((string)($placement['deployment_site'] ?? $placement['site'] ?? ''));
        $placement['label'] = $pos . ' — ' . ($site !== '' ? $site : 'Open site');
    }
    unset($placement);

    $openPlacements = array_values(array_filter($placements, static function ($p) {
        return empty($p['is_filled']);
    }));
    if ($openPlacements === []) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'All deployment slots for this job order are filled.']);
        exit;
    }

    $requiresSelection = count($openPlacements) > 1 || count($placements) > 1;

    echo json_encode([
        'success' => true,
        'job_id' => $jobOrderId,
        'job_order_id' => $jobOrderId,
        'company_name' => (string)($job['CompanyName'] ?? ''),
        'placement_count' => count($placements),
        'open_placement_count' => count($openPlacements),
        'requires_selection' => $requiresSelection,
        'placements' => $placements,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_job_placements: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load placement options.']);
}
