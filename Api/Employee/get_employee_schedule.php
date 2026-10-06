<?php
declare(strict_types=1);
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit();
}
if ($_SESSION['role'] !== 'Employee') {
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); echo json_encode(['success' => false, 'message' => 'Schedules are view-only.']); exit();
}
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';
require_once __DIR__ . '/../Helpers/employee_calendar_holidays.php';
try {
    $start = team_schedule_date((string)($_GET['start'] ?? date('Y-m-01')));
    $end = team_schedule_date((string)($_GET['end'] ?? date('Y-m-t')));
    if ($end < $start || (strtotime($end) - strtotime($start)) / 86400 > 61) {
        throw new InvalidArgumentException('Choose a date range of at most 62 days.');
    }
    $e = team_schedule_query($conn,
        'SELECT e.EmployeeID FROM employee e JOIN users u ON u.Email = e.Email
         WHERE u.UsersID = ? AND e.IsActive = 1 LIMIT 1', 'i', [(int)$_SESSION['user_id']])->fetch_assoc();
    $schedules = [];
    if ($e) {
        for ($cursor = new DateTimeImmutable($start); $cursor->format('Y-m-d') <= $end; $cursor = $cursor->modify('+1 day')) {
            $date = $cursor->format('Y-m-d');
            $schedule = payroll_resolve_deployment_schedule($conn, (int)$e['EmployeeID'], $date);
            // No deployment means no assigned expectation, rather than a fabricated default.
            if (empty($schedule['deployment_id'])) continue;
            $schedules[$date] = team_schedule_public($schedule, $date);
        }
    }
    echo json_encode(['success' => true, 'schedules' => $schedules, 'holidays' => $e ? employee_calendar_holidays($conn,$start,$end) : [], 'today' => date('Y-m-d')]);
} catch (InvalidArgumentException $e) {
    http_response_code(422); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('get_employee_schedule: ' . $e->getMessage());
    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Unable to load your schedule.']);
}
