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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

if(!is_string($input['message_text']??null)){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Enter valid message text.']);exit;}
$messageText=trim($input['message_text']);
$threadId = isset($input['thread_id']) ? (int)($input['thread_id'] ?? 0) : 0;
$contactChannel = strtolower(trim($input['contact_channel'] ?? ($input['channel'] ?? 'hr')));

if ($messageText === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message text is required']);
    exit();
}

try {
    if(isset($_SERVER['HTTP_ORIGIN'])&&parse_url($_SERVER['HTTP_ORIGIN'],PHP_URL_HOST)!==explode(':',$_SERVER['HTTP_HOST']??'')[0])throw new DomainException('Invalid request origin.',403);
    if (mb_strlen($messageText)>5000)throw new DomainException('Messages must be 5,000 characters or fewer.',422);
    if ($threadId > 0) {
        messenger_require_thread($conn,$actor,$threadId);
        $threadRow=site_query($conn,'SELECT ThreadType FROM chat_threads WHERE ThreadID=?','i',[$threadId])->fetch_assoc();
        if($threadRow['ThreadType']==='DIRECT' && (empty($_SESSION['messenger_csrf'])||!is_string($input['csrf_token']??null)||!hash_equals($_SESSION['messenger_csrf'], $input['csrf_token']))){
            http_response_code(403);
            echo json_encode(['success'=>false,'code'=>'MESSENGER_CSRF_EXPIRED','message'=>'Your Messenger session needs to be refreshed. Please try sending again.']);
            exit;
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
        $participantUsers = [];
        $pstmt = $conn->prepare("SELECT UserID FROM chat_thread_participants WHERE ThreadID = ?");
        $pstmt->bind_param("i", $threadId);
        $pstmt->execute();
        $presult = $pstmt->get_result();
        while ($p = $presult->fetch_assoc()) $participantUsers[] = (int)$p['UserID'];
        $pstmt->close();
    } else {
        if ($contactChannel === 'admin') {
            if(!in_array('Admin',(array)messenger_allowed_roles_for($actor['role']),true)&&$actor['role']!=='Admin')throw new DomainException('This contact channel is not allowed.',403);
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

    $authorizedPeers=messenger_require_thread($conn,$actor,$threadId);
    $participantUsers=array_merge([$userId],array_map('intval',array_column($authorizedPeers,'user_id')));
    $requestId=$input['request_id']??null;
    if(is_string($requestId)&&isset($_SESSION['messenger_sent'][$requestId])){echo json_encode($_SESSION['messenger_sent'][$requestId]);exit;}
    $displaySender = chat_get_user_display($conn, $userId);

    $conn->begin_transaction();
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

    $conn->commit();
    $reply=['success'=>true,'thread_id'=>$threadId,'message_id'=>$msgId];
    if(is_string($requestId)&&strlen($requestId)<100){$_SESSION['messenger_sent'][$requestId]=$reply;if(count($_SESSION['messenger_sent'])>100)array_shift($_SESSION['messenger_sent']);}
    echo json_encode($reply);
} catch (Throwable $e) {
    $conn->rollback();messenger_api_error($e);
}

