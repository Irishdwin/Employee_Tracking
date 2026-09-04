<?php
/**
 * Government deduction rate configuration (SSS brackets + PhilHealth / Pag-IBIG).
 */

declare(strict_types=1);

function deduction_rates_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function deduction_rates_require_admin(): void
{
    require_once __DIR__ . '/../Common/session_init.php';
    if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
        deduction_rates_json(['success' => false, 'message' => 'Unauthorized.'], 401);
    }
}

function deduction_rates_ensure_schema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS `sss_rates_config` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `salary_min` DECIMAL(12,2) NOT NULL,
            `salary_max` DECIMAL(12,2) NULL DEFAULT NULL,
            `monthly_salary_credit` DECIMAL(12,2) NOT NULL,
            `regular_ss_er` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `regular_ss_ee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `mpf_er` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `mpf_ee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `ec_er` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
            `effective_year` SMALLINT UNSIGNED NOT NULL DEFAULT 2025,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_sss_salary_range` (`salary_min`, `salary_max`),
            KEY `idx_sss_msc` (`monthly_salary_credit`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS `gov_percentage_rates` (
            `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `philhealth_ee_pct` DECIMAL(6,4) NOT NULL DEFAULT 2.5000,
            `philhealth_er_pct` DECIMAL(6,4) NOT NULL DEFAULT 2.5000,
            `pagibig_ee_fixed` DECIMAL(10,2) NOT NULL DEFAULT 200.00,
            `pagibig_er_fixed` DECIMAL(10,2) NOT NULL DEFAULT 200.00,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $countRes = $conn->query('SELECT COUNT(*) AS c FROM gov_percentage_rates');
    $countRow = $countRes ? $countRes->fetch_assoc() : ['c' => 0];
    if ((int)($countRow['c'] ?? 0) === 0) {
        $conn->query(
            "INSERT INTO gov_percentage_rates
                (id, philhealth_ee_pct, philhealth_er_pct, pagibig_ee_fixed, pagibig_er_fixed)
             VALUES (1, 2.5000, 2.5000, 200.00, 200.00)"
        );
    }
}

/**
 * @return array{monthly_salary_credit:float,regular_ss_er:float,regular_ss_ee:float,mpf_er:float,mpf_ee:float,ec_er:float}
 */
function deduction_rates_compute_sss_amounts(float $msc): array
{
    $regularBase = min($msc, 20000.0);
    $mpfBase = max(0.0, $msc - 20000.0);

    return [
        'monthly_salary_credit' => round($msc, 2),
        'regular_ss_ee' => round($regularBase * 0.05, 2),
        'regular_ss_er' => round($regularBase * 0.10, 2),
        'mpf_ee' => round($mpfBase * 0.05, 2),
        'mpf_er' => round($mpfBase * 0.10, 2),
        'ec_er' => $msc <= 14500.0 ? 10.0 : 30.0,
    ];
}

function deduction_rates_seed_sss_2025(mysqli $conn): void
{
    $res = $conn->query('SELECT COUNT(*) AS c FROM sss_rates_config WHERE effective_year = 2025');
    $row = $res ? $res->fetch_assoc() : ['c' => 0];
    if ((int)($row['c'] ?? 0) > 0) {
        return;
    }

    $brackets = [];
    $brackets[] = [
        'salary_min' => 0.0,
        'salary_max' => 5249.99,
        'msc' => 5000.0,
    ];

    for ($msc = 5500.0; $msc <= 34500.0; $msc += 500.0) {
        $brackets[] = [
            'salary_min' => $msc - 250.0,
            'salary_max' => $msc + 249.99,
            'msc' => $msc,
        ];
    }

    $brackets[] = [
        'salary_min' => 34750.0,
        'salary_max' => null,
        'msc' => 35000.0,
    ];

    $stmtWithMax = $conn->prepare(
        'INSERT INTO sss_rates_config
            (salary_min, salary_max, monthly_salary_credit, regular_ss_er, regular_ss_ee, mpf_er, mpf_ee, ec_er, effective_year)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2025)'
    );
    $stmtNoMax = $conn->prepare(
        'INSERT INTO sss_rates_config
            (salary_min, salary_max, monthly_salary_credit, regular_ss_er, regular_ss_ee, mpf_er, mpf_ee, ec_er, effective_year)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 2025)'
    );

    foreach ($brackets as $bracket) {
        $amounts = deduction_rates_compute_sss_amounts((float)$bracket['msc']);
        $salaryMin = (float)$bracket['salary_min'];
        $salaryMax = $bracket['salary_max'];

        if ($salaryMax === null) {
            $stmt = $stmtNoMax;
            $stmt->bind_param(
                'ddddddd',
                $salaryMin,
                $amounts['monthly_salary_credit'],
                $amounts['regular_ss_er'],
                $amounts['regular_ss_ee'],
                $amounts['mpf_er'],
                $amounts['mpf_ee'],
                $amounts['ec_er']
            );
        } else {
            $stmt = $stmtWithMax;
            $salaryMaxVal = (float)$salaryMax;
            $stmt->bind_param(
                'dddddddd',
                $salaryMin,
                $salaryMaxVal,
                $amounts['monthly_salary_credit'],
                $amounts['regular_ss_er'],
                $amounts['regular_ss_ee'],
                $amounts['mpf_er'],
                $amounts['mpf_ee'],
                $amounts['ec_er']
            );
        }
        $stmt->execute();
    }
    $stmtWithMax->close();
    $stmtNoMax->close();
}

