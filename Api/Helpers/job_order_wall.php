<?php
/**
 * Job wall visibility and per-deployment slot fulfillment tracking.
 */

/** SQL expression: number of applicants hired for this job order (legacy / header-level). */
function job_order_hired_count_sql(): string
{
    return "(SELECT COUNT(*)
             FROM job_applications ja
             WHERE ja.JobOrderID = jo.JobOrderID
               AND ja.HiredDate IS NOT NULL)";
}

/** SQL expression: hired count for a specific placement row (job_order_details). */
function job_order_detail_hired_count_sql(string $detailAlias = 'jod'): string
{
    return "(SELECT COUNT(*)
             FROM job_applications ja_slot
             WHERE ja_slot.JobOrderID = jo.JobOrderID
               AND ja_slot.target_detail_id = {$detailAlias}.detail_id
               AND ja_slot.HiredDate IS NOT NULL)";
}

/**
 * SQL condition: job order still has at least one open deployment slot on the wall.
 * - Multi-placement orders: any detail row with hired < required_workers stays visible.
 * - Legacy single-row orders: hired count < RequiredCount.
 */
function job_order_has_open_slots_sql(): string
{
    $legacyHired = job_order_hired_count_sql();
    $detailHired = job_order_detail_hired_count_sql('jod_open');

    return "(
        (
            NOT EXISTS (
                SELECT 1 FROM job_order_details jod_chk
                WHERE jod_chk.JobOrderID = jo.JobOrderID
            )
            AND {$legacyHired} < GREATEST(jo.RequiredCount, 1)
        ) OR (
            EXISTS (
                SELECT 1
                FROM job_order_details jod_open
                WHERE jod_open.JobOrderID = jo.JobOrderID
                  AND {$detailHired} < GREATEST(jod_open.required_workers, 1)
            )
        )
    )";
}

/**
 * Prefer joborder.Position; fall back to linked job_order_requests.Position when stored as 0/empty.
 */
function job_order_resolve_position(?string $jobOrderPosition, ?string $requestPosition = null): string
{
    $pos = trim((string)($jobOrderPosition ?? ''));
    if ($pos !== '' && $pos !== '0') {
        return $pos;
    }
    $req = trim((string)($requestPosition ?? ''));
    if ($req !== '' && $req !== '0') {
        return $req;
    }
    return '';
}

/** SQL LEFT JOIN to recover position text from the originating TL request. */
function job_order_request_position_join_sql(): string
{
    return 'LEFT JOIN job_order_requests jor ON jor.JobOrderID = jo.JobOrderID';
}

function job_order_is_generic_site(?string $site): bool
{
    $site = trim((string)$site);
    if ($site === '') {
        return true;
    }
    return strcasecmp($site, 'Multiple sites') === 0;
}

/**
 * @return array<int, array<string,mixed>>
 */
