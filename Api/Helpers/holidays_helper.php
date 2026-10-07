<?php
require_once __DIR__.'/holiday_scope.php';
/**
 * CRUD + payroll lookup helper for the `holidays` table.
 *
 * The `holidays` table (HolidayID, HolidayName, HolidayDate, HolidayType,
 * PayMultiplier, IsNationwide, Region, Year, Description, CreatedAt,
 * UpdatedAt) already existed in the schema before this feature — along with
 * `attendance.HolidayID/IsHolidayWork/HolidayPayRate` and
 * `payroll.HolidayPay/HolidayID` — but nothing populated or read it. Two
 * other files (Api/Employee/resignation_request.php, Api/HR/resignations.php)
 * already SELECT directly from `holidays` to exclude holidays from notice-
 * period day counts, which is further confirmation this table (not a new
 * one) is the intended single source of truth for holiday dates. This file
 * is the missing CRUD + read layer for it. No new table is created.
 */

/** Canonical holiday types. The column is VARCHAR(50) (no DB-level ENUM), so
 * this whitelist is enforced in PHP — matches the pattern of validating
 * against a fixed list without constraining future flexibility at the schema
 * level (same reasoning as job_categories' free-text-but-validated names). */
function holidays_allowed_types(): array
{
    return ['Regular Holiday', 'Special Non-working Day', 'Special Working Day', 'Local/Company Holiday'];
}

/** Defensive schema check only — the table already exists in the shipped
 * schema (onazul_hris_db.sql). This only creates it on environments where an
 * older dump is in use, and never touches data on environments where it
 * already exists correctly. */
