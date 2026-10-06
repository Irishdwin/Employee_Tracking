<?php
require __DIR__.'/../../Api/Db_connection/db_connect.php';
require __DIR__.'/../../Api/Helpers/psgc_address.php';
function test($ok,$name){if(!$ok)throw new RuntimeException($name);echo "PASS: $name\n";}
$expected=['Reg'=>18,'Prov'=>82,'City'=>149,'Mun'=>1493,'Bgy'=>42010,'SubMun'=>14,'Group'=>2];
$source=json_decode(file_get_contents(__DIR__.'/official_import.json'),true);
$stored=[];foreach(psgc_query('SELECT * FROM psgc_locations') as $row)$stored[$row['PSGCCode']]=$row;
foreach($source['locations'] as $n){$row=$stored[$n['id']]??null;
    if(!$row || $row['Name']!==$n['name'] || $row['GeographicLevel']!==$n['level'] || ($row['ParentPSGCCode']??'')!==$n['parent'] || $row['RegionCode']!==$n['region'] || ($row['ProvinceCode']??'')!==$n['province'] || ($row['CityMunicipalityCode']??'')!==$n['city'] || ($row['CorrespondenceCode']??'')!==$n['correspondence'] || ($row['CityClass']??'')!==$n['city_class'] || $row['SourceSHA256']!==$source['metadata']['source_sha256'])throw new RuntimeException('Source/database row mismatch '.$n['id']);
}
test(count($stored)===count($source['locations']),'all 43768 official source rows reconcile by code, name, level, hierarchy, class and provenance');
foreach($conn->query('SELECT GeographicLevel,COUNT(*) AS n FROM psgc_locations GROUP BY GeographicLevel') as $r)test((int)$r['n']===$expected[$r['GeographicLevel']],"source/database {$r['GeographicLevel']} count");
test(count(psgc_items('regions'))===18,'region lookup');
test(count(array_filter(psgc_items('cities','1004300000'),fn($c)=>$c['id']==='1030500000'))===1,'CDO available under Misamis Oriental selector with official identity');
test(count(array_filter(psgc_items('cities','1003500000'),fn($c)=>$c['id']==='1030900000'))===1,'Iligan available under Lanao del Norte selector with official identity');
test(psgc_record('1030900000')['province']==='' && psgc_record('1030900000')['selector_province']==='1003500000','Iligan official province remains unchanged');
function validateCity($id){$c=psgc_record($id);$bs=psgc_items('barangays',$id);test(count($bs)>0,$c['name'].' barangays');$codes=['region'=>$c['region'],'province'=>$c['province'],'city'=>$id,'barangay'=>$bs[0]['id']];$a=psgc_validate_address($codes);test($a['city']===$c['name'],$c['name'].' valid complete hierarchy');return $codes;}
$town=psgc_query("SELECT PSGCCode FROM psgc_locations WHERE GeographicLevel='Mun' AND ProvinceCode IS NOT NULL LIMIT 1")[0]['PSGCCode'];validateCity($town);
$cc=psgc_query("SELECT PSGCCode FROM psgc_locations WHERE CityClass='CC' AND ProvinceCode IS NOT NULL LIMIT 1")[0]['PSGCCode'];validateCity($cc);
$cdo=validateCity('1030500000');test($cdo['province']===''&&count(psgc_items('barangays',$cdo['city']))===80,'CDO direct Region X with 80 barangays');
$manila=validateCity('1380600000');test($manila['province']===''&&count(psgc_items('barangays',$manila['city']))===897,'Manila 897 district barangays without fake province');
validateCity('1381300000');test(count(psgc_items('provinces','1300000000'))===0&&count(psgc_items('cities','1300000000'))===17,'NCR region-only path');
$huc=psgc_query("SELECT PSGCCode FROM psgc_locations WHERE CityClass='HUC' AND PSGCCode NOT LIKE '13%' LIMIT 1")[0]['PSGCCode'];$a=validateCity($huc);test($a['province']==='','HUC without administrative province');
$icc=psgc_query("SELECT PSGCCode FROM psgc_locations WHERE CityClass='ICC' LIMIT 1")[0]['PSGCCode'];$a=validateCity($icc);test($a['province']==='','ICC without administrative province');
$dup=psgc_query("SELECT a.PSGCCode a,b.PSGCCode b FROM psgc_locations a JOIN psgc_locations b ON a.Name=b.Name AND a.PSGCCode<b.PSGCCode AND a.CityMunicipalityCode<>b.CityMunicipalityCode WHERE a.GeographicLevel='Bgy' AND b.GeographicLevel='Bgy' LIMIT 1")[0];
test(psgc_record($dup['a'])['city']!==psgc_record($dup['b'])['city'],'duplicate name has distinct parent/code');
foreach([array_replace($cdo,['region'=>'1300000000']),array_replace($cdo,['province'=>'1004300000']),array_replace($cdo,['barangay'=>$dup['a']])] as $bad){$reject=false;try{psgc_validate_address($bad);}catch(DomainException $e){$reject=true;}test($reject,'invalid hierarchy rejected');}
test((int)psgc_query("SELECT COUNT(*) n FROM psgc_locations b JOIN psgc_locations p ON b.ParentPSGCCode=p.PSGCCode WHERE b.GeographicLevel='Bgy' AND p.GeographicLevel='SubMun'")[0]['n']===897,'SubMun retained as real intermediate parents');
test(psgc_resolve_legacy(['region'=>'Northern Mindanao','state_province'=>'Misamis Oriental','city'=>'Cagayan de Oro City','barangay'=>'Carmen'])['city']==='1030500000','legacy text compatibility');
echo 'PASS: indexed plans '.json_encode(psgc_query("EXPLAIN SELECT PSGCCode FROM psgc_locations WHERE CityMunicipalityCode=? AND GeographicLevel='Bgy'",['1030500000']))."\n";
