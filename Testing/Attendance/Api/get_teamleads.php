<?php
declare(strict_types=1);
require_once __DIR__.'/_testing_guard.php';require_once __DIR__.'/_selection_context.php';
$site=testing_site($conn,$_GET);$leads=[];
if(!empty($site['TeamLeadID'])){
 try{$owned=testing_lead_site($conn,array_merge($_GET,['team_lead_id'=>$site['TeamLeadID']]));$leads[]=['team_lead_id'=>(int)$owned['TeamLeadID'],'name'=>$owned['TeamLeadName']];}
 catch(DomainException $e){}
}
echo json_encode(['success'=>true,'team_leads'=>$leads]);
