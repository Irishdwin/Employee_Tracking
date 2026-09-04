<?php
date_default_timezone_set('Asia/Manila');
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/geofence_helper.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

if (!isset($_SESSION['user_id'])) {
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

if ($userLat === false || $userLng === false || $userLat === 0.0 || $userLng === 0.0) {
    echo json_encode(['success' => false, 'message' => 'Valid GPS location is required to verify attendance.']);
    exit();
}

if (empty($selfieBase64)) {
    echo json_encode(['success' => false, 'message' => 'A live selfie photo is mandatory for verification.']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT e.EmployeeID, d.CompanyID, 
               COALESCE(c.registered_latitude, c.latitude, direct_c.registered_latitude, direct_c.latitude) as site_lat, 
               COALESCE(c.registered_longitude, c.longitude, direct_c.registered_longitude, direct_c.longitude) as site_lng, 
               COALESCE(c.geofence_radius, direct_c.geofence_radius, 100) as geofence_radius, 
               COALESCE(c.Name, direct_c.Name, 'Assigned Site') as company_name,
               d.TimeIn as scheduled_time_in, d.TimeOut as scheduled_time_out, d.GracePeriod as grace_period,
               tl.FullName as team_lead_name
        FROM users u
        JOIN employee e ON u.Email = e.Email
        LEFT JOIN deployment d ON e.EmployeeID = d.EmployeeID AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        LEFT JOIN clientcompany c ON d.CompanyID = c.CompanyID
        LEFT JOIN users tl ON d.SiteSuperVisorID = tl.UsersID
        LEFT JOIN clientcompany direct_c ON direct_c.UserID = d.SiteSuperVisorID
        WHERE u.UsersID = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $empData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$empData || empty($empData['CompanyID'])) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to an active deployment site.']);
        exit();
    }

    $siteLat = $empData['site_lat'];
    $siteLng = $empData['site_lng'];
    $allowedRadius = (float)$empData['geofence_radius'];

    if ($siteLat === null || $siteLng === null ||
        !is_numeric($siteLat) || !is_numeric($siteLng) ||
        (float)$siteLat === 0.0 || (float)$siteLng === 0.0 ||
        abs((float)$siteLat) > 90 || abs((float)$siteLng) > 180) {
        echo json_encode([
            'success' => false,
            'message' => 'Your Team Leader has not registered valid official Work Site coordinates yet.'
        ]);
        exit();
    }

    if ($allowedRadius <= 0) {
        $allowedRadius = 100;
    }

    $distanceMeters = calculateHaversineDistance($siteLat, $siteLng, $userLat, $userLng);

    if ($distanceMeters > $allowedRadius) {
        echo json_encode([
            'success' => false,
            'message' => "You are outside the allowed work site. (Distance: {$distanceMeters}m, Max Radius: {$allowedRadius}m)"
        ]);
        exit();
    }

    $photoPath = saveBase64Selfie($selfieBase64, $userId, $action);
    if (!$photoPath) {
        echo json_encode(['success' => false, 'message' => 'Failed to save selfie photo.']);
        exit();
    }

    $deviceInfo = parseUserAgentDetails($_SERVER['HTTP_USER_AGENT'] ?? '');
    $todayDate = date('Y-m-d');
    $serverNow = date('Y-m-d H:i:s');
    $employeeId = (int)$empData['EmployeeID'];

    $attCheck = $conn->prepare("SELECT AttendanceID, TimeIn, TimeOut FROM attendance WHERE EmployeeID = ? AND Date = ? LIMIT 1");
    $attCheck->bind_param("is", $employeeId, $todayDate);
    $attCheck->execute();
    $existingRecord = $attCheck->get_result()->fetch_assoc();
    $attCheck->close();

    if ($action === 'clock_in') {
        if ($existingRecord && !empty($existingRecord['TimeIn'])) {
            echo json_encode(['success' => false, 'message' => 'You have already clocked in for today (' . date('h:i A', strtotime($existingRecord['TimeIn'])) . ').']);
            exit();
        }

        $schedTimeIn = $empData['scheduled_time_in'] ?? '08:00:00';
        $gracePeriod = (int)($empData['grace_period'] ?? 15);

        $schedDateTime = new DateTime($todayDate . ' ' . $schedTimeIn);
        $graceDateTime = clone $schedDateTime;
        $graceDateTime->modify("+{$gracePeriod} minutes");
        $actualDateTime = new DateTime($serverNow);

        $attendanceStatus = 'Present';
        $minutesLate = 0;
        if ($actualDateTime > $graceDateTime) {
            $attendanceStatus = 'Late';
            $diffSecs = $actualDateTime->getTimestamp() - $schedDateTime->getTimestamp();
            $minutesLate = (int)round($diffSecs / 60);
        }

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
                "sddddsssssiii",
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
                "issddddsssssii",
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
            "sdddsi",
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

        AuditLogger::log($conn, 'Attendance', 'Clock Out', "Attendance recorded: Clocked Out at {$empData['company_name']}.", (string)$existingRecord['AttendanceID']);

        echo json_encode([
            'success' => true,
            'message' => "Clock Out successful! Verified {$distanceMeters}m from site.",
            'timestamp' => date('h:i:s A', strtotime($serverNow)),
            'distance' => $distanceMeters
        ]);
    }

} catch (Throwable $e) {
    error_log('clock_in_out_geofence exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'message' => 'Server exception: ' . $e->getMessage()]);
}