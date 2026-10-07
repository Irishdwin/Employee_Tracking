# ERD connections update

67 tables; 137 unchanged MySQL-enforced FK connectors; 29 supplemental links.
All 67 tables have a visible connection and belong to one connected diagram component.

Solid lines represent installed FK constraints. Black dashed lines represent references verified in code. Gray dotted lines represent name lookups or calculation dependencies. Orange dashed line is an explicitly unverified reference candidate. Supplemental links have no FK label or cardinality claim.

The unverified payroll_run_status_history.PayrollRunID → payroll_runs.PayrollRunID connection is included to satisfy the requested complete visual connectivity, but it is NOT a verified relationship. No current application usage was found. It must not be treated as database enforcement.

Original entity styles, positions, dimensions, columns and key labels are unchanged from Onazul_ERD_UPDATED.drawio. Database and production code untouched. Original and FK-only diagram preserved.

## Supplementary links and evidence

- applicant_profiles.UserID → users.UsersID [application_reference]
  Profile upsert uses the authenticated session user ID.
  Evidence: Api/Applicant/save_applicant_profile_setup.php:331

- chat_threads.CreatedBy → users.UsersID [application_reference]
  Thread creator supplied from the authenticated messenger actor.
  Evidence: Api/Helpers/chat_helper.php:129; Api/Common/messenger_thread.php:5

- chat_messages.ThreadID → chat_threads.ThreadID [application_reference]
  Messages inserted and fetched for an existing thread.
  Evidence: Api/Common/messages_threads.php:36; Api/Helpers/chat_helper.php:194

- chat_messages.SenderUserID → users.UsersID [application_reference]
  Explicit INNER JOIN to users.UsersID.
  Evidence: Api/Common/messages_messages.php:51

- chat_thread_participants.ThreadID → chat_threads.ThreadID [application_reference]
  Explicit thread-to-participant join.
  Evidence: Api/Common/messages_threads.php:70

- chat_thread_participants.UserID → users.UsersID [application_reference]
  Participant IDs originate from authorized users.
  Evidence: Api/Helpers/chat_helper.php:165; Api/Helpers/messenger_rbac.php:50

- chat_thread_reads.ThreadID → chat_threads.ThreadID [application_reference]
  Read cursor maintained per thread.
  Evidence: Api/Helpers/chat_helper.php:174; Api/Common/messages_threads.php:63

- chat_thread_reads.UserID → users.UsersID [application_reference]
  Read cursor upsert uses viewer user ID.
  Evidence: Api/Helpers/chat_helper.php:208

- chat_thread_reads.LastReadMessageID → chat_messages.MessageID [application_reference]
  Read cursor uses maximum MessageID; zero is an initial sentinel, not an enforced reference.
  Evidence: Api/Helpers/chat_helper.php:207; Api/Helpers/messenger_unread.php:12

- daily_time_records.employee_id → employee.EmployeeID [application_reference]
  Explicit attendance-to-employee join.
  Evidence: Api/TeamLead/get_tl_attendance_roster.php:82

- payroll_runs.CompanyID → clientcompany.CompanyID [application_reference]
  Explicit payroll-run-to-client-company join.
  Evidence: Api/Helpers/client_billing_helper.php:464

- payroll_status_history.PayrollRunID → payroll_runs.PayrollRunID [application_reference]
  Status update and history insert share the same run ID.
  Evidence: Api/Helpers/client_billing_helper.php:335; Api/Helpers/client_billing_helper.php:340

- payroll_status_history.UpdatedBy → users.UsersID [application_reference]
  Explicit history-updater join to users.
  Evidence: Api/Helpers/client_billing_helper.php:354

- payroll_group_status_history.UpdatedBy → users.UsersID [application_reference]
  Explicit history-updater join to users.
  Evidence: Api/Helpers/payroll_status_helper.php:84

- payroll_group_status_history.CompanyID → clientcompany.CompanyID [application_reference]
  Company ID validated against submitted DTR/payroll group before insert.
  Evidence: Api/Accounting/update_payroll_group_status.php:51; Api/Helpers/payroll_status_helper.php:108

