-- Real-time QR attendance — daily_time_records (phpMyAdmin / onazul_hris_db)
-- Run once. Safe to re-run: drops only if you uncomment DROP below.

-- DROP TABLE IF EXISTS `daily_time_records`;

CREATE TABLE IF NOT EXISTS `daily_time_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT NOT NULL,
  `team_lead_user_id` INT DEFAULT NULL COMMENT 'TL user who last scanned',
  `date` DATE NOT NULL,
  `time_in` DATETIME DEFAULT NULL,
  `break_time` DATETIME DEFAULT NULL COMMENT 'Break start timestamp',
  `time_out` DATETIME DEFAULT NULL,
  `total_hours_rendered` DECIMAL(6,2) DEFAULT NULL,
  `status` ENUM('Absent','Clocked In','On Break','Clocked Out') NOT NULL DEFAULT 'Absent',
  `qr_scan_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dtr_employee_date` (`employee_id`, `date`),
  KEY `idx_dtr_date_status` (`date`, `status`),
  KEY `idx_dtr_team_lead` (`team_lead_user_id`),
  CONSTRAINT `fk_dtr_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`EmployeeID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optimized: today's attendance for all employees under a Team Lead (SiteSuperVisorID)
-- Replace :team_lead_user_id with the session user id when running manually.
/*
SELECT
    e.EmployeeID              AS employee_id,
    e.EmployeeNumber          AS employee_number,
    CONCAT(e.FirstName, ' ', e.LastName) AS employee_name,
    COALESCE(d.Position, e.Position)     AS position,
    dtr.id                    AS record_id,
    COALESCE(dtr.status, 'Absent')       AS status,
    dtr.time_in,
    dtr.break_time,
    dtr.time_out,
    dtr.total_hours_rendered,
    dtr.updated_at
FROM deployment d
INNER JOIN employee e ON e.EmployeeID = d.EmployeeID AND e.IsActive = 1
LEFT JOIN daily_time_records dtr
    ON dtr.employee_id = e.EmployeeID AND dtr.date = CURDATE()
WHERE d.SiteSuperVisorID = :team_lead_user_id
  AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
GROUP BY e.EmployeeID
ORDER BY employee_name ASC;
*/
