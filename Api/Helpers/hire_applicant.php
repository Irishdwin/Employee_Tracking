<?php
/**
 * Shared hire / applicant-to-employee conversion (mysqli transaction).
 */

require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/employee_salary_helper.php';
require_once __DIR__ . '/job_order_wall.php';

/**
 * @return array{success:bool,message:string,employee_id?:int,employee_number?:string,deployment_created?:bool,salary?:array<string,float>}
 */
function hireApplicantFromApplication(
    mysqli $conn,
    int $applicationId,
    int $hiredByUserId,
    string $sssNumber,
    string $philhealthNumber,
    string $pagibigNumber,
    float $dailyRate = 0,
    float $allowance = 0
): array {
    if ($applicationId <= 0) {
        return ['success' => false, 'message' => 'Invalid application ID'];
    }

    if ($sssNumber === '' || $philhealthNumber === '' || $pagibigNumber === '') {
        return ['success' => false, 'message' => 'SSS, PhilHealth, and Pag-IBIG numbers are required'];
    }

    $salaryConfig = employee_salary_config_from_input($dailyRate, $allowance);
    if (!$salaryConfig['success']) {
        return ['success' => false, 'message' => (string)($salaryConfig['message'] ?? 'Invalid salary configuration.')];
    }

    employee_salary_ensure_schema($conn);

    $checkStmt = $conn->prepare("
        SELECT
            ja.ApplicationID,
            ja.ApplicantID,
            ja.JobOrderID,
            ja.ClientApprovalStatus,
            ja.Remarks,
            ja.HiredDate,
            ja.target_detail_id,
            a.UserID,
            a.FullName,
            a.FirstName,
            a.LastName,
            a.Email,
            a.MobileNumber,
            a.Address,
            a.BirthDate,
            a.Gender,
            jo.Position,
            jo.Location AS JobLocation,
            jo.WorkSchedule AS JobWorkSchedule,
            jo.CompanyID,
            jod.deployment_site AS SelectedDeploymentSite,
            jod.work_schedule AS SelectedWorkSchedule,
            jod.position_title AS SelectedPositionTitle,
            c.UserID AS TeamLeadUserID
        FROM job_applications ja
        INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
        INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
        INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
        LEFT JOIN job_order_details jod ON ja.target_detail_id = jod.detail_id
        WHERE ja.ApplicationID = ?
        LIMIT 1
    ");
    $checkStmt->bind_param('i', $applicationId);
    $checkStmt->execute();
    $application = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$application) {
        return ['success' => false, 'message' => 'Application not found'];
    }

    if (!empty($application['HiredDate'])) {
        return ['success' => false, 'message' => 'This applicant has already been hired'];
    }

    if (($application['ClientApprovalStatus'] ?? '') !== 'Approved') {
        return ['success' => false, 'message' => 'Cannot hire: client has not approved this application'];
    }

    if (strpos((string)($application['Remarks'] ?? ''), 'Contract uploaded:') === false) {
        return ['success' => false, 'message' => 'No employment contract on file. Upload a contract first.'];
    }

    $email = trim((string)$application['Email']);
    $existingCheck = $conn->prepare('SELECT EmployeeID FROM employee WHERE Email = ? LIMIT 1');
    $existingCheck->bind_param('s', $email);
    $existingCheck->execute();
    if ($existingCheck->get_result()->num_rows > 0) {
        $existingCheck->close();
        return ['success' => false, 'message' => 'An employee record with this email already exists'];
    }
    $existingCheck->close();

    $conn->begin_transaction();

    try {
        $statusId = null;
        $statusRes = $conn->query("SELECT StatusID FROM status WHERE Name = 'Active' LIMIT 1");
        if ($statusRes && ($statusRow = $statusRes->fetch_assoc())) {
            $statusId = (int)$statusRow['StatusID'];
            $statusRes->free();
        }

        $employeeNumber = 'EMP' . date('Y') . str_pad((string)$applicationId, 6, '0', STR_PAD_LEFT);

        $firstName = trim((string)($application['FirstName'] ?? ''));
        $lastName = trim((string)($application['LastName'] ?? ''));
        if ($firstName === '' || $lastName === '') {
            $nameParts = preg_split('/\s+/', trim((string)$application['FullName']), 2);
            if ($firstName === '') {
                $firstName = $nameParts[0] ?? 'Employee';
            }
            if ($lastName === '') {
                $lastName = $nameParts[1] ?? '';
            }
        }

        $position = trim((string)($application['SelectedPositionTitle'] ?? ''));
        if ($position === '') {
            $position = trim((string)($application['Position'] ?? ''));
        }

        $insertEmployee = $conn->prepare("
            INSERT INTO employee (
                StatusID, EmployeeNumber, FirstName, LastName, Email, Position,
                HiredDate, IsActive, BasicSalary, daily_rate, hourly_rate, allowance, monthly_salary_credit,
                SSSNumber, PhilHealthNumber, PagIBIGNumber
            ) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 1, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $basicSalary = (float)$salaryConfig['basic_salary'];
        $dailyRateVal = (float)$salaryConfig['daily_rate'];
        $hourlyRateVal = (float)$salaryConfig['hourly_rate'];
        $allowanceVal = (float)$salaryConfig['allowance'];
        $monthlyCreditVal = (float)$salaryConfig['monthly_salary_credit'];
        $insertEmployee->bind_param(
            'isssssdddddsss',
            $statusId,
            $employeeNumber,
            $firstName,
            $lastName,
            $email,
            $position,
            $basicSalary,
            $dailyRateVal,
            $hourlyRateVal,
            $allowanceVal,
            $monthlyCreditVal,
            $sssNumber,
            $philhealthNumber,
            $pagibigNumber
        );
        if (!$insertEmployee->execute()) {
            throw new RuntimeException($insertEmployee->error ?: 'Failed to create employee record');
        }
        $employeeId = (int)$insertEmployee->insert_id;
        $insertEmployee->close();

        $roleStmt = $conn->prepare("SELECT RolesID FROM roles WHERE RoleName = 'Employee' LIMIT 1");
        $roleStmt->execute();
        $roleRow = $roleStmt->get_result()->fetch_assoc();
        $roleStmt->close();

        if (!$roleRow) {
            throw new RuntimeException('Employee role is not configured in the system');
        }

        $employeeRoleId = (int)$roleRow['RolesID'];
        $userId = (int)$application['UserID'];
        $updateUserStmt = $conn->prepare('UPDATE users SET RoleID = ? WHERE UsersID = ?');
        $updateUserStmt->bind_param('ii', $employeeRoleId, $userId);
        if (!$updateUserStmt->execute()) {
            throw new RuntimeException($updateUserStmt->error ?: 'Failed to update user role');
        }
        $updateUserStmt->close();

        $updateAppStmt = $conn->prepare('UPDATE job_applications SET HiredDate = NOW(), HiredBy = ? WHERE ApplicationID = ?');
        $updateAppStmt->bind_param('ii', $hiredByUserId, $applicationId);
        if (!$updateAppStmt->execute()) {
            throw new RuntimeException($updateAppStmt->error ?: 'Failed to mark application as hired');
        }
        $updateAppStmt->close();

        markApplicantRecordHired($conn, (int)$application['ApplicantID']);
        setJobApplicationHiredStatus($conn, $applicationId);

        $deploymentCreated = createInitialDeploymentForHire($conn, $employeeId, $application, $statusId);

        $jobOrderId = (int)($application['JobOrderID'] ?? 0);
        if ($jobOrderId > 0) {
            updateJobOrderFillStatus($conn, $jobOrderId);
        }

        createEmployeePasswordNotification($conn, $userId);
        $conn->commit();

        createApplicationNotification(
            $conn,
            $applicationId,
            'application_hired',
            'Congratulations! You are Hired!',
            'Welcome to the Onazul team! Your account has been upgraded to an Employee profile. Sign in using your existing login method to access your Employee Dashboard. You can manage your password in Profile → Settings.'
        );

        return [
            'success' => true,
            'message' => 'Applicant successfully hired and role elevated.',
            'employee_id' => $employeeId,
            'employee_number' => $employeeNumber,
            'deployment_created' => $deploymentCreated,
            'role_elevated' => true,
            'salary' => [
                'daily_rate' => $dailyRateVal,
                'hourly_rate' => $hourlyRateVal,
                'allowance' => $allowanceVal,
                'monthly_salary_credit' => $monthlyCreditVal,
            ],
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Failed to hire applicant: ' . $e->getMessage()];
    }
}

function markApplicantRecordHired(mysqli $conn, int $applicantId): void
{
    static $hasStatusColumn = null;
    if ($hasStatusColumn === null) {
        $colRes = $conn->query("SHOW COLUMNS FROM applicants LIKE 'ApplicantStatus'");
        $hasStatusColumn = ($colRes && $colRes->num_rows > 0);
        if ($colRes) {
            $colRes->free();
        }
    }

    if ($hasStatusColumn) {
        $stmt = $conn->prepare("UPDATE applicants SET ApplicantStatus = 'Hired' WHERE ApplicantID = ?");
        $stmt->bind_param('i', $applicantId);
        $stmt->execute();
        $stmt->close();
        return;
    }

    // Fallback: stamp hire note on profile visibility field only if empty (non-destructive).
    $stmt = $conn->prepare("
        UPDATE applicants
        SET ProfileVisibility = 'Hired'
        WHERE ApplicantID = ?
          AND (ProfileVisibility IS NULL OR TRIM(ProfileVisibility) = '' OR ProfileVisibility = 'Public')
    ");
    $stmt->bind_param('i', $applicantId);
    $stmt->execute();
    $stmt->close();
}

function setJobApplicationHiredStatus(mysqli $conn, int $applicationId): void
{
    $hiredStatusId = null;
    $res = $conn->query("SELECT StatusID FROM status WHERE Name = 'Hired' LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        $hiredStatusId = (int)$row['StatusID'];
        $res->free();
    } else {
        $conn->query("INSERT INTO status (Name) VALUES ('Hired')");
        $hiredStatusId = (int)$conn->insert_id;
    }

    if ($hiredStatusId > 0) {
        $stmt = $conn->prepare('UPDATE job_applications SET StatusID = ? WHERE ApplicationID = ?');
        $stmt->bind_param('ii', $hiredStatusId, $applicationId);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * @param array<string,mixed> $application
 */
function createInitialDeploymentForHire(mysqli $conn, int $employeeId, array $application, ?int $statusId): bool
{
    $checkDep = $conn->prepare('SELECT DeploymentID FROM deployment WHERE EmployeeID = ? AND (EndDate IS NULL OR EndDate >= CURDATE()) LIMIT 1');
    $checkDep->bind_param('i', $employeeId);
    $checkDep->execute();
    if ($checkDep->get_result()->num_rows > 0) {
        $checkDep->close();
        throw new RuntimeException('Employee already has an active deployment');
    }
    $checkDep->close();

    $companyId = (int)($application['CompanyID'] ?? 0);
    $position = trim((string)($application['SelectedPositionTitle'] ?? ''));
    if ($position === '') {
        $position = trim((string)($application['Position'] ?? ''));
    }

    $location = trim((string)($application['SelectedDeploymentSite'] ?? ''));
    if ($location === '' || strcasecmp($location, 'Multiple sites') === 0) {
        $location = trim((string)($application['JobLocation'] ?? ''));
    }
    if (strcasecmp($location, 'Multiple sites') === 0) {
        $location = '';
    }

    $teamLeadId = (int)($application['TeamLeadUserID'] ?? 0);
    $teamLeadBind = $teamLeadId > 0 ? $teamLeadId : null;
    if ($companyId <= 0 || $position === '') {
        return false;
    }

    require_once __DIR__ . '/work_site_geofence.php';
    $site=site_resolve_legacy($conn,$companyId,$location);
    $siteId=(int)$site['SiteID'];$teamLeadBind=$site['TeamLeadID']?(int)$site['TeamLeadID']:null;
    $startDate = date('Y-m-d');
    $insertDep = $conn->prepare("
        INSERT INTO deployment (
            SiteID, EmployeeID, CompanyID, Position, Location,
            StartDate, EndDate, StatusID, SiteSuperVisorID
        ) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)
    ");
    $insertDep->bind_param(
        'iiisssii',
        $siteId,
        $employeeId,
        $companyId,
        $position,
        $location,
        $startDate,
        $statusId,
        $teamLeadBind
    );
    if (!$insertDep->execute()) {
        $err = $insertDep->error ?: 'Failed to create deployment';
        $insertDep->close();
        throw new RuntimeException($err);
    }
    $insertDep->close();
    return true;
}