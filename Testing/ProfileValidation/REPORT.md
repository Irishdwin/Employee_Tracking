# Applicant Profile field validation — 6 October 2026

Previous behavior: Save returned at the first generic name, address or experience error. The applicant could not tell which address field was missing. The form already had novalidate, required attributes and an existing top error banner; address fields use custom searchable controls.

New behavior: Save checks every required field, applies a subtle red border plus specific nearby text, sets aria-invalid and associates errors through aria-describedby. All errors are marked before scrolling/focusing the first field in DOM/form order. If a dependent control is disabled, its surrounding field container receives focus without enabling it. Reduced-motion preference is respected. Values are never cleared by validation. The banner summarizes singular/plural errors. A corrected field loses its border/message/accessibility error immediately; unresolved errors stay. Initial load has no invalid states. Backend errors retain their field association until the corresponding values change.

Required fields identified: First Name, Last Name, Date of Birth (existing minimum age 18), Region, City/Municipality, Barangay, Postal/ZIP, Street Address, Job Category, Classification and Sub-classification. Province/path is required only when needed to reach the selected location; independent cities/NCR need no artificial province. With experience selected, Job Title, Company Name, start month/year are required; end month/year are required unless Still in role is checked. Middle Name and suffix remain optional. Optional Middle Name is checked for invalid characters only when supplied. Documents, consent and separate profile workflows were not changed.

Messages include “Please select a Region.”, “Please select a Province / geographic path.”, “Please select a City / Municipality.”, “Please select a Barangay.”, “Postal / ZIP Code is required.” and “Street Address is required.” Invalid ZIP is distinguished from missing ZIP and uses the existing four-digit rule. Name, DOB, preferences and each required experience field have their own messages.

The save endpoint now returns `errors` keyed by field for missing/invalid input. The frontend maps these to the same controls. The backend validates geographic codes/relationships and retains the existing authoritative psgc_validate_address and job hierarchy checks before writes. No PSGC master data, schema or unrelated module was changed. Official CDO and Barangay 10 workbook names contain trailing whitespace; matching selectable names now trims both sides, preserving the official codes/data and avoiding false incomplete selections.

## Files

Modified:

- Html/Applicant/applicant_profile_master.html — field-error styling and validation script load.
- Js/Applicant/applicant_profile_form.js — shared payload construction, all-field validation, backend response mapping; DOB errors now use the shared field feedback instead of disabling Save before other errors can be shown.
- Js/Applicant/applicant_profile_cascades.js — trimmed name matching, validation change notifications and province-required context.
- Api/Applicant/save_applicant_profile_setup.php — structured validation errors before persistence.
- Testing/PSGC/cascade_check.js — mock events and actual searchable-select trimming for regression checks.

Added:

- Js/Applicant/applicant_profile_validation.js — field validation and accessible error rendering.
- Api/Helpers/applicant_profile_validation.php — structured authoritative field checks.
- Testing/ProfileValidation/test.js — validation/rendering/focus/correction tests.
- Testing/ProfileValidation/backend_check.php — read-only server/helper checks with official PSGC data.
- Testing/ProfileValidation/api_check.py — isolated PHP/MariaDB HTTP tests; uses the explicitly configured existing non-production fixture, not the live database.
- Testing/ProfileValidation/.htaccess — denies public access to tests.
- Testing/ProfileValidation/postal_error_verified.png — isolated browser preview evidence.
- Testing/ProfileValidation/REPORT.md — this report.

No database migration required. No existing production applicant profile was saved or changed by testing.

## Tests 1–10

1. Only Postal empty — **PASS**. CDO/Misamis Oriental, Barangay 10, street phase 1: exactly Postal is invalid. Browser preview displays the specific message and focuses Postal. Official backend helper and HTTP POST return only postal_code.
2. Only Street empty — **PASS**, VM marks/focuses Street only.
3. Only Barangay empty — **PASS**, VM marks/focuses Barangay only.
4. Region empty — **PASS**, VM marks Region and focuses it; dependent address requirements are checked individually.
5. Multiple empty fields — **PASS**, VM marks Barangay, ZIP and Street together, focuses Barangay first; actual HTTP POST returns all three field keys.
6. Fix one of multiple errors — **PASS**, VM clears ZIP only and leaves the other two. Browser preview confirms entering 9000 removes the ZIP message/invalid state.
7. All required fields complete — **PASS**, VM permits submit; isolated API saves successfully and returns saved address on GET. Conditional required name/preferences/experience fields also tested.
8. Refresh after save — **NOT VERIFIED** end to end in the authenticated production profile. Isolated save/GET/fresh session preserve address; frontend hydration regression passes; fresh browser preview has no initial invalid state. The available production browser was signed out.
9. NCR — **PASS**, client/server checks and isolated saved Manila address require no fake province.
10. Backend invalid PSGC — **PASS**, actual HTTP 400 has field-specific city error; frontend test maps the backend error to City and retains it on unrelated input changes. Existing hierarchy rejection remains intact.

Additional checks: four-digit ZIP invalid-format message, preserved input, middle/suffix optional behavior, fresh form with no errors, structured error accessibility attributes, backend City mismatch, server/frontend syntax, CDO/Iligan selector restoration, and cascade resets. Tests use VM DOM assertions, actual database/API checks and a browser preview built from the current form markup/styles. The preview intentionally does not save or authenticate an applicant. Full authenticated production-page visual/save/refresh regression remains a manual check; no claim is made that the preview covers the complete production page.
