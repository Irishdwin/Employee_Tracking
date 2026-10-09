<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Db_connection/db_connect.php';
echo $conn->query('SHOW CREATE TABLE salary_rates')->fetch_row()[1].PHP_EOL;
echo json_encode($conn->query('SELECT RequestID,EmployeeID,DeploymentID,CompanyID,WorkDate,RateSnapshot FROM add_back_requests WHERE RequestID=1')->fetch_assoc(),JSON_PRETTY_PRINT).PHP_EOL;
echo json_encode($conn->query('SELECT sr.* FROM salary_rates sr JOIN add_back_requests r ON r.EmployeeID=sr.EmployeeID WHERE r.RequestID=1')->fetch_all(MYSQLI_ASSOC),JSON_PRETTY_PRINT).PHP_EOL;
