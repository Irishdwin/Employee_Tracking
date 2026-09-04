<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode([
            'success' => false,
            'message' => 'PHP Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ' on line ' . $error['line']
        ]);
    }
});

session_start();

function respond($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    respond(false, 'User session expired. Please log in again.');
}

$userId = $_SESSION['user_id'] ?? $_SESSION['id'];

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    respond(false, 'Invalid JSON payload received.');
}

$latitude  = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$radius    = isset($data['geofence_radius']) ? intval($data['geofence_radius']) : 100;

if ($latitude === false || $longitude === false) {
    respond(false, 'Valid GPS coordinates were not provided.');
}

$radius = max(30, min(1000, $radius));

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
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
    }
}

if (!$dbLoaded) {
    respond(false, 'Could not find a valid DB connection file.');
}

if (!isset($conn) && isset($db)) { $conn = $db; }
if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
if (!isset($conn) && isset($link)) { $conn = $link; }

if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_error)) {
    respond(false, 'Database connection failed.');
}

try {
    $colCheck = $conn->query("SHOW COLUMNS FROM clientcompany LIKE 'latitude'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE clientcompany ADD COLUMN latitude DECIMAL(10, 8) NULL");
    }

    $colCheckLong = $conn->query("SHOW COLUMNS FROM clientcompany LIKE 'longitude'");
    if ($colCheckLong && $colCheckLong->num_rows === 0) {
        $conn->query("ALTER TABLE clientcompany ADD COLUMN longitude DECIMAL(11, 8) NULL");
    }

    $colCheckRegLat = $conn->query("SHOW COLUMNS FROM clientcompany LIKE 'registered_latitude'");
    if ($colCheckRegLat && $colCheckRegLat->num_rows === 0) {
        $conn->query("ALTER TABLE clientcompany ADD COLUMN registered_latitude DECIMAL(10, 8) NULL");
    }

    $colCheckRegLng = $conn->query("SHOW COLUMNS FROM clientcompany LIKE 'registered_longitude'");
    if ($colCheckRegLng && $colCheckRegLng->num_rows === 0) {
        $conn->query("ALTER TABLE clientcompany ADD COLUMN registered_longitude DECIMAL(11, 8) NULL");
    }

    $colCheckRad = $conn->query("SHOW COLUMNS FROM clientcompany LIKE 'geofence_radius'");
    if ($colCheckRad && $colCheckRad->num_rows === 0) {
        $conn->query("ALTER TABLE clientcompany ADD COLUMN geofence_radius INT DEFAULT 100");
    }
} catch (\Throwable $e) {}

$companyId = null;

try {
    $stmt = $conn->prepare("SELECT CompanyID FROM clientcompany WHERE UserID = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($fetchedCompanyId);
        if ($stmt->fetch()) {
            $companyId = $fetchedCompanyId;
        }
        $stmt->close();
    }
} catch (\Throwable $e) {}

try {
    if (!$companyId) {
        $stmtDep = $conn->prepare("SELECT CompanyID FROM deployment WHERE SiteSuperVisorID = ? LIMIT 1");
        if ($stmtDep) {
            $stmtDep->bind_param("i", $userId);
            $stmtDep->execute();
            $stmtDep->bind_result($depCompanyId);
            if ($stmtDep->fetch()) {
                $companyId = $depCompanyId;
            }
            $stmtDep->close();
        }
    }
} catch (\Throwable $e) {}

// Never fall back to an arbitrary company record. A work-site GPS
// registration must belong to the logged-in Team Lead's actual company
// or to a deployment assigned to that Team Lead.
if (!$companyId) {
    respond(false, 'No client company is assigned to this Team Lead. Work-site registration was not saved.');
}

try {
    $success = false;
    if ($companyId) {
        // Write to both latitude/longitude and registered_latitude/registered_longitude to eliminate column desynchronization
        $updateStmt = $conn->prepare("UPDATE clientcompany SET latitude = ?, longitude = ?, registered_latitude = ?, registered_longitude = ?, geofence_radius = ?, site_registered_at = NOW(), registered_by_tl_id = ? WHERE CompanyID = ?");
        if ($updateStmt) {
            $updateStmt->bind_param("ddddiii", $latitude, $longitude, $latitude, $longitude, $radius, $userId, $companyId);
            if ($updateStmt->execute()) {
                $success = true;
            }
            $updateStmt->close();
        }
    }

    if (!$success) {
        $updateStmt2 = $conn->prepare("UPDATE clientcompany SET latitude = ?, longitude = ?, registered_latitude = ?, registered_longitude = ?, geofence_radius = ?, site_registered_at = NOW(), registered_by_tl_id = ? LIMIT 1");
        if ($updateStmt2) {
            $updateStmt2->bind_param("ddddii", $latitude, $longitude, $latitude, $longitude, $radius, $userId);
            if ($updateStmt2->execute()) {
                $success = true;
            }
            $updateStmt2->close();
        }
    }

    if ($success) {
        respond(true, "Work site location registered successfully! Allowed radius: {$radius}m.");
    } else {
        respond(false, 'Failed to update work site coordinates in database.');
    }
} catch (\Throwable $e) {
    respond(false, 'Database query error: ' . $e->getMessage());
}