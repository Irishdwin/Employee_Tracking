<?php
require_once __DIR__ . '/../Common/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';
require __DIR__ . '/../Applicant/applicant_doc_fs.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Employee') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_SESSION['employee_document_csrf']) || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['employee_document_csrf'], $_POST['csrf_token'])) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Please refresh the documents page and try again.']); exit;
}

$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !isset($row['ApplicantID'])) {
    echo json_encode(['success' => false, 'message' => 'No linked applicant record found for this employee.']);
    exit();
}

$applicantId = (int)$row['ApplicantID'];
$docType = trim((string)($_POST['doc_type'] ?? 'General Document'));
$rawCat = strtolower(trim((string)($_POST['doc_category'] ?? 'general')));
$allowedCats = ['identity', 'academic', 'employment', 'general'];
$docCategory = in_array($rawCat, $allowedCats, true) ? $rawCat : 'general';

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error.']);
    exit();
}

$file = $_FILES['document'];
$maxSize = 5 * 1024 * 1024;
$allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

if ((int)$file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
    exit();
}

$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExts, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG']);
    exit();
}

$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$mimeTypes=['pdf'=>['application/pdf'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'doc'=>['application/msword','application/x-ole-storage','application/CDFV2'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip']];
$valid=in_array($mime,$mimeTypes[$ext],true);
if ($valid && in_array($ext,['png','jpg','jpeg'],true)) $valid=getimagesize($file['tmp_name'])!==false;
if ($valid && $ext==='docx') {
    $zip=new ZipArchive(); $valid=$zip->open($file['tmp_name'])===true;
    if($valid) { $valid=$zip->locateName('[Content_Types].xml')!==false && $zip->locateName('word/document.xml')!==false; $zip->close(); }
}
if (!$valid) { http_response_code(415); echo json_encode(['success'=>false,'message'=>'The file content does not match an allowed document format.']); exit; }
$replaceId=filter_var($_POST['replace_doc_id']??0,FILTER_VALIDATE_INT);
if ($replaceId===false || $replaceId<0) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid document selection.']); exit; }
$fullPath=null;
try {
    $conn->begin_transaction();
    if ($replaceId>0) {
        $owner=$conn->prepare('SELECT DocID FROM applicant_uploaded_docs WHERE DocID=? AND ApplicantID=? FOR UPDATE');
        $owner->bind_param('ii',$replaceId,$applicantId); $owner->execute();
        $owned=$owner->get_result()->fetch_assoc(); $owner->close();
        if (!$owned) { $conn->rollback(); http_response_code(403); echo json_encode(['success'=>false,'message'=>'You can only replace your own documents.']); exit; }
    }
    $uploadDir=dirname(__DIR__,2).'/Uploads/Documents/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir,0755,true)) throw new RuntimeException('Upload directory unavailable');
    $newName='empdoc_'.$applicantId.'_'.bin2hex(random_bytes(16)).'.'.$ext;
    $fullPath=$uploadDir.$newName; $dbPath='../../Uploads/Documents/'.$newName;
    if (!move_uploaded_file($file['tmp_name'],$fullPath)) throw new RuntimeException('Unable to save file');
    $size=(int)$file['size']; $origName=basename(str_replace('\\','/',(string)$file['name']));
    if ($replaceId>0) {
        $save=$conn->prepare('UPDATE applicant_uploaded_docs SET FilePath=?,OriginalFileName=?,FileSize=?,UploadedAt=NOW(),IsVerified=0,VerifiedBy=NULL,VerifiedDate=NULL WHERE DocID=? AND ApplicantID=?');
        $save->bind_param('ssiii',$dbPath,$origName,$size,$replaceId,$applicantId);
    } else {
        if ($docType==='' || strlen($docType)>100) throw new RuntimeException('Invalid document type');
        $save=$conn->prepare('INSERT INTO applicant_uploaded_docs (ApplicationID,ApplicantID,DocType,DocCategory,FilePath,OriginalFileName,FileSize) VALUES (NULL,?,?,?,?,?,?)');
        $save->bind_param('issssi',$applicantId,$docType,$docCategory,$dbPath,$origName,$size);
    }
    $save->execute(); $save->close(); $conn->commit();
    echo json_encode(['success'=>true,'message'=>$replaceId>0?'Document replaced successfully.':'Document uploaded successfully.']);
} catch(Throwable $e) {
    $conn->rollback();
    if($fullPath && is_file($fullPath)) unlink($fullPath);
    error_log('Employee document upload failed.');
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Unable to save your document. Please try again.']);
}
