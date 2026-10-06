<?php
/**
 * One-time PSGC address data import (Region -> Province -> City/Municipality -> Barangay).
 *
 * Source: https://psgc.gitlab.io/api/ — this is the SAME PSGC source already
 * used elsewhere in this project (see Api/Common/address_lookups.php), which
 * itself mirrors the official PSA PSGC quarterly publication. Re-used here
 * instead of picking a new/unverified source.
 *
 * Run once (while logged in as Admin), stage by stage, e.g.:
 *   http://localhost/Employee_Tracking/Api/Admin/seed_psgc_address_data.php?stage=status
 *   http://localhost/Employee_Tracking/Api/Admin/seed_psgc_address_data.php?stage=regions
 *   http://localhost/Employee_Tracking/Api/Admin/seed_psgc_address_data.php?stage=provinces
 *   http://localhost/Employee_Tracking/Api/Admin/seed_psgc_address_data.php?stage=cities
 *   http://localhost/Employee_Tracking/Api/Admin/seed_psgc_address_data.php?stage=barangays&offset=0&limit=100
 *
 * The barangay stage is the heavy one (~1,600+ cities to walk through, ~42,000
 * barangay rows total) so it is chunked via offset/limit and is SAFE TO
 * RE-RUN: every insert is an upsert keyed on PSGCCode, so re-running a stage
 * (or the same offset twice) never creates duplicates. Keep incrementing
 * &offset= by the returned "next_offset" until the response says
 * "done": true.
 *
 * Requires the schema migration in sql/psgc_address_hierarchy_migration.sql
 * to have been run first (adds address_region_lookup + PSGCCode/RegionID
 * columns to the existing address_*_lookup tables).
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin login required.']);
    exit;
}

set_time_limit(0);
require __DIR__ . '/../Db_connection/db_connect.php';

/** Same fetch helper pattern already used in Api/Common/address_lookups.php. */
function fetch_json_url(string $url): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $opts = ['http' => ['method' => 'GET', 'header' => "Accept: application/json\r\n", 'timeout' => 60]];
        $raw = @file_get_contents($url, false, stream_context_create($opts));
    }
    if (!$raw) return null;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

// NCR has no provinces in PSGC — its cities/municipalities attach directly
// to the region. Give it one synthetic "province row" so the existing
// Province -> City schema still works, matching the same design decision
// already made for NCR in Api/Common/address_lookups.php (which treats it
// as a single "Metro Manila" province-equivalent, code 130000000).
const NCR_REGION_CODE = '130000000';
const NCR_PSEUDO_PROVINCE_CODE = '130000000';
const NCR_PSEUDO_PROVINCE_NAME = 'Metro Manila';

$stage = isset($_GET['stage']) ? trim((string)$_GET['stage']) : 'status';

