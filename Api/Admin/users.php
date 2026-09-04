<?php
require_once __DIR__ . '/../Common/session_init.php';

// Only Admin can access this API
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

header('Content-Type: application/json');

function json_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function validate_strong_password(string $password): ?string {
    if (strlen($password) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password)) return 'Password must include at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password)) return 'Password must include at least one lowercase letter.';
    if (!preg_match('/\d/', $password)) return 'Password must include at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return 'Password must include at least one special character.';
    return null;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['user_id'])) {
        $userId = (int)$_GET['user_id'];
        if ($userId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid user ID.'], 400);
        }

        $stmt = $conn->prepare(
            "SELECT 
                u.UsersID AS user_id,
                u.Email AS username,
                u.Email AS email,
                r.RoleName AS role,
                u.FullName AS full_name,
                CASE WHEN u.IsActive = 1 THEN 'Active' ELSE 'Inactive' END AS account_status,
                u.CreatedAt AS created_at,
                u.LastLogin AS last_login
             FROM users u
             INNER JOIN roles r ON u.RoleID = r.RolesID
             WHERE u.UsersID = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            json_response(['success' => false, 'message' => 'User not found.'], 404);
        }

        $applicant = null;
        $appStmt = $conn->prepare("SELECT * FROM applicants WHERE UserID = ? LIMIT 1");
        $appStmt->bind_param("i", $userId);
        $appStmt->execute();
        $applicant = $appStmt->get_result()->fetch_assoc();
        $appStmt->close();

        $employee = null;
        $userEmail = $user['email'];
        $empStmt = $conn->prepare("SELECT * FROM employee WHERE LOWER(TRIM(Email)) = LOWER(TRIM(?)) LIMIT 1");
        $empStmt->bind_param("s", $userEmail);
        $empStmt->execute();
        $employee = $empStmt->get_result()->fetch_assoc();
        $empStmt->close();

        $employeeId = $employee ? (int)$employee['EmployeeID'] : 0;
        $applicantId = $applicant ? (int)$applicant['ApplicantID'] : 0;

        $deployment = null;
        if ($employeeId > 0) {
            $depStmt = $conn->prepare(
                "SELECT d.*, c.Name AS client_name, c.Address AS client_address 
                 FROM deployment d 
                 LEFT JOIN clientcompany c ON d.CompanyID = c.CompanyID 
                 WHERE d.EmployeeID = ? 
                 ORDER BY d.DeploymentID DESC LIMIT 1"
            );
            $depStmt->bind_param("i", $employeeId);
            $depStmt->execute();
            $deployment = $depStmt->get_result()->fetch_assoc();
            $depStmt->close();
        }

        $salary = null;
        if ($employeeId > 0) {
            $salStmt = $conn->prepare(
                "SELECT * FROM salary_rates WHERE EmployeeID = ? ORDER BY SalaryRatesID DESC LIMIT 1"
            );
            $salStmt->bind_param("i", $employeeId);
            $salStmt->execute();
            $salary = $salStmt->get_result()->fetch_assoc();
            $salStmt->close();
        }

        $recruitment = null;
        if ($applicantId > 0) {
            $recStmt = $conn->prepare(
                "SELECT ja.*, jo.Position AS job_position 
                 FROM job_applications ja 
                 LEFT JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID 
                 WHERE ja.ApplicantID = ? 
                 ORDER BY ja.ApplicationID DESC LIMIT 1"
            );
            $recStmt->bind_param("i", $applicantId);
            $recStmt->execute();
            $recruitment = $recStmt->get_result()->fetch_assoc();
            $recStmt->close();
        }

        $documents = [];
        if ($applicantId > 0) {
            $docStmt = $conn->prepare(
                "SELECT DocType, FilePath, OriginalFileName FROM applicant_uploaded_docs WHERE ApplicantID = ?"
            );
            $docStmt->bind_param("i", $applicantId);
            $docStmt->execute();
            $docRes = $docStmt->get_result();
            while ($row = $docRes->fetch_assoc()) {
                $documents[] = $row;
            }
            $docStmt->close();
        }

        $payroll = null;
        if ($employeeId > 0) {
            $payStmt = $conn->prepare(
                "SELECT * FROM payroll WHERE EmployeeID = ? ORDER BY Payroll_ID DESC LIMIT 1"
            );
            $payStmt->bind_param("i", $employeeId);
            $payStmt->execute();
            $payroll = $payStmt->get_result()->fetch_assoc();
            $payStmt->close();
        }

        json_response([
            'success'     => true,
            'user'        => $user,
            'applicant'   => $applicant,
            'employee'    => $employee,
            'deployment'  => $deployment,
            'salary'      => $salary,
            'recruitment' => $recruitment,
            'documents'   => $documents,
            'payroll'     => $payroll
        ]);
    }

    $sql = "SELECT 
                u.UsersID AS user_id,
                u.Email AS username,
                r.RoleName AS role,
                u.FullName AS full_name,
                CASE WHEN u.IsActive = 1 THEN 'Active' ELSE 'Inactive' END AS status,
                u.CreatedAt AS created_at
            FROM users u
            INNER JOIN roles r ON u.RoleID = r.RolesID
            ORDER BY u.CreatedAt DESC";

    $result = $GLOBALS['conn']->query($sql);
    $users = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->free();
    }

    json_response([
        'users'     => $users,
        'adminName' => $_SESSION['full_name'] ?? 'Administrator',
    ]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $username   = trim($_POST['username'] ?? '');
        $fullName   = trim($_POST['full_name'] ?? '');
        $role       = trim($_POST['role'] ?? '');
        $status     = trim($_POST['status'] ?? 'Active');
        $password   = $_POST['password'] ?? '';

        if ($username === '' || $fullName === '' || $role === '' || $password === '') {
            json_response(['success' => false, 'message' => 'All fields are required.'], 400);
        }
        $passwordPolicyError = validate_strong_password((string)$password);
        if ($passwordPolicyError !== null) {
            json_response(['success' => false, 'message' => $passwordPolicyError], 400);
        }

        $allowedRoles = ['Admin','HR','Accounting','TeamLead','Employee','Applicant'];
        if (!in_array($role, $allowedRoles, true)) {
            json_response(['success' => false, 'message' => 'Invalid role selected.'], 400);
        }

        $stmt = $conn->prepare("SELECT UsersID FROM users WHERE Email = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            json_response(['success' => false, 'message' => 'Username already exists.'], 409);
        }
        $stmt->close();

        $roleStmt = $conn->prepare("SELECT RolesID FROM roles WHERE RoleName = ? LIMIT 1");
        $roleStmt->bind_param("s", $role);
        $roleStmt->execute();
        $roleRes = $roleStmt->get_result();
        $roleRow = $roleRes->fetch_assoc();
        $roleStmt->close();

        if (!$roleRow) {
            json_response(['success' => false, 'message' => 'Role not found in roles table.'], 400);
        }

        $roleId   = (int)$roleRow['RolesID'];
        $isActive = $status === 'Active' ? 1 : 0;
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $insert = $conn->prepare(
            "INSERT INTO users (RoleID, FullName, Email, Password, IsActive) VALUES (?, ?, ?, ?, ?)"
        );
        $insert->bind_param("isssi", $roleId, $fullName, $username, $hashedPassword, $isActive);

        if ($insert->execute()) {
            $newId = $insert->insert_id;
            $insert->close();

            AuditLogger::log($conn, 'Auth', 'Create User', "Administrator created new user '{$fullName}' ({$role}).", (string)$newId);

            json_response(['success' => true, 'message' => 'User created successfully.', 'user_id' => $newId]);
        }

        $error = $conn->error;
        $insert->close();
        json_response(['success' => false, 'message' => 'Database error: ' . $error], 500);
    }

    if ($action === 'update_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if ($userId <= 0 || ($status !== 'Active' && $status !== 'Inactive')) {
            json_response(['success' => false, 'message' => 'Invalid data.'], 400);
        }

        $isActive = $status === 'Active' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET IsActive = ? WHERE UsersID = ?");
        $stmt->bind_param("ii", $isActive, $userId);

        if ($stmt->execute()) {
            $stmt->close();

            AuditLogger::log($conn, 'Auth', 'Update User Status', "Account status for User #{$userId} set to {$status}.", (string)$userId);

            json_response(['success' => true, 'message' => 'Status updated.']);
        }

        $error = $conn->error;
        $stmt->close();
        json_response(['success' => false, 'message' => 'Database error: ' . $error], 500);
    }

    if ($action === 'update') {
        $userId   = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = trim($_POST['role'] ?? '');
        $status   = trim($_POST['status'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($userId <= 0 || $fullName === '' || $role === '' || $status === '') {
            json_response(['success' => false, 'message' => 'All fields are required.'], 400);
        }

        $allowedRoles = ['Admin','HR','Accounting','TeamLead','Employee','Applicant'];
        if (!in_array($role, $allowedRoles, true)) {
            json_response(['success' => false, 'message' => 'Invalid role selected.'], 400);
        }

        if ($status !== 'Active' && $status !== 'Inactive') {
            json_response(['success' => false, 'message' => 'Invalid status.'], 400);
        }

        $roleStmt = $conn->prepare("SELECT RolesID FROM roles WHERE RoleName = ? LIMIT 1");
        $roleStmt->bind_param("s", $role);
        $roleStmt->execute();
        $roleRes = $roleStmt->get_result();
        $roleRow = $roleRes->fetch_assoc();
        $roleStmt->close();

        if (!$roleRow) {
            json_response(['success' => false, 'message' => 'Role not found in roles table.'], 400);
        }

        $roleId   = (int)$roleRow['RolesID'];
        $isActive = $status === 'Active' ? 1 : 0;

        if ($password !== '') {
            $passwordPolicyError = validate_strong_password((string)$password);
            if ($passwordPolicyError !== null) {
                json_response(['success' => false, 'message' => $passwordPolicyError], 400);
            }
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE users SET FullName = ?, RoleID = ?, IsActive = ?, Password = ? WHERE UsersID = ?"
            );
            $stmt->bind_param("siisi", $fullName, $roleId, $isActive, $hashedPassword, $userId);
        } else {
            $stmt = $conn->prepare(
                "UPDATE users SET FullName = ?, RoleID = ?, IsActive = ? WHERE UsersID = ?"
            );
            $stmt->bind_param("siii", $fullName, $roleId, $isActive, $userId);
        }

        if ($stmt->execute()) {
            $stmt->close();

            AuditLogger::log($conn, 'Auth', 'Update User', "Updated user details for '{$fullName}' (Role: {$role}, Status: {$status}).", (string)$userId);

            json_response(['success' => true, 'message' => 'User updated successfully.']);
        }

        $error = $conn->error;
        $stmt->close();
        json_response(['success' => false, 'message' => 'Database error: ' . $error], 500);
    }

    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid user ID.'], 400);
        }

        $inactive = 0;
        $stmt = $conn->prepare("UPDATE users SET IsActive = ? WHERE UsersID = ?");
        $stmt->bind_param("ii", $inactive, $userId);

        if ($stmt->execute()) {
            $stmt->close();

            AuditLogger::log($conn, 'Auth', 'Deactivate User', "Deactivated user account (ID: {$userId}).", (string)$userId);

            json_response(['success' => true, 'message' => 'User marked as inactive.']);
        }

        $error = $conn->error;
        $stmt->close();
        json_response(['success' => false, 'message' => 'Database error: ' . $error], 500);
    }

    json_response(['success' => false, 'message' => 'Unsupported action.'], 400);
}

json_response(['error' => 'Method not allowed'], 405);