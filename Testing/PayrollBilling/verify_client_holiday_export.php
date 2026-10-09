<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../Api/Accounting/Lib/client_billing_spreadsheet.php';
$input=json_decode(ltrim(file_get_contents(__DIR__.'/client_holiday_results.json'),"\xEF\xBB\xBF"),true,512,JSON_THROW_ON_ERROR);
if(empty($input['success']))throw new RuntimeException('Holiday fixture run must pass first.');
$cases=$input['results'];$rows=array_column($cases,'accounting_payroll');
$book=client_billing_render_spreadsheet(['rows'=>$rows,'run'=>$cases[0]['run']+['PeriodStart'=>'2026-10-01','PeriodEnd'=>'2026-10-15'],'client_name'=>'Synthetic holiday verification']);
$sheet=$book->getSheet(0);$checks=[];
foreach($cases as $index=>$case){
    $row=6+$index;$data=$rows[$index];
    foreach(['G'=>'basic_total','R'=>'holiday_duty_hrs','S'=>'holiday_duty','T'=>'holiday_ot_hrs','U'=>'holiday_ot_amount','V'=>'special_holiday_hrs','W'=>'special_holiday_pay','X'=>'special_holiday_ot_hours','Z'=>'special_holiday_ot_amount','AB'=>'gross_payroll','AM'=>'net_salary'] as $column=>$key){
        $actual=$sheet->getCell($column.$row)->getCalculatedValue();
        $checks[$case['id'].' '.$column]=is_numeric($actual)&&abs((float)$actual-(float)$data[$key])<0.005;
    }
    $checks[$case['id'].' payslip net']=isset($case['payslip']['net_pay']) && abs((float)$case['payslip']['net_pay']-(float)$data['net_salary'])<0.005;
}
$total=17+max(0,count($rows)-10);
foreach(['AB'=>'gross_payroll','AM'=>'net_salary','S'=>'holiday_duty','W'=>'special_holiday_pay','U'=>'holiday_ot_amount','Z'=>'special_holiday_ot_amount'] as $column=>$key)
    $checks['summary '.$column]=abs((float)$sheet->getCell($column.$total)->getCalculatedValue()-array_sum(array_column($rows,$key)))<0.005;
$checks['no sample employee']=strcasecmp((string)$sheet->getCell('B6')->getValue(),(string)$rows[0]['employee_name'])===0;
$failed=array_keys(array_filter($checks,static fn($ok)=>!$ok));
$result=['success'=>!$failed,'passed'=>count($checks)-count($failed),'failed'=>$failed,'checks'=>$checks,'engine'=>'Production PhpSpreadsheet renderer and formula evaluator; no database writes'];
file_put_contents(__DIR__.'/client_holiday_export_results.json',json_encode($result,JSON_PRETTY_PRINT));
echo json_encode(['success'=>$result['success'],'passed'=>$result['passed'],'failed'=>$failed],JSON_PRETTY_PRINT).PHP_EOL;
exit($failed?1:0);
