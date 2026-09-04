<?php
require_once __DIR__ . '/session_init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

function json_resp(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_resp(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$createSql = "
    CREATE TABLE IF NOT EXISTS work_schedule_lookup (
        WorkScheduleID INT(11) NOT NULL AUTO_INCREMENT,
        Name VARCHAR(100) NOT NULL,
        SortOrder INT(11) NOT NULL DEFAULT 0,
        IsActive TINYINT(1) NOT NULL DEFAULT 1,
        CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (WorkScheduleID),
        UNIQUE KEY uniq_work_schedule_name (Name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($createSql)) {
    json_resp(['success' => false, 'message' => 'Failed to initialize work schedule lookup table.'], 500);
}

$defaults = [
    [10, 'Mon-Fri 8AM-5PM'],
    [20, 'Mon-Sat 8AM-5PM'],
    [30, 'Morning Shift (6AM-2PM)'],
    [40, 'Mid Shift (2PM-10PM)'],
    [50, 'Night Shift (10PM-6AM)'],
    [60, 'Rotating'],
    [70, 'Flexible'],
];

$seedStmt = $conn->prepare('INSERT IGNORE INTO work_schedule_lookup (SortOrder, Name) VALUES (?, ?)');
if ($seedStmt) {
    foreach ($defaults as $row) {
        $order = (int)$row[0];
        $name = (string)$row[1];
        $seedStmt->bind_param('is', $order, $name);
        $seedStmt->execute();
    }
    $seedStmt->close();
}

$items = [];
$res = $conn->query('SELECT WorkScheduleID, Name FROM work_schedule_lookup WHERE IsActive = 1 ORDER BY SortOrder ASC, Name ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int)($row['WorkScheduleID'] ?? 0),
            'name' => (string)($row['Name'] ?? ''),
        ];
    }
}

json_resp(['success' => true, 'schedules' => $items]);
