<?php
// CLI-only test against the isolated multi-site test database; no production mutations.
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Helpers/contract_expiry_helper.php';
$c=new mysqli('localhost','root','','codex_multisite_20261005_r2');
$ids=[];$backup=null;
try {
$c->query("UPDATE employee SET IsActive=1 WHERE EmployeeID IN (4,5)");
$c->query("UPDATE employee SET IsActive=0 WHERE EmployeeID=6");
foreach([[4,'DATE_SUB(CURDATE(),INTERVAL 6 MONTH)'],[5,'DATE_SUB(CURDATE(),INTERVAL 5 MONTH)'],[6,'DATE_SUB(CURDATE(),INTERVAL 7 MONTH)']] as [$employee,$date]) {
$c->query("INSERT INTO employmentcontract(EmployeeID,ContractNumber,ContractType,SignedDate,ContractEndDate) VALUES ($employee,'CODEX-POLICY','Expiring Contract',$date,DATE_ADD(CURDATE(),INTERVAL 1 YEAR))");$ids[]=$c->insert_id;
}
$backup=$c->query("SELECT ja.ApplicationID,ja.Remarks FROM job_applications ja JOIN applicants a ON a.ApplicantID=ja.ApplicantID JOIN employee e ON e.Email=a.Email AND e.IsActive=1 WHERE ja.HiredDate IS NOT NULL LIMIT 1")->fetch_assoc();
if(!$backup)throw new Exception('Missing linked hired fixture');
$remark="Preserved HR note\nContract uploaded: type=Expiring Contract; years=2; months=0; signed_date=2026-03-01; expiry_date=2028-03-01; path=../../uploads/original.pdf";
$st=$c->prepare('UPDATE job_applications SET Remarks=? WHERE ApplicationID=?');$st->bind_param('si',$remark,$backup['ApplicationID']);$st->execute();
employment_contract_open_after_six_months($c);
$converted=$c->query('SELECT Remarks FROM job_applications WHERE ApplicationID='.(int)$backup['ApplicationID'])->fetch_assoc()['Remarks'];
if(!str_contains($converted,'type=Open Contract;')||str_contains($converted,'expiry_date=2028')||!str_contains($converted,'Preserved HR note')||!str_contains($converted,'../../uploads/original.pdf'))throw new Exception('Remarks conversion/preservation failed');
$rows=$c->query('SELECT ContractType,ContractEndDate FROM employmentcontract WHERE EmploymentContractID IN ('.implode(',',$ids).') ORDER BY EmploymentContractID')->fetch_all(MYSQLI_ASSOC);
if($rows[0]['ContractType']!=='Open Contract'||$rows[0]['ContractEndDate']!==null)throw new Exception('Six-month conversion failed');
if($rows[1]['ContractType']!=='Expiring Contract')throw new Exception('Premature conversion');
if($rows[2]['ContractType']!=='Expiring Contract')throw new Exception('Inactive employee converted');
if(employment_contract_open_after_six_months($c)!==0)throw new Exception('Not idempotent');
echo "PASS six-month signed-date boundary, shorter duration, inactive exclusion, expiry clearing, legacy metadata/file preservation and idempotence\n";
} finally {
if($backup){$st=$c->prepare('UPDATE job_applications SET Remarks=? WHERE ApplicationID=?');$st->bind_param('si',$backup['Remarks'],$backup['ApplicationID']);$st->execute();}
if($ids)$c->query('DELETE FROM employmentcontract WHERE EmploymentContractID IN ('.implode(',',$ids).')');
$c->query('UPDATE employee SET IsActive=1 WHERE EmployeeID=6');
}
