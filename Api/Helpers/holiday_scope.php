<?php
declare(strict_types=1);
require_once __DIR__.'/payroll_workflow.php';
function holiday_scope_ready(mysqli $db): bool {
    static $ready=[];$id=spl_object_id($db);
    if(!isset($ready[$id]))$ready[$id]=(bool)$db->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='holiday_deployment_sites'")->fetch_assoc()['n'];
    return $ready[$id];
}
function holiday_scope_ids(mysqli $db,int $holiday): array {
    if(!holiday_scope_ready($db))return [];
    return array_map('intval',array_column(payroll_query($db,'SELECT SiteID FROM holiday_deployment_sites WHERE HolidayID=? ORDER BY SiteID','i',[$holiday])->get_result()->fetch_all(MYSQLI_ASSOC),'SiteID'));
}
function holiday_scope_validate(mysqli $db,array $input): array {
    $sites=$input['site_ids'] ?? [];
    if(!is_array($sites) || count($sites)>1000)throw new InvalidArgumentException('Choose valid deployment sites.');
    $sites=array_values(array_unique(array_map('intval',$sites)));
    foreach($sites as $site)if(!payroll_query($db,'SELECT SiteID FROM deployment_sites WHERE SiteID=?','i',[$site])->get_result()->num_rows)throw new InvalidArgumentException('Deployment site not found.');
    return !empty($input['is_nationwide']) ? [] : $sites;
}
function holiday_scope_save(mysqli $db,int $holiday,array $sites): void {
    payroll_query($db,'DELETE FROM holiday_deployment_sites WHERE HolidayID=?','i',[$holiday]);
    foreach($sites as $site)payroll_query($db,'INSERT INTO holiday_deployment_sites (HolidayID,SiteID) VALUES(?,?)','ii',[$holiday,$site]);
}
/** Nationwide definitions apply everywhere; local definitions require configured scope. */
function holiday_applies_to_employee(mysqli $db,array $holiday,int $employee,string $date,?int $deployment=null): bool {
    if(!empty($holiday['IsNationwide']))return true;
    $sites=holiday_scope_ids($db,(int)$holiday['HolidayID']);
    $rows=payroll_query($db,'SELECT d.SiteID,d.Location,s.Address FROM deployment d LEFT JOIN deployment_sites s ON s.SiteID=d.SiteID AND s.CompanyID=d.CompanyID WHERE d.EmployeeID=? AND (d.StartDate IS NULL OR d.StartDate<=?) AND (d.EndDate IS NULL OR d.EndDate>=?) AND (?=0 OR d.DeploymentID=?)','issii',[$employee,$date,$date,$deployment ?? 0,$deployment ?? 0])->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach($rows as $row) {
        if($sites && in_array((int)$row['SiteID'],$sites,true))return true;
        if($sites)continue;
        $region=trim((string)($holiday['Region'] ?? ''));
        if($region!=='' && preg_match('/(?<![\pL\pN])'.preg_quote($region,'/').'(?![\pL\pN])/iu',(string)$row['Address'].' '.(string)$row['Location']))return true;
    }
    return false;
}
