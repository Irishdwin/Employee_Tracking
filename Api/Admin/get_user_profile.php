<?php
// Api/Admin/get_user_profile.php
header('Content-Type: application/json');
session_start();

// 1. Session & RBAC Verification
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Superuser'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// 2. Database Connection
require_once __DIR__ . '/../../Config/database.php'; // Adjust path if needed

$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$userId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing User ID.']);
    exit;
}

try {
    // Single consolidated query using LEFT JOINs to safely extract full profile details
    $sql = "
        SELECT 
            -- System Information
            u.id AS user_id,
            u.username,
            u.email,
            u.role,
            u.status AS account_status,
            u.created_at,
            u.updated_at,
            u.last_login,

            -- Basic & Address Information
            e.full_name,
            e.profile_picture,
            e.phone_number,
            e.birthday,
            e.age,
            e.gender,
            e.civil_status,
            e.nationality,
            e.complete_address,
            e.barangay,
            e.city,
            e.province,
            e.zip_code,

            -- Employment Information
            e.employee_id,
            e.employment_status,
            e.position,
            e.department,
            e.date_hired,
            e.employment_type,

            -- Deployment Information
            d.client_company,
            d.deployment_site,
            d.branch,
            d.shift_schedule,
            d.start_date AS deployment_start_date,
            d.end_date AS deployment_end_date,
            d.team_leader,
            d.status AS deployment_status,

            -- Salary Information
            s.daily_rate,
            s.hourly_rate,
            s.allowance,
            s.monthly_salary_credit,

            -- Government Information
            g.sss_number,
            g.philhealth_number,
            g.pagibig_number,
            g.tin_number,

            -- Recruitment Information
            a.applied_position,
            a.date_applied,
            a.recruitment_status,
            a.interview_schedule,
            a.endorsement_date,

            -- Documents
            doc.resume_path,
            doc.valid_id_path,
            doc.certificates_path,

            -- Payroll Summary
            p.payroll_date AS latest_payroll_date,
            p.gross_pay,
            p.total_deductions,
            p.net_pay

        FROM users u
        LEFT JOIN employees e ON u.id = e.user_id
        LEFT JOIN deployments d ON u.id = d.user_id AND d.status = 'Active'
        LEFT JOIN salaries s ON u.id = s.user_id
        LEFT JOIN government_ids g ON u.id = g.user_id
        LEFT JOIN applicants a ON u.id = a.user_id
        LEFT JOIN user_documents doc ON u.id = doc.user_id
        LEFT JOIN (
            SELECT user_id, payroll_date, gross_pay, total_deductions, net_pay
            FROM payrolls
            ORDER BY payroll_date DESC
            LIMIT 1
        ) p ON u.id = p.user_id
        WHERE u.id = :user_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User profile not found.']);
        exit;
    }

    echo json_encode(['status' => 'success', 'data' => $user]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Query Error: ' . $e->getMessage()]);
}