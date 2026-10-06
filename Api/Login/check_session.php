<?php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'role' => null, 'full_name' => null]);
    exit();
}

// Keep session role in sync with DB so role-based pages redirect immediately after HR/Admin changes a user's role.
// This is important for flows like Applicant -> Employee conversion (hire).
try {
    require_once __DIR__ . '/../Db_connection/db_connect.php';
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $stmt = $conn->prepare("
            SELECT u.FullName, u.Email, r.RoleName
            FROM users u
            LEFT JOIN roles r ON u.RoleID = r.RolesID
            WHERE u.UsersID = ?
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $roleName = trim((string)($row['RoleName'] ?? ''));
                if ($roleName !== '' && (!isset($_SESSION['role']) || $_SESSION['role'] !== $roleName)) {
                    $_SESSION['role'] = $roleName;
                }
                $fullName = trim((string)($row['FullName'] ?? ''));
                $email = trim((string)($row['Email'] ?? ''));
                if ($fullName !== '') {
                    $_SESSION['full_name'] = $fullName;
                }
                if ($email !== '') {
                    $_SESSION['email'] = $email;
                }
            }
            $stmt->close();
        }
    }
} catch (Throwable $e) {
    // Ignore DB sync errors; fall back to existing session role.
}

echo json_encode([
    'success'  => true,
    'timeout_seconds' => SESSION_IDLE_TIMEOUT_SECONDS,
    'remaining_seconds' => max(0, SESSION_IDLE_TIMEOUT_SECONDS - (time() - (int)$_SESSION['last_activity'])),
    'user_id'  => $_SESSION['user_id'] ?? null,
    'role'     => $_SESSION['role'],
    'full_name'=> $_SESSION['full_name'] ?? '',
    'email'    => $_SESSION['email'] ?? ''
]);