function job_order_fetch_placement_slots_mysqli(mysqli $conn, int $jobOrderId, int $requiredCountFallback = 1): array
{
    require_once __DIR__ . '/jor_multi_position.php';

    $placements = jor_fetch_placements_for_job_order($conn, $jobOrderId);
    if ($placements === []) {
        $hiredStmt = $conn->prepare('
            SELECT COUNT(*) AS c
            FROM job_applications
            WHERE JobOrderID = ?
              AND HiredDate IS NOT NULL
        ');
        $hired = 0;
        if ($hiredStmt) {
            $hiredStmt->bind_param('i', $jobOrderId);
            $hiredStmt->execute();
            $row = $hiredStmt->get_result()->fetch_assoc();
            $hired = (int)($row['c'] ?? 0);
            $hiredStmt->close();
        }
        $total = max(1, $requiredCountFallback);
        return [[
            'detail_id' => null,
            'position' => '',
            'position_title' => '',
            'deployment_site' => '',
            'site' => '',
            'work_schedule' => '',
            'required_workers' => $total,
            'total_slots' => $total,
            'filled_slots' => $hired,
            'open_slots' => max(0, $total - $hired),
            'is_filled' => $hired >= $total,
            'status' => $hired >= $total ? 'filled' : 'open',
        ]];
    }

    $countStmt = $conn->prepare('
        SELECT COUNT(*) AS c
        FROM job_applications
        WHERE JobOrderID = ?
          AND target_detail_id = ?
          AND HiredDate IS NOT NULL
    ');

    $slots = [];
    foreach ($placements as $p) {
        $detailId = (int)($p['detail_id'] ?? 0);
        $total = max(1, (int)($p['required_workers'] ?? 1));
        $filled = 0;
        if ($countStmt && $detailId > 0) {
            $countStmt->bind_param('ii', $jobOrderId, $detailId);
            $countStmt->execute();
            $countRow = $countStmt->get_result()->fetch_assoc();
            $filled = (int)($countRow['c'] ?? 0);
        }
        $open = max(0, $total - $filled);
        $isFilled = $filled >= $total;
        $slots[] = array_merge($p, [
            'total_slots' => $total,
            'filled_slots' => $filled,
            'open_slots' => $open,
            'is_filled' => $isFilled,
            'status' => $isFilled ? 'filled' : 'open',
        ]);
    }
    if ($countStmt) {
        $countStmt->close();
    }

    return $slots;
}

/**
 * @return array<int, array<string,mixed>>
 */
function job_order_fetch_placement_slots_pdo(PDO $pdo, int $jobOrderId, int $requiredCountFallback = 1): array
{
    $detailStmt = $pdo->prepare('
        SELECT detail_id, position_title, deployment_site, work_schedule, required_workers, job_category
        FROM job_order_details
        WHERE JobOrderID = ?
        ORDER BY position_title ASC, deployment_site ASC, detail_id ASC
    ');
    $detailStmt->execute([$jobOrderId]);
    $rows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $pdo->prepare('
        SELECT COUNT(*) AS c
        FROM job_applications
        WHERE JobOrderID = ?
          AND target_detail_id = ?
          AND HiredDate IS NOT NULL
    ');

    if (!$rows) {
        $legacyStmt = $pdo->prepare('
            SELECT COUNT(*) AS c
            FROM job_applications
            WHERE JobOrderID = ?
              AND HiredDate IS NOT NULL
        ');
        $legacyStmt->execute([$jobOrderId]);
        $hired = (int)($legacyStmt->fetchColumn() ?: 0);
        $total = max(1, $requiredCountFallback);
        return [[
            'detail_id' => null,
            'position' => '',
            'position_title' => '',
            'deployment_site' => '',
            'site' => '',
            'work_schedule' => '',
            'required_workers' => $total,
            'total_slots' => $total,
            'filled_slots' => $hired,
            'open_slots' => max(0, $total - $hired),
            'is_filled' => $hired >= $total,
            'status' => $hired >= $total ? 'filled' : 'open',
        ]];
    }

    $slots = [];
    foreach ($rows as $r) {
        $detailId = (int)($r['detail_id'] ?? 0);
        $total = max(1, (int)($r['required_workers'] ?? 1));
        $filled = 0;
        if ($detailId > 0) {
            $countStmt->execute([$jobOrderId, $detailId]);
            $filled = (int)($countStmt->fetchColumn() ?: 0);
        }
        $open = max(0, $total - $filled);
        $isFilled = $filled >= $total;
        $slots[] = [
            'detail_id' => $detailId,
            'position' => (string)($r['position_title'] ?? ''),
            'position_title' => (string)($r['position_title'] ?? ''),
            'deployment_site' => (string)($r['deployment_site'] ?? ''),
            'site' => (string)($r['deployment_site'] ?? ''),
            'work_schedule' => (string)($r['work_schedule'] ?? ''),
            'required_workers' => $total,
            'job_category' => (string)($r['job_category'] ?? ''),
            'total_slots' => $total,
            'filled_slots' => $filled,
            'open_slots' => $open,
            'is_filled' => $isFilled,
            'status' => $isFilled ? 'filled' : 'open',
        ];
    }

    return $slots;
}

/**
 * @param array<int, array<string,mixed>> $slots
 * @return array{total_slots:int,filled_slots:int,open_slots:int,all_filled:bool}
 */
function job_order_summarize_slots(array $slots): array
{
    $total = 0;
    $filled = 0;
    foreach ($slots as $slot) {
        $total += max(1, (int)($slot['total_slots'] ?? $slot['required_workers'] ?? 1));
        $filled += (int)($slot['filled_slots'] ?? 0);
    }
    $open = max(0, $total - $filled);
    $allFilled = $total > 0 && $open === 0;
    foreach ($slots as $slot) {
        if (empty($slot['is_filled'])) {
            $allFilled = false;
            break;
        }
    }
    return [
        'total_slots' => $total,
        'filled_slots' => $filled,
        'open_slots' => $open,
        'all_filled' => $allFilled,
    ];
}
