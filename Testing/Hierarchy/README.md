# ONAZUL HRIS: overflow and job hierarchy implementation report
Date: 5 October 2026. Source of truth: C:\xampp\htdocs\Employee_Tracking.
Scope: the two fixes in the supplied request. Existing unrelated workspace changes were preserved.

## 1. Horizontal overflow root causes
The landing hero's decorative circles extend beyond the viewport (including a 320px circle positioned right:-140px). The hero itself must remain overflow-visible for search suggestions.
The separate Applicant Job Board kept its fixed desktop sidebar and 240px content offset on phones. Its layout-wrapper also continued arranging the full-width sidebar and content horizontally.

## 2. Exact overflow fixes
Only the decorative landing shapes are wrapped in an aria-hidden, pointer-events:none, overflow:clip layer. Search controls remain outside that layer.
Local Job Board mobile CSS changes the layout-wrapper to block flow, the sidebar to relative/full width, its footer to normal flow, and the main content to width:100% with no desktop margin. Search children and filter selects can shrink within their containers.
No global body overflow hiding was introduced. No unrelated dashboard design was changed.

## 3. Existing Admin hierarchy
Reused job_categories → job_classifications → job_sub_classifications, managed by the existing Admin Job Categories section and manage_* APIs. Active dropdown data comes from these tables, not a second catalog or hardcoded list.
For example, existing IDs 4 → 5 → 22 represent Security & Protective Services → Security & Safety → Security Guard.

## 4. Database tables and relationships
Hierarchy: job_categories.id; job_classifications.category_id; job_sub_classifications.classification_id.
Assignments: joborder; job_order_details; applicants.
Existing workflows also use job_order_requests, job_applications, applicant_profiles, clientcompany, serviceagreement, users/roles, and existing notifications/audit helpers. Their unrelated business rules were preserved.

## 5. Schema before and after
joborder and job_order_details already had category_id, but no classification_id or sub_classification_id.
Both now have nullable unsigned classification_id and sub_classification_id foreign keys to the existing hierarchy.
applicants previously stored preference names. It now also has nullable category_id, classification_id and sub_classification_id foreign keys. Existing name fields/cache remain compatible.
Nullable fields preserve unmapped legacy records; new submissions require a valid full hierarchy.

## 6. Job Order integration
Existing create/edit forms receive three dependent required selects. Changing a parent clears downstream selections. Edit loads saved IDs.
Create/update validates all three active IDs server-side and stores canonical category names alongside IDs.
The missing HR edit modal was added using the existing Admin modal, because the HR edit action already depended on that form.
A single linked placement follows a header edit. Consolidated orders preserve individual placement classifications; a header cannot silently relabel all placements.

## 7. Applicant Profile integration
Existing cascade dropdowns are reused; selected option IDs are submitted with names.
Save validates the active parent-child tuple, resolves canonical labels, and persists IDs within the existing transaction.
Get returns IDs and resolves current labels from them, allowing Admin renames to be reflected rather than relying on stale cached strings. Existing hydration restores the dependent selects in order.
Exact legacy name triples can resolve to IDs; arbitrary free text cannot establish a new hierarchy.

## 8. Job Wall filtering
Both landing Job Wall and separate Job Board expose dependent category/classification/sub-classification filters.
All three existing data APIs use validated IDs: job_board.php, get_jobs.php and get_latest_jobs.php.
Filters match a single available placement, with a header fallback only for orders without placement details. Returned deployment slots are filtered consistently.
Only Open orders with available capacity qualify. Unmapped existing open jobs remain visible when hierarchy filters are cleared.

## 9. Server-side relationship validation
The new shared helper validates positive integer IDs, active records and actual parents. Missing required levels, child-without-parent, unrelated IDs, arrays and invalid IDs are rejected.
SQL values use prepared statements. Read-only public lookup returns only active category labels/IDs and their children.
Existing Admin/HR/Applicant authorization remains in place. Taxonomy writes retain existing permissions: classification/sub-classification writes are Admin-only; category add retains its existing Admin/HR/Team Lead permission. Authentication/session behavior was not modified.

## 10. Existing search/filter compatibility
Keyword, location, work type and listing-date predicates remain composed with the hierarchy predicates. Existing endpoint-specific keyword/location behavior was preserved.
Parent changes clear child filters; choosing All categories clears the tuple.
Combined keyword/location/work-type/date and hierarchy behavior was exercised through real APIs.

