<?php
/** DEVELOPMENT / TESTING ONLY. GET — list active client companies. */
declare(strict_types=1);

require_once __DIR__ . '/_testing_guard.php';

try {
    $condition=($_GET['history']??'')==='1' ? 'IsActive=1 OR EXISTS(SELECT 1 FROM deployment d WHERE d.CompanyID=clientcompany.CompanyID)' : 'IsActive=1';
    $res = $conn->query('SELECT CompanyID, Name FROM clientcompany WHERE '.$condition.' ORDER BY Name ASC');
    $companies = [];
    while ($row = $res->fetch_assoc()) {
        $companies[] = ['company_id' => (int)$row['CompanyID'], 'name' => $row['Name']];
    }
    echo json_encode(['success' => true, 'companies' => $companies]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load companies.']);
}
