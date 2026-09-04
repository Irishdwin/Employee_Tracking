<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'redirect' => '../../index.html']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];

// Get applicant info
$stmt = $conn->prepare("SELECT a.ApplicantID, a.FullName FROM applicants a WHERE a.UserID = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$applicant = $result->fetch_assoc();
$stmt->close();

if (!$applicant) {
    echo json_encode(['success' => false, 'message' => 'Applicant profile not found.']);
    exit();
}

$applicantId = (int)$applicant['ApplicantID'];

// Get application statistics
$stats = [
    'total_applications' => 0,
    'pending' => 0,
    'for_interview' => 0,
    'approved' => 0
];

$statStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN s.Name = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN s.Name = 'For Interview' THEN 1 ELSE 0 END) as for_interview,
        SUM(CASE WHEN ja.ClientApprovalStatus = 'Approved' THEN 1 ELSE 0 END) as approved
    FROM job_applications ja
    LEFT JOIN status s ON ja.StatusID = s.StatusID
    WHERE ja.ApplicantID = ?
");
$statStmt->bind_param("i", $applicantId);
$statStmt->execute();
$statResult = $statStmt->get_result();
if ($row = $statResult->fetch_assoc()) {
    $stats['total_applications'] = (int)$row['total'];
    $stats['pending'] = (int)$row['pending'];
    $stats['for_interview'] = (int)$row['for_interview'];
    $stats['approved'] = (int)$row['approved'];
}
$statStmt->close();

// Get recent applications
$recentStmt = $conn->prepare("
    SELECT 
        ja.ApplicationID,
        jo.Position,
        c.Name AS CompanyName,
        ja.ApplicationDate,
        ja.EndorsedDate,
        COALESCE(s.Name, 'Pending') AS StatusFromTable,
        ja.ClientApprovalStatus
    FROM job_applications ja
    INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
    INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
    LEFT JOIN status s ON ja.StatusID = s.StatusID
    WHERE ja.ApplicantID = ?
    ORDER BY ja.ApplicationDate DESC
    LIMIT 5
");
$recentStmt->bind_param("i", $applicantId);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();
$recentApplications = [];
while ($row = $recentResult->fetch_assoc()) {
    $status = $row['StatusFromTable'];
    if ($row['ClientApprovalStatus'] === 'Approved') {
        $status = 'Approved';
    } elseif (!empty($row['EndorsedDate'])) {
        $status = 'Endorsed';
    }
    $recentApplications[] = [
        'application_id' => (int)$row['ApplicationID'],
        'position' => $row['Position'],
        'company_name' => $row['CompanyName'],
        'application_date' => date('M d, Y', strtotime($row['ApplicationDate'])),
        'status' => $status
    ];
}
$recentStmt->close();

echo json_encode([
    'success' => true,
    'applicant_name' => $applicant['FullName'],
    'stats' => $stats,
    'recent_applications' => $recentApplications
]);
?>
