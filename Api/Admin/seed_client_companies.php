<?php
/**
 * Seed example client companies.
 * Run once (while logged in as Admin): http://localhost/Employee_Tracking/Api/Admin/seed_client_companies.php
 */
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin login required.']);
    exit;
}
require __DIR__ . '/../Db_connection/db_connect.php';

$examples = [
    ['Acme Corporation', '123 Business Ave, Metro City', 'John Smith', '+63 912 345 6789', 'Manufacturing'],
    ['TechStart Solutions', '45 Innovation Drive, Tech Park', 'Maria Garcia', '+63 917 234 5678', 'Information Technology'],
    ['Global Logistics Inc', '78 Harbor Road, Port District', 'Robert Cruz', '+63 918 345 6789', 'Logistics'],
    ['Prime Retail Group', '100 Commerce St, Central Plaza', 'Anna Reyes', '+63 919 456 7890', 'Retail'],
    ['Pacific BPO Services', '200 Tower One, Cyber Bay', 'Michael Torres', '+63 920 567 8901', 'Business Process Outsourcing'],
];

$stmt = $conn->prepare("SELECT CompanyID FROM clientcompany WHERE Name = ? LIMIT 1");
$insert = $conn->prepare("INSERT INTO clientcompany (Name, Address, ContactPerson, ContactNumber, Industry, IsActive) VALUES (?, ?, ?, ?, ?, 0)");

$added = 0;
foreach ($examples as $row) {
    $stmt->bind_param('s', $row[0]);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $insert->bind_param('sssss', $row[0], $row[1], $row[2], $row[3], $row[4]);
        $insert->execute();
        $added++;
    }
}
$stmt->close();
$insert->close();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => "Added {$added} example client companies. Refresh the admin dashboard to see them."
]);
