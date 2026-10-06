<?php
/**
 * Dynamic job_categories lookup (PDO).
 */
require_once __DIR__ . '/hris_pdo.php';

function job_categories_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS job_categories (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          category_name VARCHAR(100) NOT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_job_categories_name (category_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    if (!hris_pdo_column_exists($pdo, 'job_categories', 'is_active')) {
        $pdo->exec("ALTER TABLE job_categories ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }

    $seeds = [
        'Retail & Sales',
        'Food & Beverage / Hospitality',
        'Logistics, Warehousing & Driving',
        'Security & Protective Services',
        'Customer Service & BPO',
        'Corporate, Office & Administrative',
        'General Utilities & Maintenance',
    ];
    if ((int)$pdo->query('SELECT COUNT(*) FROM job_categories')->fetchColumn() === 0) {
        $ins = $pdo->prepare('INSERT IGNORE INTO job_categories (category_name) VALUES (?)');
        foreach ($seeds as $name) $ins->execute([$name]);
    }

    foreach (['joborder', 'job_order_requests'] as $table) {
        if (!hris_pdo_column_exists($pdo, $table, 'category_id')) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN category_id INT UNSIGNED DEFAULT NULL");
        }
        if (!hris_pdo_column_exists($pdo, $table, 'JobCategory')) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN JobCategory VARCHAR(100) DEFAULT NULL");
        }
    }

    // job_classifications / job_sub_classifications didn't exist anywhere in
    // the schema before — the values lived hardcoded in
    // Js/Applicant/applicant_profile_cascades.js. Created + seeded here too
    // (self-migrating, same pattern as the rest of this helper) so the
    // feature works even if sql/job_classification_hierarchy_migration.sql
    // hasn't been run manually yet.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS job_classifications (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          category_id INT UNSIGNED NOT NULL,
          classification_name VARCHAR(100) NOT NULL,
          is_default TINYINT(1) NOT NULL DEFAULT 0,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_classification_per_category (category_id, classification_name),
          KEY idx_classification_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS job_sub_classifications (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          classification_id INT UNSIGNED NOT NULL,
          sub_classification_name VARCHAR(100) NOT NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_subclass_per_classification (classification_id, sub_classification_name),
          KEY idx_subclass_classification (classification_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    job_classification_seed_defaults($pdo);
}

/** One-time seed mirroring the old hardcoded CLASSIFICATION_CASCADE object. */
function job_classification_seed_defaults(PDO $pdo): void
{
    $already = (int)$pdo->query('SELECT COUNT(*) FROM job_classifications')->fetchColumn();
    if ($already > 0) {
        return; // already seeded (or Admin has since edited the list) — never overwrite
    }

    $seedMap = [
        'Retail & Sales' => [
            ['Retail & Sales', true, ['Sales Clerk', 'Cashier', 'Merchandiser', 'Promodiser', 'Retail Associate']],
        ],
        'Food & Beverage / Hospitality' => [
            ['Food & Beverage', true, ['Waiter/Waitress', 'Barista', 'Kitchen Staff', 'Chef', 'Cashier', 'Food Server']],
            ['Hospitality', false, ['Housekeeping', 'Front Desk Agent', 'Bellhop', 'Room Attendant']],
        ],
        'Logistics, Warehousing & Driving' => [
            ['Logistics & Warehouse', true, ['Warehouse Staff', 'Forklift Operator', 'Delivery Rider', 'Picker/Packer']],
        ],
        'Security & Protective Services' => [
            ['Security & Safety', true, ['Security Guard', 'Lady Guard', 'Safety Officer', 'CCTV Operator']],
        ],
        'Customer Service & BPO' => [
            ['Customer Service & BPO', true, ['Call Center Agent', 'Customer Support', 'Chat Support']],
        ],
        'Corporate, Office & Administrative' => [
            ['Corporate & Admin', true, ['Office Staff', 'Data Encoder', 'Receptionist', 'Admin Assistant']],
        ],
        'General Utilities & Maintenance' => [
            ['General Utilities & Maintenance', true, ['Janitor', 'Maintenance Worker', 'Utility Worker', 'General Cleaner']],
        ],
    ];

    $catStmt = $pdo->prepare('SELECT id FROM job_categories WHERE category_name = ? LIMIT 1');
    $insClass = $pdo->prepare('INSERT IGNORE INTO job_classifications (category_id, classification_name, is_default) VALUES (?, ?, ?)');
    $insSub = $pdo->prepare('INSERT IGNORE INTO job_sub_classifications (classification_id, sub_classification_name) VALUES (?, ?)');

    foreach ($seedMap as $categoryName => $classifications) {
        $catStmt->execute([$categoryName]);
        $categoryId = $catStmt->fetchColumn();
        if (!$categoryId) continue;

        foreach ($classifications as [$className, $isDefault, $subNames]) {
            $insClass->execute([(int)$categoryId, $className, $isDefault ? 1 : 0]);
            $classId = (int)$pdo->lastInsertId();
            if ($classId <= 0) {
                $find = $pdo->prepare('SELECT id FROM job_classifications WHERE category_id = ? AND classification_name = ? LIMIT 1');
                $find->execute([(int)$categoryId, $className]);
                $classId = (int)$find->fetchColumn();
            }
            if ($classId <= 0) continue;
            foreach ($subNames as $subName) {
                $insSub->execute([$classId, $subName]);
            }
        }
    }
}

/** @return array<int, array{id:int, category_name:string, is_active:bool}> */
function job_categories_fetch_all(PDO $pdo, bool $activeOnly = false): array
{
    job_categories_ensure_schema($pdo);
    $sql = 'SELECT id, category_name, is_active FROM job_categories';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY category_name ASC';
    $rows = $pdo->query($sql)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'category_name' => (string)$row['category_name'],
            'is_active' => (bool)$row['is_active'],
        ];
    }
    return $out;
}

function job_category_create(PDO $pdo, string $name): array
{
    job_categories_ensure_schema($pdo);
    $name = job_category_sanitize_name($name);
    if ($name === '') {
        return ['success' => false, 'message' => 'Category name is required.'];
    }
    $existing = job_category_find_by_name($pdo, $name);
    if ($existing) {
        return ['success' => false, 'message' => 'That category already exists.'];
    }
    $stmt = $pdo->prepare('INSERT INTO job_categories (category_name) VALUES (?)');
    $stmt->execute([$name]);
    return ['success' => true, 'id' => (int)$pdo->lastInsertId(), 'category_name' => $name];
}

function job_category_update(PDO $pdo, int $id, string $name): array
{
    job_categories_ensure_schema($pdo);
    $name = job_category_sanitize_name($name);
    if ($id <= 0 || $name === '') {
        return ['success' => false, 'message' => 'A valid category and name are required.'];
    }
    if (!job_category_find_by_id($pdo, $id)) {
        return ['success' => false, 'message' => 'Category not found.'];
    }
    $dupe = job_category_find_by_name($pdo, $name);
    if ($dupe && $dupe['id'] !== $id) {
        return ['success' => false, 'message' => 'Another category already uses that name.'];
    }
    $stmt = $pdo->prepare('UPDATE job_categories SET category_name = ? WHERE id = ?');
    $stmt->execute([$name, $id]);
    return ['success' => true, 'id' => $id, 'category_name' => $name];
}

function job_category_set_active(PDO $pdo, int $id, bool $active): array
{
    job_categories_ensure_schema($pdo);
    if ($id <= 0 || !job_category_find_by_id($pdo, $id)) {
        return ['success' => false, 'message' => 'Category not found.'];
    }
    $stmt = $pdo->prepare('UPDATE job_categories SET is_active = ? WHERE id = ?');
    $stmt->execute([$active ? 1 : 0, $id]);
    return ['success' => true, 'id' => $id, 'is_active' => $active];
}

/** @return array<int, array{id:int, classification_name:string, is_default:bool, is_active:bool}> */
function job_classifications_fetch_by_category(PDO $pdo, int $categoryId, bool $activeOnly = true): array
{
    job_categories_ensure_schema($pdo);
    $sql = 'SELECT id, classification_name, is_default, is_active FROM job_classifications WHERE category_id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY is_default DESC, classification_name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoryId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'classification_name' => (string)$row['classification_name'],
            'is_default' => (bool)$row['is_default'],
            'is_active' => (bool)$row['is_active'],
        ];
    }
    return $out;
}

