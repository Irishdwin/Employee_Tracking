# ONAZUL ERD relationship cleanup

Input: Onazul_ERD_RELATIONSHIPS_FIXED.drawio. Output: Onazul_ERD_LEFT_RIGHT_ONLY.drawio. Original connected ERD preserved; previous removal decisions retained.
Read-only live information_schema metadata inspected; all 30 SQL migration/support files and prior production-query evidence cross-checked. No database or production code modified.

Tables inspected / existing entities: 67 / 67.
Connectors before / after: 159 / 159. Physical FKs: 137. Retained separately labelled logical references: 22.
Invalid/non-relational lines removed: 0. Duplicates removed: 0. Missing FKs added: 0. Cardinalities corrected: 0.

## Removed connectors

## Cardinality
Physical 1:1 maximum: 2. Physical 1:M maximum: 135. Nullable/optional parent links: 82.
Uniqueness is checked against complete PK/UNIQUE groups. Nullable FK permits 0..1 parent; non-null FK requires one parent. Parent may have zero children; unique child FK permits at most one child. Logical cardinalities are intended references with separately checked child uniqueness/nullability, not claims of DB enforcement. LastReadMessageID=0 is an optional initial cursor sentinel. system_logs actor has an applicant-session fallback, reported as polymorphic.
Junctions: chat_thread_participants (ThreadID, UserID) and chat_thread_reads (ThreadID, UserID) are retained with both logical parent links, representing association through visible bridge entities. No direct M:N shortcut added.
PSGC parent links remain a separately evidenced logical self-loop, and province-less cities are not forced into a province hierarchy. JSON address-code association is reported separately, not depicted as a scalar FK.

## Disconnected tables
Previously zero connectors: ['gov_percentage_rates', 'industry_lookup', 'payroll_run_status_history', 'sss_rates_config', 'work_schedule_lookup']. Missing declared relationships to those tables: none.
- gov_percentage_rates: LEGITIMATELY STANDALONE IN CURRENT DATABASE. Shared calculation configuration; no stored FK reference.
- industry_lookup: LEGITIMATELY STANDALONE IN CURRENT DATABASE. Name dropdown dependency only; no installed FK or stored reference ID.
- payroll_run_status_history: LEGITIMATELY STANDALONE IN CURRENT DATABASE. No installed FK constraints and no current production usage found; unverified candidate removed.
- sss_rates_config: LEGITIMATELY STANDALONE IN CURRENT DATABASE. Shared calculation configuration selected by salary bracket; no stored FK reference.
- work_schedule_lookup: LEGITIMATELY STANDALONE IN CURRENT DATABASE. Name dropdown with custom text values; no installed FK or stored reference ID.

Tables without installed incoming/outgoing FKs (some have clearly evidenced dashed logical links): address_region_lookup, applicant_profiles, chat_messages, chat_threads, chat_thread_participants, chat_thread_reads, daily_time_records, gov_percentage_rates, industry_lookup, payroll_group_status_history, payroll_runs, payroll_run_status_history, payroll_status_history, psgc_locations, sss_rates_config, system_logs, work_schedule_lookup.

## Geometry validation
Connector/unrelated-table intersection pairs BEFORE: 0; AFTER: 0.
Crossings BEFORE: {'proper_crossing_events': 678, 'crossing_connector_pairs': 630, 'collinear_overlap_pairs': 622}. AFTER: {'proper_crossing_events': 768, 'crossing_connector_pairs': 716, 'collinear_overlap_pairs': 591}.
Proper crossings count interior/interior segment intersections; shared endpoints and collinear overlaps are separate. Counts use the explicit saved polyline geometry. Previous automatic router effects could not be reconstructed reliably beyond saved waypoints. Final edge automatic routing is disabled; all line segments and boundary ports are explicitly fixed.
All endpoints attached only to west/east boundaries of the exact referenced PK/FK rows. First and last segments are horizontal; outward departure and approach explicitly set. All final routes also tested against source/target interiors; no text-crossing segments. No floating endpoints. Tables moved: none; positions, sizes, styles, columns and key notation preserved. Supplemental legend replaced.

## Final FK validation
All installed FKs represented exactly once. Missing FK connectors: 0. Extra physical FK connectors: 0. Duplicate relationship identities: 0. Unrelated entity intersections: 0. Detailed per-relationship source/FK/target/cardinality/route status and removed line classifications are in left_right_audit.json.

## Files
- Onazul_ERD_LEFT_RIGHT_ONLY.drawio
- left_right_audit.json
- ERD_LEFT_RIGHT_REPORT.md
- Onazul_ERD_LEFT_RIGHT_ONLY.png (preview generated separately)

Independent XML round-trip validation: all original table/attribute cells unchanged; 159 unique relationship identities; all source/target endpoints resolve; every final segment is orthogonal and avoids every table interior (including own source/target); 0 table intersections. All visible relationship label boxes independently placed outside table bounds. 3 labels retained in relationship metadata instead of covering table text. No tables moved. The separate legacy address hierarchy and PSGC self-hierarchy remain without forced links to unrelated production modules.


## Left/right endpoint validation
Routing input: Onazul_ERD_RELATIONSHIPS_FIXED.drawio (159 cleaned relationships). Original Onazul_ERD_CONNECTED.drawio was also inspected separately; its seven removed lines were not restored.
Endpoints before (cleaned input): {'left': 71, 'right': 80, 'top': 78, 'bottom': 89, 'unknown': 0}.
Endpoints before (original connected input, 166 lines): {'left': 165, 'right': 167, 'top': 0, 'bottom': 0, 'unknown': 0}.
Endpoints after: {'left': 170, 'right': 148, 'top': 0, 'bottom': 0}.
159/159 connectors rerouted. Every endpoint attached to the exact parent PK/UK and child reference row, with unique nearby boundary ports. First/last segments horizontal; departure is outward; approach is from outside. Top-side endpoints: 0. Bottom-side endpoints: 0. Table interior intersections: 0. Duplicate boundary ports: 0. Tables moved: 0.

## Routing tradeoff and editor verification
All 159 routes comply with the mandatory left/right endpoints. Sorting fan-out ports and penalizing crossings reduced the initial restricted pass from 1,247 to 768 proper crossing events. The final restricted layout has 90 more crossing events than the prior unrestricted layout (678), while overlap pairs fell from 622 to 591. These are remaining line-to-line crossings, not table intersections. A globally minimal crossing layout is not claimed.
The final .drawio was imported into a blank diagrams.net canvas and visually verified at 10% zoom. The local PNG preview uses the saved explicit connector geometry. The original source and preceding ERD versions remain preserved; no production code or database records were modified.
