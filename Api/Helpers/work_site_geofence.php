<?php
/**
 * Resolve registered work-site geofence for an employee's active deployment.
 */

require_once __DIR__ . '/geofence_helper.php';


/** Never choose an arbitrary site when a Lead supervises multiple companies. */
function work_site_companies_for_team_lead(mysqli $conn, int $userId): array {
    $rows=sites_for_lead($conn,$userId);
    foreach($rows as &$row){$row['dep_registered_latitude']=$row['registered_latitude'];$row['dep_registered_longitude']=$row['registered_longitude'];}

    return $rows;
}
function work_site_company_for_team_lead(mysqli $conn,int $userId,int $requestedCompany=0): int {
    $rows=work_site_companies_for_team_lead($conn,$userId);
    if ($requestedCompany>0) $rows=array_values(array_filter($rows,static fn($row)=>(int)$row['SiteID']===$requestedCompany));
    if (count($rows)!==1) throw new DomainException(count($rows)>1?'Multiple work sites are assigned. Select the intended company before registering or loading its GPS.':'No active company is assigned to this Team Lead.');
    return (int)$rows[0]['SiteID'];
}

/** Same existing safety rule as the Employee dashboard: accuracy must fit the radius. */
function geofence_accuracy_is_acceptable($accuracy, $radius): bool {
    return is_numeric($accuracy) && is_numeric($radius) && is_finite((float)$accuracy)
        && is_finite((float)$radius) && (float)$accuracy >= 0 && (float)$radius > 0
        && (float)$accuracy <= (float)$radius;
}

if (!function_exists('geofence_coords_are_valid')) {
    function geofence_coords_are_valid($lat, $lng): bool
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }
        $lat = (float)$lat;
        $lng = (float)$lng;
        if ($lat === 0.0 && $lng === 0.0) {
            return false;
        }
        return abs($lat) <= 90.0 && abs($lng) <= 180.0;
    }
}

if (!function_exists('geofence_pick_coordinates')) {
    /**
     * Prefer TL-registered coordinates, then legacy latitude/longitude columns.
     *
     * @param array<string,mixed> $row
     * @return array{latitude:float,longitude:float,source:string}|null
     */
    function geofence_pick_coordinates(array $row, string $prefix): ?array
    {
        $pairs = [
            ['registered_latitude', 'registered_longitude', $prefix . '_registered'],
            ['latitude', 'longitude', $prefix . '_latitude'],
        ];
        foreach ($pairs as [$latSuffix, $lngSuffix, $source]) {
            $latKey = $prefix . '_' . $latSuffix;
            $lngKey = $prefix . '_' . $lngSuffix;
            $lat = $row[$latKey] ?? null;
            $lng = $row[$lngKey] ?? null;
            if (geofence_coords_are_valid($lat, $lng)) {
                return [
                    'latitude' => (float)$lat,
                    'longitude' => (float)$lng,
                    'source' => $source,
                ];
            }
        }
        return null;
    }
}

