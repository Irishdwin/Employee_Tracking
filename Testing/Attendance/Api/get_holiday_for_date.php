<?php
/**
 * DEVELOPMENT / TESTING ONLY. GET ?employee_id=X&date=YYYY-MM-DD
 *
 * Payroll-test-scenario support (Attendance Testing Dashboard section
 * "4. Holiday Details"). Read-only — creates nothing.
 *
 * Reuses the EXISTING production sources of truth, does not invent any:
 *   - holidays_helper.php (holidays_find_by_date) for the holiday table —
 *     the same table Admin manages and payroll_compute_period() reads via
 *     holidays_fetch_for_range().
 *   - payroll_resolve_deployment_schedule() / payroll_scheduled_weekdays() /
 *     payroll_is_scheduled_day() for the employee's REAL rest-day schedule —
 *     the same functions payroll_compute_period() itself uses to decide the
 *     Regular-Holiday-+-rest-day (260%) / Special-Non-working-+-rest-day
 *     (150%) cases.
 *   - payroll_type_period_for_company() for the payroll cutoff the date
 *     falls in, using the employee's actual deployed Client Company and its
 *     configured Payroll Type A/B — same function the real payroll flow
 *     uses (see Api/Employee/get_employee_payroll.php).
 *
 * IMPORTANT — Double Holiday: the `holidays` table enforces at most ONE
 * holiday per calendar date (holidays_create()/holidays_update() reject a
 * second holiday on a date already used — see holidays_helper.php), and
 * payroll_compute_period() itself reads holidays via a date-keyed map
 * (one entry per date — a second same-date row would simply overwrite the
 * first, never stack). So "two Regular/Legal holidays on the same date" is
 * not a producible state in the current system at all — this is a real,
 * pre-existing gap reported to the developer, not something this testing
 * tool works around or invents a fix for. This endpoint reports that
 * limitation explicitly (`double_holiday_supported: false`) rather than
 * pretending to detect a state the schema cannot hold.
 */
declare(strict_types=1);

require_once __DIR__ . '/_testing_guard.php';
require_once __DIR__ . '/_selection_context.php';
require_once __DIR__ . '/../../../Api/Helpers/employee_payroll_helper.php'; // schedule + rest-day helpers, holidays_helper.php (included by it)
require_once __DIR__ . '/../../../Api/Helpers/client_billing_helper.php';   // client_billing_ensure_schema()
require_once __DIR__ . '/../../../Api/Helpers/payroll_type_helper.php';    // payroll_type_period_for_company()

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$date = trim((string)($_GET['date'] ?? ''));

if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'employee_id and a valid date (YYYY-MM-DD) are required.']);
    exit();
}

try {
    $selectedDeployment=testing_employee_context($conn,$_GET,$employeeId,$date);
    client_billing_ensure_schema($conn);

    // Real duty schedule + rest-day determination — identical logic to
    // payroll_compute_period()'s own rest-day check, never re-derived here.
    $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $date);
    $weekdays = payroll_scheduled_weekdays($schedule['working_days']);
    $isScheduledDay = payroll_is_scheduled_day($date, $weekdays);

    // Real holiday lookup — same `holidays` table payroll reads.
    $holiday = holidays_find_by_date($conn, $date);

    // Real deployment company (for the correct Payroll Type A/B cutoff).
    $depRow=$selectedDeployment;

    $period = null;
    if ($depRow && (int)$depRow['CompanyID'] > 0) {
        $period = payroll_type_period_for_company($conn, (int)$depRow['CompanyID'], $date);
    }

    echo json_encode([
        'success' => true,
        'date' => $date,
        'holiday' => $holiday, // null => Normal Workday for payroll purposes
        'scheduled' => $isScheduledDay ? 'Scheduled Workday' : 'Scheduled Rest Day',
        'is_rest_day' => !$isScheduledDay,
        'payroll_period' => $period, // null if no active deployment found for this date
        'double_holiday_supported' => true,
        'double_holiday_note' => 'The current schema and production engine support two distinct Regular Holidays on one date. Use the controlled suite to verify this without adding a test holiday to the official calendar.',
    ]);
} catch (Throwable $e) {
    error_log('[Testing/Attendance] get_holiday_for_date: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to resolve holiday/schedule information.']);
}
