# Official PSA PSGC address implementation — 6 October 2026

This report supersedes REPORT.md and its mirror-based verification. The connected project files were inspected and reused; no project ZIP was required. Implementation followed the ONAZUL HRIS Development Skill.

Subsequent user-requested selector changes: Cagayan de Oro is also listed under Misamis Oriental and Iligan under Lanao del Norte through explicit geographic presentation mappings. Official database parents/province codes remain unchanged. GET returns the selector province so saved city selections restore under those requested groups; POST still saves validated official codes. The synthetic “No province” dropdown option was removed. Region-only cities load directly when the region is selected; NCR's province field is disabled and displays “Not applicable.” Database and VM checks pass for both requested groupings, restored codes, absent synthetic option, and NCR's 17 cities/municipalities.

## Workbook inspection

Source: user-provided Downloads/PSGC-2Q-2026-Publication-Datafile.xlsx. Metadata publication date: **30 June 2026**. SHA-256: `31892bc2bdde3ea0682562d9412b5bab4d45a0be5e5a5b4f6c9d7714b94bca5d`.

Sheets: Metadata, National Summary, Prov Sum, PSGC, Notes, Coding Structure. The main geographic sheet is **PSGC**, header row **1**. Columns: A “10-digit PSGC”, B “Name”, C “Correspondence Code”, D “Geographic Level”, E “Old names”, F “City Class”, G income classification, H urban/rural, I 2024 population, J unlabeled notes, K “Status”. Correspondence codes are retained separately from current ten-digit identifiers.

Verified source and database counts are identical for each level:

- Reg: 18 source / 18 imported.
- Prov: 82 / 82.
- City: 149 / 149 (111 CC, 33 HUC, 5 ICC).
- Mun: 1,493 / 1,493.
- Bgy: 42,010 / 42,010.
- SubMun: 14 / 14.
- Two unclassified grouping rows: Isabela and Special Geographic Area, retained as Group records.
- Total: 43,766 classified geographic records plus 2 groups = 43,768 database rows.

The “Prov Sum” auxiliary sheet retains an old 2020 label/counts. It is not used for import. The current PSGC records reconcile with the 2026 National Summary. Coding Structure contains no cell-based content usable as a parent table. The importer maps current code structure and city classifications, checks every parent exists, checks for cycles, and requires every barangay to resolve to a city/municipality.

## Storage, hierarchy and UI

Existing `address_city_lookup` has a mandatory ProvinceID foreign key and cannot express these geographic exceptions. It and all existing lookup/address records remain intact. An additive **psgc_locations** table holds the official code as an ASCII CHAR(10) primary key, name, level, immediate parent, region, applicable administrative province, ancestor city/municipality, correspondence code, city class, old names, status, source hash and version. Parent, region, province and city lookups are indexed.

Ordinary municipalities and component cities use their official province. HUC/ICC classifications use the independent administrative path with no province, even where their geographic code carries a provincial association. Region-code city retrieval includes those cities. NCR uses a disabled/non-applicable province selector; its 16 cities and Pateros are available directly. No artificial province value or code is stored. Cagayan de Oro uses Region X, blank province and code **1030500000**, with all **80** barangays.

Manila's **14 SubMun districts** remain actual intermediate parents. They are not counted or displayed as ordinary municipalities. The city lookup returns Manila; barangay retrieval uses the stored city ancestor to return all **897** district barangays. Isabela retains its special grouping row but does not become an extra province/city; SGA municipalities remain reachable through their region-only path.

The existing SearchableSelect controls and profile layout are reused. Startup retrieves regions only; changes fetch relevant children and clear all old descendant names/codes. The existing loading/error/Retry and stale-response protections remain. Postal/ZIP is still manually entered and validated as four digits; street address stays manual and follows existing server sanitization. The workbook has no authoritative postal mapping.

## Save, restoration and compatibility

Existing applicants.AddressPSGC JSON stores region/province/city/barangay identifiers; no new applicant columns were needed in this request. Existing Region, StateProvince, City, Barangay, PostalCode, StreetAddress, Address and HomeLocation continue storing readable text. The save API now obtains its canonical names and validation from database-backed PSGC helpers. A tampered hierarchy cannot save. Codes and readable fields save in the existing transaction.

GET loads saved codes and preserves old text-only addresses. Uniquely resolvable legacy names restore within their parent context; ambiguous names remain visible without being assigned an invented code. Earlier saved provincial associations for independent cities normalize to the current official path on retrieval without rewriting existing address text. The frontend awaits each parent lookup before restoring its children. Restoration uses database data, not localStorage.

HR get_application_details.php reads applicants.Address and returns readable `address`. Job Wall get_jobs.php still reads readable HomeLocation. Hire helper keeps the applicant row linked to the same UserID, and reads a.Address; this task does not change conversion or add an employee-address transfer. Live Admin/HR, job matching and hiring workflows were not exercised. The legacy Admin get_user_profile.php references different column conventions (e.g. u.id/a.user_id); that pre-existing issue is outside this task and was not changed.

## Repeatable import and deployment

