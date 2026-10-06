<?php
/** Indexed local PSGC lookups imported from the official PSA workbook. */
function psgc_selector_groups(): array {
    // User-requested geographic groupings; never rewrite official parent records.
    return ['1030500000'=>'1004300000','1030900000'=>'1003500000'];
}
function psgc_query(string $sql, array $params=[]): array {
    global $conn;
    if(!isset($conn))throw new RuntimeException('PSGC database connection required.');
    $stmt=$conn->prepare($sql);
    if($params)$stmt->bind_param(str_repeat('s',count($params)),...$params);
    $stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
function psgc_node(array $row): array {
    $types=['Reg'=>'region','Prov'=>'province','City'=>'city','Mun'=>'municipality','SubMun'=>'submunicipality','Bgy'=>'barangay','Group'=>'group'];
    if($row['GeographicLevel']==='City')$type=['CC'=>'component_city','HUC'=>'highly_urbanized_city','ICC'=>'independent_component_city'][$row['CityClass']]??'city';
    else $type=$types[$row['GeographicLevel']];
    return ['id'=>$row['PSGCCode'],'name'=>$row['Name'],'type'=>$type,'parent'=>$row['ParentPSGCCode']??'',
        'region'=>$row['RegionCode'],'province'=>$row['ProvinceCode']??'','city'=>$row['CityMunicipalityCode']??'', 'old_names'=>$row['OldNames']??'',
        // Requested geographic presentation; official administrative data stays intact.
        'selector_province'=>psgc_selector_groups()[$row['PSGCCode']]??($row['ProvinceCode']??'')];
}
function psgc_metadata(): array {
    $rows=psgc_query("SELECT SourceVersion FROM psgc_locations WHERE GeographicLevel='Reg' LIMIT 1");
    if(!$rows)throw new RuntimeException('PSGC data has not been imported.');
    return ['version'=>$rows[0]['SourceVersion']];
}
function psgc_record(string $code): ?array {
    if(!preg_match('/^\d{10}$/',$code))return null;
    $rows=psgc_query('SELECT * FROM psgc_locations WHERE PSGCCode=?',[$code]);
    return $rows?psgc_node($rows[0]):null;
}
function psgc_items(string $level,string $parent=''): array {
    if($level==='regions')$rows=psgc_query("SELECT * FROM psgc_locations WHERE GeographicLevel='Reg' ORDER BY Name");
    else {
        $node=psgc_record($parent);
        if(!$node)throw new DomainException('Select a valid parent location.');
        if($level==='provinces' && $node['type']==='region')$rows=psgc_query("SELECT * FROM psgc_locations WHERE ParentPSGCCode=? AND GeographicLevel='Prov' ORDER BY Name",[$parent]);
        elseif($level==='cities' && $node['type']==='province')$rows=psgc_query("SELECT * FROM psgc_locations WHERE ProvinceCode=? AND GeographicLevel IN ('City','Mun') ORDER BY Name",[$parent]);
        elseif($level==='cities' && $node['type']==='region')$rows=psgc_query("SELECT * FROM psgc_locations WHERE RegionCode=? AND ProvinceCode IS NULL AND GeographicLevel IN ('City','Mun') ORDER BY Name",[$parent]);
        elseif($level==='barangays' && in_array($node['type'],['municipality','city','component_city','highly_urbanized_city','independent_component_city'],true))$rows=psgc_query("SELECT * FROM psgc_locations WHERE CityMunicipalityCode=? AND GeographicLevel='Bgy' ORDER BY Name",[$parent]);
        else throw new DomainException('Invalid parent for this geographic level.');
    }
    if($level==='cities' && in_array($parent,psgc_selector_groups(),true)){
        foreach(psgc_selector_groups() as $city=>$province)if($parent===$province)$rows=array_merge($rows,psgc_query('SELECT * FROM psgc_locations WHERE PSGCCode=?',[$city]));
        usort($rows,fn($a,$b)=>strcasecmp($a['Name'],$b['Name']));
    }
    return array_map('psgc_node',$rows);
}
function psgc_validate_address(array $codes): array {
    foreach(['region','province','city','barangay'] as $key)if(isset($codes[$key]) && !is_string($codes[$key]))throw new DomainException('Invalid location code.');
    $r=psgc_record($codes['region']??'');$p=psgc_record($codes['province']??'');$c=psgc_record($codes['city']??'');$b=psgc_record($codes['barangay']??'');
    if(!$r || $r['type']!=='region' || !$c || !in_array($c['type'],['municipality','city','component_city','highly_urbanized_city','independent_component_city'],true) || $c['region']!==$r['id'])throw new DomainException('Select a valid region and city.');
    if($c['province']) {
        if(!$p || $p['type']!=='province' || $p['id']!==$c['province'] || $p['region']!==$r['id'])throw new DomainException('The province does not belong to this address.');
    } elseif(($codes['province']??'')!=='')throw new DomainException('This city has no province in the PSGC hierarchy.');
    if(!$b || $b['type']!=='barangay' || $b['city']!==$c['id'])throw new DomainException('The barangay does not belong to this city/municipality.');
    return ['region'=>$r['name'],'state_province'=>$p?$p['name']:'','city'=>$c['name'],'barangay'=>$b['name'],
        'psgc'=>['region'=>$r['id'],'province'=>$p?$p['id']:'','city'=>$c['id'],'barangay'=>$b['id']]];
}
function psgc_name_key(string $name): string {
    $s=mb_strtolower(trim($name));$s=preg_replace('/\b(city of|city)\b/u','',$s);
    return preg_replace('/[^\p{L}\p{N}]/u','',$s);
}
/** Compatibility-only scan of cities; barangays remain restricted to matching parents. */
function psgc_resolve_legacy(array $profile): ?array {
    $matches=[];
    foreach(psgc_query("SELECT * FROM psgc_locations WHERE GeographicLevel IN ('City','Mun')") as $row){
        $c=psgc_node($row);
        if(psgc_name_key($c['name'])!==psgc_name_key((string)($profile['city']??'')))continue;
        if($c['province']){$p=psgc_record($c['province']);if(psgc_name_key($p['name'])!==psgc_name_key((string)($profile['state_province']??'')))continue;}
        $r=psgc_record($c['region']);$old=psgc_name_key((string)($profile['region']??''));
        if($old && !str_contains(psgc_name_key($r['name']),$old))continue;
        foreach(psgc_items('barangays',$c['id']) as $b)if(psgc_name_key($b['name'])===psgc_name_key((string)($profile['barangay']??'')))$matches[]=['region'=>$c['region'],'province'=>$c['province'],'city'=>$c['id'],'barangay'=>$b['id']];
    }
    return count($matches)===1?$matches[0]:null;
}
