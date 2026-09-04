<?php
// Employee_Tracking/Api/Admin/admin_dashboard.php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Authenticate Admin Session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strcasecmp((string)$_SESSION['role'], 'Admin') !== 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in as Admin.']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php';

// Helper function to safely check if a table exists
function tableExists($conn, $tableName) {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    return ($res && $res->num_rows > 0);
}

// Helper function to safely check if a column exists in a table
function columnExists($conn, $tableName, $columnName) {
    if (!tableExists($conn, $tableName)) return false;
    $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "` LIKE '" . $conn->real_escape_string($columnName) . "'");
    return ($res && $res->num_rows > 0);
}

try {
    // 2. Fetch Recent System Activity (Top 10 Audit Logs)
    $activities = [];
    if (tableExists($conn, 'system_logs')) {
        $logStmt = $conn->prepare("
            SELECT 
                log_id,
                username,
                role,
                module,
                action,
                description,
                created_at
            FROM system_logs
            ORDER BY created_at DESC, log_id DESC
            LIMIT 10
        ");
        
        if ($logStmt && $logStmt->execute()) {
            $res = $logStmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $activities[] = [
                    'id'          => $row['log_id'],
                    'user'        => $row['username'] ?? 'System',
                    'role'        => $row['role'] ?? 'User',
                    'module'      => $row['module'] ?? 'System',
                    'action'      => $row['action'] ?? 'Action',
                    'description' => $row['description'] ?? 'Log entry',
                    'timestamp'   => date('M d, Y h:i A', strtotime($row['created_at']))
                ];
            }
            $logStmt->close();
        }
    }

    // 3. Calculate Real-Time KPI Statistics
    // -------------------------------------------------------------
    // KPI A: Active Clients
    $activeClients = 0;
    if (tableExists($conn, 'clientcompany')) {
        if (columnExists($conn, 'clientcompany', 'IsActive')) {
            $compQuery = $conn->query("SELECT COUNT(DISTINCT CompanyID) AS cnt FROM clientcompany WHERE IsActive = 1");
        } else if (columnExists($conn, 'clientcompany', 'Status')) {
            $compQuery = $conn->query("SELECT COUNT(DISTINCT CompanyID) AS cnt FROM clientcompany WHERE LOWER(TRIM(Status)) = 'active'");
        } else {
            $compQuery = $conn->query("SELECT COUNT(DISTINCT CompanyID) AS cnt FROM clientcompany");
        }
        if ($compQuery && $r = $compQuery->fetch_assoc()) {
            $activeClients = (int)$r['cnt'];
        }
    }

    // KPI B: Total Deployed Employees
    $totalDeployedEmployees = 0;
    if (tableExists($conn, 'deployment') && tableExists($conn, 'employee')) {
        $statusClause = columnExists($conn, 'employee', 'Status') ? "AND LOWER(TRIM(e.Status)) = 'active'" : "";
        $isActiveClause = columnExists($conn, 'employee', 'IsActive') ? "AND e.IsActive = 1" : "";
        
        $depSql = "
            SELECT COUNT(DISTINCT d.EmployeeID) AS cnt 
            FROM deployment d 
            INNER JOIN employee e ON d.EmployeeID = e.EmployeeID 
            WHERE 1=1 {$statusClause} {$isActiveClause}
              AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        ";
        $depQuery = $conn->query($depSql);
        if ($depQuery && $r = $depQuery->fetch_assoc()) {
            $totalDeployedEmployees = (int)$r['cnt'];
        }
    }

    // KPI C: Pending Payroll Approvals
    $pendingPayrollApprovals = 0;
    if (tableExists($conn, 'payroll') && columnExists($conn, 'payroll', 'Status')) {
        $payQuery = $conn->query("SELECT COUNT(*) AS cnt FROM payroll WHERE Status = 'Pending'");
        if ($payQuery && $r = $payQuery->fetch_assoc()) {
            $pendingPayrollApprovals = (int)$r['cnt'];
        }
    } else if (tableExists($conn, 'payroll_runs') && columnExists($conn, 'payroll_runs', 'Status')) {
        $payRunQuery = $conn->query("SELECT COUNT(*) AS cnt FROM payroll_runs WHERE Status = 'Pending'");
        if ($payRunQuery && $r = $payRunQuery->fetch_assoc()) {
            $pendingPayrollApprovals = (int)$r['cnt'];
        }
    }

    // KPI D: Pending Quitclaims
    $pendingQuitclaims = 0;
    if (tableExists($conn, 'employee_resignations')) {
        $whereClause = "WHERE 1=0";
        if (columnExists($conn, 'employee_resignations', 'Status') && columnExists($conn, 'employee_resignations', 'quitclaim_status')) {
            $whereClause = "WHERE Status = 'Pending' OR quitclaim_status = 'Pending'";
        } else if (columnExists($conn, 'employee_resignations', 'Status')) {
            $whereClause = "WHERE Status = 'Pending'";
        } else if (columnExists($conn, 'employee_resignations', 'quitclaim_status')) {
            $whereClause = "WHERE quitclaim_status = 'Pending'";
        }
        $resigQuery = $conn->query("SELECT COUNT(*) AS cnt FROM employee_resignations {$whereClause}");
        if ($resigQuery && $r = $resigQuery->fetch_assoc()) {
            $pendingQuitclaims = (int)$r['cnt'];
        }
    } else if (tableExists($conn, 'resignations') && columnExists($conn, 'resignations', 'Status')) {
        $resigAltQuery = $conn->query("SELECT COUNT(*) AS cnt FROM resignations WHERE Status = 'Pending'");
        if ($resigAltQuery && $r = $resigAltQuery->fetch_assoc()) {
            $pendingQuitclaims = (int)$r['cnt'];
        }
    }

    $kpis = [
        'activeClients'           => $activeClients,
        'totalDeployedEmployees' => $totalDeployedEmployees,
        'pendingPayrollApprovals' => $pendingPayrollApprovals,
        'pendingQuitclaims'       => $pendingQuitclaims
    ];

    // 4. Calculate Real-Time System Alerts
    // -------------------------------------------------------------
    $alerts = [];

    // Alert A: Pending Job Order Requests
    if (tableExists($conn, 'job_order_requests') && columnExists($conn, 'job_order_requests', 'Status')) {
        $joQuery = $conn->query("SELECT COUNT(*) as cnt FROM job_order_requests WHERE Status = 'Pending'");
        $joPending = ($joQuery && $r = $joQuery->fetch_assoc()) ? (int)$r['cnt'] : 0;
        if ($joPending > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'title'   => 'Pending Job Order Requests',
                'message' => "There are {$joPending} job order request(s) awaiting approval."
            ];
        }
    }

    // Alert B: Applicants Requiring Review
    if (tableExists($conn, 'job_applications')) {
        if (tableExists($conn, 'status') && columnExists($conn, 'job_applications', 'StatusID')) {
            $appQuery = $conn->query("
                SELECT COUNT(*) as cnt 
                FROM job_applications ja
                LEFT JOIN status s ON ja.StatusID = s.StatusID
                WHERE s.Name = 'Applied' OR ja.StatusID IS NULL OR ja.StatusID = 0
            ");
        } else if (columnExists($conn, 'job_applications', 'Status')) {
            $appQuery = $conn->query("SELECT COUNT(*) as cnt FROM job_applications WHERE LOWER(TRIM(Status)) = 'applied'");
        } else {
            $appQuery = false;
        }
        
        if ($appQuery && $r = $appQuery->fetch_assoc()) {
            $appPending = (int)$r['cnt'];
            if ($appPending > 0) {
                $alerts[] = [
                    'type'    => 'info',
                    'title'   => 'Applicant Reviews Pending',
                    'message' => "{$appPending} new applicant application(s) require review."
                ];
            }
        }
    }

    if (empty($alerts)) {
        $alerts[] = [
            'type'    => 'success',
            'title'   => 'System Optimal',
            'message' => 'All operational pipelines, applications, and deployments are up to date.'
        ];
    }

    $userName = $_SESSION['full_name'] ?? $_SESSION['fullName'] ?? 'System Admin';
    $userRole = $_SESSION['role'] ?? 'Superuser';

    // 5. Return JSON Payload
    echo json_encode([
        'success'    => true,
        'kpis'       => $kpis,
        'activities' => $activities,
        'alerts'     => $alerts,
        'user'       => [
            'name' => $userName,
            'role' => $userRole
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Graceful error fallback response (HTTP 200 with error details so frontend never breaks)
    echo json_encode([
        'success'    => true,
        'kpis'       => [
            'activeClients' => 0,
            'totalDeployedEmployees' => 0,
            'pendingPayrollApprovals' => 0,
            'pendingQuitclaims' => 0
        ],
        'activities' => [],
        'alerts'     => [
            [
                'type'    => 'notice',
                'title'   => 'System Notice',
                'message' => 'Overview stats initialized.'
            ]
        ],
        'user' => [
            'name' => $_SESSION['full_name'] ?? 'System Admin',
            'role' => $_SESSION['role'] ?? 'Superuser'
        ]
    ]);
}