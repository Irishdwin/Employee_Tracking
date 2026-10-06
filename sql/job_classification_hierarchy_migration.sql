-- Job Category -> Classification -> Sub-classification hierarchy
-- (phpMyAdmin / onazul_hris_db). ADDITIVE ONLY.
--
-- job_categories already existed (shared by job orders and the applicant
-- profile) but had no active/inactive flag. No classification/sub-
-- classification table existed anywhere in the schema — the values were
-- hardcoded in Js/Applicant/applicant_profile_cascades.js as
-- CLASSIFICATION_CASCADE. This migration adds is_active to the existing
-- table and creates the two new ones, seeded from that same hardcoded data
-- so nothing an applicant could previously pick disappears.

-- NOTE: run this file once. CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT
-- EXISTS are safe to re-run, but the two ADD CONSTRAINT lines below are not
-- (MySQL/MariaDB has no "IF NOT EXISTS" for constraints) — if you run this
-- twice you'll get a harmless "duplicate key name" error on those two
-- lines specifically; everything else will have already re-applied cleanly.

ALTER TABLE `job_categories`
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS `job_classifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `classification_name` VARCHAR(100) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_classification_per_category` (`category_id`, `classification_name`),
  KEY `idx_classification_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `job_sub_classifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `classification_id` INT UNSIGNED NOT NULL,
  `sub_classification_name` VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subclass_per_classification` (`classification_id`, `sub_classification_name`),
  KEY `idx_subclass_classification` (`classification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `job_classifications`
  ADD CONSTRAINT `fk_classification_category` FOREIGN KEY (`category_id`) REFERENCES `job_categories` (`id`);

ALTER TABLE `job_sub_classifications`
  ADD CONSTRAINT `fk_subclass_classification` FOREIGN KEY (`classification_id`) REFERENCES `job_classifications` (`id`);

-- Seed: mapping the 7 existing job_categories to the classifications that
-- used to be hardcoded in JS. "Food & Beverage / Hospitality" maps to two
-- classifications since the category combined both concepts; the others
-- are close to 1:1. "General Utilities & Maintenance" had no hardcoded
-- equivalent, so a new, reasonable classification/sub-classification set
-- was created for it.

INSERT IGNORE INTO job_classifications (category_id, classification_name, is_default)
SELECT id, 'Retail & Sales', 1 FROM job_categories WHERE category_name = 'Retail & Sales';

INSERT IGNORE INTO job_classifications (category_id, classification_name, is_default)
SELECT id, 'Food & Beverage', 1 FROM job_categories WHERE category_name = 'Food & Beverage / Hospitality';
INSERT IGNORE INTO job_classifications (category_id, classification_name, is_default)
SELECT id, 'Hospitality', 0 FROM job_categories WHERE category_name = 'Food & Beverage / Hospitality';

INSERT IGNORE INTO job_classifications (category_id, classification_name, is_default)
SELECT id, 'Logistics & Warehouse', 1 FROM job_categories WHERE category_name = 'Logistics, Warehousing & Driving';

INSERT IGNORE INTO job_classifications (category_id, classification_name, is_default)
SELECT id, 'Security & Safety', 1 FROM job_categories WHERE category_name = 'Security & Protective Services';

INSERT IGNORE INTO job_classifications (category_id, classification_name, is_default)
SELECT id, 'Customer Service & BPO', 1 FROM job_categories WHERE category_name = 'Customer Service & BPO';

INSERT IGNORE INTO job_classifications (category_id, classification_name, is_default)
SELECT id, 'Corporate & Admin', 1 FROM job_categories WHERE category_name = 'Corporate, Office & Administrative';

INSERT IGNORE INTO job_classifications (category_id, classification_name, is_default)
SELECT id, 'General Utilities & Maintenance', 1 FROM job_categories WHERE category_name = 'General Utilities & Maintenance';

-- Sub-classifications, same source data as the old hardcoded object.
INSERT IGNORE INTO job_sub_classifications (classification_id, sub_classification_name)
SELECT jc.id, s.name FROM job_classifications jc
JOIN (
  SELECT 'Waiter/Waitress' AS name UNION SELECT 'Barista' UNION SELECT 'Kitchen Staff'
  UNION SELECT 'Chef' UNION SELECT 'Cashier' UNION SELECT 'Food Server'
) s ON 1=1
WHERE jc.classification_name = 'Food & Beverage';

INSERT IGNORE INTO job_sub_classifications (classification_id, sub_classification_name)
SELECT jc.id, s.name FROM job_classifications jc
JOIN (
  SELECT 'Sales Clerk' AS name UNION SELECT 'Cashier' UNION SELECT 'Merchandiser'
  UNION SELECT 'Promodiser' UNION SELECT 'Retail Associate'
) s ON 1=1
WHERE jc.classification_name = 'Retail & Sales';

INSERT IGNORE INTO job_sub_classifications (classification_id, sub_classification_name)
SELECT jc.id, s.name FROM job_classifications jc
JOIN (
  SELECT 'Housekeeping' AS name UNION SELECT 'Front Desk Agent' UNION SELECT 'Bellhop' UNION SELECT 'Room Attendant'
) s ON 1=1
WHERE jc.classification_name = 'Hospitality';

INSERT IGNORE INTO job_sub_classifications (classification_id, sub_classification_name)
SELECT jc.id, s.name FROM job_classifications jc
JOIN (
  SELECT 'Security Guard' AS name UNION SELECT 'Lady Guard' UNION SELECT 'Safety Officer' UNION SELECT 'CCTV Operator'
) s ON 1=1
WHERE jc.classification_name = 'Security & Safety';

INSERT IGNORE INTO job_sub_classifications (classification_id, sub_classification_name)
SELECT jc.id, s.name FROM job_classifications jc
JOIN (
  SELECT 'Warehouse Staff' AS name UNION SELECT 'Forklift Operator' UNION SELECT 'Delivery Rider' UNION SELECT 'Picker/Packer'
) s ON 1=1
WHERE jc.classification_name = 'Logistics & Warehouse';

INSERT IGNORE INTO job_sub_classifications (classification_id, sub_classification_name)
SELECT jc.id, s.name FROM job_classifications jc
JOIN (
  SELECT 'Call Center Agent' AS name UNION SELECT 'Customer Support' UNION SELECT 'Chat Support'
) s ON 1=1
WHERE jc.classification_name = 'Customer Service & BPO';

INSERT IGNORE INTO job_sub_classifications (classification_id, sub_classification_name)
SELECT jc.id, s.name FROM job_classifications jc
JOIN (
  SELECT 'Office Staff' AS name UNION SELECT 'Data Encoder' UNION SELECT 'Receptionist' UNION SELECT 'Admin Assistant'
) s ON 1=1
WHERE jc.classification_name = 'Corporate & Admin';

-- New: sub-classifications for the previously-unmapped category.
INSERT IGNORE INTO job_sub_classifications (classification_id, sub_classification_name)
SELECT jc.id, s.name FROM job_classifications jc
JOIN (
  SELECT 'Janitor' AS name UNION SELECT 'Maintenance Worker' UNION SELECT 'Utility Worker' UNION SELECT 'General Cleaner'
) s ON 1=1
WHERE jc.classification_name = 'General Utilities & Maintenance';
