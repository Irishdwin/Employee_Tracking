<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

$provinceId = isset($_GET['province_id']) ? (int)$_GET['province_id'] : 0;
if ($provinceId <= 0) {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
    $stmt = $conn->prepare("SELECT CityID AS id, ProvinceID AS province_id, Name AS name FROM address_city_lookup WHERE ProvinceID = ? ORDER BY Name ASC");
    $stmt->bind_param("i", $provinceId);
    $stmt->execute();
    $res = $stmt->get_result();
    $cities = [];
    while ($row = $res->fetch_assoc()) {
        $cities[] = [
            'id' => (int)$row['id'],
            'province_id' => (int)$row['province_id'],
            'name' => $row['name']
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'data' => $cities], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load cities.']);
}