<?php
/**
 * Lightweight endpoint for Add Client modal Team Lead dropdown.
 * Query params:
 * - q: optional search text (name/email)
 * - company_id: optional current company id when editing (keeps current lead selectable)
 */
session_start();
header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || ($role !== 'Admin' && $role !== 'HR')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

/**
 * Always include the Team Lead already assigned to this company (even if they would
 * otherwise be filtered out), so Edit Client can pre-select them.
 */
function append_assigned_team_lead(mysqli $conn, array $teamLeads, int $companyId): array {
    if ($companyId <= 0) {
        return $teamLeads;
    }
    $stmt = $conn->prepare(
        "SELECT u.UsersID, u.FullName, u.Email
         FROM clientcompany cc
         INNER JOIN users u ON u.UsersID = cc.UserID
         WHERE cc.CompanyID = ? AND cc.UserID IS NOT NULL
         LIMIT 1"
    );
    if (!$stmt) {
        return $teamLeads;
    }
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return $teamLeads;
    }
    $assignedId = (int)($row['UsersID'] ?? 0);
    foreach ($teamLeads as $tl) {
        if ((int)($tl['UsersID'] ?? 0) === $assignedId) {
            return $teamLeads;
        }
    }
    array_unshift($teamLeads, $row);
    return $teamLeads;
}

$editingCompanyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$q = trim((string)($_GET['q'] ?? ''));
$searchLike = '%' . $q . '%';

if ($editingCompanyId > 0) {
    $sql = "SELECT u.UsersID, u.FullName, u.Email
            FROM users u
            INNER JOIN roles r ON u.RoleID = r.RolesID
            WHERE r.RoleName IN ('TeamLead', 'Team Lead')
              AND u.IsActive = 1
              AND (? = '' OR u.FullName LIKE ? OR u.Email LIKE ?)
              AND NOT EXISTS (
                SELECT 1
                FROM clientcompany cc
                WHERE cc.IsActive = 1
                  AND cc.UserID IS NOT NULL
                  AND cc.UserID = u.UsersID
                  AND cc.CompanyID <> ?
              )
            ORDER BY u.FullName";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'team_leads' => []]);
        exit();
    }
    $stmt->bind_param('sssi', $q, $searchLike, $searchLike, $editingCompanyId);
} else {
    $sql = "SELECT u.UsersID, u.FullName, u.Email
            FROM users u
            INNER JOIN roles r ON u.RoleID = r.RolesID
            WHERE r.RoleName IN ('TeamLead', 'Team Lead')
              AND u.IsActive = 1
              AND (? = '' OR u.FullName LIKE ? OR u.Email LIKE ?)
              AND NOT EXISTS (
                SELECT 1
                FROM clientcompany cc
                WHERE cc.IsActive = 1
                  AND cc.UserID IS NOT NULL
                  AND cc.UserID = u.UsersID
              )
            ORDER BY u.FullName";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'team_leads' => []]);
        exit();
    }
    $stmt->bind_param('sss', $q, $searchLike, $searchLike);
}

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Query failed', 'team_leads' => []]);
    exit();
}
$result = $stmt->get_result();
$teamLeads = [];
while ($row = $result->fetch_assoc()) {
    $teamLeads[] = $row;
}
$stmt->close();

$teamLeads = append_assigned_team_lead($conn, $teamLeads, $editingCompanyId);

echo json_encode(['success' => true, 'team_leads' => $teamLeads]);
exit();

