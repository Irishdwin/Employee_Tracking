<?php
/**
 * Job category helpers — dynamic lookup table with legacy fallback.
 */
require_once __DIR__ . '/job_categories_lookup.php';

/** @deprecated Use job_categories_fetch_all() */
function job_category_allowed_list(): array
{
    try {
        $pdo = hris_pdo();
        $rows = job_categories_fetch_all($pdo);
        if (!empty($rows)) {
            return array_map(static function ($r) {
                return $r['category_name'];
            }, $rows);
        }
    } catch (Throwable $e) {
    }
    return ['Cashier', 'Chef', 'Security'];
}

/**
 * Returns canonical category name or empty string if invalid.
 * Accepts category id (numeric string) or category name.
 */
function job_category_normalize(?string $cat): string
{
    $cat = trim((string)$cat);
    if ($cat === '' || $cat === '0') {
        return '';
    }

    try {
        $pdo = hris_pdo();
        $resolved = job_category_resolve_input($pdo, is_numeric($cat) ? (int)$cat : 0, is_numeric($cat) ? '' : $cat);
        if ($resolved) {
            return $resolved['category_name'];
        }
        if (!is_numeric($cat)) {
            $inferred = job_category_infer_from_text($pdo, $cat);
            if ($inferred) {
                return $inferred['category_name'];
            }
        }
    } catch (Throwable $e) {
    }

    return '';
}

/** One-time data fix: map legacy slug values to lookup names. */
function job_category_migrate_legacy_values(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo = hris_pdo();
        job_categories_ensure_schema($pdo);
    } catch (Throwable $e) {
        return;
    }

    $tables = ['joborder', 'applicants', 'job_order_requests'];
    foreach ($tables as $table) {
        try {
            $cols = $conn->query("SHOW COLUMNS FROM {$table} LIKE 'JobCategory'");
            if (!$cols || $cols->num_rows === 0) {
                continue;
            }

            $res = $conn->query("SELECT DISTINCT JobCategory FROM {$table} WHERE JobCategory IS NOT NULL AND TRIM(JobCategory) <> ''");
            if (!$res) {
                continue;
            }

            while ($row = $res->fetch_assoc()) {
                $oldValStr = trim((string)($row['JobCategory'] ?? ''));
                if ($oldValStr === '') {
                    continue;
                }
                $resolved = job_category_resolve_input($pdo, 0, $oldValStr);
                if (!$resolved) {
                    $resolved = job_category_infer_from_text($pdo, $oldValStr);
                }
                if (!$resolved || $resolved['category_name'] === $oldValStr) {
                    continue;
                }

                $upd = $conn->prepare("UPDATE {$table} SET JobCategory = ? WHERE JobCategory = ?");
                if (!$upd) {
                    continue;
                }
                $newVal = $resolved['category_name'];
                $upd->bind_param('ss', $newVal, $oldValStr);
                $upd->execute();
                $upd->close();

                if (hris_pdo_column_exists($pdo, $table, 'category_id')) {
                    $upd2 = $conn->prepare("UPDATE {$table} SET category_id = ? WHERE JobCategory = ?");
                    if ($upd2) {
                        $id = $resolved['id'];
                        $upd2->bind_param('is', $id, $newVal);
                        $upd2->execute();
                        $upd2->close();
                    }
                }
            }
            $res->free();
        } catch (Throwable $e) {
        }
    }
}
