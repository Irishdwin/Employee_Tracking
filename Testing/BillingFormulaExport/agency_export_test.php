<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../Api/Helpers/billing_revision_helper.php';
require __DIR__.'/../../Api/Helpers/client_billing_helper.php';
require __DIR__.'/../../Api/Accounting/Lib/client_billing_spreadsheet.php';
$master=\PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__.'/../../Templates/SAMPLE.xlsx')->getSheet(0);
$checks=0;$outputs=[];
function agency_check(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$row=['employee_id'=>'SYNTHETIC-1','employee_db_id'=>1,'dtr_submission_id'=>1,'payroll_id'=>1,'employee_name'=>'Synthetic Employee','designation'=>'Worker','area_site'=>'Synthetic Site','daily_rate'=>1000,'days_worked'=>2,'regular_ot_hours'=>2,'lates_minutes'=>8,'allowances'=>100,'addback'=>50,'holiday_duty_hrs'=>8,'holiday_ot_hrs'=>2,'special_holiday_hrs'=>8,'special_holiday_ot_hours'=>2,'sss_ee'=>50,'philhealth_ee'=>30,'pagibig_ee'=>20,'sss_er_regular'=>100,'ec_er'=>10,'sss_er_mpf'=>5,'gross_payroll'=>5000,'net_salary'=>4900,'grand_total_invoice'=>6000,'sunday_duty_dates'=>[]];
foreach([0,1,2,10,11,17] as $count) {
 $rows=array_fill(0,$count,$row);foreach($rows as $i=>&$r){$r['employee_id']='SYNTHETIC-'.($i+1);$r['employee_db_id']=$i+1;}unset($r);
 if($count>1) {
  foreach(['regular_ot_hours','holiday_duty_hrs','holiday_ot_hrs','special_holiday_hrs','special_holiday_ot_hours','allowances','addback','sss_ee','philhealth_ee','pagibig_ee','sss_er_regular','ec_er','sss_er_mpf'] as $key)$rows[1][$key]=0;
  $rows[1]['daily_rate']=800;$rows[1]['sunday_duty_dates']=['2026-10-04'];$rows[1]['sunday_allowance']=30;
 }
 $export=['rows'=>$rows,'client_name'=>'Synthetic Client','prepared_by'=>'Synthetic Accountant','run'=>['PayrollRunID'=>1,'RunCode'=>'PR-TEST','CompanyID'=>1,'SiteID'=>1,'PeriodStart'=>'2026-10-01','PeriodEnd'=>'2026-10-15','CutoffLabel'=>'OCT 1-15 2026']];
 $book=client_billing_render_spreadsheet($export);$sheet=$book->getSheet(0);$total=7+max(1,$count);
 agency_check($sheet->getTitle()==='OCT 1-15 2026','Selected cutoff');
 $expectedInvoice=0;
 for($i=0;$i<$count;$i++) {
  $r=$i+6;
  for($c=1;$c<=49;$c++) {
   $source=$master->getCell([$c,6]);$cell=$sheet->getCell([$c,$r]);
   if($source->getDataType()==='f')agency_check($cell->getValue()===\PhpOffice\PhpSpreadsheet\ReferenceHelper::getInstance()->updateFormulaReferencesAnyWorksheet((string)$source->getValue(),0,$r-6),'Agency formula '.$cell->getCoordinate());
   agency_check($cell->getStyle()->exportArray()===$master->getCell([$c,$r<=15?$r:6])->getStyle()->exportArray(),'Column-specific native style');
  }
  // Independently evaluated sample: 2 days, 2 OT, 8 late min, legal & special holidays.
  $data=$rows[$i];$rate=$data['daily_rate'];$days=$data['days_worked'];
  $basic=$rate*$days;$ot=$rate*1.25/8*$data['regular_ot_hours'];$late=$rate/8/60*$data['lates_minutes'];$sil=$rate*5/12/2;
  $additional=$data['allowances']+($data['sunday_allowance']??0)+$sil+$data['addback']+$rate/8*$data['holiday_duty_hrs']+$rate/8*2.6*$data['holiday_ot_hrs']+$rate*.3/8*$data['special_holiday_hrs']+$rate*1.69/8*$data['special_holiday_ot_hours'];
  $gross=$basic+$ot-$late+$additional;$net=$gross-$data['sss_ee']-$data['pagibig_ee'];
  $er=$data['sss_er_regular']+$data['ec_er']+$data['sss_er_mpf']+$data['pagibig_ee'];$chargeDays=$days+count($data['sunday_duty_dates']);$thirteenth=$rate/12*$chargeDays;$overhead=36.5*$chargeDays;
  $expectedInvoice+=$gross+$er+$thirteenth+$overhead+$overhead*.12-$overhead*.02;
  foreach(['G'=>$basic,'K'=>$ot,'M'=>$late,'AB'=>$gross,'AM'=>$net,'AS'=>$er,'AT'=>$thirteenth,'AU'=>$overhead,'AW'=>$gross+$er+$thirteenth+$overhead] as $col=>$expected)
   agency_check(abs((float)$sheet->getCell($col.$r)->getCalculatedValue()-$expected)<0.0001,'Independent amount '.$col.$r);
 }
 agency_check($sheet->getCell('G'.$total)->getValue()==='=SUM(G6:G'.($total-1).')','Dynamic totals');
 agency_check($sheet->getCell('C'.($total+6))->getValue()==='=G'.$total,'Dynamic summary');
 agency_check(abs((float)$sheet->getCell('C'.($total+28))->getCalculatedValue()-$expectedInvoice)<0.001,'VAT withholding grand total');
 foreach($sheet->getCellCollection()->getCoordinates() as $address) {
  $cell=$sheet->getCell($address);if($cell->getDataType()==='f')agency_check(!str_contains((string)$cell->getValue(),'#REF!')&&!preg_match('/^#(REF!|DIV\/0!|VALUE!|NAME\?|N\/A|NUM!|NULL!)/',(string)$cell->getCalculatedValue()),'Valid formula '.$address);
 }
 $export['token']=str_repeat('a',64);$export['sheet_title']=$sheet->getTitle();$export['cells']=billing_capture_cells($book,$export);$export['column_values']=billing_capture_values($book,$export);billing_add_manifest($book,$export,$export['token']);
 $file=__DIR__.'/agency_'.$count.'_employees.xlsx';(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);$outputs[]=$file;
 $reopened=\PhpOffice\PhpSpreadsheet\IOFactory::load($file);$manifest=json_decode((string)$reopened->getSheetByName('_ONAZUL_EXPORT')->getCell('A1')->getValue(),true);
 agency_check(billing_detect_changes($reopened,$export,$manifest)===[],'Unchanged signed import');
 if($count) {
  agency_check($reopened->getSheetByName('Payroll Reconciliation')->getCell('C5')->getValue()==5000,'Saved payroll reconciliation');
  $reopened->getSheetByName('Payroll Reconciliation')->setCellValue('C5',1);$rejected=false;
  try{billing_detect_changes($reopened,$export,$manifest);}catch(InvalidArgumentException $e){$rejected=true;}agency_check($rejected,'Reconciliation tampering rejected');
 }
 $book->disconnectWorksheets();$reopened->disconnectWorksheets();
}
echo json_encode(['success'=>true,'checks'=>$checks,'counts'=>[0,1,2,10,11,17],'exports'=>$outputs],JSON_PRETTY_PRINT);
