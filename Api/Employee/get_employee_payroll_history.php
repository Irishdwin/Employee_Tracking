<?php
/**
 * GET — Employee's own finalized Payroll History.
 *
 * No params: returns the list (one row per finalized `payroll` record for
 * this employee, most recent first).
 * ?payroll_id=N: returns ONE payslip in the exact shape
 * Js/Employee/employee_payroll.js's fillPayslipModal() already expects,
 * sourced from the persisted `payroll` row (not recomputed live) — so the
 * existing payslip modal/JS can render it with zero changes to that function.
 *
 * Only StatusID = 'Finalized' rows are ever returned (per the project's own
 * status table — reused, not a new status system). Ownership is resolved
 * SERVER-SIDE from the session (same users.Email -> employee.Email join
 * already used by get_employee_payroll.php) — a payroll_id belonging to
 * another employee is never returned, regardless of what's requested.
 *
 * NOTE ON HISTORICAL DETAIL: the persisted `payroll` table does not have
 * columns for SIL accrual or late/undertime minutes-and-amount breakdown
 * (those are itemization detail computed live by payroll_compute_period()
 * but never designed to be stored). Historical GrossPay/NetPay are still
 * fully accurate (the late/undertime deduction was already subtracted into
 * GrossPay at finalize time) — only that one itemized sub-line can't be
 * redisplayed after the fact. Those fields are sent as null and the payslip
 * modal shows "—" for them instead of a possibly-misleading "0".
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php'; // payroll_kinsenas_period()
require_once __DIR__ . '/../Helpers/payroll_status_helper.php';

payroll_status_ensure_schema($conn);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Employee') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

try {
    // Same server-side identity resolution as get_employee_payroll.php —
    // never trusts a client-supplied EmployeeID.
    $empStmt = $conn->prepare(
        "SELECT e.EmployeeID, e.EmployeeNumber, e.FirstName, e.LastName, e.Position
         FROM users u
         INNER JOIN employee e ON e.Email = u.Email AND e.IsActive = 1
         WHERE u.UsersID = ?
         LIMIT 1"
    );
    $empStmt->bind_param('i', $userId);
    $empStmt->execute();
    $employee = $empStmt->get_result()->fetch_assoc();
    $empStmt->close();

    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'No employee profile linked to this account.']);
        exit();
    }
    $employeeId = (int)$employee['EmployeeID'];

    $statusStmt = $conn->prepare("SELECT StatusID FROM status WHERE Name = 'Finalized' LIMIT 1");
    $statusStmt->execute();
    $statusRow = $statusStmt->get_result()->fetch_assoc();
    $statusStmt->close();
    $finalizedStatusId = $statusRow ? (int)$statusRow['StatusID'] : null;

    if ($finalizedStatusId === null) {
        echo json_encode(['success' => true, 'history' => []]);
        exit();
    }

    $payrollId = isset($_GET['payroll_id']) ? (int)$_GET['payroll_id'] : 0;

    if ($payrollId > 0) {
        // ---- Single payslip detail ----
        $stmt = $conn->prepare("
            SELECT * FROM payroll
            WHERE Payroll_ID = ? AND EmployeeID = ? AND StatusID = ?
            LIMIT 1
        ");
        $stmt->bind_param('iii', $payrollId, $employeeId, $finalizedStatusId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Payslip not found.']);
            exit();
        }

        // Deployment context for the payslip header (position/site/company).
        $depStmt = $conn->prepare(
            "SELECT dep.Location, cc.Name AS company_name, tl.FullName AS team_lead_name
             FROM deployment dep
             LEFT JOIN clientcompany cc ON cc.CompanyID = dep.CompanyID
             LEFT JOIN users tl ON tl.UsersID = dep.SiteSuperVisorID
             WHERE dep.EmployeeID = ?
             ORDER BY dep.StartDate DESC
             LIMIT 1"
        );
        $depStmt->bind_param('i', $employeeId);
        $depStmt->execute();
        $depRow = $depStmt->get_result()->fetch_assoc();
        $depStmt->close();

        $period = payroll_kinsenas_period($row['PayPeriodStart']);
        $periodLabel = date('M j', strtotime($row['PayPeriodStart'])) . ' – ' . date('M j, Y', strtotime($row['PayPeriodEnd']));

        $daysWorked = $row['TotalDaysWorked'] !== null ? (float)$row['TotalDaysWorked'] : null;
        $dailyRate = ($daysWorked !== null && $daysWorked > 0 && $row['BasicSalary'] !== null)
            ? round((float)$row['BasicSalary'] / $daysWorked, 2)
            : null;

        // Team-level payroll status (from payroll_status_history) for the
        // group this payroll row belongs to — resolved via DTR_SubmissionID
        // -> dtr_submissions -> deployment, the same chain used everywhere
        // else in this codebase to trace a payroll record back to its
        // Team Lead + Company + Cutoff group.
        $payrollStatus = null;
        $payrollStatusHistory = [];
        if (!empty($row['DTR_SubmissionID'])) {
            $dtrSubmissionIdForGroup = (int)$row['DTR_SubmissionID'];
            $groupStmt = $conn->prepare(
                "SELECT ds.SiteSupervisorID, d.CompanyID, ds.CutoffStartDate, ds.CutoffEndDate
                 FROM dtr_submissions ds
                 LEFT JOIN deployment d ON d.DeploymentID = ds.DeploymentID
                 WHERE ds.DTR_SubmissionID = ?
                 LIMIT 1"
            );
            $groupStmt->bind_param('i', $dtrSubmissionIdForGroup);
            $groupStmt->execute();
            $groupRow = $groupStmt->get_result()->fetch_assoc();
            $groupStmt->close();

            if ($groupRow && $groupRow['SiteSupervisorID'] && $groupRow['CompanyID']) {
                $current = payroll_status_get_current(
                    $conn,
                    (int)$groupRow['SiteSupervisorID'],
                    (int)$groupRow['CompanyID'],
                    $groupRow['CutoffStartDate'],
                    $groupRow['CutoffEndDate']
                );
                if ($current) {
                    $payrollStatus = [
                        'status' => $current['Status'],
                        'remarks' => $current['Remarks'],
                        'has_proof' => $current['ProofImagePath'] !== null,
                        'proof_history_id' => $current['ProofImagePath'] !== null ? (int)$current['PayrollStatusHistoryID'] : null,
                        'updated_at' => $current['CreatedAt'],
                    ];
                }
                $payrollStatusHistory = payroll_status_get_history(
                    $conn,
                    (int)$groupRow['SiteSupervisorID'],
                    (int)$groupRow['CompanyID'],
                    $groupRow['CutoffStartDate'],
                    $groupRow['CutoffEndDate']
                );
            }
        }

        $payslip = [
            'employee_name' => trim($employee['FirstName'] . ' ' . $employee['LastName']),
            'employee_number' => $employee['EmployeeNumber'],
            'period_label' => $periodLabel,
            'designation' => $employee['Position'],
            'assigned_site' => $depRow ? trim((string)($depRow['Location'] ?? '')) : '',
            'company' => $depRow && !empty($depRow['company_name']) ? $depRow['company_name'] : 'Onazul Manpower Services',
            'team_lead' => $depRow['team_lead_name'] ?? null,
            'is_finalized' => true,
            'days_worked' => $daysWorked,
            'daily_rate' => $dailyRate,
            'earnings' => [
                'basic_pay' => (float)($row['BasicSalary'] ?? 0),
                'sunday_overtime' => 0.0, // not itemized separately in the persisted record; see note below
                'regular_overtime' => (float)($row['OvertimePay'] ?? 0),
                'holiday_pay' => (float)($row['HolidayPay'] ?? 0),
                'sil_accrual' => 0.0, // not stored historically — see file header note
                'allowance' => (float)($row['Allowances'] ?? 0),
            ],
            'late_undertime' => [
                'late_minutes' => null, // not stored historically — see file header note
                'undertime_minutes' => null,
                'amount' => null,
            ],
            'deductions' => [
                'sss_ee' => (float)($row['SSSDeduction'] ?? 0),
                'philhealth_ee' => (float)($row['PhilHealthDeduction'] ?? 0),
                'pagibig_ee' => (float)($row['PagIBIGDeduction'] ?? 0),
            ],
            'gross_pay' => (float)($row['GrossPay'] ?? 0),
            'total_deductions' => (float)($row['TotalDeductions'] ?? 0),
            'net_pay' => (float)($row['NetPay'] ?? 0),
            'payment_date' => $row['PaymentDate'],
            'status' => 'Finalized',
            'payroll_status' => $payrollStatus,
            'payroll_status_history' => $payrollStatusHistory,
        ];

        echo json_encode(['success' => true, 'payslip' => $payslip]);
        exit();
    }

    // ---- List mode ----
    $stmt = $conn->prepare("
        SELECT Payroll_ID, PayPeriodStart, PayPeriodEnd, GrossPay, TotalDeductions, NetPay, PaymentDate, DTR_SubmissionID
        FROM payroll
        WHERE EmployeeID = ? AND StatusID = ?
        ORDER BY PayPeriodStart DESC
    ");
    $stmt->bind_param('ii', $employeeId, $finalizedStatusId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $groupStmt = $conn->prepare(
        "SELECT ds.SiteSupervisorID, d.CompanyID, ds.CutoffStartDate, ds.CutoffEndDate
         FROM dtr_submissions ds
         LEFT JOIN deployment d ON d.DeploymentID = ds.DeploymentID
         WHERE ds.DTR_SubmissionID = ?
         LIMIT 1"
    );

    $history = array_map(function ($row) use ($conn, $groupStmt) {
        $period = payroll_kinsenas_period($row['PayPeriodStart']);

        $payrollStatusLabel = null;
        if (!empty($row['DTR_SubmissionID'])) {
            $dtrSubmissionId = (int)$row['DTR_SubmissionID'];
            $groupStmt->bind_param('i', $dtrSubmissionId);
            $groupStmt->execute();
            $groupRow = $groupStmt->get_result()->fetch_assoc();
            if ($groupRow && $groupRow['SiteSupervisorID'] && $groupRow['CompanyID']) {
                $current = payroll_status_get_current(
                    $conn,
                    (int)$groupRow['SiteSupervisorID'],
                    (int)$groupRow['CompanyID'],
                    $groupRow['CutoffStartDate'],
                    $groupRow['CutoffEndDate']
                );
                if ($current) {
                    $payrollStatusLabel = $current['Status'];
                }
            }
        }

        return [
            'payroll_id' => (int)$row['Payroll_ID'],
            'period_start' => $row['PayPeriodStart'],
            'period_end' => $row['PayPeriodEnd'],
            'period_label' => date('M j', strtotime($row['PayPeriodStart'])) . ' – ' . date('M j, Y', strtotime($row['PayPeriodEnd'])),
            'cycle_label' => $period['half'] === '1st' ? '1st Payroll' : '2nd Payroll',
            'gross_pay' => (float)($row['GrossPay'] ?? 0),
            'total_deductions' => (float)($row['TotalDeductions'] ?? 0),
            'net_pay' => (float)($row['NetPay'] ?? 0),
            'release_date' => $row['PaymentDate'],
            'status' => 'Finalized',
            'payroll_status' => $payrollStatusLabel,
        ];
    }, $rows);
    $groupStmt->close();

    echo json_encode(['success' => true, 'history' => $history]);
} catch (Throwable $e) {
    error_log('get_employee_payroll_history: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load payroll history.',
        'error_details' => $e->getMessage(),
    ]);
}
