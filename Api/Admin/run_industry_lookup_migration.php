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
        CREATE TABLE IF NOT EXISTS industry_lookup (
            IndustryID INT(11) NOT NULL AUTO_INCREMENT,
            Name VARCHAR(100) NOT NULL,
            CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (IndustryID),
            UNIQUE KEY uniq_industry_name (Name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed from existing clientcompany.Industry values
    $res = $conn->query("SELECT DISTINCT TRIM(Industry) AS IndustryName FROM clientcompany WHERE Industry IS NOT NULL AND TRIM(Industry) <> ''");
    if ($res) {
        $stmt = $conn->prepare("INSERT IGNORE INTO industry_lookup (Name) VALUES (?)");
        while ($row = $res->fetch_assoc()) {
            $name = $row['IndustryName'];
            if ($name === null || $name === '') continue;
            $stmt->bind_param("s", $name);
            $stmt->execute();
        }
        $stmt->close();
    }

    header('Content-Type: text/plain');
    echo "OK: industry_lookup ready.\n";
    echo "You can now use /Employee_Tracking/Api/Admin/industries.php\n";
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Migration failed: " . $e->getMessage();
}