1. Run `python tools/prepare_psgc.py <official-workbook.xlsx> <prepared.json>` with openpyxl available in the development environment. This reads the workbook once, checks its actual headers/date/levels/counts/codes and constructs validated parent relationships. It rejects a different publication/counts for explicit review.
2. Run `php tools/import_psgc.php <prepared.json> --dry-run` to validate without writes.
3. Run `php tools/import_psgc.php <prepared.json>` to apply the additive table migration and transactionally upsert by PSGC code through the existing shared DB connection. The import reconciles database counts before commit. Repeating the import is safe for this version.

The source workbook stays in Downloads and is not copied into the web application. Prepared JSON/test resources are under Testing/PSGC, whose Apache .htaccess denies public access. The importer also rejects every non-CLI request. Existing PhpSpreadsheet is present; the permitted one-time development transformation avoids adding an XLSX parser to runtime. Neither XLSX nor JSON is opened when an applicant changes a dropdown. No runtime Windows/desktop path was introduced.

Migration **sql/psgc_official_locations.sql** was applied to onazul_hris_db. Two imports returned the same 43,768 rows with no duplicate keys. Every imported source row was compared against the database by code/name/level/hierarchy/class/provenance. No existing production applicant address was edited by tests. The existing shared DB configuration is currently hardcoded locally; Railway connection setup remains a separate existing deployment concern. The address code reuses that connection and requires no Windows-specific runtime resource. Future publication changes require explicit review, especially removed codes; the current importer never deletes historical records and rolls back on unexpected total counts.

## Exact files

Added:

- tools/prepare_psgc.py — offline workbook inspection/transformation.
- tools/import_psgc.php — CLI-only validated, transactional idempotent import.
- sql/psgc_official_locations.sql — additive indexed table.
- Testing/PSGC/official_import.json — official workbook-derived import/test resource.
- Testing/PSGC/official_check.php — database/source and hierarchy checks.
- Testing/PSGC/OFFICIAL_REPORT.md — this report.

Modified:

- Api/Helpers/psgc_address.php — indexed database retrieval/validation/legacy resolution replaces JSON mirror lookup.
- Api/Applicant/get_psgc_locations.php — uses the existing database connection; endpoint name/authentication/response shape retained.
- Api/Applicant/get_applicant_profile_setup.php — canonical saved-code path restoration for independent cities.
- Testing/PSGC/check.php — delegates to official database verification.
- Testing/PSGC/cascade_check.js — VM now uses official workbook-derived records.
- Api/Data/psgc/README.md — documents the replacement/import architecture.
- Testing/PSGC/REPORT.md — marks prior report superseded.

Removed superseded unofficial data: Api/Data/psgc/metadata.json, all 18 region JSON partitions, and Testing/PSGC/flat.json. No applicant/lookup table records were removed. Production HTML/JS/save endpoint, Job Wall, classification, payroll, attendance, notifications and geofence were not changed for this task.

## Required tests and evidence

PASS here means the described database/API/VM check passed; it does not imply a live interactive UI test.

1. Ordinary province → municipality → barangay — **PASS**, Adams full hierarchy validates.
2. Ordinary province → component city → barangay — **PASS**, City of Batac full hierarchy validates.
3. Cagayan de Oro — **PASS**, official code/direct Region X path, 80 barangays, API save/reload.
4. NCR → Manila → barangay — **PASS**, 897 barangays, no fake province, save/reload.
5. NCR → Quezon City → barangay — **PASS**, database hierarchy and barangay validation.
6. HUC — **PASS**, City of Angeles independent path validates.
7. ICC — **PASS**, City of Dagupan independent path validates.
8. Duplicate barangay names — **PASS**, distinct codes/parents verified.
9. Change region — **PASS**, descendant names/codes reset in VM.
10. Change province — **PASS**, city/barangay reset in VM.
11. Change city — **PASS**, barangay reset in VM.
12. Save complete address — **PASS**, isolated PHP/MariaDB HTTP save and subsequent database-backed GET.
13. Refresh — **NOT VERIFIED** in a real browser. API reread and sequential frontend hydration passed independently.
14. Logout/login — **NOT VERIFIED** through actual login UI. A fresh authenticated fixture session retrieved the saved address.
15. Edit address — **PASS**, CDO address changes to NCR and saves/reloads without old province/barangay contamination.
16. Manipulated hierarchy — **PASS**, mismatched region/province/barangay rejected; HTTP POST returns 400.
17. All regions imported — **PASS**, 18 source/database and row reconciliation.
18. All provinces imported — **PASS**, 82 source/database and row reconciliation.
19. All cities imported — **PASS**, 149 source/database and row reconciliation.
20. All municipalities imported — **PASS**, 1,493 source/database and row reconciliation.
21. All barangays imported — **PASS**, 42,010 source/database and row reconciliation.
22. SubMun handling — **PASS**, all 14 imported as districts; 897 child barangays linked to Manila.
23. Import twice — **PASS**, repeated production import retained exactly 43,768 records with no duplicates.

Additional performance: **PASS** for VM startup (only regions requested) and database EXPLAIN (idx_psgc_city, 80 matching CDO rows). **NOT VERIFIED** for actual browser network trace/timing, because the available Codex browser is on the sign-in page. PHP syntax checks passed for affected endpoints/helper/importer. Guest and non-applicant lookup requests returned 401 in the isolated API test. Error/reset behavior passed simulation. Existing readable HR/Job Wall/conversion compatibility was traced statically; actual interactive regression is **NOT VERIFIED**. No failures remain in the executed checks.
