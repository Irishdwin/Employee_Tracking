<?php
require_once __DIR__ . '/../Common/session_init.php';
date_default_timezone_set('Asia/Manila');
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/employee_deployment_context.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';
require_once __DIR__ . '/../Helpers/payroll_type_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Employee') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

$profileStmt = $conn->prepare(
    "SELECT
        u.UsersID AS user_id,
        u.FullName AS full_name,
        u.Email AS email,
        e.EmployeeID AS employee_id,
        e.EmployeeNumber AS employee_number,
        e.Position AS employee_position,
        dep.Position AS deployment_position,
        dep.Location AS deployment_location,
        dep.StartDate AS deployment_start_date,
        dep.TimeIn AS duty_time_in,
        dep.TimeOut AS duty_time_out,
        dep.BreakDuration AS duty_break_duration,
        dep.WorkingDays AS duty_working_days,
        dep.GracePeriod AS duty_grace_period,
        dep_company.Name AS deployment_company,
        dep.CompanyID AS deployment_company_id,
        dep_company.PayrollType AS deployment_payroll_type,
        COALESCE(dep_company.registered_latitude, dep_company.latitude) AS site_lat,
        COALESCE(dep_company.registered_longitude, dep_company.longitude) AS site_lng,
        COALESCE(dep_company.geofence_radius, 100) AS geofence_radius,
        dep_company.site_registered_at AS site_registered_at,
        dep_company.registered_by_tl_id AS registered_by_tl_id,
        tl.FullName AS team_lead_name,
        a.FirstName AS applicant_first_name,
        a.LastName AS applicant_last_name,
        a.BirthDate AS applicant_birth_date,
        a.HomeLocation AS applicant_home_location,
        a.Address AS applicant_address,
        a.JobTitle AS applicant_job_title,
        a.CompanyName AS applicant_company_name,
        a.JobCategory AS applicant_job_category,
        a.PreferredClassification AS applicant_preferred_classification,
        a.PreferredSubClassification AS applicant_preferred_sub_classification
     FROM users u
     LEFT JOIN employee e ON e.Email = u.Email AND e.IsActive = 1
     LEFT JOIN (
         SELECT d1.*
         FROM deployment d1
         INNER JOIN (
             SELECT EmployeeID, MAX(StartDate) AS MaxStart
             FROM deployment
             WHERE EndDate IS NULL OR EndDate >= CURDATE()
             GROUP BY EmployeeID
         ) latest ON latest.EmployeeID = d1.EmployeeID AND latest.MaxStart = d1.StartDate
         WHERE d1.EndDate IS NULL OR d1.EndDate >= CURDATE()
     ) dep ON dep.EmployeeID = e.EmployeeID
     LEFT JOIN clientcompany dep_company ON dep_company.CompanyID = dep.CompanyID
     LEFT JOIN users tl ON tl.UsersID = dep.SiteSuperVisorID
     LEFT JOIN applicants a ON a.UserID = u.UsersID
     WHERE u.UsersID = ?
     LIMIT 1"
);
$profileStmt->bind_param('i', $userId);
$profileStmt->execute();
$profileRow = $profileStmt->get_result()->fetch_assoc();
$profileStmt->close();

if (!$profileRow) {
    echo json_encode(['success' => false, 'message' => 'Profile not found']);
    exit();
}

payroll_type_ensure_schema($conn);
$deploymentPayrollCompanyId = isset($profileRow['deployment_company_id']) ? (int)$profileRow['deployment_company_id'] : 0;
$deploymentPayrollType = payroll_type_normalize($profileRow['deployment_payroll_type'] ?? null);

$hiredContext = employee_fetch_hired_placement_context($conn, $userId);

$deploymentPosition = trim((string)($profileRow['deployment_position'] ?? $profileRow['employee_position'] ?? ''));
$deploymentCompany = trim((string)($profileRow['deployment_company'] ?? ''));
$deploymentLocation = employee_resolve_site_label(
    (string)($profileRow['deployment_location'] ?? ''),
    $hiredContext
);
if ($deploymentLocation === '—') {
    $deploymentLocation = '';
}
$workSchedule = '';

