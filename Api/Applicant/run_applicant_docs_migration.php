<?php
// One-time migration: allow ApplicationID to be NULL in applicant_uploaded_docs
// so applicants can upload general documents not tied to a specific application.
require __DIR__ . '/../Db_connection/db_connect.php';

$sql = "ALTER TABLE applicant_uploaded_docs MODIFY ApplicationID int(11) DEFAULT NULL";

$ok = $conn->query($sql);

header('Content-Type: application/json');
if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Migration applied: ApplicationID now nullable.']);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

