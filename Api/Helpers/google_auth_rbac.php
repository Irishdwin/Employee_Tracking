<?php
/**
 * Unified Google OAuth + RBAC session bootstrap for Onazul Workforce Suite.
 */

require_once __DIR__ . '/google_verify_id_token.php';

function google_oauth_exchange_code(string $code, string $redirectUri, array $cfg): array
{
    $clientId = isset($cfg['client_id']) ? (string)$cfg['client_id'] : '';
    $clientSecret = isset($cfg['client_secret']) ? (string)$cfg['client_secret'] : '';

    if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
        throw new RuntimeException('Google OAuth config incomplete.');
    }

    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postFields = http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ]);

    $tokenRespRaw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 25,
        ]);
        $tokenRespRaw = curl_exec($ch);
        if ($tokenRespRaw === false) {
            error_log('google_oauth CURL token: ' . curl_error($ch));
            $tokenRespRaw = null;
        }
        curl_close($ch);
    }

    if ($tokenRespRaw === null || $tokenRespRaw === '') {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postFields,
                'timeout' => 20,
            ],
        ]);
        $tokenRespRaw = @file_get_contents($tokenUrl, false, $context);
    }

    if (!$tokenRespRaw) {
        throw new RuntimeException('Failed to exchange code with Google.');
    }

    $tokenResp = json_decode($tokenRespRaw, true);
    if (!$tokenResp) {
        throw new RuntimeException('Invalid response from Google token endpoint.');
    }

    if (!empty($tokenResp['error'])) {
        $err = (string)$tokenResp['error'];
        $desc = isset($tokenResp['error_description']) ? (string)$tokenResp['error_description'] : '';
        throw new RuntimeException('Google OAuth token error: ' . $err . ($desc ? (' - ' . $desc) : ''));
    }

    if (empty($tokenResp['id_token'])) {
        throw new RuntimeException('Google did not return an id_token.');
    }

    $claims = verify_google_id_token((string)$tokenResp['id_token'], $clientId);
    return $claims;
}

