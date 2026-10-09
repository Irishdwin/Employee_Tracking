<?php
require_once __DIR__ . '/../Common/session_init.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$allowedRoles = ['Admin', 'HR'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/hire_applicant.php';

header('Content-Type: application/json');
require_once __DIR__.'/../Helpers/application_endorsement.php';
try {ae_authorize($conn,(int)$_SESSION['user_id'],['Admin','HR']);} catch(DomainException $e){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Forbidden']);exit;}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;
$sssNumber = isset($input['sss_number']) ? trim($input['sss_number']) : '';
$philhealthNumber = isset($input['philhealth_number']) ? trim($input['philhealth_number']) : '';
$pagibigNumber = isset($input['pagibig_number']) ? trim($input['pagibig_number']) : '';
$dailyRate = isset($input['daily_rate']) ? $input['daily_rate'] : 0;
$allowance = isset($input['allowance']) ? $input['allowance'] : 0;

$result = hireApplicantFromApplication(
    $conn,
    $applicationId,
    (int)$_SESSION['user_id'],
    $sssNumber,
    $philhealthNumber,
    $pagibigNumber,
    is_numeric($dailyRate) ? (float)$dailyRate : 0,
    is_numeric($allowance) ? (float)$allowance : 0,
    $input['sunday_duty_allowance'] ?? '0'
);

echo json_encode($result);
