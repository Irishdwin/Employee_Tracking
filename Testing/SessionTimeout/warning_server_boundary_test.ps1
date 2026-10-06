$ErrorActionPreference='Stop'
$testSessionDirectory=Join-Path ([IO.Path]::GetTempPath()) ('onazul-warning-boundary-'+[Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $testSessionDirectory | Out-Null
$php=@'
<?php
ini_set('session.use_cookies','0');ini_set('session.cache_limiter','');
$checks=[];
function check($ok,$label){global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]='PASS '.$label;}
foreach([0,839,840,899,900,901] as $age){
 session_id('boundary'.bin2hex(random_bytes(12)));session_start();
 $_SESSION=['user_id'=>'isolated-unit-fixture','role'=>'HR','last_activity'=>time()-$age];
 $before=$_SESSION['last_activity'];include getcwd().'/Api/Common/session_init.php';
 check(SESSION_IDLE_TIMEOUT_SECONDS===900,'Production timeout remains 900 seconds');
 check(isset($_SESSION['user_id'])===($age<900),"Age $age: authoritative expiry boundary");
 if($age<900)check($_SESSION['last_activity']===$before,"Age $age: validation does not renew background activity");
 session_destroy();
}
session_id('boundary'.bin2hex(random_bytes(12)));session_start();$_SESSION=[];
include getcwd().'/Api/Common/session_init.php';check(!isset($_SESSION['user_id']),'Anonymous session is not authenticated');session_destroy();
echo implode("\n",$checks)."\nTOTAL ".count($checks)." PASS\n";
'@
try {
 $php | & 'C:\xampp\php\php.exe' -d ('session.save_path="'+$testSessionDirectory+'"')
 if($LASTEXITCODE -ne 0){throw 'PHP boundary checks failed'}
} finally {
 $resolvedTestDirectory=[IO.Path]::GetFullPath($testSessionDirectory)
 $expectedTempRoot=[IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\')+'\'
 if(!$resolvedTestDirectory.StartsWith($expectedTempRoot,[StringComparison]::OrdinalIgnoreCase) -or [IO.Path]::GetFileName($resolvedTestDirectory) -notlike 'onazul-warning-boundary-*'){throw 'Unexpected cleanup path'}
 Remove-Item -LiteralPath $resolvedTestDirectory -Recurse
}
