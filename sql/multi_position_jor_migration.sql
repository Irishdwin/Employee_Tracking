-- Multi-position Job Order Request migration (onazul_hris_db)
-- Extends existing job_order_requests (RequestID) — does NOT replace the current table.
-- Run in phpMyAdmin SQL tab.

-- 1) Child table: multiple positions / deployment sites per parent request
CREATE TABLE IF NOT EXISTS `job_order_details` (
  `detail_id` INT(11) NOT NULL AUTO_INCREMENT,
  `RequestID` INT(11) NOT NULL COMMENT 'Parent JOR — maps to job_order_requests.RequestID',
  `position_title` VARCHAR(255) NOT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `job_category` VARCHAR(255) DEFAULT NULL,
  `required_workers` INT(11) NOT NULL DEFAULT 1,
  `deployment_site` VARCHAR(255) NOT NULL,
  `work_schedule` VARCHAR(150) NOT NULL,
  `JobOrderID` INT(11) DEFAULT NULL COMMENT 'Set when HR posts to job wall',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`detail_id`),
  KEY `idx_jod_request` (`RequestID`),
  KEY `idx_jod_joborder` (`JobOrderID`),
  CONSTRAINT `fk_jod_request` FOREIGN KEY (`RequestID`)
    REFERENCES `job_order_requests` (`RequestID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Flag consolidated (multi-row) parent requests
ALTER TABLE `job_order_requests`
  ADD COLUMN IF NOT EXISTS `IsConsolidated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `Remarks`;

-- 3) Applicant targets a specific placement row when applying
ALTER TABLE `job_applications`
  ADD COLUMN IF NOT EXISTS `target_detail_id` INT(11) DEFAULT NULL AFTER `JobOrderID`;

-- FK (run once; ignore error if constraint already exists)
-- ALTER TABLE `job_applications`
--   ADD CONSTRAINT `fk_app_target_detail`
--   FOREIGN KEY (`target_detail_id`) REFERENCES `job_order_details` (`detail_id`) ON DELETE SET NULL;

-- 4) Optional backfill: copy legacy single-row requests into job_order_details
INSERT INTO job_order_details (
  RequestID, position_title, category_id, job_category,
  required_workers, deployment_site, work_schedule, JobOrderID
)
SELECT
  r.RequestID,
  r.Position,
  r.category_id,
  r.JobCategory,
  r.RequiredCount,
  COALESCE(NULLIF(TRIM(r.Location), ''), 'TBD'),
  COALESCE(NULLIF(TRIM(r.WorkSchedule), ''), 'TBD'),
  r.JobOrderID
FROM job_order_requests r
WHERE NOT EXISTS (
  SELECT 1 FROM job_order_details d WHERE d.RequestID = r.RequestID
);

-- Reporting view
CREATE OR REPLACE VIEW `vw_jor_with_deployments` AS
SELECT
  r.RequestID AS jor_id,
  r.RequestedBy AS tl_id,
  r.CompanyID AS company_id,
  c.Name AS company_name,
  r.Remarks,
  r.Status,
  r.IsConsolidated,
  r.CreatedAt AS date_requested,
  d.detail_id,
  d.position_title,
  d.required_workers,
  d.deployment_site,
  d.work_schedule,
  d.JobOrderID
FROM job_order_requests r
INNER JOIN clientcompany c ON c.CompanyID = r.CompanyID
LEFT JOIN job_order_details d ON d.RequestID = r.RequestID;