if ($hiredContext) {
    if ($deploymentCompany === '') {
        $deploymentCompany = trim((string)($hiredContext['company_name'] ?? ''));
    }
    if ($deploymentPosition === '') {
        $deploymentPosition = trim((string)($hiredContext['position'] ?? ''));
    }
    if ($deploymentLocation === '') {
        $deploymentLocation = trim((string)($hiredContext['site'] ?? ''));
    }
    $workSchedule = trim((string)($hiredContext['work_schedule'] ?? ''));
}

$profileSubtitle = $deploymentPosition;
if ($deploymentPosition !== '' && $deploymentCompany !== '') {
    $profileSubtitle = $deploymentPosition . ' — ' . $deploymentCompany;
} elseif ($deploymentCompany !== '') {
    $profileSubtitle = $deploymentCompany;
}

$deploymentLabel = $deploymentCompany !== '' ? $deploymentCompany : '—';
$deploymentSiteLabel = $deploymentLocation !== '' ? $deploymentLocation : '—';
$scheduleLabel = $workSchedule !== '' ? $workSchedule : '—';

$documents = [];
$applicantId = null;

$appStmt = $conn->prepare('SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1');
$appStmt->bind_param('i', $userId);
$appStmt->execute();
$appRow = $appStmt->get_result()->fetch_assoc();
$appStmt->close();

if ($appRow && isset($appRow['ApplicantID'])) {
    $applicantId = (int)$appRow['ApplicantID'];
}

if ($applicantId) {
    $tblCheck = $conn->query("SHOW TABLES LIKE 'applicant_uploaded_docs'");
    $hasApplicantDocs = $tblCheck && $tblCheck->num_rows > 0;
    if ($hasApplicantDocs) {
        $docStmt = $conn->prepare(
            "SELECT
                DocID,
                DocType,
                COALESCE(NULLIF(TRIM(OriginalFileName), ''), NULLIF(TRIM(DocType), ''), 'Document') AS doc_name,
                FilePath,
                UploadedAt
             FROM applicant_uploaded_docs
             WHERE ApplicantID = ?
             ORDER BY UploadedAt DESC
             LIMIT 30"
        );
        if ($docStmt) {
            $docStmt->bind_param('i', $applicantId);
            $docStmt->execute();
            $docResult = $docStmt->get_result();
            while ($r = $docResult->fetch_assoc()) {
                $documents[] = [
                    'doc_id' => isset($r['DocID']) ? (int)$r['DocID'] : null,
                    'name' => (string)($r['doc_name'] ?? 'Document'),
                    'type' => (string)($r['DocType'] ?? ''),
                    'path' => (string)($r['FilePath'] ?? ''),
                    'uploaded_at' => !empty($r['UploadedAt']) ? date('M d, Y', strtotime((string)$r['UploadedAt'])) : null,
                ];
            }
            $docStmt->close();
        }
    }
}

