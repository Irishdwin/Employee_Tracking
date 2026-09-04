<?php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

$raw = file_get_contents('php://input');
$input = $raw ? (json_decode($raw, true) ?: []) : [];

$firstName = isset($input['first_name']) ? trim((string)$input['first_name']) : trim($_POST['first_name'] ?? '');
$middleName = isset($input['middle_name']) ? trim((string)$input['middle_name']) : trim($_POST['middle_name'] ?? '');
$lastName = isset($input['last_name']) ? trim((string)$input['last_name']) : trim($_POST['last_name'] ?? '');
$hasSuffix = !empty($input['has_suffix']) || !empty($_POST['has_suffix']);
$suffix = $hasSuffix ? (isset($input['suffix']) ? trim((string)$input['suffix']) : trim($_POST['suffix'] ?? '')) : '';

$mobile = isset($input['mobile_number']) ? trim((string)$input['mobile_number']) : trim($_POST['mobile_number'] ?? '');
$birthDateRaw = isset($input['birth_date']) ? trim((string)$input['birth_date']) : trim($_POST['birth_date'] ?? '');

if ($firstName === '' || mb_strlen($firstName) < 2) {
    echo json_encode(['success' => false, 'message' => 'First Name is required.']);
    exit();
}
if ($lastName === '' || mb_strlen($lastName) < 2) {
    echo json_encode(['success' => false, 'message' => 'Last Name is required.']);
    exit();
}

if ($mobile === '') {
    echo json_encode(['success' => false, 'message' => 'Mobile Number is required.']);
    exit();
}

if (!preg_match('/^(09|\+639|639)\d{9}$/', $mobile)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid Philippine mobile number.']);
    exit();
}

$digitsOnly = preg_replace('/\D/', '', $mobile);
if (preg_match('/^(\d)\1+$/', $digitsOnly)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid Philippine mobile number.']);
    exit();
}

if ($birthDateRaw === '' || strtotime($birthDateRaw) === false) {
    echo json_encode(['success' => false, 'message' => 'Date of Birth is required and must be valid.']);
    exit();
}

$birthDate = date('Y-m-d', strtotime($birthDateRaw));
$today = new DateTimeImmutable('today');
$dob = new DateTimeImmutable($birthDate);

if ($dob > $today) {
    echo json_encode(['success' => false, 'message' => 'Date of Birth cannot be in the future.']);
    exit();
}

$age = $today->diff($dob)->y;
if ($age < 18) {
    echo json_encode(['success' => false, 'message' => 'You must be at least 18 years old to apply for jobs.']);
    exit();
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
} catch (Throwable $e) {
    error_log('complete_applicant_profile db: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable. Please try again later.']);
    exit();
}

// Assemble full name cleanly
$nameParts = array_filter([$firstName, $middleName !== '' ? $middleName : null, $lastName, $suffix !== '' ? $suffix : null]);
$fullName = implode(' ', $nameParts);

$conn->begin_transaction();
try {
    $conn->query("ALTER TABLE applicants ADD COLUMN IF NOT EXISTS FirstName VARCHAR(50) DEFAULT NULL");
    $conn->query("ALTER TABLE applicants ADD COLUMN IF NOT EXISTS MiddleName VARCHAR(50) DEFAULT NULL");
    $conn->query("ALTER TABLE applicants ADD COLUMN IF NOT EXISTS LastName VARCHAR(50) DEFAULT NULL");
    $conn->query("ALTER TABLE applicants ADD COLUMN IF NOT EXISTS Suffix VARCHAR(20) DEFAULT NULL");

    $uStmt = $conn->prepare("UPDATE users SET FullName = ? WHERE UsersID = ?");
    $uStmt->bind_param("si", $fullName, $userId);
    $uStmt->execute();
    $uStmt->close();

    $appStmt = $conn->prepare("
        UPDATE applicants
        SET FullName = ?, FirstName = ?, MiddleName = ?, LastName = ?, Suffix = ?, MobileNumber = ?, BirthDate = ?, ProfileCompleted = 1
        WHERE UserID = ?
    ");
    $middleVal = $middleName !== '' ? $middleName : null;
    $suffixVal = $suffix !== '' ? $suffix : null;
    $appStmt->bind_param("sssssssi", $fullName, $firstName, $middleVal, $lastName, $suffixVal, $mobile, $birthDate, $userId);
    $appStmt->execute();
    $appStmt->close();

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Eligibility saved.',
        'redirect' => '/Employee_Tracking/Html/Applicant/applicant_terms.html'
    ]);
    exit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('complete_applicant_profile: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to complete profile. Please try again.']);
    exit();
}