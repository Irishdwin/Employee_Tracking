Employee personal profile editing

Run PHP run.php for disposable-schema API checks, and Node ui.cjs for mocked desktop/mobile browser checks. Tests cover contact-only saves preserving address, validated PSGC address updates, invalid input, CSRF, protected account fields, salary preservation, and unchanged production data.

Employees edit mobile number, birth date, gender, and home address from Edit Profile on either Employee profile screen. Name, login email, employment, payroll, and deployment details remain managed by Admin/HR. Existing address is retained unless Update my address is selected. No schema migration is required.
