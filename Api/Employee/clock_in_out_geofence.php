<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/work_site_geofence.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';

if (!isset($_SESSION['user_id'],$_SESSION['role']) || $_SESSION['role'] !== 'Employee') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$action = $input['action'] ?? ''; // 'clock_in' or 'clock_out'
$userLat = filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$userLng = filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$accuracy = filter_var($input['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);
$selfieBase64 = $input['selfie'] ?? '';

if (!in_array($action, ['clock_in', 'clock_out'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit();
}

if ($userLat === false || $userLng === false || !geofence_coords_are_valid($userLat,$userLng)) {
    echo json_encode(['success' => false, 'message' => 'Valid GPS location is required to verify attendance.']);
    exit();
}

if (empty($selfieBase64)) {
    echo json_encode(['success' => false, 'message' => 'A live selfie photo is mandatory for verification.']);
    exit();
}

try {
    $validation=geofence_validate_employee_position($conn,$userId,(float)$userLat,(float)$userLng);
    if (!empty($validation['site']['site_registered']) && !geofence_accuracy_is_acceptable($accuracy,$validation['site']['geofence_radius'])) {
        echo json_encode(['success'=>false,'message'=>'Location accuracy is too low. Refresh GPS or move to an area with better location signal.']); exit();
    }
    if (!$validation['allowed']) {
        echo json_encode(['success'=>false,'message'=>$validation['message']]); exit();
    }
    $site=$validation['site'];
    $empData=['EmployeeID'=>$site['employee_id'],'company_name'=>$site['company_name']];
    $distanceMeters=$validation['distance_meters'];
    $allowedRadius=$site['geofence_radius'];

    $deviceInfo = parseUserAgentDetails($_SERVER['HTTP_USER_AGENT'] ?? '');
    $todayDate = date('Y-m-d');
    $serverNow = date('Y-m-d H:i:s');
    $employeeId = (int)$empData['EmployeeID'];
    $expectedSchedule = payroll_resolve_deployment_schedule($conn, $employeeId, $todayDate);

    // Overnight attendance remains attached to the date on which the shift started.
    $previousDate = date('Y-m-d', strtotime($todayDate . ' -1 day'));
    $previous = team_schedule_query($conn,
        'SELECT AttendanceID, TimeIn, TimeOut FROM attendance WHERE EmployeeID = ? AND Date = ? AND TimeIn IS NOT NULL AND TimeOut IS NULL ORDER BY AttendanceID DESC LIMIT 1',
        'is', [$employeeId, $previousDate])->fetch_assoc();
    if ($previous) {
        $previousSchedule = payroll_resolve_deployment_schedule($conn, $employeeId, $previousDate);
        if ($previousSchedule['time_out'] < $previousSchedule['time_in']) {
            if ($action === 'clock_in') {
                echo json_encode(['success' => false, 'message' => 'Clock out of your previous overnight shift first.']);
                exit();
            }
            $todayDate = $previousDate;
            $expectedSchedule = $previousSchedule;
        }
    }

    // Never change a submitted day; corrections use the separate Add Back workflow.
    $conn->begin_transaction();
    $lock=$conn->prepare('SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE');
    $lock->bind_param('i',$employeeId); $lock->execute(); $lock->close();
    $locked=$conn->prepare('SELECT DTR_SubmissionID FROM dtr_submissions WHERE EmployeeID=? AND ? BETWEEN CutoffStartDate AND CutoffEndDate LIMIT 1');
    $locked->bind_param('is',$employeeId,$todayDate); $locked->execute();
    $lockedRow=$locked->get_result()->fetch_assoc(); $locked->close();
    if ($lockedRow) throw new DomainException('This day belongs to a submitted DTR. Submit an Add Back request for omitted duty.');

    $attCheck = $conn->prepare("SELECT AttendanceID, TimeIn, TimeOut FROM attendance WHERE EmployeeID = ? AND Date = ? LIMIT 1");
    $attCheck->bind_param("is", $employeeId, $todayDate);
    $attCheck->execute();
    $existingRecord = $attCheck->get_result()->fetch_assoc();
    $attCheck->close();

    if (($action==='clock_in' && !empty($existingRecord['TimeIn'])) || ($action==='clock_out' && (empty($existingRecord['TimeIn']) || !empty($existingRecord['TimeOut'])))) {
        throw new DomainException('This clock action is not available for the current attendance record.');
    }
    $photoPath = saveBase64Selfie($selfieBase64, $userId, $action);
    if (!$photoPath) {
        echo json_encode(['success' => false, 'message' => 'Failed to save selfie photo.']);
        exit();
    }

    if ($action === 'clock_in') {
        if ($existingRecord && !empty($existingRecord['TimeIn'])) {
            echo json_encode(['success' => false, 'message' => 'You have already clocked in for today (' . date('h:i A', strtotime($existingRecord['TimeIn'])) . ').']);
            exit();
        }

        $meta = payroll_timesheet_day_status(['time_in' => $serverNow], $todayDate, $expectedSchedule, $todayDate);
        $attendanceStatus = !empty($meta['is_late']) ? 'Late' : 'Present';
        $minutesLate = (int)($meta['late_minutes'] ?? 0);

        if ($existingRecord) {
            $updateSql = $conn->prepare("
                UPDATE attendance SET 
                    TimeIn = ?,
                    clockin_latitude = ?,
                    clockin_longitude = ?,
                    gps_accuracy = ?,
                    distance_from_site = ?,
                    clockin_photo = ?,
                    browser_name = ?,
                    operating_system = ?,
                    device_type = ?,
                    user_agent = ?,
                    geofence_status = 'Inside Geofence',
                    attendance_status = ?,
                    minutes_late = ?
                WHERE AttendanceID = ?
            ");
            $updateSql->bind_param(
                "sddddssssssii",
                $serverNow,
                $userLat,
                $userLng,
                $accuracy,
                $distanceMeters,
                $photoPath,
                $deviceInfo['browser'],
                $deviceInfo['os'],
                $deviceInfo['device'],
                $deviceInfo['user_agent'],
                $attendanceStatus,
                $minutesLate,
                $existingRecord['AttendanceID']
            );
            $updateSql->execute();
            $updateSql->close();
            $attId = (int)$existingRecord['AttendanceID'];
        } else {
            $insertSql = $conn->prepare("
                INSERT INTO attendance (
                    EmployeeID, Date, TimeIn, clockin_latitude, clockin_longitude,
                    gps_accuracy, distance_from_site, clockin_photo, browser_name,
                    operating_system, device_type, user_agent, geofence_status,
                    attendance_status, minutes_late
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Inside Geofence', ?, ?)
            ");
            $insertSql->bind_param(
                "issddddssssssi",
                $employeeId,
                $todayDate,
                $serverNow,
                $userLat,
                $userLng,
                $accuracy,
                $distanceMeters,
                $photoPath,
                $deviceInfo['browser'],
                $deviceInfo['os'],
                $deviceInfo['device'],
                $deviceInfo['user_agent'],
                $attendanceStatus,
                $minutesLate
            );
            $insertSql->execute();
            $attId = (int)$conn->insert_id;
            $insertSql->close();
        }

        $expectedSnapshot = $conn->prepare('UPDATE attendance SET scheduled_time_in = ?, scheduled_time_out = ? WHERE AttendanceID = ?');
        $expectedSnapshot->bind_param('ssi', $expectedSchedule['time_in'], $expectedSchedule['time_out'], $attId);
        $expectedSnapshot->execute(); $expectedSnapshot->close();

        $dtrCheck = $conn->prepare("SELECT id FROM daily_time_records WHERE employee_id = ? AND date = ? LIMIT 1");
        $dtrCheck->bind_param("is", $employeeId, $todayDate);
        $dtrCheck->execute();
        $dtrRes = $dtrCheck->get_result()->fetch_assoc();
        $dtrCheck->close();

        $dtrStatus = ($attendanceStatus === 'Late') ? 'Late' : 'Clocked In';
        if ($dtrRes) {
            $dtrUp = $conn->prepare("UPDATE daily_time_records SET time_in = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $dtrUp->bind_param("ssi", $serverNow, $dtrStatus, $dtrRes['id']);
            $dtrUp->execute();
            $dtrUp->close();
        } else {
            $dtrIns = $conn->prepare("INSERT INTO daily_time_records (employee_id, date, time_in, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $dtrIns->bind_param("isss", $employeeId, $todayDate, $serverNow, $dtrStatus);
            $dtrIns->execute();
            $dtrIns->close();
        }

        $evidenceDeployment=(int)($site['deployment_id'] ?? $expectedSchedule['deployment_id'] ?? 0);
        $depSnapshot=$conn->prepare('UPDATE attendance SET EvidenceDeploymentID=?,server_timestamp=? WHERE AttendanceID=?');
        $depSnapshot->bind_param('isi',$evidenceDeployment,$serverNow,$attId); $depSnapshot->execute(); $depSnapshot->close();
        $conn->commit();
        AuditLogger::log($conn, 'Attendance', 'Clock In', "Attendance recorded: Clocked In at {$empData['company_name']}.", (string)$attId);

        echo json_encode([
            'success' => true,
            'message' => "Clock In successful! Status: {$attendanceStatus} ({$distanceMeters}m from site).",
            'timestamp' => date('h:i:s A', strtotime($serverNow)),
            'distance' => $distanceMeters
        ]);

    } elseif ($action === 'clock_out') {
        if (!$existingRecord || empty($existingRecord['TimeIn'])) {
            echo json_encode(['success' => false, 'message' => 'You must clock in before clocking out.']);
            exit();
        }

        if (!empty($existingRecord['TimeOut'])) {
            echo json_encode(['success' => false, 'message' => 'You have already clocked out for today.']);
            exit();
        }

        $timeInTs = strtotime($existingRecord['TimeIn']);
        $timeOutTs = strtotime($serverNow);
        $diffHours = round(($timeOutTs - $timeInTs) / 3600, 2);

        $updateOut = $conn->prepare("
            UPDATE attendance SET 
                TimeOut = ?,
                clockout_latitude = ?,
                clockout_longitude = ?,
                clockout_photo = ?,
                TotalHours = ?
            WHERE AttendanceID = ?
        ");
        $updateOut->bind_param(
            "sddsdi",
            $serverNow,
            $userLat,
            $userLng,
            $photoPath,
            $diffHours,
            $existingRecord['AttendanceID']
        );
        $updateOut->execute();
        $updateOut->close();

        $dtrUpOut = $conn->prepare("UPDATE daily_time_records SET time_out = ?, total_hours_rendered = ?, status = 'Clocked Out', updated_at = NOW() WHERE employee_id = ? AND date = ?");
        $dtrUpOut->bind_param("sdis", $serverNow, $diffHours, $employeeId, $todayDate);
        $dtrUpOut->execute();
        $dtrUpOut->close();

        $conn->commit();
        AuditLogger::log($conn, 'Attendance', 'Clock Out', "Attendance recorded: Clocked Out at {$empData['company_name']}.", (string)$existingRecord['AttendanceID']);

        echo json_encode([
            'success' => true,
            'message' => "Clock Out successful! Verified {$distanceMeters}m from site.",
            'timestamp' => date('h:i:s A', strtotime($serverNow)),
            'distance' => $distanceMeters
        ]);
    }

} catch (DomainException $e) {
    $conn->rollback();
    http_response_code(409);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('clock_in_out_geofence exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'message' => 'Server exception: ' . $e->getMessage()]);
}
