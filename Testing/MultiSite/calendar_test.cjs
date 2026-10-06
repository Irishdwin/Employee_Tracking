// Executes production helpers against the isolated fixture database; all mutations roll back.
const fs=require('fs'),cp=require('child_process'),path=require('path');
const cfg=JSON.parse(fs.readFileSync(path.join(__dirname,'runtime.json'),'utf8'));
if(!/^codex_multisite_/.test(cfg.database))throw Error('Isolated database required.');
const root=path.resolve(__dirname,'../..').replace(/\\/g,'/');
const code=`
date_default_timezone_set('Asia/Manila');mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$conn=new mysqli('127.0.0.1','root','','${cfg.database}');$conn->set_charset('utf8mb4');$conn->query("SET time_zone='+08:00'");
require '${root}/Api/Helpers/work_site_geofence.php';require '${root}/Api/Helpers/payroll_type_helper.php';
$passed=0;function check($ok,$label){global $passed;if(!$ok)throw new RuntimeException('FAIL '.$label);++$passed;echo 'PASS '.$label."\\n";}
$conn->begin_transaction();try{
 $conn->query('UPDATE deployment_sites SET TeamLeadID=${cfg.lead} WHERE SiteID IN (${cfg.sites.join(',')})');
 $conn->query("UPDATE clientcompany SET PayrollType='A' WHERE CompanyID=${cfg.company}");
 $conn->query("UPDATE deployment SET StartDate='2026-10-01' WHERE SiteID IN (${cfg.sites.join(',')})");
 check(count(site_dtr_obligations($conn,${cfg.lead},'2026-10-14'))===0,'Type A: no reminder six days before release');
 check(count(site_dtr_obligations($conn,${cfg.lead},'2026-10-15'))===2,'Type A: both sites activate five days before Oct 20');
 check(count(site_dtr_obligations($conn,${cfg.lead},'2026-10-21'))===2,'Type A: pending reminder remains after release');
 check(count(site_dtr_obligations($conn,${cfg.lead},'2026-10-31'))===4,'Type A: Nov 5 release reminder begins Oct 31; distinct cutoffs');
 $conn->query("UPDATE clientcompany SET PayrollType='B' WHERE CompanyID=${cfg.company}");
 $conn->query("UPDATE deployment SET StartDate='2026-09-26' WHERE SiteID IN (${cfg.sites.join(',')})");
 check(count(site_dtr_obligations($conn,${cfg.lead},'2026-10-09'))===0,'Type B: no reminder six days before Oct 15');
 check(count(site_dtr_obligations($conn,${cfg.lead},'2026-10-10'))===2,'Type B: Oct 15 release reminders activate Oct 10');
 check(count(site_dtr_obligations($conn,${cfg.lead},'2026-10-16'))===2,'Type B: reminders persist after release');
 check(count(site_dtr_obligations($conn,${cfg.lead},'2026-10-25'))===4,'Type B: Oct 30 release reminders activate Oct 25');
 $p=payroll_type_period('B','2027-02-11');check($p['payday']==='2027-02-28','Production February release clamps to month end');
 check((new DateTimeImmutable($p['payday']))->modify('-5 days')->format('Y-m-d')==='2027-02-23','February uses five calendar days');
 $conn->query("INSERT INTO clientcompany(Name,Address,PayrollType,IsActive) VALUES('Calendar Type A fixture','Fixture address','A',1)");$company=(int)$conn->insert_id;
 $conn->query('UPDATE deployment_sites SET CompanyID='.$company.' WHERE SiteID=${cfg.sites[1]}');
 $conn->query("UPDATE deployment SET CompanyID=".$company.",StartDate='2026-10-01' WHERE SiteID=${cfg.sites[1]}");
 $items=site_dtr_obligations($conn,${cfg.lead},'2026-10-15');$types=array_unique(array_column(array_column($items,'period'),'type'));sort($types);
 check($types===['A','B'],'One Lead across two companies uses each company payroll type');
 $conn->query("INSERT INTO deployment_sites(CompanyID,Name,TeamLeadID) VALUES(${cfg.company},'Empty fixture',${cfg.lead})");$empty=(int)$conn->insert_id;
 check(!in_array($empty,array_column(site_dtr_obligations($conn,${cfg.lead},'2026-10-25'),'site_id'),true),'Sites with no eligible employees create no obligations');
 echo 'Calendar checks passed: '.$passed."\\n";
}finally{$conn->rollback();}
`;
const result=cp.spawnSync('C:/xampp/php/php.exe',['-r',code],{encoding:'utf8'});process.stdout.write(result.stdout);process.stderr.write(result.stderr);process.exitCode=result.status;
