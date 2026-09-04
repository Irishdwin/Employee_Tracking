-- Payroll runs for kinsenas client billing (Phase 5)
CREATE TABLE IF NOT EXISTS `payroll_runs` (
  `PayrollRunID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `RunCode` VARCHAR(32) NOT NULL,
  `CompanyID` INT NOT NULL,
  `PeriodStart` DATE NOT NULL,
  `PeriodEnd` DATE NOT NULL,
  `CutoffLabel` VARCHAR(120) DEFAULT NULL,
  `Status` VARCHAR(32) NOT NULL DEFAULT 'Draft',
  `AdminOverheadPct` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `VatPct` DECIMAL(5,2) NOT NULL DEFAULT 12.00,
  `CreatedBy` INT DEFAULT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ApprovedAt` DATETIME DEFAULT NULL,
  PRIMARY KEY (`PayrollRunID`),
  UNIQUE KEY `uk_payroll_runs_code` (`RunCode`),
  KEY `idx_payroll_runs_company` (`CompanyID`),
  KEY `idx_payroll_runs_period` (`PeriodStart`, `PeriodEnd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional columns (auto-added by client_billing_ensure_schema on first API load):
-- BillingRefNo VARCHAR(32), DeploymentSite VARCHAR(255), TotalInvoice DECIMAL(14,2)
