-- Optional: track applicant lifecycle on the applicants table (run once in phpMyAdmin).
ALTER TABLE `applicants`
  ADD COLUMN IF NOT EXISTS `ApplicantStatus` varchar(30) DEFAULT 'Active' AFTER `ProfileVisibilityCompleted`;

-- Mark existing hired users (applications with HiredDate set).
UPDATE applicants a
INNER JOIN job_applications ja ON ja.ApplicantID = a.ApplicantID
SET a.ApplicantStatus = 'Hired'
WHERE ja.HiredDate IS NOT NULL;

INSERT IGNORE INTO `status` (`Name`) VALUES ('Hired');
