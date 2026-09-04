<?php
/**
 * One-time script to create test accounts for Applicant, Employee, TeamLead, Accounting
 * Run via browser: http://localhost/Employee_Tracking/Api/Admin/create_test_accounts.php
 * Then delete or secure this file.
 */

require __DIR__ . '/../Db_connection/db_connect.php';

$testPassword = 'Test@123';
$results = [];
$errors = [];

function ensureRole($conn, $roleName) {
    $st = $conn->prepare("SELECT RolesID FROM roles WHERE RoleName = ? LIMIT 1");
    $st->bind_param("s", $roleName);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    if ($r) return (int)$r['RolesID'];
    $conn->query("INSERT INTO roles (RoleName, Description) VALUES ('" . $conn->real_escape_string($roleName) . "', 'Test role')");
    return $conn->insert_id;
}

function createUser($conn, $roleId, $email, $fullName, $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $check = $conn->prepare("SELECT UsersID FROM users WHERE Email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        return null;
    }
    $check->close();
    $ins = $conn->prepare("INSERT INTO users (RoleID, FullName, Email, Password, IsActive) VALUES (?, ?, ?, ?, 1)");
    $ins->bind_param("isss", $roleId, $fullName, $email, $hash);
    $ins->execute();
    $uid = $conn->insert_id;
    $ins->close();
    return $uid;
}

// 1. Accounting
$roleId = ensureRole($conn, 'Accounting');
$uid = createUser($conn, $roleId, 'accounting@onazul.com', 'Accounting User', $testPassword);
if ($uid) {
    $results[] = ['role' => 'Accounting', 'email' => 'accounting@onazul.com', 'created' => true];
} else {
    $results[] = ['role' => 'Accounting', 'email' => 'accounting@onazul.com', 'created' => false, 'note' => 'Already exists'];
}

// 2. TeamLead
$roleId = ensureRole($conn, 'TeamLead');
$uid = createUser($conn, $roleId, 'teamlead@onazul.com', 'Team Lead User', $testPassword);
if ($uid) {
    $results[] = ['role' => 'TeamLead', 'email' => 'teamlead@onazul.com', 'created' => true];
} else {
    $results[] = ['role' => 'TeamLead', 'email' => 'teamlead@onazul.com', 'created' => false, 'note' => 'Already exists'];
}

// 3. Applicant (needs applicants record)
$roleId = ensureRole($conn, 'Applicant');
$uid = createUser($conn, $roleId, 'applicant@onazul.com', 'Applicant User', $testPassword);
if ($uid) {
    $chk = $conn->prepare("SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1");
    $chk->bind_param("i", $uid);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        $insApp = $conn->prepare("INSERT INTO applicants (UserID, FullName, Email) VALUES (?, 'Applicant User', 'applicant@onazul.com')");
        $insApp->bind_param("i", $uid);
        $insApp->execute();
        $insApp->close();
    }
    $chk->close();
    $results[] = ['role' => 'Applicant', 'email' => 'applicant@onazul.com', 'created' => true];
} else {
    $results[] = ['role' => 'Applicant', 'email' => 'applicant@onazul.com', 'created' => false, 'note' => 'Already exists'];
}

// 4. Employee (needs employee record - email must match)
$roleId = ensureRole($conn, 'Employee');
$uid = createUser($conn, $roleId, 'employee@onazul.com', 'Employee User', $testPassword);
if ($uid) {
    $chk = $conn->query("SELECT EmployeeID FROM employee WHERE Email = 'employee@onazul.com' LIMIT 1");
    if (!$chk || $chk->num_rows === 0) {
        $conn->query("INSERT INTO employee (FirstName, LastName, Email, EmployeeNumber, IsActive, BasicSalary, HiredDate) VALUES ('Employee', 'User', 'employee@onazul.com', 'EMP001', 1, 25000, CURDATE())");
    }
    $results[] = ['role' => 'Employee', 'email' => 'employee@onazul.com', 'created' => true];
} else {
    $results[] = ['role' => 'Employee', 'email' => 'employee@onazul.com', 'created' => false, 'note' => 'Already exists'];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Accounts Created</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fb; }
        .credentials-box { background: #e7f1ff; border-left: 4px solid #0056b3; padding: 1rem 1.5rem; border-radius: 0.5rem; }
        .table-creds { font-family: monospace; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <i class="bi bi-people-fill text-primary" style="font-size: 3rem;"></i>
                <h3 class="text-primary mb-2 mt-3">Test Accounts</h3>
                <p class="text-muted">Use these credentials to test each role. Password for all: <strong>Test@123</strong></p>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-creds">
                    <thead><tr><th>Role</th><th>Email (Username)</th><th>Password</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($r['role']) ?></span></td>
                            <td><code><?= htmlspecialchars($r['email']) ?></code></td>
                            <td><code><?= $testPassword ?></code></td>
                            <td><?= $r['created'] ? '<span class="text-success">Created</span>' : '<span class="text-muted">' . htmlspecialchars($r['note'] ?? 'Exists') . '</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-info mt-3">
                <strong>Note:</strong> For Employee to use GU Tagging/Leave/Dashboard, assign them to a deployment (HR). For TeamLead, set them as SiteSuperVisorID on a deployment.
            </div>
            <div class="text-center mt-4">
                <a href="../../index.html" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Go to Login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
