<?php
/** Read-only HR overview. Dates use the shared connection's Manila timezone. */
require_once __DIR__ . '/application_status_helper.php';

function hrd_rows(mysqli $db, string $sql): array {
    return $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}
function hrd_filters(): array {
    $current = "e.IsActive=1 AND EXISTS (SELECT 1 FROM deployment d WHERE d.EmployeeID=e.EmployeeID AND d.StartDate<=CURDATE() AND (d.EndDate IS NULL OR d.EndDate>=CURDATE()))";
    return [
        'total' => '1=1', 'active' => 'e.IsActive=1', 'inactive' => 'e.IsActive=0',
        'awol' => "LOWER(TRIM(s.Name))='awol'",
        'resigned' => "(LOWER(TRIM(s.Name))='resigned' OR EXISTS (SELECT 1 FROM resignationrequest rr JOIN status rs ON rs.StatusID=rr.StatusID WHERE rr.EmployeeID=e.EmployeeID AND LOWER(TRIM(rs.Name))='resigned'))",
        'recent_hires' => 'e.HiredDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()',
        'deployed' => $current,
    ];
}
function hrd_summary(mysqli $db, int $userId): array {
    $metrics = [];
    foreach (hrd_filters() as $key => $where) {
        $metrics[$key] = (int)hrd_rows($db, "SELECT COUNT(*) n FROM employee e LEFT JOIN status s ON s.StatusID=e.StatusID WHERE $where")[0]['n'];
    }
    $metrics['applicants'] = (int)hrd_rows($db, 'SELECT COUNT(*) n FROM applicants')[0]['n'];
    $stages = array_fill_keys(['applied','scheduled_for_interview','endorsed_to_client','approved_by_company','hired','rejected','client_rejected'], 0);
    // Group workflow fields, then reuse the same resolver as Applicant Review.
    foreach (hrd_rows($db, "SELECT (a.HiredDate IS NOT NULL) HiredDate, (a.InterviewDate IS NOT NULL) InterviewDate, (a.EndorsedDate IS NOT NULL) EndorsedDate, a.ClientApprovalStatus, s.Name StatusFromTable, COUNT(*) n FROM job_applications a LEFT JOIN status s ON s.StatusID=a.StatusID GROUP BY (a.HiredDate IS NOT NULL), (a.InterviewDate IS NOT NULL), (a.EndorsedDate IS NOT NULL), a.ClientApprovalStatus, s.Name") as $row) {
        $stages[ash_resolve_stage($row)] += (int)$row['n'];
    }
    $metrics['pending'] = $stages['applied'];
    $metrics['applications'] = array_sum($stages);
    $metrics['open_orders'] = (int)hrd_rows($db, "SELECT COUNT(*) n FROM joborder WHERE Status='Open'")[0]['n'];
    $metrics['pending_orders'] = (int)hrd_rows($db, "SELECT COUNT(*) n FROM job_order_requests WHERE Status='Pending'")[0]['n'];
    $stmt = $db->prepare('SELECT COUNT(*) n FROM notifications WHERE UserID=? AND COALESCE(IsRead,0)=0');
    $stmt->bind_param('i', $userId); $stmt->execute();
    $metrics['notifications'] = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    $recruitment = [];
    foreach ($stages as $key => $count) {
        $label = ash_stage_display_label($key);
        $recruitment[] = ['label'=>$label, 'count'=>$count, 'filter'=>$key==='rejected' ? 'Rejected' : $label];
    }
    // Assign each currently deployed active employee to their latest current deployment.
    $deployments = hrd_rows($db, "SELECT c.Name label, COUNT(*) count FROM deployment d JOIN employee e ON e.EmployeeID=d.EmployeeID JOIN clientcompany c ON c.CompanyID=d.CompanyID WHERE e.IsActive=1 AND d.StartDate<=CURDATE() AND (d.EndDate IS NULL OR d.EndDate>=CURDATE()) AND NOT EXISTS (SELECT 1 FROM deployment newer WHERE newer.EmployeeID=d.EmployeeID AND newer.StartDate<=CURDATE() AND (newer.EndDate IS NULL OR newer.EndDate>=CURDATE()) AND (newer.StartDate>d.StartDate OR (newer.StartDate=d.StartDate AND newer.DeploymentID>d.DeploymentID))) GROUP BY c.CompanyID,c.Name ORDER BY count DESC,c.Name");
    $attendance = hrd_rows($db, "SELECT COUNT(DISTINCT e.EmployeeID) clocked_in, COUNT(DISTINCT CASE WHEN t.time_out IS NULL THEN e.EmployeeID END) open_shifts FROM daily_time_records t JOIN employee e ON e.EmployeeID=t.employee_id WHERE e.IsActive=1 AND t.date=CURDATE() AND t.time_in IS NOT NULL")[0];
    $dtr = hrd_rows($db, "SELECT COALESCE(s.Name,'Unspecified') label, COUNT(*) count FROM dtr_submissions d LEFT JOIN status s ON s.StatusID=d.StatusID WHERE d.CreatedAt>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) AND d.CreatedAt<DATE_ADD(CURDATE(),INTERVAL 1 DAY) GROUP BY s.Name ORDER BY count DESC");
    $activities = hrd_rows($db, "SELECT label, happened_at FROM (
        (SELECT CONCAT('Application received · ',COALESCE(j.Position,'Job order')) label,a.ApplicationDate happened_at FROM job_applications a LEFT JOIN joborder j ON j.JobOrderID=a.JobOrderID WHERE a.ApplicationDate<=NOW() ORDER BY a.ApplicationDate DESC LIMIT 10)
        UNION ALL (SELECT CONCAT('Employee hired · ',COALESCE(e.Position,'Employee')) label,CAST(e.HiredDate AS DATETIME) happened_at FROM employee e WHERE e.HiredDate<=CURDATE() ORDER BY e.HiredDate DESC LIMIT 10)
        UNION ALL (SELECT CONCAT('Job order created · ',j.Position) label,j.CreatedAt happened_at FROM joborder j WHERE j.CreatedAt<=NOW() ORDER BY j.CreatedAt DESC LIMIT 10)
        UNION ALL (SELECT CONCAT('HR · ',module,' · ',action) label,created_at happened_at FROM system_logs WHERE role='HR' AND module<>'Auth' AND created_at<=NOW() ORDER BY created_at DESC LIMIT 10)
    ) events ORDER BY happened_at DESC,label LIMIT 10");
    foreach ($deployments as &$row) $row['count'] = (int)$row['count']; unset($row);
    foreach ($dtr as &$row) $row['count'] = (int)$row['count']; unset($row);
    return ['metrics'=>$metrics, 'recruitment'=>$recruitment, 'deployments'=>$deployments,
        'attendance'=>array_map('intval',$attendance), 'dtr'=>$dtr, 'activities'=>$activities,
        'as_of'=>date('c'), 'date'=>date('Y-m-d'), 'timezone'=>'Asia/Manila'];
}

function hrd_employees(mysqli $db, string $filter, int $page): array {
    $where = hrd_filters()[$filter];
    $total = (int)hrd_rows($db,"SELECT COUNT(*) n FROM employee e LEFT JOIN status s ON s.StatusID=e.StatusID WHERE $where")[0]['n'];
    $page = min($page, max(1,(int)ceil($total/20)));
    $offset = ($page-1)*20;
    $stmt = $db->prepare("SELECT e.EmployeeNumber,e.FirstName,e.LastName,e.Position,COALESCE(s.Name,'Unspecified') Status,e.IsActive,e.HiredDate FROM employee e LEFT JOIN status s ON s.StatusID=e.StatusID WHERE $where ORDER BY e.LastName,e.FirstName,e.EmployeeID LIMIT 20 OFFSET ?");
    $stmt->bind_param('i',$offset); $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/20))];
}
