<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../vendor/autoload.php';
$b=\PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__.'/../../Templates/SAMPLE.xlsx');$s=$b->getActiveSheet();
foreach(range(6,17) as $r){$f=[];foreach(range(1,49) as $c){$cell=$s->getCell([$c,$r]);if($cell->getDataType()==='f')$f[$cell->getCoordinate()]=$cell->getValue();}echo json_encode(['row'=>$r,'formulas'=>$f],JSON_UNESCAPED_SLASHES).PHP_EOL;}
echo json_encode(['columns'=>array_map(fn($d)=>[$d->getWidth(),$d->getVisible()],$s->getColumnDimensions()),'print_area'=>$s->getPageSetup()->getPrintArea(),'freeze'=>$s->getFreezePane(),'hidden_rows'=>array_keys(array_filter($s->getRowDimensions(),fn($d)=>!$d->getVisible()))]).PHP_EOL;
