<?php
declare(strict_types=1);

require_once __DIR__ . '/../Common/NotificationHelper.php';
require_once __DIR__ . '/AuditLogger.php';
require_once __DIR__.'/work_site_geofence.php';

function team_schedule_table_available(mysqli $conn): bool
{
    static $checked = [];
    $key = spl_object_id($conn);
    if (!array_key_exists($key, $checked)) {
        $checked[$key] = $conn->query("SHOW TABLES LIKE 'team_schedule_entries'")->num_rows > 0;
    }
    return $checked[$key];
}

function team_schedule_query(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

function team_schedule_date(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Choose a valid schedule date.');
    }
    return $date;
}

/** Pure validation, shared by the API and focused tests. Break/grace use minutes. */
function team_schedule_validate(array $input): array
{
    $type = $input['schedule_type'] ?? '';
    if (!in_array($type, ['Fixed', 'Rotating', 'Flexible'], true)) {
        throw new InvalidArgumentException('Choose Fixed, Rotating, or Flexible.');
    }
    $result = ['schedule_type' => $type, 'date' => team_schedule_date((string)($input['date'] ?? ''))];
    foreach (['time_in', 'time_out'] as $field) {
        $time = (string)($input[$field] ?? '');
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $time)) {
            throw new InvalidArgumentException('Choose valid expected Time In and Time Out.');
        }
        $result[$field] = strlen($time) === 5 ? $time . ':00' : $time;
    }
    $start = strtotime('2000-01-01 ' . $result['time_in']);
    $end = strtotime('2000-01-01 ' . $result['time_out']);
    if ($start === $end) throw new InvalidArgumentException('Time In and Time Out must differ.');
    if ($end < $start) $end += 86400; // Overnight shift belongs to its start date.
    foreach (['break_duration' => 720, 'grace_period' => 180] as $field => $max) {
        $value = filter_var($input[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value === null || $value < 0 || $value > $max) {
            throw new InvalidArgumentException('Enter valid break and grace minutes.');
        }
        $result[$field] = $value;
    }
    if ($result['break_duration'] * 60 >= $end - $start) {
        throw new InvalidArgumentException('The break must be shorter than the shift.');
    }
    $days = $input['working_days'] ?? [];
    $allowed = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    if ($type === 'Fixed') {
        if (!is_array($days) || !$days || count(array_filter($days, 'is_string')) !== count($days)
            || count(array_unique($days)) !== count($days)
            || array_diff($days, $allowed)) {
            throw new InvalidArgumentException('Select at least one valid working day.');
        }
        $result['working_days'] = implode(', ', array_values(array_intersect($allowed, $days)));
    } else {
        $result['working_days'] = date('D', strtotime($result['date']));
    }
    return $result;
}

function team_schedule_shape(array $row): array
{
    return [
        'time_in' => $row['TimeIn'] ?: '08:00:00',
        'time_out' => $row['TimeOut'] ?: '17:00:00',
        'break_duration' => (int)($row['BreakDuration'] ?? 60),
        'break_minutes' => (int)($row['BreakDuration'] ?? 60),
        'grace_period' => (int)($row['GracePeriod'] ?? 5),
        'working_days' => $row['WorkingDays'] ?: 'Mon-Fri',
        'shift_name' => $row['ShiftName'] ?: 'Regular Shift',
        'schedule_type' => $row['ScheduleType'] ?? 'Fixed',
        'schedule_id' => isset($row['ScheduleID']) ? (int)$row['ScheduleID'] : null,
        'deployment_id' => (int)$row['DeploymentID'],
    ];
}

/** Dated expectations override archived/current recurring settings for this deployment only. */
function team_schedule_resolve(mysqli $conn, array $deployment, string $date): array
{
    if (team_schedule_table_available($conn)) {
        $override = team_schedule_query($conn,
            "SELECT * FROM team_schedule_entries WHERE DeploymentID = ?
             AND ((EntryKind = 'Dated' AND ScheduleDate = ?)
               OR (EntryKind = 'FixedHistory' AND ScheduleDate <= ? AND ValidUntil >= ?))
             ORDER BY (EntryKind = 'Dated') DESC, ScheduleDate DESC LIMIT 1",
            'isss', [(int)$deployment['DeploymentID'], $date, $date, $date])->fetch_assoc();
        if ($override) return team_schedule_shape($override);
    }
    return team_schedule_shape($deployment);
}

