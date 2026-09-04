<?php
// Employee_Tracking/Api/Admin/export_system_logs.php
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Administrator') {
    die('Unauthorized access');
}

$search = trim($_GET['search'] ?? '');
$module = trim($_GET['module'] ?? '');
$action = trim($_GET['action'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$whereClauses = ["1=1"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(username LIKE :search OR description LIKE :search OR reference_id LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($module !== '') {
    $whereClauses[] = "module = :module";
    $params[':module'] = $module;
}
if ($action !== '') {
    $whereClauses[] = "action = :action";
    $params[':action'] = $action;
}
if ($startDate !== '') {
    $whereClauses[] = "created_at >= :start_date";
    $params[':start_date'] = $startDate . ' 00:00:00';
}
if ($endDate !== '') {
    $whereClauses[] = "created_at <= :end_date";
    $params[':end_date'] = $endDate . ' 23:59:59';
}

$whereSql = implode(' AND ', $whereClauses);

$stmt = $db->prepare("SELECT log_id, username, role, module, action, description, reference_id, ip_address, browser, operating_system, created_at FROM system_logs WHERE $whereSql ORDER BY created_at DESC");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

AuditLogger::log($db, 'System Logs', 'Export', 'Exported System Activity Audit Logs to CSV');

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=System_Audit_Logs_" . date('Y-m-d_His') . ".csv");
header("Pragma: no-cache");
header("Expires: 0");

$output = fopen('php://output', 'w');
fputcsv($output, ['Log ID', 'Username', 'Role', 'Module', 'Action', 'Description', 'Ref ID', 'IP Address', 'Browser', 'OS', 'Timestamp']);

foreach ($logs as $row) {
    fputcsv($output, $row);
}
fclose($output);