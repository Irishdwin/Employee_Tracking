<?php
/**
 * Shared PDO connection for attendance APIs.
 */
function attendance_pdo(?mysqli $conn = null): PDO
{
    static $connections = [];
    $database=$conn ? (string)$conn->query('SELECT DATABASE()')->fetch_row()[0] : 'onazul_hris_db';
    if(!preg_match('/\A[a-zA-Z0-9_]+\z/D',$database))throw new RuntimeException('Invalid attendance database.');
    if(isset($connections[$database]))return $connections[$database];
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname='.$database.';charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $connections[$database]=$pdo;
}

function attendance_ensure_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS daily_time_records (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          employee_id INT NOT NULL,
          team_lead_user_id INT DEFAULT NULL,
          date DATE NOT NULL,
          time_in DATETIME DEFAULT NULL,
          break_time DATETIME DEFAULT NULL,
          time_out DATETIME DEFAULT NULL,
          total_hours_rendered DECIMAL(6,2) DEFAULT NULL,
          status ENUM('Absent','Clocked In','On Break','Clocked Out') NOT NULL DEFAULT 'Absent',
          qr_scan_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_dtr_employee_date (employee_id, date),
          KEY idx_dtr_date_status (date, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}