## 11. Match My Profile
Uses the Applicant's saved full hierarchy rather than LIKE matching a preferred label against job titles.
Explicit filters must agree with the profile tuple, preventing different placements in one consolidated order from separately satisfying conflicting filters.
Incomplete or inactive profile hierarchies safely produce no matches.

## 12. Team Lead Job Request compatibility
Team Lead submission remains the existing workflow. It cannot create an unclassified posted order through HR approval.
Both HR review surfaces request classifications for each placement, retaining its original category. Approval validates all placement tuples under the existing request lock/transaction and persists them on the resulting order/details.
Missing hierarchy or a detail from another request is rejected. Duplicate approval remains a conflict; existing reject, notifications, audit and permissions remain unchanged.
Categories with no Admin-created children require Admin hierarchy setup before a new order can be posted.

## 13. Manual mapping required
MANUAL CLASSIFICATION REQUIRED:
- JobOrderID 1: Promo Diser, category 1, Closed. No exact existing sub-classification title match.
- JobOrderID 2: sweeper, category 7, Open. No exact existing sub-classification title match.
Unmapped detail titles include Promo Diser, sweeper and Diser. They were not guessed.
Order 3 Security Guard maps to 4/5/22; order 4 promodiser maps to 1/1/11. Existing unposted Cashier detail has its exact hierarchy but its request state was not changed.
All four original orders, six requests, six details and five applicants remain.

## 14. Migration performed
Applied sql/job_hierarchy_assignment_migration.sql to local XAMPP MariaDB, then sql/job_hierarchy_exact_backfill.sql.
Backfill requires a unique exact normalized sub-classification name within the existing category, or an exact full Applicant preference triple. No aliases/fuzzy mapping and no destructive changes.
Verified all seven new foreign keys in information_schema.
The assignment migration is a one-time migration: columns tolerate existence, but named foreign keys must not be blindly reapplied. An initial unsupported conditional constraint syntax was corrected before successful application; final schema was verified.

## 15. Files changed in this request
Modified:
- Api/HR/job_orders.php
- Api/HR/job_order_requests.php
- Api/HR/get_jor_details.php
- Api/Helpers/job_categories_lookup.php
- Api/Helpers/job_wall_filters.php
- Api/Helpers/jor_multi_position.php
- Api/Helpers/job_order_request_workflow.php (already present in the working implementation)
- Api/Applicant/save_applicant_profile_setup.php
- Api/Applicant/get_applicant_profile_setup.php
- Api/Applicant/job_board.php
- Api/Applicant/get_jobs.php
- Api/Applicant/get_latest_jobs.php
- Js/Applicant/applicant_profile_cascades.js
- Js/Applicant/applicant_profile_form.js
- Js/Applicant/applicant_job_filter.js
- Js/Shared/ow_job_categories.js
- Js/HR/hr_dashboard.js
- Html/HR/hr_dashboard.html
- Html/HR/job_orders.html
- Html/Admin/admin_dashboard.html (required shared form script only)
- Html/Applicant/applicant_landing.html
- Html/Applicant/job_board.html

Created:
- Api/Helpers/job_hierarchy.php
- Api/Common/job_hierarchy.php
- Js/Shared/job_hierarchy_selects.js
- sql/job_hierarchy_assignment_migration.sql
- sql/job_hierarchy_exact_backfill.sql
- Testing/Hierarchy/admin_runtime_test.ps1
- Testing/Hierarchy/real_data_test.ps1
- Testing/Hierarchy/job_board_verified.png
- Testing/Hierarchy/README.md

Temporary static layout previews and responsive harness were removed after verification.
An initial comprehensive PHP test runner completed, but the runner later disappeared during an extended attempt; the retained PowerShell runners avoid depending on that missing file. Unrelated dirty files are not part of this request's modifications.

## 16–17. Tests and results
Local XAMPP Apache and MariaDB were accessible. Protected HTTP tests used isolated temporary PHP sessions on a temporary local PHP server; no real passwords were changed.

