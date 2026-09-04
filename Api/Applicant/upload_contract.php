<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$applicationId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;

if ($applicationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Application ID is required']);
    exit();
}

// Verify application belongs to this applicant and is approved
$checkStmt = $conn->prepare("
    SELECT ja.ApplicationID, ja.ApplicantID, ja.ClientApprovalStatus
    FROM job_applications ja
    INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
    WHERE ja.ApplicationID = ? AND a.UserID = ?
");
$checkStmt->bind_param("ii", $applicationId, $userId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$application = $checkResult->fetch_assoc();
$checkStmt->close();

if (!$application) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

if ($application['ClientApprovalStatus'] !== 'Approved') {
    echo json_encode(['success' => false, 'message' => 'Contract can only be uploaded after client approval']);
    exit();
}

// Check if contract already uploaded
$contractCheck = $conn->prepare("SELECT EmploymentContractID FROM employmentcontract WHERE EmployeeID IN (SELECT EmployeeID FROM employee WHERE EmployeeID = (SELECT ApplicantID FROM job_applications WHERE ApplicationID = ?)) LIMIT 1");
$contractCheck->bind_param("i", $applicationId);
$contractCheck->execute();
if ($contractCheck->get_result()->num_rows > 0) {
    $contractCheck->close();
    echo json_encode(['success' => false, 'message' => 'Contract already uploaded for this application']);
    exit();
}
$contractCheck->close();

if (!isset($_FILES['contract']) || $_FILES['contract']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
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

// Create uploads directory
$uploadDir = __DIR__ . '/../../uploads/employment_contracts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$fileName = 'contract_' . $applicationId . '_' . time() . '_' . uniqid() . '.' . $fileExt;
$filePath = $uploadDir . $fileName;
$relativePath = 'uploads/employment_contracts/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

// Store contract path in job_applications (we'll add a ContractPath column or use Remarks)
// For now, update Remarks field to indicate contract uploaded
$updateStmt = $conn->prepare("UPDATE job_applications SET Remarks = CONCAT(COALESCE(Remarks, ''), '\nContract uploaded: ', ?) WHERE ApplicationID = ?");
$contractPath = '../../' . $relativePath;
$updateStmt->bind_param("si", $contractPath, $applicationId);

if ($updateStmt->execute()) {
    $updateStmt->close();
    echo json_encode(['success' => true, 'message' => 'Employment contract uploaded successfully! HR will now process your conversion to employee status.']);
} else {
    unlink($filePath);
    $updateStmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to save contract record: ' . $conn->error]);
}
?>
