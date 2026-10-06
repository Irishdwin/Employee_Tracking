<?php
/** CLI-only read-only HTTP checks using isolated test-server sessions. No real accounts are changed. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(); }
require_once __DIR__ . '/../../Api/Db_connection/db_connect.php';
$sessionDir = $argv[1] ?? '';
$baseUrl = $argv[2] ?? 'http://127.0.0.1:8893';
if (!is_dir($sessionDir)) throw new RuntimeException('Supply the isolated test server session directory.');
ini_set('session.save_path', $sessionDir);
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
$passed = 0; $sessionFiles = [];
$fixtureUsers = []; $fixtureEmployee = 0; $fixtureDeployment = 0;
function http_check(bool $ok, string $label): void {
    global $passed;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $passed++; echo 'PASS: ' . $label . PHP_EOL;
}
function test_session(int $userId, string $role): string {
    global $sessionFiles, $sessionDir;
    $id = bin2hex(random_bytes(16));
    session_id($id); session_start();
    $_SESSION = ['user_id' => $userId, 'role' => $role, 'last_activity' => time()];
    session_write_close(); $sessionFiles[] = $sessionDir . '/sess_' . $id;
    return $id;
}
function http_request(string $path, ?string $cookie = null, string $method = 'GET', ?array $body = null): array {
    global $baseUrl;
    $headers = 'Content-Type: application/json' . "\r\n";
    if ($cookie) $headers .= 'Cookie: PHPSESSID=' . $cookie . "\r\n";
    $context = stream_context_create(['http' => ['method' => $method, 'header' => $headers,
        'content' => $body === null ? '' : json_encode($body), 'ignore_errors' => true, 'timeout' => 15]]);
    $raw = file_get_contents($baseUrl . $path, false, $context);
    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $m);
    return [(int)($m[1] ?? 0), json_decode($raw ?: '', true)];
}
$exitCode = 0;
try {
    $lead = $conn->query("SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID = u.RoleID WHERE r.RoleName = 'TeamLead' AND u.IsActive = 1 ORDER BY u.UsersID LIMIT 1")->fetch_assoc();
    $employee = $conn->query("SELECT u.UsersID, e.EmployeeID FROM users u JOIN employee e ON e.Email = u.Email JOIN roles r ON r.RolesID = u.RoleID WHERE r.RoleName = 'Employee' AND u.IsActive = 1 AND e.IsActive = 1 ORDER BY u.UsersID LIMIT 1")->fetch_assoc();
    if (!$lead || !$employee) throw new RuntimeException('An existing active Team Lead and Employee are needed for read-only HTTP tests.');
    // Create all isolated sessions before output, so PHP can start each session cleanly.
    $leadCookie = test_session((int)$lead['UsersID'], 'TeamLead');
    $employeeCookie = test_session((int)$employee['UsersID'], 'Employee');
    [$code] = http_request('/Api/TeamLead/team_schedule.php');
    http_check($code === 401, 'Anonymous schedule management rejected');
    [$code] = http_request('/Api/Employee/get_employee_schedule.php');
    http_check($code === 401, 'Anonymous employee schedule lookup rejected');
    [$code, $team] = http_request('/Api/TeamLead/team_schedule.php', $leadCookie);
    http_check($code === 200 && $team['success'] && !empty($team['csrf_token']), 'Team Lead roster and CSRF token load over HTTP');
    foreach ($team['employees'] as $row) {
        $d = $conn->query('SELECT SiteSuperVisorID FROM deployment WHERE DeploymentID = ' . (int)$row['deployment_id'])->fetch_assoc();
        http_check((int)$d['SiteSuperVisorID'] === (int)$lead['UsersID'], 'Roster includes only owned deployment');
    }
    [$code] = http_request('/Api/TeamLead/team_schedule.php', $employeeCookie);
    http_check($code === 403, 'Employee cannot access Team Lead management API');
    [$code] = http_request('/Api/TeamLead/team_schedule.php', $leadCookie, 'POST', ['action' => 'save']);
    http_check($code === 403, 'Missing CSRF token rejected');
    [$code] = http_request('/Api/TeamLead/team_schedule.php', $leadCookie, 'POST', ['action' => 'invalid', 'deployment_id' => 1, 'csrf_token' => $team['csrf_token']]);
    http_check($code === 422, 'Invalid schedule action rejected');
    $foreign = $conn->query('SELECT DeploymentID FROM deployment WHERE SiteSuperVisorID <> ' . (int)$lead['UsersID'] . ' AND StartDate <= CURDATE() AND (EndDate IS NULL OR EndDate >= CURDATE()) LIMIT 1')->fetch_assoc();
    if ($foreign) {
        [$code] = http_request('/Api/TeamLead/team_schedule.php', $leadCookie, 'POST', ['action' => 'save', 'deployment_id' => (int)$foreign['DeploymentID'],
            'csrf_token' => $team['csrf_token'], 'date' => date('Y-m-d'), 'schedule_type' => 'Rotating',
            'time_in' => '08:00', 'time_out' => '17:00', 'break_duration' => 60, 'grace_period' => 5]);
        http_check($code === 403, 'Actual HTTP save rejects another Team Lead\'s employee');
    }
    [$code] = http_request('/Api/Employee/get_employee_schedule.php', $employeeCookie, 'POST', []);
    http_check($code === 405, 'Employee schedule API is view-only');
    [$code, $own] = http_request('/Api/Employee/get_employee_schedule.php?employee_id=999999', $employeeCookie);
    http_check($code === 200 && $own['success'], 'Employee reads own schedule, ignoring browser employee ID');
    foreach ($own['schedules'] as $row) {
        $d = $conn->query('SELECT EmployeeID FROM deployment WHERE DeploymentID = ' . (int)$row['deployment_id'])->fetch_assoc();
        if ((int)$d['EmployeeID'] !== (int)$employee['EmployeeID']) throw new RuntimeException('Employee schedule ownership mismatch.');
    }
    http_check(true, 'Every returned schedule belongs to the session employee');
    [$code] = http_request('/Api/Employee/get_employee_schedule.php?start=2026-02-30', $employeeCookie);
    http_check($code === 422, 'Invalid lookup date rejected');
    [$code] = http_request('/Api/Employee/get_employee_schedule.php?start=2026-01-01&end=2026-12-31', $employeeCookie);
    http_check($code === 422, 'Unbounded lookup range rejected');
    [$code, $notif] = http_request('/Api/Common/notifications.php', $employeeCookie);
    http_check($code === 200 && $notif['success'], 'Existing Employee notification list loads');
    $unread = (int)$conn->query('SELECT COUNT(*) AS c FROM notifications WHERE UserID = ' . (int)$employee['UsersID'] . ' AND IsRead = 0')->fetch_assoc()['c'];
    http_check((int)$notif['unread_count'] === $unread, 'Notification unread count matches actual database');
    [$code, $attendance] = http_request('/Api/Employee/get_employee_attendance.php', $employeeCookie);
    http_check($code === 200 && $attendance['success'] && array_key_exists('expected_schedule', $attendance['today']), 'Employee attendance exposes expected schedule separately from actual attendance');
    [$code, $duty] = http_request('/Api/DutySchedule/get_duty_schedule.php', $employeeCookie);
    http_check($code === 200 && $duty['success'], 'Duty Schedule reads shared expected schedule');
    [$code, $roster] = http_request('/Api/TeamLead/get_tl_attendance_roster.php', $leadCookie);
    http_check($code === 200 && $roster['success'], 'Team Lead attendance roster still loads');

    // Exercise successful HTTP writes on clearly labelled, disposable synthetic accounts only.
    $roles = [];
    foreach ($conn->query('SELECT RolesID, RoleName FROM roles') as $row) $roles[$row['RoleName']] = (int)$row['RolesID'];
    $tag = bin2hex(random_bytes(6));
    $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    foreach (['TeamLead', 'Employee'] as $role) {
        $email = 'team-schedule-test-' . strtolower($role) . '-' . $tag . '@example.invalid';
        $roleId = $roles[$role]; $name = 'Disposable Team Schedule Test';
        $stmt = $conn->prepare('INSERT INTO users (RoleID, FullName, Email, Password) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isss', $roleId, $name, $email, $hash); $stmt->execute(); $stmt->close();
        $fixtureUsers[$role] = (int)$conn->insert_id;
        if ($role === 'Employee') $fixtureEmail = $email;
    }
    $stmt = $conn->prepare("INSERT INTO employee (FirstName, LastName, Email, IsActive) VALUES ('Disposable Schedule', 'Test', ?, 1)");
    $stmt->bind_param('s', $fixtureEmail); $stmt->execute(); $stmt->close(); $fixtureEmployee = (int)$conn->insert_id;
    $company = (int)$conn->query('SELECT CompanyID FROM clientcompany ORDER BY CompanyID LIMIT 1')->fetch_assoc()['CompanyID'];
    $fixtureLead = $fixtureUsers['TeamLead'];
    $stmt = $conn->prepare("INSERT INTO deployment (EmployeeID, CompanyID, StartDate, SiteSuperVisorID) VALUES (?, ?, '2026-01-01', ?)");
    $stmt->bind_param('iii', $fixtureEmployee, $company, $fixtureLead); $stmt->execute(); $stmt->close(); $fixtureDeployment = (int)$conn->insert_id;
    $fixtureLeadCookie = test_session($fixtureLead, 'TeamLead');
    $fixtureEmployeeCookie = test_session($fixtureUsers['Employee'], 'Employee');
    [$code, $fixtureTeam] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie);
    $payload = ['action' => 'save', 'deployment_id' => $fixtureDeployment, 'csrf_token' => $fixtureTeam['csrf_token'],
        'schedule_type' => 'Fixed', 'date' => date('Y-m-d'), 'time_in' => '07:00', 'time_out' => '16:00',
        'break_duration' => 60, 'grace_period' => 10, 'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']];
    [$code, $saved] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', $payload);
    http_check($code === 200 && $saved['event'] === 'assigned', 'TEST 1: Fixed schedule saved through actual HTTP API');
    [$code, $fixedView] = http_request('/Api/Employee/get_employee_schedule.php', $fixtureEmployeeCookie);
    http_check(($fixedView['schedules'][date('Y-m-d')]['time_in'] ?? '') === '07:00:00', 'Employee sees assigned Fixed hours');
    [$code, $fixedNotif] = http_request('/Api/Common/notifications.php', $fixtureEmployeeCookie);
    http_check($fixedNotif['unread_count'] === 1 && $fixedNotif['notifications'][0]['title'] === 'Schedule Assigned'
        && $fixedNotif['notifications'][0]['from'] === 'Team Lead', 'Fixed assignment appears in existing notification API and unread count');
    $dated = array_replace($payload, ['schedule_type' => 'Rotating', 'date' => '2026-09-29', 'time_in' => '06:00', 'time_out' => '14:00', 'break_duration' => 0]);
    [$code] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', $dated);
    http_check($code === 200, 'TEST 2: Rotating schedule for September 29 saved');
    $datedNext = array_replace($dated, ['date' => '2026-09-30', 'time_in' => '14:00', 'time_out' => '22:00']);
    [$code] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', $datedNext);
    [$code, $september] = http_request('/Api/Employee/get_employee_schedule.php?start=2026-09-29&end=2026-09-30', $fixtureEmployeeCookie);
    http_check($september['schedules']['2026-09-29']['time_in'] === '06:00:00'
        && $september['schedules']['2026-09-30']['time_in'] === '14:00:00', 'TEST 3: Employee sees independent consecutive rotating shifts');
    $flex = array_replace($dated, ['schedule_type' => 'Flexible', 'date' => '2026-10-01', 'time_in' => '22:00', 'time_out' => '06:00']);
    [$code] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', $flex);
    [$code, $october] = http_request('/Api/Employee/get_employee_schedule.php?start=2026-10-01&end=2026-10-01', $fixtureEmployeeCookie);
    http_check($code === 200 && $october['schedules']['2026-10-01']['overnight'] && $october['schedules']['2026-10-01']['schedule_type'] === 'Flexible', 'TEST 4: Flexible overnight expectation is visible to Employee');
    require_once __DIR__ . '/../../Api/Helpers/employee_payroll_helper.php';
    $expected = payroll_resolve_deployment_schedule($conn, $fixtureEmployee, '2026-09-29');
    $meta = payroll_timesheet_day_status(['time_in' => '2026-09-29 06:15:00', 'time_out' => '2026-09-29 14:15:00'], '2026-09-29', $expected, date('Y-m-d'));
    http_check($meta['late_minutes'] === 15 && $meta['is_late'], 'TEST 5: Existing attendance helper compares actual clock-in with assigned expectation');
    $updated = array_replace($dated, ['time_in' => '14:00', 'time_out' => '22:00']);
    [$code, $update] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', $updated);
    [$code, $updateNotif] = http_request('/Api/Common/notifications.php', $fixtureEmployeeCookie);
    http_check($update['event'] === 'updated' && $updateNotif['notifications'][0]['title'] === 'Schedule Updated', 'TEST 6: Update persists and creates Employee notification');
    [$code, $same] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', $updated);
    http_check($same['event'] === 'unchanged', 'Repeated identical HTTP save is idempotent');
    [$code, $entries] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie);
    $target = array_values(array_filter($entries['schedules'], static fn($s) => $s['date'] === '2026-09-29'))[0];
    [$code, $removed] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', ['action' => 'delete',
        'deployment_id' => $fixtureDeployment, 'schedule_id' => $target['schedule_id'], 'csrf_token' => $fixtureTeam['csrf_token']]);
    http_check($code === 200 && $removed['event'] === 'removed', 'TEST 7: Dated schedule deleted through HTTP API');
    [$code, $removeNotif] = http_request('/Api/Common/notifications.php', $fixtureEmployeeCookie);
    http_check($removeNotif['notifications'][0]['title'] === 'Schedule Removed'
        && strpos($removeNotif['notifications'][0]['message'], 'September 29, 2026') !== false, 'TEST 8: Removal is listed with correct date and schedule details');
    http_check($removeNotif['unread_count'] === 6, 'TEST 9: Six distinct events create six actual unread notifications');
    [$code] = http_request('/Api/Common/notifications.php', $fixtureEmployeeCookie, 'POST', ['action' => 'mark_read', 'notification_id' => $removeNotif['notifications'][0]['id']]);
    [$code, $readNotif] = http_request('/Api/Common/notifications.php', $fixtureEmployeeCookie);
    http_check($readNotif['unread_count'] === 5, 'Existing notification read API reduces unread count');
    [$code] = http_request('/Api/TeamLead/team_schedule.php', $leadCookie, 'POST', array_replace($datedNext, ['csrf_token' => $team['csrf_token']]));
    http_check($code === 403, 'TEST 10: Unrelated Team Lead cannot update fixture deployment');
    $historic = payroll_resolve_deployment_schedule($conn, $fixtureEmployee, '2026-09-28');
    http_check($historic['time_in'] === '08:00:00', 'Fixed assignment preserves earlier recurring schedule history');
    foreach ([['date' => '2026-02-30'], ['time_in' => '25:00'], ['break_duration' => 600], ['schedule_type' => 'Custom']] as $invalid) {
        [$code] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', array_replace($dated, $invalid));
        http_check($code === 422, 'Invalid schedule field rejected: ' . key($invalid));
    }
    $night = payroll_resolve_deployment_schedule($conn, $fixtureEmployee, '2026-10-01');
    $nightStatus = payroll_timesheet_day_status(['time_in' => '2026-10-01 22:00:00', 'time_out' => '2026-10-02 06:00:00'], '2026-10-01', $night, date('Y-m-d'));
    http_check($nightStatus['total_hours'] === 8.0 && !$nightStatus['is_late'], 'Overnight attendance uses actual next-day clock-out');
    $withinGrace = payroll_timesheet_day_status(['time_in' => '2026-09-30 14:10:00'], '2026-09-30',
        payroll_resolve_deployment_schedule($conn, $fixtureEmployee, '2026-09-30'), date('Y-m-d'));
    http_check(!$withinGrace['is_late'], 'Existing grace-period boundary preserved');
    require_once __DIR__ . '/../../Api/Helpers/attendance_pdo.php';
    $pdo = attendance_pdo();
    // Reuse an actual saved holiday, without changing holiday records or formulas.
    $holiday = $conn->query("SELECT HolidayDate FROM holidays WHERE HolidayType = 'Regular Holiday' AND HolidayDate >= '2026-01-01'
        AND HolidayDate IN (SELECT HolidayDate FROM holidays GROUP BY HolidayDate HAVING COUNT(*) = 1) ORDER BY HolidayDate DESC LIMIT 1")->fetch_assoc();
    if ($holiday) {
        $holidayDate = $holiday['HolidayDate'];
        $holidayShift = array_replace($dated, ['date' => $holidayDate, 'time_in' => '08:00', 'time_out' => '17:00', 'break_duration' => 60]);
        [$code] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', $holidayShift);
        $stmt = $conn->prepare("INSERT INTO daily_time_records (employee_id, date, time_in, time_out, total_hours_rendered) VALUES (?, ?, ?, ?, 9)");
        $holidayIn = $holidayDate . ' 08:00:00'; $holidayOut = $holidayDate . ' 17:00:00';
        $stmt->bind_param('isss', $fixtureEmployee, $holidayDate, $holidayIn, $holidayOut); $stmt->execute(); $stmt->close();
        $pay = payroll_compute_period($conn, $pdo, $fixtureEmployee, 700, 87.5, 0, 18200, $holidayDate, $holidayDate);
        http_check(count($pay['holiday_breakdown']) === 1 && !$pay['holiday_breakdown'][0]['is_rest_day']
            && $pay['holiday_breakdown'][0]['worked'], 'TEST 11: Actual payroll engine applies existing holiday rules to dated shift');
        http_check($pay['days_worked'] === 1 && $pay['late_undertime']['late_minutes'] === 0
            && $pay['late_undertime']['undertime_minutes'] === 0, 'TEST 12: Payroll receives assigned start, break, and real attendance');
        [$code] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', array_replace($holidayShift, ['time_in' => '09:00']));
        http_check($code === 409, 'Started attendance protects recorded holiday shift expectations');
    }
    $conn->query("INSERT INTO daily_time_records (employee_id, date, time_in, time_out, total_hours_rendered)
        VALUES ($fixtureEmployee, '2026-09-30', '2026-09-30 14:00:00', '2026-09-30 22:30:00', 8.5)");
    $otPay = payroll_compute_period($conn, $pdo, $fixtureEmployee, 700, 87.5, 0, 18200, '2026-09-30', '2026-09-30');
    http_check($otPay['ot_detail']['regular_ot_hours'] === 0.5, 'Existing overtime engine keeps additional 30 minutes under its current rules');
    $restHoliday = $conn->query("SELECT HolidayDate FROM holidays WHERE HolidayType IN ('Regular Holiday', 'Special Non-working Day')
        AND HolidayDate >= '2026-01-01' AND DAYOFWEEK(HolidayDate) IN (1, 7)
        AND HolidayDate IN (SELECT HolidayDate FROM holidays GROUP BY HolidayDate HAVING COUNT(*) = 1) ORDER BY HolidayDate DESC LIMIT 1")->fetch_assoc();
    if ($restHoliday) {
        $restDate = $restHoliday['HolidayDate'];
        $stmt = $conn->prepare('INSERT INTO daily_time_records (employee_id, date, time_in, time_out) VALUES (?, ?, ?, ?)');
        $restIn = $restDate . ' 08:00:00'; $restOut = $restDate . ' 17:00:00';
        $stmt->bind_param('isss', $fixtureEmployee, $restDate, $restIn, $restOut); $stmt->execute(); $stmt->close();
        $restPay = payroll_compute_period($conn, $pdo, $fixtureEmployee, 700, 87.5, 0, 18200, $restDate, $restDate);
        http_check(!empty($restPay['holiday_breakdown'][0]['is_rest_day']), 'Existing holiday plus rest-day branch receives recurring rest-day schedule');
    }
    $conn->query("INSERT INTO dtr_submissions (EmployeeID, DeploymentID, CutoffStartDate, CutoffEndDate)
        VALUES ($fixtureEmployee, $fixtureDeployment, '2026-10-11', '2026-10-25')");
    [$code] = http_request('/Api/TeamLead/team_schedule.php', $fixtureLeadCookie, 'POST', array_replace($dated, ['date' => '2026-10-15']));
    http_check($code === 409, 'Submitted DTR cutoff locks its expected schedules');
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL); $exitCode = 1;
} finally {
    // Exact IDs created above only; no existing users, schedules, or attendance are deleted.
    if ($fixtureEmployee) {
        $conn->query('DELETE FROM dtr_submissions WHERE EmployeeID = ' . $fixtureEmployee);
        $conn->query('DELETE FROM daily_time_records WHERE employee_id = ' . $fixtureEmployee);
    }
    if ($fixtureDeployment) {
        $conn->query('DELETE FROM team_schedule_entries WHERE DeploymentID = ' . $fixtureDeployment);
        $conn->query('DELETE FROM deployment WHERE DeploymentID = ' . $fixtureDeployment);
    }
    if ($fixtureEmployee) $conn->query('DELETE FROM employee WHERE EmployeeID = ' . $fixtureEmployee);
    foreach ($fixtureUsers as $id) {
        $conn->query('DELETE FROM notifications WHERE UserID = ' . $id);
        $conn->query('DELETE FROM users WHERE UsersID = ' . $id);
    }
    foreach ($sessionFiles as $path) if (is_file($path)) unlink($path);
    echo 'HTTP checks passed: ' . $passed . '. Isolated sessions removed.' . PHP_EOL;
}
exit($exitCode);
