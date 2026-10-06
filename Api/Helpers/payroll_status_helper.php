<?php
/**
 * Team-level Payroll Status Tracking — shared helper.
 *
 * Self-healing schema (same idiom as payroll_type_helper.php,
 * contract_expiry_helper.php elsewhere in this project): the table is
 * created on first use if the manual SQL migration hasn't been run yet.
 *
 * The "payroll group" key throughout this feature is always the same 4
 * values: SiteSupervisorID (Team Lead), CompanyID, CutoffStartDate,
 * CutoffEndDate — matching exactly how
 * Api/Accounting/get_submitted_dtrs.php already groups its batch list.
 */
declare(strict_types=1);

function payroll_status_ensure_schema(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS `payroll_group_status_history` (
          `PayrollStatusHistoryID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `SiteSupervisorID` INT NOT NULL,
          `CompanyID` INT NOT NULL,
          `CutoffStartDate` DATE NOT NULL,
          `CutoffEndDate` DATE NOT NULL,
          `Status` VARCHAR(50) NOT NULL,
          `Remarks` TEXT DEFAULT NULL,
          `ProofImagePath` VARCHAR(255) DEFAULT NULL,
          `UpdatedBy` INT NOT NULL,
          `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`PayrollStatusHistoryID`),
          KEY `idx_payroll_status_group` (`SiteSupervisorID`, `CompanyID`, `CutoffStartDate`, `CutoffEndDate`, `CreatedAt`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/**
 * Statuses Accounting may manually set through the "Update Payroll Status"
 * modal. The earlier stages (Pending DTR / DTR Submitted / DTR Verified /
 * Payroll Computed) stay auto-derived from dtr_submissions/payroll exactly
 * as they already are elsewhere in the app — Accounting isn't required to
 * manually re-log those, though 'Payroll Computed' is included here in
 * case they want to log it explicitly with a remark.
 */
function payroll_status_allowed_values(): array
{
    return [
        'Payroll Computed',
        'For Client Approval',
        'Returned for Revision',
        'Client Approved',
        'Payroll Released',
        'On Hold',
        'Needs Correction',
    ];
}

/** Latest manually-set status row for a payroll group, or null if none yet. */
function payroll_status_get_current(mysqli $conn, int $siteSupervisorId, int $companyId, string $cutoffStart, string $cutoffEnd): ?array
{
    $stmt = $conn->prepare("
        SELECT PayrollStatusHistoryID, Status, Remarks, ProofImagePath, UpdatedBy, CreatedAt
        FROM payroll_group_status_history
        WHERE SiteSupervisorID = ? AND CompanyID = ? AND CutoffStartDate = ? AND CutoffEndDate = ?
        ORDER BY CreatedAt DESC, PayrollStatusHistoryID DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('iiss', $siteSupervisorId, $companyId, $cutoffStart, $cutoffEnd);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Full history timeline for a payroll group, oldest first. */
function payroll_status_get_history(mysqli $conn, int $siteSupervisorId, int $companyId, string $cutoffStart, string $cutoffEnd): array
{
    $stmt = $conn->prepare("
        SELECT psh.PayrollStatusHistoryID, psh.Status, psh.Remarks, psh.ProofImagePath, psh.CreatedAt,
               u.FullName AS updated_by_name
        FROM payroll_group_status_history psh
        LEFT JOIN users u ON u.UsersID = psh.UpdatedBy
        WHERE psh.SiteSupervisorID = ? AND psh.CompanyID = ? AND psh.CutoffStartDate = ? AND psh.CutoffEndDate = ?
        ORDER BY psh.CreatedAt ASC, psh.PayrollStatusHistoryID ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iiss', $siteSupervisorId, $companyId, $cutoffStart, $cutoffEnd);
    $stmt->execute();
    $res = $stmt->get_result();
    $history = [];
    while ($row = $res->fetch_assoc()) {
        $history[] = [
            'id' => (int)$row['PayrollStatusHistoryID'],
            'status' => $row['Status'],
            'remarks' => $row['Remarks'],
            'has_proof' => $row['ProofImagePath'] !== null,
            'updated_by' => $row['updated_by_name'] ?? 'Accounting',
            'created_at' => $row['CreatedAt'],
        ];
    }
    $stmt->close();
    return $history;
}

/**
 * Confirms this exact payroll group actually exists (has at least one
 * dtr_submissions row matching all four keys) before anything writes to
 * it — prevents logging a status against a Team Lead/company/cutoff
 * combination that was never actually submitted.
 */
function payroll_status_group_exists(mysqli $conn, int $siteSupervisorId, int $companyId, string $cutoffStart, string $cutoffEnd): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM dtr_submissions ds
        INNER JOIN deployment d ON d.DeploymentID = ds.DeploymentID
        WHERE ds.SiteSupervisorID = ? AND d.CompanyID = ? AND ds.CutoffStartDate = ? AND ds.CutoffEndDate = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iiss', $siteSupervisorId, $companyId, $cutoffStart, $cutoffEnd);
    $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $found;
}