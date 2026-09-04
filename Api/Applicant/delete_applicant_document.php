<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require __DIR__ . '/applicant_doc_fs.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$docId = isset($data['doc_id']) ? (int)$data['doc_id'] : (int)($_POST['doc_id'] ?? 0);

if ($docId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid document.']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$applicant) {
    echo json_encode(['success' => false, 'message' => 'Applicant not found.']);
    exit();
}

$applicantId = (int)$applicant['ApplicantID'];

$q = $conn->prepare(
    "SELECT DocID, FilePath FROM applicant_uploaded_docs WHERE DocID = ? AND ApplicantID = ? LIMIT 1"
);
$q->bind_param('ii', $docId, $applicantId);
$q->execute();
$row = $q->get_result()->fetch_assoc();
$q->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Document not found.']);
    exit();
}

$abs = applicant_doc_absolute_path($row['FilePath']);
if ($abs && is_file($abs)) {
    @unlink($abs);
}

$del = $conn->prepare("DELETE FROM applicant_uploaded_docs WHERE DocID = ? AND ApplicantID = ? LIMIT 1");
$del->bind_param('ii', $docId, $applicantId);
$ok = $del->execute();
$del->close();

echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Deleted.' : 'Could not delete record.']);
