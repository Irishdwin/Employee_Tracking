<?php
/**
 * Contract expiry tracking — Service Agreement "Contract Type" (Open vs
 * Expiring) and the 30-day-before-expiry reminder for BOTH:
 *   - employmentcontract (already has ContractType: 'Open Contract' /
 *     'Expiring Contract' — see Api/HR/upload_contract.php, unchanged here)
 *   - serviceagreement (did NOT have a ContractType column — added below)
 *
 * Self-healing schema, same idiom as Api/Helpers/payroll_type_helper.php's
 * payroll_type_ensure_schema(): checks for the column, adds it only if
 * missing, never touches existing rows/columns otherwise.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/NotificationHelper.php';

function service_agreement_contract_type_ensure_schema(mysqli $conn): void
{
    $res = $conn->query("SHOW COLUMNS FROM `serviceagreement` LIKE 'ContractType'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE `serviceagreement` ADD COLUMN `ContractType` ENUM('Open','Expiring') NOT NULL DEFAULT 'Open'");
    }
}

/** @return 'Open'|'Expiring' */
function service_agreement_contract_type_normalize(?string $type): string
{
    $t = strtolower(trim((string)$type));
    return $t === 'expiring' ? 'Expiring' : 'Open';
}

/**
 * Roles that receive a service-agreement-expiring reminder. The project has
 * no functioning "Client Company" login/role today (clientcompany.UserID
 * exists as a column but no 'Client Company' row exists in the `roles`
 * table, and nothing in the codebase creates such a login) — so "notify the
 * client company" isn't reachable yet. Reminders go to the people who
 * actually manage service agreements in this system: Admin and HR.
 */
function contract_expiry_sa_notify_roles(): array
{
    return ['Admin', 'HR'];
}

/**
 * Runs the 30-day-before-expiry check for employment contracts and service
 * agreements, inserting a reminder notification for each contract that:
 *   - is explicitly of the "Expiring"/"Expiring Contract" type (Open
 *     contracts are skipped entirely — they have no ContractEndDate to
 *     check anyway)
 *   - has a ContractEndDate within the next 30 days (including already
 *     overdue ones, so a missed day doesn't silently drop the reminder)
 *   - has NOT already been reminded (deduplicated via a lookup against the
 *     existing `notifications` table itself — NotificationType +
 *     RelatedRecord — no new tracking column needed)
 *
 * Safe to call on every Admin dashboard load: idempotent, cheap SELECTs,
 * only INSERTs when a genuinely new reminder is due.
 *
 * @return array{employment_contracts_notified:int, service_agreements_notified:int}
 */
/** Agency policy: six calendar months from signing, only while the employee is active.
 * No signed date means no inferred eligibility. Existing signed files are retained.
 */
