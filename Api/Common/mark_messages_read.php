<?php
/**
 * POST JSON { "sender_id": <other user who sent unread messages> }
 * Marks the DIRECT thread with that user as read for the current viewer.
 */
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/chat_helper.php';
require_once __DIR__ . '/../Helpers/messenger_unread.php';
require_once __DIR__ . '/../Helpers/messenger_rbac.php';

chat_ensure_tables($conn);

$viewerId = (int)$_SESSION['user_id'];
$viewerRole = trim((string)($_SESSION['role'] ?? ''));

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$senderId = isset($input['sender_id']) ? (int)$input['sender_id'] : 0;
if ($senderId <= 0) {
    $senderId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
}

if ($senderId <= 0 || $senderId === $viewerId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid sender_id is required']);
    exit();
}

$roleStmt = $conn->prepare("
    SELECT r.RoleName AS role,
        (
            SELECT d.SiteSuperVisorID
            FROM deployment d
            INNER JOIN employee e ON e.EmployeeID = d.EmployeeID
            INNER JOIN users u ON u.Email = e.Email
            WHERE u.UsersID = ?
              AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
            ORDER BY d.DeploymentID DESC
            LIMIT 1
        ) AS team_lead_id
    FROM users u
    INNER JOIN roles r ON r.RolesID = u.RoleID
    WHERE u.UsersID = ? AND u.IsActive = 1
    LIMIT 1
");
$contactRole = '';
$teamLeadId = null;
if ($roleStmt) {
    $roleStmt->bind_param('ii', $senderId, $senderId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    if ($roleRow) {
        $contactRole = (string)$roleRow['role'];
        $teamLeadId = isset($roleRow['team_lead_id']) && $roleRow['team_lead_id'] !== null
            ? (int)$roleRow['team_lead_id']
            : null;
    }
}

if (!messenger_contact_allowed($viewerRole, $viewerId, $contactRole, $senderId, $teamLeadId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

$threadId = messenger_mark_read_with_user($conn, $viewerId, $senderId);
if ($threadId === null) {
    echo json_encode([
        'success' => true,
        'thread_id' => null,
        'message' => 'No thread found; nothing to mark read',
    ]);
    exit();
}

chat_mark_thread_read($conn, $threadId, $viewerId);

$counts = messenger_get_unread_counts($conn, $viewerId);
$byContact = [];
foreach ($counts['by_contact'] as $sid => $n) {
    $byContact[(string)$sid] = (int)$n;
}

echo json_encode([
    'success' => true,
    'thread_id' => $threadId,
    'sender_id' => $senderId,
    'total' => (int)$counts['total'],
    'by_contact' => $byContact,
]);
