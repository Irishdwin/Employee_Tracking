<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../Api/Helpers/billing_revision_helper.php';
require_once __DIR__.'/../../Api/Accounting/Lib/client_billing_spreadsheet.php';
$template=\PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__.'/../../Templates/SAMPLE.xlsx')->getSheet(0);
$checks=0;
function verify(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
$row=['employee_name'=>'Selected Run Employee','designation'=>'Worker','area_site'=>'Selected Site','daily_rate'=>500,'days_worked'=>2,'basic_total'=>1000,'regular_ot_hours'=>2,'regular_ot_amount'=>156.25,'lates_minutes'=>30,'lates_undertime'=>31.25,'sil_accrual'=>104.17,'gross_salary'=>1125,'gross_payroll'=>1275,'allowances'=>150,'sss_ee'=>50,'philhealth_ee'=>30,'pagibig_ee'=>20,'net_salary'=>1175,'total_ee_deductions'=>100,'grand_total_invoice'=>1400];
foreach([0,1,10,11,17] as $count) {
 $rows=array_fill(0,$count,$row);
 $book=client_billing_render_spreadsheet(['rows'=>$rows,'run'=>['CutoffLabel'=>'OCT 1-15 2026'],'client_name'=>'Selected Client','prepared_by'=>'Accounting Test']);
 $sheet=$book->getSheet(0);$exception=6+max(1,$count);$total=$exception+1;
 verify($sheet->getCell('B'.$total)->getValue()==='TOTAL','Total row '.$count);
 verify($sheet->getCell('G'.$total)->getValue()==='=SUM(G6:G'.$exception.')','SUM span '.$count);
 verify($sheet->getCell('AQ'.$total)->getValue()===null,'Original blank total AQ '.$count);
 verify($sheet->getCell('B'.$exception)->getValue()===null,'Special row remains unassigned '.$count);
 verify($sheet->getCell('W'.$exception)->getValue()==='=(E'.$exception.'*30%+E'.$exception.')/8*V'.$exception,'Special formula '.$count);
 verify($sheet->getRowDimension($exception)->getRowHeight()===$template->getRowDimension(16)->getRowHeight(),'Special height '.$count);
 verify($sheet->getCell('C'.($total+6))->getValue()==='=G'.$total,'Summary reference '.$count);
 verify($sheet->getFreezePane()===$template->getFreezePane(),'Native panes '.$count);
 foreach($rows as $i=>$unused) {
  $r=6+$i;
  foreach(['G'=>'=F'.$r.'*E'.$r,'I'=>'=E'.$r.'*1.25/8','K'=>'=I'.$r.'*J'.$r,'M'=>'=E'.$r.'/8/60*L'.$r,'P'=>'=E'.$r.'*5/12/2','AD'=>'=AB'.$r,'AM'=>'=AD'.$r.'-AL'.$r] as $c=>$formula)
   verify($sheet->getCell($c.$r)->getValue()===$formula,'Translated formula '.$c.$r);
  foreach(['D','E','G','N','O','P','AW'] as $c)
   verify($sheet->getCell($c.$r)->getStyle()->exportArray()===$template->getCell($c.($r<=15?$r:6))->getStyle()->exportArray(),'Native style '.$c.$r);
  verify($sheet->getCell('B'.$r)->getValue()==='SELECTED RUN EMPLOYEE','No stale identity '.$r);
 }
 foreach($sheet->getCellCollection()->getCoordinates() as $address)if($sheet->getCell($address)->getDataType()===\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA)
  verify(!str_contains((string)$sheet->getCell($address)->getValue(),'#REF!'),'No broken reference '.$address);
 if($count===1)(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save(__DIR__.'/renderer_test.xlsx');
 $book->disconnectWorksheets();
}
echo json_encode(['passed'=>$checks,'counts'=>[0,1,10,11,17],'scope'=>'compatible formulas only; conflicting financial formulas unchanged'],JSON_PRETTY_PRINT).PHP_EOL;
