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

    // NOTE (fixed): this previously created/queried a table shape
    // (`Type`, `CreatedAt`) that never matched the ACTUAL production
    // `notifications` table (real columns: NotificationType; there is no
    // created-at/timestamp column at all — see onazul_hris_db.sql and the
    // real writers Api/Common/NotificationHelper.php /
    // Api/Helpers/notification_helper.php, both of which insert
    // UserID/NotificationType/Title/Message/RelateedModule/RelatedRecord/
    // IsRead/Priority). Every call here failed at prepare() and was caught
    // below, returning an empty list even when unread notifications existed.
    // This endpoint is no longer used by team_lead_dashboard.html (it now
    // calls the shared Api/Common/notifications.php, matching every other
    // role), but is corrected here too rather than left broken.
    $conn->query("
        CREATE TABLE IF NOT EXISTS `notifications` (
          `NotificationID` INT(11) NOT NULL AUTO_INCREMENT,
          `UserID` INT(11) NOT NULL,
          `NotificationType` VARCHAR(50) DEFAULT NULL,
          `Title` VARCHAR(200) DEFAULT NULL,
          `Message` TEXT,
          `RelateedModule` VARCHAR(100) DEFAULT NULL,
          `RelatedRecord` INT(11) DEFAULT NULL,
          `IsRead` TINYINT(1) DEFAULT 0,
          `Priority` VARCHAR(20) DEFAULT NULL,
          PRIMARY KEY (`NotificationID`),
          KEY `idx_user_read_type` (`UserID`, `IsRead`, `NotificationType`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

    $sql = "
        SELECT NotificationID, Title, Message, NotificationType, IsRead
        FROM notifications 
        WHERE UserID = ? 
        ORDER BY NotificationID DESC 
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
            'type' => $row['NotificationType'],
            'is_read' => (bool)$row['IsRead'],
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