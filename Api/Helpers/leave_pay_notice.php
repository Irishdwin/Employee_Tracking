<?php
declare(strict_types=1);
require_once __DIR__.'/payroll_workflow.php';
require_once __DIR__.'/../Common/NotificationHelper.php';
/** This announces the agency policy; it does not calculate or award an amount. */
function employee_leave_pay_notice(mysqli $db,int $user): void {
    $db->begin_transaction();
    try {
        $actor=payroll_query($db,"SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1 AND r.RoleName='Employee' FOR UPDATE",'i',[$user])->get_result()->fetch_assoc();
        if($actor && !payroll_query($db,"SELECT NotificationID FROM notifications WHERE UserID=? AND NotificationType='leave_pay_policy' LIMIT 1",'i',[$user])->get_result()->num_rows) {
            if(!insert_system_notification($db,$user,'leave_pay_policy','Leave Pay Notice','Your leave pay will be paid together with your 13th-month pay.','employee_payroll',null))throw new RuntimeException('Unable to save leave pay notice.');
        }
        $db->commit();
    } catch(Throwable $e) {$db->rollback();throw $e;}
}
