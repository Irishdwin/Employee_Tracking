<?php
/**
 * Team Lead — assigned client company & active service agreement (read-only).
 * GET — returns JSON for the logged-in Team Lead session.
 */
require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Helpers/payroll_type_helper.php';
require __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn — needed for payroll_type_* lookups

payroll_type_ensure_schema($conn);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = trim((string)$_SESSION['role']);
if (!in_array($role, ['TeamLead', 'Team Lead'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden — Team Lead access only']);
    exit();
}

$teamLeadUserId = (int)$_SESSION['user_id'];

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=onazul_hris_db;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit();
}

/**
 * Compute agreement badge: Active | Expiring Soon | Expired | No Agreement
 */
function tl_agreement_badge(?string $startDate, ?string $endDate): array
{
    $today = new DateTimeImmutable('today');
    if ($startDate === null || $startDate === '') {
        return ['status' => 'no_agreement', 'label' => 'No Agreement', 'badge_class' => 'bg-secondary'];
    }

    try {
        $start = new DateTimeImmutable($startDate);
    } catch (Exception $e) {
        return ['status' => 'unknown', 'label' => 'Unknown', 'badge_class' => 'bg-secondary'];
    }

    if ($endDate === null || $endDate === '') {
        if ($today >= $start) {
            return ['status' => 'active', 'label' => 'Active', 'badge_class' => 'bg-success'];
        }
        return ['status' => 'pending', 'label' => 'Not Yet Active', 'badge_class' => 'bg-warning text-dark'];
    }

    try {
        $end = new DateTimeImmutable($endDate);
    } catch (Exception $e) {
        $end = null;
    }

    if ($end && $today > $end) {
        return ['status' => 'expired', 'label' => 'Expired', 'badge_class' => 'bg-danger'];
    }

    if ($today < $start) {
        return ['status' => 'pending', 'label' => 'Not Yet Active', 'badge_class' => 'bg-warning text-dark'];
    }

    if ($end) {
        $daysLeft = (int)$today->diff($end)->format('%r%a');
        if ($daysLeft >= 0 && $daysLeft <= 30) {
            return ['status' => 'expiring_soon', 'label' => 'Expiring Soon', 'badge_class' => 'bg-warning text-dark'];
        }
    }

    return ['status' => 'active', 'label' => 'Active', 'badge_class' => 'bg-success'];
}

$sql = "
    SELECT
        c.CompanyID              AS company_id,
        c.Name                   AS company_name,
        c.Address                AS company_address,
        c.ContactPerson          AS contact_person,
        c.ContactNumber          AS contact_number,
        c.Industry               AS industry,
        c.IsActive               AS company_is_active,
        c.PayrollType            AS payroll_type,
        sa.ServiceAgreementID    AS service_agreement_id,
        sa.ContractNumber        AS contract_number,
        sa.ContractStartDate     AS service_agreement_start,
        sa.ContractEndDate       AS service_agreement_end,
        sa.Status                AS agreement_status,
        sa.SignedDocumentPath    AS signed_document_path,
        'assigned_manager'       AS assignment_source
    FROM clientcompany c
    LEFT JOIN serviceagreement sa ON sa.ClientCompanyID = c.CompanyID
        AND sa.ServiceAgreementID = (
            SELECT sa2.ServiceAgreementID
            FROM serviceagreement sa2
            WHERE sa2.ClientCompanyID = c.CompanyID
              AND sa2.Status = 'Active'
            ORDER BY sa2.ContractStartDate DESC, sa2.ServiceAgreementID DESC
            LIMIT 1
        )
    WHERE c.UserID = :tl_user_id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['tl_user_id' => $teamLeadUserId]);
$row = $stmt->fetch();

