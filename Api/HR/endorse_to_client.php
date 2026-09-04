<?php
// Start output buffering immediately to catch and discard any stray notices, warnings, or whitespace from includes
ob_start();

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$allowedRoles = ['Admin', 'HR'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require __DIR__ . '/../Helpers/notification_helper.php';
if (file_exists(__DIR__ . '/../Helpers/chat_helper.php')) {
    require __DIR__ . '/../Helpers/chat_helper.php';
}

// Fallback definition for status helper if missing
if (file_exists(__DIR__ . '/../Helpers/application_status_helper.php')) {
    require __DIR__ . '/../Helpers/application_status_helper.php';
} else {
    function ash_get_or_create_status_id($conn, string $statusName, array $aliases = []): ?int {
        $names = array_merge([$statusName], $aliases);
        foreach ($names as $name) {
            $stmt = $conn->prepare("SELECT StatusID FROM status WHERE Name = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $name);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $stmt->close();
                    return (int)$row['StatusID'];
                }
                $stmt->close();
            }
        }
        $stmt = $conn->prepare("INSERT INTO status (Name) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param("s", $statusName);
            if ($stmt->execute()) {
                $id = (int)$conn->insert_id;
                $stmt->close();
                return $id;
            }
            $stmt->close();
        }
        return null;
    }
}

require_once __DIR__ . '/../Helpers/AuditLogger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;
$applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;
$userId = (int)$_SESSION['user_id'];

if ($applicationId <= 0) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit();
}

$statusId = ash_get_or_create_status_id($conn, 'Endorsed to Client', ['Endorsed']);
if (!$statusId) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Could not resolve endorsed status.']);
    exit();
}

$conn->begin_transaction();

try {
    $endorsedDate = date('Y-m-d H:i:s');
    $updateStmt = $conn->prepare("
        UPDATE job_applications 
        SET EndorsedDate = ?, EndorsedBy = ?, ClientApprovalStatus = 'Pending', StatusID = ?
        WHERE ApplicationID = ?
    ");
    $updateStmt->bind_param("siii", $endorsedDate, $userId, $statusId, $applicationId);
    $updateStmt->execute();
    $updateStmt->close();

    createApplicationNotification($conn, $applicationId, 'application_endorsed', 'Endorsed to Client', 'Your application has been endorsed to the client for review.');

    $infoQuery = $conn->query("
        SELECT COALESCE(a.FullName, CONCAT(a.FirstName, ' ', a.LastName)) AS app_name, c.Name AS comp_name 
        FROM job_applications ja 
        LEFT JOIN applicants a ON ja.ApplicantID = a.ApplicantID 
        LEFT JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
        LEFT JOIN clientcompany c ON jo.CompanyID = c.CompanyID
        WHERE ja.ApplicationID = " . (int)$applicationId
    );
    $info = ($infoQuery) ? $infoQuery->fetch_assoc() : [];
    $appName = $info['app_name'] ?? 'Applicant';
    $compName = $info['comp_name'] ?? 'Client Company';

    AuditLogger::log($conn, 'Applicant', 'Endorse', "Applicant {$appName} was endorsed to {$compName}.", (string)$applicationId);

    $tlNotifStmt = $conn->prepare("
        INSERT INTO notifications (UserID, NotificationType, Title, Message, RelateedModule, RelatedRecord, IsRead, Priority)
        SELECT DISTINCT
            tl_user.UserID,
            'endorsed_applicant',
            'New Applicant Endorsed',
            CONCAT('Applicant ', a.FullName, ' has been endorsed for ', jo.Position, ' at ', c.Name, '.'),
            'job_application',
            ja.ApplicationID,
            0,
            'Normal'
        FROM job_applications ja
        INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
        INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
        INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
        INNER JOIN (
            SELECT d.CompanyID, d.SiteSuperVisorID AS UserID FROM deployment d WHERE d.SiteSuperVisorID IS NOT NULL
            UNION
            SELECT cc.CompanyID, cc.UserID FROM clientcompany cc WHERE cc.UserID IS NOT NULL
        ) tl_user ON tl_user.CompanyID = jo.CompanyID
        WHERE ja.ApplicationID = ?
    ");
    if ($tlNotifStmt) {
        $tlNotifStmt->bind_param("i", $applicationId);
        $tlNotifStmt->execute();
        $tlNotifStmt->close();
    }

    if (function_exists('chat_ensure_tables')) {
        chat_ensure_tables($conn);
    }

    $conn->commit();

    ob_clean();
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Applicant endorsed successfully.']);
    exit();
} catch (Throwable $e) {
    $conn->rollback();
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to endorse: ' . $e->getMessage()]);
    exit();
}