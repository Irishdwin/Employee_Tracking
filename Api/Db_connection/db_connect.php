<?php
/**
 * Shared mysqli connection. Uses 127.0.0.1 to avoid Windows socket issues.
 * Forces global Philippine Standard Time (Asia/Manila, UTC+08:00).
 */
date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = '127.0.0.1';
$username = 'root';
$password = '';
$dbname = 'onazul_hris_db';

$isApiRequest = isset($_SERVER['REQUEST_URI']) && strpos((string)$_SERVER['REQUEST_URI'], '/Api/') !== false;

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if (!$conn->set_charset('utf8mb4')) {
        error_log('db_connect set_charset: ' . $conn->error);
    }
    // Force MySQL session timezone to Philippine Standard Time (+08:00)
    $conn->query("SET time_zone = '+08:00'");
} catch (Throwable $e) {
    error_log('db_connect: ' . $e->getMessage());
    if ($isApiRequest) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Database unavailable. Start MySQL in XAMPP (MariaDB), then try again.',
        ]);
        exit();
    }
    die('Connection failed: ' . $e->getMessage());
}