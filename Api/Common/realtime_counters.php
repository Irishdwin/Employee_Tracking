<?php
require_once __DIR__ . '/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => true, 'logged_in' => false, 'counts' => ['messages' => 0, 'job_orders' => 0]]);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$role = trim((string)$_SESSION['role']);
if (in_array($role, ['Admin', 'HR'], true)) {
    require_once __DIR__ . '/../Helpers/contract_expiry_helper.php';
    employment_contract_open_after_six_months($conn);
}

$markRequestsSeen = isset($_GET['mark_requests_seen']) && $_GET['mark_requests_seen'] === '1';

require_once __DIR__.'/../Helpers/messenger_unread.php';
$unreadMessages=messenger_get_unread_counts($conn,$userId)['total'];

$jobOrdersCount = 0;
$jobOrdersRejectedCount = 0;
$endorsedPendingCount = 0;
$attendanceAbsentToday = 0;

if (strcasecmp($role, 'TeamLead') === 0) {
    $tlCompanyScopeSql = "
        EXISTS (
            SELECT 1 FROM deployment d
            WHERE d.CompanyID = jo.CompanyID AND d.SiteSuperVisorID = ?
        )
        OR EXISTS (
            SELECT 1 FROM clientcompany cc
            WHERE cc.CompanyID = jo.CompanyID AND cc.UserID = ?
        )
    ";

    // Endorsed applicants awaiting Team Lead approve/reject.
    $endorsedSql = "
        SELECT COUNT(*) AS c
        FROM job_applications ja
        INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
        WHERE ja.EndorsedDate IS NOT NULL
          AND COALESCE(ja.ClientApprovalStatus, 'Pending') = 'Pending'
          AND ({$tlCompanyScopeSql})
    ";
    $endorsedStmt = $conn->prepare($endorsedSql);
    if ($endorsedStmt) {
        $endorsedStmt->bind_param('ii', $userId, $userId);
        $endorsedStmt->execute();
        $endorsedPendingCount = (int)($endorsedStmt->get_result()->fetch_assoc()['c'] ?? 0);
        $endorsedStmt->close();
    }

    // Newly HR-approved job order requests since last visit.
    $maxApprovedStmt = $conn->prepare("SELECT COALESCE(MAX(RequestID),0) AS max_id FROM job_order_requests WHERE RequestedBy = ? AND Status = 'Approved'");
    $maxRejectedStmt = $conn->prepare("SELECT COALESCE(MAX(RequestID),0) AS max_id FROM job_order_requests WHERE RequestedBy = ? AND Status = 'Rejected'");
    if ($maxApprovedStmt && $maxRejectedStmt) {
        $maxApprovedStmt->bind_param('i', $userId);
        $maxApprovedStmt->execute();
        $maxApprovedId = (int)($maxApprovedStmt->get_result()->fetch_assoc()['max_id'] ?? 0);
        $maxApprovedStmt->close();

        $maxRejectedStmt->bind_param('i', $userId);
        $maxRejectedStmt->execute();
        $maxRejectedId = (int)($maxRejectedStmt->get_result()->fetch_assoc()['max_id'] ?? 0);
        $maxRejectedStmt->close();

        $seenApprovedKey = 'rt_seen_approved_req_id';
        $seenRejectedKey = 'rt_seen_rejected_req_id';
        $seenApprovedId = isset($_SESSION[$seenApprovedKey]) ? (int)$_SESSION[$seenApprovedKey] : 0;
        $seenRejectedId = isset($_SESSION[$seenRejectedKey]) ? (int)$_SESSION[$seenRejectedKey] : 0;

        if ($markRequestsSeen) {
            $_SESSION[$seenApprovedKey] = $maxApprovedId;
            $_SESSION[$seenRejectedKey] = $maxRejectedId;
            $seenApprovedId = $maxApprovedId;
            $seenRejectedId = $maxRejectedId;
        }

        $cntApprovedStmt = $conn->prepare("SELECT COUNT(*) AS c FROM job_order_requests WHERE RequestedBy = ? AND Status = 'Approved' AND RequestID > ?");
        if ($cntApprovedStmt) {
            $cntApprovedStmt->bind_param('ii', $userId, $seenApprovedId);
            $cntApprovedStmt->execute();
            $jobOrdersCount = (int)($cntApprovedStmt->get_result()->fetch_assoc()['c'] ?? 0);
            $cntApprovedStmt->close();
        }

        $cntRejectedStmt = $conn->prepare("SELECT COUNT(*) AS c FROM job_order_requests WHERE RequestedBy = ? AND Status = 'Rejected' AND RequestID > ?");
        if ($cntRejectedStmt) {
            $cntRejectedStmt->bind_param('ii', $userId, $seenRejectedId);
            $cntRejectedStmt->execute();
            $jobOrdersRejectedCount = (int)($cntRejectedStmt->get_result()->fetch_assoc()['c'] ?? 0);
            $cntRejectedStmt->close();
        }
    }

    // Deployed team members absent today (no clock-in yet).
    $absentSql = "
        SELECT COUNT(DISTINCT e.EmployeeID) AS c
        FROM deployment d
        INNER JOIN employee e ON e.EmployeeID = d.EmployeeID AND e.IsActive = 1
        LEFT JOIN daily_time_records dtr
            ON dtr.employee_id = e.EmployeeID AND dtr.date = CURDATE()
        WHERE d.SiteSuperVisorID = ?
          AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
          AND (dtr.id IS NULL OR COALESCE(dtr.status, 'Absent') = 'Absent')
    ";
    $absentStmt = $conn->prepare($absentSql);
    if ($absentStmt) {
        $absentStmt->bind_param('i', $userId);
        if ($absentStmt->execute()) {
            $attendanceAbsentToday = (int)($absentStmt->get_result()->fetch_assoc()['c'] ?? 0);
        }
        $absentStmt->close();
    }
} elseif (strcasecmp($role, 'Applicant') === 0) {
    // Count approved applications as "new job-order updates" for applicant menu badge.
    $appIdStmt = $conn->prepare("SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1");
    if ($appIdStmt) {
        $appIdStmt->bind_param('i', $userId);
        $appIdStmt->execute();
        $aid = (int)(($appIdStmt->get_result()->fetch_assoc())['ApplicantID'] ?? 0);
        $appIdStmt->close();
        if ($aid > 0) {
            $jStmt = $conn->prepare("SELECT COUNT(*) AS c FROM job_applications WHERE ApplicantID = ? AND ClientApprovalStatus = 'Approved'");
            $jStmt->bind_param('i', $aid);
            $jStmt->execute();
            $jobOrdersCount = (int)($jStmt->get_result()->fetch_assoc()['c'] ?? 0);
            $jStmt->close();
        }
    }
} elseif (strcasecmp($role, 'HR') === 0 || strcasecmp($role, 'Admin') === 0) {
    // Pending Team Lead requests awaiting HR/Admin approval and posting to the job wall.
    $reqStmt = $conn->prepare("SELECT COUNT(*) AS c FROM job_order_requests WHERE Status = 'Pending'");
    if ($reqStmt) {
        $reqStmt->execute();
        $jobOrdersCount = (int)($reqStmt->get_result()->fetch_assoc()['c'] ?? 0);
        $reqStmt->close();
    }
}

