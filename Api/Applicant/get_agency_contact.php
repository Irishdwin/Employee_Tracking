<?php
// Public, read-only agency contact information for the applicant landing page.
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success'=>false]); exit;
}
require __DIR__ . '/../Db_connection/db_connect.php';
try {
    $result = $conn->query("SELECT SettingKey,SettingValue FROM systemsettings WHERE Category='agency_contact' AND SettingKey IN ('agency_contact_name','agency_contact_email','agency_contact_phone')");
    $contact = ['name'=>'','email'=>'','phone'=>''];
    while ($row = $result->fetch_assoc()) {
        $field = substr($row['SettingKey'], strlen('agency_contact_'));
        $value = trim($row['SettingValue'] ?? '');
        // Do not publish the legacy seed placeholders as real agency contacts.
        if ($value === 'talent@onazul.example' || $value === '+63 (000) 000-0000') $value = '';
        $contact[$field] = $value;
    }
    echo json_encode(['success'=>true,'contact'=>$contact]);
} catch (Throwable $e) {
    error_log('Public agency contact: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Unable to load agency contact details.']);
}
