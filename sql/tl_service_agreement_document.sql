-- Team Lead service agreement document (phpMyAdmin / onazul_hris_db)
--
-- The signed contract file is stored on serviceagreement.SignedDocumentPath
-- (e.g. uploads/service_agreements/sa_12_1710000000.pdf).
-- API alias: document_path → same column as SignedDocumentPath.

-- Optional: add document_path as a generated alias (MySQL 5.7+ / MariaDB 10.2+)
-- Skip if you prefer using SignedDocumentPath only.
-- ALTER TABLE `serviceagreement`
--   ADD COLUMN `document_path` VARCHAR(255)
--   GENERATED ALWAYS AS (`SignedDocumentPath`) STORED
--   AFTER `SignedDocumentPath`;

-- Ensure document column exists (safe re-run)
SET @db := DATABASE();
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'serviceagreement'
    AND COLUMN_NAME = 'SignedDocumentPath'
);
-- If missing, uncomment and run once:
-- ALTER TABLE `serviceagreement`
--   ADD COLUMN `SignedDocumentPath` VARCHAR(255) DEFAULT NULL
--   COMMENT 'Relative path e.g. uploads/service_agreements/sa_1.pdf'
--   AFTER `ContractEndDate`;

-- Reporting view (includes document_path alias)
CREATE OR REPLACE VIEW `vw_tl_service_agreement_document` AS
SELECT
    u.UsersID                 AS team_lead_user_id,
    c.CompanyID               AS company_id,
    c.Name                    AS company_name,
    sa.ServiceAgreementID     AS service_agreement_id,
    sa.ContractNumber         AS contract_number,
    sa.ContractStartDate      AS service_agreement_start,
    sa.ContractEndDate        AS service_agreement_end,
    sa.Status                 AS agreement_status,
    sa.SignedDocumentPath     AS signed_document_path,
    sa.SignedDocumentPath     AS document_path
FROM clientcompany c
INNER JOIN users u ON u.UsersID = c.UserID
LEFT JOIN serviceagreement sa ON sa.ClientCompanyID = c.CompanyID
    AND sa.ServiceAgreementID = (
        SELECT sa2.ServiceAgreementID
        FROM serviceagreement sa2
        WHERE sa2.ClientCompanyID = c.CompanyID
          AND sa2.Status = 'Active'
        ORDER BY sa2.ContractStartDate DESC, sa2.ServiceAgreementID DESC
        LIMIT 1
    );
