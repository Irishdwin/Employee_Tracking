<?php
/**
 * Employee salary configuration — validation, computation, schema helpers.
 */

declare(strict_types=1);

function employee_salary_column_exists(mysqli $conn, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'employee'
          AND COLUMN_NAME = ?
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

function employee_salary_ensure_schema(mysqli $conn): void
{
    $columns = [
        'daily_rate' => 'DECIMAL(10,2) NULL DEFAULT NULL AFTER BasicSalary',
        'hourly_rate' => 'DECIMAL(10,2) NULL DEFAULT NULL AFTER daily_rate',
        'allowance' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER hourly_rate',
        'monthly_salary_credit' => 'DECIMAL(10,2) NULL DEFAULT NULL AFTER allowance',
    ];

    foreach ($columns as $name => $definition) {
        if (!employee_salary_column_exists($conn, $name)) {
            $conn->query('ALTER TABLE employee ADD COLUMN ' . $name . ' ' . $definition);
        }
    }
}

/**
 * @return array{success:bool,message?:string,daily_rate?:float,hourly_rate?:float,allowance?:float,monthly_salary_credit?:float,basic_salary?:float}
 */
function employee_salary_config_from_input($dailyRateRaw, $allowanceRaw): array
{
    if ($dailyRateRaw === null || $dailyRateRaw === '') {
        return ['success' => false, 'message' => 'Daily Rate (PHP) is required and must be greater than zero.'];
    }

    if (!is_numeric($dailyRateRaw)) {
        return ['success' => false, 'message' => 'Daily Rate must be a valid number.'];
    }

    $dailyRate = round((float)$dailyRateRaw, 2);
    if ($dailyRate <= 0) {
        return ['success' => false, 'message' => 'Daily Rate must be greater than zero.'];
    }
    if ($dailyRate > 99999999.99) {
        return ['success' => false, 'message' => 'Daily Rate exceeds the allowed maximum.'];
    }

    $allowance = 0.0;
    if ($allowanceRaw !== null && $allowanceRaw !== '') {
        if (!is_numeric($allowanceRaw)) {
            return ['success' => false, 'message' => 'Monthly Allowance must be a valid number.'];
        }
        $allowance = round((float)$allowanceRaw, 2);
        if ($allowance < 0) {
            return ['success' => false, 'message' => 'Monthly Allowance cannot be negative.'];
        }
        if ($allowance > 99999999.99) {
            return ['success' => false, 'message' => 'Monthly Allowance exceeds the allowed maximum.'];
        }
    }

    $hourlyRate = round($dailyRate / 8, 2);
    $monthlySalaryCredit = round($dailyRate * 26, 2);

    return [
        'success' => true,
        'daily_rate' => $dailyRate,
        'hourly_rate' => $hourlyRate,
        'allowance' => $allowance,
        'monthly_salary_credit' => $monthlySalaryCredit,
        'basic_salary' => $monthlySalaryCredit,
    ];
}
