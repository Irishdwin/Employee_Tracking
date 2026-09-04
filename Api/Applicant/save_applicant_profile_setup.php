<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Applicant'
) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../Helpers/job_categories.php';

$raw = file_get_contents('php://input');
$input = $raw ? (json_decode($raw, true) ?: []) : [];

$first = isset($input['first_name']) ? trim((string)$input['first_name']) : '';
$middle = isset($input['middle_name']) ? trim((string)$input['middle_name']) : '';
$last = isset($input['last_name']) ? trim((string)$input['last_name']) : '';
$hasSuffix = !empty($input['has_suffix']);
$suffix = $hasSuffix ? trim((string)($input['suffix'] ?? '')) : '';

$home = isset($input['home_location']) ? trim((string)$input['home_location']) : '';
$streetAddress = isset($input['street_address']) ? trim((string)$input['street_address']) : '';
$city = isset($input['city']) ? trim((string)$input['city']) : '';
$stateProvince = isset($input['state_province']) ? trim((string)$input['state_province']) : '';
$barangay = isset($input['barangay']) ? trim((string)$input['barangay']) : '';
$postalCode = isset($input['postal_code']) ? trim((string)$input['postal_code']) : '';
$country = 'Philippines';

$hasRecentExperience = isset($input['has_recent_experience']) ? (int)$input['has_recent_experience'] : 0;
$jobTitle = isset($input['job_title']) ? trim((string)$input['job_title']) : '';
$companyName = isset($input['company_name']) ? trim((string)$input['company_name']) : '';

$startMonth = isset($input['experience_start_month']) ? $input['experience_start_month'] : null;
$startYear = isset($input['experience_start_year']) ? $input['experience_start_year'] : null;

$stillInRole = isset($input['still_in_role']) ? (int)$input['still_in_role'] : 0;
$endMonth = isset($input['experience_end_month']) ? $input['experience_end_month'] : null;
$endYear = isset($input['experience_end_year']) ? $input['experience_end_year'] : null;

$preferredClass = isset($input['preferred_classification']) ? trim((string)$input['preferred_classification']) : '';
$preferredSub = isset($input['preferred_sub_classification']) ? trim((string)$input['preferred_sub_classification']) : '';
$jobCategory = job_category_normalize(isset($input['job_category']) ? trim((string)$input['job_category']) : '');
$birthRaw = isset($input['birth_date']) ? trim((string)$input['birth_date']) : '';

if ($birthRaw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthRaw)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid date of birth is required (YYYY-MM-DD).']);
    exit();
}
try {
    $birthDt = new DateTimeImmutable($birthRaw . ' 00:00:00');
    $today = new DateTimeImmutable('today');
    if ($birthDt > $today) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Date of birth cannot be in the future.']);
        exit();
    }
    $age = $today->diff($birthDt)->y;
    if ($age < 18) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You must be at least 18 years old to register.']);
        exit();
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date of birth.']);
    exit();
}

if ($first === '' || mb_strlen($first) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'First name is required.']);
    exit();
}
if ($last === '' || mb_strlen($last) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Last name is required.']);
    exit();
}
if ($streetAddress === '' || $city === '' || $stateProvince === '' || $barangay === '' || $postalCode === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Province, city, barangay, street address, and postal/zip code are required.']);
    exit();
}
$fullAddress = trim($streetAddress . ', ' . $barangay . ', ' . $city . ', ' . $stateProvince . ' ' . $postalCode . ', ' . $country);
if ($home === '') {
    $home = trim($barangay . ', ' . $city . ', ' . $stateProvince . ', ' . $country);
}

if ($jobCategory === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select a job category.']);
    exit();
}

if ($preferredClass === '' || $preferredSub === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preferred classification is required.']);
    exit();
}

if ($hasRecentExperience === 1) {
    if ($jobTitle === '' || $companyName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Job title and company name are required when you have experience.']);
        exit();
    }
    if (!$startMonth || !$startYear) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select your experience start month and year.']);
        exit();
    }
    if ($stillInRole === 0) {
        if (!$endMonth || !$endYear) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select your experience end month and year, or mark “Still in role”.']);
            exit();
        }
    }
}

