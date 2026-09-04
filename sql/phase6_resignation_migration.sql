-- Phase 6: Resignation & Exit Process (30 Working Days Policy)
-- Run once in phpMyAdmin

-- Statuses for resignation flow
INSERT IGNORE INTO `status` (`Name`) VALUES
  ('Resignation Pending'),
  ('Clearance Issued'),
  ('Clearance In Progress'),
  ('Clearance Complete'),
  ('Final Pay Ready'),
  ('Quitclaim Ready'),
  ('Resigned');

-- Add columns. Run each; ignore "Duplicate column" if exists.
ALTER TABLE resignationrequest ADD COLUMN CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE resignationrequest ADD COLUMN ClearanceIssuedDate DATETIME DEFAULT NULL;
ALTER TABLE resignationrequest ADD COLUMN ClearanceIssuedBy INT DEFAULT NULL;

-- Default clearance items template (HR adds items per resignation; these are common ones)
-- clearance_items are created per resignation by HR when issuing clearance

-- Archive table for 201 File
CREATE TABLE IF NOT EXISTS `resignation_archive` (
  `ArchiveID` INT NOT NULL AUTO_INCREMENT,
  `ResignationID` INT NOT NULL,
  `EmployeeID` INT NOT NULL,
  `ArchiveMetadata` TEXT DEFAULT NULL COMMENT 'Employment records, DTR refs, payslip refs (JSON)',
  `ArchivePath` VARCHAR(500) DEFAULT NULL,
  `ArchivedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ArchivedBy` INT DEFAULT NULL,
  PRIMARY KEY (`ArchiveID`),
  KEY `idx_resignation` (`ResignationID`),
  KEY `idx_employee` (`EmployeeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
