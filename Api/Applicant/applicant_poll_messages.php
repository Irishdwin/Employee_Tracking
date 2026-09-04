<?php
/**
 * Lightweight poll for applicants: total unread + latest inbound message preview (for global toast).
 */
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strcasecmp((string)$_SESSION['role'], 'Applicant') !== 0) {
    echo json_encode(['success' => false, 'logged_in' => false]);
    exit();
}

require __DIR__ . '/../Helpers/chat_helper.php';
require __DIR__ . '/../Db_connection/db_connect.php';

chat_ensure_tables($conn);

$userId = (int)$_SESSION['user_id'];

$sql = "
    SELECT COUNT(*) AS cnt
    FROM chat_messages m
    INNER JOIN chat_thread_participants p ON p.ThreadID = m.ThreadID AND p.UserID = ?
    WHERE m.SenderUserID <> ?
      AND m.CreatedAt > COALESCE(
          (SELECT r.LastReadAt FROM chat_thread_reads r WHERE r.ThreadID = m.ThreadID AND r.UserID = ? LIMIT 1),
          '1970-01-01'
      )
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $userId, $userId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalUnread = isset($row['cnt']) ? (int)$row['cnt'] : 0;

$preview = null;
$stmt2 = $conn->prepare("
    SELECT m.MessageID, m.MessageText, m.CreatedAt, m.SenderUserID, u.FullName AS sender_name,
           t.ThreadType
    FROM chat_messages m
    INNER JOIN chat_threads t ON t.ThreadID = m.ThreadID
    INNER JOIN chat_thread_participants p ON p.ThreadID = m.ThreadID AND p.UserID = ?
    INNER JOIN users u ON u.UsersID = m.SenderUserID
    WHERE m.SenderUserID <> ?
    ORDER BY m.CreatedAt DESC, m.MessageID DESC
    LIMIT 1
");
$stmt2->bind_param('ii', $userId, $userId);
$stmt2->execute();
$last = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

if ($last) {
    $tt = $last['ThreadType'] ?? '';
    $ch = $tt === 'ADMIN_CONTACT' ? 'admin' : ($tt === 'HR_CONTACT' ? 'hr' : 'other');
    $preview = [
        'message_id' => (int)$last['MessageID'],
        'sender_name' => $last['sender_name'] ?? '',
        'preview' => function_exists('mb_substr')
            ? mb_substr((string)$last['MessageText'], 0, 160)
            : substr((string)$last['MessageText'], 0, 160),
        'thread_type' => $tt,
        'channel' => $ch,
    ];
}

echo json_encode([
    'success' => true,
    'total_unread' => $totalUnread,
    'preview' => $preview,
]);
