-- Phase 5: Payroll (Kinsenas) - Cutoff 10th and 25th, Pay dates 15th and 30th
-- Run once in phpMyAdmin

-- Statuses for payroll flow
INSERT IGNORE INTO `status` (`Name`) VALUES ('Draft'), ('For Approval'), ('Released');

-- Payroll batches: one batch per cutoff run
CREATE TABLE IF NOT EXISTS `payroll_batches` (
  `PayrollBatchID` INT NOT NULL AUTO_INCREMENT,
  `CutoffDay` TINYINT NOT NULL COMMENT '10 or 25',
  `PeriodYear` SMALLINT NOT NULL,
  `PeriodMonth` TINYINT NOT NULL,
  `PeriodStart` DATE NOT NULL,
  `PeriodEnd` DATE NOT NULL,
  `StatusID` INT DEFAULT NULL,
  `ProcessedBy` INT DEFAULT NULL,
  `ProcessedDate` DATETIME DEFAULT NULL,
  `ApprovedBy` INT DEFAULT NULL,
  `ApprovalDate` DATETIME DEFAULT NULL,
  `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`PayrollBatchID`),
  UNIQUE KEY `uq_cutoff_period` (`CutoffDay`, `PeriodYear`, `PeriodMonth`),
  KEY `idx_status` (`StatusID`),
  KEY `idx_processed` (`ProcessedBy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add PayrollBatchID to payroll. Run this line; if error "Duplicate column" then skip.
ALTER TABLE payroll ADD COLUMN PayrollBatchID INT NULL DEFAULT NULL;
