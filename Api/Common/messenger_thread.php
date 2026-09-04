<?php
/**
 * Find or create a 1:1 messenger thread (any authenticated role, RBAC-checked).
 */
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Helpers/chat_helper.php';
require_once __DIR__ . '/../Helpers/messenger_rbac.php';
require __DIR__ . '/../Db_connection/db_connect.php';

chat_ensure_tables($conn);

$userId = (int)$_SESSION['user_id'];
$viewerRole = trim((string)$_SESSION['role']);
$otherId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    $otherId = isset($input['user_id']) ? (int)$input['user_id'] : $otherId;
}

if ($otherId <= 0 || $otherId === $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid user_id is required']);
    exit();
}

$otherStmt = $conn->prepare("
    SELECT r.RoleName AS role,
        (
            SELECT d.SiteSuperVisorID
            FROM deployment d
            INNER JOIN employee e ON e.EmployeeID = d.EmployeeID
            WHERE e.Email = u.Email
              AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
            ORDER BY d.DeploymentID DESC
            LIMIT 1
        ) AS team_lead_id
    FROM users u
    INNER JOIN roles r ON r.RolesID = u.RoleID
    WHERE u.UsersID = ? AND u.IsActive = 1
    LIMIT 1
");
$otherStmt->bind_param('i', $otherId);
$otherStmt->execute();
$otherRow = $otherStmt->get_result()->fetch_assoc();
$otherStmt->close();

if (!$otherRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$otherRole = (string)$otherRow['role'];
$otherTlId = isset($otherRow['team_lead_id']) && $otherRow['team_lead_id'] !== null
    ? (int)$otherRow['team_lead_id']
    : null;

if (!messenger_contact_allowed($viewerRole, $userId, $otherRole, $otherId, $otherTlId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not allowed to message this user.']);
    exit();
}

$pairKey = 'dm_' . min($userId, $otherId) . '_' . max($userId, $otherId);

$find = $conn->prepare("
    SELECT t.ThreadID
    FROM chat_threads t
    INNER JOIN chat_thread_participants p1 ON p1.ThreadID = t.ThreadID AND p1.UserID = ?
    INNER JOIN chat_thread_participants p2 ON p2.ThreadID = t.ThreadID AND p2.UserID = ?
    WHERE t.ThreadType = 'DIRECT' AND t.RelatedModule = ?
    LIMIT 1
");
$find->bind_param('iis', $userId, $otherId, $pairKey);
$find->execute();
$row = $find->get_result()->fetch_assoc();
$find->close();

$threadId = $row ? (int)$row['ThreadID'] : 0;

if ($threadId === 0) {
    $threadId = chat_find_or_create_thread(
        $conn,
        'DIRECT',
        $pairKey,
        $otherId,
        $userId,
        [$userId, $otherId]
    );
}

echo json_encode(['success' => true, 'thread_id' => $threadId, 'user_id' => $otherId]);
