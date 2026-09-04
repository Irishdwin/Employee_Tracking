<?php
/**
 * Centralized Payroll Type (A / B) configuration service.
 *
 * The Client Company (`clientcompany.PayrollType`) is the single source of
 * truth. Everything downstream — Team Lead DTR submission cutoffs, Employee
 * Dashboard payroll info, Accounting's DTR Verification / Kinsenas Payroll
 * Run filters, and the payroll computation/export — resolves its cutoff
 * period, release date, and which statutory deductions apply through the
 * functions in this file, instead of each screen re-deriving the rules.
 *
 * TYPE A (client's spec):
 *   1st payroll: 1st–15th,               release 20th same month  -> PhilHealth + Pag-IBIG only
 *   2nd payroll: 16th–end of month,       release 5th next month   -> SSS only
 *
 * TYPE B (client's spec):
 *   1st payroll: 11th–25th,               release 30th same month  -> SSS only
 *   2nd payroll: 26th–10th next month,    release 15th same month  -> PhilHealth + Pag-IBIG only
 *
 * TYPE B's cutoff arithmetic already exists and is battle-tested as
 * payroll_kinsenas_period() (Api/Helpers/employee_payroll_helper.php) — this
 * file wraps it rather than re-implementing it, and adds the equivalent for
 * TYPE A plus the deduction-gating rules for both.
 */
declare(strict_types=1);

require_once __DIR__ . '/employee_payroll_helper.php'; // payroll_kinsenas_period()

/** Ensures clientcompany.PayrollType exists. Safe to call repeatedly. */
function payroll_type_ensure_schema(mysqli $conn): void
{
    $res = $conn->query("SHOW COLUMNS FROM `clientcompany` LIKE 'PayrollType'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE `clientcompany` ADD COLUMN `PayrollType` ENUM('A','B') NOT NULL DEFAULT 'B'");
    }
}

/** @return 'A'|'B' */
function payroll_type_normalize(?string $type): string
{
    $t = strtoupper(trim((string)$type));
    return $t === 'A' ? 'A' : 'B'; // default/fallback matches the system's existing universal cutoff (Type B's arithmetic)
}

function payroll_type_label(string $type): string
{
    return 'Type ' . payroll_type_normalize($type);
}

/**
 * Looks up a client company's configured Payroll Type.
 * Falls back to 'B' (the system's pre-existing universal cutoff) if the
 * company can't be found or the column isn't set — never breaks existing data.
 */
function payroll_type_get_company_type(mysqli $conn, int $companyId): string
{
    $stmt = $conn->prepare('SELECT PayrollType FROM clientcompany WHERE CompanyID = ? LIMIT 1');
    if (!$stmt) {
        return 'B';
    }
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return payroll_type_normalize($row['PayrollType'] ?? null);
}

/**
 * TYPE A cutoff resolver — mirrors payroll_kinsenas_period()'s shape/contract
 * but for the 1–15 / 16–end-of-month cutoffs.
 *
 * @return array{start:string,end:string,payday:string,payday_display:string,label:string,half:string,type:string}
 */
function payroll_type_period_typeA(?string $refDate = null): array
{
    $ref = $refDate ? new DateTime($refDate) : new DateTime('today');
    $day = (int)$ref->format('j');
    $year = (int)$ref->format('Y');
    $month = (int)$ref->format('n');
    $lastDay = (int)$ref->format('t');

    if ($day <= 15) {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = sprintf('%04d-%02d-15', $year, $month);
        $payday = sprintf('%04d-%02d-20', $year, $month);
        $half = '1st';
    } else {
        $start = sprintf('%04d-%02d-16', $year, $month);
        $end = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
        $nextMonthDt = (clone $ref)->modify('first day of next month');
        $payday = $nextMonthDt->format('Y-m-') . '05';
        $half = '2nd';
    }

    return [
        'start' => $start,
        'end' => $end,
        'payday' => $payday,
        'payday_display' => date('M j, Y', strtotime($payday)),
        'label' => date('M j, Y', strtotime($start)) . ' – ' . date('M j, Y', strtotime($end)),
        'half' => $half, // '1st' = 1-15, '2nd' = 16-end (matches the client's own Payroll #1 / #2 numbering)
        'type' => 'A',
    ];
}