if (!$row) {
    $fallbackSql = "
        SELECT
            c.CompanyID              AS company_id,
            c.Name                   AS company_name,
            c.Address                AS company_address,
            c.ContactPerson          AS contact_person,
            c.ContactNumber          AS contact_number,
            c.Industry               AS industry,
            c.IsActive               AS company_is_active,
            c.PayrollType            AS payroll_type,
            sa.ServiceAgreementID    AS service_agreement_id,
            sa.ContractNumber        AS contract_number,
            sa.ContractStartDate     AS service_agreement_start,
            sa.ContractEndDate       AS service_agreement_end,
            sa.Status                AS agreement_status,
            sa.SignedDocumentPath    AS signed_document_path,
            'deployment_supervisor'  AS assignment_source
        FROM deployment d
        INNER JOIN clientcompany c ON c.CompanyID = d.CompanyID
        LEFT JOIN serviceagreement sa ON sa.ClientCompanyID = c.CompanyID
            AND sa.ServiceAgreementID = (
                SELECT sa2.ServiceAgreementID
                FROM serviceagreement sa2
                WHERE sa2.ClientCompanyID = c.CompanyID
                  AND sa2.Status = 'Active'
                ORDER BY sa2.ContractStartDate DESC, sa2.ServiceAgreementID DESC
                LIMIT 1
            )
        WHERE d.SiteSuperVisorID = :tl_user_id
          AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        ORDER BY d.StartDate DESC, d.DeploymentID DESC
        LIMIT 1
    ";
    $fb = $pdo->prepare($fallbackSql);
    $fb->execute(['tl_user_id' => $teamLeadUserId]);
    $row = $fb->fetch();
}

if (!$row) {
    echo json_encode([
        'success' => true,
        'has_company' => false,
        'message' => 'No client company is currently assigned to your account.',
        'company' => null,
    ]);
    exit();
}

$badge = tl_agreement_badge(
    $row['service_agreement_start'] ?? null,
    $row['service_agreement_end'] ?? null
);

$durationLabel = '—';
if (!empty($row['service_agreement_start'])) {
    $durationLabel = $row['service_agreement_start'];
    if (!empty($row['service_agreement_end'])) {
        $durationLabel .= ' to ' . $row['service_agreement_end'];
    } else {
        $durationLabel .= ' to Ongoing';
    }
}

// Payroll Type is a configuration of the Client Company — the Team Lead
// never selects it, it's simply read off the assigned company and used to
// resolve the current cutoff/release/deductions via the centralized
// payroll_type_helper.php service.
$companyId = (int)$row['company_id'];
$payrollType = payroll_type_normalize($row['payroll_type'] ?? null);
$currentPeriod = payroll_type_period($payrollType);
$deductions = payroll_type_deductions($currentPeriod['type'], $currentPeriod['half']);

echo json_encode([
    'success' => true,
    'has_company' => true,
    'company' => [
        'company_id' => $companyId,
        'company_name' => $row['company_name'] ?? '',
        'contract_number' => $row['contract_number'] ?? '',
        'service_agreement_start' => $row['service_agreement_start'] ?? '',
        'service_agreement_end' => $row['service_agreement_end'] ?? '',
        'service_agreement_duration' => $durationLabel,
        'company_address' => $row['company_address'] ?? '',
        'contact_person' => $row['contact_person'] ?? '',
        'contact_number' => $row['contact_number'] ?? '',
        'industry' => $row['industry'] ?? '',
        'company_is_active' => (int)($row['company_is_active'] ?? 0) === 1,
        'service_agreement_id' => isset($row['service_agreement_id']) ? (int)$row['service_agreement_id'] : null,
        'agreement_status' => $row['agreement_status'] ?? '',
        'signed_document_path' => $row['signed_document_path'] ?? '',
        'assignment_source' => $row['assignment_source'] ?? '',
        'agreement_badge' => $badge,
        'payroll_type' => $payrollType,
        'payroll_type_label' => payroll_type_label($payrollType),
    ],
    'payroll' => [
        'type' => $payrollType,
        'type_label' => payroll_type_label($payrollType),
        'current_period' => $currentPeriod,
        'cutoff_label' => $currentPeriod['label'],
        'release_date' => $currentPeriod['payday'],
        'release_date_display' => $currentPeriod['payday_display'],
        'deductions' => $deductions,
        'deductions_list' => array_values(array_filter([
            $deductions['philhealth'] ? 'PhilHealth' : null,
            $deductions['pagibig'] ? 'Pag-IBIG' : null,
            $deductions['sss'] ? 'SSS' : null,
        ])),
    ],
]);