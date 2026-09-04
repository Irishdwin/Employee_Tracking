<?php
/**
 * DEVELOPMENT / TESTING ONLY. GET ?company_id=X
 * Lists Team Leads (users) who have at least one active deployment for this
 * company — same relationship (deployment.SiteSuperVisorID) already used
 * throughout the production Team Lead APIs.
 */
declare(strict_types=1);

require_once __DIR__ . '/_testing_guard.php';

$companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
if ($companyId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'company_id is required.']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT DISTINCT u.UsersID, u.FullName
        FROM deployment d
        INNER JOIN users u ON u.UsersID = d.SiteSuperVisorID
        WHERE d.CompanyID = ?
          AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        ORDER BY u.FullName ASC
    ");
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $res = $stmt->get_result();

    $teamLeads = [];
    while ($row = $res->fetch_assoc()) {
        $teamLeads[] = ['team_lead_id' => (int)$row['UsersID'], 'name' => $row['FullName']];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'team_leads' => $teamLeads]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load Team Leads.']);
}
