<?php
require __DIR__ . '/../Api/Db_connection/db_connect.php';

echo "=== Simple WHERE CompanyID=19 ===" . PHP_EOL;
$r = $conn->query("SELECT jo.JobOrderID, jo.CompanyID, c.Name FROM joborder jo INNER JOIN clientcompany c ON jo.CompanyID=c.CompanyID WHERE c.IsActive=1 AND jo.CompanyID=19");
while ($row = $r->fetch_assoc()) {
    echo $row['JobOrderID'] . ' cid=' . $row['CompanyID'] . ' @ ' . $row['Name'] . PHP_EOL;
}

echo PHP_EOL . "=== IsActive only + CompanyID=19 ===" . PHP_EOL;
$r2 = $conn->query("SELECT COUNT(*) AS cnt FROM joborder jo INNER JOIN clientcompany c ON jo.CompanyID=c.CompanyID WHERE c.IsActive=1 AND jo.CompanyID=19");
$row2 = $r2->fetch_assoc();
echo 'count=' . $row2['cnt'] . PHP_EOL;
