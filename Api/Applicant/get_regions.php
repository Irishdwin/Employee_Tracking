<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
    $result = $conn->query("SELECT RegionID AS id, Name AS name FROM address_region_lookup ORDER BY Name ASC");
    $regions = [];
    while ($row = $result->fetch_assoc()) {
        $regions[] = [
            'id' => (int)$row['id'],
            'name' => $row['name']
        ];
    }
    echo json_encode(['success' => true, 'data' => $regions], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load regions.']);
}