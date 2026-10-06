<?php
require_once __DIR__ . '/../Common/session_init.php';

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/job_categories.php';
require_once __DIR__ . '/../Helpers/job_order_wall.php';
require_once __DIR__.'/../Helpers/job_hierarchy.php';

job_category_migrate_legacy_values($conn);

header('Content-Type: application/json');

$isApplicant = isset($_SESSION['user_id']) && isset($_SESSION['role'])
    && strcasecmp(trim((string)$_SESSION['role']), 'Applicant') === 0;
$userId = $isApplicant ? (int)$_SESSION['user_id'] : 0;

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

function fmtRequestDate(?string $d): array
{
    if ($d === null || $d === '' || $d === '0000-00-00') {
        return ['label' => 'Date TBD', 'ts' => 0];
    }
    $ts = strtotime($d);
    if ($ts === false) {
        return ['label' => 'Date TBD', 'ts' => 0];
    }
    return ['label' => date('M d, Y', $ts), 'ts' => $ts];
}

$savedClass = '';
$savedSub = '';
$savedHome = '';
$savedJobCategory = '';

if ($userId > 0) {
    $stmtPref = $conn->prepare("
        SELECT PreferredClassification, PreferredSubClassification, HomeLocation, JobCategory
        FROM applicants
        WHERE UserID = ?
        LIMIT 1
    ");
    $stmtPref->bind_param('i', $userId);
    $stmtPref->execute();
    $prefRes = $stmtPref->get_result();
    $prefRow = $prefRes->fetch_assoc();
    $stmtPref->close();

    if ($prefRow) {
        $savedClass = trim((string)($prefRow['PreferredClassification'] ?? ''));
        $savedSub = trim((string)($prefRow['PreferredSubClassification'] ?? ''));
        $savedHome = trim((string)($prefRow['HomeLocation'] ?? ''));
        $savedJobCategory = job_category_normalize(trim((string)($prefRow['JobCategory'] ?? '')));
    }
}

if($userId>0){$labels=jh_profile_labels($conn,$userId);$savedJobCategory=(string)($labels['category_name']??$savedJobCategory);$savedClass=(string)($labels['classification_name']??$savedClass);$savedSub=(string)($labels['sub_classification_name']??$savedSub);}
$qKeyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
$qLoc = isset($_GET['location']) ? trim((string)$_GET['location']) : '';
$qWorkType = isset($_GET['work_type']) ? trim((string)$_GET['work_type']) : '';
$qSinceDays = isset($_GET['since_days']) ? (int)$_GET['since_days'] : 0;
$scopeMatch = (isset($_GET['scope']) && $_GET['scope'] === 'match') || (isset($_GET['filter']) && $_GET['filter'] === 'match');
$isDictionaryRequest = isset($_GET['dictionary']) && $_GET['dictionary'] === '1';

if ($isDictionaryRequest) {
    $dictSql = "
        SELECT DISTINCT jo.Position, c.Name AS CompanyName
        FROM joborder jo
        INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
        WHERE c.IsActive = 1 AND jo.Status = 'Open' AND " . job_order_has_open_slots_sql() . "
        ORDER BY jo.CreatedAt DESC
    ";
    $dictRes = $conn->query($dictSql);
    $titles = [];
    $companies = [];
    if ($dictRes) {
        while ($dRow = $dictRes->fetch_assoc()) {
            $p = trim((string)($dRow['Position'] ?? ''));
            $co = trim((string)($dRow['CompanyName'] ?? ''));
            if ($p !== '' && $p !== '0') $titles[] = $p;
            if ($co !== '') $companies[] = $co;
        }
        $dictRes->free();
    }
    echo json_encode([
        'success' => true,
        'titles' => array_values(array_unique($titles)),
        'companies' => array_values(array_unique($companies)),
    ]);
    exit();
}

$baseSql = "
    SELECT 
        jo.JobOrderID,
        jo.Position,
        jo.RequiredCount,
        jo.Location,
        jo.WorkSchedule,
        jo.RequestDate,
        jo.Qualifications,
        jo.RequiredSkills,
        jo.CreatedAt,
        jo.JobCategory AS JobOrderCategory,
        c.Name AS CompanyName,
        jor.Position AS RequestPosition
    FROM joborder jo
    INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
    " . job_order_request_position_join_sql() . "
    WHERE c.IsActive = 1 AND jo.Status = 'Open'
      AND " . job_order_has_open_slots_sql() . "
";

$extraWhere = '';
$types = '';
$params = [];

// 1. STRICT PROFILE MATCHING MODE (Match My Profile)
if ($scopeMatch && $userId > 0) {
    $profile=jh_profile_filter($conn,$userId);$extraWhere.=$profile['where'];$types.=$profile['types'];$params=array_merge($params,$profile['params']);
}

// 2. STRICT WHAT KEYWORD / COMPANY SEARCH
if ($qKeyword !== '') {
    $cleanKw = trim(preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $qKeyword));
    
    if (stripos($cleanKw, 'gaisano') !== false && (stripos($cleanKw, 'bulua') !== false || stripos($cleanKw, 'carmen') !== false || stripos($cleanKw, 'puerto') !== false || stripos($cleanKw, 'uptown') !== false)) {
        $branch = stripos($cleanKw, 'bulua') !== false ? 'Bulua' : (stripos($cleanKw, 'carmen') !== false ? 'Carmen' : (stripos($cleanKw, 'puerto') !== false ? 'Puerto' : 'Uptown'));
        $extraWhere .= ' AND (c.Name LIKE ? AND (jo.Location LIKE ? OR c.Name LIKE ?))';
        $types .= 'sss';
        $params[] = '%Gaisano%';
        $params[] = '%' . $branch . '%';
        $params[] = '%' . $branch . '%';
    } elseif (stripos($cleanKw, 'gaisano') !== false) {
        $extraWhere .= ' AND (c.Name LIKE ?)';
        $types .= 's';
        $params[] = '%Gaisano%';
    } else {
        $tokens = preg_split('/\s+/', $cleanKw);
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || $token === '-') continue;
            $kwToken = '%' . $token . '%';
            if (strcasecmp($token, 'diser') === 0 || strcasecmp($token, 'promodiser') === 0) {
                $extraWhere .= ' AND (jo.Position LIKE ? OR jo.Position LIKE ? OR c.Name LIKE ?)';
                $types .= 'sss';
                $params[] = '%Promodiser%';
                $params[] = '%Promo Diser%';
                $params[] = $kwToken;
            } else {
                $extraWhere .= ' AND (jo.Position LIKE ? OR c.Name LIKE ? OR jo.Location LIKE ?)';
                $types .= 'sss';
                $params[] = $kwToken;
                $params[] = $kwToken;
                $params[] = $kwToken;
            }
        }
    }
}

