<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

$cityId = isset($_GET['city_id']) ? (int)$_GET['city_id'] : 0;
if ($cityId <= 0) {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
    $stmt = $conn->prepare("SELECT BarangayID AS id, CityID AS city_id, Name AS name FROM address_barangay_lookup WHERE CityID = ? ORDER BY Name ASC");
    $stmt->bind_param("i", $cityId);
    $stmt->execute();
    $res = $stmt->get_result();
    $barangays = [];
    while ($row = $res->fetch_assoc()) {
        $barangays[] = [
            'id' => (int)$row['id'],
            'city_id' => (int)$row['city_id'],
            'name' => $row['name']
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'data' => $barangays], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load barangays.']);
}