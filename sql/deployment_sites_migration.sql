-- Additive migration. Existing employee deployments and submission history remain intact.
CREATE TABLE IF NOT EXISTS deployment_sites (
 SiteID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 CompanyID INT NOT NULL,
 Name VARCHAR(150) NOT NULL,
 Address TEXT NULL,
 TeamLeadID INT NULL,
 IsActive TINYINT(1) NOT NULL DEFAULT 1,
 LegacyDefault TINYINT(1) NULL,
 registered_latitude DECIMAL(10,8) NULL,
 registered_longitude DECIMAL(11,8) NULL,
 geofence_radius INT NOT NULL DEFAULT 100,
 site_registered_at DATETIME NULL,
 registered_by_tl_id INT NULL,
 UNIQUE KEY one_legacy_default (CompanyID, LegacyDefault),
 KEY site_lead (TeamLeadID, IsActive),
 FOREIGN KEY (CompanyID) REFERENCES clientcompany(CompanyID),
 FOREIGN KEY (TeamLeadID) REFERENCES users(UsersID)
) ENGINE=InnoDB;
ALTER TABLE deployment ADD COLUMN IF NOT EXISTS SiteID INT NULL;
ALTER TABLE dtr_submissions ADD COLUMN IF NOT EXISTS SiteID INT NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS DTRReminderKey VARCHAR(100) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_dtr_reminder ON notifications(DTRReminderKey);
CREATE INDEX IF NOT EXISTS ix_deployment_site ON deployment(SiteID);
CREATE INDEX IF NOT EXISTS ix_submission_site_cutoff ON dtr_submissions(SiteID,CutoffStartDate,CutoffEndDate);
-- Only a single distinct legacy location can be safely mapped automatically.
INSERT INTO deployment_sites (CompanyID,Name,Address,TeamLeadID,LegacyDefault,
 registered_latitude,registered_longitude,geofence_radius,site_registered_at,registered_by_tl_id)
SELECT c.CompanyID,c.Name,COALESCE(x.Location,c.Address),c.UserID,1,
 CASE WHEN c.registered_latitude IS NOT NULL AND c.registered_longitude IS NOT NULL THEN c.registered_latitude ELSE c.latitude END,
 CASE WHEN c.registered_latitude IS NOT NULL AND c.registered_longitude IS NOT NULL THEN c.registered_longitude ELSE c.longitude END,
 COALESCE(c.geofence_radius,100),c.site_registered_at,c.registered_by_tl_id
FROM clientcompany c LEFT JOIN (
 SELECT CompanyID,MAX(NULLIF(TRIM(Location),'')) Location,
 COUNT(DISTINCT NULLIF(TRIM(Location),'')) Locations FROM deployment GROUP BY CompanyID
) x ON x.CompanyID=c.CompanyID
WHERE COALESCE(x.Locations,0)<=1
AND NOT EXISTS(SELECT 1 FROM deployment_sites s WHERE s.CompanyID=c.CompanyID AND s.LegacyDefault=1);
UPDATE deployment d JOIN deployment_sites s ON s.CompanyID=d.CompanyID AND s.LegacyDefault=1
SET d.SiteID=s.SiteID WHERE d.SiteID IS NULL;
UPDATE dtr_submissions ds JOIN deployment d ON d.DeploymentID=ds.DeploymentID
SET ds.SiteID=d.SiteID WHERE ds.SiteID IS NULL;
