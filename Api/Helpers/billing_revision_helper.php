<?php
declare(strict_types=1);
require_once __DIR__.'/payroll_workflow.php';

// All employee-row values may be revised; signed row identity stays unchanged.
const BILLING_EDITABLE_COLUMNS = [
 'B'=>'employee_name','C'=>'designation','D'=>'area_site','E'=>'daily_rate','F'=>'days_worked','G'=>'basic_total',
 'H'=>'sunday_ot_amount','I'=>'regular_ot_rate','J'=>'regular_ot_hours','K'=>'regular_ot_amount','L'=>'lates_minutes','M'=>'lates_undertime','N'=>'gross_salary',
 'O'=>'allowances','P'=>'sil_accrual','Q'=>'addback','R'=>'holiday_duty_hrs','S'=>'holiday_duty','T'=>'holiday_ot_hrs','U'=>'holiday_ot_amount',
 'V'=>'special_holiday_hrs','W'=>'special_holiday_pay','X'=>'special_holiday_ot_hours','Y'=>'special_holiday_ot_rate','Z'=>'special_holiday_ot_amount',
 'AA'=>'additional_pay','AB'=>'gross_before_lates','AC'=>'additional_lates','AD'=>'gross_payroll','AE'=>'sss_ee','AF'=>'additional_pagibig_ee',
 'AG'=>'additional_philhealth_ee','AH'=>'philhealth_ee','AI'=>'other_pagibig_ee','AJ'=>'pagibig_ee','AK'=>'other_ee_deductions','AL'=>'total_ee_deductions',
 'AM'=>'net_salary','AN'=>'ec_er','AO'=>'sss_er_regular','AP'=>'sss_er_mpf','AQ'=>'philhealth_er','AR'=>'pagibig_er','AS'=>'total_er_dues',
 'AT'=>'thirteenth_month_accrual','AU'=>'admin_overhead_amount','AV'=>'billing_additions','AW'=>'grand_total_invoice'];
