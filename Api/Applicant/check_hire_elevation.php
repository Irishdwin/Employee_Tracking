<?php
/**
 * Detect seamless Applicant -> Employee role elevation after admin hire.
 * Syncs session role from DB and reports whether the celebration modal should show.
 */
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'elevated' => false,
        'role_elevated' => false,
    ]);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

$userId = (int)$_SESSION['user_id'];
$previousRole = trim((string)($_SESSION['role'] ?? ''));

$stmt = $conn->prepare("
    SELECT u.FullName, u.Email, r.RoleName
    FROM users u
    LEFT JOIN roles r ON u.RoleID = r.RolesID
    WHERE u.UsersID = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$roleName = trim((string)($userRow['RoleName'] ?? $previousRole));
if ($roleName !== '') {
    $_SESSION['role'] = $roleName;
}
if (!empty($userRow['FullName'])) {
    $_SESSION['full_name'] = trim((string)$userRow['FullName']);
}
if (!empty($userRow['Email'])) {
    $_SESSION['email'] = trim((string)$userRow['Email']);
}

$roleLower = strtolower($roleName);
$isEmployee = ($roleLower === 'employee');

$hiredApplication = false;
$hiredAppId = null;
$applicantStmt = $conn->prepare('SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1');
$applicantStmt->bind_param('i', $userId);
$applicantStmt->execute();
$applicantRow = $applicantStmt->get_result()->fetch_assoc();
$applicantStmt->close();

if ($applicantRow) {
    $applicantId = (int)$applicantRow['ApplicantID'];
    $hireStmt = $conn->prepare('
        SELECT ApplicationID
        FROM job_applications
        WHERE ApplicantID = ? AND HiredDate IS NOT NULL
        ORDER BY HiredDate DESC
        LIMIT 1
    ');
    $hireStmt->bind_param('i', $applicantId);
    $hireStmt->execute();
    $hireRow = $hireStmt->get_result()->fetch_assoc();
    $hireStmt->close();
    if ($hireRow) {
        $hiredApplication = true;
        $hiredAppId = (int)$hireRow['ApplicationID'];
    }
}

$elevated = $isEmployee || $hiredApplication;
$wasApplicant = strcasecmp($previousRole, 'Applicant') === 0;

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'role' => $roleName,
    'role_elevated' => $elevated,
    'elevated' => $elevated,
    'is_employee' => $isEmployee,
    'has_hired_application' => $hiredApplication,
    'hired_application_id' => $hiredAppId,
    'was_applicant' => $wasApplicant,
    'show_celebration' => $elevated && ($isEmployee || $hiredApplication),
    'employee_dashboard' => '/Employee_Tracking/Html/Employee/employee_dashboard.html',
    'message' => $elevated
        ? 'Your account has been upgraded to an Employee profile.'
        : 'No role elevation detected.',
]);

exit();
