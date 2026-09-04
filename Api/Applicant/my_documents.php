<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'redirect' => '../../index.html']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require __DIR__ . '/applicant_doc_fs.php';

header('Content-Type: application/json');

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

$docStmt = $conn->prepare(
    'SELECT 
        DocID,
        DocType,
        COALESCE(NULLIF(TRIM(DocCategory), \'\'), \'general\') AS DocCategory,
        FilePath,
        OriginalFileName,
        FileSize,
        IsVerified,
        UploadedAt
    FROM applicant_uploaded_docs
    WHERE ApplicantID = ?
    ORDER BY UploadedAt DESC'
);
$docStmt->bind_param('i', $applicantId);
$docStmt->execute();
$docResult = $docStmt->get_result();
$documents = [];

while ($row = $docResult->fetch_assoc()) {
    $cat = strtolower((string)$row['DocCategory']);
    if (!in_array($cat, ['identity', 'academic', 'employment', 'general'], true)) {
        $cat = 'general';
    }
    $documents[] = [
        'doc_id' => (int)$row['DocID'],
        'doc_type' => $row['DocType'],
        'doc_category' => $cat,
        'file_path' => $row['FilePath'],
        'original_filename' => $row['OriginalFileName'],
        'file_size' => isset($row['FileSize']) ? (int)$row['FileSize'] : null,
        'is_verified' => (bool)$row['IsVerified'],
        'uploaded_at' => date('M d, Y', strtotime($row['UploadedAt'])),
    ];
}
$docStmt->close();

echo json_encode([
    'success' => true,
    'documents' => $documents,
]);
