<?php
/**
 * Resolve stored DB path (../../uploads/... or ../../Uploads/...) to absolute filesystem path.
 */
function applicant_doc_absolute_path(string $filePathFromDb): ?string
{
    $p = trim($filePathFromDb);
    if ($p === '') {
        return null;
    }
    $p = preg_replace('#^\.\./\.\./#', '', $p);
    $root = dirname(__DIR__, 2);
    $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p);
    $real = realpath($full);
    return $real !== false ? $real : null;
}

function applicant_doc_ensure_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $r = @$conn->query("SHOW COLUMNS FROM applicant_uploaded_docs LIKE 'DocCategory'");
    if ($r && $r->num_rows === 0) {
        @$conn->query(
            "ALTER TABLE applicant_uploaded_docs ADD COLUMN DocCategory VARCHAR(32) NOT NULL DEFAULT 'general' AFTER DocType"
        );
    }
    $done = true;
}
