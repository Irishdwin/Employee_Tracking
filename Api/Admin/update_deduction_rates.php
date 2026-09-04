<?php
require_once __DIR__ . '/../Helpers/deduction_rates_helper.php';

deduction_rates_require_admin();
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    deduction_rates_json(['success' => false, 'message' => 'Method not allowed. Use POST.'], 405);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = isset($input['action']) ? trim((string)$input['action']) : '';
if ($action === '') {
    deduction_rates_json(['success' => false, 'message' => 'Missing action.'], 400);
}

deduction_rates_ensure_schema($conn);
deduction_rates_seed_sss_2025($conn);

try {
    if ($action === 'update_gov_rates') {
        $validated = deduction_rates_validate_gov($input);
        if (!$validated['success']) {
            deduction_rates_json($validated, 400);
        }
        $data = $validated['data'];

        $stmt = $conn->prepare(
            'UPDATE gov_percentage_rates
             SET philhealth_ee_pct = ?, philhealth_er_pct = ?, pagibig_ee_fixed = ?, pagibig_er_fixed = ?
             WHERE id = 1'
        );
        $stmt->bind_param(
            'dddd',
            $data['philhealth_ee_pct'],
            $data['philhealth_er_pct'],
            $data['pagibig_ee_fixed'],
            $data['pagibig_er_fixed']
        );
        $stmt->execute();
        $stmt->close();

        AuditLogger::log($conn, 'Settings', 'Update', 'Government deduction rates (PhilHealth & Pag-IBIG) were updated.', '1');

        deduction_rates_json([
            'success' => true,
            'message' => 'PhilHealth and Pag-IBIG rates updated successfully.',
            'gov_rates' => $data,
        ]);
    }

    if ($action === 'update_sss_bracket') {
        $validated = deduction_rates_validate_bracket($input, true);
        if (!$validated['success']) {
            deduction_rates_json($validated, 400);
        }
        $data = $validated['data'];

        if ($data['salary_max'] === null) {
            $stmt = $conn->prepare(
                'UPDATE sss_rates_config
                 SET salary_min = ?, salary_max = NULL, monthly_salary_credit = ?,
                     regular_ss_er = ?, regular_ss_ee = ?, mpf_er = ?, mpf_ee = ?, ec_er = ?
                 WHERE id = ? AND is_active = 1'
            );
            $stmt->bind_param(
                'ddddddi',
                $data['salary_min'],
                $data['monthly_salary_credit'],
                $data['regular_ss_er'],
                $data['regular_ss_ee'],
                $data['mpf_er'],
                $data['mpf_ee'],
                $data['ec_er'],
                $data['id']
            );
        } else {
            $stmt = $conn->prepare(
                'UPDATE sss_rates_config
                 SET salary_min = ?, salary_max = ?, monthly_salary_credit = ?,
                     regular_ss_er = ?, regular_ss_ee = ?, mpf_er = ?, mpf_ee = ?, ec_er = ?
                 WHERE id = ? AND is_active = 1'
            );
            $stmt->bind_param(
                'ddddddddi',
                $data['salary_min'],
                $data['salary_max'],
                $data['monthly_salary_credit'],
                $data['regular_ss_er'],
                $data['regular_ss_ee'],
                $data['mpf_er'],
                $data['mpf_ee'],
                $data['ec_er'],
                $data['id']
            );
        }

        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected < 1) {
            deduction_rates_json(['success' => false, 'message' => 'SSS bracket not found or unchanged.'], 404);
        }

        AuditLogger::log($conn, 'Settings', 'Update', "Updated SSS rate bracket (ID: {$data['id']}).", (string)$data['id']);

        deduction_rates_json([
            'success' => true,
            'message' => 'SSS bracket updated successfully.',
            'bracket' => $data,
        ]);
    }

    if ($action === 'add_sss_bracket') {
        $validated = deduction_rates_validate_bracket($input, false);
        if (!$validated['success']) {
            deduction_rates_json($validated, 400);
        }
        $data = $validated['data'];

        if ($data['salary_max'] === null) {
            $stmt = $conn->prepare(
                'INSERT INTO sss_rates_config
                    (salary_min, salary_max, monthly_salary_credit, regular_ss_er, regular_ss_ee, mpf_er, mpf_ee, ec_er, effective_year)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 2025)'
            );
            $stmt->bind_param(
                'ddddddd',
                $data['salary_min'],
                $data['monthly_salary_credit'],
                $data['regular_ss_er'],
                $data['regular_ss_ee'],
                $data['mpf_er'],
                $data['mpf_ee'],
                $data['ec_er']
            );
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO sss_rates_config
                    (salary_min, salary_max, monthly_salary_credit, regular_ss_er, regular_ss_ee, mpf_er, mpf_ee, ec_er, effective_year)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2025)'
            );
            $stmt->bind_param(
                'dddddddd',
                $data['salary_min'],
                $data['salary_max'],
                $data['monthly_salary_credit'],
                $data['regular_ss_er'],
                $data['regular_ss_ee'],
                $data['mpf_er'],
                $data['mpf_ee'],
                $data['ec_er']
            );
        }

        $stmt->execute();
        $newId = (int)$conn->insert_id;
        $stmt->close();

        AuditLogger::log($conn, 'Settings', 'Create', "Created new SSS rate bracket (ID: {$newId}).", (string)$newId);

        deduction_rates_json([
            'success' => true,
            'message' => 'New SSS bracket added successfully.',
            'bracket_id' => $newId,
        ], 201);
    }

    if ($action === 'delete_sss_bracket') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if ($id <= 0) {
            deduction_rates_json(['success' => false, 'message' => 'Invalid SSS bracket ID.'], 400);
        }

        $countRes = $conn->query('SELECT COUNT(*) AS c FROM sss_rates_config WHERE is_active = 1');
        $countRow = $countRes ? $countRes->fetch_assoc() : ['c' => 0];
        if ((int)($countRow['c'] ?? 0) <= 1) {
            deduction_rates_json(['success' => false, 'message' => 'At least one SSS bracket must remain active.'], 400);
        }

        $stmt = $conn->prepare('UPDATE sss_rates_config SET is_active = 0 WHERE id = ? AND is_active = 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected < 1) {
            deduction_rates_json(['success' => false, 'message' => 'SSS bracket not found.'], 404);
        }

        AuditLogger::log($conn, 'Settings', 'Delete', "Deactivated SSS rate bracket (ID: {$id}).", (string)$id);

        deduction_rates_json([
            'success' => true,
            'message' => 'SSS bracket removed successfully.',
        ]);
    }

    deduction_rates_json(['success' => false, 'message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('update_deduction_rates: ' . $e->getMessage());
    deduction_rates_json([
        'success' => false,
        'message' => 'Failed to update deduction rates. Please try again.',
    ], 500);
}