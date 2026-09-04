-- Applicant job board filter schema (phpMyAdmin / onazul_hris_db)
-- Maps existing joborder + applicant_profiles to the job_orders API shape.

-- 1) Applicant preferred category (used by filter=match)
ALTER TABLE `applicant_profiles`
  ADD COLUMN IF NOT EXISTS `preferred_category` VARCHAR(100) DEFAULT NULL
  COMMENT 'e.g. Retail, Cashier, Accounting, Driver';

-- Sync from existing JobCategory when preferred_category is empty
UPDATE applicant_profiles ap
INNER JOIN applicants a ON a.UserID = ap.UserID
SET ap.preferred_category = a.JobCategory
WHERE (ap.preferred_category IS NULL OR TRIM(ap.preferred_category) = '')
  AND a.JobCategory IS NOT NULL AND TRIM(a.JobCategory) <> '';

-- 2) Job order category + status on source table
ALTER TABLE `joborder`
  ADD COLUMN IF NOT EXISTS `JobCategory` VARCHAR(100) DEFAULT NULL AFTER `Position`;

ALTER TABLE `joborder`
  ADD COLUMN IF NOT EXISTS `Status` ENUM('Open','Closed') NOT NULL DEFAULT 'Open';

-- Infer categories from position titles where missing
UPDATE joborder SET JobCategory = 'Cashier'
WHERE (JobCategory IS NULL OR TRIM(JobCategory) = '')
  AND LOWER(Position) LIKE '%cashier%';

UPDATE joborder SET JobCategory = 'Chef'
WHERE (JobCategory IS NULL OR TRIM(JobCategory) = '')
  AND (LOWER(Position) LIKE '%chef%' OR LOWER(Position) LIKE '%kitchen%');

UPDATE joborder SET JobCategory = 'Security'
WHERE (JobCategory IS NULL OR TRIM(JobCategory) = '')
  AND (LOWER(Position) LIKE '%security%' OR LOWER(Position) LIKE '%guard%');

-- 3) Read-only view for reporting / manual queries (optional)
DROP VIEW IF EXISTS `job_orders`;
CREATE VIEW `job_orders` AS
SELECT
    jo.JobOrderID AS id,
    jo.Position AS job_title,
    c.Name AS company_name,
    jo.Location AS location,
    jo.WorkSchedule AS work_shift,
    COALESCE(NULLIF(jo.PublishedDate, '0000-00-00'), NULLIF(jo.RequestDate, '0000-00-00'), DATE(jo.CreatedAt)) AS date_posted,
    COALESCE(NULLIF(jo.JobCategory, ''), 'General') AS job_category,
    CASE
        WHEN jo.Status = 'Closed' THEN 'Closed'
        WHEN (SELECT COUNT(*) FROM job_applications ja
              WHERE ja.JobOrderID = jo.JobOrderID AND ja.HiredDate IS NOT NULL) >= jo.RequiredCount
        THEN 'Closed'
        ELSE 'Open'
    END AS status,
    jo.Qualifications AS qualifications,
    jo.RequiredSkills AS required_skills,
    jo.RequiredCount AS required_count,
    jo.CreatedAt AS created_at
FROM joborder jo
INNER JOIN clientcompany c ON c.CompanyID = jo.CompanyID
WHERE c.IsActive = 1;

-- Optimized: all open roles
-- SELECT * FROM job_orders WHERE status = 'Open' ORDER BY date_posted DESC;

-- Optimized: match applicant category (replace :preferred_category)
-- SELECT * FROM job_orders
-- WHERE status = 'Open' AND job_category = :preferred_category
-- ORDER BY date_posted DESC;
