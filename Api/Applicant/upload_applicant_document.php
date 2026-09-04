<?php
/**
 * Quick document upload API for applicant profile key documents.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$docType = trim((string)($_POST['document_type'] ?? 'General'));
$docCategory = trim((string)($_POST['document_category'] ?? 'general'));

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error occurred.']);
    exit();
}

$file = $_FILES['document'];
$originalName = basename($file['name']);
$fileSize = (int)$file['size'];
$tmpName = $file['tmp_name'];

$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];

if (!in_array($ext, $allowedExtensions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file format. Allowed: PDF, Word, JPG, PNG, WEBP.']);
    exit();
}

if ($fileSize > 15 * 1024 * 1024) { // 15MB limit
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 15MB limit.']);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';

    // Get ApplicantID for this user
    $appStmt = $conn->prepare("SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1");
    $appStmt->bind_param("i", $userId);
    $appStmt->execute();
    $appRes = $appStmt->get_result();
    $appRow = $appRes->fetch_assoc();
    $appStmt->close();

    if (!$appRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Applicant record not found.']);
        exit();
    }
    $applicantId = (int)$appRow['ApplicantID'];

    $uploadDir = __DIR__ . '/../../Uploads/Documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFileName = sprintf('doc_%d_%d_%s.%s', $applicantId, time(), bin2hex(random_bytes(4)), $ext);
    $destination = $uploadDir . $newFileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file on server.']);
        exit();
    }

    $dbFilePath = '../../Uploads/Documents/' . $newFileName;

    // Insert or record upload
    $insStmt = $conn->prepare("
        INSERT INTO applicant_uploaded_docs (ApplicantID, DocType, DocCategory, FilePath, OriginalFileName, FileSize, UploadedAt)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $insStmt->bind_param("issssi", $applicantId, $docType, $docCategory, $dbFilePath, $originalName, $fileSize);
    $insStmt->execute();
    $newDocId = (int)$conn->insert_id;
    $insStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully.',
        'doc_id' => $newDocId,
        'original_filename' => $originalName
    ]);
    exit();

} catch (Throwable $e) {
    error_log('upload_applicant_document error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while saving document: ' . $e->getMessage()]);
    exit();
}