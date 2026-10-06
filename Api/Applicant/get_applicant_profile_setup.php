<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json');

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
ensureApplicantColumn($conn, 'ProfileVisibility', 'varchar(20) DEFAULT NULL');
ensureApplicantColumn($conn, 'ProfileDetailsCompleted', 'tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'ProfileVisibilityCompleted', 'tinyint(1) DEFAULT 0');
ensureApplicantColumn($conn, 'BirthDate', 'date DEFAULT NULL');
ensureApplicantColumn($conn, 'JobCategory', 'varchar(255) DEFAULT NULL');

$stmt = $conn->prepare("
    SELECT
        FirstName,
        MiddleName,
        LastName,
        Suffix,
        COALESCE(NULLIF(TRIM(applicants.FullName), ''), NULLIF(TRIM(users.FullName), '')) AS FullName,
        BirthDate,
        StreetAddress,
        Region,
        AddressPSGC,
        City,
        StateProvince,
        Barangay,
        PostalCode,
        Country,
        HomeLocation,
        Address,
        HasRecentExperience,
        JobTitle,
        CompanyName,
        ExperienceStartMonth,
        ExperienceStartYear,
        ExperienceEndMonth,
        ExperienceEndYear,
        StillInRole,
        PreferredClassification,
        PreferredSubClassification,
        JobCategory,
        category_id,classification_id,sub_classification_id,
        ProfileVisibility,
        ProfileDetailsCompleted,
        ProfileVisibilityCompleted
    FROM applicants
    LEFT JOIN users ON users.UsersID = applicants.UserID
    WHERE applicants.UserID = ?
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Applicant profile not found.']);
    exit();
}

$detailsCompleted = isset($row['ProfileDetailsCompleted']) ? (int)$row['ProfileDetailsCompleted'] === 1 : false;
$visibilityCompleted = isset($row['ProfileVisibilityCompleted']) ? (int)$row['ProfileVisibilityCompleted'] === 1 : false;

$firstName = isset($row['FirstName']) ? trim((string)$row['FirstName']) : '';
$middleName = isset($row['MiddleName']) ? trim((string)$row['MiddleName']) : '';
$lastName = isset($row['LastName']) ? trim((string)$row['LastName']) : '';
$suffix = isset($row['Suffix']) ? trim((string)$row['Suffix']) : '';

if ($firstName === '' && $lastName === '') {
    $fullName = isset($row['FullName']) ? trim((string)$row['FullName']) : '';
    if ($fullName !== '') {
        $parts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts) {
            $firstName = (string)$parts[0];
            if (count($parts) > 2) {
                $lastName = (string)$parts[count($parts) - 1];
                $commonSuffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
                if (in_array($lastName, $commonSuffixes, true)) {
                    $suffix = $lastName;
                    $lastName = (string)$parts[count($parts) - 2];
                    $middleName = implode(' ', array_slice($parts, 1, count($parts) - 3));
                } else {
                    $middleName = implode(' ', array_slice($parts, 1, count($parts) - 2));
                }
            } elseif (count($parts) === 2) {
                $lastName = (string)$parts[1];
            }
        }
    }
}

// Fetch uploaded documents for this applicant
$docs = [];
$docStmt = $conn->prepare("SELECT d.DocID, d.DocType, d.FilePath, d.OriginalFileName FROM applicant_uploaded_docs d JOIN applicants a ON d.ApplicantID = a.ApplicantID WHERE a.UserID = ?");
$docStmt->bind_param("i", $userId);
$docStmt->execute();
$docRes = $docStmt->get_result();
while ($dRow = $docRes->fetch_assoc()) {
    $docs[] = $dRow;
}
$docStmt->close();

