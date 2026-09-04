<?php
// Employee_Tracking/Api/Admin/get_system_logs.php
header('Content-Type: application/json');

$dbConnectPath = dirname(__DIR__) . '/Db_connection/db_connect.php';
require_once $dbConnectPath;

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(10, min(100, intval($_GET['limit'] ?? 15)));
    $offset = ($page - 1) * $limit;

    $search = trim($_GET['search'] ?? '');
    $module = trim($_GET['module'] ?? '');
    $action = trim($_GET['action'] ?? '');
    $startDate = trim($_GET['start_date'] ?? '');
    $endDate = trim($_GET['end_date'] ?? '');

    $whereClauses = ["1=1"];
    $types = "";
    $bindValues = [];

    if ($search !== '') {
        $whereClauses[] = "(username LIKE ? OR description LIKE ? OR reference_id LIKE ? OR ip_address LIKE ?)";
        $searchTerm = "%$search%";
        array_push($bindValues, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        $types .= "ssss";
    }

    if ($module !== '') {
        $whereClauses[] = "module = ?";
        array_push($bindValues, $module);
        $types .= "s";
    }

    if ($action !== '') {
        $whereClauses[] = "action = ?";
        array_push($bindValues, $action);
        $types .= "s";
    }

    if ($startDate !== '') {
        $whereClauses[] = "created_at >= ?";
        array_push($bindValues, $startDate . ' 00:00:00');
        $types .= "s";
    }

    if ($endDate !== '') {
        $whereClauses[] = "created_at <= ?";
        array_push($bindValues, $endDate . ' 23:59:59');
        $types .= "s";
    }

    $whereSql = implode(' AND ', $whereClauses);

    // Count Total Records
    $countSql = "SELECT COUNT(*) FROM system_logs WHERE $whereSql";
    $countStmt = $conn->prepare($countSql);
    if (!empty($bindValues)) {
        $countStmt->bind_param($types, ...$bindValues);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = (int)$countResult->fetch_row()[0];

    // Fetch Paginated Logs
    $sql = "SELECT log_id, user_id, username, role, module, action, description, reference_id, ip_address, browser, operating_system, device, 
                   DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') AS formatted_time, created_at 
            FROM system_logs 
            WHERE $whereSql 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    
    $paginationTypes = $types . "ii";
    $paginationValues = array_merge($bindValues, [$limit, $offset]);
    
    $stmt->bind_param($paginationTypes, ...$paginationValues);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'total' => $totalRecords,
            'page' => $page,
            'limit' => $limit,
            'pages' => max(1, ceil($totalRecords / $limit))
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}