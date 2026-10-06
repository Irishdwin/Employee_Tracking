-- Additive Add Back workflow. No attendance/DTR values are rewritten.
CREATE TABLE IF NOT EXISTS add_back_requests (
 RequestID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 EmployeeID INT NOT NULL, SubmittedBy INT NOT NULL, DeploymentID INT NOT NULL,
 CompanyID INT NOT NULL, AttendanceID INT NULL, WorkDate DATE NOT NULL,
 DutyStart DATETIME NOT NULL, DutyEnd DATETIME NOT NULL,
 RequestedHours DECIMAL(6,2) NOT NULL, OriginalHours DECIMAL(6,2) NOT NULL,
 Reason TEXT NOT NULL, SourceSnapshot LONGTEXT NOT NULL, RateSnapshot LONGTEXT NULL,
 StatusID INT NOT NULL, SubmittedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ReviewedBy INT NULL, ReviewedAt DATETIME NULL, ReviewRemarks TEXT NULL,
 ApprovedHours DECIMAL(6,2) NULL, ApprovedAmount DECIMAL(12,2) NULL,
 CalculationSnapshot LONGTEXT NULL, TargetStart DATE NULL, TargetEnd DATE NULL,
 Payroll_ID INT NULL,
 KEY ix_addback_employee_date (EmployeeID,WorkDate),
 KEY ix_addback_target (EmployeeID,DeploymentID,TargetStart,TargetEnd,StatusID),
 FOREIGN KEY (EmployeeID) REFERENCES employee(EmployeeID),
 FOREIGN KEY (SubmittedBy) REFERENCES users(UsersID),
 FOREIGN KEY (DeploymentID) REFERENCES deployment(DeploymentID),
 FOREIGN KEY (CompanyID) REFERENCES clientcompany(CompanyID),
 FOREIGN KEY (AttendanceID) REFERENCES attendance(AttendanceID),
 FOREIGN KEY (StatusID) REFERENCES status(StatusID),
 FOREIGN KEY (ReviewedBy) REFERENCES users(UsersID),
 FOREIGN KEY (Payroll_ID) REFERENCES payroll(Payroll_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS add_back_evidence (
 EvidenceID INT NOT NULL AUTO_INCREMENT PRIMARY KEY, RequestID INT NOT NULL,
 EvidenceType VARCHAR(20) NOT NULL, StorageName VARCHAR(100) NOT NULL,
 MimeType VARCHAR(50) NOT NULL, ByteSize INT NOT NULL, Sha256 CHAR(64) NOT NULL,
 CapturedAt DATETIME NULL, CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_addback_evidence_type (RequestID,EvidenceType),
 FOREIGN KEY (RequestID) REFERENCES add_back_requests(RequestID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE payroll_adjustments MODIFY Payroll_ID INT NULL;
ALTER TABLE payroll_adjustments ADD COLUMN IF NOT EXISTS AddBackRequestID INT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_adjustment_addback ON payroll_adjustments(AddBackRequestID);
SET @addback_fk_exists=(SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='payroll_adjustments' AND CONSTRAINT_NAME='fk_pa_addback_request');
SET @addback_fk_sql=IF(@addback_fk_exists=0,
 'ALTER TABLE payroll_adjustments ADD CONSTRAINT fk_pa_addback_request FOREIGN KEY(AddBackRequestID) REFERENCES add_back_requests(RequestID)',
 'SELECT 1');
PREPARE addback_fk_statement FROM @addback_fk_sql;
EXECUTE addback_fk_statement;
DEALLOCATE PREPARE addback_fk_statement;
ALTER TABLE salary_rates ADD COLUMN IF NOT EXISTS DailyRate DECIMAL(10,2) NULL;
ALTER TABLE salary_rates ADD COLUMN IF NOT EXISTS HourlyRate DECIMAL(10,2) NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS EvidenceDeploymentID INT NULL;
