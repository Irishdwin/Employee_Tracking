<?php
/** Read-only workforce view; shares the dashboard's workforce predicates. */
require_once __DIR__.'/hr_dashboard_helper.php';

function hrem_rows(mysqli $db, string $sql, string $types='', array $params=[]): array {
    $stmt=$db->prepare($sql);
    if ($types!=='') $stmt->bind_param($types,...$params);
    $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return $rows;
}
function hrem_int(array $input,string $key,int $default,int $minimum=1): int {
    $raw=$input[$key]??$default;
    if($minimum===0&&$raw==='')$raw=0;
    $value=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>$minimum,'max_range'=>1000000]]);
    if ($value===false) throw new DomainException('Invalid '.$key.'.',400);
    return $value;
}
function hrem_text(array $input,string $key,int $max=150): string {
    $value=$input[$key]??'';
    if (!is_string($value)||mb_strlen($value)>$max) throw new DomainException('Invalid '.$key.'.',400);
    return trim($value);
}
function hrem_from(): string {
    // Same current-deployment rule and latest tie-break as the HR overview.
    return " FROM employee e LEFT JOIN status s ON s.StatusID=e.StatusID
        LEFT JOIN deployment d ON d.DeploymentID=(SELECT cd.DeploymentID FROM deployment cd
            WHERE cd.EmployeeID=e.EmployeeID AND e.IsActive=1 AND cd.StartDate<=CURDATE()
            AND (cd.EndDate IS NULL OR cd.EndDate>=CURDATE())
            ORDER BY cd.StartDate DESC,cd.DeploymentID DESC LIMIT 1)
        LEFT JOIN clientcompany c ON c.CompanyID=d.CompanyID";
}
function hrem_columns(): string {
    return "e.EmployeeID,e.EmployeeNumber,e.FirstName,e.LastName,e.Position,e.EmployeeType,e.HiredDate,
        e.StatusID,COALESCE(s.Name,'Unspecified') RecordedStatus,e.IsActive,
        d.DeploymentID,d.CompanyID,c.Name CompanyName,d.Position DeploymentPosition,d.Location WorkSite";
}
function hrem_options(mysqli $db): array {
    $from=hrem_from();
    $labels=['total'=>'All employees','active'=>'Active employees','inactive'=>'Inactive employees','awol'=>'AWOL employees','resigned'=>'Resigned employees','recent_hires'=>'Recently hired (30 days)','deployed'=>'Currently deployed'];
    $workforce=[];
    foreach(hrd_filters() as $key=>$predicate) $workforce[]=['value'=>$key,'label'=>$labels[$key]];
    return [
        'workforce'=>$workforce,
        'companies'=>hrem_rows($db,"SELECT DISTINCT c.CompanyID id,c.Name label".$from." WHERE c.CompanyID IS NOT NULL ORDER BY c.Name,c.CompanyID"),
        'sites'=>hrem_rows($db,"SELECT DISTINCT d.Location value".$from." WHERE d.Location IS NOT NULL AND TRIM(d.Location)<>'' ORDER BY d.Location"),
        'positions'=>hrem_rows($db,"SELECT DISTINCT e.Position value FROM employee e WHERE e.Position IS NOT NULL AND TRIM(e.Position)<>'' ORDER BY e.Position"),
        'types'=>hrem_rows($db,"SELECT DISTINCT e.EmployeeType value FROM employee e WHERE e.EmployeeType IS NOT NULL AND TRIM(e.EmployeeType)<>'' ORDER BY e.EmployeeType"),
    ];
}
function hrem_list(mysqli $db,array $input): array {
    $filter=hrem_text($input,'filter',30)?:'total';
    if(!isset(hrd_filters()[$filter])) throw new DomainException('Invalid workforce filter.',400);
    $page=hrem_int($input,'page',1);
    $company=hrem_int($input,'company_id',0,0);
    $q=hrem_text($input,'q',100);$site=hrem_text($input,'site');
    $position=hrem_text($input,'position',100);$type=hrem_text($input,'employee_type',50);
    $where=' WHERE '.hrd_filters()[$filter];$types='';$params=[];
    if($q!=='') {
        // Search literal text; % and _ supplied by the user are not wildcards.
        $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';
        $where.=" AND (e.EmployeeNumber LIKE ? ESCAPE '!' OR e.FirstName LIKE ? ESCAPE '!' OR e.LastName LIKE ? ESCAPE '!' OR CONCAT(e.FirstName,' ',e.LastName) LIKE ? ESCAPE '!' OR e.Position LIKE ? ESCAPE '!')";
        $types.='sssss';$params=array_merge($params,array_fill(0,5,$like));
    }
    foreach([['c.CompanyID',$company,'i'],['d.Location',$site,'s'],['e.Position',$position,'s'],['e.EmployeeType',$type,'s']] as [$column,$value,$binding]){
        if($value!==''&&$value!==0){$where.=" AND $column=?";$types.=$binding;$params[]=$value;}
    }
    $from=hrem_from();$total=(int)hrem_rows($db,'SELECT COUNT(*) n'.$from.$where,$types,$params)[0]['n'];
    $pages=max(1,(int)ceil($total/20));$page=min($page,$pages);
    $rows=hrem_rows($db,'SELECT '.hrem_columns().$from.$where.' ORDER BY e.LastName,e.FirstName,e.EmployeeID LIMIT 20 OFFSET ?',$types.'i',array_merge($params,[($page-1)*20]));
    return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'page_size'=>20,'filter'=>$filter,'as_of'=>date('c')];
}
function hrem_details(mysqli $db,array $input): array {
    $id=hrem_int($input,'employee_id',0);
    // Validate legacy callers' page input, but return the complete history regardless of page.
    if(isset($input['detail_page']))hrem_int($input,'detail_page',1);
    $rows=hrem_rows($db,"SELECT ".hrem_columns().",e.Email,dep.Description Department,e.SSSNumber,e.PhilHealthNumber,e.PagIBIGNumber,
        d.StartDate CurrentStartDate,d.EndDate CurrentEndDate,ds.Name CurrentDeploymentStatus,du.FullName CurrentTeamLead".
        hrem_from().' LEFT JOIN department dep ON dep.DepartmentID=e.DepartmentID LEFT JOIN status ds ON ds.StatusID=d.StatusID LEFT JOIN users du ON du.UsersID=d.SiteSuperVisorID WHERE e.EmployeeID=?','i',[$id]);
    if(!$rows) throw new DomainException('Employee not found.',404);
    $deployments=hrem_rows($db,"SELECT d.DeploymentID,d.CompanyID,c.Name CompanyName,d.Position,d.Location,d.StartDate,d.EndDate,
        COALESCE(s.Name,'Unspecified') RecordedStatus,u.FullName TeamLeadName,
        (d.StartDate<=CURDATE() AND (d.EndDate IS NULL OR d.EndDate>=CURDATE())) CurrentDates
        FROM deployment d LEFT JOIN clientcompany c ON c.CompanyID=d.CompanyID
        LEFT JOIN status s ON s.StatusID=d.StatusID LEFT JOIN users u ON u.UsersID=d.SiteSuperVisorID
        WHERE d.EmployeeID=? ORDER BY (d.DeploymentID=?) DESC,d.StartDate DESC,d.DeploymentID DESC",'ii',[$id,(int)($rows[0]['DeploymentID']??0)]);
    $resignation=hrem_rows($db,"SELECT rr.EffectiveDate,COALESCE(s.Name,'Unspecified') RecordedStatus FROM resignationrequest rr
        LEFT JOIN status s ON s.StatusID=rr.StatusID WHERE rr.EmployeeID=? ORDER BY rr.ResignationID DESC LIMIT 1",'i',[$id]);
    return ['employee'=>$rows[0],'deployments'=>$deployments,'resignation'=>$resignation[0]??null];
}

