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

    $seeds = [
        'Retail & Sales',
        'Food & Beverage / Hospitality',
        'Logistics, Warehousing & Driving',
        'Security & Protective Services',
        'Customer Service & BPO',
        'Corporate, Office & Administrative',
        'General Utilities & Maintenance',
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO job_categories (category_name) VALUES (?)');
    foreach ($seeds as $name) {
        $ins->execute([$name]);
    }

    foreach (['joborder', 'job_order_requests'] as $table) {
        if (!hris_pdo_column_exists($pdo, $table, 'category_id')) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN category_id INT UNSIGNED DEFAULT NULL");
        }
        if (!hris_pdo_column_exists($pdo, $table, 'JobCategory')) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN JobCategory VARCHAR(100) DEFAULT NULL");
        }
    }
}

/** @return array<int, array{id:int, category_name:string}> */
function job_categories_fetch_all(PDO $pdo): array
{
    job_categories_ensure_schema($pdo);
    $rows = $pdo->query('SELECT id, category_name FROM job_categories ORDER BY category_name ASC')->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'category_name' => (string)$row['category_name'],
        ];
    }
    return $out;
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
