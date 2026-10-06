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

$savedClass = '';
$savedSub = '';
$savedHome = '';
$savedJobCategory = '';
if ($userId > 0) {
    $cols = [];
    foreach (['PreferredClassification', 'PreferredSubClassification', 'HomeLocation', 'JobCategory'] as $c) {
        if (job_wall_column_exists($conn, 'applicants', $c)) {
            $cols[] = $c;
        }
    }
    if (!empty($cols)) {
        $stmt = $conn->prepare('SELECT ' . implode(', ', $cols) . ' FROM applicants WHERE UserID = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $savedClass = trim((string)($row['PreferredClassification'] ?? ''));
            $savedSub = trim((string)($row['PreferredSubClassification'] ?? ''));
            $savedHome = trim((string)($row['HomeLocation'] ?? ''));
            $savedJobCategory = job_category_normalize(trim((string)($row['JobCategory'] ?? '')));
        }
    }
}

$sinceCursor = isset($_GET['since_cursor']) ? (int)$_GET['since_cursor'] : 0;
if($userId>0){$labels=jh_profile_labels($conn,$userId);$savedJobCategory=(string)($labels['category_name']??$savedJobCategory);$savedClass=(string)($labels['classification_name']??$savedClass);$savedSub=(string)($labels['sub_classification_name']??$savedSub);}
$qKeyword = trim((string)($_GET['keyword'] ?? ''));
$qLoc = trim((string)($_GET['location'] ?? ''));
$qWorkType = trim((string)($_GET['work_type'] ?? ''));
$qSinceDays = isset($_GET['since_days']) ? (int)$_GET['since_days'] : 0;

$wallMode = job_wall_resolve_mode_from_request($_GET, $qKeyword);

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

$sql = "
    SELECT jo.JobOrderID, jo.Position, jo.RequiredCount, jo.Location, jo.WorkSchedule, jo.RequestDate,
           jo.Qualifications, jo.RequiredSkills, c.Name AS CompanyName,
           UNIX_TIMESTAMP(jo.CreatedAt) AS CreatedTs,
           jor.Position AS RequestPosition
    FROM joborder jo
    INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
    " . job_order_request_position_join_sql() . "
    WHERE c.IsActive = 1 AND jo.Status = 'Open'
      AND " . job_order_has_open_slots_sql() . "
      AND UNIX_TIMESTAMP(jo.CreatedAt) > ?
" . $filterParts['where'] . " ORDER BY jo.CreatedAt DESC LIMIT 20";

$types = 'i' . $filterParts['types'];
$params = array_merge([$sinceCursor], $filterParts['params']);

$result = job_wall_query_with_filters($conn, $sql, $types, $params);
if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load latest jobs.']);
    exit();
}

$jobs = [];
$maxCursor = $sinceCursor;
while ($row = $result->fetch_assoc()) {
    $createdTs = (int)($row['CreatedTs'] ?? 0);
    if ($createdTs > $maxCursor) {
        $maxCursor = $createdTs;
    }
    $requestDate = isset($row['RequestDate']) && $row['RequestDate'] && $row['RequestDate'] !== '0000-00-00'
        ? date('M d, Y', strtotime($row['RequestDate']))
        : 'Date TBD';
    $positionName = job_order_resolve_position(
        (string)($row['Position'] ?? ''),
        (string)($row['RequestPosition'] ?? '')
    );
    $jobs[] = [
        'job_order_id' => (int)$row['JobOrderID'],
        'position' => $positionName,
        'company_name' => $row['CompanyName'] ?? '',
        'required_count' => (int)($row['RequiredCount'] ?? 0),
        'location' => $row['Location'] ?? '',
        'work_schedule' => $row['WorkSchedule'] ?? '',
        'qualifications' => $row['Qualifications'] ?? '',
        'required_skills' => $row['RequiredSkills'] ?? '',
        'request_date' => $requestDate,
        'posted_ts' => $createdTs,
        'matches_preference' => $wallMode === 'profile',
    ];
}
$result->free();

echo json_encode([
    'success' => true,
    'jobs' => $jobs,
    'cursor' => $maxCursor,
    'active_filters' => [
        'mode' => $wallMode,
        'scope' => $wallMode === 'profile' ? 'match' : ($wallMode === 'search' ? 'search' : 'all'),
    ],
]);
exit();


