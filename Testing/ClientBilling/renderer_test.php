<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../Api/Accounting/Lib/client_billing_spreadsheet.php';
$original=PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__.'/../../Templates/SAMPLE.xlsx')->getActiveSheet();
$names=[];for($r=6;$r<=15;$r++)$names[]=$original->getCell('B'.$r)->getValue();
foreach([0,1,3,10,12,25] as $n){
$rows=[];for($i=0;$i<$n;$i++)$rows[]=['employee_name'=>'AUDIT ROW '.$i,'daily_rate'=>500,'days_worked'=>2];
$book=client_billing_render_spreadsheet(['run'=>['CutoffLabel'=>'AUDIT','PeriodStart'=>'2026-09-26','PeriodEnd'=>'2026-10-10'],'rows'=>$rows,'totals'=>[],'client_name'=>'AUDIT CLIENT']);
$s=$book->getActiveSheet();$generated=0;
foreach($s->getCellCollection()->getCoordinates() as $coord){$v=$s->getCell($coord)->getValue();if(is_string($v)&&in_array($v,$names,true))throw new Exception('Original employee retained');if(str_starts_with($coord,'B')&&is_string($v)&&str_starts_with($v,'AUDIT ROW '))$generated++;}
if($generated!==$n)throw new Exception('Incorrect employee count');
$t=6+max(1,$n);if($s->getCell('B'.$t)->getValue()!=='TOTAL')throw new Exception('Totals row placement');
if(abs((float)$s->getCell('G'.$t)->getCalculatedValue()-($n*1000))>0.001)throw new Exception('Incorrect totals');
foreach(['AF','AG','AI'] as $col)for($r=6;$r<6+$n;$r++)if($s->getCell($col.$r)->getValue()!==0)throw new Exception('Unassigned column');
echo "PASS count $n, privacy, explicit columns, dynamic totals\n";$book->disconnectWorksheets();
}
