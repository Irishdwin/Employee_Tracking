# ONAZUL ERD update report

Original: Onazul_WORD_ERD.drawio; 32 entities.

Current installed production schema: 67 tables (56 in the base dump); 30 SQL migration/support files inspected.

Validation uses read-only live information_schema metadata, cross-checked against the project dump, SQL migrations and PHP schema helpers. No data rows were queried and no DDL/DML was executed. Commented optional constraints and views are excluded.

## Project versus installed database differences
Project-defined tables not installed (excluded from final ERD): ['app_users', 'payroll_batches', 'resignation_archive'].
Installed tables absent from parsed project definitions (included from verified metadata): ['payroll_run_status_history'].
Project-only column definitions absent from live schema: {'payroll': ['PayrollBatchID'], 'resignationrequest': ['ClearanceIssuedBy', 'ClearanceIssuedDate', 'CreatedAt'], 'applicants': ['ApplicantStatus', 'OtpCodeHash', 'OtpExpiresAt']}.
Additional installed columns beyond parsed project definitions: {'overtime': ['OTEndTime', 'OTStartTime'], 'job_order_requests': ['RejectionReason'], 'applicants': ['Region'], 'clientcompany': ['PayrollType'], 'payroll_runs': ['SiteSupervisorID'], 'serviceagreement': ['ContractType']}.
The diagram represents the installed schema; these discrepancies are reported rather than applying migrations or depicting uninstalled legacy tables.

## Existing entities updated
- applicantdocuments: added DocumentationType; removed DocumentationType (Resume / Biodata); corrected names/key labels DocumentPath.
- attendance: added clockin_latitude, clockin_longitude, clockout_latitude, clockout_longitude, gps_accuracy, distance_from_site, clockin_photo, clockout_photo, browser_name, operating_system, device_type, user_agent, geofence_status, attendance_status, scheduled_time_in, scheduled_time_out, minutes_late, server_timestamp, EvidenceDeploymentID; removed none; corrected names/key labels none.
- bonus: added BonusType, ApprovedBy, CreatedBy; removed BonusType (13th Month / Holiday), ApprovedBy (Users), CreatedBy (Users); corrected names/key labels none.
- cashadvance: added none; removed none; corrected names/key labels none.
- cashadvancededuction: added none; removed none; corrected names/key labels none.
- clearance_items: added ItemName, IsCleared, ClearedDate, ClearedBy; removed ItemCategory (ID/Uniform), ItemDescription, IsReturned, ReturnedDate, Condition, ChargeAmount, CreatedDate, ReceivedBy; corrected names/key labels none.
- clientcompany: added registered_latitude, registered_longitude, geofence_radius, site_registered_at, registered_by_tl_id, latitude, longitude, PayrollType; removed none; corrected names/key labels CompanyTypeID.
- deductions: added none; removed none; corrected names/key labels none.
- department: added ManagerID; removed EmployeeID, ; corrected names/key labels none.
- deployment: added SiteSuperVisorID, TimeIn, TimeOut, BreakDuration, GracePeriod, WorkingDays, ShiftName, SiteID; removed SiteSuperVisorID (Users); corrected names/key labels none.
- dtr_submissions: added SubmittedBy, ReviewedBy, ApprovedBy, StatusID, SiteID; removed SubmittedBy (Users), ReviewedBy (Users), ApprovedBy (Users), StatudID; corrected names/key labels none.
- employee: added daily_rate, hourly_rate, allowance, monthly_salary_credit; removed none; corrected names/key labels BasicSalary.
- employmentcontract: added none; removed none; corrected names/key labels none.
- holidays: added none; removed none; corrected names/key labels none.
- joborder: added WorkSchedule, JobCategory, Status, category_id, TimeIn, TimeOut, BreakDuration, GracePeriod, WorkingDays, ShiftName, classification_id, sub_classification_id; removed none; corrected names/key labels ID.
- joborderpositions: added none; removed none; corrected names/key labels none.
- jobrequirements: added none; removed none; corrected names/key labels none.
- leaves: added none; removed none; corrected names/key labels none.
- notifications: added DTRReminderKey; removed none; corrected names/key labels none.
- overtime: added OTStartTime, OTEndTime; removed none; corrected names/key labels none.
- payroll: added ProcessedBy; removed ProcessedBy (Users); corrected names/key labels none.
- payroll_adjustments: added AddBackRequestID; removed none; corrected names/key labels none.
- payroll_audit_log: added none; removed none; corrected names/key labels none.
- payslip: added none; removed none; corrected names/key labels none.
- performance: added ReviewerID; removed ReviewerID (Users); corrected names/key labels none.
- resignationrequest: added none; removed none; corrected names/key labels ClearanceApprovedDate, QuitclaimSignedDate.
- roles: added none; removed none; corrected names/key labels none.
- salary_rates: added DailyRate, HourlyRate; removed none; corrected names/key labels none.
- serviceagreement: added Status, ContractType; removed none; corrected names/key labels none.
- status: added none; removed none; corrected names/key labels none.
- systemsettings: added none; removed none; corrected names/key labels none.
- users: added LocalPasswordConfigured, MessengerLastActivity; removed none; corrected names/key labels none.

