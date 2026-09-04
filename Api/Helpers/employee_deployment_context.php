<?php
/**
 * Resolve an employee's active deployment site from hire/placement records.
 */

function employee_is_generic_site(?string $site): bool
{
    $site = trim((string)$site);
    if ($site === '') {
        return true;
    }
    return strcasecmp($site, 'Multiple sites') === 0;
}

/**
 * @return array<string,mixed>|null
 */
function employee_fetch_hired_placement_context(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            c.Name AS company_name,
            COALESCE(NULLIF(TRIM(jod.position_title), ''), jo.Position) AS position,
            COALESCE(
                NULLIF(TRIM(jod.deployment_site), ''),
                NULLIF(TRIM(dep.Location), ''),
                NULLIF(TRIM(jo.Location), '')
            ) AS site,
            COALESCE(
                NULLIF(TRIM(jo.WorkSchedule), '')
            ) AS work_schedule,
            jod.deployment_site AS chosen_deployment_site,
            dep.Location AS deployment_table_location,
            jo.Location AS job_order_location
        FROM job_applications ja
        INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
        INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
        INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
        LEFT JOIN job_order_details jod ON ja.target_detail_id = jod.detail_id
        LEFT JOIN employee e ON e.Email = a.Email AND e.IsActive = 1
        LEFT JOIN deployment dep ON dep.EmployeeID = e.EmployeeID
            AND (dep.EndDate IS NULL OR dep.EndDate >= CURDATE())
        WHERE a.UserID = ?
          AND ja.HiredDate IS NOT NULL
        ORDER BY ja.HiredDate DESC, ja.ApplicationID DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    $chosen = trim((string)($row['chosen_deployment_site'] ?? ''));
    $site = trim((string)($row['site'] ?? ''));
    if ($chosen !== '') {
        $site = $chosen;
    } elseif (employee_is_generic_site($site)) {
        $depLoc = trim((string)($row['deployment_table_location'] ?? ''));
        if (!employee_is_generic_site($depLoc)) {
            $site = $depLoc;
        } else {
            $site = '';
        }
    }

    return [
        'company_name' => trim((string)($row['company_name'] ?? '')),
        'position' => trim((string)($row['position'] ?? '')),
        'site' => $site,
        'location' => $site,
        'work_schedule' => trim((string)($row['work_schedule'] ?? '')),
    ];
}

function employee_resolve_site_label(?string $deploymentLocation, ?array $hiredContext = null): string
{
    $deploymentLocation = trim((string)$deploymentLocation);
    if (!employee_is_generic_site($deploymentLocation)) {
        return $deploymentLocation;
    }

    if ($hiredContext) {
        $fromHire = trim((string)($hiredContext['site'] ?? $hiredContext['location'] ?? ''));
        if (!employee_is_generic_site($fromHire)) {
            return $fromHire;
        }
    }

    return $deploymentLocation !== '' ? $deploymentLocation : '—';
}