<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../Api/Helpers/billing_revision_helper.php';
$book=\PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__.'/../../Templates/SAMPLE.xlsx');
$sheet=$book->getSheet(0);
$sheet->setCellValue('E6',700)->setCellValue('F6',2)->setCellValue('J6',2)->setCellValue('L6',30)->setCellValue('O6',150)->setCellValue('R6',8);
$snapshot=['rows'=>[['employee_name'=>'Test']], 'cells'=>billing_capture_cells($book,['rows'=>[[]]])];
$values=billing_capture_values($book,$snapshot);
$expected=['G6'=>1400,'I6'=>109.38,'K6'=>218.75,'M6'=>43.75,'P6'=>145.83,'S6'=>700];
foreach($expected as $cell=>$value){
 if(abs($values[$cell]-$value)>0.001)throw new RuntimeException('Incorrect trusted calculation '.$cell);
 if($sheet->getCell($cell)->getDataType()!==\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA)throw new RuntimeException('Formula overwritten '.$cell);
}
$snapshot['column_values']=$values;
if(billing_original_values($snapshot)!==$values)throw new RuntimeException('Original import baseline mismatch');
$path=__DIR__.'/formula_capture_test.xlsx';
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
$saved=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);
foreach($expected as $cell=>$value)if($saved->getSheet(0)->getCell($cell)->getValue()!==$sheet->getCell($cell)->getValue())throw new RuntimeException('Formula lost on save '.$cell);
echo json_encode(['passed'=>14,'checks'=>'trusted template calculation, stored import baseline, six XLSX formula round trips','file'=>$path],JSON_PRETTY_PRINT).PHP_EOL;
