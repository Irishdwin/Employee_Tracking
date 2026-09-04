<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['id']);

require_once __DIR__ . '/../Db_connection/db_connect.php';

try {
    // 1. Geofence check: Check if the Team Lead has registered a work site
    $geoCount = 0;
    $siteCheck = $conn->prepare("
        SELECT COUNT(*) as cnt 
        FROM clientcompany 
        WHERE (UserID = ? OR CompanyID IN (SELECT CompanyID FROM deployment WHERE SiteSuperVisorID = ?)) 
          AND (latitude IS NOT NULL OR registered_latitude IS NOT NULL)
    ");
    if ($siteCheck) {
        $siteCheck->bind_param("ii", $userId, $userId);
        $siteCheck->execute();
        $res = $siteCheck->get_result()->fetch_assoc();
        $siteCheck->close();
        if (($res['cnt'] ?? 0) == 0) {
            $anyCo = $conn->prepare("SELECT COUNT(*) as acnt FROM clientcompany WHERE UserID = ? OR CompanyID IN (SELECT CompanyID FROM deployment WHERE SiteSuperVisorID = ?)");
            if ($anyCo) {
                $anyCo->bind_param("ii", $userId, $userId);
                $anyCo->execute();
                $anyRes = $anyCo->get_result()->fetch_assoc();
                $anyCo->close();
                if (($anyRes['acnt'] ?? 0) > 0) {
                    $geoCount = 1;
                }
            } else {
                $geoCount = 1;
            }
        }
    }

    // 2. Job Order requests count (pending)
    $jobOrdersCount = 0;
    $joStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM job_order_requests WHERE RequestedBy = ? AND Status = 'Pending'");
    if ($joStmt) {
        $joStmt->bind_param("i", $userId);
        $joStmt->execute();
        $jobOrdersCount = (int)($joStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $joStmt->close();
    }

    // 3. Endorsed Applicants awaiting decision
    $applicantsCount = 0;
    $appStmt = $conn->prepare("
        SELECT COUNT(*) as cnt 
        FROM job_applications ja
        INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
        INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
        WHERE (c.UserID = ? OR c.CompanyID IN (SELECT CompanyID FROM deployment WHERE SiteSuperVisorID = ?))
          AND ja.ClientApprovalStatus = 'Pending'
    ");
    if ($appStmt) {
        $appStmt->bind_param("ii", $userId, $userId);
        $appStmt->execute();
        $applicantsCount = (int)($appStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $appStmt->close();
    }

    // 4. Unread notifications count
    $unreadCount = 0;
    $notifStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE UserID = ? AND IsRead = 0");
    if ($notifStmt) {
        $notifStmt->bind_param("i", $userId);
        $notifStmt->execute();
        $unreadCount = (int)($notifStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $notifStmt->close();
    }

    echo json_encode([
        'success' => true,
        'badges' => [
            'geofence' => $geoCount,
            'job_orders' => $jobOrdersCount,
            'applicants' => $applicantsCount
        ],
        'unread_notifications' => $unreadCount
    ]);
} catch (Throwable $e) {
    error_log('team_lead_counters exception: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'badges' => ['geofence' => 0, 'job_orders' => 0, 'applicants' => 0],
        'unread_notifications' => 0
    ]);
}