<?php
require_once __DIR__.'/holiday_scope.php';
/** Calendar reads the same holiday definitions as payroll, including multiple holidays per date.
 * Scope is descriptive, matching existing holiday-range behavior; no pay calculations or DDL.
 */
function employee_calendar_holidays(mysqli $db,string $start,string $end,int $employeeId=0): array {
    $stmt=$db->prepare('SELECT HolidayID,HolidayName,HolidayDate,HolidayType,IsNationwide,Region,Description FROM holidays WHERE HolidayDate BETWEEN ? AND ? ORDER BY HolidayDate,HolidayID');
    $stmt->bind_param('ss',$start,$end);$stmt->execute();$result=$stmt->get_result();$days=[];
    while ($row=$result->fetch_assoc()) {
        if($employeeId>0 && !holiday_applies_to_employee($db,$row,$employeeId,$row['HolidayDate']))continue;
        $days[$row['HolidayDate']][]=['id'=>(int)$row['HolidayID'],'name'=>$row['HolidayName'],'date'=>$row['HolidayDate'],
            'type'=>$row['HolidayType'],'is_nationwide'=>(bool)$row['IsNationwide'],'region'=>$row['Region'],'description'=>$row['Description']];
    }
    $stmt->close();return $days;
}
