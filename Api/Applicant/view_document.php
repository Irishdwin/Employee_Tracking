<?php
require_once __DIR__ . '/../Common/session_init.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    http_response_code(401);
    echo 'Unauthorized';
    exit();
}

$userId = (int)$_SESSION['user_id'];
$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$docType = isset($_GET['type']) ? trim((string)$_GET['type']) : '';

if ($docId <= 0 && $docType === '') {
    http_response_code(400);
    echo 'Invalid document reference.';
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';

    // Verify applicant owns the record
    $appStmt = $conn->prepare("SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1");
    $appStmt->bind_param("i", $userId);
    $appStmt->execute();
    $appRes = $appStmt->get_result();
    $appRow = $appRes->fetch_assoc();
    $appStmt->close();

    if (!$appRow) {
        http_response_code(403);
        echo 'Applicant record not found.';
        exit();
    }
    $applicantId = (int)$appRow['ApplicantID'];

    if ($docId > 0) {
        $docStmt = $conn->prepare("SELECT FilePath, OriginalFileName, DocType FROM applicant_uploaded_docs WHERE DocID = ? AND ApplicantID = ? LIMIT 1");
        $docStmt->bind_param("ii", $docId, $applicantId);
    } else {
        $docStmt = $conn->prepare("SELECT FilePath, OriginalFileName, DocType FROM applicant_uploaded_docs WHERE ApplicantID = ? AND DocType = ? ORDER BY DocID DESC LIMIT 1");
        $docStmt->bind_param("is", $applicantId, $docType);
    }
    $docStmt->execute();
    $docRes = $docStmt->get_result();
    $doc = $docRes->fetch_assoc();
    $docStmt->close();

    if (!$doc) {
        http_response_code(404);
        echo 'Document not found or unauthorized.';
        exit();
    }

    $relativePath = $doc['FilePath'];
    $fullPath = __DIR__ . '/../../' . ltrim(str_replace(['../../', '../'], '', $relativePath), '/');
    if (!file_exists($fullPath)) {
        $fullPath = __DIR__ . '/' . $relativePath;
        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo 'File on disk not found.';
            exit();
        }
    }

    $filename = $doc['OriginalFileName'] ?: basename($fullPath);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $mime = 'application/octet-stream';
    switch ($ext) {
        case 'pdf':
            $mime = 'application/pdf';
            break;
        case 'jpg':
        case 'jpeg':
            $mime = 'image/jpeg';
            break;
        case 'png':
            $mime = 'image/png';
            break;
        case 'webp':
            $mime = 'image/webp';
            break;
        case 'doc':
            $mime = 'application/msword';
            break;
        case 'docx':
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            break;
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit();

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error loading document.';
    exit();
}