- payroll_group_status_history.SiteSupervisorID → users.UsersID [application_reference]
  Team Lead ID validated against submitted DTR group; DTR supervisor has an enforced users FK.
  Evidence: Api/Helpers/payroll_status_helper.php:108; Api/Accounting/update_payroll_group_status.php:160

- address_province_lookup.RegionID → address_region_lookup.RegionID [application_reference]
  Region-filtered province lookup; migration documents the hierarchy but does not declare a constraint.
  Evidence: Api/Applicant/get_provinces.php:12; sql/psgc_address_hierarchy_migration.sql:23

- psgc_locations.ParentPSGCCode → psgc_locations.PSGCCode [application_reference]
  Parent-child geographic traversal; no forced province for NCR/HUC cities.
  Evidence: Api/Helpers/psgc_address.php:38

- applicants.AddressPSGC → psgc_locations.PSGCCode [application_reference]
  JSON region/province/city/barangay codes validated against PSGC records. Multi-value application reference, not a scalar FK.
  Evidence: Api/Applicant/save_applicant_profile_setup.php:105,324; Api/Helpers/psgc_address.php:50

- system_logs.user_id → users.UsersID [application_reference]
  Audit actor is the session user ID. Logger also falls back to applicant_id; that fallback is polymorphic and cannot be an enforced users FK.
  Evidence: Api/Helpers/AuditLogger.php:14,27

- deployment.SiteID → deployment_sites.SiteID [application_reference]
  Explicit deployment-site join.
  Evidence: Api/TeamLead/get_tl_attendance_roster.php:78

- dtr_submissions.SiteID → deployment_sites.SiteID [application_reference]
  Authorized SiteID is validated and inserted into DTR submissions.
  Evidence: Api/TeamLead/submit_dtr.php:100,130

- attendance.EvidenceDeploymentID → deployment.DeploymentID [application_reference]
  Attendance evidence deployment ID compared against the actual deployment.
  Evidence: Api/Helpers/add_back.php:24

- clientcompany.Industry → industry_lookup.Name [dependency]
  Industry dropdown stores the selected name as text; not an IndustryID FK.
  Evidence: Js/Admin/admin_dashboard.js:559; Api/Admin/industries.php:61

- job_order_requests.WorkSchedule → work_schedule_lookup.Name [dependency]
  Work schedule dropdown stores selected name as text and permits custom values; no FK.
  Evidence: Html/TeamLead/request_job_order.html:408; Api/Common/work_schedules.php:62

- applicants.Region → address_region_lookup.Name [dependency]
  Legacy applicant region options API is retained. Current selector uses psgc_locations instead; this is a legacy lookup association, not a current enforced or scalar-ID reference.
  Evidence: Api/Applicant/get_regions.php:7; Js/Applicant/applicant_profile_cascades.js:10,61

- payroll.SSSDeduction → sss_rates_config.monthly_salary_credit [dependency]
  Payroll calculation selects an active SSS bracket by salary/MSC; computation dependency, not a stored key.
  Evidence: Api/Helpers/employee_payroll_helper.php:366,371

- payroll.PhilHealthDeduction → gov_percentage_rates.philhealth_ee_pct [dependency]
  Payroll calculation reads the government rate configuration; computation dependency, not a stored key.
  Evidence: Api/Helpers/employee_payroll_helper.php:400,403

- payroll_run_status_history.PayrollRunID → payroll_runs.PayrollRunID [unverified_reference]
  Candidate reference suggested by installed schema only. No FK constraint and no application usage found; explicitly unverified.
  Evidence: Installed information_schema metadata; no current PHP/SQL implementation found

## Validation
Counts: {'application_reference': 23, 'dependency': 5, 'unverified_reference': 1}.
Missing declared FK connectors: 0. Duplicate endpoint pairs: 0. Isolated tables: 0. Connected component: 67 tables.
Editable XML parse: passed. Column and FK flags unchanged.
All FK-only validation claims remain limited to installed constraints. Supplementary links are separately classified.
Final checks: connected XML imported successfully into a blank diagrams.net document. Every cell from the previous FK-only ERD is unchanged at the XML-element level. 166 unique connectors have valid endpoints; all 67 tables are connected. PNG preview visually inspected.
