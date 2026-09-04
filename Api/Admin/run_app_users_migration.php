<?php
/**
 * Create app_users table (used by login, create_admin, create_hr, users API).
 * Run once in browser or: php run_app_users_migration.php
 */
require __DIR__ . '/../Db_connection/db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS `app_users` (
    `user_id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `role` varchar(50) NOT NULL,
    `full_name` varchar(100) NOT NULL,
    `status` varchar(20) DEFAULT 'Active',
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

$ok = $conn->query($sql);
header('Content-Type: application/json');
echo json_encode([
    'success' => (bool)$ok,
    'message' => $ok ? 'Table app_users ready.' : $conn->error,
], JSON_PRETTY_PRINT);
