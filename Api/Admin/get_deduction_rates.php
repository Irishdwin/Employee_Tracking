<?php
require_once __DIR__ . '/../Helpers/deduction_rates_helper.php';

deduction_rates_require_admin();
require __DIR__ . '/../Db_connection/db_connect.php';

deduction_rates_ensure_schema($conn);
deduction_rates_seed_sss_2025($conn);

$gov = deduction_rates_fetch_gov($conn);
$sss = deduction_rates_fetch_sss($conn);

deduction_rates_json([
    'success' => true,
    'gov_rates' => $gov ? [
        'philhealth_ee_pct' => (float)$gov['philhealth_ee_pct'],
        'philhealth_er_pct' => (float)$gov['philhealth_er_pct'],
        'pagibig_ee_fixed' => (float)$gov['pagibig_ee_fixed'],
        'pagibig_er_fixed' => (float)$gov['pagibig_er_fixed'],
        'updated_at' => $gov['updated_at'],
    ] : null,
    'sss_brackets' => array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'salary_min' => (float)$row['salary_min'],
            'salary_max' => $row['salary_max'] !== null ? (float)$row['salary_max'] : null,
            'monthly_salary_credit' => (float)$row['monthly_salary_credit'],
            'regular_ss_er' => (float)$row['regular_ss_er'],
            'regular_ss_ee' => (float)$row['regular_ss_ee'],
            'mpf_er' => (float)$row['mpf_er'],
            'mpf_ee' => (float)$row['mpf_ee'],
            'ec_er' => (float)$row['ec_er'],
            'effective_year' => (int)$row['effective_year'],
            'updated_at' => $row['updated_at'],
        ];
    }, $sss),
]);
