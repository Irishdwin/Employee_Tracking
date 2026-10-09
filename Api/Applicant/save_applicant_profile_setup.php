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
require_once __DIR__ . '/../Helpers/job_hierarchy.php';

$raw = file_get_contents('php://input');
$input = $raw ? (json_decode($raw, true) ?: []) : [];
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/applicant_profile_validation.php';
require_once __DIR__.'/../Helpers/applicant_required_documents.php';
$missingDocuments=applicant_missing_documents($conn,$userId);
if($missingDocuments){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Upload your résumé and valid ID before saving your profile.','missing_documents'=>$missingDocuments]);exit;}

try{$fieldErrors=applicant_profile_errors($input);}
catch(Throwable $e){error_log('Profile validation: '.$e->getMessage());http_response_code(503);echo json_encode(['success'=>false,'message'=>'Profile validation is temporarily unavailable. Please retry.']);exit;}
if($fieldErrors){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Please complete the highlighted required fields.','errors'=>$fieldErrors]);exit;}
try{$hierarchy=jh_resolve_profile($conn,$input);}catch(DomainException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage(),'errors'=>['category_id'=>'Please verify your Job Category.','classification_id'=>'Please select a valid Classification for this Job Category.','sub_classification_id'=>'Please select a valid Sub-classification for this Classification.']]);exit;}

$first = isset($input['first_name']) ? trim((string)$input['first_name']) : '';
$middle = isset($input['middle_name']) ? trim((string)$input['middle_name']) : '';
$last = isset($input['last_name']) ? trim((string)$input['last_name']) : '';
$hasSuffix = !empty($input['has_suffix']);
$suffix = $hasSuffix ? trim((string)($input['suffix'] ?? '')) : '';

$home = isset($input['home_location']) ? trim((string)$input['home_location']) : '';
$streetAddress = isset($input['street_address']) ? trim((string)$input['street_address']) : '';
$region = isset($input['region']) ? trim((string)$input['region']) : '';
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

$preferredClass = $hierarchy['classification_name'];
$preferredSub = $hierarchy['sub_classification_name'];
$jobCategory = $hierarchy['category_name'];
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
require_once __DIR__.'/../Helpers/psgc_address.php';
try {
    $codes=$input['address_psgc']??null;
    if(!is_array($codes))throw new DomainException('Please select your PSGC address locations.');
    $canonical=psgc_validate_address($codes);
    $region=$canonical['region'];$stateProvince=$canonical['state_province'];$city=$canonical['city'];$barangay=$canonical['barangay'];
    $addressPSGC=json_encode($canonical['psgc'],JSON_UNESCAPED_UNICODE);
    if($streetAddress==='' || mb_strlen($streetAddress)>255 || preg_match('/[\x00-\x1f<>]/u',$streetAddress))throw new DomainException('Enter a valid street address (maximum 255 characters).');
    if(!preg_match('/^\d{4}$/',$postalCode))throw new DomainException('Enter a four-digit Philippine postal/ZIP code.');
} catch(DomainException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);exit;}
catch(Throwable $e){error_log('PSGC address validation: '.$e->getMessage());http_response_code(503);echo json_encode(['success'=>false,'message'=>'Address locations are temporarily unavailable. Please retry.']);exit;}
$fullAddress=implode(', ',array_filter([$streetAddress,$barangay,$city,$stateProvince,$region,$postalCode,$country],fn($v)=>$v!==''));
$home=implode(', ',array_filter([$barangay,$city,$stateProvince,$country],fn($v)=>$v!==''));

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
    require_once __DIR__ . '/../Db_connection/db_connect.php';
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
ensureApplicantColumn($conn, 'Region', 'varchar(120) DEFAULT NULL');
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

// Profile Visibility now has its own dedicated settings page
// (Html/Applicant/applicant_visibility_settings.html +
// Api/Applicant/save_applicant_visibility.php) and is no longer part of
// this form. If this save doesn't include it, preserve whatever the
// applicant already has (so re-saving this form never silently resets a
// choice made on the dedicated settings page) — only default to "Public"
// for a genuinely first-time save with nothing set yet.
// ProfileVisibilityCompleted below is still set to 1 unconditionally, since
// applicant_login.php / verify_signin_code.php / google_auth_rbac.php all
// gate onboarding completion on that flag.
$allowedVis = ['Public', 'Standard', 'Limited', 'Hidden'];
$profileVisibility = isset($input['profile_visibility']) ? trim((string)$input['profile_visibility']) : '';
if (!in_array($profileVisibility, $allowedVis, true)) {
    $existingVisStmt = $conn->prepare("SELECT ProfileVisibility FROM applicants WHERE UserID = ? LIMIT 1");
    $existingVisStmt->bind_param('i', $userId);
    $existingVisStmt->execute();
    $existingVisRow = $existingVisStmt->get_result()->fetch_assoc();
    $existingVisStmt->close();
    $existingVis = trim((string)($existingVisRow['ProfileVisibility'] ?? ''));
    $profileVisibility = in_array($existingVis, $allowedVis, true) ? $existingVis : 'Public';
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
            Region = ?,
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
        "sssssssssssssssissiiiiissssi",
        $fullName,
        $fullAddress,
        $first,
        $middleVal,
        $last,
        $suffixVal,
        $birthRaw,
        $home,
        $streetAddress,
        $region,
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

    $gender=$input['gender'];$sexStmt=$conn->prepare('UPDATE applicants SET Gender=? WHERE UserID=?');$sexStmt->bind_param('si',$gender,$userId);$sexStmt->execute();$sexStmt->close();

    $addressStmt=$conn->prepare('UPDATE applicants SET AddressPSGC=? WHERE UserID=?');
    $addressStmt->bind_param('si',$addressPSGC,$userId);$addressStmt->execute();$addressStmt->close();

    $hstmt=$conn->prepare('UPDATE applicants SET category_id=?,classification_id=?,sub_classification_id=? WHERE UserID=?');
    $hstmt->bind_param('iiii',$hierarchy['category_id'],$hierarchy['classification_id'],$hierarchy['sub_classification_id'],$userId);$hstmt->execute();$hstmt->close();

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
