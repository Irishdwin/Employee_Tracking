<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../Api/Db_connection/db_connect.php';
$conn->multi_query(file_get_contents(__DIR__.'/../sql/employee_exit_workflow_migration.sql'));
do{if($result=$conn->store_result())$result->free();}while($conn->more_results()&&$conn->next_result());
echo "Additive employee exit migration applied. Existing records retained.\n";
