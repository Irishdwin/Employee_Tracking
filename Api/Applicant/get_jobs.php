<?php
require_once __DIR__ . '/../Common/session_init.php';

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/job_categories.php';
require_once __DIR__ . '/../Helpers/job_order_wall.php';
require_once __DIR__ . '/../Helpers/job_wall_filters.php';

job_category_migrate_legacy_values($conn);

header('Content-Type: application/json');

$isApplicant = isset($_SESSION['user_id']) && isset($_SESSION['role'])
    && strcasecmp(trim((string)$_SESSION['role']), 'Applicant') === 0;
$userId = $isApplicant ? (int)$_SESSION['user_id'] : 0;

function lcNorm(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
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
    $prefRow = $stmtPref->get_result()->fetch_assoc();
    $stmtPref->close();

    if ($prefRow) {
        $savedClass = trim((string)($prefRow['PreferredClassification'] ?? ''));
        $savedSub = trim((string)($prefRow['PreferredSubClassification'] ?? ''));
        $savedHome = trim((string)($prefRow['HomeLocation'] ?? ''));
        $savedJobCategory = job_category_normalize(trim((string)($prefRow['JobCategory'] ?? '')));
    }
}

$qKeyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
$qClass = isset($_GET['classification']) ? trim((string)$_GET['classification']) : '';
$qLoc = isset($_GET['location']) ? trim((string)$_GET['location']) : '';
$qJobCategory = job_category_normalize(isset($_GET['job_category']) ? trim((string)$_GET['job_category']) : '');
$qWorkType = isset($_GET['work_type']) ? trim((string)$_GET['work_type']) : '';
$qSinceDays = isset($_GET['since_days']) ? (int)$_GET['since_days'] : 0;

$wallMode = job_wall_resolve_mode_from_request($_GET, $qKeyword);
if ($wallMode === 'profile' && $userId <= 0) {
    echo json_encode(['success' => true, 'jobs' => [], 'applicant' => null]);
    exit();
}

$filterParts = job_wall_build_filters($conn, [
    'mode' => $wallMode,
    'keyword' => $qKeyword,
    'location' => $qLoc,
    'work_type' => $qWorkType,
    'since_days' => $qSinceDays,
    'saved_job_category' => $savedJobCategory,
    'saved_classification' => $savedClass,
    'saved_sub_classification' => $savedSub,
]);

if ($qClass !== '' && $wallMode === 'all') {
    $like = '%' . $qClass . '%';
    $filterParts['where'] .= ' AND (jo.Position LIKE ? OR jo.Qualifications LIKE ? OR jo.RequiredSkills LIKE ? OR jor.Position LIKE ?)';
    $filterParts['types'] .= 'ssss';
    array_push($filterParts['params'], $like, $like, $like, $like);
}

if ($qJobCategory !== '' && $wallMode === 'all') {
    $categoryId = job_wall_resolve_category_id($conn, $qJobCategory);
    if ($categoryId > 0 && job_wall_column_exists($conn, 'joborder', 'category_id')) {
        $filterParts['where'] .= ' AND (jo.category_id = ? OR jo.JobCategory = ?)';
        $filterParts['types'] .= 'is';
        array_push($filterParts['params'], $categoryId, $qJobCategory);
    } else {
        $filterParts['where'] .= ' AND (jo.JobCategory = ?)';
        $filterParts['types'] .= 's';
        $filterParts['params'][] = $qJobCategory;
    }
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
    WHERE c.IsActive = 1
      AND " . job_order_has_open_slots_sql() . "
";

$sql = $baseSql . $filterParts['where'] . ' ORDER BY jo.RequestDate DESC, jo.CreatedAt DESC';

$result = job_wall_query_with_filters($conn, $sql, $filterParts['types'], $filterParts['params']);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load jobs.']);
    exit();
}

$jobs = [];
$strictFilter = ($wallMode === 'search' || $wallMode === 'profile');

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

        $matchesPref = $wallMode === 'profile';
        if (!$strictFilter && $savedClass !== '') {
            $hay = lcNorm(
                ($row['Position'] ?? '') . ' '
                . ($row['Qualifications'] ?? '') . ' '
                . ($row['RequiredSkills'] ?? '')
            );
            $badgeNorm = lcNorm($savedClass);
            if ($badgeNorm !== '') {
                $found = function_exists('mb_strpos')
                    ? mb_strpos($hay, $badgeNorm, 0, 'UTF-8')
                    : strpos($hay, $badgeNorm);
                if ($found !== false) {
                    $matchesPref = true;
                }
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
            'matches_preference' => $matchesPref,
        ];
    }
    $result->free();
}

if (!$strictFilter) {
    usort($jobs, function ($a, $b) {
        $ma = !empty($a['matches_preference']) ? 1 : 0;
        $mb = !empty($b['matches_preference']) ? 1 : 0;
        if ($ma !== $mb) {
            return $mb - $ma;
        }
        $ta = (int)($a['posted_ts'] ?? 0);
        $tb = (int)($b['posted_ts'] ?? 0);
        return $tb - $ta;
    });
}

$scopeLabel = $wallMode === 'profile' ? 'match' : ($wallMode === 'search' ? 'search' : 'all');

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
        'mode' => $wallMode,
        'keyword' => $qKeyword,
        'classification' => $qClass,
        'location' => $qLoc,
        'job_category' => $qJobCategory,
        'work_type' => $qWorkType,
        'since_days' => $qSinceDays > 0 ? $qSinceDays : 0,
        'scope' => $scopeLabel,
    ],
]);
exit();
