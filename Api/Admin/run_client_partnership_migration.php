<?php
/**
 * Run Client Partnership migration.
 * Execute once: open in browser as Admin or run: php run_client_partnership_migration.php
 */
require __DIR__ . '/../Db_connection/db_connect.php';

$sqls = [
    "ALTER TABLE `serviceagreement` ADD COLUMN `Status` ENUM('Active','Expired') DEFAULT 'Active' AFTER `Notes`",
    "ALTER TABLE `joborder` ADD COLUMN `WorkSchedule` TEXT DEFAULT NULL AFTER `Location`",
];

$done = [];
$errors = [];

foreach ($sqls as $sql) {
    if ($conn->query($sql)) {
        $done[] = substr($sql, 0, 60) . '...';
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            $done[] = '(column already exists) ' . substr($sql, 0, 50) . '...';
        } else {
            $errors[] = $conn->error;
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => empty($errors),
    'executed' => $done,
    'errors' => $errors,
], JSON_PRETTY_PRINT);
