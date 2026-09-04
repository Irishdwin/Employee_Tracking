<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

function respond($success, $data = []) {
    echo json_encode(array_merge(['success' => $success], $data));
    exit;
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    respond(false, ['message' => 'User session expired.']);
}

$userId = $_SESSION['user_id'] ?? $_SESSION['id'];

// Database Connection
$dbLoaded = false;
$searchFolders = [
    __DIR__ . '/../Db_connection',
    __DIR__ . '/../config',
    __DIR__ . '/../Config',
    __DIR__ . '/../../Config',
];

foreach ($searchFolders as $folder) {
    if (is_dir($folder)) {
        $files = glob($folder . '/*.php');
        if (!empty($files)) {
            foreach ($files as $file) {
                try {
                    require_once $file;
                    if (isset($conn) || isset($db) || isset($mysqli) || isset($link)) {
                        $dbLoaded = true;
                        break 2;
                    }
                } catch (\Throwable $e) { continue; }
            }
        }
    }
}

if (!isset($conn) && isset($db)) { $conn = $db; }
if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
if (!isset($conn) && isset($link)) { $conn = $link; }

if (!$dbLoaded || !isset($conn)) {
    respond(false, ['message' => 'Database connection failed.']);
}

// Fetch Company Site Data specifically scoped to Team Lead with robust coalescing
try {
    $stmt = $conn->prepare("
        SELECT COALESCE(latitude, registered_latitude) AS lat, 
               COALESCE(longitude, registered_longitude) AS lng, 
               COALESCE(geofence_radius, 100) AS radius 
        FROM clientcompany 
        WHERE UserID = ? OR CompanyID IN (SELECT CompanyID FROM deployment WHERE SiteSuperVisorID = ?)
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            respond(true, [
                'latitude' => $row['lat'] !== null ? (float)$row['lat'] : null,
                'longitude' => $row['lng'] !== null ? (float)$row['lng'] : null,
                'geofence_radius' => $row['radius'] ? (int)$row['radius'] : 100
            ]);
        }
        $stmt->close();
    }

    $stmt2 = $conn->query("SELECT COALESCE(latitude, registered_latitude) AS lat, COALESCE(longitude, registered_longitude) AS lng, COALESCE(geofence_radius, 100) AS radius FROM clientcompany WHERE latitude IS NOT NULL OR registered_latitude IS NOT NULL LIMIT 1");
    if ($stmt2 && $row = $stmt2->fetch_assoc()) {
        respond(true, [
            'latitude' => $row['lat'] !== null ? (float)$row['lat'] : null,
            'longitude' => $row['lng'] !== null ? (float)$row['lng'] : null,
            'geofence_radius' => $row['radius'] ? (int)$row['radius'] : 100
        ]);
    }

    respond(false, ['message' => 'No registered work site found.']);
} catch (\Throwable $e) {
    respond(false, ['message' => $e->getMessage()]);
}