try {
    require __DIR__ . '/../Db_connection/db_connect.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']);
    exit();
}

function ensureApplicantColumn(mysqli $conn, string $column, string $definition): void
{
    $conn->query("ALTER TABLE applicants ADD COLUMN IF NOT EXISTS " . $column . " " . $definition);
}

ensureApplicantColumn($conn, 'FirstName', 'varchar(50) DEFAULT NULL');
ensureApplicantColumn($conn, 'MiddleName', 'varchar(50) DEFAULT NULL');
ensureApplicantColumn($conn, 'LastName', 'varchar(50) DEFAULT NULL');
ensureApplicantColumn($conn, 'Suffix', 'varchar(20) DEFAULT NULL');
ensureApplicantColumn($conn, 'StreetAddress', 'varchar(255) DEFAULT NULL');
ensureApplicantColumn($conn, 'City', 'varchar(120) DEFAULT NULL');
ensureApplicantColumn($conn, 'StateProvince', 'varchar(120) DEFAULT NULL');
ensureApplicantColumn($conn, 'Barangay', 'varchar(120) DEFAULT NULL');
ensureApplicantColumn($conn, 'PostalCode', 'varchar(40) DEFAULT NULL');
ensureApplicantColumn($conn, 'Country', 'varchar(120) DEFAULT NULL');
ensureApplicantColumn($conn, 'HomeLocation', 'varchar(150) DEFAULT NULL');
ensureApplicantColumn($conn, 'HasRecentExperience', 'tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'JobTitle', 'varchar(150) DEFAULT NULL');
ensureApplicantColumn($conn, 'CompanyName', 'varchar(150) DEFAULT NULL');
ensureApplicantColumn($conn, 'ExperienceStartMonth', 'int(11) DEFAULT NULL');
ensureApplicantColumn($conn, 'ExperienceStartYear', 'int(11) DEFAULT NULL');
ensureApplicantColumn($conn, 'ExperienceEndMonth', 'int(11) DEFAULT NULL');
ensureApplicantColumn($conn, 'ExperienceEndYear', 'int(11) DEFAULT NULL');
ensureApplicantColumn($conn, 'StillInRole', 'tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'PreferredClassification', 'varchar(100) DEFAULT NULL');
ensureApplicantColumn($conn, 'PreferredSubClassification', 'varchar(100) DEFAULT NULL');
ensureApplicantColumn($conn, 'ProfileDetailsCompleted', 'tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'ProfileVisibility', 'varchar(20) DEFAULT NULL');
ensureApplicantColumn($conn, 'ProfileVisibilityCompleted', 'tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'BirthDate', 'date DEFAULT NULL');
ensureApplicantColumn($conn, 'ProfileStatus', 'tinyint NOT NULL DEFAULT 0');
ensureApplicantColumn($conn, 'JobCategory', 'varchar(255) DEFAULT NULL');

$conn->query("CREATE TABLE IF NOT EXISTS applicant_profiles (
    UserID INT NOT NULL PRIMARY KEY,
    FirstName VARCHAR(50) DEFAULT NULL,
    MiddleName VARCHAR(50) DEFAULT NULL,
    LastName VARCHAR(50) DEFAULT NULL,
    Suffix VARCHAR(20) DEFAULT NULL,
    BirthDate DATE DEFAULT NULL,
    HomeLocation VARCHAR(150) DEFAULT NULL,
    PreferredClassification VARCHAR(100) DEFAULT NULL,
    PreferredSubClassification VARCHAR(100) DEFAULT NULL,
    ProfileDetailsCompleted TINYINT(1) DEFAULT 0,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$profileVisibility = isset($input['profile_visibility']) ? trim((string)$input['profile_visibility']) : '';
$allowedVis = ['Public', 'Standard', 'Limited', 'Hidden'];
if ($profileVisibility === '' || !in_array($profileVisibility, $allowedVis, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select a profile visibility option.']);
    exit();
}

$nameParts = array_filter([$first, $middle !== '' ? $middle : null, $last, $suffix !== '' ? $suffix : null]);
$fullName = implode(' ', $nameParts);

$conn->begin_transaction();
try {
    $roleStmt = $conn->prepare("SELECT RolesID FROM roles WHERE RoleName = 'Applicant' LIMIT 1");
    $roleStmt->execute();
    $roleRes = $roleStmt->get_result();
    $roleRow = $roleRes->fetch_assoc();
    $roleStmt->close();
    $applicantRoleId = $roleRow ? (int)$roleRow['RolesID'] : 0;
    if ($applicantRoleId <= 0) {
        throw new RuntimeException('Applicant role missing');
    }

    $uStmt = $conn->prepare("UPDATE users SET FullName = ?, RoleID = ? WHERE UsersID = ?");
    $uStmt->bind_param("sii", $fullName, $applicantRoleId, $userId);
    $uStmt->execute();
    $uStmt->close();

    $stmt = $conn->prepare("
        UPDATE applicants SET
            FullName = ?,
            Address = ?,
            FirstName = ?,
            MiddleName = ?,
            LastName = ?,
            Suffix = ?,
            BirthDate = ?,
            HomeLocation = ?,
            StreetAddress = ?,
            City = ?,
            StateProvince = ?,
            Barangay = ?,
            PostalCode = ?,
            Country = ?,
            HasRecentExperience = ?,
            JobTitle = ?,
            CompanyName = ?,
            ExperienceStartMonth = ?,
            ExperienceStartYear = ?,
            ExperienceEndMonth = ?,
            ExperienceEndYear = ?,
            StillInRole = ?,
            PreferredClassification = ?,
            PreferredSubClassification = ?,
            JobCategory = ?,
            ProfileVisibility = ?,
            ProfileDetailsCompleted = 1,
            ProfileVisibilityCompleted = 1,
            ProfileCompleted = 1,
            ProfileStatus = 2
        WHERE UserID = ?
    ");

    $jobTitleDb = $hasRecentExperience === 1 ? $jobTitle : null;
    $companyNameDb = $hasRecentExperience === 1 ? $companyName : null;
    $startMonthDb = $hasRecentExperience === 1 ? (int)$startMonth : null;
    $startYearDb = $hasRecentExperience === 1 ? (int)$startYear : null;
    $endMonthDb = ($hasRecentExperience === 1 && $stillInRole === 0) ? (int)$endMonth : null;
    $endYearDb = ($hasRecentExperience === 1 && $stillInRole === 0) ? (int)$endYear : null;
    $middleVal = $middle !== '' ? $middle : null;
    $suffixVal = $suffix !== '' ? $suffix : null;

    $stmt->bind_param(
        "ssssssssssssssissiiiiissssi",
        $fullName,
        $fullAddress,
        $first,
        $middleVal,
        $last,
        $suffixVal,
        $birthRaw,
        $home,
        $streetAddress,
        $city,
        $stateProvince,
        $barangay,
        $postalCode,
        $country,
        $hasRecentExperience,
        $jobTitleDb,
        $companyNameDb,
        $startMonthDb,
        $startYearDb,
        $endMonthDb,
        $endYearDb,
        $stillInRole,
        $preferredClass,
        $preferredSub,
        $jobCategory,
        $profileVisibility,
        $userId
    );
    $stmt->execute();
    $stmt->close();

    $profStmt = $conn->prepare("
        INSERT INTO applicant_profiles (
            UserID, FirstName, MiddleName, LastName, Suffix, BirthDate, HomeLocation,
            PreferredClassification, PreferredSubClassification, ProfileDetailsCompleted
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            FirstName = VALUES(FirstName),
            MiddleName = VALUES(MiddleName),
            LastName = VALUES(LastName),
            Suffix = VALUES(Suffix),
            BirthDate = VALUES(BirthDate),
            HomeLocation = VALUES(HomeLocation),
            PreferredClassification = VALUES(PreferredClassification),
            PreferredSubClassification = VALUES(PreferredSubClassification),
            ProfileDetailsCompleted = 1
    ");
    $profStmt->bind_param(
        "issssssss",
        $userId,
        $first,
        $middleVal,
        $last,
        $suffixVal,
        $birthRaw,
        $home,
        $preferredClass,
        $preferredSub
    );
    $profStmt->execute();
    $profStmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'redirect' => '../../Html/Applicant/applicant_landing.html'
    ]);
    exit();
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save profile details: ' . $e->getMessage()]);
    exit();
}