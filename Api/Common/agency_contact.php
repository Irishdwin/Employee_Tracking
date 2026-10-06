<?php
/**
 * Agency Contact settings — GET (any authenticated role) / POST (Admin, HR
 * only). Backs the "Agency Contact" card on the Applicant application
 * details page (Html/Applicant/my_applications.html) and the Admin/HR
 * "Agency Contact" settings section.
 *
 * Reuses the existing, previously-unused `systemsettings` table
 * (SettingKey/SettingValue/Category/IsEditable/UpdatedBy — see
 * onazul_hris_db.sql) rather than creating a new table. No schema changes;
 * only idempotent default-row seeding (seeded once from the values that
 * were previously hardcoded in my_applications.html, so they remain the
 * real, editable starting values rather than a code-level fallback).
 */
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

const AGENCY_CONTACT_KEYS = [
    'name' => 'agency_contact_name',
    'email' => 'agency_contact_email',
    'phone' => 'agency_contact_phone',
];
const AGENCY_CONTACT_CATEGORY = 'agency_contact';
// The values that were hardcoded in Html/Applicant/my_applications.html
// before this change — used only to seed the row the first time it's read,
// not as a per-request fallback.
const AGENCY_CONTACT_SEED_DEFAULTS = [
    'name' => 'Onazul Manpower Agent',
    'email' => 'talent@onazul.example',
    'phone' => '+63 (000) 000-0000',
];

function agency_contact_ensure_seeded(mysqli $conn): void
{
    foreach (AGENCY_CONTACT_KEYS as $field => $key) {
        $stmt = $conn->prepare('SELECT SettingID FROM systemsettings WHERE SettingKey = ? LIMIT 1');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            continue;
        }
        $value = AGENCY_CONTACT_SEED_DEFAULTS[$field];
        $ins = $conn->prepare(
            'INSERT INTO systemsettings (SettingKey, SettingValue, SettingType, Category, IsEditable)
             VALUES (?, ?, \'text\', ?, 1)'
        );
        $category = AGENCY_CONTACT_CATEGORY;
        $ins->bind_param('sss', $key, $value, $category);
        $ins->execute();
        $ins->close();
    }
}

function agency_contact_fetch(mysqli $conn): array
{
    agency_contact_ensure_seeded($conn);
    $keys = array_values(AGENCY_CONTACT_KEYS);
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $types = str_repeat('s', count($keys));
    $stmt = $conn->prepare("SELECT SettingKey, SettingValue, UpdatedAt FROM systemsettings WHERE SettingKey IN ($placeholders)");
    $stmt->bind_param($types, ...$keys);
    $stmt->execute();
    $res = $stmt->get_result();
    $byKey = [];
    $updatedAt = null;
    while ($row = $res->fetch_assoc()) {
        $byKey[$row['SettingKey']] = $row['SettingValue'];
        if ($updatedAt === null || $row['UpdatedAt'] > $updatedAt) {
            $updatedAt = $row['UpdatedAt'];
        }
    }
    $stmt->close();

    return [
        'name' => $byKey[AGENCY_CONTACT_KEYS['name']] ?? '',
        'email' => $byKey[AGENCY_CONTACT_KEYS['email']] ?? '',
        'phone' => $byKey[AGENCY_CONTACT_KEYS['phone']] ?? '',
        'updated_at' => $updatedAt,
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Any authenticated role may VIEW the agency contact (Applicants
        // need this to render their own application details page).
        echo json_encode(['success' => true, 'contact' => agency_contact_fetch($conn)]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Only Admin/HR may EDIT — enforced server-side, not just by hiding
        // the settings UI from other roles.
        if (!in_array($_SESSION['role'], ['Admin', 'HR'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only Admin or HR can update the Agency Contact.']);
            exit();
        }

        $raw = file_get_contents('php://input');
        $input = json_decode($raw ?: '', true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $name = trim((string)($input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));

        if ($name === '' || $email === '' || $phone === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Agency Contact Name, Email, and Phone Number are all required.']);
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']);
            exit();
        }
        if (mb_strlen($name) > 150 || mb_strlen($email) > 150 || mb_strlen($phone) > 50) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'One or more fields exceed the allowed length.']);
            exit();
        }

        agency_contact_ensure_seeded($conn);

        $userId = (int)$_SESSION['user_id'];
        $values = [
            AGENCY_CONTACT_KEYS['name'] => $name,
            AGENCY_CONTACT_KEYS['email'] => $email,
            AGENCY_CONTACT_KEYS['phone'] => $phone,
        ];
        $stmt = $conn->prepare('UPDATE systemsettings SET SettingValue = ?, UpdatedBy = ? WHERE SettingKey = ?');
        foreach ($values as $key => $value) {
            $stmt->bind_param('sis', $value, $userId, $key);
            $stmt->execute();
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Agency Contact updated.', 'contact' => agency_contact_fetch($conn)]);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
} catch (Throwable $e) {
    error_log('[agency_contact] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to process Agency Contact settings.']);
}