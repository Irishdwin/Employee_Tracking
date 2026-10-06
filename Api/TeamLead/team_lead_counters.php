<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if(!in_array($_SESSION['role']??'', ['TeamLead','Team Lead'],true)){http_response_code(403);echo json_encode(['success'=>false]);exit;}
$userId = (int)($_SESSION['user_id'] ?? $_SESSION['id']);

require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/work_site_geofence.php';

require_once __DIR__ . '/../Helpers/work_site_geofence.php';
try {
    $obligations=site_dtr_sync_notifications($conn,$userId);
    // Warn when ANY authorized current site lacks a complete valid coordinate pair.
    $geoCount=0;
    foreach(work_site_companies_for_team_lead($conn,$userId) as $site){
        if(geofence_pick_coordinates($site,'dep')===null)++$geoCount;
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

    // New endorsements only; viewing the list clears these notifications.
    $applicantsCount = 0;
    $appStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE UserID = ? AND IsRead = 0 AND NotificationType = 'endorsed_applicant'");
    $appStmt->bind_param('i', $userId);
    $appStmt->execute();
    $applicantsCount = (int)($appStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $appStmt->close();

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
            'applicants' => $applicantsCount,
            'dtr'=>count($obligations)
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