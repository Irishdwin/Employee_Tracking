# Resignations, Clearance & AWOL — dashboard integration

Implemented in the connected project on 2026-10-09. Existing resignation APIs, database fields, role/record permissions and workflow rules remain the same.

## 1. Root cause

The HR sidebar linked directly to `resignations.html`. That document created its own standalone page instead of using the dashboard's `ProfileViewSwitch.initBootstrapSpa()` content-section navigation. The dashboard therefore disappeared on navigation.

## 2. Files modified

- `C:/xampp/htdocs/Employee_Tracking/Html/HR/hr_dashboard.html`
- `C:/xampp/htdocs/Employee_Tracking/Html/HR/resignations.html`
- `C:/xampp/htdocs/Employee_Tracking/Js/HR/hr_dashboard.js`
- `C:/xampp/htdocs/Employee_Tracking/Js/Shared/employee_exit.js`
- `C:/xampp/htdocs/Employee_Tracking/Css/employee_exit.css`
- `C:/xampp/htdocs/Employee_Tracking/Js/Shared/ow_notifications.js`
- `C:/xampp/htdocs/Employee_Tracking/Html/Admin/admin_dashboard.html`
- `C:/xampp/htdocs/Employee_Tracking/Js/Admin/admin_dashboard.js`

Admin changes preserve its existing access to the former shared HR/Admin page; they add the same feature within its own dashboard rather than routing an Admin into an HR-only dashboard.

Tests under `C:/xampp/htdocs/Employee_Tracking/Testing/EmployeeExit/`: `dashboard_ui_check.cjs`, `dashboard_compatibility_check.cjs`, their JSON results and dashboard screenshots. Existing `ui_check.cjs` now covers the three other roles; HR coverage moved to the integrated-dashboard suite.

## 3. Integration

A `section-resignations` content section hosts the existing `employeeExit` component and existing HR API. It uses the dashboard heading/subtitle styles and existing workflow cards, with a dashboard-only employee/site search and workflow-stage filter. Actual existing fields supply employee number, company/site, letter date, clearance/final-pay stage and actions. The empty state reads “No resignation requests found.” and gives a practical next step.

## 4. Sidebar navigation

The sidebar now targets `#section-resignations` using the existing `data-target` navigation. The normal section switch highlights that link and hides the previous section. Direct hash links work. Existing hash/back/forward handling and navigation history conventions are preserved. The compatibility page checks the existing session and uses `location.replace()` to open the HR or Admin dashboard section, avoiding a second interface and redirect loops.

## 5. Navbar preservation

No sidebar, navbar or profile-menu shell was duplicated. The existing header, sidebar toggle, notification bell, profile dropdown and floating messenger widget remain part of the same document. Mobile retains the existing sidebar drawer behavior.

## 6. Messaging and notifications

The existing messenger opens and closes from the integrated workspace. The notification bell opens the existing notification section and navigation back restores the resignation section. Employee-exit notifications now link directly into the appropriate HR/Admin dashboard section, using the existing section function where available. No messaging or notification API was changed.

## 7. Existing workflow

The shared component still uses the same HR resignation endpoint, CSRF token and authorized action forms for physical letter registration, HR review, physical clearance issuance/return/acceptance, Team Lead clearance, Accounting final-pay review/release, quitclaim tracking and AWOL review. Search/filtering does not change records or workflow permissions. Initialization is guarded; concurrent load calls are coalesced and background polling only runs while the component is visible. Returning to the section does not register duplicate listeners.

## 8. Responsive layout

Verified 1366px desktop, 1024px laptop, 768px tablet and 390px mobile. The existing cards/actions wrap; search/filter controls and metadata become one column on small screens. No document-level horizontal overflow was observed. Existing Team Lead, Accounting and Employee exit pages were separately checked at desktop/mobile widths.

## 9. Actual tests

- Integrated HR browser suite: all four viewport runs passed. Synthetic HR sessions and mocked APIs exercised direct hash navigation, sidebar highlighting, single-request Refresh, physical-letter/AWOL dialogs, HR approval confirmation and success, clearance dialog, search/status filters, empty state, messenger, notifications, profile, switching away/back, browser hash history and legacy redirect. No page errors were recorded.
- Legacy compatibility browser checks cover Admin's own dashboard redirect and non-HR/Admin rejection.
- Existing isolated backend/HTTP suite: 88 checks passed, including resignation/clearance/final-pay/quitclaim/AWOL transitions, role/scope/CSRF protection, document access and preserved production business-record checksums.
- Six existing browser runs passed for Team Lead, Accounting and Employee workflows at 1366px/390px.
- JavaScript syntax and whitespace checks passed.

Evidence: `C:/xampp/htdocs/Employee_Tracking/Testing/EmployeeExit/dashboard_ui_results.json`, `dashboard_compatibility_results.json`, `results.json` and `ui_results.json`. Screenshots are `dashboard-hr-1366.png`, `dashboard-hr-1024.png`, `dashboard-hr-768.png` and `dashboard-hr-390.png` in that directory.

## 10. Remaining limits

Browser interaction tests use synthetic session/API fixtures, so no real resignation, clearance, final pay or employee account was changed from the browser. Real backend transitions and permission checks were exercised only inside disposable databases. Search/status filtering operates on the records already returned by the existing API. Existing dashboard history behavior is preserved rather than changed for unrelated modules.
