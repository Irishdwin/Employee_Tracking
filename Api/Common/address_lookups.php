<?php
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

function json_resp(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function fetch_json_url(string $url, array $postPayload = null): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($postPayload !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postPayload));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $opts = ['http' => ['method' => $postPayload === null ? 'GET' : 'POST', 'header' => "Accept: application/json\r\n", 'timeout' => 30]];
        if ($postPayload !== null) {
            $opts['http']['header'] .= "Content-Type: application/json\r\n";
            $opts['http']['content'] = json_encode($postPayload);
        }
        $raw = @file_get_contents($url, false, stream_context_create($opts));
    }
    if (!$raw) return null;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

$level = isset($_GET['level']) ? trim((string)$_GET['level']) : 'countries';

if ($level === 'countries') {
    $res = fetch_json_url('https://restcountries.com/v3.1/all?fields=name,cca2');
    $items = [];
    if (is_array($res)) {
        foreach ($res as $c) {
            $name = isset($c['name']['common']) ? trim((string)$c['name']['common']) : '';
            $id = isset($c['cca2']) ? trim((string)$c['cca2']) : $name;
            if ($name === '') continue;
            $items[] = ['id' => $id !== '' ? $id : $name, 'name' => $name];
        }
        usort($items, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    }
    json_resp(['success' => true, 'items' => $items]);
}

if ($level === 'provinces') {
    $country = trim((string)($_GET['country_name'] ?? ''));
    if ($country === '') json_resp(['success' => true, 'items' => []]);
    if (strcasecmp($country, 'Philippines') === 0) {
        $items = [];
        $provinces = fetch_json_url('https://psgc.gitlab.io/api/provinces/');
        if (is_array($provinces)) {
            foreach ($provinces as $p) {
                $name = trim((string)($p['name'] ?? ''));
                $code = trim((string)($p['code'] ?? ''));
                if ($name === '') continue;
                $items[] = ['id' => $code !== '' ? $code : $name, 'name' => $name];
            }
        }
        // NCR has no province rows in PSGC, so include as selectable province-equivalent.
        $items[] = ['id' => '130000000', 'name' => 'Metro Manila'];
        usort($items, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
        json_resp(['success' => true, 'items' => $items]);
    }
    $res = fetch_json_url('https://countriesnow.space/api/v0.1/countries/states', ['country' => $country]);
    $items = [];
    if (is_array($res) && !empty($res['data']['states']) && is_array($res['data']['states'])) {
        foreach ($res['data']['states'] as $s) {
            $name = trim((string)($s['name'] ?? ''));
            if ($name === '') continue;
            $items[] = ['id' => $name, 'name' => $name];
        }
        usort($items, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    }
    json_resp(['success' => true, 'items' => $items]);
}

if ($level === 'cities') {
    $country = trim((string)($_GET['country_name'] ?? ''));
    $province = trim((string)($_GET['province_name'] ?? ''));
    if ($country === '' || $province === '') json_resp(['success' => true, 'items' => []]);
    if (strcasecmp($country, 'Philippines') === 0) {
        $items = [];
        if (strcasecmp($province, 'Metro Manila') === 0) {
            $cities = fetch_json_url('https://psgc.gitlab.io/api/regions/130000000/cities-municipalities/');
        } else {
            $provinces = fetch_json_url('https://psgc.gitlab.io/api/provinces/');
            $provinceCode = '';
            if (is_array($provinces)) {
                foreach ($provinces as $p) {
                    $name = trim((string)($p['name'] ?? ''));
                    if (strcasecmp($name, $province) === 0) {
                        $provinceCode = trim((string)($p['code'] ?? ''));
                        break;
                    }
                }
            }
            if ($provinceCode === '') json_resp(['success' => true, 'items' => []]);
            $cities = fetch_json_url('https://psgc.gitlab.io/api/provinces/' . rawurlencode($provinceCode) . '/cities-municipalities/');
        }
        if (is_array($cities)) {
            foreach ($cities as $c) {
                $name = trim((string)($c['name'] ?? ''));
                $code = trim((string)($c['code'] ?? ''));
                if ($name === '') continue;
                $items[] = ['id' => $code !== '' ? $code : $name, 'name' => $name];
            }
            usort($items, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
        }
        json_resp(['success' => true, 'items' => $items]);
    }
    $res = fetch_json_url('https://countriesnow.space/api/v0.1/countries/state/cities', ['country' => $country, 'state' => $province]);
    $items = [];
    if (is_array($res) && !empty($res['data']) && is_array($res['data'])) {
        foreach ($res['data'] as $nameRaw) {
            $name = trim((string)$nameRaw);
            if ($name === '') continue;
            $items[] = ['id' => $name, 'name' => $name];
        }
        usort($items, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    }
    json_resp(['success' => true, 'items' => $items]);
}

if ($level === 'barangays') {
    $country = trim((string)($_GET['country_name'] ?? ''));
    $province = trim((string)($_GET['province_name'] ?? ''));
    $city = trim((string)($_GET['city_name'] ?? ''));
    if ($country === '' || $province === '' || $city === '') json_resp(['success' => true, 'items' => []]);

    // Barangay level is available via PSGC for Philippines.
    if (strcasecmp($country, 'Philippines') !== 0) {
        json_resp(['success' => true, 'items' => []]);
    }

    $provinces = fetch_json_url('https://psgc.gitlab.io/api/provinces/');
    if (!is_array($provinces)) json_resp(['success' => true, 'items' => []]);

    $provinceCode = '';
    foreach ($provinces as $p) {
        $name = trim((string)($p['name'] ?? ''));
        if (strcasecmp($name, $province) === 0) {
            $provinceCode = trim((string)($p['code'] ?? ''));
            break;
        }
    }
    if ($provinceCode === '') json_resp(['success' => true, 'items' => []]);

    $cities = fetch_json_url('https://psgc.gitlab.io/api/provinces/' . rawurlencode($provinceCode) . '/cities-municipalities/');
    if (!is_array($cities)) json_resp(['success' => true, 'items' => []]);

    $cityCode = '';
    foreach ($cities as $c) {
        $name = trim((string)($c['name'] ?? ''));
        if (strcasecmp($name, $city) === 0) {
            $cityCode = trim((string)($c['code'] ?? ''));
            break;
        }
    }
    if ($cityCode === '') json_resp(['success' => true, 'items' => []]);

    $barangays = fetch_json_url('https://psgc.gitlab.io/api/cities-municipalities/' . rawurlencode($cityCode) . '/barangays/');
    $items = [];
    if (is_array($barangays)) {
        foreach ($barangays as $b) {
            $name = trim((string)($b['name'] ?? ''));
            if ($name === '') continue;
            $items[] = ['id' => $name, 'name' => $name];
        }
        usort($items, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    }
    json_resp(['success' => true, 'items' => $items]);
}

json_resp(['success' => false, 'message' => 'Invalid level.'], 400);