const BILLING_TEXT_FIELDS=['employee_name','designation','area_site'];
function billing_component_label(string $key): string {
 $labels=['employee_name'=>'Employee name (billing record)','designation'=>'Designation (billing record)','area_site'=>'Area / site (billing record)','basic_total'=>'Basic pay','sil_accrual'=>'Leave pay','addback'=>'Add Back','sss_ee'=>'SSS employee deduction','philhealth_ee'=>'PhilHealth employee deduction','pagibig_ee'=>'Pag-IBIG employee deduction','other_ee_deductions'=>'Other deductions','gross_before_lates'=>'Gross before additional lates','gross_payroll'=>'Gross payroll','net_salary'=>'Net salary','total_er_dues'=>'Total employer dues','grand_total_invoice'=>'Client invoice','thirteenth_month_accrual'=>'13th-month accrual'];
 return $labels[$key]??ucwords(str_replace('_',' ',$key));
}
function billing_storage(): string {
    $dir=dirname(__DIR__,2).'/uploads/payroll_billing';
    if (!is_dir($dir) && !mkdir($dir,0750,true)) throw new RuntimeException('Unable to create billing storage.');
    if (!is_file($dir.'/.htaccess')) file_put_contents($dir.'/.htaccess',"Require all denied\n");
    return $dir;
}
function billing_json(array $data): string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);}
function billing_fingerprint(array $payroll): string {ksort($payroll);return hash('sha256',billing_json($payroll));}
function billing_money($value): float {
    if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || (float)$value > 99999999.99)
        throw new InvalidArgumentException('Enter a non-negative amount of at most 99,999,999.99.');
    if (abs((float)$value-round((float)$value,2))>0.000001) throw new InvalidArgumentException('Amounts may have at most two decimal places.');
    return round((float)$value,2);
}
/** Apply an authorized component difference to a frozen payroll, not to attendance. */
function billing_apply_components(array $row,array $targets): array {
    $old=$row;
    foreach(BILLING_EDITABLE_COLUMNS as $field) if(!array_key_exists($field,$row)) $row[$field]=in_array($field,BILLING_TEXT_FIELDS,true)?'':0.0;
    $row['gross_before_lates']=$old['gross_before_lates']??$old['gross_payroll'];
    $row['additional_pay']=$old['additional_pay']??round((float)$old['gross_payroll']-(float)($old['gross_salary']??$old['basic_total']),2);
    $row['regular_ot_rate']=$old['regular_ot_rate']??round((float)($old['daily_rate']??0)/8*1.25,2);
    $row['special_holiday_ot_rate']=$old['special_holiday_ot_rate']??((float)($old['special_holiday_ot_hours']??0)>0?round((float)$old['special_holiday_ot_amount']/(float)$old['special_holiday_ot_hours'],2):round((float)($old['daily_rate']??0)/8*1.3*1.3,2));
    $row['billing_additions']=$old['billing_additions']??round((float)$old['total_er_dues']+(float)$old['thirteenth_month_accrual']+(float)($old['admin_overhead_amount']??0),2);
    $old=$row;
    foreach($targets as $field=>$value) {
        if(!in_array($field,BILLING_EDITABLE_COLUMNS,true))throw new InvalidArgumentException('Unknown billing field.');
        if(in_array($field,BILLING_TEXT_FIELDS,true)) {
            if(!is_string($value) || trim($value)==='' || mb_strlen($value)>255)throw new InvalidArgumentException('Enter a non-empty billing label up to 255 characters.');
            $row[$field]=trim($value);
        } else $row[$field]=billing_money($value);
    }
    $set=function(string $field,float $value) use (&$row,$targets): void {if(!array_key_exists($field,$targets))$row[$field]=round($value,2);};
    $delta=function(array $fields)use(&$row,$old):float {$sum=0;foreach($fields as $field)$sum+=(float)$row[$field]-(float)$old[$field];return round($sum,2);};
    if(isset($targets['daily_rate']) || isset($targets['days_worked']))$set('basic_total',(float)$row['daily_rate']*(float)$row['days_worked']);
    if(isset($targets['daily_rate']))$set('regular_ot_rate',(float)$row['daily_rate']/8*1.25);
    if(isset($targets['daily_rate']) || isset($targets['regular_ot_rate']) || isset($targets['regular_ot_hours']))$set('regular_ot_amount',(float)$row['regular_ot_rate']*(float)$row['regular_ot_hours']);
    if(isset($targets['lates_minutes']))$set('lates_undertime',(float)$row['lates_minutes']*(float)$row['daily_rate']/480);
    // Use the original effective pay per hour, preserving holiday/rest-day mixes.
    foreach(['holiday_duty_hrs'=>'holiday_duty','holiday_ot_hrs'=>'holiday_ot_amount','special_holiday_hrs'=>'special_holiday_pay','special_holiday_ot_hours'=>'special_holiday_ot_amount'] as $hours=>$pay) {
        if(isset($targets[$hours]) && !isset($targets[$pay])) {
            if((float)$old[$hours]>0)$set($pay,(float)$row[$hours]*(float)$old[$pay]/(float)$old[$hours]);
            elseif((float)$row[$hours]>0 && !($hours==='special_holiday_ot_hours' && isset($targets['special_holiday_ot_rate'])))throw new InvalidArgumentException('Also enter '.billing_component_label($pay).' when adding holiday hours without an original pay basis.');
        }
    }
    if(isset($targets['special_holiday_ot_rate']))$set('special_holiday_ot_amount',(float)$row['special_holiday_ot_rate']*(float)$row['special_holiday_ot_hours']);
    $set('gross_salary',(float)$old['gross_salary']+$delta(['basic_total','regular_ot_amount','sunday_ot_amount'])-$delta(['lates_undertime']));
    $set('additional_pay',(float)$old['additional_pay']+$delta(['allowances','sil_accrual','addback','holiday_duty','holiday_ot_amount','special_holiday_pay','special_holiday_ot_amount']));
    $set('gross_before_lates',(float)$old['gross_before_lates']+$delta(['gross_salary','additional_pay']));
    $set('gross_payroll',(float)$old['gross_payroll']+$delta(['gross_before_lates'])-$delta(['additional_lates']));
    $set('total_ee_deductions',(float)$old['total_ee_deductions']+$delta(['sss_ee','philhealth_ee','pagibig_ee','other_ee_deductions','additional_pagibig_ee','additional_philhealth_ee','other_pagibig_ee']));
    $set('net_salary',(float)$row['gross_payroll']-(float)$row['total_ee_deductions']);
    if(abs((float)$row['net_salary']-((float)$row['gross_payroll']-(float)$row['total_ee_deductions']))>0.011)throw new InvalidArgumentException('Net salary must equal gross payroll minus total deductions. Correct the workbook totals.');
    $set('total_er_dues',(float)$old['total_er_dues']+$delta(['ec_er','sss_er_regular','sss_er_mpf','philhealth_er','pagibig_er']));
    $row['subtotal_labor_cost']=round((float)$row['gross_payroll']+(float)$row['total_er_dues']+(float)$row['thirteenth_month_accrual'],2);
    $set('admin_overhead_amount',$row['subtotal_labor_cost']*(float)$row['admin_overhead_pct']/100);
    $row['vat_on_overhead']=round((float)$row['admin_overhead_amount']*(float)$row['vat_pct']/100,2);
    $set('billing_additions',(float)$row['total_er_dues']+(float)$row['thirteenth_month_accrual']+(float)$row['admin_overhead_amount']);
    $set('grand_total_invoice',(float)$row['gross_payroll']+(float)$row['billing_additions']+(float)$row['vat_on_overhead']);
    foreach(BILLING_EDITABLE_COLUMNS as $field)if(!in_array($field,BILLING_TEXT_FIELDS,true))$row[$field]=billing_money($row[$field]);
    // Extra HDMF/PHIC deductions in previously blank workbook columns are persisted.
    foreach(BILLING_EDITABLE_COLUMNS as $field)if(isset($targets[$field]) || $row[$field]!==$old[$field])$row['billing_overrides'][$field]=true;
    $row['persisted_philhealth_ee']=round((float)$row['philhealth_ee']+(float)$row['additional_philhealth_ee'],2);
    $row['persisted_pagibig_ee']=round((float)$row['pagibig_ee']+(float)$row['additional_pagibig_ee']+(float)$row['other_pagibig_ee'],2);
    if($row['total_ee_deductions']+0.011<$row['sss_ee']+$row['persisted_philhealth_ee']+$row['persisted_pagibig_ee'])throw new InvalidArgumentException('Total deductions cannot be less than the employee contribution deductions. Correct the workbook totals.');

    return $row;
}
function billing_refresh_totals(array $export): array {
    $export['totals']=['grand_total'=>0.0,'net_salary'=>0.0,'gross_payroll'=>0.0,'labor_cost'=>0.0];
    foreach($export['rows'] as $row) foreach(['grand_total'=>'grand_total_invoice','net_salary'=>'net_salary','gross_payroll'=>'gross_payroll','labor_cost'=>'subtotal_labor_cost'] as $total=>$key)
        $export['totals'][$total]=round($export['totals'][$total]+(float)$row[$key],2);
    return $export;
}
/** Freeze original calculated detail and authoritative finalized amounts. Caller holds run lock. */
function billing_prepare_snapshot(mysqli $db,array $export): array {
    $run=$export['run'];$members=[];$sites=[];
    foreach($export['rows'] as &$row) {
        $employee=(int)$row['employee_db_id'];$dtr=(int)$row['dtr_submission_id'];
        $p=payroll_query($db,'SELECT p.* FROM payroll p JOIN status s ON s.StatusID=p.StatusID WHERE p.EmployeeID=? AND p.DTR_SubmissionID=? AND p.PayPeriodStart=? AND p.PayPeriodEnd=? AND s.Name=\'Finalized\' FOR UPDATE','iiss',[$employee,$dtr,$run['PeriodStart'],$run['PeriodEnd']])->get_result()->fetch_all(MYSQLI_ASSOC);
        if(count($p)>1) throw new InvalidArgumentException('Duplicate finalized payroll records exist. Resolve them before export.');
        $row['payroll_id']=$p ? (int)$p[0]['Payroll_ID'] : null;
        $row['payroll_fingerprint']=$p ? billing_fingerprint($p[0]) : null;
        $row['payroll_snapshot']=$p ? $p[0] : null;
        if($p) {
            $pay=$p[0];
            // Persisted totals are authoritative; never regenerate final pay from current rates.
            $row['basic_total']=(float)$pay['BasicSalary'];
            $row['allowances']=(float)$pay['Allowances'];
            $row['sss_ee']=(float)$pay['SSSDeduction'];$row['philhealth_ee']=round((float)$pay['PhilHealthDeduction']-(float)($row['additional_philhealth_ee']??0),2);$row['pagibig_ee']=round((float)$pay['PagIBIGDeduction']-(float)($row['additional_pagibig_ee']??0)-(float)($row['other_pagibig_ee']??0),2);
            $row['other_ee_deductions']=round((float)$pay['TotalDeductions']-(float)$pay['SSSDeduction']-(float)$pay['PhilHealthDeduction']-(float)$pay['PagIBIGDeduction'],2);
            $row['protected_other_deductions']=round((float)$pay['TaxDeduction']+(float)$pay['LoanDeduction'],2);
            $row['gross_payroll']=(float)$pay['GrossPay'];$row['total_ee_deductions']=(float)$pay['TotalDeductions'];$row['net_salary']=(float)$pay['NetPay'];
            $row=billing_apply_components($row,[]);
            $members[]=$row;
        }
        $sites[(int)$row['site_id']]=true;
    } unset($row);
    if(count($sites)!==1) throw new InvalidArgumentException('Choose a single deployment-site run with verified employees.');
    $site=(int)array_key_first($sites);
    if(!empty($run['SiteID']) && (int)$run['SiteID']!==$site) throw new InvalidArgumentException('Payroll run site mismatch.');
    $export['run']['SiteID']=$site;
    $export['run']['PayrollTypeSnapshot']=$run['PayrollTypeSnapshot'] ?? $run['current_payroll_type'] ?? null;
    payroll_query($db,'UPDATE payroll_runs SET PayrollTypeSnapshot=COALESCE(PayrollTypeSnapshot,?) WHERE PayrollRunID=?','si',[$export['run']['PayrollTypeSnapshot'],(int)$run['PayrollRunID']]);
    payroll_query($db,'UPDATE payroll_runs SET SiteID=? WHERE PayrollRunID=? AND (SiteID IS NULL OR SiteID=?)','iii',[$site,(int)$run['PayrollRunID'],$site]);
    $existing=payroll_query($db,'SELECT EmployeeID,Payroll_ID,DTR_SubmissionID FROM payroll_run_members WHERE PayrollRunID=? ORDER BY EmployeeID','i',[(int)$run['PayrollRunID']])->get_result()->fetch_all(MYSQLI_ASSOC);
    if($existing) {
        $a=array_map('strval',array_column($existing,'Payroll_ID','EmployeeID'));$b=[];foreach($members as $r)$b[(string)$r['employee_db_id']]=(string)$r['payroll_id'];ksort($a);ksort($b);
        if($a!==$b) throw new RuntimeException('The archived payroll roster changed. Resolve the run membership before exporting.',409);
    }
    // A pre-finalization export is review-only. Freeze membership once every payroll exists.
    if(count($members)===count($export['rows'])) foreach($members as $row) payroll_query($db,'INSERT IGNORE INTO payroll_run_members (PayrollRunID,EmployeeID,Payroll_ID,DTR_SubmissionID,SiteID,SnapshotJson) VALUES(?,?,?,?,?,?)','iiiiis',[(int)$run['PayrollRunID'],(int)$row['employee_db_id'],(int)$row['payroll_id'],(int)$row['dtr_submission_id'],$site,billing_json($row)]);
    return billing_refresh_totals($export);
}
function billing_capture_cells(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,array $export): array {
    $sheet=$book->getSheet(0);$cells=[];
    foreach($sheet->getCellCollection()->getCoordinates() as $address) {
        $value=$sheet->getCell($address)->getValue();
        if($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText)$value=$value->getPlainText();
        if($value!==null && $value!=='')$cells[$address]=$value;
    }
    for($r=1;$r<=6+max(1,count($export['rows']));$r++) for($c=1;$c<=49;$c++) {
        $address=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$r;
        $value=$sheet->getCell($address)->getValue();
        if($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) $value=$value->getPlainText();
        $cells[$address]=$value;
    }
    return $cells;
}
/** Evaluate only the renderer's known arithmetic; never run workbook formulas. */
function billing_original_values(array $snapshot): array {
 if(isset($snapshot['column_values']))return $snapshot['column_values'];
 $values=[];
 foreach($snapshot['rows'] as $i=>$row) {
  $r=$i+6;$seen=[];
  $get=function(string $column)use(&$get,&$seen,$snapshot,$r):float {
   if(isset($seen[$column]))return $seen[$column];
   $raw=$snapshot['cells'][$column.$r]??0;
   if(is_numeric($raw)||$raw===null||$raw==='')return $seen[$column]=(float)$raw;
   $value=match($column) {
    'G'=>$get('E')*$get('F'),'I'=>$get('E')/8*1.25,
    'N'=>$get('G')+$get('K')-$get('M')+$get('H'),'P'=>$get('E')*5/24,
    'Y'=>$get('X')>0?$get('Z')/$get('X'):$get('E')/8*1.3*1.3,
    'AA'=>$get('O')+$get('P')+$get('Q')+$get('S')+$get('U')+$get('W')+$get('Z'),
    'AB'=>$get('N')+$get('AA'),'AD'=>$get('AB'),
    'AL'=>$get('AE')+$get('AH')+$get('AJ')+$get('AK'),'AM'=>$get('AD')-$get('AL'),
    'AS'=>$get('AN')+$get('AO')+$get('AP')+$get('AQ')+$get('AR'),
    'AV'=>$get('AS')+$get('AT')+$get('AU'),
    default=>throw new InvalidArgumentException('Unsupported original billing value in '.$column.$r.'. Export a fresh workbook.')
   };
   return $seen[$column]=$value;
  };
  foreach(BILLING_EDITABLE_COLUMNS as $column=>$field)$values[$column.$r]=in_array($field,BILLING_TEXT_FIELDS,true)?($snapshot['cells'][$column.$r]??''):round($get($column),2);
 }
 return $values;
}
function billing_capture_values(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,array $snapshot): array {
 unset($snapshot['column_values']);$snapshot['cells']=billing_capture_cells($book,$snapshot);
 return billing_original_values($snapshot);
}
function billing_add_manifest(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,array $snapshot,string $token): void {
    $sheet=$book->createSheet();$sheet->setTitle('_ONAZUL_EXPORT');
    $manifest=['version'=>1,'token'=>$token,'run_id'=>(int)$snapshot['run']['PayrollRunID'],'run_code'=>$snapshot['run']['RunCode'],'billing_ref'=>client_billing_billing_ref($snapshot['run']['RunCode']),'company_id'=>(int)$snapshot['run']['CompanyID'],'site_id'=>(int)$snapshot['run']['SiteID'],'start'=>$snapshot['run']['PeriodStart'],'end'=>$snapshot['run']['PeriodEnd']];
    $sheet->setCellValueExplicit('A1',billing_json($manifest),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    foreach($snapshot['rows'] as $i=>$row) $sheet->setCellValueExplicit('A'.($i+2),billing_json(['row'=>$i+6,'employee_id'=>(int)$row['employee_db_id'],'employee_number'=>$row['employee_id'],'payroll_id'=>$row['payroll_id'],'dtr_id'=>(int)$row['dtr_submission_id']]),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
    $book->setActiveSheetIndex(0);
}
function billing_validate_zip(string $path): void {
    $zip=new ZipArchive();if($zip->open($path)!==true) throw new InvalidArgumentException('Choose a valid XLSX workbook.');
    try {
        $total=0;if($zip->numFiles>2000) throw new InvalidArgumentException('Workbook contains too many entries.');
        for($i=0;$i<$zip->numFiles;$i++) {
            $entry=$zip->statIndex($i);$name=$entry['name'];$total+=(int)$entry['size'];
            if($total>50*1024*1024 || preg_match('~vbaProject|externalLinks|\.\./~i',$name)) throw new InvalidArgumentException('Macros, external links or oversized workbooks are not accepted.');
            if(str_ends_with(strtolower($name),'.xml') && preg_match('/<!DOCTYPE|<!ENTITY/i',(string)$zip->getFromIndex($i))) throw new InvalidArgumentException('Unsupported XML content.');
        }
        if($zip->locateName('[Content_Types].xml')===false || $zip->locateName('xl/workbook.xml')===false) throw new InvalidArgumentException('Choose an XLSX workbook.');
    } finally {$zip->close();}
}
/** Validate protected cells verbatim. Do not execute any imported formula. */
function billing_detect_changes(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,array $snapshot,array $manifest): array {
    $run=$snapshot['run'];$originalValues=billing_original_values($snapshot);
    $expected=['version'=>1,'token'=>$snapshot['token'],'run_id'=>(int)$run['PayrollRunID'],'run_code'=>$run['RunCode'],'billing_ref'=>client_billing_billing_ref($run['RunCode']),'company_id'=>(int)$run['CompanyID'],'site_id'=>(int)$run['SiteID'],'start'=>$run['PeriodStart'],'end'=>$run['PeriodEnd']];
    if($manifest!==$expected) throw new InvalidArgumentException('Workbook identity does not match its original payroll run.');
    if($book->getSheetCount()!==2 || $book->getSheet(0)->getTitle()!==$snapshot['sheet_title']) throw new InvalidArgumentException('Workbook structure changed.');
    $sheet=$book->getSheet(0);$metadata=$book->getSheetByName('_ONAZUL_EXPORT');$changes=[];
    foreach($snapshot['rows'] as $index=>$row) {
        $r=$index+6;
        $identity=json_decode((string)$metadata->getCell('A'.($index+2))->getValue(),true);
        if($identity!==['row'=>$r,'employee_id'=>(int)$row['employee_db_id'],'employee_number'=>$row['employee_id'],'payroll_id'=>$row['payroll_id'],'dtr_id'=>(int)$row['dtr_submission_id']]) throw new InvalidArgumentException('Employee identity or row order changed.');
    }
    foreach($snapshot['cells'] as $address=>$old) {
        preg_match('/^([A-Z]+)(\d+)$/',$address,$m);
        $editable=isset(BILLING_EDITABLE_COLUMNS[$m[1]]) && (int)$m[2]>=6 && (int)$m[2]<6+count($snapshot['rows']);
        $value=$sheet->getCell($address)->getValue();if($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText)$value=$value->getPlainText();
        $same=is_numeric($old)&&is_numeric($value) ? abs((float)$old-(float)$value)<0.0000001 : $old===$value;
        if(!$same && !$editable)throw new InvalidArgumentException('Workbook header, payroll identity or layout cell '.$address.' changed. Edit employee-row billing values only.');
        if(!$same && is_string($value) && str_starts_with($value,'='))throw new InvalidArgumentException('Changed formula in '.$address.'. Enter the corrected numeric value instead of a new formula.');
    }
    foreach($snapshot['rows'] as $index=>$row) {
        $r=$index+6;
        foreach(BILLING_EDITABLE_COLUMNS as $column=>$component) {
            $address=$column.$r;$raw=$sheet->getCell($address)->getValue();$original=$snapshot['cells'][$address]??null;
            $same=is_numeric($original)&&is_numeric($raw) ? abs((float)$original-(float)$raw)<0.0000001 : $original===$raw;
            if($same)continue; // Server recalculates dependencies of changed inputs.
            if(in_array($component,BILLING_TEXT_FIELDS,true)) {
                if(!is_string($raw)||trim($raw)===''||mb_strlen($raw)>255)throw new InvalidArgumentException('Invalid text in '.$address.'.');
                $value=trim($raw);$old=(string)$original;$difference=null;
            } else {
                $value=billing_money($raw);
                $old=(float)($originalValues[$address]??(is_numeric($original)?$original:($row[$component]??0)));
                $difference=round($value-$old,2);if(abs($difference)<0.005)continue;
            }
            $changes[]=['employee_id'=>(int)$row['employee_db_id'],'employee_number'=>$row['employee_id'],'employee_name'=>$row['employee_name'],'payroll_id'=>$row['payroll_id'],'component'=>$component,'label'=>billing_component_label($component),'column'=>$column,'old'=>$old,'new'=>$value,'difference'=>$difference];
        }
    }

    // Reject inserted employee identities anywhere beyond the signed roster.
    if($sheet->getHighestDataRow()>2000 || \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn())>100) throw new InvalidArgumentException('Workbook exceeds the supported layout.');
    foreach($sheet->getCellCollection()->getCoordinates() as $address) {
        $value=$sheet->getCell($address)->getValue();
        if(!array_key_exists($address,$snapshot['cells']) && $value!==null && $value!=='') throw new InvalidArgumentException('Unexpected cell '.$address.' was added.');
    }
    foreach($metadata->getCellCollection()->getCoordinates() as $address) {
        $value=$metadata->getCell($address)->getValue();
        if($value!==null && $value!=='' && !in_array($address,array_map(static fn($r)=>'A'.$r,range(1,count($snapshot['rows'])+1)),true))throw new InvalidArgumentException('Workbook metadata changed.');
    }
    $originalRows=count($snapshot['rows']);
    for($r=6+$originalRows+1;$r<=$sheet->getHighestDataRow();$r++) {
        $a=$sheet->getCell('A'.$r)->getValue();$b=$sheet->getCell('B'.$r)->getValue();
        if(is_numeric($a) && trim((string)$b)!=='') throw new InvalidArgumentException('Unexpected employee rows were added.');
    }
    return $changes;
}
/** The confirmation is atomic, stale-preview safe and replay-safe. */
function billing_confirm_revision(mysqli $db,string $token,string $reason,int $actor): array {
    if(trim($reason)==='' || mb_strlen($reason)>2000) throw new InvalidArgumentException('Enter a revision reason (maximum 2,000 characters).');
    $lookup=payroll_query($db,'SELECT PayrollRunID FROM payroll_billing_revisions WHERE PreviewToken=?','s',[$token])->get_result()->fetch_assoc();
    if(!$lookup) throw new RuntimeException('Billing preview not found.',404);
    $db->begin_transaction();
    try {
        $run=payroll_query($db,'SELECT * FROM payroll_runs WHERE PayrollRunID=? FOR UPDATE','i',[(int)$lookup['PayrollRunID']])->get_result()->fetch_assoc();
        $revision=payroll_query($db,'SELECT * FROM payroll_billing_revisions WHERE PreviewToken=? FOR UPDATE','s',[$token])->get_result()->fetch_assoc();
        if((int)$revision['CreatedBy']!==$actor) throw new RuntimeException('This preview belongs to another Accounting session.',403);
        if($revision['ConfirmedAt']) {$db->commit();return ['success'=>true,'already_confirmed'=>true,'changed_employees'=>(int)$revision['ChangedEmployees']];}
        if($run['CurrentStatus']==='Payroll Released') throw new RuntimeException('Released payroll cannot be revised through billing import.',409);
        $preview=json_decode($revision['PreviewJson'],true,512,JSON_THROW_ON_ERROR);
        $export=payroll_query($db,'SELECT * FROM payroll_billing_exports WHERE ExportID=?','i',[(int)$revision['ExportID']])->get_result()->fetch_assoc();
        $latest=payroll_query($db,'SELECT ExportID FROM payroll_billing_exports WHERE PayrollRunID=? ORDER BY ExportID DESC LIMIT 1','i',[(int)$run['PayrollRunID']])->get_result()->fetch_assoc();
        if((int)$latest['ExportID']!==(int)$export['ExportID']) throw new RuntimeException('A newer export exists. Export and preview the latest workbook.',409);
        $snapshot=json_decode($export['SnapshotJson'],true,512,JSON_THROW_ON_ERROR);
        $targets=[];foreach($preview['changes'] as $c)$targets[(int)$c['employee_id']][$c['component']]=$c['new'];
        $changed=0;
        foreach($snapshot['rows'] as &$row) {
            if(empty($row['payroll_id'])) throw new RuntimeException('Finalize this payroll run and export a new workbook before importing changes.',409);
            payroll_query($db,'SELECT DTR_SubmissionID FROM dtr_submissions WHERE DTR_SubmissionID=? FOR UPDATE','i',[(int)$row['dtr_submission_id']]);
            $status=payroll_status_for_dtr($db,(int)$row['dtr_submission_id']);
            if(($status['current']['status'] ?? '')==='Payroll Released')throw new RuntimeException('Released payroll cannot be revised through billing import.',409);
            $p=payroll_query($db,'SELECT * FROM payroll WHERE Payroll_ID=? FOR UPDATE','i',[(int)$row['payroll_id']])->get_result()->fetch_assoc();
            $member=payroll_query($db,'SELECT * FROM payroll_run_members WHERE PayrollRunID=? AND EmployeeID=? AND Payroll_ID=? AND DTR_SubmissionID=? AND SiteID=?','iiiii',[(int)$run['PayrollRunID'],(int)$row['employee_db_id'],(int)$row['payroll_id'],(int)$row['dtr_submission_id'],(int)$run['SiteID']])->get_result()->fetch_assoc();
            if(!$p || !$member || billing_fingerprint($p)!==$row['payroll_fingerprint']) throw new RuntimeException('Payroll changed after export. Export and preview a fresh workbook.',409);
            if(!isset($targets[(int)$row['employee_db_id']])) continue;
            $old=billing_apply_components($row,[]);$row=billing_apply_components($row,$targets[(int)$row['employee_db_id']]);
            foreach($targets[(int)$row['employee_db_id']] as $component=>$amount) {
                $isText=in_array($component,BILLING_TEXT_FIELDS,true);
                $previous=$old[$component]??0;
                $diff=$isText?null:round((float)$amount-(float)$previous,2);
                if(!$isText && abs($diff)<0.005)continue;
                // Monetary correction rows are audit records; avoid feeding hour or rate changes into pay twice.
                if(!$isText && !preg_match('/hours|hrs|rate|days|minutes/',$component)) {
                    $deduction=str_contains($component,'deduction') || str_ends_with($component,'_ee') || $component==='lates_undertime' || $component==='additional_lates';
                    $addition=$deduction?($diff<0?1:0):($diff>0?1:0);
                    $type=$component==='allowances'?'Billing Allowance':($component==='other_ee_deductions'?'Billing Other Deduction':'Billing Revision');
                    payroll_query($db,'INSERT INTO payroll_adjustments (Payroll_ID,EmployeeID,AdjustmentType,Amount,IsAddition,Reason,ApprovedBy,CreatedBy) VALUES(?,?,?,?,?,?,?,?)','iisdisii',[(int)$row['payroll_id'],(int)$row['employee_db_id'],$type,abs($diff),$addition,$reason,$actor,$actor]);
                    payroll_query($db,'INSERT INTO payroll_billing_adjustment_sources (RevisionID,Payroll_ID,Component,AdjustmentID) VALUES(?,?,?,?)','iisi',[(int)$revision['RevisionID'],(int)$row['payroll_id'],$component,(int)$db->insert_id]);
                }
                $context=['run_id'=>(int)$run['PayrollRunID'],'billing_ref'=>$run['BillingRefNo'],'employee_id'=>(int)$row['employee_db_id'],'component'=>$component,'revision_id'=>(int)$revision['RevisionID'],'workbook_hash'=>$revision['FileHash'],'reason'=>$reason];
                payroll_query($db,'INSERT INTO payroll_audit_log (Payroll_ID,Action,PerformedBy,OldValue,NewValue,IPAddress) VALUES(?,?,?,?,?,?)','isisss',[(int)$row['payroll_id'],'Billing Revision',$actor,billing_json($context+['value'=>$previous]),billing_json($context+['value'=>$amount,'difference'=>$diff]),substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45)]);
            }
            $values=['BasicSalary'=>$row['basic_total'],'Allowances'=>$row['allowances'],'GrossPay'=>$row['gross_payroll'],'TotalDeductions'=>$row['total_ee_deductions'],'NetPay'=>$row['net_salary'],'SSSDeduction'=>$row['sss_ee'],'PhilHealthDeduction'=>$row['persisted_philhealth_ee'],'PagIBIGDeduction'=>$row['persisted_pagibig_ee']];
            foreach(['OvertimePay'=>['regular_ot_amount','sunday_ot_amount','holiday_ot_amount','special_holiday_ot_amount'],'HolidayPay'=>['holiday_duty','special_holiday_pay']] as $field=>$parts){$values[$field]=(float)$p[$field];foreach($parts as $part)$values[$field]+=(float)$row[$part]-(float)($old[$part]??0);$values[$field]=billing_money(round($values[$field],2));}
            if(isset($targets[(int)$row['employee_db_id']]['days_worked'])){$values['TotalDaysWorked']=$row['days_worked'];$values['RegularHours']=round((float)$row['days_worked']*8,2);}
            if(isset($targets[(int)$row['employee_db_id']]['regular_ot_hours']))$values['OvertimeHours']=round((float)$p['OvertimeHours']+(float)$row['regular_ot_hours']-(float)$old['regular_ot_hours'],2);
            if(isset($values['RegularHours'])||isset($values['OvertimeHours']))$values['TotalHoursWorked']=round((float)($values['RegularHours']??$p['RegularHours'])+(float)($values['OvertimeHours']??$p['OvertimeHours']),2);
            if(isset($values['TotalDaysWorked']) && $values['TotalDaysWorked']>999.99)throw new InvalidArgumentException('Days worked exceed supported payroll limits.');
            foreach(['RegularHours','OvertimeHours','TotalHoursWorked'] as $field)if(isset($values[$field]) && ($values[$field]<0||$values[$field]>99999.99))throw new InvalidArgumentException('Hours exceed supported payroll limits.');
            $args=array_values($values);$args[]=(int)$row['payroll_id'];
            payroll_query($db,'UPDATE payroll SET '.implode(',',array_map(static fn($field)=>$field.'=?',array_keys($values))).' WHERE Payroll_ID=?',str_repeat('d',count($values)).'i',$args);
            require_once __DIR__.'/../Common/NotificationHelper.php';
            $users=payroll_query($db,"SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID JOIN employee e ON LOWER(TRIM(e.Email))=LOWER(TRIM(u.Email)) WHERE e.EmployeeID=? AND r.RoleName='Employee' AND u.IsActive=1",'i',[(int)$row['employee_db_id']])->get_result();
            $labels=array_map('billing_component_label',array_keys($targets[(int)$row['employee_db_id']]));
            $message='Your payroll for '.$run['PeriodStart'].' to '.$run['PeriodEnd'].' was updated after a billing revision. Changed: '.implode(', ',$labels).'. Net pay: ₱'.number_format((float)$p['NetPay'],2).' → ₱'.number_format((float)$row['net_salary'],2).'. Reason: '.$reason.'. Open Payroll to view the updated details.';
            while($recipient=$users->fetch_assoc())if(!insert_system_notification($db,(int)$recipient['UsersID'],'payroll_billing_updated','Payroll Updated',$message,'payroll',(int)$row['payroll_id']))throw new RuntimeException('Unable to notify employee of payroll revision.');
            payroll_query($db,'UPDATE payroll_run_members SET SnapshotJson=? WHERE PayrollRunID=? AND EmployeeID=?','sii',[billing_json($row),(int)$run['PayrollRunID'],(int)$row['employee_db_id']]);
            $changed++;
        } unset($row);
        $snapshot=billing_refresh_totals($snapshot);
        payroll_query($db,'UPDATE payroll_runs SET TotalInvoice=? WHERE PayrollRunID=?','di',[$snapshot['totals']['grand_total'],(int)$run['PayrollRunID']]);
        payroll_query($db,'UPDATE payroll_billing_revisions SET Reason=?,ConfirmedBy=?,ConfirmedAt=NOW(),ChangedEmployees=? WHERE RevisionID=?','siii',[$reason,$actor,$changed,(int)$revision['RevisionID']]);
        $db->commit();return ['success'=>true,'changed_employees'=>$changed,'message'=>$changed.' employee payroll record(s) updated.'];
    } catch(Throwable $e) {$db->rollback();throw $e;}
}