function respond(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($stage === 'status') {
    $counts = [];
    foreach (['address_region_lookup' => 'RegionID', 'address_province_lookup' => 'ProvinceID', 'address_city_lookup' => 'CityID', 'address_barangay_lookup' => 'BarangayID'] as $table => $pk) {
        $res = $conn->query("SELECT COUNT(*) AS c FROM `$table`");
        $counts[$table] = $res ? (int)$res->fetch_assoc()['c'] : null;
    }
    respond([
        'success' => true,
        'counts' => $counts,
        'expected_approx' => ['address_region_lookup' => 18, 'address_province_lookup' => 83, 'address_city_lookup' => 1642, 'address_barangay_lookup' => 42024],
        'next' => 'Run ?stage=regions, then provinces, then cities, then barangays (chunked).'
    ]);
}

if ($stage === 'regions') {
    $regions = fetch_json_url('https://psgc.gitlab.io/api/regions/');
    if (!is_array($regions)) {
        respond(['success' => false, 'message' => 'Could not reach psgc.gitlab.io or unexpected response for regions.']);
    }
    $stmt = $conn->prepare("INSERT INTO address_region_lookup (PSGCCode, Name) VALUES (?, ?) ON DUPLICATE KEY UPDATE Name = VALUES(Name)");
    $inserted = 0;
    foreach ($regions as $r) {
        $code = trim((string)($r['code'] ?? ''));
        $name = trim((string)($r['name'] ?? ''));
        if ($code === '' || $name === '') continue;
        $stmt->bind_param('ss', $code, $name);
        $stmt->execute();
        $inserted++;
    }
    $stmt->close();
    respond(['success' => true, 'stage' => 'regions', 'processed' => $inserted]);
}

if ($stage === 'provinces') {
    // address_province_lookup already enforces a NOT NULL CountryID (FK to
    // address_country_lookup, which already has "Philippines" pre-seeded).
    // Look it up rather than hardcoding an ID, in case it differs per install.
    $countryRow = $conn->query("SELECT CountryID FROM address_country_lookup WHERE Name = 'Philippines' LIMIT 1")->fetch_assoc();
    if (!$countryRow) {
        respond(['success' => false, 'message' => 'No "Philippines" row found in address_country_lookup — cannot satisfy the CountryID foreign key.']);
    }
    $countryId = (int)$countryRow['CountryID'];

    $regionRes = $conn->query("SELECT RegionID, PSGCCode FROM address_region_lookup");
    if (!$regionRes || $regionRes->num_rows === 0) {
        respond(['success' => false, 'message' => 'No regions found — run ?stage=regions first.']);
    }
    $insertProv = $conn->prepare("INSERT INTO address_province_lookup (RegionID, CountryID, PSGCCode, Name) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE Name = VALUES(Name), RegionID = VALUES(RegionID), CountryID = VALUES(CountryID)");
    $processed = 0;
    while ($region = $regionRes->fetch_assoc()) {
        $regionId = (int)$region['RegionID'];
        $regionCode = $region['PSGCCode'];

        if ($regionCode === NCR_REGION_CODE) {
            // NCR: no real provinces upstream — insert the single pseudo-province.
            // bind_param() needs actual variables (it binds by reference), so
            // the constants have to be copied into locals first.
            $ncrCode = NCR_PSEUDO_PROVINCE_CODE;
            $ncrName = NCR_PSEUDO_PROVINCE_NAME;
            $insertProv->bind_param('iiss', $regionId, $countryId, $ncrCode, $ncrName);
            $insertProv->execute();
            $processed++;
            continue;
        }

        $provinces = fetch_json_url('https://psgc.gitlab.io/api/regions/' . rawurlencode($regionCode) . '/provinces/');
        if (!is_array($provinces)) continue;
        foreach ($provinces as $p) {
            $code = trim((string)($p['code'] ?? ''));
            $name = trim((string)($p['name'] ?? ''));
            if ($code === '' || $name === '') continue;
            $insertProv->bind_param('iiss', $regionId, $countryId, $code, $name);
            $insertProv->execute();
            $processed++;
        }
    }
    $insertProv->close();
    respond(['success' => true, 'stage' => 'provinces', 'processed' => $processed]);
}

if ($stage === 'cities') {
    $provRes = $conn->query("SELECT ProvinceID, PSGCCode FROM address_province_lookup WHERE PSGCCode IS NOT NULL");
    if (!$provRes || $provRes->num_rows === 0) {
        respond(['success' => false, 'message' => 'No provinces found — run ?stage=provinces first.']);
    }
    $insertCity = $conn->prepare("INSERT INTO address_city_lookup (ProvinceID, PSGCCode, Name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE Name = VALUES(Name), ProvinceID = VALUES(ProvinceID)");
    $processed = 0;
    while ($prov = $provRes->fetch_assoc()) {
        $provinceId = (int)$prov['ProvinceID'];
        $provinceCode = $prov['PSGCCode'];

        $url = ($provinceCode === NCR_PSEUDO_PROVINCE_CODE)
            ? 'https://psgc.gitlab.io/api/regions/' . NCR_REGION_CODE . '/cities-municipalities/'
            : 'https://psgc.gitlab.io/api/provinces/' . rawurlencode($provinceCode) . '/cities-municipalities/';

        $cities = fetch_json_url($url);
        if (!is_array($cities)) continue;
        foreach ($cities as $c) {
            $code = trim((string)($c['code'] ?? ''));
            $name = trim((string)($c['name'] ?? ''));
            if ($code === '' || $name === '') continue;
            $insertCity->bind_param('iss', $provinceId, $code, $name);
            $insertCity->execute();
            $processed++;
        }
    }
    $insertCity->close();
    respond(['success' => true, 'stage' => 'cities', 'processed' => $processed]);
}

if ($stage === 'barangays') {
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit = max(1, min(300, (int)($_GET['limit'] ?? 100)));

    $totalRes = $conn->query("SELECT COUNT(*) AS c FROM address_city_lookup WHERE PSGCCode IS NOT NULL");
    $total = $totalRes ? (int)$totalRes->fetch_assoc()['c'] : 0;

    $stmt = $conn->prepare("SELECT CityID, PSGCCode FROM address_city_lookup WHERE PSGCCode IS NOT NULL ORDER BY CityID LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $cityRes = $stmt->get_result();

    $insertBgy = $conn->prepare("INSERT INTO address_barangay_lookup (CityID, PSGCCode, Name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE Name = VALUES(Name), CityID = VALUES(CityID)");
    $processed = 0;
    $citiesWalked = 0;
    while ($city = $cityRes->fetch_assoc()) {
        $cityId = (int)$city['CityID'];
        $cityCode = $city['PSGCCode'];
        $barangays = fetch_json_url('https://psgc.gitlab.io/api/cities-municipalities/' . rawurlencode($cityCode) . '/barangays/');
        $citiesWalked++;
        if (!is_array($barangays)) continue;
        foreach ($barangays as $b) {
            $code = trim((string)($b['code'] ?? ''));
            $name = trim((string)($b['name'] ?? ''));
            if ($code === '' || $name === '') continue;
            $insertBgy->bind_param('iss', $cityId, $code, $name);
            $insertBgy->execute();
            $processed++;
        }
    }
    $stmt->close();
    $insertBgy->close();

    $nextOffset = $offset + $citiesWalked;
    respond([
        'success' => true,
        'stage' => 'barangays',
        'cities_walked_this_run' => $citiesWalked,
        'barangays_processed_this_run' => $processed,
        'offset' => $offset,
        'next_offset' => $nextOffset,
        'total_cities' => $total,
        'done' => $nextOffset >= $total
    ]);
}

if ($stage === 'cleanup_legacy_samples') {
    // Safety gate: only allow this once the real import clearly succeeded,
    // so we never wipe the old sample data and leave the dropdowns empty.
    $bgyCount = (int)($conn->query("SELECT COUNT(*) AS c FROM address_barangay_lookup WHERE PSGCCode IS NOT NULL")->fetch_assoc()['c']);
    if ($bgyCount < 40000) {
        respond([
            'success' => false,
            'message' => "Refusing to clean up: only {$bgyCount} PSGC-coded barangays found (expected ~42,000). Finish the barangays stage first."
        ]);
    }
    $deleted = [];
    // Children first (FK order), only rows left over from the original
    // hand-seeded sample (they have no PSGCCode).
    foreach (['address_barangay_lookup', 'address_city_lookup', 'address_province_lookup'] as $table) {
        $res = $conn->query("DELETE FROM `$table` WHERE PSGCCode IS NULL");
        $deleted[$table] = $conn->affected_rows;
    }
    respond(['success' => true, 'stage' => 'cleanup_legacy_samples', 'deleted' => $deleted]);
}

respond(['success' => false, 'message' => 'Unknown stage. Use status | regions | provinces | cities | barangays | cleanup_legacy_samples.']);