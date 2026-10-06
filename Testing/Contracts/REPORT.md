# Employment contract changes

Implemented the agency rule confirmed by the user: six calendar months from the contract signed date, while the employee is active.

- Open Contract disables both years/months inputs, stores zero fixed duration and has no expiry.
- Expiring Contract enables both inputs. Years are no longer hardcoded to two. Server validation requires positive total duration, whole years 0–100 and months 0–11.
- Automatic conversion changes the type to Open Contract and removes the expiry. It preserves the signed document and unrelated application remarks.
- Both employmentcontract rows and the existing hired-application Remarks metadata are handled. Applicants who have not yet been hired are excluded from automatic conversion.
- Conversion runs before expiry reminders, during existing authenticated Admin/HR counters, and HR contract-list loading. No scheduled background service exists; conversion is processed when these requests run. Missing signed dates are not guessed.

Files modified: Html/HR/applicant_contracts.html; Api/HR/upload_contract.php; Api/HR/get_conversion_list.php; Api/Helpers/contract_expiry_helper.php; Api/Common/realtime_counters.php.
Files created: Testing/Contracts/six_month_policy.php; this report. No schema changes.

PASS: PHP syntax on all four APIs/helpers; contract page inline JavaScript syntax; isolated MariaDB six-month boundary, shorter tenure exclusion, inactive exclusion, expiry clearing, repeated-run idempotence, hired-application metadata conversion and preservation of signed file/HR notes. Test fixture contract rows were cleaned up and altered fixture application remarks restored. Production business records were not changed by tests.

NOT VERIFIED: authenticated browser upload, visible field interactions, real file upload and full reminder UI regression. Existing Admin/HR role restrictions remain in place.
