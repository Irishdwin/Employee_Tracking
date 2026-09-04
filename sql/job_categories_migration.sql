-- Dynamic job categories lookup (phpMyAdmin / onazul_hris_db)
-- Replaces hardcoded Cashier/Chef/Security with expandable categories.

CREATE TABLE IF NOT EXISTS `job_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_categories_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `job_categories` (`category_name`) VALUES
('Retail & Sales'),
('Food & Beverage / Hospitality'),
('Logistics, Warehousing & Driving'),
('Security & Protective Services'),
('Customer Service & BPO'),
('Corporate, Office & Administrative'),
('General Utilities & Maintenance');

-- Foreign keys on job order workflow tables
ALTER TABLE `joborder`
  ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED DEFAULT NULL AFTER `Position`,
  ADD KEY IF NOT EXISTS `idx_joborder_category_id` (`category_id`);

ALTER TABLE `job_order_requests`
  ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED DEFAULT NULL AFTER `Position`,
  ADD KEY IF NOT EXISTS `idx_jor_category_id` (`category_id`);

-- Backfill category_id from legacy JobCategory text
UPDATE `joborder` jo
INNER JOIN `job_categories` jc ON jc.category_name = 'Retail & Sales'
SET jo.category_id = jc.id
WHERE jo.category_id IS NULL
  AND LOWER(TRIM(COALESCE(jo.JobCategory, ''))) IN ('cashier', 'sales_customer', 'retail', 'sales clerk', 'sales clerk');

UPDATE `joborder` jo
INNER JOIN `job_categories` jc ON jc.category_name = 'Food & Beverage / Hospitality'
SET jo.category_id = jc.id
WHERE jo.category_id IS NULL
  AND (
    LOWER(TRIM(COALESCE(jo.JobCategory, ''))) IN ('chef', 'food_prep_service', 'kitchen')
    OR LOWER(jo.Position) LIKE '%chef%'
    OR LOWER(jo.Position) LIKE '%kitchen%'
  );

UPDATE `joborder` jo
INNER JOIN `job_categories` jc ON jc.category_name = 'Security & Protective Services'
SET jo.category_id = jc.id
WHERE jo.category_id IS NULL
  AND (
    LOWER(TRIM(COALESCE(jo.JobCategory, ''))) IN ('security', 'protective_service', 'guard')
    OR LOWER(jo.Position) LIKE '%security%'
    OR LOWER(jo.Position) LIKE '%guard%'
  );

UPDATE `joborder` jo
INNER JOIN `job_categories` jc ON jc.category_name = TRIM(jo.JobCategory)
SET jo.category_id = jc.id
WHERE jo.category_id IS NULL
  AND TRIM(COALESCE(jo.JobCategory, '')) <> '';

UPDATE `job_order_requests` r
INNER JOIN `job_categories` jc ON jc.category_name = 'Retail & Sales'
SET r.category_id = jc.id
WHERE r.category_id IS NULL
  AND LOWER(TRIM(COALESCE(r.JobCategory, ''))) IN ('cashier', 'sales_customer', 'retail');

UPDATE `job_order_requests` r
INNER JOIN `job_categories` jc ON jc.category_name = 'Food & Beverage / Hospitality'
SET r.category_id = jc.id
WHERE r.category_id IS NULL
  AND LOWER(TRIM(COALESCE(r.JobCategory, ''))) IN ('chef', 'food_prep_service', 'kitchen');

UPDATE `job_order_requests` r
INNER JOIN `job_categories` jc ON jc.category_name = 'Security & Protective Services'
SET r.category_id = jc.id
WHERE r.category_id IS NULL
  AND LOWER(TRIM(COALESCE(r.JobCategory, ''))) IN ('security', 'protective_service', 'guard');

UPDATE `job_order_requests` r
INNER JOIN `job_categories` jc ON jc.category_name = TRIM(r.JobCategory)
SET r.category_id = jc.id
WHERE r.category_id IS NULL
  AND TRIM(COALESCE(r.JobCategory, '')) <> '';

-- Sync JobCategory text from lookup for backward-compatible filters
UPDATE `joborder` jo
INNER JOIN `job_categories` jc ON jc.id = jo.category_id
SET jo.JobCategory = jc.category_name
WHERE jo.category_id IS NOT NULL;

UPDATE `job_order_requests` r
INNER JOIN `job_categories` jc ON jc.id = r.category_id
SET r.JobCategory = jc.category_name
WHERE r.category_id IS NOT NULL;