function holidays_ensure_schema(mysqli $conn): void
{
    static $ready=[];$key=spl_object_id($conn);if(isset($ready[$key]))return;
    $ready[$key]=true;
    $conn->query("
        CREATE TABLE IF NOT EXISTS `holidays` (
          `HolidayID` int(11) NOT NULL AUTO_INCREMENT,
          `HolidayName` varchar(100) NOT NULL,
          `HolidayDate` date NOT NULL,
          `HolidayType` varchar(50) DEFAULT NULL,
          `PayMultiplier` decimal(4,2) DEFAULT 1.00,
          `IsNationwide` tinyint(1) DEFAULT 1,
          `Region` varchar(100) DEFAULT NULL,
          `Year` int(11) DEFAULT NULL,
          `Description` text DEFAULT NULL,
          `CreatedAt` datetime DEFAULT current_timestamp(),
          `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`HolidayID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function holidays_column_exists(mysqli $conn, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $table = 'holidays';
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $cache[$column] = ((int)($row['cnt'] ?? 0) > 0);
}

function holidays_row_out(array $row): array
{
    return [
        'id' => (int)$row['HolidayID'],
        'name' => (string)$row['HolidayName'],
        'date' => (string)$row['HolidayDate'],
        'type' => $row['HolidayType'] !== null ? (string)$row['HolidayType'] : null,
        'pay_multiplier' => isset($row['PayMultiplier']) ? (float)$row['PayMultiplier'] : 1.0,
        'is_nationwide' => !empty($row['IsNationwide']),
        'region' => $row['Region'] !== null ? (string)$row['Region'] : null,
        'year' => isset($row['Year']) && $row['Year'] !== null ? (int)$row['Year'] : (int)date('Y', strtotime((string)$row['HolidayDate'])),
        'description' => $row['Description'] !== null ? (string)$row['Description'] : null,
    ];
}

/** @return array<int, array<string,mixed>> */
function holidays_fetch_all(mysqli $conn, ?int $year = null): array
{
    holidays_ensure_schema($conn);
    if ($year !== null) {
        $stmt = $conn->prepare('SELECT * FROM holidays WHERE YEAR(HolidayDate) = ? ORDER BY HolidayDate ASC');
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query('SELECT * FROM holidays ORDER BY HolidayDate ASC');
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = holidays_row_out($row);
    }
    return $out;
}

function holidays_find_by_id(mysqli $conn, int $id): ?array
{
    if ($id <= 0) return null;
    holidays_ensure_schema($conn);
    $stmt = $conn->prepare('SELECT * FROM holidays WHERE HolidayID = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? holidays_row_out($row) : null;
}

/** Used for the duplicate-date validation rule. */
function holidays_find_by_date(mysqli $conn, string $date, ?int $excludeId = null): ?array
{
    holidays_ensure_schema($conn);
    if ($excludeId !== null) {
        $stmt = $conn->prepare('SELECT * FROM holidays WHERE HolidayDate = ? AND HolidayID <> ? LIMIT 1');
        $stmt->bind_param('si', $date, $excludeId);
    } else {
        $stmt = $conn->prepare('SELECT * FROM holidays WHERE HolidayDate = ? LIMIT 1');
        $stmt->bind_param('s', $date);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? holidays_row_out($row) : null;
}

/**
 * Validates and normalizes Admin-submitted holiday fields. Shared by create
 * and update so both enforce the exact same rules (server-side — per the
 * project's security rule that pay rates/definitions are never trusted from
 * the browser).
 *
 * @return array{ok:true, data:array}|array{ok:false, message:string}
 */
function holidays_validate_input(array $input): array
{
    $name = trim((string)($input['name'] ?? ''));
    $date = trim((string)($input['date'] ?? ''));
    $type = trim((string)($input['type'] ?? ''));
    $multiplier = $input['pay_multiplier'] ?? null;
    $isNationwide = !empty($input['is_nationwide']);
    $region = trim((string)($input['region'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));

    if ($name === '') {
        return ['ok' => false, 'message' => 'Holiday name is required.'];
    }
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 100, 'UTF-8');
    } else {
        $name = substr($name, 0, 100);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return ['ok' => false, 'message' => 'A valid holiday date (YYYY-MM-DD) is required.'];
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return ['ok' => false, 'message' => 'The holiday date is not a valid calendar date.'];
    }

    if ($type === '' || !in_array($type, holidays_allowed_types(), true)) {
        return ['ok' => false, 'message' => 'A valid holiday type is required.'];
    }

    if (!is_numeric($multiplier)) {
        return ['ok' => false, 'message' => 'A valid pay rate / multiplier is required.'];
    }
    $multiplier = round((float)$multiplier, 2);
    if ($multiplier < 1.00 || $multiplier > 9.99) {
        return ['ok' => false, 'message' => 'Pay multiplier must be between 1.00 and 9.99.'];
    }

    if ($region !== '' && function_exists('mb_substr')) {
        $region = mb_substr($region, 0, 100, 'UTF-8');
    }

    return [
        'ok' => true,
        'data' => [
            'name' => $name,
            'date' => $date,
            'type' => $type,
            'pay_multiplier' => $multiplier,
            'is_nationwide' => $isNationwide,
            'region' => $region !== '' ? $region : null,
            'year' => (int)$d->format('Y'),
            'description' => $description !== '' ? $description : null,
        ],
    ];
}

/**
 * Decides whether a new/edited holiday may share a date with existing ones.
 * Per the Double Holiday reference, the ONLY same-date combination that's
 * defined is exactly two *different* Regular/Legal Holidays (e.g. Maundy
 * Thursday landing on Araw ng Kagitingan). Everything else sharing a date
 * is treated as a genuine conflict:
 *   - an exact name+date duplicate (accidental re-entry)
 *   - a date that already has 2 Regular Holidays (reference only covers two)
 *   - any combination involving a non-"Regular Holiday" type (Special
 *     Non-working/Working Day, Local holiday) -- no reference formula
 *     exists for those sharing a date, so not invented here
 *
 * @return array{ok:true}|array{ok:false, message:string}
 */
function holidays_check_same_date_conflict(mysqli $conn, string $date, string $name, string $type, ?int $excludeId): array
{
    $stmt = $conn->prepare('SELECT HolidayID, HolidayName, HolidayType FROM holidays WHERE HolidayDate = ?' . ($excludeId !== null ? ' AND HolidayID <> ?' : ''));
    if ($excludeId !== null) {
        $stmt->bind_param('si', $date, $excludeId);
    } else {
        $stmt->bind_param('s', $date);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = [];
    while ($row = $res->fetch_assoc()) {
        $existing[] = $row;
    }
    $stmt->close();

    if (empty($existing)) {
        return ['ok' => true];
    }

    foreach ($existing as $row) {
        if (strcasecmp((string)$row['HolidayName'], $name) === 0) {
            return ['ok' => false, 'message' => 'A holiday with that name already exists on that date.'];
        }
    }

    if ($type !== 'Regular Holiday' || (string)$existing[0]['HolidayType'] !== 'Regular Holiday') {
        return ['ok' => false, 'message' => 'A holiday is already defined on that date. Two holidays may only share a date when both are Regular/Legal Holidays (Double Holiday).'];
    }

    if (count($existing) >= 2) {
        return ['ok' => false, 'message' => 'That date already has two Regular Holidays defined (Double Holiday) -- a third cannot be added.'];
    }

    return ['ok' => true];
}

function holidays_create(mysqli $conn, array $input): array
{
    holidays_ensure_schema($conn);
    $validated = holidays_validate_input($input);
    if (!$validated['ok']) {
        return ['success' => false, 'message' => $validated['message']];
    }
    $data = $validated['data'];

    $conflict = holidays_check_same_date_conflict($conn, $data['date'], $data['name'], $data['type'], null);
    if (!$conflict['ok']) {
        return ['success' => false, 'message' => $conflict['message']];
    }

    $stmt = $conn->prepare(
        'INSERT INTO holidays (HolidayName, HolidayDate, HolidayType, PayMultiplier, IsNationwide, Region, Year, Description)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $isNationwide = $data['is_nationwide'] ? 1 : 0;
    $stmt->bind_param(
        'sssdisis',
        $data['name'],
        $data['date'],
        $data['type'],
        $data['pay_multiplier'],
        $isNationwide,
        $data['region'],
        $data['year'],
        $data['description']
    );
    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Could not save the holiday.'];
    }
    return ['success' => true, 'id' => $stmt->insert_id];
}

function holidays_update(mysqli $conn, int $id, array $input): array
{
    holidays_ensure_schema($conn);
    if (!holidays_find_by_id($conn, $id)) {
        return ['success' => false, 'message' => 'Holiday not found.'];
    }
    $validated = holidays_validate_input($input);
    if (!$validated['ok']) {
        return ['success' => false, 'message' => $validated['message']];
    }
    $data = $validated['data'];

    $conflict = holidays_check_same_date_conflict($conn, $data['date'], $data['name'], $data['type'], $id);
    if (!$conflict['ok']) {
        return ['success' => false, 'message' => $conflict['message']];
    }

    $stmt = $conn->prepare(
        'UPDATE holidays SET HolidayName = ?, HolidayDate = ?, HolidayType = ?, PayMultiplier = ?,
         IsNationwide = ?, Region = ?, Year = ?, Description = ? WHERE HolidayID = ?'
    );
    $isNationwide = $data['is_nationwide'] ? 1 : 0;
    $stmt->bind_param(
        'sssdisisi',
        $data['name'],
        $data['date'],
        $data['type'],
        $data['pay_multiplier'],
        $isNationwide,
        $data['region'],
        $data['year'],
        $data['description'],
        $id
    );
    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Could not update the holiday.'];
    }
    return ['success' => true];
}

function holidays_delete(mysqli $conn, int $id): array
{
    holidays_ensure_schema($conn);
    if (!holidays_find_by_id($conn, $id)) {
        return ['success' => false, 'message' => 'Holiday not found.'];
    }
    $stmt = $conn->prepare('DELETE FROM holidays WHERE HolidayID = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Could not delete the holiday.'];
    }
    return ['success' => true];
}

/**
 * Holiday definitions falling within [start, end], keyed by ISO date, each
 * date mapping to a LIST of holidays on it (not a single entry) so that a
 * Double Holiday -- two Regular/Legal Holidays sharing the same date (e.g.
 * Maundy Thursday landing on Araw ng Kagitingan) -- is detectable rather
 * than silently collapsed to whichever row the query happened to return
 * last. payroll_compute_period() uses this to size the correct holiday-pay
 * add-on per the multiplier(s) Admin configured, instead of assuming a
 * fixed 2x for every holiday.
 *
 * @return array<string, array<int, array{id:int,name:string,type:?string,pay_multiplier:float}>>
 */
function holidays_fetch_for_range(mysqli $conn, string $start, string $end, int $employeeId = 0, ?int $deploymentId = null): array
{
    holidays_ensure_schema($conn);
    $stmt = $conn->prepare(
        'SELECT HolidayID, HolidayName, HolidayDate, HolidayType, PayMultiplier,IsNationwide,Region
         FROM holidays WHERE HolidayDate BETWEEN ? AND ? ORDER BY HolidayID ASC'
    );
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $iso = (string)$row['HolidayDate'];
        if($employeeId>0 && !holiday_applies_to_employee($conn,$row,$employeeId,$iso,$deploymentId))continue;
        if (!isset($out[$iso])) {
            $out[$iso] = [];
        }
        $out[$iso][] = [
            'id' => (int)$row['HolidayID'],
            'name' => (string)$row['HolidayName'],
            'type' => $row['HolidayType'] !== null ? (string)$row['HolidayType'] : null,
            'pay_multiplier' => isset($row['PayMultiplier']) ? (float)$row['PayMultiplier'] : 1.0,
        ];
    }
    return $out;
}