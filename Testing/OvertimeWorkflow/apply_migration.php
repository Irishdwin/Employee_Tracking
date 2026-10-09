<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Db_connection/db_connect.php';
$sql=file_get_contents(__DIR__.'/../../sql/overtime_employee_response_migration.sql');
$conn->multi_query($sql);do{if($r=$conn->store_result())$r->free();}while($conn->more_results()&&$conn->next_result());
echo "Additive overtime migration applied. Existing records retained.\n";
