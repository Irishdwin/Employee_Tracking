<?php
/**
 * Messenger contacts for any logged-in role (RBAC-filtered).
 */
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/messenger_rbac.php';

$viewerId = (int)$_SESSION['user_id'];
$viewerRole = trim((string)$_SESSION['role']);

$sql = "
    SELECT
        u.UsersID AS user_id,
        u.FullName AS full_name,
        u.Email AS email,
        r.RoleName AS role,
        (
            SELECT GROUP_CONCAT(DISTINCT cc.Name ORDER BY cc.Name SEPARATOR ', ')
            FROM clientcompany cc
            WHERE cc.UserID = u.UsersID
            LIMIT 3
        ) AS assigned_companies,
        (
            SELECT cc2.Name
            FROM deployment d
            INNER JOIN clientcompany cc2 ON cc2.CompanyID = d.CompanyID
            INNER JOIN employee e ON e.EmployeeID = d.EmployeeID
            WHERE e.Email = u.Email
              AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
            ORDER BY d.DeploymentID DESC
            LIMIT 1
        ) AS deployment_company,
        (
            SELECT d2.SiteSuperVisorID
            FROM deployment d2
            INNER JOIN employee e2 ON e2.EmployeeID = d2.EmployeeID
            WHERE e2.Email = u.Email
              AND (d2.EndDate IS NULL OR d2.EndDate >= CURDATE())
            ORDER BY d2.DeploymentID DESC
            LIMIT 1
        ) AS team_lead_id
    FROM users u
    INNER JOIN roles r ON r.RolesID = u.RoleID
    WHERE u.IsActive = 1 AND u.UsersID <> ?
    ORDER BY r.RoleName ASC, u.FullName ASC
    LIMIT 500
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load contacts: ' . $conn->error,
    ]);
    exit();
}

$stmt->bind_param('i', $viewerId);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load contacts: ' . $stmt->error,
    ]);
    $stmt->close();
    exit();
}

$result = $stmt->get_result();

$allUsers = [];
$contacts = [];

while ($row = $result->fetch_assoc()) {
    $contactRole = (string)$row['role'];
    $contactUserId = (int)$row['user_id'];
    $teamLeadId = isset($row['team_lead_id']) && $row['team_lead_id'] !== null
        ? (int)$row['team_lead_id']
        : null;

    $company = trim((string)($row['assigned_companies'] ?? ''));
    if ($company === '') {
        $company = trim((string)($row['deployment_company'] ?? ''));
    }
    if ($company === '') {
        $company = 'Onazul Manpower';
    }

    $isAssignedTl = ($teamLeadId !== null && $teamLeadId === $viewerId);

    $entry = [
        'user_id' => $contactUserId,
        'id' => $contactUserId,
        'full_name' => $row['full_name'],
        'email' => $row['email'],
        'role' => $contactRole,
        'company' => $company,
        'subtitle' => $contactRole . ' · ' . $company,
        'team_lead_id' => $teamLeadId,
        'manager_id' => $teamLeadId,
        'managerId' => $teamLeadId,
        'assigned_team_lead_id' => $teamLeadId,
        'is_assigned_to_current_team_lead' => messenger_normalize_role($contactRole) === 'Employee'
            ? $isAssignedTl
            : null,
    ];

    $allUsers[] = $entry;

    if (messenger_contact_allowed($viewerRole, $viewerId, $contactRole, $contactUserId, $teamLeadId)) {
        $contacts[] = $entry;
    }
}
$stmt->close();

messenger_enrich_thread_unread($conn, $viewerId, $contacts);
messenger_enrich_thread_unread($conn, $viewerId, $allUsers);

$messagesUnread = 0;
$msgCntStmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM chat_messages m
    INNER JOIN chat_thread_participants p ON p.ThreadID = m.ThreadID AND p.UserID = ?
    LEFT JOIN chat_thread_reads r ON r.ThreadID = m.ThreadID AND r.UserID = ?
    WHERE m.SenderUserID <> ?
      AND (r.LastReadAt IS NULL OR m.CreatedAt > r.LastReadAt)
");
if ($msgCntStmt) {
    $msgCntStmt->bind_param('iii', $viewerId, $viewerId, $viewerId);
    $msgCntStmt->execute();
    $msgRow = $msgCntStmt->get_result()->fetch_assoc();
    $messagesUnread = (int)($msgRow['unread_count'] ?? 0);
    $msgCntStmt->close();
}

echo json_encode([
    'success' => true,
    'viewer' => [
        'user_id' => $viewerId,
        'id' => $viewerId,
        'role' => $viewerRole,
    ],
    'all_users' => $allUsers,
    'contacts' => $contacts,
    'messages_unread' => $messagesUnread,
    'meta' => [
        'total_users' => count($allUsers),
        'allowed_contacts' => count($contacts),
    ],
]);

/**
 * Attach thread_id and per-contact unread_count for DIRECT (dm) threads.
 */
function messenger_enrich_thread_unread(mysqli $conn, int $viewerId, array &$users): void
{
    if (empty($users)) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT
            t.ThreadID,
            t.RelatedModule,
            (
                SELECT COUNT(*)
                FROM chat_messages m
                WHERE m.ThreadID = t.ThreadID
                  AND m.SenderUserID <> ?
                  AND m.CreatedAt > COALESCE(
                        (
                            SELECT r.LastReadAt
                            FROM chat_thread_reads r
                            WHERE r.ThreadID = t.ThreadID AND r.UserID = ?
                        ),
                        '1970-01-01'
                  )
            ) AS unread_count
        FROM chat_threads t
        INNER JOIN chat_thread_participants p ON p.ThreadID = t.ThreadID AND p.UserID = ?
        WHERE t.ThreadType = 'DIRECT'
    ");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('iii', $viewerId, $viewerId, $viewerId);
    if (!$stmt->execute()) {
        $stmt->close();
        return;
    }

    $byOtherUser = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rm = (string)($row['RelatedModule'] ?? '');
        if (!preg_match('/^dm_(\d+)_(\d+)$/', $rm, $m)) {
            continue;
        }
        $a = (int)$m[1];
        $b = (int)$m[2];
        $otherId = ($a === $viewerId) ? $b : (($b === $viewerId) ? $a : 0);
        if ($otherId <= 0 || $otherId === $viewerId) {
            continue;
        }
        $byOtherUser[$otherId] = [
            'thread_id' => (int)$row['ThreadID'],
            'unread_count' => (int)($row['unread_count'] ?? 0),
        ];
    }
    $stmt->close();

    foreach ($users as &$u) {
        $uid = (int)($u['user_id'] ?? $u['id'] ?? 0);
        if (isset($byOtherUser[$uid])) {
            $u['thread_id'] = $byOtherUser[$uid]['thread_id'];
            $u['unread_count'] = $byOtherUser[$uid]['unread_count'];
        } else {
            $u['thread_id'] = null;
            $u['unread_count'] = 0;
        }
    }
    unset($u);
}
