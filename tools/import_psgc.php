<?php
/** CLI only. php tools/import_psgc.php prepared.json [--dry-run] */
if(PHP_SAPI !== 'cli'){http_response_code(404);exit;}
try {
    $data=json_decode(file_get_contents($argv[1]??''),true,512,JSON_THROW_ON_ERROR);
    $expected=['Reg'=>18,'Prov'=>82,'City'=>149,'Mun'=>1493,'Bgy'=>42010,'SubMun'=>14,'Group'=>2];
    $counts=[];$codes=[];
    foreach($data['locations'] as $n){
        if(!preg_match('/^\d{10}$/',$n['id']) || isset($codes[$n['id']]))throw new RuntimeException('Invalid/duplicate code.');
        $codes[$n['id']]=$n;$counts[$n['level']]=($counts[$n['level']]??0)+1;
    }
    foreach($expected as $level=>$count)if(($counts[$level]??0)!==$count)throw new RuntimeException('Unexpected source counts.');
    if(count($counts)!==count($expected))throw new RuntimeException('Unknown geographic level.');
    foreach($codes as $n)if($n['parent'] && !isset($codes[$n['parent']]))throw new RuntimeException('Missing parent.');
    if(in_array('--dry-run',$argv,true)){echo json_encode($counts)." validated; no database writes.\n";exit;}
    require __DIR__.'/../Api/Db_connection/db_connect.php';
    $conn->query(file_get_contents(__DIR__.'/../sql/psgc_official_locations.sql'));
    $conn->begin_transaction();
    $stmt=$conn->prepare('INSERT INTO psgc_locations (PSGCCode,Name,GeographicLevel,ParentPSGCCode,RegionCode,ProvinceCode,CityMunicipalityCode,CorrespondenceCode,CityClass,OldNames,Status,SourceSHA256,SourceVersion) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE Name=VALUES(Name),GeographicLevel=VALUES(GeographicLevel),ParentPSGCCode=VALUES(ParentPSGCCode),RegionCode=VALUES(RegionCode),ProvinceCode=VALUES(ProvinceCode),CityMunicipalityCode=VALUES(CityMunicipalityCode),CorrespondenceCode=VALUES(CorrespondenceCode),CityClass=VALUES(CityClass),OldNames=VALUES(OldNames),Status=VALUES(Status),SourceSHA256=VALUES(SourceSHA256),SourceVersion=VALUES(SourceVersion)');
    $hash=$data['metadata']['source_sha256'];$version=$data['metadata']['version'];
    foreach($codes as $n){
        $parent=$n['parent']?:null;$province=$n['province']?:null;$city=$n['city']?:null;$correspondence=$n['correspondence']?:null;
        $stmt->bind_param('sssssssssssss',$n['id'],$n['name'],$n['level'],$parent,$n['region'],$province,$city,$correspondence,$n['city_class'],$n['old_names'],$n['status'],$hash,$version);$stmt->execute();
    }
    $actual=[];foreach($conn->query('SELECT GeographicLevel, COUNT(*) AS n FROM psgc_locations GROUP BY GeographicLevel') as $r)$actual[$r['GeographicLevel']]=(int)$r['n'];
    if(count($actual)!==count($expected))throw new RuntimeException('Unexpected database levels; transaction rolled back.');
    foreach($expected as $level=>$count)if(($actual[$level]??0)!==$count)throw new RuntimeException('Imported counts mismatch; transaction rolled back.');
    $conn->commit();echo json_encode(['source'=>$counts,'database'=>$actual,'total'=>array_sum($actual)])."\n";
} catch(Throwable $e){if(isset($conn))$conn->rollback();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
