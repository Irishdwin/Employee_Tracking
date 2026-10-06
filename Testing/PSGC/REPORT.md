> Superseded by [OFFICIAL_REPORT.md](OFFICIAL_REPORT.md), which uses the uploaded official workbook and database-backed lookups. This historical report describes the earlier mirror implementation.

# Applicant Philippine address implementation — 6 October 2026

## Previous implementation and source

The profile already used SearchableSelect controls and local address lookup tables. The database contained 17 regions, 82 provinces, 1,580 cities/municipalities and 2,645 barangays. Province was always required, saved addresses used names alone, and the save endpoint did not validate geographic parent relationships.

The replacement is a locally bundled PSA-derived PSGC snapshot as of 30 June 2026, published 13 July 2026: 18 regions, 82 provinces, 149 cities, 1,493 municipalities and 42,010 barangays. Official reference: https://psa.gov.ph/classification/psgc/summary . Acquisition source: https://github.com/bendlikeabamboo/barangay-data-repository/tree/main/2026-07-13 . Direct download of the official workbook returned HTTP 403; an exact comparison against that workbook is NOT VERIFIED. Metadata records provenance and the downloaded source hash. Runtime does not depend on either website.

## Behavior and persistence

Existing searchable controls are reused. Startup fetches regions only; province, city/municipality and barangay options are fetched on demand. Changing a parent clears every dependent selection and code. Requests have loading status, error text, Retry and protection against stale responses. Street address and four-digit ZIP remain manual inputs; no incomplete ZIP mapping is used.

Ordinary provinces contain their component cities and municipalities. Cities with no province in PSGC use a clearly labeled UI grouping with an empty saved province code. NCR automatically uses this path, with 16 cities and Pateros; no synthetic province is saved. Cagayan de Oro is available directly under Region X with all 80 barangays. Manila's 897 barangays are collected through its actual submunicipality parents. Isabela's intermediate geographic grouping is not offered as an extra city. Special Geographic Area municipalities remain reachable.

Ten-digit string PSGC identifiers are saved in applicants.AddressPSGC as JSON with region, province, city and barangay keys. Existing Region, StateProvince, City, Barangay, PostalCode, StreetAddress, Address and HomeLocation fields retain human-readable names. The server derives names from validated codes and checks parent relationships, street input and ZIP. Both code and text updates occur in the existing transaction. The additive migration sql/applicant_address_psgc.sql was applied to onazul_hris_db; no existing address records were rewritten.

GET restores the codes; the UI loads each parent before setting its child. Legacy names are resolved within their parent context only when unambiguous. Unresolved old text remains visible and requires selecting a valid PSGC address before saving. Old database lookup tables and unrelated profile sections are retained. Job matching still receives readable HomeLocation, not numeric PSGC codes. Actual Job Wall and HR browser regression checks remain unverified.

## Exact implementation files

Modified for this request:

- Html/Applicant/applicant_profile_master.html — geographic-path label.
- Js/Applicant/applicant_profile_cascades.js — local PSGC cascade, resets, restore and errors.
- Js/Applicant/applicant_profile_form.js — code payload and address validation.
- Api/Applicant/get_applicant_profile_setup.php — saved codes and legacy resolution.
- Api/Applicant/save_applicant_profile_setup.php — hierarchy validation and transactional persistence.

Added:

- Api/Applicant/get_psgc_locations.php — authenticated applicant lookup endpoint.
- Api/Helpers/psgc_address.php — lookup, validation and legacy resolution.
- Api/Data/psgc/metadata.json and 18 region-code JSON partitions.
- Api/Data/psgc/README.md — source/version and update guidance.
- sql/applicant_address_psgc.sql — additive AddressPSGC column.
- Testing/PSGC/check.php, cascade_check.js, api_check.py, .htaccess, flat.json and this report — verification and source snapshot. Test HTTP access is denied by .htaccess.

Other changes already in the working tree belong to previous requests. No attendance, payroll, DTR, Team Lead, Accounting, Railway, job classification or application behavior was changed for this request.

## Required test results

Tests use local data/helper assertions, a Node VM with mocked DOM and real dataset-backed responses, and HTTP requests against an isolated PHP/MariaDB fixture. They are not a claim of full interactive browser testing.

1. REGION — PASS: all 18 regions available.
2. ORDINARY PROVINCE — PASS: Region X returns its five provinces.
3. MUNICIPALITY — PASS: ordinary province returns municipality records.
4. COMPONENT CITY — NOT VERIFIED end to end: component cities are present, but a specific component-city selection and save was not exercised.
5. CAGAYAN DE ORO — PASS: correct direct-region path, all 80 barangays, save and restore.
6. NCR — PASS: 17 cities/municipalities, no fake province, Manila address saves and reloads.
7. BARANGAY — PASS: CDO and Manila counts, selected-city scoping, all 42,010 bundled records.
8. DUPLICATE BARANGAY NAME — PASS: identical names in different cities retain distinct validated codes.
9. CHANGE REGION — PASS: dependent values and codes reset in VM.
10. CHANGE PROVINCE — PASS: city and barangay reset in VM.
11. CHANGE CITY — PASS: barangay resets in VM.
12. SAVE — PASS: isolated HTTP POST and subsequent database-backed GET preserve the address.
13. PAGE REFRESH — NOT VERIFIED in a real browser: GET reload and sequential UI hydration passed separately.
14. LOGOUT / LOGIN — NOT VERIFIED through the login UI: a fresh authenticated session retrieves the saved address successfully.
15. EDIT ADDRESS — PASS: fixture address changes from CDO to NCR and reloads correctly.
16. INVALID MANIPULATED LOCATION — PASS: wrong region, province and barangay relationships rejected; invalid POST returns 400.
17. ADMIN/HR VIEW — NOT VERIFIED in a browser: human-readable storage retained, but HR view not opened.
18. JOB WALL REGRESSION — NOT VERIFIED end to end: readable HomeLocation retained and consumer inspected; matching UI not exercised.
19. PERFORMANCE — PASS for requested lazy-load behavior: initial VM request is regions only; no national barangay payload. Actual browser timing not measured.
20. ERROR HANDLING — PASS in simulation: failed lookup leaves child codes cleared and exposes error/Retry handling. Real browser visual layout not verified.

Additional PASS checks: PHP syntax, JavaScript syntax, legacy CDO names resolve without rewriting data, guest and non-applicant lookup requests receive 401, Isabela remains selectable without an artificial province.

## Limitations and deployment

Apply the migration before deploying the changed GET/save endpoints to any other database. The snapshot is versioned, not continuously updated. Before replacing it, verify a new complete publication and regenerate all partitions together. The official workbook's byte-level equivalence and interactive browser checks listed above remain outstanding. api_check.py is specific to the isolated local fixture and must not be pointed at the live application or reused without provisioning that fixture. No current applicant profile was changed by the save tests.

