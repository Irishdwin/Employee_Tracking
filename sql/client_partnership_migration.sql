-- Client Partnership Phase: Service Agreement Status & Job Order Work Schedule
-- Run this on onazul_hris_db

ALTER TABLE `serviceagreement`
  ADD COLUMN `Status` ENUM('Active','Expired') DEFAULT 'Active' AFTER `Notes`;

ALTER TABLE `joborder`
  ADD COLUMN `WorkSchedule` TEXT DEFAULT NULL AFTER `Location`;

-- Backfill Status for existing agreements based on ContractEndDate
UPDATE `serviceagreement`
SET `Status` = IF(ContractEndDate IS NULL OR ContractEndDate >= CURDATE(), 'Active', 'Expired')
WHERE `Status` IS NULL;
