<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Helpers/chat_helper.php';
require __DIR__ . '/../Db_connection/db_connect.php';

chat_ensure_tables($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$threadId = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
if ($threadId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'thread_id is required']);
    exit();
}

// Verify membership
$memStmt = $conn->prepare("SELECT 1 FROM chat_thread_participants WHERE ThreadID = ? AND UserID = ? LIMIT 1");
$memStmt->bind_param("ii", $threadId, $userId);
$memStmt->execute();
$isMember = $memStmt->get_result()->num_rows > 0;
$memStmt->close();

if (!$isMember) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

// Mark read
chat_mark_thread_read($conn, $threadId, $userId);

$stmt = $conn->prepare("
    SELECT
        m.MessageID,
        m.SenderUserID,
        u.FullName AS sender_name,
        m.MessageText,
        m.IsSystemMessage,
        m.CreatedAt
    FROM chat_messages m
    INNER JOIN users u ON u.UsersID = m.SenderUserID
    WHERE m.ThreadID = ?
    ORDER BY m.CreatedAt ASC, m.MessageID ASC
    LIMIT 200
");
$stmt->bind_param("i", $threadId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'message_id' => (int)$row['MessageID'],
        'sender_user_id' => (int)$row['SenderUserID'],
        'sender_name' => $row['sender_name'] ?: ('User #' . (int)$row['SenderUserID']),
        'message_text' => $row['MessageText'],
        'is_system' => (int)$row['IsSystemMessage'] === 1,
        'created_at' => $row['CreatedAt'] ? (string)$row['CreatedAt'] : null,
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'messages' => $messages]);