// IDs survive Admin renames; legacy text remains available for records awaiting mapping.
require_once __DIR__.'/../Helpers/job_hierarchy.php';
if($row['sub_classification_id']){
    $canonical=jh_rows($conn,'SELECT c.category_name,k.classification_name,s.sub_classification_name FROM job_sub_classifications s JOIN job_classifications k ON k.id=s.classification_id JOIN job_categories c ON c.id=k.category_id WHERE c.id=? AND k.id=? AND s.id=?','iii',[(int)$row['category_id'],(int)$row['classification_id'],(int)$row['sub_classification_id']]);
    if($canonical){$row['JobCategory']=$canonical[0]['category_name'];$row['PreferredClassification']=$canonical[0]['classification_name'];$row['PreferredSubClassification']=$canonical[0]['sub_classification_name'];}
}
require_once __DIR__.'/../Helpers/psgc_address.php';
$addressCodes=json_decode($row['AddressPSGC']??'',true);
if(is_array($addressCodes)){
    // The earlier snapshot attached some independent cities to a province.
    // Restore their official path without rewriting any saved address text.
    try{
        $savedCity=psgc_record((string)($addressCodes['city']??''));
        if($savedCity && ($addressCodes['region']??'')===$savedCity['region']){
            $restoredCodes=$addressCodes;$restoredCodes['province']=$savedCity['province'];
            $addressCodes=psgc_validate_address($restoredCodes)['psgc'];
        }
    }catch(Throwable $e){error_log('PSGC address restoration: '.$e->getMessage());$addressCodes=null;}
}
if(!$addressCodes){
    try{$addressCodes=psgc_resolve_legacy(['region'=>$row['Region']??'','state_province'=>$row['StateProvince']??'','city'=>$row['City']??'','barangay'=>$row['Barangay']??'']);}
    catch(Throwable $e){error_log('Legacy address resolution: '.$e->getMessage());$addressCodes=null;}
}

echo json_encode([
    'success' => true,
    'details_completed' => $detailsCompleted,
    'visibility_completed' => $visibilityCompleted,
    'profile' => [
        'category_id' => $row['category_id'],
        'classification_id' => $row['classification_id'],
        'sub_classification_id' => $row['sub_classification_id'],
        'first_name' => $firstName !== '' ? $firstName : null,
        'middle_name' => $middleName !== '' ? $middleName : null,
        'last_name' => $lastName !== '' ? $lastName : null,
        'suffix' => $suffix !== '' ? $suffix : null,
        'birth_date' => isset($row['BirthDate']) ? $row['BirthDate'] : null,
        'street_address' => $row['StreetAddress'] ?? null,
        'region' => $row['Region'] ?? null,
        'address_psgc' => $addressCodes,
        'address_selector_province' => is_array($addressCodes) ? (psgc_record((string)($addressCodes['city']??''))['selector_province']??'') : '',
        'city' => $row['City'] ?? null,
        'state_province' => $row['StateProvince'] ?? null,
        'barangay' => $row['Barangay'] ?? null,
        'postal_code' => $row['PostalCode'] ?? null,
        'country' => $row['Country'] ?? 'Philippines',
        'home_location' => $row['HomeLocation'] ?? ($row['Address'] ?? null),
        'has_recent_experience' => (int)($row['HasRecentExperience'] ?? 0),
        'job_title' => $row['JobTitle'] ?? null,
        'company_name' => $row['CompanyName'] ?? null,
        'experience_start_month' => $row['ExperienceStartMonth'] ?? null,
        'experience_start_year' => $row['ExperienceStartYear'] ?? null,
        'experience_end_month' => $row['ExperienceEndMonth'] ?? null,
        'experience_end_year' => $row['ExperienceEndYear'] ?? null,
        'still_in_role' => (int)($row['StillInRole'] ?? 0),
        'preferred_classification' => $row['PreferredClassification'] ?? null,
        'preferred_sub_classification' => $row['PreferredSubClassification'] ?? null,
        'job_category' => $row['JobCategory'] ?? null,
        'profile_visibility' => $row['ProfileVisibility'] ?? null
    ],
    'documents' => $docs
]);
exit();
