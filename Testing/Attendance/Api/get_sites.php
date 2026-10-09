<?php
declare(strict_types=1);
require_once __DIR__.'/_testing_guard.php';require_once __DIR__.'/_selection_context.php';
$company=(int)($_GET['company_id']??0);if(!$company)testing_context_fail('Select a company.');
$condition=($_GET['history']??'')==='1' ? ' AND (s.IsActive=1 OR EXISTS(SELECT 1 FROM deployment d WHERE d.SiteID=s.SiteID))' : ' AND s.IsActive=1 AND c.IsActive=1';
$sites=site_query($conn,'SELECT s.SiteID site_id,s.Name name,s.Address address,c.PayrollType payroll_type FROM deployment_sites s JOIN clientcompany c ON c.CompanyID=s.CompanyID WHERE s.CompanyID=?'.$condition.' ORDER BY s.Name,s.SiteID','i',[$company])->fetch_all(MYSQLI_ASSOC);
echo json_encode(['success'=>true,'sites'=>$sites]);
