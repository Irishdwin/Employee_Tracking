<?php
/**
 * Service Agreement uploaded — notify Admin, HR, and TeamLead only.
 * POST JSON:
 *   document_name, start_date, expiration_date (YYYY-MM-DD),
 *   service_agreement_id, company_name
 */
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/service_agreement_notify_helper.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$documentName = trim((string)($input['document_name'] ?? $input['contract_number'] ?? ''));
$startDate = trim((string)($input['start_date'] ?? $input['contract_start_date'] ?? ''));
$expirationDate = trim((string)($input['expiration_date'] ?? $input['expirationDate'] ?? $input['contract_end_date'] ?? ''));
$companyName = trim((string)($input['company_name'] ?? ''));
$agreementId = isset($input['service_agreement_id']) ? (int)$input['service_agreement_id'] : 0;

if ($documentName === '' || $startDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'document_name and start_date are required']);
    exit();
}

$result = notify_service_agreement_uploaded(
    $conn,
    $agreementId,
    $documentName,
    $startDate,
    $expirationDate !== '' ? $expirationDate : null,
    $companyName !== '' ? $companyName : null
);

$copy = sa_build_agreement_copy($documentName, $startDate, $expirationDate);

echo json_encode([
    'success' => true,
    'message' => $copy['message'],
    'notified_count' => $result['notified_count'],
    'recipients' => $result['recipients'],
    'websocket_broadcast' => $result['websocket_broadcast'],
]);
