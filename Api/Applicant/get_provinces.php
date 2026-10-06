<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

// region_id is optional so any existing caller that expects "all provinces"
// keeps working unchanged; the new Region -> Province cascade passes it.
$regionId = isset($_GET['region_id']) ? (int)$_GET['region_id'] : 0;

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
    if ($regionId > 0) {
        $stmt = $conn->prepare("SELECT ProvinceID AS id, RegionID AS region_id, Name AS name FROM address_province_lookup WHERE RegionID = ? ORDER BY Name ASC");
        $stmt->bind_param("i", $regionId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT ProvinceID AS id, RegionID AS region_id, Name AS name FROM address_province_lookup ORDER BY Name ASC");
    }
    $provinces = [];
    while ($row = $result->fetch_assoc()) {
        $provinces[] = [
            'id' => (int)$row['id'],
            'region_id' => isset($row['region_id']) ? (int)$row['region_id'] : null,
            'name' => $row['name']
        ];
    }
    if (isset($stmt)) { $stmt->close(); }
    echo json_encode(['success' => true, 'data' => $provinces], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load provinces.']);
}