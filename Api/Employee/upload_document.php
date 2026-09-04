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

applicant_doc_ensure_schema($conn);

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

$uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Uploads' . DIRECTORY_SEPARATOR . 'Documents' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
    exit();
}

$newName = 'empdoc_' . $applicantId . '_' . time() . '_' . uniqid('', true) . '.' . $ext;
$fullPath = $uploadDir . $newName;
$dbPath = '../../Uploads/Documents/' . $newName;

if (!move_uploaded_file((string)$file['tmp_name'], $fullPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
    exit();
}

$size = (int)$file['size'];
$ins = $conn->prepare(
    'INSERT INTO applicant_uploaded_docs (ApplicationID, ApplicantID, DocType, DocCategory, FilePath, OriginalFileName, FileSize)
     VALUES (NULL, ?, ?, ?, ?, ?, ?)'
);
$origName = (string)$file['name'];
$ins->bind_param('issssi', $applicantId, $docType, $docCategory, $dbPath, $origName, $size);

if (!$ins->execute()) {
    @unlink($fullPath);
    $err = $conn->error;
    $ins->close();
    echo json_encode(['success' => false, 'message' => 'Failed to save document record: ' . $err]);
    exit();
}
$ins->close();

echo json_encode([
    'success' => true,
    'message' => 'Document uploaded successfully.',
    'document' => [
        'name' => $origName,
        'type' => $docType,
        'path' => $dbPath,
    ],
]);
