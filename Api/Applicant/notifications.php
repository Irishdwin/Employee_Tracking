<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'redirect' => '../../index.html']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? trim($input['action']) : '';
    $notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;

    if ($action === 'mark_read' && $notificationId > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET IsRead = 1 WHERE NotificationID = ? AND UserID = ?");
        $stmt->bind_param("ii", $notificationId, $userId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit();
    }
    if ($action === 'mark_all_read') {
        $conn->query("UPDATE notifications SET IsRead = 1 WHERE UserID = $userId");
        echo json_encode(['success' => true]);
        exit();
    }
}

$limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === '1';

$sql = "
    SELECT NotificationID, NotificationType, Title, Message, IsRead, RelatedRecord
    FROM notifications
    WHERE UserID = ?
";
if ($unreadOnly) {
    $sql .= " AND IsRead = 0";
}
$sql .= " ORDER BY NotificationID DESC LIMIT ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $limit);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => (int)$row['NotificationID'],
        'type' => $row['NotificationType'],
        'title' => $row['Title'],
        'message' => $row['Message'],
        'is_read' => (bool)$row['IsRead'],
        'application_id' => (int)($row['RelatedRecord'] ?? 0)
    ];
}
$stmt->close();

$countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE UserID = ? AND IsRead = 0");
$countStmt->bind_param("i", $userId);
$countStmt->execute();
$unreadCount = (int)$countStmt->get_result()->fetch_assoc()['c'];
$countStmt->close();

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount
]);