function rtSafeCount(mysqli $conn, string $sql): int
{
    try {
        $res = $conn->query($sql);
        if (!$res) {
            return 0;
        }
        $row = $res->fetch_assoc();
        $res->free();
        return (int)($row['c'] ?? $row['cnt'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

// Accounting's action badge counts submitted employee DTRs still awaiting verification.
$accountingDtrPending = 0;
if ($role === 'Accounting') {
    $accountingDtrPending = rtSafeCount($conn,
        "SELECT COUNT(*) AS c FROM dtr_submissions
         WHERE SiteSupervisorID IS NOT NULL
           AND (ReviewedBy IS NULL OR ReviewDate IS NULL)");
}

// Unread in-app notifications (bell icon).
$unreadTeam = rtSafeCount($conn, "SELECT COUNT(*) AS c FROM notifications WHERE UserID = {$userId} AND IsRead = 0 AND Type = 'deployed_team'");
$unreadJobOrders = rtSafeCount($conn, "SELECT COUNT(*) AS c FROM notifications WHERE UserID = {$userId} AND IsRead = 0 AND Type = 'job_order_request'");
$unreadEndorsed = rtSafeCount($conn, "SELECT COUNT(*) AS c FROM notifications WHERE UserID = {$userId} AND IsRead = 0 AND Type = 'endorsed_applicant'");

$notificationsUnread = rtSafeCount(
    $conn,
    "SELECT COUNT(*) AS c FROM notifications WHERE UserID = {$userId} AND IsRead = 0"
);

// Admin + HR workforce workflow counts.
$clientsWithoutAgreement = 0;
$serviceAgreementsExpiring = 0;
$payrollPending = 0;
$applicantReviewPending = 0;
$applicantContractsPending = 0;
$employeeConversionReady = 0;

$isAdmin = strcasecmp($role, 'Admin') === 0;
$isHrOrAdmin = $isAdmin || strcasecmp($role, 'HR') === 0;

if ($isAdmin) {
    $clientsWithoutAgreement = rtSafeCount(
        $conn,
        "SELECT COUNT(*) AS c
         FROM clientcompany c
         WHERE NOT EXISTS (
             SELECT 1
             FROM serviceagreement sa
             WHERE sa.ClientCompanyID = c.CompanyID
               AND sa.Status = 'Active'
               AND COALESCE(NULLIF(TRIM(sa.SignedDocumentPath), ''), '') <> ''
         )"
    );

    $serviceAgreementsExpiring = rtSafeCount(
        $conn,
        "SELECT COUNT(*) AS c
         FROM serviceagreement sa
         WHERE sa.Status = 'Active'
           AND sa.ContractEndDate IS NOT NULL
           AND sa.ContractEndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)"
    );

    $payrollPending = rtSafeCount(
        $conn,
        "SELECT COUNT(*) AS c
         FROM payroll_batches pb
         INNER JOIN status s ON pb.StatusID = s.StatusID
         WHERE s.Name = 'For Approval'"
    );
}

if ($isHrOrAdmin) {
    // New applications and interview queue — HR must review or advance.
    $applicantReviewPending = rtSafeCount(
        $conn,
        "SELECT COUNT(*) AS c
         FROM job_applications ja
         LEFT JOIN status s ON ja.StatusID = s.StatusID
         WHERE ja.HiredDate IS NULL
           AND ((ja.ClientApprovalStatus NOT IN ('Approved', 'Rejected')
           AND ja.EndorsedDate IS NULL
           AND (ja.StatusID IS NULL OR s.Name IN ('Pending','For Interview')))
           OR (ja.ClientApprovalStatus='Rejected' AND ja.EndorsedDate IS NOT NULL
           AND COALESCE(s.Name,'') NOT IN ('Rejected','Declined','Failed')))"
    );

    // Client-approved, not hired, contract file still missing.
    $applicantContractsPending = rtSafeCount(
        $conn,
        "SELECT COUNT(*) AS c
         FROM job_applications ja
         WHERE ja.ClientApprovalStatus = 'Approved'
           AND ja.HiredDate IS NULL
           AND (ja.Remarks IS NULL OR ja.Remarks NOT LIKE '%Contract uploaded:%')"
    );

    // Client-approved with contract on file — ready for hire/conversion.
    $employeeConversionReady = rtSafeCount(
        $conn,
        "SELECT COUNT(*) AS c
         FROM job_applications ja
         WHERE ja.ClientApprovalStatus = 'Approved'
           AND ja.HiredDate IS NULL
           AND ja.Remarks LIKE '%Contract uploaded:%'"
    );
}

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'role' => $role,
    'counts' => [
        'messages' => $unreadMessages,
        'accounting_dtr_pending' => $accountingDtrPending,
        'job_orders' => $jobOrdersCount,
        'job_orders_rejected' => $jobOrdersRejectedCount,
        'job_orders_updates' => $jobOrdersCount + $jobOrdersRejectedCount,
        'endorsed_pending' => $endorsedPendingCount,
        'attendance_absent_today' => $attendanceAbsentToday,
        'job_orders_to_post' => $jobOrdersCount,
        'clients_without_agreement' => $clientsWithoutAgreement,
        'service_agreements_expiring' => $serviceAgreementsExpiring,
        'payroll_pending' => $payrollPending,
        'applicant_review_pending' => $applicantReviewPending,
        'applicant_contracts_pending' => $applicantContractsPending,
        'employee_conversion_ready' => $employeeConversionReady,
        'notifications_unread' => $notificationsUnread,
        'unread_team' => $unreadTeam,
        'unread_job_orders' => $unreadJobOrders,
        'unread_endorsed' => $unreadEndorsed,
    ],
]);
exit();