## New entities added
- add_back_evidence: defined in sql\add_back_workflow.sql, live information_schema (2026-10-07); FK parents: add_back_requests.
- add_back_requests: defined in sql\add_back_workflow.sql, live information_schema (2026-10-07); FK parents: attendance, clientcompany, deployment, employee, payroll, status, users.
- address_barangay_lookup: defined in onazul_hris_db.sql, sql\psgc_address_hierarchy_migration.sql, live information_schema (2026-10-07); FK parents: address_city_lookup.
- address_city_lookup: defined in onazul_hris_db.sql, sql\psgc_address_hierarchy_migration.sql, live information_schema (2026-10-07); FK parents: address_province_lookup.
- address_country_lookup: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: none declared.
- address_province_lookup: defined in onazul_hris_db.sql, sql\psgc_address_hierarchy_migration.sql, live information_schema (2026-10-07); FK parents: address_country_lookup.
- address_region_lookup: defined in sql\psgc_address_hierarchy_migration.sql, live information_schema (2026-10-07); FK parents: none declared.
- applicant_profiles: defined in onazul_hris_db.sql, sql\applicant_job_board_filter.sql, live information_schema (2026-10-07); FK parents: none declared.
- applicant_uploaded_docs: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: applicants, job_applications, users.
- applicants: defined in onazul_hris_db.sql, sql\add_applicant_status_column.sql, sql\applicant_address_psgc.sql, sql\applicant_profile_status_otp.sql, sql\job_hierarchy_assignment_migration.sql, live information_schema (2026-10-07); FK parents: job_categories, job_classifications, job_sub_classifications, users.
- application_endorsement_attempts: defined in sql\application_endorsement_attempts.sql, live information_schema (2026-10-07); FK parents: clientcompany, job_applications, joborder, users.
- chat_messages: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: none declared.
- chat_thread_participants: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: none declared.
- chat_thread_reads: defined in onazul_hris_db.sql, sql\messenger_read_cursor.sql, live information_schema (2026-10-07); FK parents: none declared.
- chat_threads: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: none declared.
- daily_time_records: defined in onazul_hris_db.sql, sql\daily_time_records.sql, live information_schema (2026-10-07); FK parents: none declared.
- deployment_sites: defined in sql\deployment_sites_migration.sql, live information_schema (2026-10-07); FK parents: clientcompany, users.
- gov_percentage_rates: defined in onazul_hris_db.sql, sql\deduction_rates_config_migration.sql, live information_schema (2026-10-07); FK parents: none declared.
- gu_tags: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: attendance, deployment, employee, users.
- industry_lookup: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: none declared.
- job_applications: defined in onazul_hris_db.sql, sql\applications_target_detail_migration.sql, sql\multi_position_jor_migration.sql, live information_schema (2026-10-07); FK parents: applicants, job_order_details, joborder, status, users.
- job_categories: defined in onazul_hris_db.sql, sql\job_categories_migration.sql, sql\job_classification_hierarchy_migration.sql, live information_schema (2026-10-07); FK parents: none declared.
- job_classifications: defined in sql\job_classification_hierarchy_migration.sql, live information_schema (2026-10-07); FK parents: job_categories.
- job_order_details: defined in onazul_hris_db.sql, sql\multi_position_jor_migration.sql, sql\job_hierarchy_assignment_migration.sql, live information_schema (2026-10-07); FK parents: job_classifications, job_order_requests, job_sub_classifications.
- job_order_requests: defined in onazul_hris_db.sql, sql\job_categories_migration.sql, sql\multi_position_jor_migration.sql, live information_schema (2026-10-07); FK parents: clientcompany, users.
- job_sub_classifications: defined in sql\job_classification_hierarchy_migration.sql, live information_schema (2026-10-07); FK parents: job_classifications.
- payroll_group_status_history: defined in Api\Helpers\payroll_status_helper.php, live information_schema (2026-10-07); FK parents: none declared.
- payroll_run_status_history: defined in live information_schema (2026-10-07); FK parents: none declared.
- payroll_runs: defined in onazul_hris_db.sql, sql\payroll_runs_migration.sql, live information_schema (2026-10-07); FK parents: none declared.
- payroll_status_history: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: none declared.
- psgc_locations: defined in sql\psgc_official_locations.sql, live information_schema (2026-10-07); FK parents: none declared.
- sss_rates_config: defined in onazul_hris_db.sql, sql\deduction_rates_config_migration.sql, live information_schema (2026-10-07); FK parents: none declared.
- system_logs: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: none declared.
- team_schedule_entries: defined in sql\team_schedule_migration.sql, live information_schema (2026-10-07); FK parents: deployment, users.
- work_schedule_lookup: defined in onazul_hris_db.sql, live information_schema (2026-10-07); FK parents: none declared.

