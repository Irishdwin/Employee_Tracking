# Team Lead request classification workflow

Implemented in the connected project on 2026-10-05.

## Existing flow and schema

The Team Lead dashboard has an inline multi-position form in `Html/TeamLead/team_lead_dashboard.html`, posting JSON to `Api/TeamLead/submit_consolidated_jor.php`. A standalone single-position form in `Html/TeamLead/request_job_order.html` posts through `Api/TeamLead/request_job_order.php`, which routes to the consolidated transaction. Requests are stored in `job_order_requests`, with individual positions in `job_order_details`.

Admin and HR dashboards share `Js/HR/hr_dashboard.js` and `Api/HR/get_jor_details.php`. Their posting/rejection endpoint is `Api/HR/job_order_requests.php`, using `jorw_decide()` in `Api/Helpers/job_order_request_workflow.php`. The standalone HR Job Orders page has its own inline preview/post controller and was updated as well. Posting creates `joborder` and links each request position to it through `job_order_details.JobOrderID`.

The existing hierarchy is `job_categories.id` → `job_classifications.category_id`; `job_classifications.id` → `job_sub_classifications.classification_id`. Detail and job-order records already have `category_id`, `classification_id`, and `sub_classification_id`. Classification/sub-classification foreign keys are present; request/header and detail IDs are linked by the existing request foreign key. Parent request headers have category only: per-position details are the hierarchy source of truth. No schema change or duplicate category system was needed.

## Changes

- Both Team Lead forms use the existing `Api/Common/job_hierarchy.php` and `Js/Shared/job_hierarchy_selects.js` cascading selectors. All three fields are required. Child selectors start disabled; changing a category resets both children, and changing classification resets sub-classification. Only the matching active database children are offered.
- The shared selector supports the Team Lead dashboard's existing Tailwind classes; existing Bootstrap consumers retain their styling.
- Submission uses existing `jh_id()` and `jh_validate()` to enforce active IDs and both parent-child relationships server-side. It no longer guesses a new request's category from its position/name. IDs are persisted in every `job_order_details` row in the existing request transaction.
- The single-position endpoint forwards the classification IDs to that same transaction. Its GET now initializes the authenticated user ID correctly and returns saved hierarchy labels for the request preview.
- Team Lead list/detail reads include classification and sub-classification labels. The dashboard's position listing shows the submitted path; the standalone preview shows the saved path.
- HR/Admin dashboard preview displays saved category, classification and sub-classification as escaped read-only text. Complete requests have no classification dropdowns. The directly related modal wraps long values and uses a stacked layout on narrow screens.
- The standalone HR Job Orders preview follows the same rule, including loading/error text.
- Posting uses saved position IDs. `jorw_classification_choice()` rejects conflicting reviewer IDs rather than silently replacing submitted values. Existing worker counts, company, position, sites, schedules, service-agreement checks, statuses and notification/audit transactions remain in use.
- Only missing legacy fields may be completed by a reviewer. Already stored fields are locked and checked server-side. Invalid legacy combinations fail existing hierarchy validation. Header-only legacy requests get a detail row during posting so their completed hierarchy remains visible and associated with the request. No historical IDs were fabricated or live legacy records backfilled.

## Security, Applicant wall and counters

Authenticated active Team Lead checks remain at both submission endpoints. The company is checked against the authenticated user's active managed-company/deployment relationships using `jorw_validate_company()`, including existing GPS registration and signed active service-agreement requirements. Submitted Team Lead IDs are not used as requester identity. HR/Admin authentication and active-role checks remain at preview/decision APIs. Prepared statements and output escaping remain in use.

Applicant APIs already use `jh_filter()`, `jh_request_filter()`, `jh_profile_filter()` and placement tuple matching. Posted details retain the exact saved hierarchy and JobOrderID, so the existing tuple filters can find them. Applicant files were not changed.

Submission and decision still use the existing notifications table/helpers and audit logger. A temporary-table runtime test confirmed submission recipients, decision recipients/unread state, no order on rejection, and pending-count changes. Existing badge refresh frontend regression checks passed; authenticated browser badge rendering remains unverified.

