-- Applicant preferred placement on apply (multi-deployment job orders).
-- This project stores applications in `job_applications` (not a separate `applications` table).

ALTER TABLE `job_applications`
  ADD COLUMN IF NOT EXISTS `target_detail_id` INT(11) DEFAULT NULL AFTER `JobOrderID`;

-- MariaDB 10.4 may not support IF NOT EXISTS on ADD COLUMN; run once or ignore duplicate errors.

-- Optional FK (skip if already exists):
-- ALTER TABLE `job_applications`
--   ADD CONSTRAINT `fk_app_target_detail`
--   FOREIGN KEY (`target_detail_id`) REFERENCES `job_order_details` (`detail_id`) ON DELETE SET NULL;
