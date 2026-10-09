-- Additive; existing overtime rows retain their legacy approval behavior.
ALTER TABLE overtime
 ADD COLUMN IF NOT EXISTS EmployeeResponseRequired TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS EmployeeResponse VARCHAR(20) NULL,
 ADD COLUMN IF NOT EXISTS EmployeeRespondedAt DATETIME NULL,
 ADD COLUMN IF NOT EXISTS EmployeeRejectionReason TEXT NULL,
 ADD COLUMN IF NOT EXISTS ActualTimeIn DATETIME NULL,
 ADD COLUMN IF NOT EXISTS ActualTimeOut DATETIME NULL,
 ADD COLUMN IF NOT EXISTS ClockInEvidence TEXT NULL,
 ADD COLUMN IF NOT EXISTS ClockOutEvidence TEXT NULL;
INSERT INTO status (Name) SELECT 'Accepted' WHERE NOT EXISTS (SELECT 1 FROM status WHERE Name='Accepted');
