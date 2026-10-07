<?php
/**
 * Admin Holidays management.
 *
 * GET  ?year=YYYY (optional) — list all holidays, optionally filtered by year
 * POST action=add | edit | delete   (Admin-only)
 *
 * Reuses the existing `holidays` table (see Api/Helpers/holidays_helper.php
 * for why this is the existing table, not a new one). Server-side validation
 * only — the pay multiplier and holiday definition always come from the
 * database, never trusted from the request body, per the project's payroll
 * security rules.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn
require_once __DIR__ . '/../Helpers/holidays_helper.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $actor=payroll_query($conn,'SELECT r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1','i',[(int)$_SESSION['user_id']])->get_result()->fetch_assoc();
    if(!$actor || $actor['RoleName']!=='Admin') {http_response_code(403);echo json_encode(['success'=>false,'message'=>'Admin access required.']);exit;}
    if($method==='POST' && (!isset($_SESSION['payroll_csrf']) || !hash_equals($_SESSION['payroll_csrf'],(string)($_SERVER['HTTP_X_PAYROLL_CSRF'] ?? '')))) {http_response_code(403);echo json_encode(['success'=>false,'message'=>'Reload the holiday page and try again.']);exit;}
    holidays_ensure_schema($conn);

    if ($method === 'GET') {
        $year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;
        $holidays = holidays_fetch_all($conn, $year);
        foreach($holidays as &$h)$h['site_ids']=holiday_scope_ids($conn,(int)$h['id']);unset($h);
        $sites=$conn->query('SELECT s.SiteID,s.Name,c.Name company FROM deployment_sites s JOIN clientcompany c ON c.CompanyID=s.CompanyID ORDER BY c.Name,s.Name')->fetch_all(MYSQLI_ASSOC);
        echo json_encode([
            'success' => true,
            'csrf_token' => payroll_csrf_token(),
            'holidays' => $holidays,
            'allowed_types' => holidays_allowed_types(),
            'sites' => $sites,
        ]);
        exit();
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $action = trim((string)($input['action'] ?? ''));

        if ($action === 'add') {
            $sites=holiday_scope_validate($conn,$input);
            $conn->begin_transaction();
            $result = holidays_create($conn, $input);
            if($result['success']) {holiday_scope_save($conn,(int)$result['id'],$sites);$conn->commit();} else $conn->rollback();
            echo json_encode($result);
            exit();
        }

        if ($action === 'edit') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'A valid holiday id is required.']);
                exit();
            }
            $sites=holiday_scope_validate($conn,$input);
            $conn->begin_transaction();
            $result = holidays_update($conn, $id, $input);
            if($result['success']) {holiday_scope_save($conn,$id,$sites);$conn->commit();} else $conn->rollback();
            echo json_encode($result);
            exit();
        }

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'A valid holiday id is required.']);
                exit();
            }
            $conn->begin_transaction();
            $result = holidays_delete($conn, $id);
            if($result['success']) {holiday_scope_save($conn,$id,[]);$conn->commit();} else $conn->rollback();
            echo json_encode($result);
            exit();
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('manage_holidays: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error managing holidays.']);
}