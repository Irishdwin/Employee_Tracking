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
        $sheet->setCellValue('K' . $rowIndex, '=I' . $rowIndex . '*J' . $rowIndex);

        $sheet->setCellValue('L' . $rowIndex, (float)($rowData['lates_minutes'] ?? 0));
        $sheet->setCellValue('M' . $rowIndex, (float)($rowData['lates_undertime'] ?? 0));
        $sheet->setCellValue('N' . $rowIndex, '=G' . $rowIndex . '+K' . $rowIndex . '-M' . $rowIndex . '+O' . $rowIndex . '+P' . $rowIndex . '+Q' . $rowIndex . '+U' . $rowIndex . '+Z' . $rowIndex);

        $sheet->setCellValue('O' . $rowIndex, (float)($rowData['allowances'] ?? 0));
        $sheet->setCellValue('P' . $rowIndex, '=E' . $rowIndex . '*5/24');
        $sheet->setCellValue('Q' . $rowIndex, (float)($rowData['addback'] ?? 0));

        $sheet->setCellValue('R' . $rowIndex, (float)($rowData['holiday_duty_hrs'] ?? 0));
        $sheet->setCellValue('S' . $rowIndex, (float)($rowData['holiday_duty'] ?? 0));
        $sheet->setCellValue('T' . $rowIndex, (float)($rowData['holiday_ot_hrs'] ?? 0));
        $sheet->setCellValue('U' . $rowIndex, '=S' . $rowIndex . '+T' . $rowIndex . '*I' . $rowIndex);

        $sheet->setCellValue('V' . $rowIndex, (float)($rowData['special_holiday_hrs'] ?? 0));
        $sheet->setCellValue('W' . $rowIndex, (float)($rowData['special_holiday_pay'] ?? 0));
        $sheet->setCellValue('X' . $rowIndex, (float)($rowData['special_holiday_ot_hours'] ?? 0));
        $sheet->setCellValue('Y' . $rowIndex, '=E' . $rowIndex . '/8*1.3*1.3');
        $sheet->setCellValue('Z' . $rowIndex, '=X' . $rowIndex . '*Y' . $rowIndex);

        $sheet->setCellValue('AA' . $rowIndex, '=O' . $rowIndex . '+P' . $rowIndex . '+Q' . $rowIndex . '+U' . $rowIndex . '+W' . $rowIndex . '+Z' . $rowIndex);
        $sheet->setCellValue('AB' . $rowIndex, '=N' . $rowIndex);
        $sheet->setCellValue('AC' . $rowIndex, '=AA' . $rowIndex);
        $sheet->setCellValue('AD' . $rowIndex, '=AB' . $rowIndex . '+AC' . $rowIndex);

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

        $rowIndex++;
        $seq++;
    }

    $sheet->freezePane('E6');
    $sheet->setSelectedCell('A1');

    return $spreadsheet;
}