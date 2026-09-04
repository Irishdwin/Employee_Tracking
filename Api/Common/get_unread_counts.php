<?php
/**
 * GET — total unread messages + per-sender breakdown for the logged-in user.
 */
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/chat_helper.php';
require_once __DIR__ . '/../Helpers/messenger_unread.php';

chat_ensure_tables($conn);

$viewerId = (int)$_SESSION['user_id'];
$counts = messenger_get_unread_counts($conn, $viewerId);

$byContact = [];
foreach ($counts['by_contact'] as $senderId => $n) {
    $byContact[(string)$senderId] = (int)$n;
}

echo json_encode([
    'success' => true,
    'total' => (int)$counts['total'],
    'by_contact' => $byContact,
    'byContact' => $byContact,
    'sender_id' => $byContact,
]);