function google_rbac_column_exists(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

function google_rbac_ensure_applicant_column(mysqli $conn, string $column, string $definition): void
{
    if (google_rbac_column_exists($conn, 'applicants', $column)) {
        return;
    }
    $conn->query('ALTER TABLE applicants ADD COLUMN ' . $definition);
}

function google_rbac_origin_from_redirect(string $redirectUri): array
{
    $pu = parse_url($redirectUri);
    $scheme = isset($pu['scheme']) ? (string)$pu['scheme'] : 'http';
    $host = isset($pu['host']) ? (string)$pu['host'] : 'localhost';
    $port = isset($pu['port']) ? ':' . (int)$pu['port'] : '';
    $origin = $scheme . '://' . $host . $port;
    $path = isset($pu['path']) ? str_replace('\\', '/', (string)$pu['path']) : '/Employee_Tracking/index.html';

    // Always resolve to the project root (/Employee_Tracking), not the OAuth callback folder.
    if (preg_match('#^(.*?/Employee_Tracking)(?:/|$)#i', $path, $matches)) {
        $appDir = rtrim($matches[1], '/');
    } else {
        $appDir = rtrim(dirname($path), '/');
        if ($appDir === '' || $appDir === '.') {
            $appDir = '/Employee_Tracking';
        }
    }

    return [$origin, $appDir];
}

function google_rbac_root_relative_paths(): array
{
    return [
        'Admin' => '/Employee_Tracking/Html/Admin/admin_dashboard.html',
        'HR' => '/Employee_Tracking/Html/HR/hr_dashboard.html',
        'Accounting' => '/Employee_Tracking/Html/Accounting/accounting_dashboard.html',
        'TeamLead' => '/Employee_Tracking/Html/TeamLead/team_lead_dashboard.html',
        'Applicant' => '/Employee_Tracking/Html/Applicant/my_applications.html',
        'Employee' => '/Employee_Tracking/Html/Employee/employee_dashboard.html',
    ];
}

function google_rbac_dashboard_paths(string $origin, string $appDir): array
{
    return [
        'Admin' => $origin . $appDir . '/Html/Admin/admin_dashboard.html',
        'HR' => $origin . $appDir . '/Html/HR/hr_dashboard.html',
        'Accounting' => $origin . $appDir . '/Html/Accounting/accounting_dashboard.html',
        'TeamLead' => $origin . $appDir . '/Html/TeamLead/team_lead_dashboard.html',
        'Applicant' => $origin . $appDir . '/Html/Applicant/my_applications.html',
        'Employee' => $origin . $appDir . '/Html/Employee/employee_dashboard.html',
    ];
}

function google_rbac_applicant_profile_complete(mysqli $conn, int $userId): bool
{
    google_rbac_ensure_applicant_column($conn, 'BirthDate', 'BirthDate date DEFAULT NULL');
    google_rbac_ensure_applicant_column($conn, 'ProfileDetailsCompleted', 'ProfileDetailsCompleted tinyint(1) DEFAULT 0');
    google_rbac_ensure_applicant_column($conn, 'ProfileVisibilityCompleted', 'ProfileVisibilityCompleted tinyint(1) DEFAULT 0');

    $chk = $conn->prepare('
        SELECT BirthDate, ProfileDetailsCompleted, ProfileVisibilityCompleted
        FROM applicants
        WHERE UserID = ?
        LIMIT 1
    ');
    $chk->bind_param('i', $userId);
    $chk->execute();
    $cr = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$cr) {
        return false;
    }

    $bd = $cr['BirthDate'] ?? null;
    $dDone = isset($cr['ProfileDetailsCompleted']) ? (int)$cr['ProfileDetailsCompleted'] === 1 : false;
    $vDone = isset($cr['ProfileVisibilityCompleted']) ? (int)$cr['ProfileVisibilityCompleted'] === 1 : false;
    if (!$dDone || !$vDone || !$bd) {
        return false;
    }

    try {
        $birth = new DateTimeImmutable($bd);
        $today = new DateTimeImmutable('today');
        if ($birth > $today) {
            return false;
        }
        return $today->diff($birth)->y >= 18;
    } catch (Throwable $e) {
        return false;
    }
}

function google_rbac_get_role_id(mysqli $conn, string $roleName): ?int
{
    $stmt = $conn->prepare('SELECT RolesID FROM roles WHERE RoleName = ? LIMIT 1');
    $stmt->bind_param('s', $roleName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['RolesID'] : null;
}

/**
 * @return array<string,mixed>
 */
function google_rbac_process_verified_email(
    mysqli $conn,
    string $email,
    string $fullName,
    string $redirectUri
): array {
    $email = trim($email);
    if ($email === '') {
        throw new RuntimeException('Email is missing from Google profile.');
    }

    [$origin, $appDir] = google_rbac_origin_from_redirect($redirectUri);
    $dashboards = google_rbac_dashboard_paths($origin, $appDir);

    $userStmt = $conn->prepare('
        SELECT u.UsersID, u.FullName, u.RoleID, u.Email, u.IsActive, r.RoleName
        FROM users u
        INNER JOIN roles r ON u.RoleID = r.RolesID
        WHERE LOWER(TRIM(u.Email)) = LOWER(TRIM(?))
        LIMIT 1
    ');
    $userStmt->bind_param('s', $email);
    $userStmt->execute();
    $userRow = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    $isNewUser = !$userRow;

    if ($userRow) {
        if ((int)($userRow['IsActive'] ?? 0) !== 1) {
            throw new RuntimeException('Account is not active. Please contact the administrator.');
        }

        $userId = (int)$userRow['UsersID'];
        $roleName = trim((string)$userRow['RoleName']);
        $finalName = $fullName !== '' ? $fullName : trim((string)($userRow['FullName'] ?? ''));
    } else {
        $applicantRoleId = google_rbac_get_role_id($conn, 'Applicant');
        if (!$applicantRoleId) {
            throw new RuntimeException('Applicant role is not configured.');
        }

        $roleName = 'Applicant';
        $finalName = $fullName !== '' ? $fullName : 'Applicant';
        $randomPassword = bin2hex(random_bytes(16));
        $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);

        $stmtUser = $conn->prepare('
            INSERT INTO users (RoleID, FullName, Email, Password, IsActive)
            VALUES (?, ?, ?, ?, 1)
        ');
        $stmtUser->bind_param('isss', $applicantRoleId, $finalName, $email, $hashedPassword);
        if (!$stmtUser->execute()) {
            throw new RuntimeException('Could not create user account.');
        }
        $userId = (int)$stmtUser->insert_id;
        $stmtUser->close();

        $stmtApp = $conn->prepare('
            INSERT INTO applicants (UserID, FullName, Email, MobileNumber, ProfileCompleted)
            VALUES (?, ?, ?, NULL, 0)
        ');
        $stmtApp->bind_param('iss', $userId, $finalName, $email);
        $stmtApp->execute();
        $stmtApp->close();
    }

    if (strcasecmp($roleName, 'Applicant') === 0) {
        $appCheck = $conn->prepare('SELECT ApplicantID FROM applicants WHERE UserID = ? LIMIT 1');
        $appCheck->bind_param('i', $userId);
        $appCheck->execute();
        $appRow = $appCheck->get_result()->fetch_assoc();
        $appCheck->close();

        if (!$appRow) {
            $stmtApp2 = $conn->prepare('
                INSERT INTO applicants (UserID, FullName, Email, MobileNumber, ProfileCompleted)
                VALUES (?, ?, ?, NULL, 0)
            ');
            $stmtApp2->bind_param('iss', $userId, $finalName, $email);
            $stmtApp2->execute();
            $stmtApp2->close();
        }
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $roleName;
    $_SESSION['full_name'] = $finalName;
    $_SESSION['email'] = $email;

    if (strcasecmp($roleName, 'Applicant') === 0) {
        $_SESSION['applicant_policy_accepted'] = false;
    } else {
        unset($_SESSION['applicant_policy_accepted']);
    }

    $loginStmt = $conn->prepare('UPDATE users SET LastLogin = NOW() WHERE UsersID = ?');
    if ($loginStmt) {
        $loginStmt->bind_param('i', $userId);
        $loginStmt->execute();
        $loginStmt->close();
    }

    $redirect = $dashboards[$roleName] ?? ($origin . $appDir . '/index.html');
    $needsPolicy = false;
    $redirectAfterAccept = $redirect;

    if (strcasecmp($roleName, 'Applicant') === 0) {
        $profileComplete = google_rbac_applicant_profile_complete($conn, $userId);
        $onboardingFull = $origin . $appDir . '/Html/Applicant/applicant_profile_onboarding.html';
        $redirect = $profileComplete ? $dashboards['Applicant'] : $onboardingFull;
        $redirectAfterAccept = $redirect;
        $needsPolicy = true;
    } else {
        $needsPolicy = false;
        $redirectAfterAccept = $redirect;
    }

    $rootPaths = google_rbac_root_relative_paths();
    if (strcasecmp($roleName, 'Applicant') === 0 && !$profileComplete) {
        $redirectPath = '/Employee_Tracking/Html/Applicant/applicant_profile_onboarding.html';
    } else {
        $redirectPath = $rootPaths[$roleName] ?? '/Employee_Tracking/index.html';
    }

    return [
        'success' => true,
        'message' => $isNewUser
            ? 'Welcome! Your applicant account has been created.'
            : 'Signed in successfully.',
        'role' => $roleName,
        'role_key' => strtolower($roleName),
        'redirect' => $redirect,
        'redirect_path' => $redirectPath,
        'redirect_after_accept' => $redirectAfterAccept,
        'needs_policy' => $needsPolicy,
        'is_new_user' => $isNewUser,
        'role_elevated' => strcasecmp($roleName, 'Employee') === 0,
    ];
}
