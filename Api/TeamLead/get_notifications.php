<?php
require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// Session check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 15;

try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Auto-create notifications table if missing
    $conn->query("
        CREATE TABLE IF NOT EXISTS `notifications` (
          `NotificationID` INT(11) NOT NULL AUTO_INCREMENT,
          `UserID` INT(11) NOT NULL,
          `Title` VARCHAR(255) NOT NULL,
          `Message` TEXT NOT NULL,
          `Type` VARCHAR(50) NOT NULL DEFAULT 'general',
          `ReferenceID` INT(11) DEFAULT NULL,
          `IsRead` TINYINT(1) NOT NULL DEFAULT 0,
          `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`NotificationID`),
          KEY `idx_user_read_type` (`UserID`, `IsRead`, `Type`),
          KEY `idx_created_at` (`CreatedAt`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $sql = "
        SELECT NotificationID, Title, Message, Type, IsRead, CreatedAt 
        FROM notifications 
        WHERE UserID = ? 
        ORDER BY CreatedAt DESC 
        LIMIT " . (int)$limit;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Query prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['NotificationID'],
            'title' => $row['Title'],
            'message' => $row['Message'],
            'type' => $row['Type'],
            'is_read' => (bool)$row['IsRead'],
            'created_at' => date('M d, Y h:i A', strtotime($row['CreatedAt'])),
            'raw_time' => $row['CreatedAt']
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'notifications' => $items
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'notifications' => []
    ]);
}
exit();
?>