function employment_contract_open_after_six_months(mysqli $conn): int
{
    $conn->begin_transaction();
    try {
        $conn->query("UPDATE employmentcontract ec JOIN employee e ON e.EmployeeID=ec.EmployeeID
            SET ec.ContractType='Open Contract', ec.ContractEndDate=NULL
            WHERE e.IsActive=1 AND ec.ContractType='Expiring Contract'
              AND ec.SignedDate IS NOT NULL AND DATE_ADD(ec.SignedDate, INTERVAL 6 MONTH)<=CURDATE()");
        $changed=$conn->affected_rows;
        // The applicant upload flow stores metadata in Remarks, including after hiring.
        $rows=$conn->query("SELECT ja.ApplicationID, ja.Remarks FROM job_applications ja
            JOIN applicants a ON a.ApplicantID=ja.ApplicantID
            JOIN employee e ON e.Email=a.Email AND e.IsActive=1
            WHERE ja.HiredDate IS NOT NULL AND ja.Remarks LIKE '%Contract uploaded: type=Expiring Contract;%'
            FOR UPDATE");
        $stmt=$conn->prepare('UPDATE job_applications SET Remarks=? WHERE ApplicationID=?');
        while($row=$rows->fetch_assoc()) {
            if(!preg_match('/Contract uploaded: ([^\r\n]*)/', $row['Remarks'], $entry)) continue;
            if(!preg_match('/(?:^|; )signed_date=(\d{4}-\d{2}-\d{2})(?:;|$)/', $entry[1], $date)) continue;
            $signed=DateTimeImmutable::createFromFormat('!Y-m-d', $date[1]);
            if(!$signed || $signed->format('Y-m-d')!==$date[1]) continue;
            // MySQL calendar-month addition clamps month-end, matching stored contracts.
            $eligible=$conn->prepare('SELECT DATE_ADD(?, INTERVAL 6 MONTH)<=CURDATE() AS eligible');
            $eligible->bind_param('s',$date[1]);$eligible->execute();
            $due=(bool)$eligible->get_result()->fetch_assoc()['eligible'];$eligible->close();
            if(!$due) continue;
            $updated=str_replace('type=Expiring Contract;', 'type=Open Contract;', $entry[0]);
            $updated=preg_replace('/expiry_date=\d{4}-\d{2}-\d{2}/', 'expiry_date=', $updated);
            $remarks=str_replace($entry[0],$updated,$row['Remarks']);
            $id=(int)$row['ApplicationID'];$stmt->bind_param('si',$remarks,$id);$stmt->execute();
            $changed+=$stmt->affected_rows;
        }
        $stmt->close();$conn->commit();return $changed;
    } catch(Throwable $e) { $conn->rollback();throw $e; }
}

function run_contract_expiry_check(mysqli $conn): array
{
    service_agreement_contract_type_ensure_schema($conn);

    $converted = employment_contract_open_after_six_months($conn);
    $employmentNotified = contract_expiry_check_employment_contracts($conn);
    $serviceAgreementNotified = contract_expiry_check_service_agreements($conn);

    return [
        'employment_contracts_converted' => $converted,
        'employment_contracts_notified' => $employmentNotified,
        'service_agreements_notified' => $serviceAgreementNotified,
    ];
}

function contract_expiry_already_notified(mysqli $conn, int $userId, string $notificationType, int $relatedRecordId): bool
{
    $stmt = $conn->prepare(
        "SELECT NotificationID FROM notifications
         WHERE UserID = ? AND NotificationType = ? AND RelatedRecord = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('isi', $userId, $notificationType, $relatedRecordId);
    $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $found;
}

function contract_expiry_check_employment_contracts(mysqli $conn): int
{
    $sql = "
        SELECT ec.EmploymentContractID, ec.ContractEndDate, ec.ContractNumber,
               e.EmployeeID, u.UsersID, CONCAT(e.FirstName, ' ', e.LastName) AS employee_name
        FROM employmentcontract ec
        INNER JOIN employee e ON e.EmployeeID = ec.EmployeeID AND e.IsActive = 1
        INNER JOIN users u ON u.Email = e.Email
        WHERE ec.ContractType = 'Expiring Contract'
          AND ec.ContractEndDate IS NOT NULL
          AND ec.ContractEndDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ";
    $res = $conn->query($sql);
    if (!$res) {
        return 0;
    }

    $count = 0;
    while ($row = $res->fetch_assoc()) {
        $contractId = (int)$row['EmploymentContractID'];
        $userId = (int)$row['UsersID'];

        if (contract_expiry_already_notified($conn, $userId, 'employment_contract_expiring', $contractId)) {
            continue;
        }

        $endDate = $row['ContractEndDate'];
        $daysLeft = (int)floor((strtotime($endDate) - strtotime(date('Y-m-d'))) / 86400);
        $daysLeftLabel = $daysLeft >= 0 ? ($daysLeft . ' day(s)') : 'already past due';

        $title = 'Your Employment Contract is Expiring Soon';
        $message = sprintf(
            'Your employment contract %s is set to expire on %s (%s). Please coordinate with HR regarding renewal.',
            $row['ContractNumber'] ?: ('#' . $contractId),
            date('F j, Y', strtotime($endDate)),
            $daysLeftLabel
        );

        if (insert_system_notification($conn, $userId, 'employment_contract_expiring', $title, $message, 'employment_contract', $contractId)) {
            $count++;
        }
    }

    return $count;
}

function contract_expiry_check_service_agreements(mysqli $conn): int
{
    $sql = "
        SELECT sa.ServiceAgreementID, sa.ContractEndDate, sa.ContractNumber, cc.Name AS company_name
        FROM serviceagreement sa
        INNER JOIN clientcompany cc ON cc.CompanyID = sa.ClientCompanyID
        WHERE sa.ContractType = 'Expiring'
          AND sa.Status = 'Active'
          AND sa.ContractEndDate IS NOT NULL
          AND sa.ContractEndDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ";
    $res = $conn->query($sql);
    if (!$res) {
        return 0;
    }

    $allowedRoles = contract_expiry_sa_notify_roles();
    $placeholders = implode(',', array_fill(0, count($allowedRoles), '?'));
    $types = str_repeat('s', count($allowedRoles));
    $recipientStmt = $conn->prepare("
        SELECT u.UsersID FROM users u
        INNER JOIN roles r ON r.RolesID = u.RoleID
        WHERE u.IsActive = 1 AND r.RoleName IN ($placeholders)
    ");

    $count = 0;
    while ($row = $res->fetch_assoc()) {
        $agreementId = (int)$row['ServiceAgreementID'];
        $endDate = $row['ContractEndDate'];
        $daysLeft = (int)floor((strtotime($endDate) - strtotime(date('Y-m-d'))) / 86400);
        $daysLeftLabel = $daysLeft >= 0 ? ($daysLeft . ' day(s)') : 'already past due';

        $title = 'Service Agreement Expiring Soon';
        $message = sprintf(
            'The Service Agreement %s with %s is set to expire on %s (%s). Please coordinate renewal with the client.',
            $row['ContractNumber'] ?: ('#' . $agreementId),
            $row['company_name'],
            date('F j, Y', strtotime($endDate)),
            $daysLeftLabel
        );

        $recipientStmt->bind_param($types, ...$allowedRoles);
        $recipientStmt->execute();
        $recipients = $recipientStmt->get_result();

        while ($recipient = $recipients->fetch_assoc()) {
            $userId = (int)$recipient['UsersID'];
            if (contract_expiry_already_notified($conn, $userId, 'service_agreement_expiring', $agreementId)) {
                continue;
            }
            if (insert_system_notification($conn, $userId, 'service_agreement_expiring', $title, $message, 'service_agreement', $agreementId)) {
                $count++;
            }
        }
    }
    if ($recipientStmt) {
        $recipientStmt->close();
    }

    return $count;
}
