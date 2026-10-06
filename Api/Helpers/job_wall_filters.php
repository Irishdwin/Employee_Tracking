<?php
require_once __DIR__.'/job_hierarchy.php';
/**
 * Shared strict filters for applicant job wall APIs.
 * Modes: all (open jobs), search (keyword title/company), profile (applicant preference).
 */

function job_wall_normalize_key(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    $text = preg_replace('/[\s\-–—]+/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function job_wall_column_exists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $sql = "SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cache[$key] = isset($row['cnt']) && (int)$row['cnt'] > 0;
    return $cache[$key];
}

/**
 * Resolve active company IDs whose normalized name matches the keyword.
 * Multi-token keywords require every token to appear in the company name.
 */
function job_wall_resolve_company_ids(mysqli $conn, string $keyword): array
{
    $needle = job_wall_normalize_key($keyword);
    if ($needle === '') {
        return [];
    }
    $tokens = array_values(array_filter(explode(' ', $needle), static function ($t) {
        return $t !== '';
    }));
    if ($tokens === []) {
        return [];
    }

    $res = $conn->query('SELECT CompanyID, Name FROM clientcompany WHERE IsActive = 1');
    if (!$res) {
        return [];
    }

    $exact = [];
    $tokenMatches = [];
    while ($row = $res->fetch_assoc()) {
        $companyId = (int)($row['CompanyID'] ?? 0);
        if ($companyId <= 0) {
            continue;
        }
        $companyKey = job_wall_normalize_key((string)($row['Name'] ?? ''));
        if ($companyKey === '') {
            continue;
        }
        if ($companyKey === $needle) {
            $exact[] = $companyId;
            continue;
        }
        $allPresent = true;
        foreach ($tokens as $token) {
            if (strpos($companyKey, $token) === false) {
                $allPresent = false;
                break;
            }
        }
        if ($allPresent) {
            $tokenMatches[] = $companyId;
        }
    }
    $res->free();

    if ($exact !== []) {
        return array_values(array_unique($exact));
    }
    return array_values(array_unique($tokenMatches));
}

/**
 * Resolve job category id from canonical category name.
 */
function job_wall_resolve_category_id(mysqli $conn, string $categoryName): int
{
    $categoryName = trim($categoryName);
    if ($categoryName === '') {
        return 0;
    }
    $stmt = $conn->prepare('SELECT id FROM job_categories WHERE category_name = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $categoryName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : 0;
}

/**
 * @return array{where:string,types:string,params:array,mode:string,company_ids:array,category_id:int}
 */
function job_wall_build_filters(mysqli $conn, array $options): array
{
    $mode = isset($options['mode']) ? (string)$options['mode'] : 'all';
    if (!in_array($mode, ['all', 'search', 'profile'], true)) {
        $mode = 'all';
    }

    $keyword = trim((string)($options['keyword'] ?? ''));
    $location = trim((string)($options['location'] ?? ''));
    $workType = trim((string)($options['work_type'] ?? ''));
    $sinceDays = (int)($options['since_days'] ?? 0);

    $savedJobCategory = trim((string)($options['saved_job_category'] ?? ''));
    $savedClass = trim((string)($options['saved_classification'] ?? ''));
    $savedSub = trim((string)($options['saved_sub_classification'] ?? ''));

    $extraWhere = '';
    $types = '';
    $params = [];
    $companyIds = [];
    $categoryId = 0;

    if ($mode === 'profile') {
        $profile=jh_profile_filter($conn,(int)($options['user_id']??$_SESSION['user_id']??0));
        $extraWhere.=$profile['where'];$types.=$profile['types'];$params=array_merge($params,$profile['params']);
    } elseif ($mode === 'search' && $keyword !== '') {
        $companyIds = job_wall_resolve_company_ids($conn, $keyword);
        if ($companyIds !== []) {
            $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
            $extraWhere .= ' AND jo.CompanyID IN (' . $placeholders . ')';
            $types .= str_repeat('i', count($companyIds));
            foreach ($companyIds as $cid) {
                $params[] = $cid;
            }
        } else {
            $cleanKw = trim(preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $keyword));
            $tokens = preg_split('/\s+/', $cleanKw);
            foreach ($tokens as $token) {
                $token = trim($token);
                if ($token === '' || $token === '-') {
                    continue;
                }
                if (strcasecmp($token, 'diser') === 0 || strcasecmp($token, 'promodiser') === 0) {
                    $extraWhere .= ' AND (jo.Position LIKE ? OR jo.Position LIKE ? OR jor.Position LIKE ? OR jor.Position LIKE ?)';
                    $types .= 'ssss';
                    $params[] = '%Promodiser%';
                    $params[] = '%Promo Diser%';
                    $params[] = '%Promodiser%';
                    $params[] = '%Promo Diser%';
                } else {
                    $kwToken = '%' . $token . '%';
                    $extraWhere .= ' AND (jo.Position LIKE ? OR jor.Position LIKE ?)';
                    $types .= 'ss';
                    $params[] = $kwToken;
                    $params[] = $kwToken;
                }
            }
        }
    }

    if ($location !== '') {
        if (strcasecmp($location, 'Cagayan de Oro City') === 0
            || strcasecmp($location, 'Cagayan de Oro') === 0
            || strcasecmp($location, 'CDO') === 0) {
            $extraWhere .= ' AND (jo.Location LIKE ? OR jo.Location LIKE ? OR jo.Location LIKE ?)';
            $types .= 'sss';
            $params[] = '%CDO%';
            $params[] = '%Cagayan%';
            $params[] = '%Cagayan de Oro%';
        } else {
            $extraWhere .= ' AND (jo.Location LIKE ?)';
            $types .= 's';
            $params[] = '%' . $location . '%';
        }
    }

    if ($workType === 'fulltime') {
        $extraWhere .= " AND (LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%full%' OR LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%mon%' OR LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%weekday%' OR LOWER(COALESCE(jo.Position,'')) LIKE '%full%time%')";
    } elseif ($workType === 'parttime') {
        $extraWhere .= " AND (LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%part%' OR LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%half%')";
    } elseif ($workType === 'contract') {
        $extraWhere .= " AND (LOWER(COALESCE(jo.WorkSchedule,'')) LIKE '%contract%' OR LOWER(COALESCE(jo.Position,'')) LIKE '%contract%')";
    }

    if ($sinceDays > 0 && $sinceDays <= 3650) {
        $extraWhere .= ' AND jo.RequestDate > ? AND jo.RequestDate >= DATE_SUB(CURDATE(), INTERVAL ? DAY)';
        $types .= 'si';
        $params[] = '1970-01-02';
        $params[] = $sinceDays;
    }

    $hierarchy=jh_request_filter($conn,$_GET);
    $extraWhere.=$hierarchy['where'];$types.=$hierarchy['types'];$params=array_merge($params,$hierarchy['params']);
    return [
        'where' => $extraWhere,
        'types' => $types,
        'params' => $params,
        'mode' => $mode,
        'company_ids' => $companyIds,
        'category_id' => $categoryId,
    ];
}

/**
 * Resolve wall mode from request query params.
 */
function job_wall_resolve_mode_from_request(array $get, string $keyword = ''): string
{
    $scope = isset($get['scope']) ? trim((string)$get['scope']) : '';
    $filter = isset($get['filter']) ? trim((string)$get['filter']) : '';

    if ($scope === 'match' || $filter === 'match') {
        return 'profile';
    }
    if ($scope === 'search' || $filter === 'search') {
        return $keyword !== '' ? 'search' : 'all';
    }
    if ($keyword !== '') {
        return 'search';
    }
    return 'all';
}

/**
 * Bind dynamic parameters to a mysqli prepared statement.
 */
function job_wall_bind_params(mysqli_stmt $stmt, string $types, array $params): bool
{
    if ($types === '' || $params === []) {
        return true;
    }
    $refs = [];
    $refs[] = $types;
    foreach ($params as $k => $v) {
        $refs[] = &$params[$k];
    }
    return call_user_func_array([$stmt, 'bind_param'], $refs);
}

/**
 * Execute a prepared SELECT and return mysqli_result|false.
 */
function job_wall_query_with_filters(mysqli $conn, string $sql, string $types, array $params)
{
    if ($types === '' || $params === []) {
        return $conn->query($sql);
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    if (!job_wall_bind_params($stmt, $types, $params)) {
        $stmt->close();
        return false;
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

