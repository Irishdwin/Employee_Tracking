# Official PSGC data

Runtime uses the indexed psgc_locations database table populated from the user-provided PSA PSGC-2Q-2026-Publication-Datafile.xlsx (30 June 2026). Superseded mirror JSON files have been removed.

Workbook -> tools/prepare_psgc.py -> validated JSON -> CLI tools/import_psgc.php -> MySQL/MariaDB -> authenticated get_psgc_locations.php -> existing searchable Applicant selectors.

The workbook is an offline import source, not a runtime dependency. See Testing/PSGC/OFFICIAL_REPORT.md for exact fields, source hash, all counts, relationships, repeatable commands and test limitations. Acknowledge the Philippine Statistics Authority as geographic data source.
