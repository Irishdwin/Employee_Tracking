<?php
/**
 * Returns the logged-in user's profile (role-specific fields included).
 */
require_once __DIR__ . '/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$role = trim((string)$_SESSION['role']);

function profile_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return 'U';
    }
    $a = mb_substr($parts[0], 0, 1);
    $b = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return strtoupper($a . $b) ?: 'U';
}

function profile_role_label(string $role): string
{
    $map = [
        'Admin' => 'Administrator',
        'HR' => 'HR Officer',
        'Accounting' => 'Accounting Staff',
        'TeamLead' => 'Team Lead',
        'Employee' => 'Employee',
        'Applicant' => 'Applicant',
    ];
    return $map[$role] ?? $role;
}

function table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    $ok = ($res && $res->num_rows > 0);
    if ($res) {
        $res->free();
    }
    return $ok;
}

$stmt = $conn->prepare("
    SELECT
        u.UsersID AS user_id,
        u.FullName AS full_name,
        u.Email AS email,
        u.IsActive AS is_active,
        u.CreatedAt AS created_at,
        r.RoleName AS role
    FROM users u
    INNER JOIN roles r ON u.RoleID = r.RolesID
    WHERE u.UsersID = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Keep session in sync with database.
$_SESSION['full_name'] = (string)$row['full_name'];
$_SESSION['email'] = (string)$row['email'];
$_SESSION['role'] = (string)$row['role'];
$role = (string)$row['role'];

$user = [
    'user_id' => (int)$row['user_id'],
    'full_name' => (string)$row['full_name'],
    'email' => (string)$row['email'],
    'role' => (string)$row['role'],
    'role_label' => profile_role_label((string)$row['role']),
    'initials' => profile_initials((string)$row['full_name']),
    'is_active' => ((int)$row['is_active'] === 1),
    'created_at' => $row['created_at'],
    'mobile' => '',
    'address' => '',
    'birth_date' => '',
    'home_location' => '',
    'position_title' => '',
    'company_name' => '',
    'client_name' => '',
    'location' => '',
    'employee_number' => '',
    'employee_id' => null,
    'assigned_companies' => [],
    'subtitle' => profile_role_label((string)$row['role']),
];

// Extended profile from admin user management (employee_profiles).
if (table_exists($conn, 'employee_profiles')) {
    $pStmt = $conn->prepare("
        SELECT client_name, full_name, birth_date, address, position_title
        FROM employee_profiles
        WHERE user_id = ?
        LIMIT 1
    ");
    if ($pStmt) {
        $pStmt->bind_param('i', $userId);
        $pStmt->execute();
        if ($p = $pStmt->get_result()->fetch_assoc()) {
            if (!empty($p['full_name'])) {
                $user['full_name'] = (string)$p['full_name'];
                $user['initials'] = profile_initials($user['full_name']);
            }
            $user['birth_date'] = (string)($p['birth_date'] ?? '');
            $user['address'] = (string)($p['address'] ?? '');
            $user['position_title'] = (string)($p['position_title'] ?? '');
            $user['client_name'] = (string)($p['client_name'] ?? '');
            $user['company_name'] = $user['client_name'];
        }
        $pStmt->close();
    }
}

// Applicant-specific profile.
if (strcasecmp($role, 'Applicant') === 0) {
    $aStmt = $conn->prepare("
        SELECT ApplicantID, FullName, FirstName, LastName, Email, MobileNumber,
               Address, BirthDate, HomeLocation, PreferredClassification,
               PreferredSubClassification, JobTitle, CompanyName
        FROM applicants
        WHERE UserID = ?
        LIMIT 1
    ");
    if ($aStmt) {
        $aStmt->bind_param('i', $userId);
        $aStmt->execute();
        if ($a = $aStmt->get_result()->fetch_assoc()) {
            if (!empty($a['FullName'])) {
                $user['full_name'] = (string)$a['FullName'];
                $user['initials'] = profile_initials($user['full_name']);
            }
            $user['mobile'] = (string)($a['MobileNumber'] ?? '');
            $user['address'] = (string)($a['Address'] ?? $a['HomeLocation'] ?? '');
            $user['home_location'] = (string)($a['HomeLocation'] ?? '');
            $user['birth_date'] = (string)($a['BirthDate'] ?? '');
            $user['position_title'] = (string)($a['JobTitle'] ?? $a['PreferredSubClassification'] ?? '');
            $user['company_name'] = (string)($a['CompanyName'] ?? '');
        }
        $aStmt->close();
    }
}

// Employee record + active deployment.
if (strcasecmp($role, 'Employee') === 0) {
    $eStmt = $conn->prepare("
        SELECT e.EmployeeID, e.EmployeeNumber, e.FirstName, e.LastName, e.Email, e.Position,
               d.Position AS deployment_position, d.Location AS deployment_location,
               c.Name AS deployment_company
        FROM users u
        LEFT JOIN employee e ON e.Email = u.Email
        LEFT JOIN deployment d ON d.EmployeeID = e.EmployeeID
            AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        LEFT JOIN clientcompany c ON c.CompanyID = d.CompanyID
        WHERE u.UsersID = ?
        ORDER BY d.StartDate DESC
        LIMIT 1
    ");
    if ($eStmt) {
        $eStmt->bind_param('i', $userId);
        $eStmt->execute();
        if ($e = $eStmt->get_result()->fetch_assoc()) {
            $user['employee_id'] = isset($e['EmployeeID']) ? (int)$e['EmployeeID'] : null;
            $user['employee_number'] = (string)($e['EmployeeNumber'] ?? '');
            $pos = trim((string)($e['deployment_position'] ?? $e['Position'] ?? ''));
            $company = trim((string)($e['deployment_company'] ?? ''));
            $user['position_title'] = $pos;
            $user['company_name'] = $company;
            $user['location'] = (string)($e['deployment_location'] ?? '');
            if ($pos !== '' && $company !== '') {
                $user['subtitle'] = $pos . ' — ' . $company;
            } elseif ($pos !== '') {
                $user['subtitle'] = $pos;
            }
        }
        $eStmt->close();
    }

    $appStmt = $conn->prepare("
        SELECT MobileNumber, Address, HomeLocation, BirthDate
        FROM applicants
        WHERE UserID = ?
        LIMIT 1
    ");
    if ($appStmt) {
        $appStmt->bind_param('i', $userId);
        $appStmt->execute();
        if ($ap = $appStmt->get_result()->fetch_assoc()) {
            if ($user['mobile'] === '') {
                $user['mobile'] = (string)($ap['MobileNumber'] ?? '');
            }
            if ($user['address'] === '') {
                $user['address'] = (string)($ap['Address'] ?? $ap['HomeLocation'] ?? '');
            }
            if ($user['home_location'] === '') {
                $user['home_location'] = (string)($ap['HomeLocation'] ?? '');
            }
            if ($user['birth_date'] === '') {
                $user['birth_date'] = (string)($ap['BirthDate'] ?? '');
            }
        }
        $appStmt->close();
    }
}

// Team Lead — companies assigned to this user account.
if (strcasecmp($role, 'TeamLead') === 0) {
    $tlStmt = $conn->prepare("
        SELECT CompanyID, Name, ContactPerson, ContactNumber, Industry, Address
        FROM clientcompany
        WHERE UserID = ?
        ORDER BY Name ASC
    ");
    if ($tlStmt) {
        $tlStmt->bind_param('i', $userId);
        $tlStmt->execute();
        $res = $tlStmt->get_result();
        $companies = [];
        while ($c = $res->fetch_assoc()) {
            $companies[] = [
                'company_id' => (int)$c['CompanyID'],
                'name' => (string)$c['Name'],
                'contact_person' => (string)($c['ContactPerson'] ?? ''),
                'contact_number' => (string)($c['ContactNumber'] ?? ''),
                'industry' => (string)($c['Industry'] ?? ''),
                'location' => (string)($c['Address'] ?? ''),
            ];
        }
        $tlStmt->close();
        $user['assigned_companies'] = $companies;
        if (!empty($companies)) {
            $user['company_name'] = (string)$companies[0]['name'];
            $user['client_name'] = $user['company_name'];
            $user['location'] = (string)($companies[0]['location'] ?? '');
            $user['subtitle'] = 'Team Lead — ' . $user['company_name'];
            if (count($companies) > 1) {
                $user['subtitle'] .= ' (+' . (count($companies) - 1) . ' more)';
            }
        } else {
            $user['subtitle'] = 'Team Lead';
        }
    }
}

// HR / Admin / Accounting subtitle polish.
if (in_array($role, ['Admin', 'HR', 'Accounting'], true)) {
    if ($user['position_title'] !== '' && $user['client_name'] !== '') {
        $user['subtitle'] = $user['position_title'] . ' — ' . $user['client_name'];
    } elseif ($user['position_title'] !== '') {
        $user['subtitle'] = $user['position_title'];
    } else {
        $user['subtitle'] = profile_role_label($role);
    }
}

echo json_encode([
    'success' => true,
    'user' => $user,
]);
