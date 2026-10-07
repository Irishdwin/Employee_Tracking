-- Stores hashes only. Existing account passwords and HRIS records are unchanged.
CREATE TABLE IF NOT EXISTS employee_password_history (
 HistoryID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 UserID INT UNSIGNED NOT NULL,
 PasswordHash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_employee_password_hash (UserID,PasswordHash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
