<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require __DIR__ . '/applicant_doc_fs.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

applicant_doc_ensure_schema($conn);

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare('SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$applicant = $result->fetch_assoc();
$stmt->close();

if (!$applicant) {
    echo json_encode(['success' => false, 'message' => 'Applicant profile not found.']);
    exit();
}

$applicantId = (int)$applicant['ApplicantID'];
$docType = trim($_POST['doc_type'] ?? '');
$applicationId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;

$rawCat = strtolower(trim($_POST['doc_category'] ?? 'general'));
$allowedCats = ['identity', 'academic', 'employment', 'general'];
$docCategory = in_array($rawCat, $allowedCats, true) ? $rawCat : 'general';

if ($docType === '') {
    echo json_encode(['success' => false, 'message' => 'Document type is required.']);
    exit();
}

if ($applicationId <= 0) {
    try {
        $colRes = $conn->query("SHOW COLUMNS FROM applicant_uploaded_docs LIKE 'ApplicationID'");
        $needsAlter = false;
        if ($colRes && $colRes->num_rows > 0) {
            $colRow = $colRes->fetch_assoc();
            $needsAlter = isset($colRow['Null']) && strtoupper((string)$colRow['Null']) === 'NO';
        }
        if ($needsAlter) {
            $conn->query('ALTER TABLE applicant_uploaded_docs MODIFY ApplicationID INT(11) DEFAULT NULL');
        }
    } catch (Throwable $e) {
    }
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error.']);
    exit();
}

$file = $_FILES['document'];
$maxSize = 5 * 1024 * 1024;
$allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
    exit();
}

$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExt, $allowedExts, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG']);
    exit();
}

// Primary storage: Employee_Tracking/Uploads/Documents/
$uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Uploads' . DIRECTORY_SEPARATOR . 'Documents' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = 'doc_' . $applicantId . '_' . time() . '_' . uniqid('', true) . '.' . $fileExt;
$filePath = $uploadDir . $fileName;
$relativePathForDb = '../../Uploads/Documents/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit();
}

$fileSize = (int)$file['size'];

$insertOk = false;
$insertMsg = '';

if ($applicationId > 0) {
    $insertStmt = $conn->prepare(
        'INSERT INTO applicant_uploaded_docs (ApplicationID, ApplicantID, DocType, DocCategory, FilePath, OriginalFileName, FileSize)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStmt->bind_param(
        'iissssi',
        $applicationId,
        $applicantId,
        $docType,
        $docCategory,
        $relativePathForDb,
        $file['name'],
        $fileSize
    );
} else {
    $insertStmt = $conn->prepare(
        'INSERT INTO applicant_uploaded_docs (ApplicationID, ApplicantID, DocType, DocCategory, FilePath, OriginalFileName, FileSize)
         VALUES (NULL, ?, ?, ?, ?, ?, ?)'
    );
    $insertStmt->bind_param(
        'issssi',
        $applicantId,
        $docType,
        $docCategory,
        $relativePathForDb,
        $file['name'],
        $fileSize
    );
}

try {
    if ($insertStmt->execute()) {
        $insertStmt->close();
        $insertOk = true;
    } else {
        unlink($filePath);
        $insertMsg = $conn->error;
        $insertStmt->close();
    }
} catch (mysqli_sql_exception $e) {
    unlink($filePath);
    if (isset($insertStmt)) {
        $insertStmt->close();
    }
    $insertMsg = $e->getMessage();
}

if ($insertOk) {
    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully!',
        'doc_category' => $docCategory,
        'doc_type' => $docType,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save document record: ' . ($insertMsg ?: 'unknown error'),
    ]);
}
