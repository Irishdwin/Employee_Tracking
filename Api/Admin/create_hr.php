<?php
/**
 * One-time script to create an HR account for Client Partnership phase
 * Run this file once via browser: http://localhost/Employee_Tracking/Api/Admin/create_hr.php
 * Then delete or secure this file.
 */

require __DIR__ . '/../Db_connection/db_connect.php';

// Default HR credentials (change these if needed)
$hrUsername = 'hr';      // will be stored in `Email`
$hrPassword = 'Hr@123';
$hrFullName = 'HR User';

// Resolve RoleID for 'HR' (auto-create role if missing)
$roleName = 'HR';
$roleStmt = $conn->prepare("SELECT RolesID FROM roles WHERE RoleName = ? LIMIT 1");
$roleStmt->bind_param("s", $roleName);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$roleRow = $roleRes->fetch_assoc();
$roleStmt->close();

if (!$roleRow) {
    // Create the HR role if it does not exist
    $desc = 'HR role (auto-created by create_hr.php)';
    $insertRole = $conn->prepare("INSERT INTO roles (RoleName, Description) VALUES (?, ?)");
    $insertRole->bind_param("ss", $roleName, $desc);
    if ($insertRole->execute()) {
        $roleId = (int)$insertRole->insert_id;
        $insertRole->close();
    } else {
        echo "<!DOCTYPE html><html><body><h3>Failed to create HR role: " . htmlspecialchars($conn->error) . "</h3></body></html>";
        exit();
    }
} else {
    $roleId = (int)$roleRow['RolesID'];
}

// Check if HR user already exists in `users`
$checkStmt = $conn->prepare("SELECT UsersID FROM users WHERE RoleID = ? LIMIT 1");
$checkStmt->bind_param("i", $roleId);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>HR Account Creation</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css' rel='stylesheet'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='card shadow-sm'>
                <div class='card-body p-5 text-center'>
                    <i class='bi bi-exclamation-triangle-fill text-warning' style='font-size: 4rem;'></i>
                    <h3 class='text-warning mb-3 mt-3'>HR Account Already Exists</h3>
                    <p class='text-muted'>An HR account with username <strong>{$hrUsername}</strong> already exists.</p>
                    <a href='../../index.html' class='btn btn-primary mt-3'>Go to Login Page</a>
                </div>
            </div>
        </div>
    </body>
    </html>";
    $checkStmt->close();
    exit();
}

$checkStmt->close();

$hashedPassword = password_hash($hrPassword, PASSWORD_DEFAULT);

// Insert HR user into `users`
$isActive = 1;
$insertStmt = $conn->prepare(
    "INSERT INTO users (RoleID, FullName, Email, Password, IsActive) VALUES (?, ?, ?, ?, ?)"
);
$insertStmt->bind_param("isssi", $roleId, $hrFullName, $hrUsername, $hashedPassword, $isActive);

if ($insertStmt->execute()) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>HR Account Created</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css' rel='stylesheet'>
        <style>
            body { background-color: #f5f7fb; }
            .success-card { max-width: 600px; margin: 50px auto; }
            .credentials-box { background: #e7f1ff; border-left: 4px solid #0056b3; padding: 1.5rem; border-radius: 0.5rem; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='card shadow-sm success-card'>
                <div class='card-body p-5'>
                    <div class='text-center mb-4'>
                        <i class='bi bi-check-circle-fill text-success' style='font-size: 4rem;'></i>
                        <h3 class='text-success mb-2 mt-3'>HR Account Created Successfully</h3>
                        <p class='text-muted'>HR can now log in to create Job Orders (Admin handles Client Companies and Service Agreements):</p>
                    </div>
                    <div class='credentials-box mb-4'>
                        <h6 class='fw-bold mb-3'>Login Credentials:</h6>
                        <table class='table table-borderless mb-0'>
                            <tr><td class='fw-semibold' style='width: 120px;'>Username:</td><td><code class='bg-white px-2 py-1 rounded'>{$hrUsername}</code></td></tr>
                            <tr><td class='fw-semibold'>Password:</td><td><code class='bg-white px-2 py-1 rounded'>{$hrPassword}</code></td></tr>
                            <tr><td class='fw-semibold'>Role:</td><td><span class='badge bg-info'>HR</span></td></tr>
                        </table>
                    </div>
                    <div class='text-center mt-4'>
                        <a href='../../index.html' class='btn btn-primary btn-lg'><i class='bi bi-box-arrow-in-right me-2'></i>Go to Login Page</a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
} else {
    echo "<!DOCTYPE html><html><body class='bg-light p-5'><div class='alert alert-danger'>Error: " . htmlspecialchars($conn->error) . "</div><a href='../../index.html' class='btn btn-secondary'>Back</a></body></html>";
}

$insertStmt->close();
$conn->close();
