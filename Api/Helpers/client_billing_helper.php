<?php
/**
 * Client payroll billing — payroll run registry, per-employee billing rows, XLSX column map, status tracking.
 */
declare(strict_types=1);

require_once __DIR__ . '/employee_payroll_helper.php';
require_once __DIR__ . '/employee_salary_helper.php';
require_once __DIR__ . '/attendance_pdo.php';
require_once __DIR__ . '/deduction_rates_helper.php';
require_once __DIR__ . '/payroll_type_helper.php';
require_once __DIR__ . '/payroll_status_service.php';

function client_billing_ensure_schema(mysqli $conn): void
{
    employee_salary_ensure_schema($conn);
    deduction_rates_ensure_schema($conn);
    payroll_type_ensure_schema($conn);

    $conn->query(
        "CREATE TABLE IF NOT EXISTS `payroll_runs` (
            `PayrollRunID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `RunCode` VARCHAR(32) NOT NULL,
            `CompanyID` INT NOT NULL,
            `PeriodStart` DATE NOT NULL,
            `PeriodEnd` DATE NOT NULL,
            `CutoffLabel` VARCHAR(120) DEFAULT NULL,
            `Status` VARCHAR(32) NOT NULL DEFAULT 'Draft',
            `CurrentStatus` VARCHAR(64) NOT NULL DEFAULT 'Pending DTR',
            `AdminOverheadPct` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            `VatPct` DECIMAL(5,2) NOT NULL DEFAULT 12.00,
            `CreatedBy` INT DEFAULT NULL,
            `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ApprovedAt` DATETIME DEFAULT NULL,
            PRIMARY KEY (`PayrollRunID`),
            UNIQUE KEY `uk_payroll_runs_code` (`RunCode`),
            KEY `idx_payroll_runs_company` (`CompanyID`),
            KEY `idx_payroll_runs_period` (`PeriodStart`, `PeriodEnd`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS `payroll_status_history` (
            `HistoryID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `PayrollRunID` INT UNSIGNED NOT NULL,
            `Status` VARCHAR(64) NOT NULL,
            `Remarks` TEXT DEFAULT NULL,
            `AttachmentPath` VARCHAR(255) DEFAULT NULL,
            `UpdatedBy` INT NOT NULL,
            `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`HistoryID`),
            KEY `idx_psh_run` (`PayrollRunID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    client_billing_ensure_column($conn, 'payroll_runs', 'BillingRefNo', 'BillingRefNo VARCHAR(32) DEFAULT NULL');
    client_billing_ensure_column($conn, 'payroll_runs', 'DeploymentSite', 'DeploymentSite VARCHAR(255) DEFAULT NULL');
    client_billing_ensure_column($conn, 'payroll_runs', 'TotalInvoice', 'TotalInvoice DECIMAL(14,2) DEFAULT NULL');
    client_billing_ensure_column($conn, 'payroll_runs', 'CurrentStatus', "CurrentStatus VARCHAR(64) NOT NULL DEFAULT 'Pending DTR'");
    client_billing_ensure_column($conn, 'payroll_runs', 'SiteID', 'SiteID INT DEFAULT NULL');
    client_billing_ensure_column($conn, 'payroll_runs', 'PayrollTypeSnapshot', 'PayrollTypeSnapshot VARCHAR(1) DEFAULT NULL');
    client_billing_ensure_column($conn, 'payroll_runs', 'SiteSupervisorID', 'SiteSupervisorID INT DEFAULT NULL');
}

function client_billing_ensure_column(mysqli $conn, string $table, string $column, string $definition): void
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return;
    }
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN {$definition}");
    }
}

function client_billing_billing_ref(string $runCode): string
{
    $runCode = trim($runCode);
    if (str_starts_with($runCode, 'PR-')) {
        return 'BR-' . substr($runCode, 3);
    }
    if (str_starts_with($runCode, 'BR-')) {
        return $runCode;
    }
    return 'BR-' . $runCode;
}

function client_billing_run_code_from_ref(string $billingRef): string
{
    $billingRef = trim($billingRef);
    if (str_starts_with($billingRef, 'BR-')) {
        return 'PR-' . substr($billingRef, 3);
    }
    return $billingRef;
}

function client_billing_primary_deployment_site(mysqli $conn, int $companyId, string $start, string $end): string
{
    $stmt = $conn->prepare(
        "SELECT dep.Location, COUNT(*) AS site_count
         FROM deployment dep
         INNER JOIN employee e ON e.EmployeeID = dep.EmployeeID AND e.IsActive = 1
         WHERE dep.CompanyID = ?
           AND (dep.EndDate IS NULL OR dep.EndDate >= ?)
           AND (dep.StartDate IS NULL OR dep.StartDate <= ?)
           AND NULLIF(TRIM(dep.Location), '') IS NOT NULL
         GROUP BY dep.Location
         ORDER BY site_count DESC, dep.Location ASC
         LIMIT 1"
    );
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('iss', $companyId, $start, $end);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return trim((string)($row['Location'] ?? ''));
}

