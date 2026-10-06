<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$allowedRoles = ['Admin', 'HR'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$applicationId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;

if ($applicationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Application ID is required']);
    exit();
}

// Contract metadata (agency uploads)
$contractTypeInput = isset($_POST['contract_type']) ? strtolower(trim((string)$_POST['contract_type'])) : 'open';
$contractMonths = isset($_POST['contract_months']) ? (int)$_POST['contract_months'] : 0;
$signedDateRaw = isset($_POST['signed_date']) ? trim((string)$_POST['signed_date']) : '';

$contractYears = filter_var($_POST['contract_years'] ?? null, FILTER_VALIDATE_INT);
$contractMonths = filter_var($_POST['contract_months'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($contractTypeInput, ['open', 'expiring'], true)) {
    echo json_encode(['success'=>false, 'message'=>'Choose a valid contract type.']); exit();
}
if ($contractTypeInput === 'open') { $contractYears = 0; $contractMonths = 0; }
$contractType = ($contractTypeInput === 'expiring') ? 'Expiring Contract' : 'Open Contract';

if ($contractYears === false || $contractYears === null || $contractYears < 0 || $contractYears > 100 || $contractMonths === false || $contractMonths === null || $contractMonths < 0 || $contractMonths > 11 || ($contractTypeInput === 'expiring' && $contractYears * 12 + $contractMonths <= 0)) {
    echo json_encode(['success' => false, 'message' => 'Provide a positive duration: years 0–100 and months 0–11.']);
    exit();
}

if ($signedDateRaw === '' || strtotime($signedDateRaw) === false) {
    echo json_encode(['success' => false, 'message' => 'Signed date is required and must be a valid date.']);
    exit();
}

$signedDate = date('Y-m-d', strtotime($signedDateRaw));
$expiryDate = null;
if ($contractTypeInput === 'expiring') {
    $totalMonths = ($contractYears * 12) + $contractMonths;

    $expiryDate = date('Y-m-d', strtotime($signedDate . ' +' . $totalMonths . ' months'));
}

// Verify application exists and is client-approved (not yet hired)
$checkStmt = $conn->prepare("
    SELECT ja.ApplicationID, ja.ApplicantID, ja.ClientApprovalStatus, ja.HiredDate
    , ja.Remarks
    FROM job_applications ja
    WHERE ja.ApplicationID = ?
");
$checkStmt->bind_param("i", $applicationId);
$checkStmt->execute();
$appResult = $checkStmt->get_result();
$application = $appResult->fetch_assoc();
$checkStmt->close();

if (!$application) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

if ($application['ClientApprovalStatus'] !== 'Approved') {
    echo json_encode(['success' => false, 'message' => 'Contract can only be uploaded for client-approved applications']);
    exit();
}

if (!empty($application['HiredDate'])) {
    echo json_encode(['success' => false, 'message' => 'This applicant has already been converted to employee']);
    exit();
}

if (!isset($_FILES['contract']) || $_FILES['contract']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a contract file to upload']);
    exit();
}

$file = $_FILES['contract'];
$maxSize = 10 * 1024 * 1024; // 10MB
$allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit();
}

$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExt, $allowedExts)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, JPG, PNG']);
    exit();
}

$uploadDir = __DIR__ . '/../../uploads/employment_contracts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = 'contract_hr_' . $applicationId . '_' . time() . '_' . uniqid() . '.' . $fileExt;
$filePath = $uploadDir . $fileName;
$relativePath = 'uploads/employment_contracts/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

// Store contract metadata + file URL in job_applications.Remarks
$uploadedAt = date('Y-m-d H:i:s');
$contractPathValue = '../../' . $relativePath; // front-end can open directly

$remarksExisting = isset($application['Remarks']) ? (string)$application['Remarks'] : '';

// Remove previous contract entry if any (single-line entry begins with 'Contract uploaded:')
$pos = strpos($remarksExisting, 'Contract uploaded:');
if ($pos !== false) {
    $remarksExisting = trim(substr($remarksExisting, 0, $pos));
}

$entry = 'Contract uploaded: type=' . $contractType .
    '; years=' . $contractYears .
    '; months=' . $contractMonths .
    '; signed_date=' . $signedDate .
    '; expiry_date=' . ($expiryDate ?: '') .
    '; uploaded_at=' . $uploadedAt .
    '; path=' . $contractPathValue;

$remarksNew = $remarksExisting !== '' ? ($remarksExisting . "\n" . $entry) : $entry;

$updateStmt = $conn->prepare("UPDATE job_applications SET Remarks = ? WHERE ApplicationID = ?");
$updateStmt->bind_param("si", $remarksNew, $applicationId);

if ($updateStmt->execute()) {
    $updateStmt->close();
    echo json_encode([
        'success' => true,
        'message' => 'Employment contract uploaded successfully. You can now convert this applicant to employee.',
        'contract' => [
            'type' => $contractType,
            'years' => $contractYears,
            'months' => $contractMonths,
            'signed_date' => $signedDate,
            'expiry_date' => $expiryDate,
            'uploaded_at' => $uploadedAt,
            'path' => $contractPathValue
        ]
    ]);
} else {
    unlink($filePath);
    $updateStmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to save contract record: ' . $conn->error]);
}
