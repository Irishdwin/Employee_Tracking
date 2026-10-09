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

### Controlled holiday suite (isolated data)

Click **Run All Controlled Holiday Tests** without selecting a real employee.
The local Admin-only POST endpoint creates a disposable database from the current
schema, uses synthetic employees at ₱1,000/day and ₱125/hour, and exercises the
production Attendance → Team Lead submission → Accounting verification → payroll
finalization → employee payslip pipeline. The calendar is fixed to October 1–15,
2026; neither the computer clock nor real holiday records are changed.

The results show expected/actual gross amounts and expandable component checks.
The database is removed after the run. Temporary fixture code/logs remain in the
system temporary directory for diagnosis. Requires local PHP process execution
and MySQL CREATE/DROP DATABASE privileges. Do not expose this development tool
publicly. See `HOLIDAY_E2E_REPORT.md` and `holiday_e2e_results.json` for evidence.

### Manual attendance mode (real records)

1. Select a **Client Company**.
2. Select an active **Deployment Site** in that company.
3. Select the **Team Lead assigned to that site**, then an **Employee** with a
   current active deployment at that site. A Team Lead can appear at several
   sites. Sites with no employees still display their assigned Team Lead.
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

The manual attendance mode uses the existing `onazul_hris_db` database:
- `attendance` (existing table — no new columns)
- `daily_time_records` (existing table — no new columns)
- `employee`, `deployment`, `clientcompany`, `users` (read-only, for the
  cascading Company → Site → Team Lead → Employee dropdowns and schedule lookup)

The site selector uses production `deployment_sites.TeamLeadID` authority and
the shared `site_for_lead()`, `site_owned_employee()` and
`site_current_deployment_sql()` helpers. Selected company/site/lead/deployment
context is validated on each attendance, OT and payroll request. This manual
mode is for current deployments; historical deployments outside that selected
context are rejected. Payroll Type and dated cutoffs use production helpers.

**No new database tables or columns were created for this feature.**

## Selected employee rate and controlled holiday mode

Selecting Company → Site → Team Lead → Employee automatically loads a read-only
payroll rate card. `get_employee_payroll_context.php` validates the deployment and
date and reads current `employee.daily_rate`, `hourly_rate`, and `BasicSalary`.
`payroll_employee_rates()` reuses the existing production precedence and two-decimal
rounding. Production payroll does not currently resolve historical `salary_rates`
effective dates; choosing an earlier test date still uses current employee fields.

Choose a worked scenario and date, then **Run Selected Employee Holiday Scenario**.
This copies only rates/schedule/context into an anonymous disposable fixture and
calls the production Attendance, Team Lead DTR, Accounting, payroll and payslip APIs.
It creates no holiday or attendance in the production database. The test comparison
separates basic pay, holiday premium and holiday OT; allowances and deductions are
excluded from this compensation comparison. Browser rate overrides are ignored.
Rest-day scenarios require a date matching the employee's actual rest schedule.
The selected-employee runner supports worked scenarios A–H; Custom means normal
8-hour work. The existing manual record tools still write real attendance/OT.

The separate **Run All Controlled Holiday Tests** button remains the independent
synthetic ₱1,000/day and ₱125/hour A–K suite plus the boundary case. It does not
depend on the selected employee or rate card. Both controlled modes clean up the
disposable database and check production table checksums.

See `EMPLOYEE_RATE_TEST_REPORT.md` for the implementation and Tests 1–12 evidence.

## Setting late minutes

In Attendance Details, enter whole-number Late minutes to set Time In that many minutes after the employee’s scheduled start for the selected date. Leave the field blank to use manual Time In. Changing Time In manually clears the late override. The server resolves the schedule again and uses the existing production attendance/payroll calculator; values within the grace period still produce zero recorded late minutes. Negative, fractional and next-day clock-in offsets are rejected. The field works for both creating and updating unlocked test attendance.


## Historical attendance

Select an employee and date to resolve the actual deployment/site and effective schedule. Ended deployments and archived historical records are available in testing history selectors. The date must be covered by an existing deployment. Submitted DTR/approved or finalized payroll periods stay locked; use Add Back/correction or isolated fixtures. Create never silently replaces attendance. See `C:/xampp/htdocs/Employee_Tracking/docs/HISTORICAL_ATTENDANCE_FIX.md` for implementation and verification evidence.