function client_billing_refresh_run_metadata(mysqli $conn, string $runCode): void
{
    client_billing_ensure_schema($conn);
    $run = client_billing_get_run($conn, $runCode);
    if (!$run) {
        return;
    }

    $billingRef = client_billing_billing_ref($runCode);
    $site = client_billing_primary_deployment_site(
        $conn,
        (int)$run['CompanyID'],
        (string)$run['PeriodStart'],
        (string)$run['PeriodEnd']
    );

    $stmt = $conn->prepare(
        "UPDATE payroll_runs SET BillingRefNo = ?, DeploymentSite = ? WHERE RunCode = ? AND TotalInvoice IS NULL AND Status IN ('Draft','Pending')"
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sss', $billingRef, $site, $runCode);
    $stmt->execute();
    $stmt->close();
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function client_billing_map_run_row(mysqli $conn, array $row): array
{
    $runCode = (string)$row['RunCode'];
    $billingRef = trim((string)($row['BillingRefNo'] ?? ''));
    if ($billingRef === '') {
        $billingRef = client_billing_billing_ref($runCode);
    }

    $deploymentSite = trim((string)($row['DeploymentSite'] ?? ''));
    if ($deploymentSite === '') {
        $deploymentSite = client_billing_primary_deployment_site(
            $conn,
            (int)$row['CompanyID'],
            (string)$row['PeriodStart'],
            (string)$row['PeriodEnd']
        );
    }

    $totalInvoice = isset($row['TotalInvoice']) && $row['TotalInvoice'] !== null
        ? round((float)$row['TotalInvoice'], 2)
        : null;

    $currentStatus = trim((string)($row['CurrentStatus'] ?? ''));
    if ($currentStatus === '') {
        $currentStatus = 'Pending DTR';
    }
    if(!empty($row['SiteSupervisorID']) && !empty($row['SiteID'])) {
        $dtr=payroll_query($conn,'SELECT ds.DTR_SubmissionID FROM dtr_submissions ds JOIN deployment d ON d.DeploymentID=ds.DeploymentID AND d.EmployeeID=ds.EmployeeID WHERE d.CompanyID=? AND ds.SiteSupervisorID=? AND ds.SiteID=? AND ds.CutoffStartDate=? AND ds.CutoffEndDate=? LIMIT 1','iiiss',[(int)$row['CompanyID'],(int)$row['SiteSupervisorID'],(int)$row['SiteID'],$row['PeriodStart'],$row['PeriodEnd']])->get_result()->fetch_assoc();
        if($dtr)$currentStatus=payroll_status_for_dtr($conn,(int)$dtr['DTR_SubmissionID'])['current']['status'] ?? $currentStatus;
    }

    return [
        'payroll_run_id' => (int)$row['PayrollRunID'],
        'run_id' => $runCode,
        'billing_ref_no' => $billingRef,
        'client_name' => (string)$row['client_name'],
        'client' => (string)$row['client_name'],
        'deployment_site' => $deploymentSite !== '' ? $deploymentSite : '—',
        'company_id' => (int)$row['CompanyID'],
        'site_id' => (int)($row['SiteID'] ?? 0),
        'site_supervisor_id' => (int)($row['SiteSupervisorID'] ?? 0),
        'period_start' => (string)$row['PeriodStart'],
        'period_end' => (string)$row['PeriodEnd'],
        'cutoff_period' => (string)($row['CutoffLabel'] ?? ''),
        'cutoff' => (string)($row['CutoffLabel'] ?? ''),
        'status' => (string)$row['Status'],
        'current_status' => $currentStatus,
        'employee_count' => (int)$row['employee_count'],
        'verified_employee_count'=>(int)($row['verified_employee_count']??0),
        'exportable' => (int)($row['site_count'] ?? 1)<=1 && (int)($row['verified_employee_count']??0)>0,
        'export_block_reason'=>(int)($row['site_count']??1)>1?'Choose a site-specific run.':'No verified DTRs for this cutoff.',
        'total_invoice' => $totalInvoice,
        'admin_overhead_pct' => (float)$row['AdminOverheadPct'],
        'vat_pct' => (float)$row['VatPct'],
        'approved_at' => $row['ApprovedAt'],
        'payroll_type' => payroll_type_normalize($row['payroll_type'] ?? null),
        'payroll_type_label' => payroll_type_label($row['payroll_type'] ?? null),
    ];
}

function client_billing_run_code(string $periodStart, string $periodEnd, int $companyId, int $leadId = 0, int $siteId = 0): string
{
    // Was: year+month+coarse "day<=10 vs >10" half-of-month bucket. This project's actual
    // kinsenas boundaries are 11th-25th / 26th-10th, so nearly every period END date falls
    // in "half B" — meaning two genuinely different cutoff ranges for the same company could
    // hash to the identical RunCode and silently collide via ON DUPLICATE KEY UPDATE (only
    // CutoffLabel gets overwritten; the run's actual PeriodStart/PeriodEnd could end up
    // belonging to whichever pair happened to insert first). Keying on the exact start/end
    // dates guarantees each distinct cutoff range for a company gets its own row.
    $start = new DateTime($periodStart);
    $end = new DateTime($periodEnd);
    return sprintf('PR-%s-%s-C%d', $start->format('Ymd'), $end->format('Ymd'), $companyId)
        . ($leadId > 0 ? '-T'.$leadId : '') . ($siteId > 0 ? '-S'.$siteId : '');
}

function client_billing_sync_runs(mysqli $conn): void
{
    client_billing_ensure_schema($conn);

    $subRes = $conn->query("
        SELECT DISTINCT ds.CutoffStartDate, ds.CutoffEndDate, ds.CutoffPeriod, dep.CompanyID
        FROM dtr_submissions ds
        INNER JOIN deployment dep ON dep.DeploymentID = ds.DeploymentID
        WHERE ds.CutoffStartDate IS NOT NULL AND ds.CutoffEndDate IS NOT NULL
    ");
    
    $cutoffPairs = [];
    if ($subRes) {
        while ($sp = $subRes->fetch_assoc()) {
            $cutoffPairs[] = [
                $sp['CutoffStartDate'],
                $sp['CutoffEndDate'],
                $sp['CutoffPeriod'] ?? (date('M j, Y', strtotime($sp['CutoffStartDate'])) . ' – ' . date('M j, Y', strtotime($sp['CutoffEndDate']))),
                (int)$sp['CompanyID']
            ];
        }
    }

    $companies = $conn->query('SELECT CompanyID, PayrollType FROM clientcompany WHERE IsActive = 1');
    $compList = [];
    if ($companies) {
        while ($c = $companies->fetch_assoc()) {
            $compList[] = ['id' => (int)$c['CompanyID'], 'type' => payroll_type_normalize($c['PayrollType'] ?? null)];
        }
    }

    // Each company's payroll periods now come from ITS OWN Payroll Type (A or B)
    // — Type B's period arithmetic still flows through the existing
    // payroll_kinsenas_period() (via payroll_type_period_typeB()), so companies
    // left on the default Type B behave exactly as before this feature existed.
    foreach ($compList as $comp) {
        $compId = $comp['id'];
        $compPeriod = payroll_type_period($comp['type']);
        $compPrevPeriod = payroll_type_previous_period($comp['type'], $compPeriod);
        foreach ([$compPeriod, $compPrevPeriod] as $p) {
            $cutoffPairs[] = [
                $p['start'],
                $p['end'],
                $p['label'],
                $compId
            ];
        }
    }

    $insertStmt = $conn->prepare(
        'INSERT INTO payroll_runs
            (RunCode, CompanyID, PeriodStart, PeriodEnd, CutoffLabel, Status, CurrentStatus)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE 
            RunCode = VALUES(RunCode)'
    );

    foreach ($cutoffPairs as $item) {
        $start = $item[0];
        $end = $item[1];
        $label = $item[2];
        $companyId = $item[3];

        $runCode = client_billing_run_code($start, $end, $companyId);
        $hasEmployees = client_billing_company_has_deployments($conn, $companyId, $start, $end);
        if (!$hasEmployees) {
            continue;
        }

        $status = 'Draft';
        $currentStatus = 'Pending DTR';

        if ($insertStmt) {
            $insertStmt->bind_param('sisssss', $runCode, $companyId, $start, $end, $label, $status, $currentStatus);
            $insertStmt->execute();
        }
        client_billing_refresh_run_metadata($conn, $runCode);
    }
    if ($insertStmt) $insertStmt->close();

    // Existing run lifecycle and archives must not be overwritten by background sync.
    client_billing_sync_site_runs($conn);

}

function client_billing_update_run_status(mysqli $conn, string $runCode, string $newStatus, string $remarks, ?array $fileInfo, int $userId): bool
{
    if ($newStatus === 'Computed') $newStatus = 'Payroll Computed';
    if (!in_array($newStatus,payroll_status_allowed_values(),true)) throw new InvalidArgumentException('Invalid payroll status.');
    client_billing_ensure_schema($conn);
    $run = client_billing_get_run($conn,$runCode);
    if (!$run) return false;
    $proof = payroll_save_proof($fileInfo);
    $conn->begin_transaction();
    try {
        $locked=payroll_query($conn,'SELECT * FROM payroll_runs WHERE PayrollRunID=? FOR UPDATE','i',[(int)$run['PayrollRunID']])->get_result()->fetch_assoc();
        $members=client_billing_fetch_employees_for_run($conn,$locked,true);
        $frozen=payroll_query($conn,'SELECT EmployeeID,DTR_SubmissionID FROM payroll_run_members WHERE PayrollRunID=?','i',[(int)$locked['PayrollRunID']])->get_result()->fetch_all(MYSQLI_ASSOC);
        if($frozen) {
            $roster=[];foreach($frozen as $member)$roster[(int)$member['EmployeeID']]=(int)$member['DTR_SubmissionID'];
            $members=array_values(array_filter($members,static fn($member)=>isset($roster[(int)$member['EmployeeID']]) && $roster[(int)$member['EmployeeID']]===(int)$member['DTR_SubmissionID']));
            if(count($members)!==count($frozen))throw new RuntimeException('The archived payroll roster no longer matches its verified DTRs.',409);
        }
        if (!$members) throw new InvalidArgumentException('No verified DTRs belong to this run.');
        if($newStatus==='Payroll Released')payroll_status_require_finalized($conn,$members,$run['PeriodStart'],$run['PeriodEnd']);
        $unchanged=$locked['CurrentStatus']===$newStatus;
        foreach($members as $member)if((payroll_status_for_dtr($conn,(int)$member['DTR_SubmissionID'])['current']['status'] ?? '')!==$newStatus)$unchanged=false;
        if ($unchanged) {
            $conn->commit();
            if ($proof) @unlink(dirname(__DIR__,2).'/'.$proof);
            return true;
        }
        payroll_query($conn,'UPDATE payroll_runs SET CurrentStatus=? WHERE PayrollRunID=?','si',[$newStatus,(int)$run['PayrollRunID']]);
        payroll_query($conn,'INSERT INTO payroll_status_history (PayrollRunID,Status,Remarks,AttachmentPath,UpdatedBy) VALUES(?,?,?,?,?)','isssi',[(int)$run['PayrollRunID'],$newStatus,$remarks,$proof,$userId]);
        $event=(int)$conn->insert_id;
        payroll_status_notify($conn,array_column($members,'EmployeeID'),$newStatus,$run['PeriodStart'],$run['PeriodEnd'],'payroll_run_status',$event);
        $conn->commit();
        return true;
    } catch(Throwable $e) {
        $conn->rollback();
        if ($proof) @unlink(dirname(__DIR__,2).'/'.$proof);
        throw $e;
    }

}

function client_billing_get_status_history(mysqli $conn, int $payrollRunId): array
{
    client_billing_ensure_schema($conn);
    $stmt = $conn->prepare(
        "SELECT h.HistoryID, h.Status, h.Remarks, h.AttachmentPath, h.UpdatedAt, u.FullName AS updated_by_name
         FROM payroll_status_history h
         LEFT JOIN users u ON u.UsersID = h.UpdatedBy
         WHERE h.PayrollRunID = ?
         ORDER BY h.UpdatedAt ASC, h.HistoryID ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $payrollRunId);
    $stmt->execute();
    $res = $stmt->get_result();
    $history = [];
    while ($row = $res->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    return $history;
}

function client_billing_mark_exported(mysqli $conn, string $runCode, ?float $totalInvoice = null): void
{
    if ($totalInvoice !== null) {
        $stmt = $conn->prepare(
            "UPDATE payroll_runs
             SET Status = 'Exported', TotalInvoice = ?
             WHERE RunCode = ?"
        );
        $stmt->bind_param('ds', $totalInvoice, $runCode);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt = $conn->prepare(
        "UPDATE payroll_runs SET Status = 'Exported' WHERE RunCode = ? AND Status <> 'Exported'"
    );
    $stmt->bind_param('s', $runCode);
    $stmt->execute();
    $stmt->close();
}

function client_billing_company_has_deployments(mysqli $conn, int $companyId, string $start, string $end): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS c
         FROM dtr_submissions ds
         INNER JOIN deployment dep ON dep.DeploymentID = ds.DeploymentID
         INNER JOIN employee e ON e.EmployeeID = ds.EmployeeID AND e.IsActive = 1
         WHERE dep.CompanyID = ?
           AND ds.CutoffStartDate = ?
           AND ds.CutoffEndDate = ?'
    );
    if ($stmt) {
        $stmt->bind_param('iss', $companyId, $start, $end);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ((int)($row['c'] ?? 0) > 0) {
            return true;
        }
    }

    $stmt2 = $conn->prepare(
        'SELECT COUNT(*) AS c
         FROM deployment dep
         INNER JOIN employee e ON e.EmployeeID = dep.EmployeeID AND e.IsActive = 1
         WHERE dep.CompanyID = ?
           AND (dep.EndDate IS NULL OR dep.EndDate >= ?)
           AND (dep.StartDate IS NULL OR dep.StartDate <= ?)'
    );
    if ($stmt2) {
        $stmt2->bind_param('iss', $companyId, $start, $end);
        $stmt2->execute();
        $row2 = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        return ((int)($row2['c'] ?? 0)) > 0;
    }
    return false;
}

/**
 * @return array<int,array<string,mixed>>
 */
function client_billing_list_runs(mysqli $conn): array
{
    $split = client_billing_list_runs_split($conn);
    return array_merge($split['active_runs'], $split['history_runs']);
}

/**
 * @return array{active_runs:array<int,array<string,mixed>>,history_runs:array<int,array<string,mixed>>,clients:array<int,string>}
 */
function client_billing_list_runs_split(mysqli $conn): array
{
    client_billing_sync_runs($conn);

    $sql = "
        SELECT pr.PayrollRunID, pr.RunCode, pr.CompanyID, pr.PeriodStart, pr.PeriodEnd,
               pr.CutoffLabel, pr.Status, pr.CurrentStatus, pr.AdminOverheadPct, pr.VatPct, pr.ApprovedAt,
               pr.BillingRefNo, pr.DeploymentSite, pr.TotalInvoice, pr.SiteID, pr.SiteSupervisorID,
               cc.Name AS client_name,
               COALESCE(pr.PayrollTypeSnapshot,cc.PayrollType) AS payroll_type,
               (SELECT COUNT(DISTINCT ds.EmployeeID)
                FROM dtr_submissions ds
                INNER JOIN deployment dep ON dep.DeploymentID = ds.DeploymentID
                INNER JOIN employee e ON e.EmployeeID = ds.EmployeeID AND e.IsActive = 1
                WHERE dep.CompanyID = pr.CompanyID
                  AND ds.CutoffStartDate = pr.PeriodStart
                  AND ds.CutoffEndDate = pr.PeriodEnd
                  AND (pr.SiteID IS NULL OR ds.SiteID=pr.SiteID)
                  AND (pr.SiteSupervisorID IS NULL OR ds.SiteSupervisorID=pr.SiteSupervisorID)
               ) AS employee_count,
               (SELECT COUNT(DISTINCT ds.SiteID) FROM dtr_submissions ds JOIN deployment d ON d.DeploymentID=ds.DeploymentID AND d.EmployeeID=ds.EmployeeID WHERE d.CompanyID=pr.CompanyID AND ds.CutoffStartDate=pr.PeriodStart AND ds.CutoffEndDate=pr.PeriodEnd AND (pr.SiteID IS NULL OR ds.SiteID=pr.SiteID) AND (pr.SiteSupervisorID IS NULL OR ds.SiteSupervisorID=pr.SiteSupervisorID)) site_count
        FROM payroll_runs pr
        INNER JOIN clientcompany cc ON cc.CompanyID = pr.CompanyID
        ORDER BY pr.PeriodEnd DESC, cc.Name ASC
    ";
    $res = $conn->query($sql);
    $activeRuns = [];
    $historyRuns = [];
    $clients = [];

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ((int)$row['employee_count'] === 0 && empty($row['SiteID']) && empty($row['SiteSupervisorID'])) {
                $fallbackStmt = $conn->prepare(
                    'SELECT COUNT(DISTINCT dep.EmployeeID) AS ec
                     FROM deployment dep
                     INNER JOIN employee e ON e.EmployeeID = dep.EmployeeID AND e.IsActive = 1
                     WHERE dep.CompanyID = ?
                       AND (dep.EndDate IS NULL OR dep.EndDate >= ?)
                       AND (dep.StartDate IS NULL OR dep.StartDate <= ?)'
                );
                $fallbackStmt->bind_param('iss', $row['CompanyID'], $row['PeriodStart'], $row['PeriodEnd']);
                $fallbackStmt->execute();
                $fbRow = $fallbackStmt->get_result()->fetch_assoc();
                $fallbackStmt->close();
                $row['employee_count'] = $fbRow['ec'] ?? 0;
            }

            $snapshotCount=payroll_query($conn,'SELECT COUNT(*) n FROM payroll_run_members WHERE PayrollRunID=?','i',[(int)$row['PayrollRunID']])->get_result()->fetch_assoc();
            if ((int)$snapshotCount['n']) $row['employee_count']=(int)$snapshotCount['n'];
            $eligible=payroll_query($conn,'SELECT COUNT(DISTINCT ds.EmployeeID) n FROM dtr_submissions ds JOIN deployment dep ON dep.DeploymentID=ds.DeploymentID AND dep.EmployeeID=ds.EmployeeID WHERE dep.CompanyID=? AND dep.SiteID=ds.SiteID AND ds.SiteID IS NOT NULL AND ds.ReviewedBy IS NOT NULL AND ds.ReviewDate IS NOT NULL AND ds.CutoffStartDate=? AND ds.CutoffEndDate=? AND (?=0 OR ds.SiteID=?) AND (?=0 OR ds.SiteSupervisorID=?)','issiiii',[(int)$row['CompanyID'],$row['PeriodStart'],$row['PeriodEnd'],(int)$row['SiteID'],(int)$row['SiteID'],(int)$row['SiteSupervisorID'],(int)$row['SiteSupervisorID']])->get_result()->fetch_assoc();
            $row['verified_employee_count']=max((int)$eligible['n'],(int)$snapshotCount['n']);
            $mapped = client_billing_map_run_row($conn, $row);
            $clients[$mapped['client_name']] = $mapped['client_name'];

            if (in_array($mapped['status'], ['Draft', 'Pending'], true)) {
                $activeRuns[] = $mapped;
            } else {
                $historyRuns[] = $mapped;
            }
        }
    }

    sort($clients);

    return [
        'active_runs' => $activeRuns,
        'history_runs' => $historyRuns,
        'clients' => array_values($clients),
    ];
}

/**
 * @return array<string,mixed>|null
 */
function client_billing_get_run(mysqli $conn, string $runCode): ?array
{
    client_billing_ensure_schema($conn);
    $runCode = client_billing_run_code_from_ref($runCode);
    $stmt = $conn->prepare(
        'SELECT pr.*, cc.Name AS client_name,cc.PayrollType current_payroll_type
         FROM payroll_runs pr
         INNER JOIN clientcompany cc ON cc.CompanyID = pr.CompanyID
         WHERE pr.RunCode = ? OR pr.BillingRefNo = ?
         LIMIT 1'
    );
    $billingRef = client_billing_billing_ref($runCode);
    $stmt->bind_param('ss', $runCode, $billingRef);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function client_billing_lookup_sss_er(mysqli $conn, float $msc): array
{
    deduction_rates_ensure_schema($conn);
    $stmt = $conn->prepare(
        'SELECT regular_ss_er, regular_ss_ee, mpf_er, mpf_ee, ec_er, monthly_salary_credit
         FROM sss_rates_config
         WHERE is_active = 1 AND ? >= salary_min AND (salary_max IS NULL OR ? <= salary_max)
         ORDER BY salary_min DESC
         LIMIT 1'
    );
    $stmt->bind_param('dd', $msc, $msc);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return [
            'regular_ss_er' => (float)$row['regular_ss_er'],
            'mpf_er' => (float)$row['mpf_er'],
            'ec_er' => (float)$row['ec_er'],
        ];
    }

    $fallback = deduction_rates_compute_sss_amounts($msc);
    return [
        'regular_ss_er' => $fallback['regular_ss_er'],
        'mpf_er' => $fallback['mpf_er'],
        'ec_er' => $fallback['ec_er'],
    ];
}

function client_billing_resolve_work_schedule(mysqli $conn, int $employeeId): ?string
{
    $stmt = $conn->prepare(
        "SELECT CONCAT(IFNULL(TimeIn, '08:00:00'), ' - ', IFNULL(TimeOut, '17:00:00')) AS ws
         FROM deployment
         WHERE EmployeeID = ? AND (EndDate IS NULL OR EndDate >= CURDATE())
         ORDER BY StartDate DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $ws = trim((string)($row['ws'] ?? ''));
    return $ws !== '' ? $ws : '08:00:00 - 17:00:00';
}

function client_billing_admin_overhead_pct(mysqli $conn, int $companyId, float $default = 10.0): float
{
    $stmt = $conn->prepare(
        "SELECT AgencyFeePercentage
         FROM serviceagreement
         WHERE ClientCompanyID = ? AND Status = 'Active'
         ORDER BY ApprovedDate DESC, CreatedDate DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $pct = isset($row['AgencyFeePercentage']) ? (float)$row['AgencyFeePercentage'] : 0.0;
    return $pct > 0 ? $pct : $default;
}

function client_billing_fetch_employees_for_run(mysqli $conn, array $run, bool $lock = false): array
{
    $companyId = (int)$run['CompanyID'];
    $start = (string)$run['PeriodStart'];
    $end = (string)$run['PeriodEnd'];

    $stmt=$conn->prepare("SELECT e.EmployeeID,e.EmployeeNumber,e.FirstName,e.LastName,e.Position,
        e.daily_rate,e.hourly_rate,e.allowance,e.monthly_salary_credit,e.BasicSalary,
        e.SSSNumber,e.PhilHealthNumber,e.PagIBIGNumber,e.TINNumber,
        COALESCE(NULLIF(TRIM(dep.Position),''),e.Position) AS designation,dep.Location AS area_site,
        dep.SiteID,dep.DeploymentID,ds.DTR_SubmissionID
        FROM dtr_submissions ds JOIN employee e ON e.EmployeeID=ds.EmployeeID
        JOIN deployment dep ON dep.DeploymentID=ds.DeploymentID AND dep.EmployeeID=ds.EmployeeID
        WHERE dep.CompanyID=? AND ds.CutoffStartDate=? AND ds.CutoffEndDate=?
          AND ds.ReviewedBy IS NOT NULL AND ds.ReviewDate IS NOT NULL
          AND dep.SiteID IS NOT NULL AND ds.SiteID=dep.SiteID
          AND (?=0 OR ds.SiteSupervisorID=?) AND (?=0 OR ds.SiteID=?)
        ORDER BY ds.DTR_SubmissionID".($lock ? " FOR UPDATE" : ""));
    $lead=(int)($run['SiteSupervisorID'] ?? 0);$site=(int)($run['SiteID'] ?? 0);
    $stmt->bind_param('issiiii',$companyId,$start,$end,$lead,$lead,$site,$site);$stmt->execute();
    $employees=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    $seen=[];$sites=[];
    foreach($employees as $employee) {
        $id=(int)$employee['EmployeeID'];
        if(isset($seen[$id]))throw new RuntimeException('Duplicate verified DTRs exist for an employee in this cutoff. Resolve them before exporting.',409);
        $seen[$id]=true;$sites[(int)$employee['SiteID']]=true;
    }
    // Legacy payroll runs have no stable SiteID. Never silently combine branches.
    if(count($sites)>1)throw new RuntimeException('This legacy payroll run contains multiple deployment sites. Site-specific payroll runs are required before export.',409);
    return $employees;
}

function client_billing_build_export(mysqli $conn, string $runCode): array
{
    $run = client_billing_get_run($conn, $runCode);
    if (!$run) {
        throw new RuntimeException('Payroll run not found.');
    }

    // Add Back payroll and archived billing retain the reviewed itemization.
    $frozen=payroll_query($conn,'SELECT SnapshotJson FROM payroll_run_members WHERE PayrollRunID=? ORDER BY EmployeeID','i',[(int)$run['PayrollRunID']])->get_result()->fetch_all(MYSQLI_ASSOC);
    if($frozen){$rows=array_map(static fn($m)=>json_decode($m['SnapshotJson'],true,512,JSON_THROW_ON_ERROR),$frozen);$totals=['grand_total'=>0.0,'net_salary'=>0.0,'gross_payroll'=>0.0,'labor_cost'=>0.0];foreach($rows as $row)foreach(['grand_total'=>'grand_total_invoice','net_salary'=>'net_salary','gross_payroll'=>'gross_payroll','labor_cost'=>'subtotal_labor_cost'] as $key=>$field)$totals[$key]=round($totals[$key]+(float)$row[$field],2);return ['run'=>$run,'client_name'=>$run['client_name'],'rows'=>$rows,'totals'=>$totals];}

    $pdo = attendance_pdo($conn);
    attendance_ensure_table($pdo);

    $periodStart = (string)$run['PeriodStart'];
    $periodEnd = (string)$run['PeriodEnd'];
    $companyId = (int)$run['CompanyID'];
    $adminPct = (float)($run['AdminOverheadPct'] ?? 0);
    if ($adminPct <= 0) {
        $adminPct = client_billing_admin_overhead_pct($conn, $companyId, 10.0);
    }
    $vatPct = (float)($run['VatPct'] ?? 12.0);
    $clientName = (string)$run['client_name'];

    $employees = client_billing_fetch_employees_for_run($conn, $run);
    if(!$employees)throw new RuntimeException('No verified DTRs belong to this billing reference. Verify and compute the selected site payroll first.',409);
    $gov = payroll_fetch_gov_rates($conn);
    $rows = [];

    // Which statutory deductions apply to THIS run's cutoff, per the client
    // company's configured Payroll Type (A/B) + which half of the cutoff this
    // run represents — see payroll_type_helper.php. Both the employee (EE) and
    // company (ER) shares are gated the same way so the export stays internally
    // consistent (an EE deduction with no matching ER share, or vice versa,
    // would be wrong on the payroll sheet).
    $companyPayrollType = payroll_type_get_company_type($conn, $companyId);
    $runPeriodForType = payroll_type_period($companyPayrollType, $periodStart);
    $deductionFlags = payroll_type_deductions($runPeriodForType['type'], $runPeriodForType['half']);

    $totals = [
        'grand_total' => 0.0,
        'net_salary' => 0.0,
        'gross_payroll' => 0.0,
        'labor_cost' => 0.0,
    ];

    foreach ($employees as $emp) {
        $empId = (int)$emp['EmployeeID'];
        $saved=payroll_query($conn,"SELECT p.* FROM payroll p JOIN status s ON s.StatusID=p.StatusID WHERE p.EmployeeID=? AND p.DTR_SubmissionID=? AND p.PayPeriodStart=? AND p.PayPeriodEnd=? AND s.Name='Finalized'",'iiss',[$empId,(int)$emp['DTR_SubmissionID'],$periodStart,$periodEnd])->get_result()->fetch_all(MYSQLI_ASSOC);
        if(count($saved)>1)throw new RuntimeException('Duplicate finalized payroll records require Accounting review.',409);
        if($saved){
            $p=$saved[0];$days=(float)$p['TotalDaysWorked'];$recordedRate=$days>0?round((float)$p['BasicSalary']/$days,2):0.0;
            if((float)$p['HolidayPay']!==0.0)throw new RuntimeException('This legacy finalized payroll has holiday pay without an archived billing breakdown. Accounting must recover its original holiday classification before exporting; payroll history has not been changed.',409);
            $msc=$recordedRate*26;$sssEr=client_billing_lookup_sss_er($conn,$msc);
            $erSss=$deductionFlags['sss']?round($sssEr['regular_ss_er']/2,2):0;$erMpf=$deductionFlags['sss']?round($sssEr['mpf_er']/2,2):0;$erEc=$deductionFlags['sss']?round($sssEr['ec_er']/2,2):0;
            $erPhil=$deductionFlags['philhealth']?round($msc*($gov['philhealth_er_pct']/100)/2,2):0;$erPagibig=$deductionFlags['pagibig']?round($gov['pagibig_er_fixed']/2,2):0;$er=$erSss+$erMpf+$erEc+$erPhil+$erPagibig;
            $gross=(float)$p['GrossPay'];$basic=(float)$p['BasicSalary'];$other=(float)$p['TotalDeductions']-(float)$p['SSSDeduction']-(float)$p['PhilHealthDeduction']-(float)$p['PagIBIGDeduction'];
            if($gross<$basic||$other<0)throw new RuntimeException('Legacy payroll component totals require Accounting reconciliation before billing export. Stored payroll is preserved.',409);
            $accrual=round($gross/12,2);$labor=round($gross+$er+$accrual,2);$admin=round($labor*$adminPct/100,2);$vat=round($admin*$vatPct/100,2);
            $row=['employee_db_id'=>$empId,'employee_id'=>$emp['EmployeeNumber'],'employee_name'=>trim($emp['FirstName'].' '.$emp['LastName']),'designation'=>$emp['designation'],'area_site'=>$emp['area_site']?:$clientName,'dtr_submission_id'=>(int)$emp['DTR_SubmissionID'],'site_id'=>(int)$emp['SiteID'],'deployment_id'=>(int)$emp['DeploymentID'],'daily_rate'=>$recordedRate,'days_worked'=>$days,'basic_total'=>$basic,'gross_salary'=>$basic,'additional_pay'=>round($gross-$basic,2),'gross_before_lates'=>$gross,'gross_payroll'=>$gross,'total_ee_deductions'=>(float)$p['TotalDeductions'],'net_salary'=>(float)$p['NetPay'],'sss_ee'=>(float)$p['SSSDeduction'],'philhealth_ee'=>(float)$p['PhilHealthDeduction'],'pagibig_ee'=>(float)$p['PagIBIGDeduction'],'other_ee_deductions'=>round($other,2),'sss_er_regular'=>$erSss,'sss_er_mpf'=>$erMpf,'ec_er'=>$erEc,'philhealth_er'=>$erPhil,'pagibig_er'=>$erPagibig,'total_er_dues'=>$er,'thirteenth_month_accrual'=>$accrual,'subtotal_labor_cost'=>$labor,'admin_overhead_pct'=>$adminPct,'admin_overhead_amount'=>$admin,'vat_pct'=>$vatPct,'vat_on_overhead'=>$vat,'grand_total_invoice'=>round($labor+$admin+$vat,2),'legacy_recorded_payroll'=>true,'payroll_id'=>(int)$p['Payroll_ID'],'payroll_snapshot'=>$p,'legacy_note'=>'Recovered from stored finalized payroll. Additional pay is the recorded gross less basic pay; original overtime/rest-day/late itemization was not archived. Recorded aggregate overtime: PHP '.$p['OvertimePay'].' for '.$p['OvertimeHours'].' hours. Recorded allowance field: PHP '.$p['Allowances'].'. Payroll gross, deductions and net are preserved. Employer/accrual billing uses the existing billing settings and requires Accounting review.'];
            foreach(['basic_total','gross_salary','additional_pay','gross_before_lates','gross_payroll','total_ee_deductions','net_salary'] as $key)$row['billing_overrides'][$key]=true;
            foreach(['regular_ot_hours','regular_ot_amount','sunday_ot_hours','sunday_ot_amount','special_holiday_ot_hours','special_holiday_ot_amount','total_overtime','lates_minutes','lates_undertime','allowances','sil_accrual','holiday_duty','holiday_duty_hrs','holiday_ot_hrs','holiday_ot_amount','special_holiday_hrs','special_holiday_pay','night_differential','other_incentives','sunday_allowance','addback','additional_philhealth_ee','additional_pagibig_ee','other_pagibig_ee'] as $key)$row[$key]=0.0;
            foreach(['sss_number'=>'SSSNumber','philhealth_number'=>'PhilHealthNumber','pagibig_number'=>'PagIBIGNumber','tin'=>'TINNumber'] as $key=>$column)$row[$key]=(string)($emp[$column]??'');
            $row['pay_period']=$run['CutoffLabel']??($periodStart.' - '.$periodEnd);$row['run_id']=$runCode;$row['client_name']=$clientName;$row['status']=$run['Status'];
            foreach(['grand_total'=>'grand_total_invoice','net_salary'=>'net_salary','gross_payroll'=>'gross_payroll','labor_cost'=>'subtotal_labor_cost'] as $total=>$key)$totals[$total]+=$row[$key];
            $rows[]=$row;continue;
        }
        $dailyRate = (float)($emp['daily_rate'] ?? 0);
        $hourlyRate = (float)($emp['hourly_rate'] ?? 0);
        $allowance = (float)($emp['allowance'] ?? 0);
        $msc = (float)($emp['monthly_salary_credit'] ?? 0);

        if ($dailyRate <= 0 && (float)($emp['BasicSalary'] ?? 0) > 0) {
            $dailyRate = round((float)$emp['BasicSalary'] / 26, 2);
            $hourlyRate = round($dailyRate / 8, 2);
            $msc = round((float)$emp['BasicSalary'], 2);
        }
        if ($hourlyRate <= 0 && $dailyRate > 0) {
            $hourlyRate = round($dailyRate / 8, 2);
        }
        if ($msc <= 0 && $dailyRate > 0) {
            $msc = round($dailyRate * 26, 2);
        }

        $workSchedule = client_billing_resolve_work_schedule($conn, $empId);
        $payroll = payroll_compute_period(
            $conn,
            $pdo,
            $empId,
            $dailyRate,
            $hourlyRate,
            $allowance,
            $msc,
            $periodStart,
            $periodEnd,
            $workSchedule,
            $deductionFlags,
            (int)$emp['DeploymentID']
        );

        $sssEr = client_billing_lookup_sss_er($conn, $msc);
        $sssErRegularHalf = $deductionFlags['sss'] ? round($sssEr['regular_ss_er'] / 2, 2) : 0.0;
        $sssErMpfHalf = $deductionFlags['sss'] ? round($sssEr['mpf_er'] / 2, 2) : 0.0;
        $ecErHalf = $deductionFlags['sss'] ? round($sssEr['ec_er'] / 2, 2) : 0.0;
        $philErHalf = $deductionFlags['philhealth'] ? round($msc * ($gov['philhealth_er_pct'] / 100) / 2, 2) : 0.0;
        $pagibigErHalf = $deductionFlags['pagibig'] ? round($gov['pagibig_er_fixed'] / 2, 2) : 0.0;
        $totalErHalf = round($sssErRegularHalf + $sssErMpfHalf + $ecErHalf + $philErHalf + $pagibigErHalf, 2);

        $regOtHours = (float)($payroll['ot_detail']['regular_ot_hours'] ?? 0);
        $sunOtHours = (float)($payroll['ot_detail']['sunday_hours'] ?? 0);
        $regOtPay = (float)($payroll['earnings']['regular_overtime'] ?? 0);
        $regOtHours += (float)($payroll['ot_detail']['approved_overtime_hours'] ?? 0);
        $regOtPay += (float)($payroll['earnings']['approved_overtime'] ?? 0);
        $sunOtPay = (float)($payroll['earnings']['sunday_overtime'] ?? 0);

        // Split the holiday base pay and holiday OT pay by HolidayType, to
        // match Templates/SAMPLE.xlsx's two separate tracks:
        //   - "L-Holiday Duty" (cols R-U)  = Regular Holiday only
        //   - "Special Holiday" (cols V-Z) = every other holiday type
        //     (Special Non-working Day, Special Working Day, Local/Company
        //     Holiday) — the sample template only has these two tracks, so
        //     anything that isn't "Regular Holiday" goes in the Special
        //     bucket, same as the client's own sheet only distinguishes
        //     Legal vs Special.
        $holidayBreakdown = $payroll['holiday_breakdown'] ?? [];
        $holidayOtBreakdown = $payroll['holiday_ot_breakdown'] ?? [];
        $regularHolidayHrs = 0.0;
        $regularHolidayPay = 0.0;
        $specialHolidayHrs = 0.0;
        $specialHolidayPay = 0.0;
        foreach ($holidayBreakdown as $h) {
            // 'worked' distinguishes an actual worked holiday-duty day from
            // the Regular Holiday no-work pay guarantee (100% pay, but the
            // employee rendered 0 hours) -- only the former counts as hours
            // in the export. Defaults to true for backward compatibility
            // with breakdown shapes that predate this flag.
            $workedFlag = array_key_exists('worked', $h) ? (bool)$h['worked'] : true;
            if (($h['type'] ?? '') === 'Regular Holiday') {
                if ($workedFlag) {
                    $regularHolidayHrs += (float)($h['duty_hours'] ?? 8.0);
                }
                $regularHolidayPay += (float)($h['pay'] ?? 0);
            } else {
                if ($workedFlag) {
                    $specialHolidayHrs += (float)($h['duty_hours'] ?? 8.0);
                }
                $specialHolidayPay += (float)($h['pay'] ?? 0);
            }
        }
        $regularHolidayOtHours = 0.0;
        $regularHolidayOtPay = 0.0;
        $specialHolidayOtHours = 0.0;
        $specialHolidayOtPay = 0.0;
        foreach ($holidayOtBreakdown as $h) {
            if (($h['type'] ?? '') === 'Regular Holiday') {
                $regularHolidayOtHours += (float)($h['hours'] ?? 0);
                $regularHolidayOtPay += (float)($h['pay'] ?? 0);
            } else {
                $specialHolidayOtHours += (float)($h['hours'] ?? 0);
                $specialHolidayOtPay += (float)($h['pay'] ?? 0);
            }
        }
        $sundayAllowance=(float)($payroll['earnings']['sunday_allowance']??0);
        $totalOtPay = round($regOtPay + $sunOtPay + $regularHolidayOtPay + $specialHolidayOtPay, 2);

        $basicTotal = (float)($payroll['earnings']['basic_pay'] ?? 0);
        $latesUndertime = (float)($payroll['late_undertime_deduction'] ?? 0);
        $grossSalary = round($basicTotal + $totalOtPay + $sundayAllowance - $latesUndertime, 2);
        $allowanceHalf = (float)($payroll['earnings']['allowance'] ?? 0);
        $silAccrual = (float)($payroll['earnings']['sil_accrual'] ?? 0);
        $grossPayroll = (float)($payroll['gross_pay'] ?? 0);

        $sssEe = (float)($payroll['deductions']['sss_ee'] ?? 0);
        $philEe = (float)($payroll['deductions']['philhealth_ee'] ?? 0);
        $pagibigEe = (float)($payroll['deductions']['pagibig_ee'] ?? 0);
        $otherEeDed = 0.0;
        $totalEeDed = (float)($payroll['total_deductions'] ?? 0);
        $netSalary = (float)($payroll['net_pay'] ?? 0);

        $thirteenthAccrual = round(($grossSalary - $sundayAllowance) / 12, 2);
        $laborCost = round($grossPayroll + $totalErHalf + $thirteenthAccrual, 2);
        $adminOverhead = round($laborCost * ($adminPct / 100), 2);
        $vatOnOverhead = round($adminOverhead * ($vatPct / 100), 2);
        $grandTotal = round($laborCost + $adminOverhead + $vatOnOverhead, 2);

        $fullName = trim($emp['FirstName'] . ' ' . $emp['LastName']);
        $area = trim((string)($emp['area_site'] ?? ''));
        if ($area === '' || strcasecmp($area, 'Multiple sites') === 0) {
            $area = $clientName;
        }

        $rows[] = [
            'employee_id' => (string)$emp['EmployeeNumber'],
            'employee_db_id' => $empId,
            'dtr_submission_id' => (int)$emp['DTR_SubmissionID'],
            'site_id' => (int)$emp['SiteID'],
            'deployment_id' => (int)$emp['DeploymentID'],
            'employee_name' => $fullName,
            'designation' => trim((string)($emp['designation'] ?? '')),
            'area_site' => $area,
            'daily_rate' => $dailyRate,
            'days_worked' => (int)($payroll['days_worked'] ?? 0),
            'basic_total' => $basicTotal,
            'addback' => (float)($payroll['earnings']['add_back'] ?? 0),
            'regular_ot_hours' => $regOtHours,
            'regular_ot_amount' => $regOtPay,
            'sunday_ot_hours' => $sunOtHours,
            'sunday_ot_amount' => round($sunOtPay+$sundayAllowance,2),
            'sunday_allowance' => $sundayAllowance,
            'sunday_duty_dates' => $payroll['earnings']['sunday_duty_dates']??[],
            'sunday_allowance_rate' => $payroll['earnings']['sunday_allowance_rate']??0,
            'holiday_duty_hrs' => $regularHolidayHrs,
            'holiday_duty' => $regularHolidayPay,
            'holiday_ot_hrs' => $regularHolidayOtHours,
            'holiday_ot_amount' => $regularHolidayOtPay,
            'special_holiday_hrs' => $specialHolidayHrs,
            'special_holiday_pay' => $specialHolidayPay,
            'special_holiday_ot_hours' => $specialHolidayOtHours,
            'special_holiday_ot_amount' => $specialHolidayOtPay,
            'total_overtime' => $totalOtPay,
            'lates_minutes' => (float)($payroll['late_undertime']['total_minutes'] ?? 0),
            'lates_undertime' => $latesUndertime,
            'gross_salary' => $grossSalary,
            'allowances' => $allowanceHalf,
            'sil_accrual' => $silAccrual,
            'night_differential' => 0.0,
            'other_incentives' => 0.0,
            'gross_payroll' => $grossPayroll,
            'sss_ee' => $sssEe,
            'philhealth_ee' => $philEe,
            'pagibig_ee' => $pagibigEe,
            'other_ee_deductions' => $otherEeDed,
            'total_ee_deductions' => $totalEeDed,
            'net_salary' => $netSalary,
            'sss_er_regular' => $sssErRegularHalf,
            'sss_er_mpf' => $sssErMpfHalf,
            'ec_er' => $ecErHalf,
            'philhealth_er' => $philErHalf,
            'pagibig_er' => $pagibigErHalf,
            'total_er_dues' => $totalErHalf,
            'thirteenth_month_accrual' => $thirteenthAccrual,
            'subtotal_labor_cost' => $laborCost,
            'admin_overhead_pct' => $adminPct,
            'admin_overhead_amount' => $adminOverhead,
            'vat_pct' => $vatPct,
            'vat_on_overhead' => $vatOnOverhead,
            'grand_total_invoice' => $grandTotal,
            'sss_number' => (string)($emp['SSSNumber'] ?? ''),
            'philhealth_number' => (string)($emp['PhilHealthNumber'] ?? ''),
            'pagibig_number' => (string)($emp['PagIBIGNumber'] ?? ''),
            'tin' => (string)($emp['TINNumber'] ?? ''),
            'pay_period' => (string)($run['CutoffLabel'] ?? ($periodStart . ' – ' . $periodEnd)),
            'run_id' => $runCode,
            'client_name' => $clientName,
            'status' => (string)$run['Status'],
        ];

        $totals['grand_total'] += $grandTotal;
        $totals['net_salary'] += $netSalary;
        $totals['gross_payroll'] += $grossPayroll;
        $totals['labor_cost'] += $laborCost;
    }

    foreach ($totals as $k => $v) {
        $totals[$k] = round($v, 2);
    }

    return [
        'rows' => $rows,
        'totals' => $totals,
        'client_name' => $clientName,
        'run' => $run,
    ];
}

function client_billing_column_headers(): array
{
    return [
        'Employee ID', 'Employee Name', 'Designation', 'Area / Site', 'Daily Rate', 'Days Worked', 'Basic Total',
        'Regular OT Hours', 'Regular OT Amount', 'Sunday OT Hours', 'Sunday OT Amount', 'Special Holiday OT Hours',
        'Special Holiday OT Amount', 'Total Overtime', 'Lates / Undertime', 'Gross Salary', 'Allowances',
        'SIL Accrual (Daily × 5 ÷ 24)', 'Holiday Duty Pay', 'Night Differential', 'Other Incentives',
        'Gross Payroll', 'SSS EE Share', 'PhilHealth EE Share', 'Pag-IBIG EE Share', 'Other EE Deductions',
        'Total EE Deductions', 'Net Salary', 'SSS ER Share (Regular)', 'SSS ER Share (MPF)', 'EC ER Fee',
        'PhilHealth ER Share', 'Pag-IBIG ER Share', 'Total ER Government Dues', '13th Month Accrual Reserve',
        'Subtotal Labor & Benefits Cost', 'Admin Overhead Rate (%)', 'Agency Admin Overhead Fee',
        'VAT Rate (%)', 'VAT on Admin Overhead', 'Grand Total Invoice (Per Employee)',
        'SSS Number', 'PhilHealth Number', 'Pag-IBIG Number', 'TIN', 'Pay Period', 'Payroll Run ID',
        'Client Company', 'Status / Remarks',
    ];
}

function client_billing_row_to_cells(array $row): array
{
    return [
        $row['employee_id'], $row['employee_name'], $row['designation'], $row['area_site'], $row['daily_rate'],
        $row['days_worked'], $row['basic_total'], $row['regular_ot_hours'], $row['regular_ot_amount'],
        $row['sunday_ot_hours'], $row['sunday_ot_amount'], $row['special_holiday_ot_hours'],
        $row['special_holiday_ot_amount'], $row['total_overtime'], $row['lates_undertime'], $row['gross_salary'],
        $row['allowances'], $row['sil_accrual'], $row['holiday_duty'], $row['night_differential'],
        $row['other_incentives'], $row['gross_payroll'], $row['sss_ee'], $row['philhealth_ee'],
        $row['pagibig_ee'], $row['other_ee_deductions'], $row['total_ee_deductions'], $row['net_salary'],
        $row['sss_er_regular'], $row['sss_er_mpf'], $row['ec_er'], $row['philhealth_er'],
        $row['pagibig_er'], $row['total_er_dues'], $row['thirteenth_month_accrual'], $row['subtotal_labor_cost'],
        $row['admin_overhead_pct'], $row['admin_overhead_amount'], $row['vat_pct'], $row['vat_on_overhead'],
        $row['grand_total_invoice'], $row['sss_number'], $row['philhealth_number'], $row['pagibig_number'],
        $row['tin'], $row['pay_period'], $row['run_id'], $row['client_name'], $row['status'],
    ];
}

/** Split ambiguous multi-site company cutoffs; keep legacy rows intact. */
function client_billing_sync_site_runs(mysqli $db): void {
    $rows=$db->query("SELECT d.CompanyID,ds.SiteID,ds.SiteSupervisorID,ds.CutoffStartDate,ds.CutoffEndDate,MAX(ds.CutoffPeriod) label,MAX(d.Location) site FROM dtr_submissions ds JOIN deployment d ON d.DeploymentID=ds.DeploymentID AND d.EmployeeID=ds.EmployeeID WHERE ds.SiteID IS NOT NULL AND ds.ReviewedBy IS NOT NULL AND ds.ReviewDate IS NOT NULL GROUP BY d.CompanyID,ds.SiteID,ds.SiteSupervisorID,ds.CutoffStartDate,ds.CutoffEndDate");
    while($r=$rows->fetch_assoc()) {
        $code=client_billing_run_code($r['CutoffStartDate'],$r['CutoffEndDate'],(int)$r['CompanyID'],(int)$r['SiteSupervisorID'],(int)$r['SiteID']);
        payroll_query($db,"INSERT IGNORE INTO payroll_runs (RunCode,BillingRefNo,CompanyID,SiteSupervisorID,SiteID,PeriodStart,PeriodEnd,CutoffLabel,DeploymentSite,Status,CurrentStatus) VALUES(?,?,?,?,?,?,?,?,?,'Draft','Payroll Computed')",'ssiiissss',[$code,client_billing_billing_ref($code),(int)$r['CompanyID'],(int)$r['SiteSupervisorID'],(int)$r['SiteID'],$r['CutoffStartDate'],$r['CutoffEndDate'],$r['label'],$r['site']]);
    }
}
