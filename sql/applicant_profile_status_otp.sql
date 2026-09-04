-- Applicant email OTP + onboarding profile status (run once if you prefer SQL over PHP auto-migrate).
-- Api/Applicant/*.php also adds missing columns via ALTER TABLE when needed.
-- If a column already exists, skip that line or comment it out.

ALTER TABLE `applicants`
  ADD COLUMN `ProfileStatus` TINYINT NOT NULL DEFAULT 0
    COMMENT '0=new (terms pending),1=terms accepted,2=profile setup complete';

ALTER TABLE `applicants`
  ADD COLUMN `JobCategory` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `applicants`
  ADD COLUMN `OtpCodeHash` VARCHAR(64) DEFAULT NULL COMMENT 'Optional: hash if persisting OTP in DB';

ALTER TABLE `applicants`
  ADD COLUMN `OtpExpiresAt` DATETIME DEFAULT NULL COMMENT 'Optional: OTP expiry when using DB storage';

-- Note: Current sign-in flow stores the 6-digit OTP in PHP session (send_signin_code.php).
-- DB OTP columns are optional for auditing or multi-device flows.

UPDATE `applicants`
SET `ProfileStatus` = 2
WHERE `ProfileDetailsCompleted` = 1
  AND `ProfileVisibilityCompleted` = 1
  AND `BirthDate` IS NOT NULL
  AND `ProfileStatus` < 2;

UPDATE `applicants`
SET `ProfileStatus` = 1
WHERE COALESCE(`RegistrationTermsAccepted`, 0) = 1
  AND `ProfileStatus` < 2
  AND (`ProfileDetailsCompleted` = 0 OR `ProfileVisibilityCompleted` = 0 OR `BirthDate` IS NULL);
