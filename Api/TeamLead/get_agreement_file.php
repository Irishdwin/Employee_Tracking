<?php
/**
 * Team Lead — fetch assigned service agreement document metadata or stream inline preview.
 *
 * GET (default)  → JSON { success, document_path, view_url, contract_number, mime_type, ... }
 * GET ?stream=1  → inline PDF/image for iframe (session + assignment required)
 */
require_once __DIR__ . '/../Common/session_init.php';

$stream = isset($_GET['stream']) && (string)$_GET['stream'] === '1';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($stream) {
        http_response_code(401);
        exit('Unauthorized');
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = trim((string)$_SESSION['role']);
if (!in_array($role, ['TeamLead', 'Team Lead'], true)) {
    if ($stream) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden — Team Lead access only']);
    exit;
}

$teamLeadUserId = (int)$_SESSION['user_id'];

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=onazul_hris_db;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    if ($stream) {
        http_response_code(503);
        exit('Database unavailable');
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

/**
 * @return array<string,mixed>|null
 */
function tl_fetch_agreement_for_user(PDO $pdo, int $teamLeadUserId): ?array
{
    $sql = "
        SELECT
            c.CompanyID              AS company_id,
            c.Name                   AS company_name,
            sa.ServiceAgreementID    AS service_agreement_id,
            sa.ContractNumber        AS contract_number,
            sa.ContractStartDate     AS service_agreement_start,
            sa.ContractEndDate       AS service_agreement_end,
            sa.Status                AS agreement_status,
            sa.SignedDocumentPath    AS signed_document_path
        FROM clientcompany c
        LEFT JOIN serviceagreement sa ON sa.ClientCompanyID = c.CompanyID
            AND sa.ServiceAgreementID = (
                SELECT sa2.ServiceAgreementID
                FROM serviceagreement sa2
                WHERE sa2.ClientCompanyID = c.CompanyID
                  AND sa2.Status = 'Active'
                ORDER BY sa2.ContractStartDate DESC, sa2.ServiceAgreementID DESC
                LIMIT 1
            )
        WHERE c.UserID = :tl_user_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['tl_user_id' => $teamLeadUserId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    $fallbackSql = "
        SELECT
            c.CompanyID              AS company_id,
            c.Name                   AS company_name,
            sa.ServiceAgreementID    AS service_agreement_id,
            sa.ContractNumber        AS contract_number,
            sa.ContractStartDate     AS service_agreement_start,
            sa.ContractEndDate       AS service_agreement_end,
            sa.Status                AS agreement_status,
            sa.SignedDocumentPath    AS signed_document_path
        FROM deployment d
        INNER JOIN clientcompany c ON c.CompanyID = d.CompanyID
        LEFT JOIN serviceagreement sa ON sa.ClientCompanyID = c.CompanyID
            AND sa.ServiceAgreementID = (
                SELECT sa2.ServiceAgreementID
                FROM serviceagreement sa2
                WHERE sa2.ClientCompanyID = c.CompanyID
                  AND sa2.Status = 'Active'
                ORDER BY sa2.ContractStartDate DESC, sa2.ServiceAgreementID DESC
                LIMIT 1
            )
        WHERE d.SiteSuperVisorID = :tl_user_id
          AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        ORDER BY d.StartDate DESC, d.DeploymentID DESC
        LIMIT 1
    ";
    $fb = $pdo->prepare($fallbackSql);
    $fb->execute(['tl_user_id' => $teamLeadUserId]);
    $row = $fb->fetch();
    return $row ?: null;
}

function tl_agreement_mime(string $ext): string
{
    $map = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    return $map[strtolower($ext)] ?? 'application/octet-stream';
}

function tl_agreement_absolute_path(string $relativePath): ?string
{
    $relativePath = str_replace('\\', '/', trim($relativePath));
    $relativePath = ltrim($relativePath, '/');
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return null;
    }

    $projectRoot = realpath(dirname(__DIR__, 2));
    if ($projectRoot === false) {
        return null;
    }

    $abs = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($abs === false || !is_file($abs) || !is_readable($abs)) {
        return null;
    }

    if (strpos($abs, $projectRoot) !== 0) {
        return null;
    }

    return $abs;
}

$row = tl_fetch_agreement_for_user($pdo, $teamLeadUserId);

if (!$row) {
    if ($stream) {
        http_response_code(404);
        exit('No assigned company');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'No client company is assigned to your account.',
    ]);
    exit;
}

$documentPath = trim((string)($row['signed_document_path'] ?? ''));
if ($documentPath === '') {
    if ($stream) {
        http_response_code(404);
        exit('Document not uploaded');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'No signed service agreement document has been uploaded for your assigned company.',
        'company_name' => $row['company_name'] ?? '',
        'contract_number' => $row['contract_number'] ?? '',
    ]);
    exit;
}

$absPath = tl_agreement_absolute_path($documentPath);
if ($absPath === null) {
    if ($stream) {
        http_response_code(404);
        exit('File not found');
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'The agreement file is missing on the server. Contact your administrator.',
        'document_path' => $documentPath,
    ]);
    exit;
}

$ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
$mime = tl_agreement_mime($ext);

if ($stream) {
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($absPath));
    header('Content-Disposition: inline; filename="' . basename($absPath) . '"');
    header('Cache-Control: private, max-age=300');
    header('X-Content-Type-Options: nosniff');
    readfile($absPath);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$viewUrl = 'get_agreement_file.php?stream=1';

echo json_encode([
    'success' => true,
    'company_id' => (int)($row['company_id'] ?? 0),
    'company_name' => $row['company_name'] ?? '',
    'service_agreement_id' => isset($row['service_agreement_id']) ? (int)$row['service_agreement_id'] : null,
    'contract_number' => $row['contract_number'] ?? '',
    'document_path' => $documentPath,
    'view_url' => $viewUrl,
    'mime_type' => $mime,
    'preview_mode' => in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'iframe' : 'download',
]);
