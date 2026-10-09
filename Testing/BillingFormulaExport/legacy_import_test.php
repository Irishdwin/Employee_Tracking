<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../Api/Helpers/client_billing_helper.php';
require __DIR__.'/../../Api/Helpers/billing_revision_helper.php';
$book=\PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__.'/renderer_test.xlsx');
$snapshot=['rows'=>[['employee_db_id'=>1,'employee_id'=>'LEGACY-TEST','payroll_id'=>1,'dtr_submission_id'=>1,'employee_name'=>'Legacy Test']],
 'run'=>['PayrollRunID'=>1,'RunCode'=>'PR-LEGACY','CompanyID'=>1,'SiteID'=>1,'PeriodStart'=>'2026-10-01','PeriodEnd'=>'2026-10-15'],
 'token'=>str_repeat('b',64),'sheet_title'=>$book->getSheet(0)->getTitle()];
$snapshot['cells']=billing_capture_cells($book,$snapshot);$snapshot['column_values']=billing_capture_values($book,$snapshot);
billing_add_manifest($book,$snapshot,$snapshot['token']);
$manifest=json_decode((string)$book->getSheetByName('_ONAZUL_EXPORT')->getCell('A1')->getValue(),true);
if($book->getSheetCount()!==2 || billing_detect_changes($book,$snapshot,$manifest)!==[])throw new RuntimeException('Legacy two-sheet import failed');
$original=(float)$book->getSheet(0)->getCell('O6')->getValue();$book->getSheet(0)->setCellValue('O6',$original+10);
$changes=billing_detect_changes($book,$snapshot,$manifest);
if(count($changes)!==1 || $changes[0]['component']!=='allowances' || $changes[0]['difference']!==10.0)throw new RuntimeException('Legacy change detection failed');
echo "PASS: historical two-sheet import and legacy allowance change detection retained.\n";
require __DIR__.'/../../Api/Accounting/Lib/client_billing_spreadsheet.php';
$snapshot['rows'][0]=array_merge($snapshot['rows'][0],['pagibig_ee'=>20,'additional_pagibig_ee'=>5,'other_pagibig_ee'=>2,'philhealth_ee'=>30,'additional_philhealth_ee'=>3,'additional_lates'=>10,'daily_rate'=>1000,'days_worked'=>2]);
$snapshot['client_name']='Synthetic';$snapshot['prepared_by']='Synthetic';
$agency=client_billing_render_spreadsheet($snapshot);$s=$agency->getSheet(0);
if($s->getCell('AF6')->getValue()!==25.0 || $s->getCell('AC6')->getValue()!==10.0 || $s->getCell('AI6')->getValue()!==22.0)throw new RuntimeException('Approved additional inputs lost');
$snapshot['sheet_title']=$s->getTitle();$snapshot['cells']=billing_capture_cells($agency,$snapshot);$snapshot['column_values']=billing_capture_values($agency,$snapshot);billing_add_manifest($agency,$snapshot,$snapshot['token']);
$manifest=json_decode((string)$agency->getSheetByName('_ONAZUL_EXPORT')->getCell('A1')->getValue(),true);$s->setCellValue('AF6',30);
$changes=billing_detect_changes($agency,$snapshot,$manifest);
if(count($changes)!==1 || $changes[0]['component']!=='pagibig_ee' || $changes[0]['new']!==25.0)throw new RuntimeException('Agency deduction delta incorrectly includes existing extra deduction');
echo "PASS: additional deduction inputs retained and agency delta maps to saved contribution correctly.\n";
