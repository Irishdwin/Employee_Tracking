$ErrorActionPreference = 'Stop'
$php = @'
<?php
require getcwd().'/Api/Db_connection/db_connect.php';
require getcwd().'/Api/Helpers/job_hierarchy.php';
session_save_path(getenv('TEMP').'/onazul-hierarchy-tests');ini_set('session.use_cookies','0');ini_set('session.cache_limiter','');
$id='hierarchycheck'.bin2hex(random_bytes(10));$c=$k=$s=0;$count=0;
function ck($ok,$label){global $count;if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;$count++;}
function req($path,$data=null,$auth=true){global $id;$headers="Content-Type: application/json\r\n".($auth?"Cookie: PHPSESSID=$id\r\n":'');$raw=file_get_contents('http://127.0.0.1:8894/Api/'.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>$headers,'content'=>$data===null?'':json_encode($data),'ignore_errors'=>true,'timeout'=>15]]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$m);return [(int)$m[1],json_decode($raw,true)];}
try{
$u=$conn->query("SELECT u.UsersID,u.RoleID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE r.RoleName='Admin' AND u.IsActive=1 LIMIT 1")->fetch_assoc();session_id($id);session_start();$_SESSION=['user_id'=>(int)$u['UsersID'],'role'=>'Admin','RoleID'=>(int)$u['RoleID'],'last_activity'=>time()];session_write_close();
ck(req('Common/manage_classifications.php',['action'=>'add'],false)[0]===403,'Anonymous taxonomy writes denied');
$name='HIERARCHY CHECK '.bin2hex(random_bytes(6));$r=req('Common/manage_categories.php',['category_name'=>$name]);$c=(int)($r[1]['category']['id']??0);ck($r[0]===200&&$c>0,'Admin creates category');
$r=req('Common/manage_classifications.php',['action'=>'add','category_id'=>$c,'classification_name'=>'Test Class']);$k=(int)($r[1]['id']??0);ck($r[0]===200&&$k>0,'Admin creates classification');
$r=req('Common/manage_sub_classifications.php',['action'=>'add','classification_id'=>$k,'sub_classification_name'=>'Test Sub']);$s=(int)($r[1]['id']??0);ck($r[0]===200&&$s>0,'Admin creates sub-classification');
ck(jh_validate($conn,$c,$k,$s)['category_name']===$name,'Created tuple accepted');
foreach([['manage_categories.php','category_name',$c,$name.' Renamed'],['manage_classifications.php','classification_name',$k,'Renamed Class'],['manage_sub_classifications.php','sub_classification_name',$s,'Renamed Sub']] as $row){$r=req('Common/'.$row[0],['action'=>'edit','id'=>$row[2],$row[1]=>$row[3]]);ck($r[0]===200&&!empty($r[1]['success']),'Admin rename '.$row[1]);}
$h=jh_validate($conn,$c,$k,$s);ck($h['category_name']===$name.' Renamed'&&$h['classification_name']==='Renamed Class'&&$h['sub_classification_name']==='Renamed Sub','Canonical renamed labels resolved');
$r=req('Common/job_hierarchy.php',null,false);$tree=array_values(array_filter($r[1]['categories']??[],fn($x)=>(int)$x['id']===$c));ck($r[0]===200&&count($tree)===1,'Public dropdown tree reflects Admin updates');
foreach([['manage_categories.php',$c],['manage_classifications.php',$k],['manage_sub_classifications.php',$s]] as $row){$r=req('Common/'.$row[0],['action'=>'toggle_active','id'=>$row[1],'is_active'=>false]);ck(!empty($r[1]['success']),'Admin deactivates '.$row[0]);try{jh_validate($conn,$c,$k,$s);$denied=false;}catch(DomainException $e){$denied=true;}ck($denied,'Inactive tuple denied '.$row[0]);$r=req('Common/'.$row[0],['action'=>'toggle_active','id'=>$row[1],'is_active'=>true]);ck(!empty($r[1]['success']),'Admin reactivates '.$row[0]);}
foreach([['category_id'=>'bad'],['classification_id'=>$k],['category_id'=>4,'classification_id'=>$k],['category_id'=>[$c]]] as $query){ck(req('Applicant/get_jobs.php?'.http_build_query($query),null,false)[0]===400,'Invalid or unrelated hierarchy rejected');}
ck(req('HR/job_orders.php',null,false)[0]===401,'Anonymous Job Order access denied');
echo "TOTAL $count PASS\n";
}finally{if($s)$conn->query('DELETE FROM job_sub_classifications WHERE id='.$s);if($k)$conn->query('DELETE FROM job_classifications WHERE id='.$k);if($c)$conn->query('DELETE FROM job_categories WHERE id='.$c);$f=session_save_path().'/sess_'.$id;if(is_file($f))unlink($f);echo "Temporary taxonomy and session cleaned.\n";}

'@
$php | & 'C:\xampp\php\php.exe'
if ($LASTEXITCODE -ne 0) { throw 'Hierarchy runtime checks failed' }

