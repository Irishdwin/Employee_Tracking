<?php
session_start();

// Only allow logged-in applicants
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'redirect' => '../../index.html']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];

// Get applicant profile for this user
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

// Fetch all applications for this applicant
$sql = "
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
";

$appsStmt = $conn->prepare($sql);
$appsStmt->bind_param("i", $applicantId);
$appsStmt->execute();
$appsResult = $appsStmt->get_result();

$applications = [];
while ($row = $appsResult->fetch_assoc()) {
    $status = $row['StatusFromTable'];
    if ($row['ClientApprovalStatus'] === 'Approved') {
        $status = 'Approved';
    } elseif (!empty($row['EndorsedDate'])) {
        $status = 'Endorsed';
    }
    $applications[] = [
        'application_id'   => (int)$row['ApplicationID'],
        'position'         => $row['Position'],
        'company_name'     => $row['CompanyName'],
        'application_date' => date('M d, Y', strtotime($row['ApplicationDate'])),
        'status'           => $status,
    ];
}
$appsStmt->close();

echo json_encode([
    'success'         => true,
    'applicant_name'  => $applicant['FullName'],
    'applications'    => $applications,
]);

?>