function job_classification_find_by_id(PDO $pdo, int $id): ?array
{
    if ($id <= 0) return null;
    job_categories_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT id, category_id, classification_name, is_default, is_active FROM job_classifications WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'id' => (int)$row['id'],
        'category_id' => (int)$row['category_id'],
        'classification_name' => (string)$row['classification_name'],
        'is_default' => (bool)$row['is_default'],
        'is_active' => (bool)$row['is_active'],
    ];
}

/** The classification an applicant with no experience should be auto-assigned for a given category. */
function job_classification_get_default_for_category(PDO $pdo, int $categoryId): ?array
{
    $options = job_classifications_fetch_by_category($pdo, $categoryId, true);
    if (empty($options)) return null;
    foreach ($options as $opt) {
        if ($opt['is_default']) return $opt;
    }
    return $options[0]; // fetch already orders is_default DESC, so this is a safe fallback
}

function job_classification_create(PDO $pdo, int $categoryId, string $name, bool $isDefault = false): array
{
    job_categories_ensure_schema($pdo);
    $name = job_category_sanitize_name($name);
    if ($categoryId <= 0 || $name === '' || !job_category_find_by_id($pdo, $categoryId)) {
        return ['success' => false, 'message' => 'A valid category and classification name are required.'];
    }
    $stmt = $pdo->prepare('SELECT id FROM job_classifications WHERE category_id = ? AND classification_name = ? LIMIT 1');
    $stmt->execute([$categoryId, $name]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'That classification already exists for this category.'];
    }
    if ($isDefault) {
        $pdo->prepare('UPDATE job_classifications SET is_default = 0 WHERE category_id = ?')->execute([$categoryId]);
    }
    $ins = $pdo->prepare('INSERT INTO job_classifications (category_id, classification_name, is_default) VALUES (?, ?, ?)');
    $ins->execute([$categoryId, $name, $isDefault ? 1 : 0]);
    return ['success' => true, 'id' => (int)$pdo->lastInsertId()];
}

