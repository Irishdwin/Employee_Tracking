<?php
/** Projects time classifications from the production payroll result; calculates no pay rates. */
function dtr_classification_from_payroll(array $period): array
{
    $ot = $period['ot_detail'] ?? [];
    $ordinary = (float)($ot['regular_ot_hours'] ?? 0) + (float)($ot['approved_overtime_hours'] ?? 0);
    $holidayOt = (float)($ot['holiday_ot_hours'] ?? 0);
    $workedHolidayHours = 0.0;
    $holidays = $period['holiday_breakdown'] ?? [];
    foreach ($holidays as $holiday) {
        if (!empty($holiday['worked'])) {
            $workedHolidayHours += (float)($holiday['duty_hours'] ?? 0);
        }
    }
    return [
        // Matches the production daily-pay model, including worked holidays.
        'regular_hours' => round((float)($period['days_worked'] ?? 0) * 8, 2),
        'ordinary_overtime_hours' => round($ordinary, 2),
        'overtime_hours' => round($ordinary + (float)($ot['sunday_hours'] ?? 0) + $holidayOt, 2),
        'holiday_work_hours' => round($workedHolidayHours, 2),
        'holiday_overtime_hours' => round($holidayOt, 2),
        'approved_overtime_breakdown' => $ot['approved_overtime_breakdown'] ?? [],
        'holiday_breakdown' => $holidays,
        'holiday_ot_breakdown' => $period['holiday_ot_breakdown'] ?? [],
    ];
}

/** Old submissions have no classification snapshot; their missing holiday hours remain unknown. */
function dtr_classification_decode(?string $remarks): ?array
{
    $prefix = 'DTR_CLASSIFICATION_V1:';
    if (!str_starts_with((string)$remarks, $prefix)) {
        return null;
    }
    $value = json_decode(substr($remarks, strlen($prefix)), true);
    return is_array($value) ? $value : null;
}