function team_schedule_public(array $schedule, string $date): array
{
    $schedule['date'] = $date;
    $schedule['is_working_day'] = payroll_is_scheduled_day($date, payroll_scheduled_weekdays($schedule['working_days']));
    $schedule['time_in_display'] = date('h:i A', strtotime($schedule['time_in']));
    $schedule['time_out_display'] = date('h:i A', strtotime($schedule['time_out']));
    $schedule['overnight'] = $schedule['time_out'] < $schedule['time_in'];
    return $schedule;
}

/** Serializes changes on the owned deployment, never trusting a browser employee ID. */
function team_schedule_owned_deployment(mysqli $conn, int $deploymentId, int $leadId): array
{
    $row = team_schedule_query($conn,
        "SELECT d.*, e.Email, CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName
         FROM deployment d JOIN employee e ON e.EmployeeID = d.EmployeeID AND e.IsActive = 1
         WHERE d.DeploymentID = ? AND EXISTS(SELECT 1 FROM deployment_sites s JOIN clientcompany c ON c.CompanyID=s.CompanyID AND c.IsActive=1 WHERE s.SiteID=d.SiteID AND s.TeamLeadID=? AND s.IsActive=1)
           AND d.StartDate <= CURDATE() AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
           AND d.DeploymentID = (SELECT d2.DeploymentID FROM deployment d2
               WHERE d2.EmployeeID = d.EmployeeID AND d2.StartDate <= CURDATE()
                 AND (d2.EndDate IS NULL OR d2.EndDate >= CURDATE())
               ORDER BY d2.StartDate DESC, d2.DeploymentID DESC LIMIT 1)
         FOR UPDATE", 'ii', [$deploymentId, $leadId])->fetch_assoc();
    if (!$row) throw new DomainException('This employee is not in your current deployed team.', 403);
    return $row;
}

function team_schedule_assert_editable(mysqli $conn, int $employeeId, string $date, bool $recurring = false): void
{
    $end = $recurring ? '9999-12-31' : $date;
    $locked = team_schedule_query($conn,
        'SELECT DTR_SubmissionID FROM dtr_submissions WHERE EmployeeID = ?
         AND CutoffStartDate <= ? AND CutoffEndDate >= ? LIMIT 1', 'iss', [$employeeId, $end, $date])->num_rows > 0;
    $locked = $locked || team_schedule_query($conn,
        'SELECT Payroll_ID FROM payroll WHERE EmployeeID = ? AND PayPeriodStart <= ? AND PayPeriodEnd >= ? LIMIT 1',
        'iss', [$employeeId, $end, $date])->num_rows > 0;
    // A started shift keeps its original expectation, including an overnight clock-out.
    $started = team_schedule_query($conn,
        'SELECT AttendanceID FROM attendance WHERE EmployeeID = ? AND Date = ? AND TimeIn IS NOT NULL LIMIT 1',
        'is', [$employeeId, $date])->num_rows > 0;
    $started = $started || team_schedule_query($conn,
        'SELECT id FROM daily_time_records WHERE employee_id = ? AND date = ? AND time_in IS NOT NULL LIMIT 1',
        'is', [$employeeId, $date])->num_rows > 0;
    if ($locked || $started) {
        throw new DomainException('This schedule is locked by recorded attendance, a submitted DTR, or payroll.', 409);
    }
}

function team_schedule_notify(mysqli $conn, array $deployment, string $event, array $schedule): void
{
    // employee has no UsersID column: reuse the actual employee.Email -> users.Email relationship.
    $users = team_schedule_query($conn,
        "SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID = u.RoleID
         WHERE u.Email = ? AND u.IsActive = 1 AND r.RoleName = 'Employee'", 's', [$deployment['Email']]);
    if ($users->num_rows !== 1) throw new DomainException('The employee needs one active linked Employee account.', 409);
    $recipient = (int)$users->fetch_assoc()['UsersID'];
    $title = $schedule['schedule_type'] !== 'Fixed' && $event !== 'removed' ? 'Duty Schedule Changed' : 'Schedule ' . ucfirst($event);
    $label = $schedule['schedule_type'] === 'Fixed'
        ? 'from ' . date('F j, Y', strtotime($schedule['date'])) . ' (' . $schedule['working_days'] . ')'
        : 'for ' . date('F j, Y', strtotime($schedule['date']));
    $times = date('g:i A', strtotime($schedule['time_in'])) . '–' . date('g:i A', strtotime($schedule['time_out']));
    if ($schedule['time_out'] < $schedule['time_in']) $times .= ' (next day)';
    $message = 'Your ' . $schedule['schedule_type'] . ' work schedule ' . $label . ' has been ' . $event . '. '
        . 'Expected: ' . $times . '; break: ' . $schedule['break_duration'] . ' minutes.';
    if($schedule['schedule_type']!=='Fixed' && $event!=='removed')$message='Your duty schedule has been changed by your Team Lead. Date: '.date('F j, Y',strtotime($schedule['date'])).'. New Duty: '.$times.'. This change applies only to this date.';
    if ($event === 'removed') $message .= ' Your recurring deployment schedule applies again. Please check your updated schedule.';
    if (!insert_system_notification($conn, $recipient, 'schedule_' . $event, $title, $message, 'team_schedule',
        (int)$deployment['DeploymentID'])) throw new RuntimeException('Schedule notification could not be saved.');
}

/** Caller owns the transaction. Save + notification either both commit or both roll back. */
function team_schedule_save(mysqli $conn, int $leadId, int $deploymentId, array $input): string
{
    $s = team_schedule_validate($input);
    $d = team_schedule_owned_deployment($conn, $deploymentId, $leadId);
    if ($s['date'] < $d['StartDate'] || ($d['EndDate'] && $s['date'] > $d['EndDate'])) {
        throw new InvalidArgumentException('The schedule date must be within the deployment.');
    }
    team_schedule_assert_editable($conn, (int)$d['EmployeeID'], $s['date'], $s['schedule_type'] === 'Fixed');
    if ($s['schedule_type'] === 'Fixed') {
        if ($s['date'] !== date('Y-m-d')) throw new InvalidArgumentException('Fixed schedule changes take effect today. Use a dated shift for another date.');
        $same = $d['TimeIn'] === $s['time_in'] && $d['TimeOut'] === $s['time_out']
            && (int)$d['BreakDuration'] === $s['break_duration'] && (int)$d['GracePeriod'] === $s['grace_period']
            && $d['WorkingDays'] === $s['working_days'] && $d['ShiftName'] === 'Fixed';
        if ($same) return 'unchanged';
        $last = team_schedule_query($conn,
            "SELECT MAX(ValidUntil) AS LastDate FROM team_schedule_entries WHERE DeploymentID = ? AND EntryKind = 'FixedHistory'",
            'i', [$deploymentId])->fetch_assoc()['LastDate'];
        $from = $last ? date('Y-m-d', strtotime($last . ' +1 day')) : $d['StartDate'];
        $until = date('Y-m-d', strtotime($s['date'] . ' -1 day'));
        if ($from && $from <= $until) {
            $prior = team_schedule_shape($d);
            $stmt = $conn->prepare("INSERT INTO team_schedule_entries
                (DeploymentID, EntryKind, ScheduleDate, ValidUntil, ScheduleType, TimeIn, TimeOut,
                 BreakDuration, GracePeriod, WorkingDays, ShiftName, UpdatedBy)
                VALUES (?, 'FixedHistory', ?, ?, 'Fixed', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssiissi', $deploymentId, $from, $until, $prior['time_in'], $prior['time_out'],
                $prior['break_duration'], $prior['grace_period'], $prior['working_days'], $prior['shift_name'], $leadId);
            $stmt->execute(); $stmt->close();
        }
        $event = $d['ShiftName'] === 'Fixed' ? 'updated' : 'assigned';
        $stmt = $conn->prepare("UPDATE deployment SET TimeIn = ?, TimeOut = ?, BreakDuration = ?, GracePeriod = ?, WorkingDays = ?, ShiftName = 'Fixed' WHERE DeploymentID = ?");
        $stmt->bind_param('ssiisi', $s['time_in'], $s['time_out'], $s['break_duration'], $s['grace_period'], $s['working_days'], $deploymentId);
        $stmt->execute(); $stmt->close();
    } else {
        $current=team_schedule_public(team_schedule_resolve($conn,$d,$s['date']),$s['date']);
        if($current['is_working_day'] && $current['time_in']===$s['time_in'] && $current['time_out']===$s['time_out'] && (int)$current['break_duration']===$s['break_duration'] && (int)$current['grace_period']===$s['grace_period'])return 'unchanged';
        $old = team_schedule_query($conn,
            "SELECT * FROM team_schedule_entries WHERE DeploymentID = ? AND ScheduleDate = ? AND EntryKind = 'Dated'",
            'is', [$deploymentId, $s['date']])->fetch_assoc();
        if ($old && $old['ScheduleType'] === $s['schedule_type'] && $old['TimeIn'] === $s['time_in']
            && $old['TimeOut'] === $s['time_out'] && (int)$old['BreakDuration'] === $s['break_duration']
            && (int)$old['GracePeriod'] === $s['grace_period']) return 'unchanged';
        $event = $old ? 'updated' : 'assigned';
        $stmt = $conn->prepare("INSERT INTO team_schedule_entries
            (DeploymentID, ScheduleDate, ScheduleType, TimeIn, TimeOut, BreakDuration, GracePeriod, WorkingDays, ShiftName, UpdatedBy)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE ScheduleType = VALUES(ScheduleType), TimeIn = VALUES(TimeIn), TimeOut = VALUES(TimeOut),
              BreakDuration = VALUES(BreakDuration), GracePeriod = VALUES(GracePeriod), WorkingDays = VALUES(WorkingDays),
              ShiftName = VALUES(ShiftName), UpdatedBy = VALUES(UpdatedBy)");
        $stmt->bind_param('issssiissi', $deploymentId, $s['date'], $s['schedule_type'], $s['time_in'], $s['time_out'],
            $s['break_duration'], $s['grace_period'], $s['working_days'], $s['schedule_type'], $leadId);
        $stmt->execute(); $stmt->close();
    }
    team_schedule_notify($conn, $d, $event, $s);
    AuditLogger::log($conn,'Team Schedule','Schedule Change','Changed '.$d['EmployeeName'].' duty '.($s['schedule_type']==='Fixed'?'from ':'on ').$s['date'].' from '.(isset($current)?($current['is_working_day']?$current['time_in'].'–'.$current['time_out']:'Rest day'):$d['TimeIn'].'–'.$d['TimeOut']).' to '.$s['time_in'].'–'.$s['time_out'],(string)$deploymentId);
    return $event;
}

function team_schedule_delete(mysqli $conn, int $leadId, int $deploymentId, int $scheduleId): void
{
    $d = team_schedule_owned_deployment($conn, $deploymentId, $leadId);
    $row = team_schedule_query($conn,
        "SELECT * FROM team_schedule_entries WHERE ScheduleID = ? AND DeploymentID = ? AND EntryKind = 'Dated'",
        'ii', [$scheduleId, $deploymentId])->fetch_assoc();
    if (!$row) throw new DomainException('Dated schedule not found in this deployment.', 404);
    team_schedule_assert_editable($conn, (int)$d['EmployeeID'], $row['ScheduleDate']);
    $stmt = $conn->prepare("DELETE FROM team_schedule_entries WHERE ScheduleID = ? AND DeploymentID = ? AND EntryKind = 'Dated'");
    $stmt->bind_param('ii', $scheduleId, $deploymentId);
    $stmt->execute(); $stmt->close();
    $s = team_schedule_shape($row); $s['date'] = $row['ScheduleDate'];
    team_schedule_notify($conn, $d, 'removed', $s);
}
