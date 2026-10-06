<?php
require_once __DIR__ . '/../Common/session_init.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/jor_multi_position.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

jor_multi_ensure_schema($conn);

$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$jobOrderId = 0;
if (isset($input['job_order_id'])) {
    $jobOrderId = (int)$input['job_order_id'];
} elseif (isset($input['job_id'])) {
    $jobOrderId = (int)$input['job_id'];
}
$targetDetailId = isset($input['target_detail_id']) ? (int)$input['target_detail_id'] : 0;

if ($jobOrderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Job Order ID']);
    exit();
}

$stmt = $conn->prepare('SELECT ApplicantID, BirthDate, ProfileCompleted FROM applicants WHERE UserID = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$applicant = $result->fetch_assoc();
$stmt->close();

if (!$applicant) {
    echo json_encode(['success' => false, 'message' => 'Applicant profile not found. Please complete your profile first.']);
    exit();
}

$applicantId = (int)$applicant['ApplicantID'];
$birthDateRaw = $applicant['BirthDate'] ?? null;
$profileCompleted = isset($applicant['ProfileCompleted']) ? (int)$applicant['ProfileCompleted'] : 0;

if ($profileCompleted !== 1 || empty($birthDateRaw)) {
    echo json_encode(['success' => false, 'message' => 'Complete your profile first (Date of Birth and eligibility).']);
    exit();
}

$birthDate = new DateTimeImmutable($birthDateRaw);
$today = new DateTimeImmutable('today');
if ($birthDate > $today) {
    echo json_encode(['success' => false, 'message' => 'Invalid Date of Birth.']);
    exit();
}
$age = $today->diff($birthDate)->y;
if ($age < 18) {
    echo json_encode(['success' => false, 'message' => 'You must be at least 18 years old to apply for jobs.']);
    exit();
}

$placements = jor_fetch_placements_for_job_order($conn, $jobOrderId);
$requiresSelection = count($placements) > 1;

if ($requiresSelection && $targetDetailId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select your preferred position and deployment site.',
        'requires_selection' => true,
    ]);
    exit();
}

$selectedPlacement = null;
if ($targetDetailId > 0) {
    foreach ($placements as $p) {
        if ((int)$p['detail_id'] === $targetDetailId) {
            $selectedPlacement = $p;
            break;
        }
    }
    if (!$selectedPlacement) {
        echo json_encode(['success' => false, 'message' => 'Invalid placement selection for this job order.']);
        exit();
    }
}

$checkStmt = $conn->prepare('SELECT ApplicationID FROM job_applications WHERE ApplicantID = ? AND JobOrderID = ? LIMIT 1');
$checkStmt->bind_param('ii', $applicantId, $jobOrderId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    $checkStmt->close();
    echo json_encode(['success' => false, 'message' => 'You have already applied for this position.']);
    exit();
}
$checkStmt->close();

$hasTargetCol = false;
$colCheck = $conn->query("SHOW COLUMNS FROM job_applications LIKE 'target_detail_id'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasTargetCol = true;
}

if ($hasTargetCol && $targetDetailId > 0) {
    $insertStmt = $conn->prepare("
        INSERT INTO job_applications (ApplicantID, JobOrderID, target_detail_id, StatusID, ClientApprovalStatus)
        VALUES (?, ?, ?, NULL, 'Pending')
    ");
    $insertStmt->bind_param('iii', $applicantId, $jobOrderId, $targetDetailId);
} else {
    $insertStmt = $conn->prepare("
        INSERT INTO job_applications (ApplicantID, JobOrderID, StatusID, ClientApprovalStatus)
        VALUES (?, ?, NULL, 'Pending')
    ");
    $insertStmt->bind_param('ii', $applicantId, $jobOrderId);
}

if ($insertStmt->execute()) {
    $applicationId = (int)$conn->insert_id;
    $insertStmt->close();

    require_once __DIR__ . '/../Helpers/application_notify_helper.php';
    require_once __DIR__ . '/../Helpers/notification_helper.php';
    createApplicationNotification($conn, $applicationId, 'application_submitted', 'Application Submitted', 'Your application has been received. You can track its progress in My Applications.');

    $metaStmt = $conn->prepare("
        SELECT a.FullName AS ApplicantName, jo.Position, c.Name AS CompanyName,
               d.deployment_site
        FROM job_applications ja
        INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
        INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
        INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
        LEFT JOIN job_order_details d ON ja.target_detail_id = d.detail_id
        WHERE ja.ApplicationID = ?
        LIMIT 1
    ");
    if ($metaStmt) {
        $metaStmt->bind_param('i', $applicationId);
        $metaStmt->execute();
        $meta = $metaStmt->get_result()->fetch_assoc();
        $metaStmt->close();
        if ($meta) {
            notify_new_job_application(
                $conn,
                $applicationId,
                (string)($meta['ApplicantName'] ?? ''),
                (string)($meta['Position'] ?? ''),
                (string)($meta['CompanyName'] ?? ''),
                isset($meta['deployment_site']) ? (string)$meta['deployment_site'] : null
            );
        }
    }

    $payload = ['success' => true, 'message' => 'Application submitted successfully!', 'application_id' => $applicationId];
    if ($selectedPlacement) {
        $payload['target_placement'] = $selectedPlacement;
    }
    echo json_encode($payload);
} else {
    $insertStmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to submit application: ' . $conn->error]);
}
