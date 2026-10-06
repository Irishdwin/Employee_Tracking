-- Additive: retain application IDs and snapshot each existing current endorsement.
CREATE TABLE IF NOT EXISTS application_endorsement_attempts (
 AttemptID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 ApplicationID INT NOT NULL,
 AttemptNumber INT NOT NULL,
 JobOrderID INT NOT NULL,
 CompanyID INT NOT NULL,
 TargetDetailID INT NULL,
 CompanyName VARCHAR(150) NOT NULL,
 PositionTitle VARCHAR(255) NOT NULL,
 DeploymentSite VARCHAR(255) NULL,
 EndorsedBy INT NULL,
 EndorsedAt DATETIME NOT NULL,
 Decision ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
 DecidedBy INT NULL,
 DecidedAt DATETIME NULL,
 DecisionReason TEXT NULL,
 ReendorsementReason TEXT NULL,
 LegacySnapshot TINYINT NOT NULL DEFAULT 0,
 PendingApplicationID INT GENERATED ALWAYS AS (CASE WHEN Decision='Pending' THEN ApplicationID ELSE NULL END) STORED,
 UNIQUE KEY uq_attempt_number(ApplicationID,AttemptNumber),
 UNIQUE KEY uq_pending_endorsement(PendingApplicationID),
 CONSTRAINT fk_endorsement_application FOREIGN KEY(ApplicationID) REFERENCES job_applications(ApplicationID),
 CONSTRAINT fk_endorsement_order FOREIGN KEY(JobOrderID) REFERENCES joborder(JobOrderID),
 CONSTRAINT fk_endorsement_company FOREIGN KEY(CompanyID) REFERENCES clientcompany(CompanyID),
 CONSTRAINT fk_endorsement_sender FOREIGN KEY(EndorsedBy) REFERENCES users(UsersID),
 CONSTRAINT fk_endorsement_reviewer FOREIGN KEY(DecidedBy) REFERENCES users(UsersID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO application_endorsement_attempts
 (ApplicationID,AttemptNumber,JobOrderID,CompanyID,TargetDetailID,CompanyName,PositionTitle,DeploymentSite,EndorsedBy,EndorsedAt,Decision,DecidedAt,DecisionReason,LegacySnapshot)
SELECT ja.ApplicationID,1,ja.JobOrderID,jo.CompanyID,ja.target_detail_id,c.Name,
 COALESCE(d.position_title,jo.Position),COALESCE(d.deployment_site,jo.Location),ja.EndorsedBy,ja.EndorsedDate,
 COALESCE(ja.ClientApprovalStatus,'Pending'),ja.ClientApprovalDate,
 CASE WHEN ja.ClientApprovalStatus='Rejected' THEN ja.Remarks ELSE NULL END,1
FROM job_applications ja JOIN joborder jo ON jo.JobOrderID=ja.JobOrderID
JOIN clientcompany c ON c.CompanyID=jo.CompanyID
LEFT JOIN job_order_details d ON d.detail_id=ja.target_detail_id AND d.JobOrderID=ja.JobOrderID
WHERE ja.EndorsedDate IS NOT NULL
AND NOT EXISTS(SELECT 1 FROM application_endorsement_attempts ea WHERE ea.ApplicationID=ja.ApplicationID);
