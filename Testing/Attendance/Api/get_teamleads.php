<?php
declare(strict_types=1);
require_once __DIR__.'/_testing_guard.php';require_once __DIR__.'/_selection_context.php';
$site=testing_site($conn,$_GET);$leads=[];
if(!empty($site['TeamLeadID'])){
 try{$owned=site_for_lead($conn,(int)$site['TeamLeadID'],(int)$site['SiteID']);$leads[]=['team_lead_id'=>(int)$owned['TeamLeadID'],'name'=>$owned['TeamLeadName']];}
 catch(DomainException $e){}
}
echo json_encode(['success'=>true,'team_leads'=>$leads]);