if (!function_exists('work_site_resolve_for_user')) {
    /**
     * @return array<string,mixed>|null
     */
    function work_site_resolve_for_user(mysqli $conn, int $userId): ?array
    {
        $count=$conn->prepare("SELECT COUNT(*) AS n FROM users u
            JOIN employee e ON e.Email=u.Email AND e.IsActive=1
            JOIN deployment d ON d.EmployeeID=e.EmployeeID AND d.StartDate<=CURDATE()
              AND (d.EndDate IS NULL OR d.EndDate>=CURDATE())
            JOIN status st ON st.StatusID=d.StatusID AND st.Name='Active'
            WHERE u.UsersID=? AND u.IsActive=1");
        $count->bind_param('i',$userId);$count->execute();
        $matches=(int)$count->get_result()->fetch_assoc()['n'];$count->close();
        if($matches>1)throw new DomainException('Multiple current deployments were found. Please contact HR to correct your deployment.',409);
        $sql = "
            SELECT
                d.DeploymentID,
                d.CompanyID,
                s.TeamLeadID AS SiteSuperVisorID,
                d.SiteID,
                s.Name AS site_name,
                s.Address AS deployment_location,
                dep_co.Name AS company_name,
                s.registered_latitude AS dep_registered_latitude,
                s.registered_longitude AS dep_registered_longitude,
                s.registered_latitude AS dep_latitude,
                s.registered_longitude AS dep_longitude,
                s.geofence_radius AS dep_geofence_radius,
                e.EmployeeID,
                e.EmployeeNumber,
                tl.FullName AS team_lead_name
            FROM users u
            INNER JOIN employee e ON e.Email = u.Email AND e.IsActive = 1
            INNER JOIN deployment d ON d.EmployeeID = e.EmployeeID
                AND d.StartDate <= CURDATE() AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
            INNER JOIN status ds ON ds.StatusID = d.StatusID AND ds.Name = 'Active'
            INNER JOIN clientcompany dep_co ON dep_co.CompanyID = d.CompanyID AND dep_co.IsActive = 1
            INNER JOIN deployment_sites s ON s.SiteID=d.SiteID AND s.CompanyID=d.CompanyID AND s.IsActive=1
            LEFT JOIN users tl ON tl.UsersID = s.TeamLeadID
            INNER JOIN roles r ON r.RolesID=u.RoleID
            WHERE u.UsersID = ? AND u.IsActive=1 AND r.RoleName='Employee'
            ORDER BY d.StartDate DESC, d.DeploymentID DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        $picked = geofence_pick_coordinates($row, 'dep');
        $radius = (int)($row['dep_geofence_radius'] ?? 0);
        if ($radius <= 0) {
            $radius = 100;
        }

        return [
            'site_id' => (int)$row['SiteID'],
            'site_name' => (string)$row['site_name'],
            'deployment_id' => (int)$row['DeploymentID'],
            'employee_id' => (int)$row['EmployeeID'],
            'company_id' => (int)$row['CompanyID'],
            'company_name' => (string)($row['company_name'] ?? ''),
            'deployment_location' => (string)($row['deployment_location'] ?? ''),
            'team_lead_name' => (string)($row['team_lead_name'] ?? ''),
            'site_supervisor_id' => isset($row['SiteSuperVisorID']) ? (int)$row['SiteSuperVisorID'] : null,
            'site_latitude' => $picked['latitude'] ?? null,
            'site_longitude' => $picked['longitude'] ?? null,
            'geofence_radius' => $radius,
            'coordinate_source' => $picked['source'] ?? null,
            'site_registered' => $picked !== null,
        ];
    }
}

if (!function_exists('geofence_validate_employee_position')) {
    /**
     * @return array{allowed:bool,distance_meters:float,message:string,site:array<string,mixed>}
     */
    function geofence_validate_employee_position(
        mysqli $conn,
        int $userId,
        float $employeeLat,
        float $employeeLng
    ): array {
        $site = work_site_resolve_for_user($conn, $userId);
        if (!$site) {
            return [
                'allowed' => false,
                'distance_meters' => 0.0,
                'message' => 'You are not assigned to an active deployment site.',
                'site' => [],
            ];
        }

        if (!$site['site_registered']
            || !geofence_coords_are_valid($site['site_latitude'], $site['site_longitude'])) {
            return [
                'allowed' => false,
                'distance_meters' => 0.0,
                'message' => 'Your Team Leader has not registered the official Work Site coordinates yet.',
                'site' => $site,
            ];
        }

        if (!geofence_coords_are_valid($employeeLat, $employeeLng)) {
            return [
                'allowed' => false,
                'distance_meters' => 0.0,
                'message' => 'Valid GPS location is required to verify attendance.',
                'site' => $site,
            ];
        }

        $siteLat = (float)$site['site_latitude'];
        $siteLng = (float)$site['site_longitude'];
        $radius = (float)$site['geofence_radius'];

        $distanceMeters = calculateHaversineDistance(
            $employeeLat,
            $employeeLng,
            $siteLat,
            $siteLng
        );

        if ($distanceMeters > $radius) {
            return [
                'allowed' => false,
                'distance_meters' => $distanceMeters,
                'message' => sprintf(
                    'You are outside the allowed work site. (Distance: %.2fm, Max Radius: %.0fm)',
                    $distanceMeters,
                    $radius
                ),
                'site' => $site,
            ];
        }

        return [
            'allowed' => true,
            'distance_meters' => $distanceMeters,
            'message' => sprintf('Inside geofence (%.2fm from site).', $distanceMeters),
            'site' => $site,
        ];
    }
}


/** Site identity and current authority shared by roster, geofence, schedules and DTR. */
function site_query(mysqli $conn,string $sql,string $types='',array $values=[]): mysqli_result {
    $s=$conn->prepare($sql); if($types!=='')$s->bind_param($types,...$values);
    $s->execute();$r=$s->get_result();$s->close();return $r;
}
function site_current_deployment_sql(string $alias='d'): string {
    return "$alias.StartDate<=CURDATE() AND ($alias.EndDate IS NULL OR $alias.EndDate>=CURDATE())
      AND EXISTS(SELECT 1 FROM status st WHERE st.StatusID=$alias.StatusID AND st.Name='Active')
      AND $alias.DeploymentID=(SELECT dx.DeploymentID FROM deployment dx JOIN status sx ON sx.StatusID=dx.StatusID AND sx.Name='Active'
        WHERE dx.EmployeeID=$alias.EmployeeID AND dx.StartDate<=CURDATE() AND (dx.EndDate IS NULL OR dx.EndDate>=CURDATE())
        ORDER BY dx.StartDate DESC,dx.DeploymentID DESC LIMIT 1)";
}
function sites_for_lead(mysqli $conn,int $lead): array {
    $current=site_current_deployment_sql();
    return site_query($conn,"SELECT s.*,c.Name CompanyName,c.PayrollType,u.FullName TeamLeadName,
       (SELECT COUNT(DISTINCT d.EmployeeID) FROM deployment d JOIN employee e ON e.EmployeeID=d.EmployeeID AND e.IsActive=1
         WHERE d.SiteID=s.SiteID AND $current) EmployeeCount
       FROM deployment_sites s JOIN clientcompany c ON c.CompanyID=s.CompanyID AND c.IsActive=1
       JOIN users u ON u.UsersID=s.TeamLeadID AND u.IsActive=1 JOIN roles r ON r.RolesID=u.RoleID AND r.RoleName IN ('TeamLead','Team Lead')
       WHERE s.TeamLeadID=? AND s.IsActive=1 ORDER BY c.Name,s.Name,s.SiteID",'i',[$lead])->fetch_all(MYSQLI_ASSOC);
}
function site_for_lead(mysqli $conn,int $lead,int $siteId): array {
    foreach(sites_for_lead($conn,$lead) as $site)if((int)$site['SiteID']===$siteId)return $site;
    throw new DomainException('This active deployment site is not assigned to you.',403);
}
function site_owned_employee(mysqli $conn,int $lead,int $employee,int $siteId=0): ?array {
    $current=site_current_deployment_sql();
    return site_query($conn,"SELECT d.* FROM deployment d JOIN deployment_sites s ON s.SiteID=d.SiteID AND s.IsActive=1
      JOIN clientcompany c ON c.CompanyID=s.CompanyID AND c.IsActive=1
      JOIN employee e ON e.EmployeeID=d.EmployeeID AND e.IsActive=1
      JOIN users u ON u.UsersID=s.TeamLeadID AND u.IsActive=1 JOIN roles r ON r.RolesID=u.RoleID AND r.RoleName IN ('TeamLead','Team Lead')
      WHERE e.EmployeeID=? AND s.TeamLeadID=? AND (?=0 OR s.SiteID=?) AND $current",'iiii',[$employee,$lead,$siteId,$siteId])->fetch_assoc();
}
/** Legacy hiring may resolve only an unambiguous site. Never guess a branch. */
function site_resolve_legacy(mysqli $conn,int $company,string $location): array {
    $rows=site_query($conn,'SELECT * FROM deployment_sites WHERE CompanyID=? AND IsActive=1 ORDER BY SiteID','i',[$company])->fetch_all(MYSQLI_ASSOC);
    $matched=array_values(array_filter($rows,static fn($s)=>trim((string)$s['Address'])===trim($location)||trim($s['Name'])===trim($location)));
    if(count($matched)===1)return $matched[0];
    if(count($rows)===1)return $rows[0];
    throw new DomainException('Select the correct deployment site; this company has multiple or unconfigured sites.',422);
}

/** Actual current deployments requiring DTR, excluding already submitted/verified rows. */
function site_dtr_pending(mysqli $conn,int $lead,int $siteId,array $period): array {
    $current=site_current_deployment_sql();
    return site_query($conn,"SELECT d.EmployeeID,d.DeploymentID FROM deployment d
      JOIN deployment_sites s ON s.SiteID=d.SiteID AND s.IsActive=1
      JOIN employee e ON e.EmployeeID=d.EmployeeID AND e.IsActive=1
      WHERE s.TeamLeadID=? AND s.SiteID=? AND COALESCE(d.AllowDTRSubmission,1)=1 AND $current
      AND d.StartDate<=? AND (d.EndDate IS NULL OR d.EndDate>=?)
      AND NOT EXISTS(SELECT 1 FROM dtr_submissions ds WHERE ds.EmployeeID=d.EmployeeID
        AND ds.CutoffStartDate=? AND ds.CutoffEndDate=?)",'iissss',[$lead,$siteId,$period['end'],$period['start'],$period['start'],$period['end']])->fetch_all(MYSQLI_ASSOC);
}
function site_dtr_obligations(mysqli $conn,int $lead,?string $today=null): array {
    require_once __DIR__.'/payroll_type_helper.php';
    $today=$today??date('Y-m-d');$items=[];
    foreach(sites_for_lead($conn,$lead) as $site){
        $current=site_current_deployment_sql();
        $min=site_query($conn,"SELECT MIN(d.StartDate) FirstDate FROM deployment d JOIN employee e ON e.EmployeeID=d.EmployeeID AND e.IsActive=1
          WHERE d.SiteID=? AND COALESCE(d.AllowDTRSubmission,1)=1 AND $current",'i',[(int)$site['SiteID']])->fetch_assoc()['FirstDate'];
        if(!$min)continue;
        $period=payroll_type_period($site['PayrollType'],$min);
        while($period['start']<=$today){
            $remind=(new DateTimeImmutable($period['payday']))->modify('-5 days')->format('Y-m-d');
            if($remind<=$today && site_dtr_pending($conn,$lead,(int)$site['SiteID'],$period)){
                $items[]=['site_id'=>(int)$site['SiteID'],'site_name'=>$site['Name'],'company_name'=>$site['CompanyName'],
                  'period'=>$period,'reminder_date'=>$remind];
            }
            $period=payroll_type_period($site['PayrollType'],(new DateTimeImmutable($period['end']))->modify('+1 day')->format('Y-m-d'));
        }
    }
    return $items;
}
/** Lazy generation: shared notification helper, unique business key and serialized user refresh. */
function site_dtr_sync_notifications(mysqli $conn,int $lead): array {
    require_once __DIR__.'/../Common/NotificationHelper.php';
    $lock='onazul:dtr:'.$lead;
    if((int)site_query($conn,'SELECT GET_LOCK(?,5) acquired','s',[$lock])->fetch_assoc()['acquired']!==1)throw new RuntimeException('DTR reminders are busy.');
    try {
        $items=site_dtr_obligations($conn,$lead);$keys=[];
        foreach($items as $item){
            $p=$item['period'];$key=$lead.':'.$item['site_id'].':'.$p['start'].':'.$p['end'].':dtr';$keys[$key]=true;
            if(site_query($conn,'SELECT NotificationID FROM notifications WHERE DTRReminderKey=?','s',[$key])->num_rows)continue;
            $conn->begin_transaction();
            try {
                $module='dtr_cutoff:'.$p['start'];
                $message=$item['company_name'].' — '.$item['site_name']."\nCutoff: ".$p['label']."\nPayroll release: ".$p['payday_display']."\nPlease review and submit this site's employee DTRs.";
                if(!insert_system_notification($conn,$lead,'dtr_submission_reminder','DTR Submission Reminder',$message,$module,$item['site_id']))throw new RuntimeException('Reminder could not be saved.');
                $id=(int)$conn->insert_id;
                $st=$conn->prepare('UPDATE notifications SET DTRReminderKey=? WHERE NotificationID=?');$st->bind_param('si',$key,$id);$st->execute();$st->close();$conn->commit();
            }catch(Throwable $e){$conn->rollback();throw $e;}
        }
        foreach(site_query($conn,'SELECT NotificationID,DTRReminderKey FROM notifications WHERE UserID=? AND DTRReminderKey IS NOT NULL AND IsRead=0','i',[$lead])->fetch_all(MYSQLI_ASSOC) as $n){
            if(!isset($keys[$n['DTRReminderKey']])){$st=$conn->prepare('UPDATE notifications SET IsRead=1 WHERE NotificationID=?');$st->bind_param('i',$n['NotificationID']);$st->execute();$st->close();}
        }
        return $items;
    }finally{site_query($conn,'SELECT RELEASE_LOCK(?)','s',[$lock]);}
}
