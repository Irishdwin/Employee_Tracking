<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ob_start();require __DIR__.'/../Attendance/holiday_e2e_runner.php';$output=ob_get_clean();
$result=json_decode($output,true);
$failed=array_values(array_filter($result['results'] ?? [],static fn($r)=>($r['status'] ?? '')!=='PASS'));
$ok=($result['success'] ?? false) && ($result['production_unchanged'] ?? false) && !$failed;
echo json_encode(['success'=>$ok,'passed'=>count($result['results'] ?? [])-count($failed),'failed'=>array_map(static fn($r)=>['id'=>$r['id'],'scenario'=>$r['scenario'],'checks'=>$r['checks']],$failed),'production_unchanged'=>$result['production_unchanged'] ?? null,'cleanup'=>$result['cleanup'] ?? null,'message'=>$result['message'] ?? null],JSON_PRETTY_PRINT).PHP_EOL;
exit($ok?0:1);
