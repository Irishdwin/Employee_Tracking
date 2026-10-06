<?php
/** Mirror authenticated interaction activity without refreshing the idle clock. */
function messenger_record_activity(mysqli $conn, array $actor): void {
    $activity=min(time(),max(0,(int)($_SESSION['last_activity']??0)));
    if(!$activity)return;
    $stmt=$conn->prepare('UPDATE users SET MessengerLastActivity=? WHERE UsersID=? AND IsActive=1 AND (MessengerLastActivity IS NULL OR MessengerLastActivity<?)');
    $id=(int)$actor['user_id'];
    $stmt->bind_param('iii',$activity,$id,$activity);
    $stmt->execute();$stmt->close();
}
