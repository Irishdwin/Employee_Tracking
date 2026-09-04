<?php
/**
 * Shared PDO connection for HRIS applicant APIs.
 */
function hris_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=onazul_hris_db;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

function hris_pdo_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function hris_jobs_ensure_schema(PDO $pdo): void
{
    if (!hris_pdo_column_exists($pdo, 'applicant_profiles', 'preferred_category')) {
        $pdo->exec(
            "ALTER TABLE applicant_profiles
             ADD COLUMN preferred_category VARCHAR(100) DEFAULT NULL
             COMMENT 'Retail, Cashier, Accounting, Driver, etc.'"
        );
    }
    if (!hris_pdo_column_exists($pdo, 'joborder', 'JobCategory')) {
        $pdo->exec(
            "ALTER TABLE joborder
             ADD COLUMN JobCategory VARCHAR(100) DEFAULT NULL AFTER Position"
        );
    }
    if (!hris_pdo_column_exists($pdo, 'joborder', 'Status')) {
        $pdo->exec(
            "ALTER TABLE joborder
             ADD COLUMN Status ENUM('Open','Closed') NOT NULL DEFAULT 'Open'"
        );
    }
}