/**
 * TYPE B cutoff resolver — thin wrapper around the existing, already-correct
 * payroll_kinsenas_period(), just relabeled so 'half' matches the client's
 * own Payroll #1 (11-25) / #2 (26-10) numbering instead of that function's
 * internal '1st'/'2nd' labels (which are keyed to calendar-month position,
 * not the client's payroll-number scheme).
 *
 * @return array{start:string,end:string,payday:string,payday_display:string,label:string,half:string,type:string}
 */
function payroll_type_period_typeB(?string $refDate = null): array
{
    $p = payroll_kinsenas_period($refDate);
    $day = (int)(new DateTime($p['start']))->format('j');
    // 11-25 start day is always 11 -> Payroll #1 (SSS only, release 30th).
    // 26-... start day is always 26 -> Payroll #2 (PhilHealth+Pag-IBIG, release 15th).
    $half = ($day === 11) ? '1st' : '2nd';

    return [
        'start' => $p['start'],
        'end' => $p['end'],
        'payday' => $p['payday'],
        'payday_display' => $p['payday_display'],
        'label' => $p['label'],
        'half' => $half,
        'type' => 'B',
    ];
}

/**
 * @return array{start:string,end:string,payday:string,payday_display:string,label:string,half:string,type:string}
 */
function payroll_type_period(string $type, ?string $refDate = null): array
{
    return payroll_type_normalize($type) === 'A'
        ? payroll_type_period_typeA($refDate)
        : payroll_type_period_typeB($refDate);
}

/**
 * The period immediately BEFORE the given one — same shape, same type.
 * Used by run-sync (payroll_runs) so the previous cutoff also gets its
 * payroll_runs row synced, matching the existing Type-B-only behavior in
 * client_billing_sync_runs() but now generalized to either type.
 */
function payroll_type_previous_period(string $type, array $period): array
{
    $prevRef = (new DateTime($period['start']))->modify('-1 day')->format('Y-m-d');
    return payroll_type_period($type, $prevRef);
}

/**
 * Resolves a company's current payroll period from ITS OWN configured
 * Payroll Type — the single call site downstream code should use instead of
 * calling payroll_kinsenas_period() directly.
 *
 * @return array{start:string,end:string,payday:string,payday_display:string,label:string,half:string,type:string,company_id:int}
 */
function payroll_type_period_for_company(mysqli $conn, int $companyId, ?string $refDate = null): array
{
    $type = payroll_type_get_company_type($conn, $companyId);
    $period = payroll_type_period($type, $refDate);
    $period['company_id'] = $companyId;
    return $period;
}

/**
 * Which statutory deductions apply for a given Payroll Type + half, per the
 * client's exact spec:
 *   Type A #1 (1-15):  PhilHealth + Pag-IBIG, no SSS
 *   Type A #2 (16-end): SSS only
 *   Type B #1 (11-25): SSS only
 *   Type B #2 (26-10): PhilHealth + Pag-IBIG, no SSS
 *
 * @return array{sss:bool,philhealth:bool,pagibig:bool}
 */
function payroll_type_deductions(string $type, string $half): array
{
    $type = payroll_type_normalize($type);
    $half = trim($half) === '1st' ? '1st' : '2nd';

    if ($type === 'A') {
        return $half === '1st'
            ? ['sss' => false, 'philhealth' => true, 'pagibig' => true]
            : ['sss' => true, 'philhealth' => false, 'pagibig' => false];
    }

    // Type B
    return $half === '1st'
        ? ['sss' => true, 'philhealth' => false, 'pagibig' => false]
        : ['sss' => false, 'philhealth' => true, 'pagibig' => true];
}

/**
 * Convenience: deduction flags directly from a company id + reference date,
 * so callers don't need to juggle type/half themselves.
 *
 * @return array{sss:bool,philhealth:bool,pagibig:bool}
 */
function payroll_type_deductions_for_company(mysqli $conn, int $companyId, ?string $refDate = null): array
{
    $period = payroll_type_period_for_company($conn, $companyId, $refDate);
    return payroll_type_deductions($period['type'], $period['half']);
}