echo json_encode([
    'success' => true,
    'profile' => [
        'user_id' => (int)$profileRow['user_id'],
        'full_name' => (string)($profileRow['full_name'] ?? ''),
        'email' => (string)($profileRow['email'] ?? ''),
        'employee_id' => isset($profileRow['employee_id']) ? (int)$profileRow['employee_id'] : null,
        'employee_number' => (string)($profileRow['employee_number'] ?? ''),
        'position' => $deploymentPosition,
        'company_name' => $deploymentCompany,
        'location' => $deploymentLocation,
        'subtitle' => $profileSubtitle,
        'work_schedule' => $workSchedule,
        'team_lead_name' => (string)($profileRow['team_lead_name'] ?? '—'),
        'assigned_date' => (string)($profileRow['deployment_start_date'] ?? '—'),
        'deployment_status' => !empty($profileRow['deployment_company']) ? 'Active' : 'Unassigned',
        'duty_days' => (string)($profileRow['duty_working_days'] ?? 'Mon-Fri'),
        'duty_time_in' => !empty($profileRow['duty_time_in']) ? date('h:i A', strtotime($profileRow['duty_time_in'])) : '08:00 AM',
        'duty_time_out' => !empty($profileRow['duty_time_out']) ? date('h:i A', strtotime($profileRow['duty_time_out'])) : '05:00 PM',
        'duty_break_duration' => (int)($profileRow['duty_break_duration'] ?? 60),
        'duty_grace_period' => (int)($profileRow['duty_grace_period'] ?? 15),
        'applicant_profile' => [
            'first_name' => (string)($profileRow['applicant_first_name'] ?? ''),
            'last_name' => (string)($profileRow['applicant_last_name'] ?? ''),
            'birth_date' => (string)($profileRow['applicant_birth_date'] ?? ''),
            'home_location' => (string)($profileRow['applicant_home_location'] ?? ''),
            'address' => (string)($profileRow['applicant_address'] ?? ''),
            'job_title' => (string)($profileRow['applicant_job_title'] ?? ''),
            'company_name' => (string)($profileRow['applicant_company_name'] ?? ''),
            'job_category' => (string)($profileRow['applicant_job_category'] ?? ''),
            'preferred_classification' => (string)($profileRow['applicant_preferred_classification'] ?? ''),
            'preferred_sub_classification' => (string)($profileRow['applicant_preferred_sub_classification'] ?? ''),
        ],
    ],
    'deployment' => [
        'company_name' => $deploymentCompany,
        'company_label' => $deploymentLabel,
        'company_id' => isset($profileRow['deployment_company_id']) ? (int)$profileRow['deployment_company_id'] : null,
        'payroll_type' => $deploymentPayrollType,
        'payroll_type_label' => payroll_type_label($deploymentPayrollType),
        'site' => $deploymentLocation,
        'site_label' => $deploymentSiteLabel,
        'site_latitude' => isset($profileRow['site_lat']) ? (float)$profileRow['site_lat'] : null,
        'site_longitude' => isset($profileRow['site_lng']) ? (float)$profileRow['site_lng'] : null,
        'geofence_radius' => isset($profileRow['geofence_radius']) ? (int)$profileRow['geofence_radius'] : 100,
        'site_registered_at' => (string)($profileRow['site_registered_at'] ?? ''),
        'site_registered' => (
            isset($profileRow['site_lat'], $profileRow['site_lng']) &&
            $profileRow['site_lat'] !== null &&
            $profileRow['site_lng'] !== null &&
            (float)$profileRow['site_lat'] !== 0.0 &&
            (float)$profileRow['site_lng'] !== 0.0
        ),
        'schedule' => $workSchedule,
        'schedule_label' => $scheduleLabel,
        'shift_display' => $deploymentLabel !== '—'
            ? $deploymentLabel . ($deploymentSiteLabel !== '—' ? ', ' . $deploymentSiteLabel : '')
            : '—',
        'team_lead' => (string)($profileRow['team_lead_name'] ?? '—'),
        'assigned_date' => (string)($profileRow['deployment_start_date'] ?? '—'),
        'status' => !empty($profileRow['deployment_company']) ? 'Active' : 'Unassigned',
    ],
    'analytics' => [
        'leave_credits' => 0,
        'hours_this_week' => 0,
        'cash_advance_balance' => null,
    ],
    'payroll' => (function () use ($profileRow, $deploymentPayrollType, $deploymentPayrollCompanyId) {
        $period = $deploymentPayrollCompanyId
            ? payroll_type_period($deploymentPayrollType)
            : payroll_kinsenas_period();
        $deductions = payroll_type_deductions($period['type'] ?? $deploymentPayrollType, $period['half'] ?? '1st');
        return [
            'next_payday' => $period['payday_display'],
            'cutoff_period' => $period['label'],
            'payroll_type' => $deploymentPayrollType,
            'payroll_type_label' => payroll_type_label($deploymentPayrollType),
            'period_start' => $period['start'] ?? null,
            'period_end' => $period['end'] ?? null,
            'scheduled_deductions' => array_values(array_filter([
                $deductions['philhealth'] ? 'PhilHealth' : null,
                $deductions['pagibig'] ? 'Pag-IBIG' : null,
                $deductions['sss'] ? 'SSS' : null,
            ])),
            'past_releases' => [],
            'active_deductions' => [],
        ];
    })(),
    'applicant_id' => $applicantId,
    'documents' => $documents,
]);