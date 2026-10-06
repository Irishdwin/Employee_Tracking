<?php
/**
 * Multi-position Job Order Request schema helpers.
 */
declare(strict_types=1);

function jor_multi_ensure_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS job_order_details (
        detail_id INT(11) NOT NULL AUTO_INCREMENT,
        RequestID INT(11) NOT NULL,
        position_title VARCHAR(255) NOT NULL,
        category_id INT UNSIGNED DEFAULT NULL,
        job_category VARCHAR(255) DEFAULT NULL,
        required_workers INT(11) NOT NULL DEFAULT 1,
        deployment_site VARCHAR(255) NOT NULL,
        work_schedule VARCHAR(150) NOT NULL,
        JobOrderID INT(11) DEFAULT NULL,
        CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (detail_id),
        KEY idx_jod_request (RequestID),
        KEY idx_jod_joborder (JobOrderID),
        CONSTRAINT fk_jod_request FOREIGN KEY (RequestID) REFERENCES job_order_requests (RequestID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $cols = $conn->query("SHOW COLUMNS FROM job_order_requests LIKE 'IsConsolidated'");
        if ($cols && $cols->num_rows === 0) {
            $conn->query("ALTER TABLE job_order_requests ADD COLUMN IsConsolidated TINYINT(1) NOT NULL DEFAULT 0 AFTER Remarks");
        }
    } catch (Throwable $e) {
    }

    try {
        $cols = $conn->query("SHOW COLUMNS FROM job_applications LIKE 'target_detail_id'");
        if ($cols && $cols->num_rows === 0) {
            $conn->query("ALTER TABLE job_applications ADD COLUMN target_detail_id INT(11) DEFAULT NULL AFTER JobOrderID");
        }
    } catch (Throwable $e) {
    }

    try {
        $fk = $conn->query("SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'job_applications'
              AND CONSTRAINT_NAME = 'fk_app_target_detail'");
        $row = $fk ? $fk->fetch_assoc() : null;
        if (!$row || (int)$row['c'] === 0) {
            $conn->query("ALTER TABLE job_applications
                ADD CONSTRAINT fk_app_target_detail
                FOREIGN KEY (target_detail_id) REFERENCES job_order_details (detail_id) ON DELETE SET NULL");
        }
    } catch (Throwable $e) {
    }
}

/**
 * @return array<int, array<string,mixed>>
 */
function jor_fetch_details_for_request(mysqli $conn, int $requestId): array
{
    $stmt = $conn->prepare("
        SELECT detail_id, RequestID, position_title, category_id, job_category,
               required_workers, deployment_site, work_schedule, JobOrderID
        FROM job_order_details
        WHERE RequestID = ?
        ORDER BY detail_id ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

/**
 * @return array<int, array<string,mixed>>
 */
function jor_fetch_placements_for_job_order(mysqli $conn, int $jobOrderId): array
{
    $stmt = $conn->prepare("
        SELECT detail_id, position_title, deployment_site, work_schedule, required_workers, job_category,category_id,classification_id,sub_classification_id
        FROM job_order_details
        WHERE JobOrderID = ?
        ORDER BY position_title ASC, deployment_site ASC, detail_id ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $jobOrderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'detail_id' => (int)$r['detail_id'],
            'category_id' => (int)$r['category_id'],
            'classification_id' => (int)$r['classification_id'],
            'sub_classification_id' => (int)$r['sub_classification_id'],
            'position' => (string)$r['position_title'],
            'position_title' => (string)$r['position_title'],
            'deployment_site' => (string)$r['deployment_site'],
            'site' => (string)$r['deployment_site'],
            'work_schedule' => (string)$r['work_schedule'],
            'required_workers' => (int)$r['required_workers'],
            'workers' => (int)$r['required_workers'],
            'job_category' => (string)($r['job_category'] ?? ''),
            'label' => trim((string)$r['position_title']) . ' — ' . trim((string)$r['deployment_site']),
        ];
    }
    $stmt->close();
    return $rows;
}

function jor_tl_can_access_company(mysqli $conn, int $userId, int $companyId): bool
{
    if ($companyId <= 0) {
        return false;
    }
    $checkDep = $conn->prepare('SELECT DeploymentID FROM deployment WHERE SiteSuperVisorID = ? AND CompanyID = ? LIMIT 1');
    if ($checkDep) {
        $checkDep->bind_param('ii', $userId, $companyId);
        $checkDep->execute();
        $assigned = $checkDep->get_result()->num_rows > 0;
        $checkDep->close();
        if ($assigned) {
            return true;
        }
    }
    $checkCc = $conn->prepare('SELECT CompanyID FROM clientcompany WHERE CompanyID = ? AND UserID = ? LIMIT 1');
    if (!$checkCc) {
        return false;
    }
    $checkCc->bind_param('ii', $companyId, $userId);
    $checkCc->execute();
    $assigned = $checkCc->get_result()->num_rows > 0;
    $checkCc->close();
    return $assigned;
}

function jor_company_has_active_agreement(mysqli $conn, int $companyId): bool
{
    if ($companyId <= 0) {
        return false;
    }
    $sql = "SELECT ServiceAgreementID
            FROM serviceagreement
            WHERE ClientCompanyID = ?
              AND Status = 'Active'
              AND COALESCE(NULLIF(TRIM(SignedDocumentPath), ''), '') <> ''
            ORDER BY ServiceAgreementID DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $has = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $has;
}
