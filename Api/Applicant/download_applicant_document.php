<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    header('HTTP/1.0 403 Forbidden');
    exit('Unauthorized');
}

require __DIR__ . '/../Db_connection/db_connect.php';
require __DIR__ . '/applicant_doc_fs.php';

$docId = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0;
if ($docId <= 0) {
    header('HTTP/1.0 400 Bad Request');
    exit('Invalid request');
}

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$applicant) {
    header('HTTP/1.0 404 Not Found');
    exit('Not found');
}

$applicantId = (int)$applicant['ApplicantID'];

$q = $conn->prepare(
    "SELECT FilePath, OriginalFileName FROM applicant_uploaded_docs WHERE DocID = ? AND ApplicantID = ? LIMIT 1"
);
$q->bind_param('ii', $docId, $applicantId);
$q->execute();
$row = $q->get_result()->fetch_assoc();
$q->close();

if (!$row) {
    header('HTTP/1.0 404 Not Found');
    exit('Not found');
}

$abs = applicant_doc_absolute_path($row['FilePath']);
if (!$abs || !is_file($abs) || !is_readable($abs)) {
    header('HTTP/1.0 404 Not Found');
    exit('File missing');
}

$orig = $row['OriginalFileName'] ?: basename($abs);
$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
$map = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
];
if (isset($map[$ext])) {
    $mime = $map[$ext];
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Content-Disposition: attachment; filename="' . basename($orig) . '"');
header('Cache-Control: private, max-age=0');
readfile($abs);
exit();
