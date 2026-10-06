-- Additive Team Schedule migration. Current recurring schedules remain in deployment.
-- FixedHistory rows archive prior recurring settings; Dated rows are daily overrides.
CREATE TABLE IF NOT EXISTS team_schedule_entries (
    ScheduleID INT NOT NULL AUTO_INCREMENT,
    DeploymentID INT NOT NULL,
    EntryKind ENUM('Dated', 'FixedHistory') NOT NULL DEFAULT 'Dated',
    ScheduleDate DATE NOT NULL,
    ValidUntil DATE DEFAULT NULL,
    ScheduleType ENUM('Fixed', 'Rotating', 'Flexible') NOT NULL,
    TimeIn TIME NOT NULL,
    TimeOut TIME NOT NULL,
    BreakDuration INT UNSIGNED NOT NULL DEFAULT 60,
    GracePeriod INT UNSIGNED NOT NULL DEFAULT 5,
    WorkingDays VARCHAR(100) NOT NULL,
    ShiftName VARCHAR(100) NOT NULL,
    UpdatedBy INT NOT NULL,
    UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ScheduleID),
    UNIQUE KEY uq_team_schedule_date (DeploymentID, ScheduleDate, EntryKind),
    CONSTRAINT fk_team_schedule_deployment FOREIGN KEY (DeploymentID) REFERENCES deployment (DeploymentID),
    CONSTRAINT fk_team_schedule_updated_by FOREIGN KEY (UpdatedBy) REFERENCES users (UsersID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
