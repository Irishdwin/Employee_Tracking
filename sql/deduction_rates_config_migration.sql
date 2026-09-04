-- Government deduction configuration tables (SSS 2025 tiers + PhilHealth / Pag-IBIG rates).
-- Target database: onazul_hris_db

CREATE TABLE IF NOT EXISTS `sss_rates_config` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `salary_min` DECIMAL(12,2) NOT NULL,
    `salary_max` DECIMAL(12,2) NULL DEFAULT NULL,
    `monthly_salary_credit` DECIMAL(12,2) NOT NULL,
    `regular_ss_er` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `regular_ss_ee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `mpf_er` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `mpf_ee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `ec_er` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    `effective_year` SMALLINT UNSIGNED NOT NULL DEFAULT 2025,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sss_salary_range` (`salary_min`, `salary_max`),
    KEY `idx_sss_msc` (`monthly_salary_credit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gov_percentage_rates` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `philhealth_ee_pct` DECIMAL(6,4) NOT NULL DEFAULT 2.5000 COMMENT 'PhilHealth employee share percentage',
    `philhealth_er_pct` DECIMAL(6,4) NOT NULL DEFAULT 2.5000 COMMENT 'PhilHealth employer share percentage',
    `pagibig_ee_fixed` DECIMAL(10,2) NOT NULL DEFAULT 200.00 COMMENT 'Pag-IBIG employee fixed deduction (PHP)',
    `pagibig_er_fixed` DECIMAL(10,2) NOT NULL DEFAULT 200.00 COMMENT 'Pag-IBIG employer fixed contribution (PHP)',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gov_percentage_rates`
    (`id`, `philhealth_ee_pct`, `philhealth_er_pct`, `pagibig_ee_fixed`, `pagibig_er_fixed`)
SELECT 1, 2.5000, 2.5000, 200.00, 200.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `gov_percentage_rates` WHERE `id` = 1);

-- SSS 2025 brackets are auto-seeded on first API load via deduction_rates_helper.php
-- (61 tiers from MSC 5,000 to 35,000 per SSS Circular 2024-006 / 15% rate).
