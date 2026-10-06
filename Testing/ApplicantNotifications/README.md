Applicant navigation and notification checks

run.php uses a disposable database and copied APIs. It checks seven application notification types through the production notification helper, delivery through the applicant inbox API, individual and bulk read actions, account scoping, and unchanged production data. It does not simulate every hiring/endorsement workflow end to end.

ui.cjs uses mocked API responses against actual pages. Desktop and mobile checks cover notification details, read/unread styling, unread filtering, automatic refresh on returning to the page, navigation information dialogs, and responsive fit. It also checks the HR interview validation pop-up without invoking a browser alert.

The inbox polls every 15 seconds while visible. Existing notification producers cover scheduling, HR status changes, endorsements, client decisions, and hiring; new applications now receive a submission confirmation. No historical notifications are fabricated.
