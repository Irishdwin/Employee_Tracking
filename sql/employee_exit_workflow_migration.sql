-- Additive exit workflow. Existing physical records are retained for HR review.
ALTER TABLE resignationrequest ADD COLUMN IF NOT EXISTS WorkflowDetails LONGTEXT NULL;
CREATE TABLE IF NOT EXISTS exit_workflow_history (
 HistoryID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 RecordType VARCHAR(20) NOT NULL, RecordID INT NOT NULL, ActorID INT NOT NULL,
 Action VARCHAR(80) NOT NULL, Details LONGTEXT NOT NULL,
 CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY exit_history_record (RecordType,RecordID),
 CONSTRAINT exit_history_actor FOREIGN KEY (ActorID) REFERENCES users(UsersID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS awol_review_cases (
 CaseID INT NOT NULL AUTO_INCREMENT PRIMARY KEY, EmployeeID INT NOT NULL,
 EpisodeStart DATE NOT NULL, EpisodeEnd DATE NOT NULL,
 State VARCHAR(30) NOT NULL DEFAULT 'Pending Review',
 Evidence LONGTEXT NOT NULL, PreviousStatusID INT NULL,
 ReviewedBy INT NULL, ReviewedAt DATETIME NULL, Remarks TEXT NULL,
 UNIQUE KEY awol_episode (EmployeeID,EpisodeStart),
 CONSTRAINT awol_employee FOREIGN KEY (EmployeeID) REFERENCES employee(EmployeeID),
 CONSTRAINT awol_reviewer FOREIGN KEY (ReviewedBy) REFERENCES users(UsersID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
