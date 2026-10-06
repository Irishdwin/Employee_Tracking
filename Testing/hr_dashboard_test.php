<?php
/** Read-only integration tests. Run with a local PHP server on port 8894. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../Api/Db_connection/db_connect.php';
require __DIR__.'/../Api/Helpers/application_status_helper.php';
$base=$argv[1] ?? 'http://127.0.0.1:8894'; $passed=0; $sessions=[];
function check($condition,$label) { global $passed; if(!$condition) throw new RuntimeException('FAIL '.$label); echo "PASS $label\n"; ++$passed; }
function sessionFor($uid,$role,$old=false) {
    global $sessions;
    $id='hrtest'.bin2hex(random_bytes(12));
    session_id($id); session_start(); $_SESSION=['user_id'=>$uid,'role'=>$role,'last_activity'=>time()-($old ? 1000 : 0)]; session_write_close(); $sessions[]=$id; return $id;
}
function request($path,$sid=null,$method='GET') {
    global $base;
    $opts=['http'=>['method'=>$method,'ignore_errors'=>true,'header'=>$sid ? "Cookie: PHPSESSID=$sid\r\n" : '']];
    $body=file_get_contents($base.$path,false,stream_context_create($opts));
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$m);
    return [(int)$m[1],json_decode($body,true),$body,$http_response_header];
}
ini_set('session.use_cookies','0'); ini_set('session.cache_limiter','');
try {
    $hr=(int)$conn->query("SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE r.RoleName='HR' AND u.IsActive=1 LIMIT 1")->fetch_assoc()['UsersID'];
    $sid=sessionFor($hr,'HR'); $url='/Api/HR/hr_dashboard.php';
    check(request($url)[0]===401,'Unauthenticated request rejected');
    foreach(['Admin','Accounting','TeamLead','Employee','Applicant'] as $role) check(request($url,sessionFor($hr,$role))[0]===403,"$role rejected");
    check(request($url,sessionFor(1,'HR'))[0]===403,'Forged HR session with Admin database role rejected');
    check(request($url,sessionFor(2147483647,'HR'))[0]===403,'Missing HR account rejected');
    check(request($url,sessionFor($hr,'HR',true))[0]===401,'Expired session rejected');
    check(request($url,$sid,'POST')[0]===405,'Mutation method rejected');
    [$code,$result,$raw,$headers]=request($url,$sid);
    check($code===200 && $result['success'],'HR summary loads'); $d=$result['data']; $m=$d['metrics'];
    check(in_array('Cache-Control: no-store',$headers),'Sensitive response not cached');
    $queries=[
      'total'=>'SELECT COUNT(*) n FROM employee','active'=>'SELECT COUNT(*) n FROM employee WHERE IsActive=1',
      'inactive'=>'SELECT COUNT(*) n FROM employee WHERE IsActive=0',
      'awol'=>"SELECT COUNT(*) n FROM employee e JOIN status s ON s.StatusID=e.StatusID WHERE LOWER(TRIM(s.Name))='awol'",
      'resigned'=>"SELECT COUNT(*) n FROM employee e LEFT JOIN status s ON s.StatusID=e.StatusID WHERE LOWER(TRIM(s.Name))='resigned' OR e.EmployeeID IN (SELECT rr.EmployeeID FROM resignationrequest rr JOIN status rs ON rs.StatusID=rr.StatusID WHERE LOWER(TRIM(rs.Name))='resigned')",
      'recent_hires'=>'SELECT COUNT(*) n FROM employee WHERE HiredDate>=CURDATE()-INTERVAL 29 DAY AND HiredDate<=CURDATE()',
      'deployed'=>'SELECT COUNT(DISTINCT e.EmployeeID) n FROM employee e JOIN deployment d ON d.EmployeeID=e.EmployeeID WHERE e.IsActive=1 AND d.StartDate<=CURDATE() AND (d.EndDate IS NULL OR d.EndDate>=CURDATE())',
      'applicants'=>'SELECT COUNT(*) n FROM applicants','applications'=>'SELECT COUNT(*) n FROM job_applications',
      'open_orders'=>"SELECT COUNT(*) n FROM joborder WHERE Status='Open'",'pending_orders'=>"SELECT COUNT(*) n FROM job_order_requests WHERE Status='Pending'",
      'notifications'=>"SELECT COUNT(*) n FROM notifications WHERE UserID=$hr AND COALESCE(IsRead,0)=0",
    ];
    foreach($queries as $key=>$sql) check($m[$key]===(int)$conn->query($sql)->fetch_assoc()['n'],"$key counter matches database");
    $stages=[]; foreach($conn->query('SELECT a.*,s.Name StatusFromTable FROM job_applications a LEFT JOIN status s ON s.StatusID=a.StatusID')->fetch_all(MYSQLI_ASSOC) as $row) { $label=ash_stage_display_label(ash_resolve_stage($row)); $stages[$label]=($stages[$label] ?? 0)+1; }
    foreach($d['recruitment'] as $row) check($row['count']===($stages[$row['label']] ?? 0),'Recruitment stage '.$row['label']);
    check($m['pending']===($stages['Applied'] ?? 0),'Pending initial review matches canonical workflow');
    check(array_sum(array_column($d['deployments'],'count'))===$m['deployed'],'Deployment chart counts distinct current employees');
    check(array_sum(array_column($d['dtr'],'count'))===(int)$conn->query('SELECT COUNT(*) n FROM dtr_submissions WHERE CreatedAt>=CURDATE()-INTERVAL 29 DAY AND CreatedAt<CURDATE()+INTERVAL 1 DAY')->fetch_assoc()['n'],'DTR chart uses actual submission records');
    check($d['attendance']['clocked_in']===(int)$conn->query('SELECT COUNT(DISTINCT e.EmployeeID) n FROM daily_time_records t JOIN employee e ON e.EmployeeID=t.employee_id WHERE e.IsActive=1 AND t.date=CURDATE() AND t.time_in IS NOT NULL')->fetch_assoc()['n'],'Attendance uses recorded clock-ins');
    $known=[];
    foreach($conn->query("SELECT CONCAT('Application received · ',COALESCE(j.Position,'Job order')) label,a.ApplicationDate happened_at FROM job_applications a LEFT JOIN joborder j ON j.JobOrderID=a.JobOrderID UNION ALL SELECT CONCAT('Employee hired · ',COALESCE(Position,'Employee')),CAST(HiredDate AS DATETIME) FROM employee UNION ALL SELECT CONCAT('Job order created · ',Position),CreatedAt FROM joborder UNION ALL SELECT CONCAT('HR · ',module,' · ',action),created_at FROM system_logs WHERE role='HR' AND module<>'Auth'")->fetch_all(MYSQLI_ASSOC) as $row) $known[$row['label'].'|'.$row['happened_at']]=true;
    check(count($d['activities'])<=10,'Activity list bounded');
    foreach($d['activities'] as $row) check(isset($known[$row['label'].'|'.$row['happened_at']]),'Activity traceable to source record');
    foreach(['total','active','inactive','awol','resigned','recent_hires','deployed'] as $filter) {
       [$status,$roster]=request($url.'?action=employees&filter='.$filter,$sid);
       check($status===200 && $roster['data']['total']===$m[$filter],"$filter drilldown matches card");
       foreach($roster['data']['rows'] as $row) check(!array_diff(array_keys($row),['EmployeeNumber','FirstName','LastName','Position','Status','IsActive','HiredDate']),'Roster fields limited to HR overview');
    }
    foreach(['?action=unknown','?action=employees&filter=unknown','?action=employees&page=0','?action=employees&page[]=1','?action=employees&filter[]=active'] as $query) check(request($url.$query,$sid)[0]===400,'Invalid dashboard input rejected');
    check(request($url,$sid)[0]===200,'Refresh returns live summary');
    [$status,$counters]=request('/Api/Common/realtime_counters.php',$sid);
    check($status===200 && $counters['logged_in'],'Existing HR sidebar counter API loads');
    $badgeQueries=[
        'job_orders'=>"SELECT COUNT(*) n FROM job_order_requests WHERE Status='Pending'",
        'applicant_review_pending'=>"SELECT COUNT(*) n FROM job_applications a LEFT JOIN status s ON s.StatusID=a.StatusID WHERE a.HiredDate IS NULL AND a.ClientApprovalStatus NOT IN ('Approved','Rejected') AND a.EndorsedDate IS NULL AND (a.StatusID IS NULL OR s.Name='For Interview')",
        'applicant_contracts_pending'=>"SELECT COUNT(*) n FROM job_applications WHERE ClientApprovalStatus='Approved' AND HiredDate IS NULL AND (Remarks IS NULL OR Remarks NOT LIKE '%Contract uploaded:%')",
        'employee_conversion_ready'=>"SELECT COUNT(*) n FROM job_applications WHERE ClientApprovalStatus='Approved' AND HiredDate IS NULL AND Remarks LIKE '%Contract uploaded:%'",
        'notifications_unread'=>"SELECT COUNT(*) n FROM notifications WHERE UserID=$hr AND IsRead=0",
    ];
    foreach($badgeQueries as $key=>$sql) check($counters['counts'][$key]===(int)$conn->query($sql)->fetch_assoc()['n'],"Sidebar/bell $key uses actual existing workflow records");
    [$status,$notifications]=request('/Api/Common/notifications.php',$sid);
    check($status===200 && $notifications['success'],'Existing notification list API loads');
    check(request('/Html/HR/hr_dashboard.html')[0]===200,'HR HTML loads');
    foreach(['job_orders.php','job_order_requests.php','applicant_review.php','deployments.php'] as $endpoint) {
        [$status,$body]=request('/Api/HR/'.$endpoint,$sid); check($status===200 && is_array($body),'Existing HR read API '.$endpoint);
    }
    echo "TOTAL $passed PASS\n";
} finally {
    foreach($sessions as $id) { $path=session_save_path().'/sess_'.$id; if(is_file($path)) unlink($path); }
}
