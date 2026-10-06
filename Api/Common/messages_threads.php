<?php
require_once __DIR__.'/session_init.php';

header('Content-Type: application/json');

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

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$stmt = $conn->prepare("
    SELECT
        t.ThreadID,
        t.ThreadType,
        t.RelatedModule,
        t.RelatedRecord,
        (
            SELECT m.MessageText
            FROM chat_messages m
            WHERE m.ThreadID = t.ThreadID
            ORDER BY m.CreatedAt DESC
            LIMIT 1
        ) AS last_message_text,
        (
            SELECT m.CreatedAt
            FROM chat_messages m
            WHERE m.ThreadID = t.ThreadID
            ORDER BY m.CreatedAt DESC
            LIMIT 1
        ) AS last_created_at,
        (
            SELECT u2.FullName
            FROM chat_thread_participants p2
            INNER JOIN users u2 ON u2.UsersID = p2.UserID
            WHERE p2.ThreadID = t.ThreadID AND p2.UserID <> ?
            ORDER BY u2.FullName ASC
            LIMIT 1
        ) AS other_user_name,
        (
            SELECT COUNT(*)
            FROM chat_messages m2
            WHERE m2.ThreadID = t.ThreadID
              AND m2.SenderUserID <> $userId AND m2.MessageID > COALESCE(
                    (
                        SELECT r2.LastReadMessageID
                        FROM chat_thread_reads r2
                        WHERE r2.ThreadID = t.ThreadID AND r2.UserID = ?
                    ),
                    0
              )
        ) AS unread_count
    FROM chat_threads t
    INNER JOIN chat_thread_participants p ON p.ThreadID = t.ThreadID
    WHERE p.UserID = ?
    ORDER BY last_created_at DESC, t.ThreadID DESC
    LIMIT 50
");
$stmt->bind_param("iii", $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$threads = [];
while ($row = $result->fetch_assoc()) {
    try{$peers=messenger_require_thread($conn,$actor,(int)$row['ThreadID']);}catch(DomainException $e){continue;}
    $peerIds=array_map('intval',array_column($peers,'user_id'));$idsSql=implode(',',$peerIds);$id=(int)$row['ThreadID'];
    $last=site_query($conn,"SELECT MessageText,CreatedAt FROM chat_messages WHERE ThreadID=? AND SenderUserID IN ($userId,$idsSql) ORDER BY MessageID DESC LIMIT 1",'i',[$id])->fetch_assoc();
    $row['last_message_text']=$last['MessageText']??null;$row['last_created_at']=$last['CreatedAt']??null;
    $row['other_user_name']=$peers[0]['full_name'];
    $row['unread_count']=(int)site_query($conn,"SELECT COUNT(*) n FROM chat_messages m WHERE m.ThreadID=? AND m.SenderUserID IN ($idsSql) AND m.MessageID>COALESCE((SELECT LastReadMessageID FROM chat_thread_reads WHERE ThreadID=? AND UserID=?),0)",'iii',[$id,$id,$userId])->fetch_assoc()['n'];
    $tt = $row['ThreadType'] ?? '';
    $channel = 'other';
    $label = $tt;
    if ($tt === 'ADMIN_CONTACT') {
        $channel = 'admin';
        $label = 'Administrators';
    } elseif ($tt === 'HR_CONTACT') {
        $channel = 'hr';
        $label = 'HR';
    }
    $threads[] = [
        'thread_id' => (int)$row['ThreadID'],
        'thread_type' => $tt,
        'channel' => $channel,
        'conversation_label' => $label,
        'related_module' => $row['RelatedModule'],
        'related_record' => $row['RelatedRecord'] !== null ? (int)$row['RelatedRecord'] : null,
        'other_user_name' => $row['other_user_name'] ?: null,
        'last_message_text' => $row['last_message_text'] ?: null,
        'last_created_at' => $row['last_created_at'] ?: null,
        'unread_count' => (int)$row['unread_count'],
    ];
}
$stmt->close();

$_SESSION['messenger_csrf']??=bin2hex(random_bytes(32));
echo json_encode(['success'=>true,'threads'=>$threads,'csrf_token'=>$_SESSION['messenger_csrf']]);
