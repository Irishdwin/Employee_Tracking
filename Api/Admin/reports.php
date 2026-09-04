<?php
/**
 * GET — Payroll & Billing summary report for Admin.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/client_billing_helper.php';

if (!isset($_SESSION['user_id'], $_SESSION['role']) || strcasecmp((string)$_SESSION['role'], 'Admin') !== 0) {
    http_response_code(401);
    echo 'Unauthorized';
    exit();
}

client_billing_ensure_schema($conn);
$runs = client_billing_list_runs($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll &amp; Billing Reports - Onazul HRIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>System Payroll &amp; Billing Reports</h2>
                <p class="text-muted">Comprehensive summary of all generated payroll runs and invoices.</p>
            </div>
            <a href="admin_dashboard.html" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i> Back to Dashboard</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Billing Ref No.</th>
                                <th>Client Company</th>
                                <th>Deployment Site</th>
                                <th>Cutoff Period</th>
                                <th>Employee Count</th>
                                <th>Total Invoice</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($runs)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No payroll runs recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($runs as $r): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($r['billing_ref_no'] ?? $r['run_id']) ?></strong></td>
                                        <td><?= htmlspecialchars($r['client_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['deployment_site'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['cutoff_period'] ?? '') ?></td>
                                        <td class="text-center"><?= (int)$r['employee_count'] ?></td>
                                        <td class="text-end">₱<?= number_format((float)($r['total_invoice'] ?? 0), 2) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($r['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>