## Exact modified files

Team Lead:

- `Html/TeamLead/team_lead_dashboard.html`
- `Html/TeamLead/request_job_order.html`
- `Api/TeamLead/submit_consolidated_jor.php`
- `Api/TeamLead/request_job_order.php`
- `Api/TeamLead/get_tl_job_requests.php`

Admin/HR:

- `Html/Admin/admin_dashboard.html`
- `Html/HR/hr_dashboard.html`
- `Html/HR/job_orders.html`
- `Js/HR/hr_dashboard.js`
- `Api/HR/get_jor_details.php`

Shared directly related helpers:

- `Api/Helpers/job_order_request_workflow.php`
- `Js/Shared/job_hierarchy_selects.js`

Created report: `Testing/JobOrderRequests/CLASSIFICATION_WORKFLOW_REPORT.md`. Controlled PHP verification scripts were executed against temporary shadow tables; the newly written test file was no longer present on subsequent inspection, so no persistent new PHP test artifact is claimed. Production edits and this report were separately checked. Existing dirty files outside this scope were preserved.

## Required test results

1. Team Lead form — **PASS static/controller checks; authenticated browser NOT VERIFIED.** Both entry points attach required three-field selectors. Browser was at an expired-session login screen.
2. Category cascade — **PASS runtime controller test.** Executed the actual shared JS controller with a controlled tree; only matching classifications appeared.
3. Sub-classification cascade — **PASS runtime controller test.** Only matching children appeared; parent changes cleared descendants and disabled them appropriately.
4. Required validation — **PASS server helper/controller checks; full HTTP form submission NOT VERIFIED.** Missing hierarchy rejected by production validator; frontend checks required IDs.
5. Server hierarchy validation — **PASS runtime.** Cross-category IDs rejected by actual production validator against real hierarchy data.
6. Persistence — **PASS temporary-table fixture/post checks; authenticated submission HTTP transaction NOT VERIFIED.** Posting reads and copies saved detail IDs. Submission bind/SQL paths inspected and syntax-checked.
7. HR review — **PASS static/controller checks; authenticated visual test NOT VERIFIED.** Complete saved hierarchy has read-only text; legacy-only selectors.
8. Admin review — **PASS static checks; authenticated visual test NOT VERIFIED.** Shares the exact HR controller/preview API.
9. Posting — **PASS runtime production helper.** No review IDs were sent; posted order copied exact saved IDs. Tampering rejected. Temporary shadow tables only.
10. Applicant Job Wall — **PASS runtime existing tuple filter SQL with posted placement; full Applicant browser/API display NOT VERIFIED.** Applicant files unchanged.
11. Rejection — **PASS runtime production helper.** No Job Order created; existing decision notification recorded in shadow notifications table.
12. Legacy request — **PASS runtime.** Missing detail hierarchy completed safely; header-only legacy request posted and persisted as a detail. Live pending legacy data was not changed.
13. Authorization — **PASS runtime company validation and static role/session checks; manipulated authenticated HTTP payload NOT VERIFIED.** Unauthorized company rejected by production helper.
14. Counters — **PASS runtime pending/unread changes and existing frontend refresh regression; full browser badges NOT VERIFIED.**

Additional results: existing `Testing/JobOrderRequests/frontend_test.js` **37 PASS**; four dashboard/form inline-script syntax checks **PASS**; changed PHP files **PASS** syntax checks; shared selector syntax **PASS**. The actual shared dropdown controller passed initial-disabled, category/sub-category filtering and reset tests. Temporary database tests passed 14 assertions without writing live business records. These tests do not substitute for authenticated end-to-end browser acceptance.

## Limitations

No live request was submitted, posted or rejected for testing. No users were signed in by bypassing authentication. Desktop/mobile visual acceptance and full role-to-role HTTP workflow await a normal signed-in session. Deactivated hierarchy values still fail the existing active-hierarchy validation at posting; reviewers cannot silently substitute a different saved classification. No new policy for editing already submitted classifications was introduced.
