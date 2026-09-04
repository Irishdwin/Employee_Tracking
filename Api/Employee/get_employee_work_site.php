<?php
require_once __DIR__ . '/../Common/session_init.php';
date_default_timezone_set('Asia/Manila');
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/work_site_geofence.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Employee') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$site = work_site_resolve_for_user($conn, $userId);

if (!$site) {
    echo json_encode([
        'success' => false,
        'message' => 'No active deployment work site found.',
        'work_site' => null,
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'work_site' => [
        'company_id' => $site['company_id'],
        'company_name' => $site['company_name'],
        'deployment_location' => $site['deployment_location'],
        'team_lead_name' => $site['team_lead_name'],
        'latitude' => $site['site_latitude'],
        'longitude' => $site['site_longitude'],
        'geofence_radius' => $site['geofence_radius'],
        'is_registered' => (bool)$site['site_registered'],
        'coordinate_source' => $site['coordinate_source'],
    ],
]);
exit();
