<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Db_connection/db_connect.php';
foreach(['overtime','attendance','employee','leaves'] as $t){echo "$t\n";echo $conn->query("SHOW CREATE TABLE `$t`")->fetch_row()[1]."\n";}
echo json_encode($conn->query("SELECT StatusID,Name FROM status WHERE Name IN ('Pending','Accepted','Rejected','Active','Completed','Approved','Cancelled')")->fetch_all(MYSQLI_ASSOC));
