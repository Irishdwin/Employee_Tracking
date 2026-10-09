<?php
require_once __DIR__.'/../Common/session_init.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/client_admin_security.php';
try { client_admin_require($conn); } catch(Throwable $e) {
 http_response_code(403);header('Content-Type: application/json');echo json_encode(['success'=>false,'message'=>$e->getCode()===403?$e->getMessage():'Unable to verify Admin authorization.']);exit;
}
if(empty($_SESSION['industry_edit_key']))$_SESSION['industry_edit_key']=bin2hex(random_bytes(32));
function industry_edit_token(int $id,string $name): string {
 return hash_hmac('sha256',$id.':'.$name,$_SESSION['industry_edit_key']);
}
header('Content-Type: application/json');

function json_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function ensure_industry_lookup_table(mysqli $conn): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS industry_lookup (
            IndustryID INT(11) NOT NULL AUTO_INCREMENT,
            Name VARCHAR(100) NOT NULL,
            CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (IndustryID),
            UNIQUE KEY uniq_industry_name (Name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    if (!$conn->query($sql)) {
        json_response(['success' => false, 'message' => 'Failed to initialize industry lookup table.'], 500);
    }

    // Seed from existing industries so dropdown is immediately useful.
    $res = $conn->query("SELECT DISTINCT TRIM(Industry) AS IndustryName FROM clientcompany WHERE Industry IS NOT NULL AND TRIM(Industry) <> ''");
    if ($res) {
        $stmt = $conn->prepare("INSERT IGNORE INTO industry_lookup (Name) VALUES (?)");
        if ($stmt) {
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)$row['IndustryName']);
                if ($name === '') continue;
                $stmt->bind_param("s", $name);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}
ensure_industry_lookup_table($conn);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $res = $conn->query("SELECT IndustryID, Name FROM industry_lookup ORDER BY Name ASC");
    $items = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = ['id' => (int)$row['IndustryID'], 'name' => $row['Name'], 'edit_token'=>industry_edit_token((int)$row['IndustryID'],$row['Name'])];
        }
    }
    json_response(['success' => true, 'industries' => $items,'csrf_token'=>payroll_csrf_token()]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if (!in_array($action,['create','update'],true)) {
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '' || mb_strlen($name)>100) {
        json_response(['success' => false, 'message' => 'Industry name is required and must be at most 100 characters.'], 400);
    }

    try {
      $conn->begin_transaction();
      $id=(int)($_POST['industry_id']??0);$old=null;
      if($action==='update') {
       $old=payroll_query($conn,'SELECT IndustryID,Name FROM industry_lookup WHERE IndustryID=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();
       if(!$old || !hash_equals(industry_edit_token($id,$old['Name']),(string)($_POST['edit_token']??'')))throw new InvalidArgumentException('Industry selection changed or expired. Reload the industries and select it again.');
      }
      $duplicate=payroll_query($conn,'SELECT IndustryID FROM industry_lookup WHERE Name=? AND IndustryID<>? LIMIT 1','si',[$name,$action==='update'?$id:0])->get_result()->fetch_assoc();
      if($duplicate)throw new InvalidArgumentException('An industry with this name already exists.');
      if($old) {
       payroll_query($conn,'UPDATE industry_lookup SET Name=? WHERE IndustryID=?','si',[$name,$id]);
       // Legacy company references are stored as names, not industry IDs.
       payroll_query($conn,'UPDATE clientcompany SET Industry=? WHERE TRIM(Industry)=?','ss',[$name,$old['Name']]);
      } else {
       payroll_query($conn,'INSERT INTO industry_lookup(Name) VALUES(?)','s',[$name]);$id=(int)$conn->insert_id;
      }
      $conn->commit();
      json_response(['success'=>true,'message'=>$old?'Industry updated.':'Industry added.','industry'=>['id'=>$id,'name'=>$name]]);
    }catch(Throwable $e){
     $conn->rollback();
     $message=$e instanceof InvalidArgumentException?$e->getMessage():($e->getCode()===1062?'An industry with this name already exists.':'Unable to save industry.');
     json_response(['success'=>false,'message'=>$message],$e instanceof InvalidArgumentException||$e->getCode()===1062?409:500);
    }
}

json_response(['success' => false, 'message' => 'Invalid request method.'], 405);