function job_classification_update(PDO $pdo, int $id, string $name): array
{
    $name = job_category_sanitize_name($name);
    $existing = job_classification_find_by_id($pdo, $id);
    if (!$existing || $name === '') {
        return ['success' => false, 'message' => 'A valid classification and name are required.'];
    }
    $stmt = $pdo->prepare('UPDATE job_classifications SET classification_name = ? WHERE id = ?');
    $stmt->execute([$name, $id]);
    return ['success' => true];
}

function job_classification_set_active(PDO $pdo, int $id, bool $active): array
{
    if (!job_classification_find_by_id($pdo, $id)) {
        return ['success' => false, 'message' => 'Classification not found.'];
    }
    $pdo->prepare('UPDATE job_classifications SET is_active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    return ['success' => true];
}

function job_classification_set_default(PDO $pdo, int $id): array
{
    $existing = job_classification_find_by_id($pdo, $id);
    if (!$existing) {
        return ['success' => false, 'message' => 'Classification not found.'];
    }
    $pdo->prepare('UPDATE job_classifications SET is_default = 0 WHERE category_id = ?')->execute([$existing['category_id']]);
    $pdo->prepare('UPDATE job_classifications SET is_default = 1 WHERE id = ?')->execute([$id]);
    return ['success' => true];
}

/** @return array<int, array{id:int, sub_classification_name:string, is_active:bool}> */
function job_sub_classifications_fetch_by_classification(PDO $pdo, int $classificationId, bool $activeOnly = true): array
{
    job_categories_ensure_schema($pdo);
    $sql = 'SELECT id, sub_classification_name, is_active FROM job_sub_classifications WHERE classification_id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sub_classification_name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$classificationId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'sub_classification_name' => (string)$row['sub_classification_name'],
            'is_active' => (bool)$row['is_active'],
        ];
    }
    return $out;
}

function job_sub_classification_find_by_id(PDO $pdo, int $id): ?array
{
    if ($id <= 0) return null;
    $stmt = $pdo->prepare('SELECT id, classification_id, sub_classification_name, is_active FROM job_sub_classifications WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'id' => (int)$row['id'],
        'classification_id' => (int)$row['classification_id'],
        'sub_classification_name' => (string)$row['sub_classification_name'],
        'is_active' => (bool)$row['is_active'],
    ];
}

function job_sub_classification_create(PDO $pdo, int $classificationId, string $name): array
{
    $name = job_category_sanitize_name($name);
    if ($classificationId <= 0 || $name === '' || !job_classification_find_by_id($pdo, $classificationId)) {
        return ['success' => false, 'message' => 'A valid classification and sub-classification name are required.'];
    }
    $stmt = $pdo->prepare('SELECT id FROM job_sub_classifications WHERE classification_id = ? AND sub_classification_name = ? LIMIT 1');
    $stmt->execute([$classificationId, $name]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'That sub-classification already exists for this classification.'];
    }
    $ins = $pdo->prepare('INSERT INTO job_sub_classifications (classification_id, sub_classification_name) VALUES (?, ?)');
    $ins->execute([$classificationId, $name]);
    return ['success' => true, 'id' => (int)$pdo->lastInsertId()];
}

