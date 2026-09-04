-- Team Lead ↔ Client Company relationship (Onazul HRIS)
-- Run in phpMyAdmin on database: onazul_hris_db
--
-- EXISTING LINK (no new tables required):
--   clientcompany.UserID  →  users.UsersID  (Team Lead account managing the client)
--   serviceagreement.ClientCompanyID  →  clientcompany.CompanyID
--
-- Logical column map for the TL Profile API:
--   company_name              ← clientcompany.Name
--   company_address           ← clientcompany.Address
--   contact_person            ← clientcompany.ContactPerson
--   contract_number           ← serviceagreement.ContractNumber
--   service_agreement_start   ← serviceagreement.ContractStartDate
--   service_agreement_end     ← serviceagreement.ContractEndDate

-- Index for Team Lead lookups (run once; ignore error if index already exists)
-- ALTER TABLE `clientcompany` ADD KEY `idx_clientcompany_userid` (`UserID`);

-- Optional: enforce referential integrity (skip if FK already exists or UserID is NULL for unassigned clients)
-- ALTER TABLE `clientcompany`
--   ADD CONSTRAINT `fk_clientcompany_team_lead_user`
--   FOREIGN KEY (`UserID`) REFERENCES `users` (`UsersID`)
--   ON DELETE SET NULL ON UPDATE CASCADE;

-- Read-only view for reporting / phpMyAdmin inspection
CREATE OR REPLACE VIEW `vw_tl_assigned_company` AS
SELECT
    u.UsersID                    AS team_lead_user_id,
    u.FullName                   AS team_lead_name,
    u.Email                      AS team_lead_email,
    c.CompanyID                  AS company_id,
    c.Name                       AS company_name,
    c.Address                    AS company_address,
    c.ContactPerson              AS contact_person,
    c.ContactNumber              AS contact_number,
    c.Industry                   AS industry,
    c.IsActive                   AS company_is_active,
    sa.ServiceAgreementID        AS service_agreement_id,
    sa.ContractNumber            AS contract_number,
    sa.ContractStartDate         AS service_agreement_start,
    sa.ContractEndDate           AS service_agreement_end,
    sa.Status                    AS agreement_status,
    sa.SignedDocumentPath        AS signed_document_path
FROM users u
INNER JOIN roles r ON r.RolesID = u.RoleID AND r.RoleName IN ('TeamLead', 'Team Lead')
INNER JOIN clientcompany c ON c.UserID = u.UsersID
LEFT JOIN serviceagreement sa ON sa.ClientCompanyID = c.CompanyID
    AND sa.Status = 'Active'
    AND sa.ServiceAgreementID = (
        SELECT MAX(sa2.ServiceAgreementID)
        FROM serviceagreement sa2
        WHERE sa2.ClientCompanyID = c.CompanyID
          AND sa2.Status = 'Active'
    );
