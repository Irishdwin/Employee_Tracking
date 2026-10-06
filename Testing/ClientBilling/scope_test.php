<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Helpers/client_billing_helper.php';
$c=new mysqli('localhost','root','','codex_multisite_20261005_r2');
$run=['CompanyID'=>30,'PeriodStart'=>'2026-09-11','PeriodEnd'=>'2026-09-25'];
$c->begin_transaction();
try{
$rows=client_billing_fetch_employees_for_run($c,$run);
if(count($rows)!==2 || array_unique(array_column($rows,'SiteID'))!=[6])throw new Exception('Verified branch scope');echo "PASS verified branch only\n";
$empty=client_billing_fetch_employees_for_run($c,array_replace($run,['PeriodStart'=>'2025-01-01','PeriodEnd'=>'2025-01-15']));if($empty)throw new Exception('Fallback leak');echo "PASS empty cutoff has no deployment fallback\n";
$c->query("UPDATE dtr_submissions SET ReviewedBy=3,ReviewDate=NOW() WHERE SiteID=7 AND CutoffStartDate='2026-09-11'");
try{client_billing_fetch_employees_for_run($c,$run);throw new Exception('Multi-site export accepted');}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'multiple deployment sites'))throw $e;echo "PASS ambiguous multi-site run rejected\n";}
}finally{$c->rollback();}