Required tests:
1. Admin-created category/classification/sub-classification propagation — PASS: real API CRUD and public active tree.
2. Job Order creation with valid hierarchy — PASS: initial HTTP fixture suite verified persisted IDs; incomplete/invalid combinations rejected.
3. Job Order edit/save hierarchy — PASS at API; PASS saved-ID restore in isolated browser form. Full authenticated page submit click — NOT VERIFIED.
4. Parent change clears child selections — PASS in live Job Board browser and shared form component.
5. Applicant profile preference save — PASS through authenticated fixture API, with actual database persistence.
6. Applicant saved preference restore — PASS API IDs/canonical labels; existing hydration traced. Full authenticated save/reload browser flow — NOT VERIFIED.
7. Job Wall hierarchy filtering — PASS through all three real APIs and live browser.
8. Keyword/location/work-type/date compatibility — PASS initial API fixture suite.
9. Invalid parent-child combinations — PASS: wrong parents, missing parents, invalid string/array IDs and inactive tuples denied.
10. Match My Profile — PASS: fixture API checks across three wall APIs plus read-only checks of the real Security profile. Conflicting explicit filters return no match.
11. Existing Job Orders preserved — PASS: original IDs 1–4 remain; no fixture users/companies/categories remain.
12. Responsiveness — PASS: live public pages and isolated exact source profile/form markup at 1440, 1280, 768 and 375px. For every tested document, scrollWidth equals clientWidth.

Executed suites:
- Initial comprehensive HTTP/database fixture suite: 36 PASS including cleanup. Covered HR/Admin create/edit, Applicant authorization, profile save/get, wall filtering, Match My Profile, TL approval classification and duplicate approval.
- Retained admin_runtime_test.ps1: 24 PASS. Admin create/rename/deactivate/reactivate, active lookup, invalid query parameters, anonymous writes/access denial.
- Retained real_data_test.ps1: 7 PASS. Real profile match, combined-filter consistency, cleared filters, Closed exclusion, record preservation, fixture cleanup.
- Existing HR integration suite: 82 PASS, including role authorization, real counters and existing HR read APIs.
- Existing HR frontend suite: 42 PASS.
- Existing Job Order Request frontend suite: 37 PASS, rerun after the final related JS adjustment.
- Existing session timeout frontend regression: 61 PASS.
- PHP syntax: 14 affected PHP files PASS.
- JavaScript syntax: 6 affected JS files PASS; existing HR/TL inline script parsing PASS via frontend suite.
- Live Job Board browser error log: no errors observed.
- Browser form restore: 6 hierarchy controls and edit values 4/5/22 at all four exact frame widths.
- Clear filters restores the two real open jobs; selecting 4/5/22 shows only Security Guard.

Testing limitations/failures:
The extended version of the initial PHP runner did not complete after the runner disappeared; its additional assertions are NOT VERIFIED from that attempt. Test fixtures left by that interruption were explicitly cleaned, then Admin lifecycle assertions were rerun successfully with the retained runner.
A draft PowerShell test invocation failed due to PHP CLI session-path quoting; corrected to session_save_path() before the 24-PASS run.
Temporary form previews initially included the real auth scripts and generated harness-only errors; removed those scripts from the isolated preview before layout/control verification. This preview does not establish authenticated browser behavior.

To rerun retained Admin HTTP tests: start a temporary PHP server on 127.0.0.1:8894 rooted at this project with session.save_path pointing to TEMP/onazul-hierarchy-tests, then run Testing/Hierarchy/admin_runtime_test.ps1 from the project root. The runner uses an existing active local Admin only to synthesize an isolated test session and cleans its own temporary taxonomy rows/session.
real_data_test.ps1 is read-only and checks the inspected local records; it intentionally expects this database's original four orders.
The temporary server was stopped at completion.

## 18. Remaining limitations
- Two legacy orders require explicit manual mapping; no assumptions were substituted.
- Empty Admin category branches need classifications/sub-classifications before mandatory full assignment.
- An inactive saved preference is retained by ID/name but unavailable for a new selection until reactivated or changed.
- Full signed-in browser create/edit/profile-save and HR approval click flows, other browsers and physical devices are NOT VERIFIED.
- Multi-placement filtering/validation is implemented; a complete multi-placement authenticated browser approval walkthrough is NOT VERIFIED.
- Existing authentication semantics in older endpoints were preserved; no unrelated authentication hardening was attempted.
- No unrelated payroll, attendance/DTR, holiday, Team Schedule, notification or dashboard design changes were made.

