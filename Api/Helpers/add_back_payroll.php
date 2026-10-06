<?php
declare(strict_types=1);
function add_back_payroll_total(PDO $pdo,int $employee,int $deployment,string $start,string $end): float {
    // Optional migration keeps existing installations and isolated regression fixtures compatible.
    static $ready=[]; $key=spl_object_id($pdo);
    if (!array_key_exists($key,$ready)) $ready[$key]=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='add_back_requests'")->fetchColumn();
    if (!$ready[$key]) return 0.0;
    $q=$pdo->prepare("SELECT COALESCE(SUM(pa.Amount),0) FROM add_back_requests r JOIN payroll_adjustments pa ON pa.AddBackRequestID=r.RequestID JOIN status s ON s.StatusID=r.StatusID JOIN deployment d ON d.DeploymentID=r.DeploymentID AND d.EmployeeID=r.EmployeeID AND d.CompanyID=r.CompanyID WHERE r.EmployeeID=? AND r.DeploymentID=? AND r.TargetStart=? AND r.TargetEnd=? AND s.Name='Approved' AND pa.IsAddition=1");
    $q->execute([$employee,$deployment,$start,$end]); return round((float)$q->fetchColumn(),2);
}
/** Caller holds the employee lock and transaction containing the payroll insert. */
function add_back_post_payroll(mysqli $conn,int $payroll,int $employee,int $deployment,string $start,string $end): void {
    $exists=$conn->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='add_back_requests'")->fetch_assoc();
    if (!(int)$exists['n']) return;
    $q=$conn->prepare("SELECT r.RequestID,r.Payroll_ID,pa.Payroll_ID AdjustmentPayroll FROM add_back_requests r JOIN status s ON s.StatusID=r.StatusID JOIN payroll_adjustments pa ON pa.AddBackRequestID=r.RequestID WHERE r.EmployeeID=? AND r.DeploymentID=? AND r.TargetStart=? AND r.TargetEnd=? AND s.Name='Approved' FOR UPDATE");
    $q->bind_param('iiss',$employee,$deployment,$start,$end); $q->execute(); $rows=$q->get_result()->fetch_all(MYSQLI_ASSOC); $q->close();
    foreach ($rows as $r) {
        if ($r['Payroll_ID'] && (int)$r['Payroll_ID']!==$payroll) throw new RuntimeException('Add Back already belongs to another payroll.');
        if ($r['AdjustmentPayroll'] && (int)$r['AdjustmentPayroll']!==$payroll) throw new RuntimeException('Adjustment already posted to another payroll.');
        if ($r['Payroll_ID']) continue;
        $id=(int)$r['RequestID'];
        $q=$conn->prepare('UPDATE payroll_adjustments SET Payroll_ID=? WHERE AddBackRequestID=? AND Payroll_ID IS NULL'); $q->bind_param('ii',$payroll,$id); $q->execute(); $q->close();
        $q=$conn->prepare('UPDATE add_back_requests SET Payroll_ID=? WHERE RequestID=? AND Payroll_ID IS NULL'); $q->bind_param('ii',$payroll,$id); $q->execute(); $q->close();
        require_once __DIR__.'/AuditLogger.php';
        if (!AuditLogger::log($conn,'Add Back','Posted','Request #'.$id.' posted to payroll #'.$payroll,(string)$id)) throw new RuntimeException('Audit write failed.');
    }
}