function job_sub_classification_update(PDO $pdo, int $id, string $name): array
{
    $name = job_category_sanitize_name($name);
    if (!job_sub_classification_find_by_id($pdo, $id) || $name === '') {
        return ['success' => false, 'message' => 'A valid sub-classification and name are required.'];
    }
    $pdo->prepare('UPDATE job_sub_classifications SET sub_classification_name = ? WHERE id = ?')->execute([$name, $id]);
    return ['success' => true];
}

function job_sub_classification_set_active(PDO $pdo, int $id, bool $active): array
{
    if (!job_sub_classification_find_by_id($pdo, $id)) {
        return ['success' => false, 'message' => 'Sub-classification not found.'];
    }
    $pdo->prepare('UPDATE job_sub_classifications SET is_active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    return ['success' => true];
}

function job_category_sanitize_name(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 100, 'UTF-8');
    }
    return substr($name, 0, 100);
}

function job_category_legacy_name_map(): array
{
    return [
        'cashier' => 'Retail & Sales',
        'sales_customer' => 'Retail & Sales',
        'retail' => 'Retail & Sales',
        'sales clerk' => 'Retail & Sales',
        'merchandiser' => 'Retail & Sales',
        'chef' => 'Food & Beverage / Hospitality',
        'food_prep_service' => 'Food & Beverage / Hospitality',
        'kitchen' => 'Food & Beverage / Hospitality',
        'waiter' => 'Food & Beverage / Hospitality',
        'security' => 'Security & Protective Services',
        'protective_service' => 'Security & Protective Services',
        'guard' => 'Security & Protective Services',
        'driver' => 'Logistics, Warehousing & Driving',
        'delivery' => 'Logistics, Warehousing & Driving',
        'bpo' => 'Customer Service & BPO',
        'call center' => 'Customer Service & BPO',
        'encoder' => 'Corporate, Office & Administrative',
        'accounting' => 'Corporate, Office & Administrative',
        'janitor' => 'General Utilities & Maintenance',
        'housekeeper' => 'General Utilities & Maintenance',
    ];
}

function job_category_infer_from_text(PDO $pdo, string $text): ?array
{
    $lc = strtolower(trim($text));
    if ($lc === '') {
        return null;
    }
    $map = job_category_legacy_name_map();
    foreach ($map as $needle => $categoryName) {
        if (strpos($lc, $needle) !== false) {
            return job_category_find_by_name($pdo, $categoryName);
        }
    }
    return null;
}

function job_category_find_by_name(PDO $pdo, string $name): ?array
{
    job_categories_ensure_schema($pdo);
    $name = job_category_sanitize_name($name);
    if ($name === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, category_name FROM job_categories WHERE category_name = ? LIMIT 1');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return ['id' => (int)$row['id'], 'category_name' => (string)$row['category_name']];
    }
    $stmt = $pdo->prepare('SELECT id, category_name FROM job_categories WHERE LOWER(category_name) = LOWER(?) LIMIT 1');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return ['id' => (int)$row['id'], 'category_name' => (string)$row['category_name']];
    }
    return null;
}

function job_category_find_by_id(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    job_categories_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT id, category_name FROM job_categories WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return ['id' => (int)$row['id'], 'category_name' => (string)$row['category_name']];
}

/**
 * Resolve category_id and/or job_category name from form input.
 *
 * @param mixed $categoryId
 * @param mixed $categoryName
 */
function job_category_resolve_input(PDO $pdo, $categoryId, $categoryName = ''): ?array
{
    job_categories_ensure_schema($pdo);

    $id = is_numeric($categoryId) ? (int)$categoryId : 0;
    if ($id > 0) {
        $byId = job_category_find_by_id($pdo, $id);
        if ($byId) {
            return $byId;
        }
    }

    $name = job_category_sanitize_name((string)$categoryName);
    if ($name !== '') {
        $lc = strtolower($name);
        $legacy = job_category_legacy_name_map();
        if (isset($legacy[$lc])) {
            $name = $legacy[$lc];
        }
        $byName = job_category_find_by_name($pdo, $name);
        if ($byName) {
            return $byName;
        }
    }

    if ($name !== '') {
        return job_category_infer_from_text($pdo, $name);
    }

    return null;
}

function job_category_submission_alert(string $position): string
{
    $position = trim($position);
    if ($position === '') {
        $position = 'this role';
    }
    return "Submission Alert: The position '{$position}' requires a valid category. "
        . "If the required category is missing from the list, please use the 'Add New Category' option before resubmitting.";
}

function job_category_invalid_response(string $position): array
{
    return [
        'success' => false,
        'error_type' => 'invalid_category',
        'message' => job_category_submission_alert($position),
    ];
}