## Verified relationships (parent to child connectors)
- add_back_evidence.RequestID → add_back_requests.RequestID (added)
- add_back_requests.EmployeeID → employee.EmployeeID (added)
- add_back_requests.SubmittedBy → users.UsersID (added)
- add_back_requests.DeploymentID → deployment.DeploymentID (added)
- add_back_requests.CompanyID → clientcompany.CompanyID (added)
- add_back_requests.AttendanceID → attendance.AttendanceID (added)
- add_back_requests.StatusID → status.StatusID (added)
- add_back_requests.ReviewedBy → users.UsersID (added)
- add_back_requests.Payroll_ID → payroll.Payroll_ID (added)
- address_barangay_lookup.CityID → address_city_lookup.CityID (added)
- address_city_lookup.ProvinceID → address_province_lookup.ProvinceID (added)
- address_province_lookup.CountryID → address_country_lookup.CountryID (added)
- applicant_uploaded_docs.ApplicantID → applicants.ApplicantID (added)
- applicant_uploaded_docs.ApplicationID → job_applications.ApplicationID (added)
- applicant_uploaded_docs.VerifiedBy → users.UsersID (added)
- applicantdocuments.EmployeeID → employee.EmployeeID (retained/corrected)
- applicantdocuments.StatusID → status.StatusID (retained/corrected)
- applicantdocuments.VerifiedBy → users.UsersID (retained/corrected)
- applicants.category_id → job_categories.id (added)
- applicants.classification_id → job_classifications.id (added)
- applicants.sub_classification_id → job_sub_classifications.id (added)
- applicants.UserID → users.UsersID (added)
- application_endorsement_attempts.ApplicationID → job_applications.ApplicationID (added)
- application_endorsement_attempts.CompanyID → clientcompany.CompanyID (added)
- application_endorsement_attempts.JobOrderID → joborder.JobOrderID (added)
- application_endorsement_attempts.DecidedBy → users.UsersID (added)
- application_endorsement_attempts.EndorsedBy → users.UsersID (added)
- attendance.ApprovedBy → users.UsersID (retained/corrected)
- attendance.DTRSubmissionID → dtr_submissions.DTR_SubmissionID (retained/corrected)
- attendance.EmployeeID → employee.EmployeeID (retained/corrected)
- attendance.HolidayID → holidays.HolidayID (retained/corrected)
- attendance.StatusID → status.StatusID (retained/corrected)
- bonus.ApprovedBy → users.UsersID (added)
- bonus.CreatedBy → users.UsersID (added)
- bonus.EmployeeID → employee.EmployeeID (retained/corrected)
- bonus.Payroll_ID → payroll.Payroll_ID (retained/corrected)
- bonus.StatusID → status.StatusID (retained/corrected)
- cashadvance.ApprovedBy → users.UsersID (retained/corrected)
- cashadvance.EmployeeID → employee.EmployeeID (retained/corrected)
- cashadvance.Payroll_ID → payroll.Payroll_ID (retained/corrected)
- cashadvancededuction.CashAdvanceID → cashadvance.CashAdvanceID (retained/corrected)
- cashadvancededuction.Payroll_ID → payroll.Payroll_ID (retained/corrected)
- clearance_items.ClearedBy → users.UsersID (added)
- clearance_items.ResignationID → resignationrequest.ResignationID (retained/corrected)
- clientcompany.UserID → users.UsersID (retained/corrected)
- deductions.CreatedBy → users.UsersID (retained/corrected)
- deductions.EmployeeID → employee.EmployeeID (retained/corrected)
- deductions.Payroll_ID → payroll.Payroll_ID (retained/corrected)
- department.ManagerID → employee.EmployeeID (added)
- deployment.CompanyID → clientcompany.CompanyID (retained/corrected)
- deployment.EmployeeID → employee.EmployeeID (retained/corrected)
- deployment.ServiceAgreementID → serviceagreement.ServiceAgreementID (retained/corrected)
- deployment.StatusID → status.StatusID (retained/corrected)
- deployment.SiteSuperVisorID → users.UsersID (added)
- deployment_sites.CompanyID → clientcompany.CompanyID (added)
- deployment_sites.TeamLeadID → users.UsersID (added)
- dtr_submissions.ApprovedBy → users.UsersID (added)
- dtr_submissions.DeploymentID → deployment.DeploymentID (retained/corrected)
- dtr_submissions.EmployeeID → employee.EmployeeID (retained/corrected)
- dtr_submissions.ReviewedBy → users.UsersID (added)
- dtr_submissions.StatusID → status.StatusID (added)
- dtr_submissions.SubmittedBy → users.UsersID (added)
- dtr_submissions.SiteSupervisorID → users.UsersID (retained/corrected)
- employee.DepartmentID → department.DepartmentID (retained/corrected)
- employee.StatusID → status.StatusID (retained/corrected)
- employmentcontract.ApprovedBy → users.UsersID (retained/corrected)
- employmentcontract.CreatedBy → users.UsersID (retained/corrected)
- employmentcontract.EmployeeID → employee.EmployeeID (retained/corrected)
- employmentcontract.StatusID → status.StatusID (retained/corrected)
- gu_tags.AttendanceID → attendance.AttendanceID (added)
- gu_tags.DeploymentID → deployment.DeploymentID (added)
- gu_tags.EmployeeID → employee.EmployeeID (added)
- gu_tags.VerifiedBy → users.UsersID (added)
- job_applications.target_detail_id → job_order_details.detail_id (added)
- job_applications.ApplicantID → applicants.ApplicantID (added)
- job_applications.EndorsedBy → users.UsersID (added)
- job_applications.HiredBy → users.UsersID (added)
- job_applications.JobOrderID → joborder.JobOrderID (added)
- job_applications.StatusID → status.StatusID (added)
- job_classifications.category_id → job_categories.id (added)
- job_order_details.classification_id → job_classifications.id (added)
- job_order_details.RequestID → job_order_requests.RequestID (added)
- job_order_details.sub_classification_id → job_sub_classifications.id (added)
- job_order_requests.CompanyID → clientcompany.CompanyID (added)
- job_order_requests.ProcessedBy → users.UsersID (added)
- job_order_requests.RequestedBy → users.UsersID (added)
- job_sub_classifications.classification_id → job_classifications.id (added)
- joborder.ApprovedBy → users.UsersID (retained/corrected)
- joborder.classification_id → job_classifications.id (added)
- joborder.CompanyID → clientcompany.CompanyID (retained/corrected)
- joborder.CreatedBy → users.UsersID (retained/corrected)
- joborder.ServiceAgreementID → serviceagreement.ServiceAgreementID (retained/corrected)
- joborder.sub_classification_id → job_sub_classifications.id (added)
- joborderpositions.JobOrderID → joborder.JobOrderID (retained/corrected)
- jobrequirements.JobOrderID → joborder.JobOrderID (retained/corrected)
- leaves.ApprovedBy → users.UsersID (retained/corrected)
- leaves.EmployeeID → employee.EmployeeID (retained/corrected)
- leaves.StatusID → status.StatusID (retained/corrected)
- notifications.UserID → users.UsersID (retained/corrected)
- overtime.ApprovedBy → users.UsersID (retained/corrected)
- overtime.AttendanceID → attendance.AttendanceID (retained/corrected)
- overtime.DeploymentID → deployment.DeploymentID (retained/corrected)
- overtime.EmployeeID → employee.EmployeeID (retained/corrected)
- overtime.RequestedBy → users.UsersID (retained/corrected)
- overtime.StatusID → status.StatusID (retained/corrected)
- payroll.DTR_SubmissionID → dtr_submissions.DTR_SubmissionID (retained/corrected)
- payroll.EmployeeID → employee.EmployeeID (retained/corrected)
- payroll.HolidayID → holidays.HolidayID (retained/corrected)
- payroll.ProcessedBy → users.UsersID (added)
- payroll.SalaryRatesID → salary_rates.SalaryRatesID (retained/corrected)
- payroll.StatusID → status.StatusID (retained/corrected)
- payroll_adjustments.AddBackRequestID → add_back_requests.RequestID (added)
- payroll_adjustments.ApprovedBy → users.UsersID (retained/corrected)
- payroll_adjustments.CreatedBy → users.UsersID (retained/corrected)
- payroll_adjustments.EmployeeID → employee.EmployeeID (retained/corrected)
- payroll_adjustments.Payroll_ID → payroll.Payroll_ID (retained/corrected)
- payroll_audit_log.Payroll_ID → payroll.Payroll_ID (retained/corrected)
- payroll_audit_log.PerformedBy → users.UsersID (retained/corrected)
- payslip.EmployeeID → employee.EmployeeID (retained/corrected)
- payslip.Payroll_ID → payroll.Payroll_ID (retained/corrected)
- performance.EmployeeID → employee.EmployeeID (retained/corrected)
- performance.ReviewerID → users.UsersID (added)
- resignationrequest.ApprovedBy → users.UsersID (retained/corrected)
- resignationrequest.EmployeeID → employee.EmployeeID (retained/corrected)
- resignationrequest.ProcessedBy → users.UsersID (retained/corrected)
- resignationrequest.StatusID → status.StatusID (retained/corrected)
- salary_rates.CompanyID → clientcompany.CompanyID (retained/corrected)
- salary_rates.CreatedBy → users.UsersID (retained/corrected)
- salary_rates.DeploymentID → deployment.DeploymentID (retained/corrected)
- salary_rates.EmployeeID → employee.EmployeeID (retained/corrected)
- serviceagreement.ApprovedBy → users.UsersID (retained/corrected)
- serviceagreement.ClientCompanyID → clientcompany.CompanyID (retained/corrected)
- serviceagreement.CreatedBy → users.UsersID (retained/corrected)
- systemsettings.UpdatedBy → users.UsersID (retained/corrected)
- team_schedule_entries.DeploymentID → deployment.DeploymentID (added)
- team_schedule_entries.UpdatedBy → users.UsersID (added)
- users.RoleID → roles.RolesID (retained/corrected)

