<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['id']);
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$module = trim((string)($input['module'] ?? $input['module_id'] ?? $input['moduleId'] ?? ''));

require_once __DIR__ . '/../Db_connection/db_connect.php';

try {
    if ($module === 'endorsed') {
        $stmt = $conn->prepare("UPDATE notifications SET IsRead = 1 WHERE UserID = ? AND NotificationType = 'endorsed_applicant' AND IsRead = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    } elseif ($module !== '') {
        $stmt = $conn->prepare("UPDATE notifications SET IsRead = 1 WHERE UserID = ? AND (NotificationType = ? OR RelateedModule = ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $userId, $module, $module);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("UPDATE notifications SET IsRead = 1 WHERE UserID = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Module marked as read.']);
} catch (Throwable $e) {
    error_log('mark_module_read exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}