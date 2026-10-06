<?php
declare(strict_types=1);
require_once __DIR__.'/_testing_guard.php';require_once __DIR__.'/_selection_context.php';
$company=(int)($_GET['company_id']??0);if(!$company)testing_context_fail('Select a company.');
$sites=site_query($conn,'SELECT s.SiteID site_id,s.Name name,s.Address address,c.PayrollType payroll_type FROM deployment_sites s JOIN clientcompany c ON c.CompanyID=s.CompanyID AND c.IsActive=1 WHERE s.CompanyID=? AND s.IsActive=1 ORDER BY s.Name,s.SiteID','i',[$company])->fetch_all(MYSQLI_ASSOC);
echo json_encode(['success'=>true,'sites'=>$sites]);