## ERD-only entities
None. ClearanceItem maps to clearance_items.

## Layout and support scope
Original headers, table widths, row styles, fonts, IDs and x coordinates retained. 14 existing entities moved vertically to resolve expanded-table overlap. New tables placed beside related entities using cloned original styles.
12 obsolete, unverified or duplicate original connectors removed. Cardinality uses nullable FK and single-column unique/PK constraints; parents may have zero children.
Support/lookup/log tables absent from the original are explicitly listed as new entities above; all project-defined base tables are included. No test-directory DDL, views or row data included.

## Validation
{
  "database_table_count": 67,
  "erd_table_count": 67,
  "matched_tables": 67,
  "missing_tables": [],
  "extra_tables": [],
  "missing_attributes": [],
  "pk_mismatches": [],
  "fk_mismatches": [],
  "missing_relationships": [],
  "duplicate_relationships": 0,
  "unresolved_schema_items": []
}

## Sources
- sql\add_applicant_status_column.sql
- sql\add_back_workflow.sql
- sql\applicant_address_psgc.sql
- sql\applicant_job_board_filter.sql
- sql\applicant_profile_status_otp.sql
- sql\application_endorsement_attempts.sql
- sql\application_workflow_status_migration.sql
- sql\applications_target_detail_migration.sql
- sql\client_partnership_migration.sql
- sql\daily_time_records.sql
- sql\deduction_rates_config_migration.sql
- sql\deployment_sites_migration.sql
- sql\employee_password_setup.sql
- sql\employee_salary_config_migration.sql
- sql\job_categories_migration.sql
- sql\job_classification_hierarchy_migration.sql
- sql\job_hierarchy_assignment_migration.sql
- sql\job_hierarchy_exact_backfill.sql
- sql\messenger_presence.sql
- sql\messenger_read_cursor.sql
- sql\multi_position_jor_migration.sql
- sql\onazul_messaging_and_jobs.sql
- sql\payroll_runs_migration.sql
- sql\phase5_payroll_migration.sql
- sql\phase6_resignation_migration.sql
- sql\psgc_address_hierarchy_migration.sql
- sql\psgc_official_locations.sql
- sql\team_schedule_migration.sql
- sql\tl_company_profile_schema.sql
- sql\tl_service_agreement_document.sql

## Files
- Onazul_ERD_UPDATED.drawio (editable XML)
- schema_audit.json (schema-only audit and exact change details)
- ERD_UPDATE_REPORT.md

XML parses and round-trip entity/column/key/edge/ID assertions pass. GUI opening and preview are verified separately if available.

## Final editor and preview verification
The final XML was successfully imported and rendered as editable entities and connectors in diagrams.net on 2026-10-07. The Open/Device chooser timed out and URL opening could not fetch localhost; Import/Device succeeded. PNG is a separate schema-only rendering using the same coordinates and routed lines, not an official diagrams.net export. Full preview visually inspected; table overlaps avoided.
Final post-routing validation: 67 tables, 137 unique declared FK relationships, no dangling endpoints, no duplicate IDs. Original 32 entities retained; 35 added; 14 existing entities shifted vertically only. No production files or database structure/data changed.
