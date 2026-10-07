<?php
declare(strict_types=1);

/** Shared database/authorization primitives; never starts a transaction implicitly. */
function payroll_query(mysqli $db, string $sql, string $types = '', array $args = []): mysqli_stmt {
    $stmt = $db->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$args);
    $stmt->execute();
    return $stmt;
}
function payroll_csrf_token(): string {
    if (empty($_SESSION['payroll_csrf'])) $_SESSION['payroll_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['payroll_csrf'];
}
function payroll_require_accounting(mysqli $db, bool $write = false): int {
    $id = (int)($_SESSION['user_id'] ?? 0);
    $actor = payroll_query($db, 'SELECT r.RoleName Role,u.IsActive FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=?', 'i', [$id])->get_result()->fetch_assoc();
    if (!$actor || !(int)$actor['IsActive'] || !in_array($actor['Role'], ['Accounting','Admin'], true))
        throw new RuntimeException('Accounting authorization required.', 403);
    if ($write && (!isset($_SESSION['payroll_csrf']) || !hash_equals($_SESSION['payroll_csrf'], (string)($_SERVER['HTTP_X_PAYROLL_CSRF'] ?? $_POST['csrf_token'] ?? ''))))
        throw new RuntimeException('Your session verification expired. Reload this page and try again.', 403);
    return $id;
}
function payroll_api_error(Throwable $error): void {
    $code = $error instanceof InvalidArgumentException ? 422 : (in_array($error->getCode(), [403,404,409], true) ? $error->getCode() : 500);
    if ($code === 500) error_log('Payroll workflow: '.$error->getMessage());
    http_response_code($code);
    echo json_encode(['success'=>false,'message'=>$code === 500 ? 'Unable to complete the payroll operation.' : $error->getMessage()]);
}
function payroll_save_proof(?array $file): ?string {
    if (!$file || (int)$file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ((int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 10*1024*1024 || !is_uploaded_file($file['tmp_name']))
        throw new InvalidArgumentException('Choose a JPG, PNG or PDF proof of at most 10 MB.');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf'];
    if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) throw new InvalidArgumentException('Proof content does not match its file type.');
    $dir = dirname(__DIR__,2).'/uploads/payroll_status_proof';
    if (!is_dir($dir) && !mkdir($dir,0750,true)) throw new RuntimeException('Unable to create proof storage.');
    file_put_contents($dir.'/.htaccess', "Require all denied\n");
    $path = 'uploads/payroll_status_proof/'.bin2hex(random_bytes(20)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'],dirname(__DIR__,2).'/'.$path)) throw new RuntimeException('Unable to save proof.');
    return $path;
}
