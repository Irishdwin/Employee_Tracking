$ErrorActionPreference='Stop'
$php=@'
<?php
require getcwd().'/Api/Db_connection/db_connect.php';require getcwd().'/Api/Helpers/job_hierarchy.php';$count=0;
function ck($ok,$label){global $count;if(!$ok)throw new RuntimeException('FAIL '.$label);++$count;echo "PASS $label\n";}
function jobs($f){global $conn;return jh_rows($conn,'SELECT jo.JobOrderID FROM joborder jo WHERE jo.Status=\'Open\''.$f['where'],$f['types'],$f['params']);}
$profile=jh_rows($conn,'SELECT UserID FROM applicants WHERE category_id=4 AND classification_id=5 AND sub_classification_id=22 LIMIT 1')[0];$uid=(int)$profile['UserID'];
$_GET=[];$matching=jobs(jh_profile_filter($conn,$uid));ck(count($matching)===1&&(int)$matching[0]['JobOrderID']===3,'Real saved Security profile matches order 3');
$_GET=['category_id'=>1];ck(jobs(jh_profile_filter($conn,$uid))===[],'Conflicting profile and category cannot match different placements');
$_GET=['category_id'=>4,'classification_id'=>5,'sub_classification_id'=>22];ck(jobs(jh_profile_filter($conn,$uid))===$matching,'Compatible profile and explicit tuple match');
$_GET=[];ck(count(jobs(jh_filter($conn,0,0,0)))===2,'Clear filters preserves both real open jobs including unmapped legacy job');
ck(jobs(jh_filter($conn,1,1,11))===[],'Closed exact-mapped order not returned');
ck(count(jh_rows($conn,'SELECT JobOrderID FROM joborder'))===4,'All four original Job Orders remain');
ck((int)jh_rows($conn,"SELECT COUNT(*) n FROM job_categories WHERE category_name LIKE 'HIERARCHY%'")[0]['n']===0,'No marked taxonomy fixtures remain');
echo "TOTAL $count PASS\n";

'@
$php | & 'C:\xampp\php\php.exe'
if($LASTEXITCODE -ne 0){throw 'Read-only hierarchy regression failed'}

