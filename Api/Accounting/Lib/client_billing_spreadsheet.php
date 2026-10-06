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
    $sheet->setTitle(substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $periodLabel) ?: 'Billing', 0, 31));

    // Update period label in cell B1
    $sheet->setCellValue('B1', $periodLabel);

    // The source workbook is presentation only. Remove all sample business data.
    $sheet->setCellValue('AO2', 'CHARGE TO ' . strtoupper((string)$export['client_name']));
    $sheet->setCellValue('B51', 'ONAZUL');
    $sheet->setCellValue('AA67', null);
    $count=count($export['rows']);
    $capacity=max(1,$count); // zero employees retain one blank, styled row
    $sourceStyle=$sheet->getStyle('A6:AW6');
    $sourceHeight=$sheet->getRowDimension(6)->getRowHeight();
    if($capacity>11)$sheet->insertNewRowBefore(17,$capacity-11);
    elseif($capacity<11)$sheet->removeRow(6+$capacity,11-$capacity);
    $totalRow=6+$capacity;
    for($r=6;$r<$totalRow;$r++) {
        $sheet->duplicateStyle($sourceStyle,'A'.$r.':AW'.$r);
        $sheet->getRowDimension($r)->setRowHeight($sourceHeight);
        for($c=1;$c<=49;$c++)$sheet->setCellValue([$c,$r],null);
    }
    for($c=5;$c<=49;$c++) {
        $letter=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $sheet->setCellValue([$c,$totalRow],$count?'=SUM('.$letter.'6:'.$letter.($totalRow-1).')':0);
    }
    $sheet->setCellValue('B'.$totalRow,'TOTAL');

    $dataStartRow = 6;
    $rowIndex = $dataStartRow;
    $seq = 1;

    foreach ($export['rows'] as $rowData) {
        $sheet->setCellValue('A' . $rowIndex, $seq);
        $sheet->setCellValue('B' . $rowIndex, strtoupper((string)($rowData['employee_name'] ?? '')));
        $sheet->setCellValue('C' . $rowIndex, strtoupper((string)($rowData['designation'] ?? '')));
        $sheet->setCellValue('D' . $rowIndex, strtoupper((string)($rowData['area_site'] ?? '')));

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
        $sheet->setCellValue('AC' . $rowIndex, 0);
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
        $sheet->setCellValue('AW' . $rowIndex, '=AD' . $rowIndex . '+AS' . $rowIndex . '+AT' . $rowIndex . '+AU' . $rowIndex);

        // Represent the production engine's computed values without recalculating
        // payroll from rounded display quantities (for example fractional days).
        foreach(['G'=>'basic_total','N'=>'gross_salary','P'=>'sil_accrual',
            'AB'=>'gross_payroll','AD'=>'gross_payroll','AL'=>'total_ee_deductions',
            'AM'=>'net_salary'] as $column=>$key) {
            if(array_key_exists($key,$rowData))$sheet->setCellValue($column.$rowIndex,(float)$rowData[$key]);
        }
        if(isset($rowData['gross_payroll'],$rowData['gross_salary']))
            $sheet->setCellValue('AA'.$rowIndex,round((float)$rowData['gross_payroll']-(float)$rowData['gross_salary'],2));

        $rowIndex++;
        $seq++;
    }

    $sheet->freezePane('E6');
    $sheet->setSelectedCell('A1');

    return $spreadsheet;
}
