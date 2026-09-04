<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

function json_resp($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $action = isset($input['action']) ? trim((string)$input['action']) : '';
    $notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;

    if ($action === 'mark_read' && $notificationId > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET IsRead = 1 WHERE NotificationID = ? AND UserID = ?");
        $stmt->bind_param("ii", $notificationId, $userId);
        $stmt->execute();
        $stmt->close();
        json_resp(['success' => true]);
    }

    if ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET IsRead = 1 WHERE UserID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        json_resp(['success' => true]);
    }

    json_resp(['success' => false, 'message' => 'Invalid action'], 400);
}

if ($method === 'GET') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
    $limit = max(1, min(100, $limit));
    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === '1';

    $sql = "
        SELECT NotificationID, NotificationType, Title, Message, RelateedModule, RelatedRecord, IsRead, Priority
        FROM notifications
        WHERE UserID = ?
    ";
    if ($unreadOnly) $sql .= " AND IsRead = 0 ";
    $sql .= " ORDER BY NotificationID DESC LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $type = (string)($row['NotificationType'] ?? '');
        $fromLabel = 'System';
        if ($type === 'interview_scheduled' || $type === 'application_status' || strpos($type, 'application_') === 0) $fromLabel = 'HR';
        if ($type === 'client_approved' || $type === 'client_rejected') $fromLabel = 'Client';
        if ($type === 'hr_message') $fromLabel = 'HR';
        if ($type === 'team_lead_company_assigned' || $type === 'company_now_active') $fromLabel = 'Admin';
        if ($type === 'service_agreement_uploaded') $fromLabel = 'System';

        $notifications[] = [
            'id' => (int)$row['NotificationID'],
            'type' => $row['NotificationType'],
            'title' => $row['Title'],
            'message' => $row['Message'],
            'from' => $fromLabel,
            'related_module' => $row['RelateedModule'],
            'related_record' => $row['RelatedRecord'] !== null ? (int)$row['RelatedRecord'] : null,
            'is_read' => (bool)$row['IsRead'],
            'priority' => $row['Priority'],
        ];
    }
    $stmt->close();

    // Unread count
    $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE UserID = ? AND IsRead = 0");
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $unreadCount = (int)$countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    json_resp(['success' => true, 'notifications' => $notifications, 'unread_count' => $unreadCount]);
}

json_resp(['success' => false, 'message' => 'Invalid request'], 400);