// 3. STRICT WHERE LOCATION SEARCH
if (trim($qLoc) !== '') {
    $locClean = trim($qLoc);
    if (strcasecmp($locClean, 'Cagayan de Oro City') === 0 || strcasecmp($locClean, 'Cagayan de Oro') === 0 || strcasecmp($locClean, 'CDO') === 0) {
        $extraWhere .= ' AND (jo.Location LIKE ? OR jo.Location LIKE ? OR jo.Location LIKE ?)';
        $types .= 'sss';
        $params[] = '%CDO%';
        $params[] = '%Cagayan%';
        $params[] = '%Cagayan de Oro%';
    } else {
        $extraWhere .= ' AND (jo.Location LIKE ?)';
        $types .= 's';
        $params[] = '%' . $locClean . '%';
    }
}

if ($qWorkType === 'fulltime') {
    $extraWhere .= " AND (LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%full%' OR LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%mon%' OR LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%weekday%' OR LOWER(COALESCE(jo.Position,'')) LIKE '%full%time%')";
} elseif ($qWorkType === 'parttime') {
    $extraWhere .= " AND (LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%part%' OR LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%half%')";
} elseif ($qWorkType === 'contract') {
    $extraWhere .= " AND (LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%contract%' OR LOWER(COALESCE(jo.Position,'')) LIKE '%contract%')";
}

