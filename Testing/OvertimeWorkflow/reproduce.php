<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Db_connection/db_connect.php';
$q=$conn->prepare('SELECT ?,?,?,?,?,?,?,?,?,?');$a=1;$b=null;$c=1;$d=date('Y-m-d');$e='18:00';$f='20:00';$g=2.0;$h=1;$i=1;$j='Test';
try{$q->bind_param('iiisssdis',$a,$b,$c,$d,$e,$f,$g,$h,$i,$j);echo 'Unexpected success';}catch(Throwable $err){echo get_class($err).': '.$err->getMessage()."\n";}
echo json_encode($conn->query("SELECT StatusID,Name FROM status WHERE Name IN ('Pending','Accepted','Rejected','Active','Completed','Approved')")->fetch_all(MYSQLI_ASSOC));
echo "\n".$conn->query('SHOW CREATE TABLE leaves')->fetch_row()[1];
