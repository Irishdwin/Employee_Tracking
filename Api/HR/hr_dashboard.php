<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function hrd_reply(array $data, int $code=200): void {
    http_response_code($code); echo json_encode($data,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit;
}
if (empty($_SESSION['user_id'])) hrd_reply(['success'=>false,'message'=>'Authentication required.'],401);
if (($_SESSION['role'] ?? '') !== 'HR') hrd_reply(['success'=>false,'message'=>'HR access required.'],403);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { header('Allow: GET'); hrd_reply(['success'=>false,'message'=>'Method not allowed.'],405); }
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/hr_dashboard_helper.php';
require_once __DIR__ . '/../Helpers/hr_employee_management.php';
try {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND r.RoleName='HR'");
    $stmt->bind_param('i',$uid); $stmt->execute();
    $allowed = $stmt->get_result()->num_rows === 1; $stmt->close();
    if (!$allowed) hrd_reply(['success'=>false,'message'=>'HR access required.'],403);
    $action = $_GET['action'] ?? 'summary';
    if ($action === 'summary') {
        $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        $data = hrd_summary($conn,$uid); $conn->commit();
    } elseif ($action === 'employees') {
        $filter = $_GET['filter'] ?? 'total';
        $page = filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
        if (!is_string($filter) || !isset(hrd_filters()[$filter]) || $page===false) hrd_reply(['success'=>false,'message'=>'Invalid employee filter or page.'],400);
        $data = hrd_employees($conn,$filter,$page);
    } elseif (in_array($action,['employee_options','employee_list','employee_details'],true)) {
        $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        if ($action==='employee_options') $data=hrem_options($conn);
        elseif ($action==='employee_list') $data=hrem_list($conn,$_GET);
        else $data=hrem_details($conn,$_GET);
        $conn->commit();
    } else hrd_reply(['success'=>false,'message'=>'Unknown dashboard action.'],400);
    hrd_reply(['success'=>true,'data'=>$data]);
} catch (DomainException $e) {
    $conn->rollback();
    hrd_reply(['success'=>false,'message'=>$e->getMessage()],in_array($e->getCode(),[400,404],true)?$e->getCode():400);
} catch (Throwable $e) {
    error_log('HR dashboard: '.$e->getMessage());
    hrd_reply(['success'=>false,'message'=>'Dashboard data could not be loaded. Please retry.'],500);
}
