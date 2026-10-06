<?php
require_once __DIR__.'/session_init.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Helpers/chat_helper.php';
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/messenger_rbac.php';
try{$actor=messenger_actor($conn);}catch(Throwable $e){messenger_api_error($e);exit;}


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

try{$authorizedPeers=messenger_require_thread($conn,$actor,$threadId);}catch(Throwable $e){messenger_api_error($e);exit;}

// Mark read


$senderIds=implode(',',array_merge([$userId],array_map('intval',array_column($authorizedPeers,'user_id'))));
$before=max(0,(int)($_GET['before_message_id']??0));$older=$before>0?' AND m.MessageID<'.$before:'';
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
    WHERE m.ThreadID = ? AND m.SenderUserID IN ($senderIds) $older
    ORDER BY m.MessageID DESC
    LIMIT 201
");
$stmt->bind_param("i", $threadId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    if((int)$row['SenderUserID']!==$userId&&!in_array((int)$row['SenderUserID'],array_map('intval',array_column($authorizedPeers,'user_id')),true))continue;
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

$hasMore=count($messages)>200;if($hasMore)array_pop($messages);
if($messages)chat_mark_thread_read($conn,$threadId,$userId,max(array_column($messages,'message_id')));
require_once __DIR__.'/../Helpers/messenger_unread.php';
$unread=messenger_get_unread_counts($conn,$userId);
echo json_encode(['read_confirmed'=>true,'unread'=>$unread,'success'=>true,'messages'=>array_reverse($messages),'has_more'=>$hasMore,'oldest_message_id'=>$messages?min(array_column($messages,'message_id')):null]);

