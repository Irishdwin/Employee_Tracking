# Client Companies implementation report

1. **Files inspected:** `Html/Admin/admin_dashboard.html`, `Js/Admin/admin_dashboard.js`, `Js/Admin/deployment_sites.js`, `Api/Admin/client_companies.php`, `Api/Admin/industries.php`, `Html/Applicant/applicant_landing.html`, `Js/Applicant/applicant_auth_modal.js`, `Api/Applicant/register_applicant.php`, `Js/Shared/password_strength.js`, `Api/Helpers/password_policy.php`, session/database helpers, and payroll CSRF helpers. Actual industry, company, user, role, deployment-site and deployment schema/constraints were inspected.

2. **Files modified:** the three Admin frontend files above; both Admin APIs; Applicant landing HTML, auth-modal JS and registration API. Applicant changes only extract/reuse the existing mobile format and enforce it server-side.

3. **Files created:** `Js/Shared/ph_mobile.js`, `Api/Helpers/ph_mobile.php`, `Js/Admin/client_company_ui.js`, `Api/Helpers/client_admin_security.php`, `Css/client_company_controls.css`, this report, and `Testing/ClientCompanies/` API/browser tests, result JSON, screenshots and test-only cached copies of existing Bootstrap/SweetAlert libraries.

4. **Applicant contact validation reused:** the existing canonical `+639` followed by nine digits rule and Applicant input-formatting routine were extracted into `PhMobile`. Applicant retains its formatting routine. Admin validates the entered value immediately, preserving malformed input so it cannot silently become valid. Both registration APIs use the same server helper; whitespace around the submitted value is trimmed.

5. **Add Client account validation reused:** the existing Gmail-only rule, strong-password validator, password checklist updater, shared strength meter, confirmation validation and show/hide controls are reused. The site editor clones the existing account card, resets its state and binds the shared account controller. The existing PHP password policy remains authoritative and unchanged.

6. **Industry implementation inspected:** `industry_lookup` has `IndustryID`, unique `Name` (100 characters, case-insensitive collation) and `CreatedAt`. Companies currently store `clientcompany.Industry` as a name, not an industry foreign key. The existing industries API serves the dropdown and creation dialog.

7. **Industry editing:** pencil actions beside the Add/Edit Client industry dropdowns load the selected record into the existing small dialog. Updating preserves its ID, validates trimmed required names and length, rejects case-insensitive duplicates, and transactionally updates company text references matching its old name. A session-bound token ties edits to the selected ID and current name; tampered or stale selections are rejected.

8. **Company registration:** the initial site control and automatic initial site insertion were removed. Company registration, payroll type, service agreement and existing company account behavior remain. Deployment Sites still provides Add Site and Configure/Reassign after registration.

9. **Existing Team Lead confirmation:** Save Site validates locally, then displays the actual selected site and Team Lead in Confirm Changes. Cancel makes no save request and restores the draft. Confirm sends one request; success is shown only after a successful response. Failure displays a safe error and restores the editor.

10. **New Team Lead creation:** immediate required, Gmail, duplicate-email, password and confirmation feedback blocks invalid saves. Valid input uses the same confirmation/save flow. Account creation and site assignment share a transaction; assignment failure rolls back the new account. Existing-account mode does not create another account. One Team Lead can retain multiple sites.

11. **Cursor investigation:** no accidental editable Admin static content or application designMode was found. Static text had no explicit application cursor boundary, allowing normal browser text-hover behavior. The supplied still images cannot establish whether browser caret browsing was enabled on the user's computer; that browser setting is not claimed as diagnosed.

12. **Cursor fix:** scoped Admin/modal CSS supplies default cursors for static content and pointer cursors for links/buttons. Static labels/headings avoid accidental selection; useful table data remains copyable. Real inputs, password fields, textareas and editable content retain text cursor/caret behavior. No F7 interception or browser accessibility override was added.

13. **Database changes:** no production migration, new table, destructive rewrite or foreign-key change. Industry edits use the existing name references. Tests create and remove their own randomly named isolated schema.

14. **Security:** both Admin APIs resolve the authenticated session user against the database and require an active Admin role. Mutations validate the existing CSRF mechanism. Site/company ownership and active Team Lead role are checked server-side before account creation. Prepared statements, unique email constraints, existing password policy and password hashing are preserved. Failed transactions return safe messages; passwords are not logged.

15. **Tests performed:** actual HTTP API tests against an isolated copy of the real schema and APIs, including actual Team Lead login through the existing CAPTCHA/login endpoint. Browser tests use the real dashboard/components at desktop width 1535 and mobile width 390 with controlled API responses. Tests cover immediate contact feedback, industry editing, confirmation/cancellation, account validation, success/failure dialogs and computed cursor/caret styles. API checks cover permissions, CSRF, invalid/tampered requests, duplicate accounts, multi-site assignments, company View/Edit/status/payroll/service-agreement behavior, and deliberately induced transaction failures. PHP/JS syntax and diff whitespace checks were also performed. Desktop/mobile screenshots were visually inspected.

16. **Results:** 50 API checks and 38 browser checks passed. Syntax checks and `git diff --check` passed. Intentional browser failure tests returned 422 and correctly showed errors rather than success. Details are saved in `Testing/ClientCompanies/http_results.json` and `Testing/ClientCompanies/ui_results.json`.

17. **Limitations:** browser interaction tests used mocked responses; server persistence/authentication were independently tested through real isolated HTTP requests. No live production employee/company records were changed for testing. Browser-level caret browsing cannot be established from screenshots and remains under browser control. Existing production CDN dependencies were preserved; transient CDN failures were avoided in tests by caching the same libraries locally for the test harness only.