if ($qSinceDays > 0 && $qSinceDays <= 3650) {
    $extraWhere .= ' AND jo.RequestDate > ? AND jo.RequestDate >= DATE_SUB(CURDATE(), INTERVAL ? DAY)';
    $types .= 'si';
    $params[] = '1970-01-02';
    $params[] = $qSinceDays;
}

$hierarchy=jh_request_filter($conn,$_GET);$extraWhere.=$hierarchy['where'];$types.=$hierarchy['types'];$params=array_merge($params,$hierarchy['params']);
$sql = $baseSql . $extraWhere . ' ORDER BY jo.RequestDate DESC, jo.CreatedAt DESC';

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not load jobs.', 'jobs' => []]);
        exit();
    }
    $bindArgs = [];
    $bindArgs[] = $types;
    foreach ($params as $p) {
        $bindArgs[] = $p;
    }
    $refs = [];
    foreach ($bindArgs as $k => $v) {
        $refs[$k] = &$bindArgs[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load jobs.', 'jobs' => []]);
    exit();
}

$jobs = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dateParts = fmtRequestDate(isset($row['RequestDate']) ? (string)$row['RequestDate'] : null);
        $positionName = job_order_resolve_position(
            (string)($row['Position'] ?? ''),
            (string)($row['RequestPosition'] ?? '')
        );
        $jobOrderId = (int)$row['JobOrderID'];
        $deploymentSlots = job_order_fetch_placement_slots_mysqli(
            $conn,
            $jobOrderId,
            (int)($row['RequiredCount'] ?? 1)
        );
        $deploymentSlots=jh_filter_slots($conn,$deploymentSlots,$jobOrderId,$scopeMatch?$userId:0);
        $slotSummary = job_order_summarize_slots($deploymentSlots);
        if ($slotSummary['all_filled']) {
            continue;
        }

        $displayLocation = (string)($row['Location'] ?? '');
        if (job_order_is_generic_site($displayLocation) && count($deploymentSlots) === 1) {
            $onlySite = trim((string)($deploymentSlots[0]['deployment_site'] ?? ''));
            if ($onlySite !== '') {
                $displayLocation = $onlySite;
            }
        } elseif (job_order_is_generic_site($displayLocation) && count($deploymentSlots) > 1) {
            $openSites = [];
            foreach ($deploymentSlots as $slot) {
                if (empty($slot['is_filled'])) {
                    $siteName = trim((string)($slot['deployment_site'] ?? ''));
                    if ($siteName !== '') {
                        $openSites[] = $siteName;
                    }
                }
            }
            if ($openSites !== []) {
                $displayLocation = implode(', ', array_unique($openSites));
            }
        }

        $jobs[] = [
            'id' => $jobOrderId,
            'job_order_id' => $jobOrderId,
            'position' => $positionName,
            'company_name' => $row['CompanyName'],
            'required_count' => (int)$row['RequiredCount'],
            'total_slots' => $slotSummary['total_slots'],
            'filled_slots' => $slotSummary['filled_slots'],
            'open_slots' => $slotSummary['open_slots'],
            'deployment_slots' => $deploymentSlots,
            'location' => $displayLocation,
            'work_schedule' => $row['WorkSchedule'],
            'qualifications' => $row['Qualifications'] ?? '',
            'required_skills' => $row['RequiredSkills'] ?? '',
            'request_date' => $dateParts['label'],
            'posted_ts' => $dateParts['ts'],
            'matches_preference' => true,
        ];
    }
    $result->free();
}

echo json_encode([
    'success' => true,
    'jobs' => $jobs,
    'applicant' => $userId > 0 ? [
        'saved_classification' => $savedClass,
        'saved_sub_classification' => $savedSub,
        'saved_job_category' => $savedJobCategory,
        'home_location' => $savedHome,
    ] : null,
    'active_filters' => [
        'keyword' => $qKeyword,
        'location' => $qLoc,
        'scope' => $scopeMatch ? 'match' : 'all',
    ],
]);
exit();