function deduction_rates_fetch_gov(mysqli $conn): ?array
{
    $res = $conn->query('SELECT * FROM gov_percentage_rates WHERE id = 1 LIMIT 1');
    return $res ? $res->fetch_assoc() : null;
}

function deduction_rates_fetch_sss(mysqli $conn): array
{
    $rows = [];
    $res = $conn->query(
        'SELECT id, salary_min, salary_max, monthly_salary_credit, regular_ss_er, regular_ss_ee,
                mpf_er, mpf_ee, ec_er, effective_year, is_active, updated_at
         FROM sss_rates_config
         WHERE is_active = 1
         ORDER BY salary_min ASC, monthly_salary_credit ASC'
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * @return array{success:bool,message?:string,data?:array}
 */
function deduction_rates_validate_bracket(array $input, bool $requireId = false): array
{
    if ($requireId && (empty($input['id']) || (int)$input['id'] <= 0)) {
        return ['success' => false, 'message' => 'Invalid SSS bracket ID.'];
    }

    $fields = [
        'salary_min' => 'Salary minimum',
        'monthly_salary_credit' => 'Monthly salary credit',
        'regular_ss_er' => 'Regular SS employer share',
        'regular_ss_ee' => 'Regular SS employee share',
        'mpf_er' => 'MPF employer share',
        'mpf_ee' => 'MPF employee share',
        'ec_er' => 'EC employer share',
    ];

    $data = [];
    foreach ($fields as $key => $label) {
        if (!isset($input[$key]) || $input[$key] === '') {
            return ['success' => false, 'message' => $label . ' is required.'];
        }
        if (!is_numeric($input[$key])) {
            return ['success' => false, 'message' => $label . ' must be numeric.'];
        }
        $value = (float)$input[$key];
        if ($value < 0) {
            return ['success' => false, 'message' => $label . ' cannot be negative.'];
        }
        $data[$key] = round($value, 2);
    }

    $salaryMax = null;
    if (isset($input['salary_max']) && $input['salary_max'] !== '' && $input['salary_max'] !== null) {
        if (!is_numeric($input['salary_max'])) {
            return ['success' => false, 'message' => 'Salary maximum must be numeric.'];
        }
        $salaryMax = round((float)$input['salary_max'], 2);
        if ($salaryMax < $data['salary_min']) {
            return ['success' => false, 'message' => 'Salary maximum must be greater than or equal to salary minimum.'];
        }
    }

    $data['salary_max'] = $salaryMax;

    if ($requireId) {
        $data['id'] = (int)$input['id'];
    }

    return ['success' => true, 'data' => $data];
}

/**
 * @return array{success:bool,message?:string,data?:array}
 */
function deduction_rates_validate_gov(array $input): array
{
    $required = [
        'philhealth_ee_pct' => 'PhilHealth employee share',
        'philhealth_er_pct' => 'PhilHealth employer share',
        'pagibig_ee_fixed' => 'Pag-IBIG employee fixed deduction',
        'pagibig_er_fixed' => 'Pag-IBIG employer fixed contribution',
    ];

    $data = [];
    foreach ($required as $key => $label) {
        if (!isset($input[$key]) || $input[$key] === '') {
            return ['success' => false, 'message' => $label . ' is required.'];
        }
        if (!is_numeric($input[$key])) {
            return ['success' => false, 'message' => $label . ' must be numeric.'];
        }
        $value = (float)$input[$key];
        if ($value < 0) {
            return ['success' => false, 'message' => $label . ' cannot be negative.'];
        }
        if (strpos($key, '_pct') !== false && $value > 100) {
            return ['success' => false, 'message' => $label . ' cannot exceed 100%.'];
        }
        $data[$key] = round($value, strpos($key, '_pct') !== false ? 4 : 2);
    }

    return ['success' => true, 'data' => $data];
}
