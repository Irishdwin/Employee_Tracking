# Applicant PDF-only resumes

Implemented using the ONAZUL HRIS Development Skill supplied in ONAZUL_HRIS_Claude_Development_Skill_Updated.zip and the attached PDF résumé requirements. Changes remain in the connected project.

## Gap and implementation

Two applicant upload APIs accepted images and Word documents for Resume/CV; the profile endpoint allowed 15 MB while document management allowed 5 MB. Both now use a shared PDF résumé validator with a 5 MB limit. Other document upload formats/limits are preserved.

The profile résumé card says Resume/CV (PDF Only), displays the requested 5 MB helper, and accepts PDF only. The general Employment History uploader now explicitly distinguishes Resume/CV from other employment documents, defaults to Resume/CV, and changes its accept filter accordingly. Both frontend upload paths validate extension, browser MIME when supplied, and size. Existing filename/status, view, download and replacement controls remain.

The PHP helper inspects actual file size, extension, finfo MIME, PDF header, EOF/startxref and document structure markers; it rejects recognizable active-content markers. Both upload APIs independently enforce it. New files use random filenames under Uploads/Documents/Resumes, denied to direct Apache access. Existing applicant_uploaded_docs relationships remain. No migration or new table was created. Replacement creates a new latest record while preserving earlier files. Failed record writes remove their new file; the quick uploader no longer returns raw exception details.

## Protected viewing and recruitment

The existing view_document endpoint serves inline PDFs/images or forced downloads. It verifies the actual active database role as well as the session role. Applicant/Employee access is limited to the owning account; HR/Admin can inspect recruitment documents. Team Lead access requires an endorsed application within the lead’s assigned company scope. File paths resolve only inside Uploads/Documents; filename headers are sanitized and responses use private/no-store and nosniff headers.

New résumé PDFs are never served through public storage URLs. Historical flat document URLs route through the same authenticated viewer via Apache rewrite; no historical files were moved, deleted or converted. This shared routing also requires authorized access for other documents in that directory. Their upload formats and contents are unchanged. PDF URLs returned to profile, document management, HR review, Admin user details and linked Employee documents use the existing protected endpoint. HR application details now reuse the existing shared session bootstrap. Existing PDF/image preview components remain; missing profile preview documents show an unavailable/replacement message.

## Files modified

- Html/Applicant/applicant_profile_master.html
- Js/Applicant/applicant_profile_form.js
- Js/Applicant/applicant_documents_panel.js
- Api/Applicant/upload_applicant_document.php
- Api/Applicant/upload_document.php
- Api/Applicant/view_document.php
- Api/Applicant/download_applicant_document.php
- Api/Applicant/applicant_doc_fs.php
- Api/Applicant/my_documents.php
- Api/Applicant/get_applicant_profile_setup.php
- Api/Helpers/applicant_required_documents.php
- Api/HR/get_application_details.php
- Api/Admin/users.php
- Api/Employee/dashboard_data.php
- Testing/ClientCompanies/http_test.php and generated http_results.json

Created: Api/Helpers/resume_upload.php, Uploads/Documents/.htaccess, Uploads/Documents/Resumes/.htaccess, and this report.

## Verification evidence

RUNTIME VERIFIED: 118 isolated HTTP/database checks passed, including 29 new résumé-related checks plus existing module regressions. They cover both actual upload endpoints accepting a valid PDF and rejecting JPG, PNG, renamed image .pdf, malformed PDF and over-limit content; preserved replacement history; own preview/download; HR/Admin access; other applicant isolation; forged role denial; unauthorized application upload; legacy image preview; valid ID image upload; missing résumé upload; assigned/unassigned Team Lead access; and HR recruitment returning protected résumé URLs.

RUNTIME VERIFIED: Five existing required-document checks passed: résumé and ID are required, another applicant’s ID does not count, existing valid documents satisfy completion, and missing files do not count.

RUNTIME VERIFIED: Apache returned 403 for a temporary new résumé storage probe and 404 for an unregistered historical flat URL. Synthetic probe files were removed. Isolated test databases, sessions and uploads were cleaned; real applicant documents were not changed.

STATICALLY VERIFIED: Changed PHP files, applicant JavaScript and profile inline scripts passed syntax checks. Existing résumé image previews remain supported; no image-to-PDF conversion was introduced.

## Limits and manual checks

PDF inspection is file-type/structural validation, not a complete PDF parser or malware scanner. It does not guarantee every possible corrupted or malicious PDF is detected. Browser PDF rendering, PDF internal features, mobile viewer behavior and a full human recruitment UAT still require manual verification. The server reports missing/invalid newly stored résumé files safely. Uploaded PDFs with detected active content are intentionally rejected.

Apache mod_rewrite and .htaccess enforcement are required; the current XAMPP protection probe passed. Copies hosted on servers without those protections must configure equivalent access restrictions. There was no existing CSRF token on these upload flows to preserve; this work did not introduce a new CSRF scheme.

Automatic approval review initially rejected a proposed legacy route that served non-résumé documents before authentication. That proposal was not executed. The implemented safer route authorizes every historical document request before serving it.
