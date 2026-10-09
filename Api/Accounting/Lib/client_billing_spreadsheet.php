<?php
/**
 * PhpSpreadsheet renderer for client payroll billing template (cols A–AW).
 * Loads SAMPLE.xlsx as master template from Templates/ directory to preserve all native styling, formulas, and layouts.
 */
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * @param array{rows:array<int,array<string,mixed>>,totals:array<string,float>,client_name:string,run:array<string,mixed>} $export
 */
function client_billing_render_spreadsheet(array $export): Spreadsheet
{
    $run = $export['run'];
    $periodLabel = strtoupper((string)($run['CutoffLabel'] ?? ''));
    if ($periodLabel === '') {
        $periodLabel = strtoupper(date('F j', strtotime((string)$run['PeriodStart'])))
            . '-' . date('j Y', strtotime((string)$run['PeriodEnd']));
    }

    $projectRoot = dirname(__DIR__, 3);
    $candidates = [
        $projectRoot . '/Templates/SAMPLE.xlsx',
        $projectRoot . '/SAMPLE.xlsx',
        __DIR__ . '/../../../Templates/SAMPLE.xlsx',
        __DIR__ . '/../../../SAMPLE.xlsx',
        isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/Employee_Tracking/Templates/SAMPLE.xlsx' : '',
        isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/Employee_Tracking/SAMPLE.xlsx' : '',
    ];

    $templatePath = '';
    foreach ($candidates as $path) {
        if ($path !== '' && file_exists($path)) {
            if (!is_readable($path)) {
                throw new RuntimeException('Template file found at ' . $path . ' but is not readable (check file permissions).');
            }
            $templatePath = $path;
            break;
        }
    }

    if ($templatePath === '') {
        throw new RuntimeException('Master sample Excel template (SAMPLE.xlsx) not found. Checked paths: ' . implode(' | ', array_filter($candidates)));
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();
    // The agency's ordinary row is the formula master, including contributions.
    $templateFormulas=[];
    for($c=1;$c<=49;$c++) {
        $cell=$sheet->getCell([$c,6]);
        if($cell->getDataType()===\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA)
            $templateFormulas[$cell->getColumn()]=(string)$cell->getValue();
    }
    $sheet->setTitle(substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $periodLabel) ?: 'Billing', 0, 31));

    // Update period label in cell B1
    $sheet->setCellValue('B1', $periodLabel);

    // The source workbook is presentation only. Remove all sample business data.
    $sheet->setCellValue('AO2', 'CHARGE TO ' . strtoupper((string)$export['client_name']));
    // Discover the signature section in the source template, then row resizing moves it naturally.
    if(isset($export['prepared_by'])) {
        $signatureCell=null;
        foreach($sheet->getRowIterator() as $signatureRow) {
            foreach($signatureRow->getCellIterator() as $cell) {
                if(trim(strtoupper((string)$cell->getValue()))==='PAYROLL IN-CHARGE') {
                    $signatureCell=$cell->getColumn().($cell->getRow()-1);break 2;
                }
            }
        }
        if(!$signatureCell)throw new RuntimeException('Prepared-by signature section not found in billing template.');
        $sheet->setCellValueExplicit($signatureCell,mb_strtoupper((string)$export['prepared_by']),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }
    $sheet->setCellValue('AA67', null);
    $count=count($export['rows']);
    $capacity=max(1,$count); // zero employees retain one blank, styled row
    $nativeStyles=[];$specialFormulas=[];$totalFormulas=[];
    for($c=1;$c<=49;$c++) {
        $nativeStyles[$c]=$sheet->getCell([$c,6])->getXfIndex();
        if($sheet->getCell([$c,16])->getDataType()===\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA)
            $specialFormulas[$c]=(string)$sheet->getCell([$c,16])->getValue();
        $totalFormulas[$c]=$sheet->getCell([$c,17])->getValue();
    }
    $sourceHeight=$sheet->getRowDimension(6)->getRowHeight();
    // Ten ordinary employee rows, then the client's distinct blank exception
    // row. Do not turn the exception into an arbitrary employee's payroll.
    if($capacity>10)$sheet->insertNewRowBefore(16,$capacity-10);
    elseif($capacity<10)$sheet->removeRow(6+$capacity,10-$capacity);
    $specialRow=6+$capacity;$totalRow=$specialRow+1;
    for($r=6;$r<$specialRow;$r++) {
        if($r>=16)$sheet->getRowDimension($r)->setRowHeight($sourceHeight);
        for($c=1;$c<=49;$c++) {
            $sheet->setCellValue([$c,$r],null);
            // Each source column has its own fill, borders and number format.
            if($r>=16)$sheet->getCell([$c,$r])->setXfIndex($nativeStyles[$c]);
        }
    }
    foreach($specialFormulas as $c=>$formula)
        $sheet->setCellValue([$c,$specialRow],\PhpOffice\PhpSpreadsheet\ReferenceHelper::getInstance()->updateFormulaReferencesAnyWorksheet($formula,0,$specialRow-16));
    for($c=5;$c<=49;$c++) {
        $letter=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        // Preserve actual total formulas and deliberate blanks (notably AQ).
        $formula=$totalFormulas[$c];
        if(is_string($formula) && $formula==='=SUM('.$letter.'6:'.$letter.'16)')
            $sheet->setCellValue([$c,$totalRow],'=SUM('.$letter.'6:'.$letter.$specialRow.')');
    }
    $sheet->setCellValue('B'.$totalRow,'TOTAL');

    $dataStartRow = 6;
    $rowIndex = $dataStartRow;

    foreach ($export['rows'] as $rowData) {
        $sheet->setCellValue('A' . $rowIndex, '=ROW(A'.($rowIndex-5).')');
        $sheet->setCellValueExplicit('B' . $rowIndex, strtoupper((string)($rowData['employee_name'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C' . $rowIndex, strtoupper((string)($rowData['designation'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D' . $rowIndex, strtoupper((string)($rowData['area_site'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $sheet->setCellValue('E' . $rowIndex, (float)($rowData['daily_rate'] ?? 0));
        $sheet->setCellValue('F' . $rowIndex, (float)($rowData['days_worked'] ?? 0));
        $sheet->setCellValue('G' . $rowIndex, '=E' . $rowIndex . '*F' . $rowIndex);

        $sheet->setCellValue('H' . $rowIndex, (float)($rowData['sunday_ot_amount'] ?? 0));
        $sheet->setCellValue('I' . $rowIndex, '=E' . $rowIndex . '/8*1.25');
        $sheet->setCellValue('J' . $rowIndex, (float)($rowData['regular_ot_hours'] ?? 0));
        $sheet->setCellValue('K' . $rowIndex, (float)($rowData['regular_ot_amount'] ?? 0));

        $sheet->setCellValue('L' . $rowIndex, (float)($rowData['lates_minutes'] ?? 0));
        $sheet->setCellValue('M' . $rowIndex, (float)($rowData['lates_undertime'] ?? 0));
        $sheet->setCellValue('N' . $rowIndex, '=G' . $rowIndex . '+K' . $rowIndex . '-M' . $rowIndex . '+H' . $rowIndex);

        $sheet->setCellValue('O' . $rowIndex, (float)($rowData['allowances'] ?? 0));
        $sheet->setCellValue('P' . $rowIndex, '=E' . $rowIndex . '*5/24');
        $sheet->setCellValue('Q' . $rowIndex, (float)($rowData['addback'] ?? 0));

        $sheet->setCellValue('R' . $rowIndex, (float)($rowData['holiday_duty_hrs'] ?? 0));
        $sheet->setCellValue('S' . $rowIndex, (float)($rowData['holiday_duty'] ?? 0));
        $sheet->setCellValue('T' . $rowIndex, (float)($rowData['holiday_ot_hrs'] ?? 0));
        // Use the engine's holiday OT amount: ordinary regular holidays use
        // 260%, while rest days and double holidays require different rates.
        $sheet->setCellValue('U' . $rowIndex, (float)($rowData['holiday_ot_amount'] ?? 0));

        $sheet->setCellValue('V' . $rowIndex, (float)($rowData['special_holiday_hrs'] ?? 0));
        $sheet->setCellValue('W' . $rowIndex, (float)($rowData['special_holiday_pay'] ?? 0));
        $sheet->setCellValue('X' . $rowIndex, (float)($rowData['special_holiday_ot_hours'] ?? 0));
        // Show the effective rate when the period contains special holiday OT.
        $sheet->setCellValue('Y' . $rowIndex, '=IF(X' . $rowIndex . '>0,Z' . $rowIndex . '/X' . $rowIndex . ',E' . $rowIndex . '/8*1.3*1.3)');
        $sheet->setCellValue('Z' . $rowIndex, (float)($rowData['special_holiday_ot_amount'] ?? 0));

        $sheet->setCellValue('AA' . $rowIndex, '=O' . $rowIndex . '+P' . $rowIndex . '+Q' . $rowIndex . '+S' . $rowIndex . '+U' . $rowIndex . '+W' . $rowIndex . '+Z' . $rowIndex);
        $sheet->setCellValue('AB' . $rowIndex, '=N' . $rowIndex . '+AA' . $rowIndex);
        $sheet->setCellValue('AC' . $rowIndex, (float)($rowData['additional_lates'] ?? 0));
        $sheet->setCellValue('AD' . $rowIndex, '=AB' . $rowIndex);

        $sheet->setCellValue('AF' . $rowIndex, 0);
        $sheet->setCellValue('AG' . $rowIndex, 0);
        $sheet->setCellValue('AI' . $rowIndex, 0);
        $sheet->setCellValue('AE' . $rowIndex, (float)($rowData['sss_ee'] ?? 0));
        $sheet->setCellValue('AH' . $rowIndex, (float)($rowData['philhealth_ee'] ?? 0));
        $sheet->setCellValue('AJ' . $rowIndex, (float)($rowData['pagibig_ee'] ?? 0));
        $sheet->setCellValue('AK' . $rowIndex, (float)($rowData['other_ee_deductions'] ?? 0));
        $sheet->setCellValue('AL' . $rowIndex, '=AE' . $rowIndex . '+AH' . $rowIndex . '+AJ' . $rowIndex . '+AK' . $rowIndex);
        $sheet->setCellValue('AM' . $rowIndex, '=AD' . $rowIndex . '-AL' . $rowIndex);

        $sheet->setCellValue('AN' . $rowIndex, (float)($rowData['ec_er'] ?? 0));
        $sheet->setCellValue('AO' . $rowIndex, (float)($rowData['sss_er_regular'] ?? 0));
        $sheet->setCellValue('AP' . $rowIndex, (float)($rowData['sss_er_mpf'] ?? 0));
        $sheet->setCellValue('AQ' . $rowIndex, (float)($rowData['philhealth_er'] ?? 0));
        $sheet->setCellValue('AR' . $rowIndex, (float)($rowData['pagibig_er'] ?? 0));
        $sheet->setCellValue('AS' . $rowIndex, '=AN' . $rowIndex . '+AO' . $rowIndex . '+AP' . $rowIndex . '+AQ' . $rowIndex . '+AR' . $rowIndex);

        $sheet->setCellValue('AT' . $rowIndex, (float)($rowData['thirteenth_month_accrual'] ?? 0));
        $sheet->setCellValue('AU' . $rowIndex, (float)($rowData['admin_overhead_amount'] ?? 0));
        $sheet->setCellValue('AV' . $rowIndex, '=AS' . $rowIndex . '+AT' . $rowIndex . '+AU' . $rowIndex);
        $sheet->setCellValue('AW' . $rowIndex, (float)($rowData['grand_total_invoice'] ?? 0));

        // H is a count of Sunday duty days in AT/AU, never a peso amount.
        $sheet->setCellValue('H'.$rowIndex, count(array_unique($rowData['sunday_duty_dates'] ?? [])));
        $sheet->setCellValue('O'.$rowIndex, (float)($rowData['allowances']??0)+(float)($rowData['sunday_allowance']??0));
        $sheet->setCellValue('AF'.$rowIndex, (float)($rowData['pagibig_ee']??0)+(float)($rowData['additional_pagibig_ee']??0));
        $sheet->setCellValue('AG'.$rowIndex, (float)($rowData['additional_philhealth_ee']??0));
        $sheet->setCellValue('AI'.$rowIndex, (float)($rowData['pagibig_ee']??0)+(float)($rowData['other_pagibig_ee']??0));
        // Match the source headings: AN SSS, AO EC, AP additional ER SSS.
        $sheet->setCellValue('AN'.$rowIndex, (float)($rowData['sss_er_regular']??0));
        $sheet->setCellValue('AO'.$rowIndex, (float)($rowData['ec_er']??0));
        foreach($templateFormulas as $column=>$formula) {
            $sheet->setCellValue($column.$rowIndex, \PhpOffice\PhpSpreadsheet\ReferenceHelper::getInstance()
                ->updateFormulaReferencesAnyWorksheet($formula,0,$rowIndex-6));
        }
        if(!empty($rowData['legacy_note']))$sheet->getComment('AA'.$rowIndex)->getText()->createTextRun($rowData['legacy_note']);
        $rowIndex++;
    }

    // Saved payroll is shown separately and remains the authoritative payslip.
    $reconciliation=$spreadsheet->createSheet();
    $reconciliation->setTitle('Payroll Reconciliation');
    foreach(client_billing_reconciliation_cells($export, $sheet->getTitle()) as $address=>$value) {
        if(is_string($value) && str_starts_with($value,'=')) $reconciliation->setCellValue($address,$value);
        else $reconciliation->setCellValueExplicit($address,$value,is_numeric($value)?\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC:\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }
    $reconciliation->getStyle('A4:J4')->getFont()->setBold(true);
    $reconciliation->getStyle('C5:J'.max(5,$count+5))->getNumberFormat()->setFormatCode('#,##0.00');
    $reconciliation->getColumnDimension('A')->setWidth(18);
    $reconciliation->getColumnDimension('B')->setWidth(32);
    foreach(range('C','J') as $column)$reconciliation->getColumnDimension($column)->setWidth(23);
    $reconciliation->getRowDimension(2)->setRowHeight(44);
    $reconciliation->mergeCells('A2:J2');
    $reconciliation->getStyle('A2')->getAlignment()->setWrapText(true);
    $reconciliation->freezePane('C5');
    $spreadsheet->setActiveSheetIndex(0);
    \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($spreadsheet)->clearCalculationCache();
    return $spreadsheet;
}

/** Stable protected reconciliation values. No payroll or rate computation. */
function client_billing_reconciliation_cells(array $export, string $title): array
{
    $cells=['A1'=>'Agency billing versus saved payroll',
        'A2'=>'Billing uses the agency template, including its 36.50 daily overhead, deduction and holiday formulas. Differences below do not change saved payroll. Agency row charges exclude summary VAT and withholding. Blank special row remains unassigned.'];
    $headers=['Employee ID','Employee','Saved Gross','Agency Gross','Gross Difference','Saved Net','Agency Net','Net Difference','Saved Invoice incl VAT','Agency row charge'];
    foreach($headers as $i=>$header)$cells[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1).'4']=$header;
    $ref="'".str_replace("'","''",$title)."'!";
    foreach($export['rows'] as $i=>$row) {
        $r=$i+5;$employeeRow=$i+6;
        foreach(['A'=>$row['employee_id']??($row['employee_db_id']??''),'B'=>$row['employee_name']??'',
            'C'=>(float)($row['gross_payroll']??0),'D'=>'='.$ref.'AD'.$employeeRow,'E'=>'=D'.$r.'-C'.$r,
            'F'=>(float)($row['net_salary']??0),'G'=>'='.$ref.'AM'.$employeeRow,'H'=>'=G'.$r.'-F'.$r,
            'I'=>(float)($row['grand_total_invoice']??0),'J'=>'='.$ref.'AW'.$employeeRow] as $column=>$value)$cells[$column.$r]=$value;
    }
    $total=count($export['rows'])+5;$cells['B'.$total]='TOTAL';
    foreach(range('C','J') as $c)$cells[$c.$total]=$total>5?'=SUM('.$c.'5:'.$c.($total-1).')':0;
    return $cells;
}
