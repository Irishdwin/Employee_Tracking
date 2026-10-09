<?php
require_once __DIR__.'/payroll_workflow.php';
function client_admin_require(mysqli $db): int {
 $id=(int)($_SESSION['user_id']??0);
 $actor=payroll_query($db,'SELECT u.IsActive,r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=?','i',[$id])->get_result()->fetch_assoc();
 if(!$actor || !(int)$actor['IsActive'] || $actor['RoleName']!=='Admin')throw new RuntimeException('Admin authorization required.',403);
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && (!isset($_SESSION['payroll_csrf']) || !hash_equals($_SESSION['payroll_csrf'],(string)($_SERVER['HTTP_X_PAYROLL_CSRF']??$_POST['csrf_token']??''))))
  throw new RuntimeException('Session verification expired. Reload and try again.',403);
 return $id;
}
