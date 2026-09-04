<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo "Forbidden";
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS job_order_requests (
            RequestID INT(11) NOT NULL AUTO_INCREMENT,
            RequestedBy INT(11) NOT NULL,
            CompanyID INT(11) NOT NULL,
            Position VARCHAR(100) NOT NULL,
            JobCategory VARCHAR(255) DEFAULT NULL,
            RequiredCount INT(11) NOT NULL DEFAULT 1,
            Location VARCHAR(150) DEFAULT NULL,
            WorkSchedule VARCHAR(100) DEFAULT NULL,
            Remarks TEXT DEFAULT NULL,
            Status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
            ProcessedBy INT(11) DEFAULT NULL,
            ProcessedAt DATETIME DEFAULT NULL,
            JobOrderID INT(11) DEFAULT NULL,
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (RequestID),
            KEY fk_jor_requested (RequestedBy),
            KEY fk_jor_company (CompanyID),
            KEY fk_jor_processed (ProcessedBy),
            KEY idx_jor_joborder (JobOrderID),
            CONSTRAINT fk_jor_requested FOREIGN KEY (RequestedBy) REFERENCES users (UsersID),
            CONSTRAINT fk_jor_company FOREIGN KEY (CompanyID) REFERENCES clientcompany (CompanyID),
            CONSTRAINT fk_jor_processed FOREIGN KEY (ProcessedBy) REFERENCES users (UsersID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Self-heal if JobCategory was added after the table already existed.
    try {
        $cols = $conn->query("SHOW COLUMNS FROM job_order_requests LIKE 'JobCategory'");
        if ($cols && $cols->num_rows === 0) {
            $conn->query("ALTER TABLE job_order_requests ADD COLUMN JobCategory VARCHAR(255) DEFAULT NULL");
        }
    } catch (Throwable $e) {}

    header('Content-Type: text/plain');
    echo "OK: job_order_requests table created.\n";
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Migration failed: " . $e->getMessage();
}
