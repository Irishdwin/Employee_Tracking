-- Employee salary configuration for payroll staging (Admin hire / deployment).
-- Target table: employee (singular in onazul_hris_db)

ALTER TABLE `employee`
  ADD COLUMN IF NOT EXISTS `daily_rate` DECIMAL(10, 2) NULL AFTER `BasicSalary`,
  ADD COLUMN IF NOT EXISTS `hourly_rate` DECIMAL(10, 2) NULL AFTER `daily_rate`,
  ADD COLUMN IF NOT EXISTS `allowance` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER `hourly_rate`,
  ADD COLUMN IF NOT EXISTS `monthly_salary_credit` DECIMAL(10, 2) NULL AFTER `allowance`;

-- Backfill legacy rows from BasicSalary where possible (26-day credit convention).
UPDATE `employee`
SET
  `daily_rate` = ROUND(COALESCE(`BasicSalary`, 0) / 26, 2),
  `hourly_rate` = ROUND(COALESCE(`BasicSalary`, 0) / 26 / 8, 2),
  `monthly_salary_credit` = ROUND(COALESCE(`BasicSalary`, 0), 2)
WHERE (`daily_rate` IS NULL OR `daily_rate` = 0)
  AND COALESCE(`BasicSalary`, 0) > 0;

UPDATE `employee`
SET
  `daily_rate` = 0.00,
  `hourly_rate` = 0.00,
  `monthly_salary_credit` = 0.00
WHERE `daily_rate` IS NULL;

-- Enforce NOT NULL on computed payroll fields for new hires.
ALTER TABLE `employee`
  MODIFY COLUMN `daily_rate` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `hourly_rate` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `monthly_salary_credit` DECIMAL(10, 2) NOT NULL DEFAULT 0.00;
