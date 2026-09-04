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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$messageText = trim($input['message_text'] ?? '');
$threadId = isset($input['thread_id']) ? (int)($input['thread_id'] ?? 0) : 0;
$contactChannel = strtolower(trim($input['contact_channel'] ?? ($input['channel'] ?? 'hr')));

if ($messageText === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message text is required']);
    exit();
}

try {
    if ($threadId > 0) {
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
        $participantUsers = [];
        $pstmt = $conn->prepare("SELECT UserID FROM chat_thread_participants WHERE ThreadID = ?");
        $pstmt->bind_param("i", $threadId);
        $pstmt->execute();
        $presult = $pstmt->get_result();
        while ($p = $presult->fetch_assoc()) $participantUsers[] = (int)$p['UserID'];
        $pstmt->close();
    } else {
        if ($contactChannel === 'admin') {
            $staffUsers = chat_get_active_users_by_role($conn, 'Admin');
            if (empty($staffUsers)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'No active Administrator account found.']);
                exit();
            }
            $participantUsers = [$userId];
            foreach ($staffUsers as $u) {
                $participantUsers[] = (int)$u['user_id'];
            }
            $participantUsers = array_values(array_unique($participantUsers));
            $threadId = chat_find_or_create_thread(
                $conn,
                'ADMIN_CONTACT',
                'admin_contact',
                $userId,
                $userId,
                $participantUsers
            );
        } else {
            $hrUsers = chat_get_active_users_by_role($conn, 'HR');
            if (empty($hrUsers)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'No active HR account found.']);
                exit();
            }

            $participantUsers = [];
            $participantUsers[] = $userId;
            foreach ($hrUsers as $u) {
                $participantUsers[] = (int)$u['user_id'];
            }
            $participantUsers = array_values(array_unique($participantUsers));

            $threadId = chat_find_or_create_thread(
                $conn,
                'HR_CONTACT',
                'hr_contact',
                $userId,
                $userId,
                $participantUsers
            );
        }
    }

    $displaySender = chat_get_user_display($conn, $userId);

    $msgId = chat_add_message($conn, $threadId, $userId, $messageText, false);

    // Mark sender as read
    chat_mark_thread_read($conn, $threadId, $userId);

    // Notify recipients (exclude sender)
    $recipients = array_values(array_filter($participantUsers, function ($x) use ($userId) { return (int)$x !== $userId; }));
    foreach ($recipients as $rid) {
        chat_create_notification(
            $conn,
            (int)$rid,
            'hr_message',
            'New message from ' . $displaySender,
            $messageText,
            'chat_thread',
            $threadId,
            null
        );
    }

    echo json_encode(['success' => true, 'thread_id' => $threadId, 'message_id' => $msgId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $e->getMessage()]);
}

