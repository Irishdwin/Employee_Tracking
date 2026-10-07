# ONAZUL ERD relationship cleanup

Input: Onazul_ERD_CONNECTED.drawio. Output: Onazul_ERD_RELATIONSHIPS_FIXED.drawio.
Read-only live information_schema metadata inspected; all 30 SQL migration/support files and prior production-query evidence cross-checked. No database or production code modified.

Tables inspected / existing entities: 67 / 67.
Connectors before / after: 166 / 159. Physical FKs: 137. Retained separately labelled logical references: 22.
Invalid/non-relational lines removed: 7. Duplicates removed: 0. Missing FKs added: 0. Cardinalities corrected: 22.

## Removed connectors
- applicants.AddressPSGC -> psgc_locations.PSGCCode: JSON multi-value address reference cannot be represented as a scalar FK/cardinality
- clientcompany.Industry -> industry_lookup.Name: Lookup/calculation dependency, not an ERD key relationship
- job_order_requests.WorkSchedule -> work_schedule_lookup.Name: Lookup/calculation dependency, not an ERD key relationship
- applicants.Region -> address_region_lookup.Name: Lookup/calculation dependency, not an ERD key relationship
- payroll.SSSDeduction -> sss_rates_config.monthly_salary_credit: Lookup/calculation dependency, not an ERD key relationship
- payroll.PhilHealthDeduction -> gov_percentage_rates.philhealth_ee_pct: Lookup/calculation dependency, not an ERD key relationship
- payroll_run_status_history.PayrollRunID -> payroll_runs.PayrollRunID: Unverified schema-only candidate

## Cardinality
Physical 1:1 maximum: 2. Physical 1:M maximum: 135. Nullable/optional parent links: 82.
Uniqueness is checked against complete PK/UNIQUE groups. Nullable FK permits 0..1 parent; non-null FK requires one parent. Parent may have zero children; unique child FK permits at most one child. Logical cardinalities are intended references with separately checked child uniqueness/nullability, not claims of DB enforcement. LastReadMessageID=0 is an optional initial cursor sentinel. system_logs actor has an applicant-session fallback, reported as polymorphic.
Junctions: chat_thread_participants (ThreadID, UserID) and chat_thread_reads (ThreadID, UserID) are retained with both logical parent links, representing association through visible bridge entities. No direct M:N shortcut added.
PSGC parent links remain a separately evidenced logical self-loop, and province-less cities are not forced into a province hierarchy. JSON address-code association is reported separately, not depicted as a scalar FK.

## Disconnected tables
Previously zero connectors: []. Missing declared relationships to those tables: none.
- gov_percentage_rates: LEGITIMATELY STANDALONE IN CURRENT DATABASE. Shared calculation configuration; no stored FK reference.
- industry_lookup: LEGITIMATELY STANDALONE IN CURRENT DATABASE. Name dropdown dependency only; no installed FK or stored reference ID.
- payroll_run_status_history: LEGITIMATELY STANDALONE IN CURRENT DATABASE. No installed FK constraints and no current production usage found; unverified candidate removed.
- sss_rates_config: LEGITIMATELY STANDALONE IN CURRENT DATABASE. Shared calculation configuration selected by salary bracket; no stored FK reference.
- work_schedule_lookup: LEGITIMATELY STANDALONE IN CURRENT DATABASE. Name dropdown with custom text values; no installed FK or stored reference ID.

Tables without installed incoming/outgoing FKs (some have clearly evidenced dashed logical links): address_region_lookup, applicant_profiles, chat_messages, chat_threads, chat_thread_participants, chat_thread_reads, daily_time_records, gov_percentage_rates, industry_lookup, payroll_group_status_history, payroll_runs, payroll_run_status_history, payroll_status_history, psgc_locations, sss_rates_config, system_logs, work_schedule_lookup.

## Geometry validation
Connector/unrelated-table intersection pairs BEFORE: 44; AFTER: 0.
Crossings BEFORE: {'proper_crossing_events': 1070, 'crossing_connector_pairs': 940, 'collinear_overlap_pairs': 2053}. AFTER: {'proper_crossing_events': 673, 'crossing_connector_pairs': 628, 'collinear_overlap_pairs': 627}.
Proper crossings count interior/interior segment intersections; shared endpoints and collinear overlaps are separate. Counts use the explicit saved polyline geometry. Previous automatic router effects could not be reconstructed reliably beyond saved waypoints. Final edge automatic routing is disabled; all line segments and boundary ports are explicitly fixed.
All final routes also tested against source/target interiors; no text-crossing segments. No floating endpoints. Tables moved: none; positions, sizes, styles, columns and key notation preserved. Supplemental legend replaced.

## Final FK validation
All installed FKs represented exactly once. Missing FK connectors: 0. Extra physical FK connectors: 0. Duplicate relationship identities: 0. Unrelated entity intersections: 0. Detailed per-relationship source/FK/target/cardinality/route status and removed line classifications are in relationship_fix_audit.json.

## Files
- Onazul_ERD_RELATIONSHIPS_FIXED.drawio
- relationship_fix_audit.json
- ERD_RELATIONSHIPS_FIXED_REPORT.md
- Onazul_ERD_RELATIONSHIPS_FIXED.png (preview generated separately)

Independent XML round-trip validation: all original table/attribute cells unchanged; 159 unique relationship identities; all source/target endpoints resolve; every final segment is orthogonal and avoids every table interior (including own source/target); 0 table intersections. All visible relationship label boxes independently placed outside table bounds. 2 labels retained in relationship metadata instead of covering table text. No tables moved. The separate legacy address hierarchy and PSGC self-hierarchy remain without forced links to unrelated production modules.

## Explicit final counts and special cases
- Database tables / ERD entities: 67 / 67.
- Existing connectors: 166. Final connectors: 159 (137 physical + 22 logical).
- Unsupported/non-relational lines removed: 7. Duplicate lines removed: 0. Missing FK connectors added: 0.
- Logical cardinality markers corrected: 22. Physical cardinality markers already matched current constraints.
- Physical maximum 1:1 relationships:
  - payroll_adjustments.AddBackRequestID -> add_back_requests.RequestID
  - payslip.Payroll_ID -> payroll.Payroll_ID
- Physical maximum 1:M relationships: 135.
- Nullable physical relationships: 82.
- Previously disconnected tables: 0 (the connected input had forced lines). New relationships added to previously disconnected tables: 0.
- Five zero-connector tables remain for the explicit standalone reasons above.
- All 137 installed FKs verified exactly once after XML round-trip; 0 missing, 0 extra, 0 duplicates, 0 orphan/floating connectors.
- All 159 polylines geometrically checked against every entity interior; intersections 44 → 0.
- Proper connector crossing events: 1,070 → 673 (37.1% reduction); overlap pairs: 2,053 → 627 (69.5% reduction).
- Tables moved: 0. Original entity geometry and styles unchanged.
- Final editable XML successfully imported and rendered in diagrams.net on 2026-10-07. PNG preview independently rendered and visually inspected.
- The two visible bridge tables represent logical M:N associations via 1:M parent-to-bridge links; neither has installed FK constraints.
- payroll_run_status_history is retained as an entity with no invented connection. Its name/ID similarity alone is not accepted as relationship evidence.
