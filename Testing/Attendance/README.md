# Attendance Testing Tool — DEVELOPMENT / TESTING ONLY

**This is not part of the production Onazul HRIS.** It exists purely so that
during capstone development/testing, you can create realistic employee
attendance/DTR records without manually inserting rows through phpMyAdmin.

## Purpose

Create, edit, and delete real `attendance` / `daily_time_records` rows for
any employee, so you can exercise the full downstream pipeline (Employee
Dashboard, Smart Calendar, Team Lead review, DTR submission, Accounting
verification, Payroll, Overtime, Payslip) with realistic data on demand.

## Environment

Development / testing only. Requires an active **Admin** session in the main
Onazul HRIS (log in normally through the production login page first, then
open this tool in the same browser).

## How to access

Open directly in the browser — there is no link to it anywhere in the
production sidebars, by design:

```
http://localhost/Employee_Tracking/Testing/Attendance/attendance_testing.html
```

## How to use

1. Select a **Client Company**.
2. Select the **Team Lead** deployed to that company.
3. Select the **Employee** deployed under that Team Lead.
4. The employee's actual duty schedule (working days, time in/out, break,
   grace period — pulled live from the `deployment` table, same as
   production) is shown for reference.
5. Enter a **Date**, **Time In**, and optionally **Time Out**, then
   **Save Record**.
6. The record appears in the table below immediately, and — because it's a
   real row in the real tables — will also immediately appear on that
   employee's actual Employee Dashboard, Smart Calendar, and the Team
   Lead's roster/DTR review, exactly like a real clock-in would.
7. **Edit** or **Delete** any not-yet-submitted record from the table.
   Records already submitted to Accounting are locked and shown grayed out
   — the tool will not touch those.
8. To simulate an **Absent** day, simply don't create a record for that
   date (or delete one you created) — Absent is inferred automatically for
   any past scheduled working day with no record, exactly as production
   already does. Non-working days are automatically Rest Days regardless of
   what you enter.

## How the data flows

```
Attendance Testing Tool
        │  (writes directly, same tables/columns as the real clock-in/out endpoint)
        ▼
attendance + daily_time_records   (existing production tables — no new tables)
        │
        ├──▶ Employee Dashboard / Smart Calendar / Payroll tab
        ├──▶ Team Lead DTR Cutoff Review / Overtime panel
        ├──▶ Team Lead → Accounting DTR Submission
        ├──▶ Accounting DTR Verification
        └──▶ Payroll computation / Payroll History
```

Status (Present / Late / Undertime), late minutes, and undertime minutes are
computed by calling the **same** `payroll_timesheet_day_status()` function
that the Employee Dashboard, Team Lead roster, and payroll computation
already use (`Api/Helpers/employee_payroll_helper.php`) — this tool does not
reimplement that logic, it only calls it, so a "Late" record created here is
guaranteed to display as "Late" everywhere else too.

## Production deployment

**Delete this entire `/Testing/` folder before publishing the system
online.** Nothing in the production system depends on it — it only ever
reads/writes existing tables through its own isolated API files. No
production file was modified to build this tool, and no production sidebar
links to it.

After deleting `/Testing/`, verify:

- [ ] Employee Dashboard still works
- [ ] Team Lead Dashboard still works
- [ ] Accounting Dashboard still works
- [ ] Admin Dashboard still works
- [ ] Payroll still works
- [ ] DTR verification / submission still work
- [ ] Employee Smart Calendar still works
- [ ] Employee clock-in / clock-out still work
- [ ] GPS/geofence attendance still works
- [ ] Overtime functionality still works
- [ ] Employee payslip / Payroll History still work

(All of the above should already be unaffected, since this tool never
touched any of those files — this checklist is just a final sanity pass.)

## Files in this module

```
/Testing/Attendance/
    README.md                              (this file)
    attendance_testing.html
    attendance_testing.css
    attendance_testing.js
    Api/
        _testing_guard.php                 (shared Admin-only auth guard — includes,
                                             does not modify, the production session/DB files)
        get_companies.php
        get_teamleads.php
        get_employees.php
        get_employee_attendance_list.php
        save_attendance.php                (create + update)
        delete_attendance.php
```

## Database

Uses the existing `onazul_hris_db` database exclusively:
- `attendance` (existing table — no new columns)
- `daily_time_records` (existing table — no new columns)
- `employee`, `deployment`, `clientcompany`, `users` (read-only, for the
  cascading Company → Team Lead → Employee dropdowns and schedule lookup)

**No new database tables or columns were created for this feature.**
