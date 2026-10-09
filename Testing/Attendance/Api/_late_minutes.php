<?php
declare(strict_types=1);
/** Testing input changes clock-in evidence; production remains the late calculator. */
function testing_late_time_in(mixed $minutes,array $schedule): string {
    if((!is_int($minutes)&&!is_string($minutes))||!preg_match('/^\d{1,4}$/D',(string)$minutes)||(int)$minutes>1439)throw new InvalidArgumentException('Late minutes must be a whole number from 0 to 1439.');
    if(!preg_match('/^(\d{2}):(\d{2})(?::00)?$/D',(string)($schedule['time_in']??''),$parts)||(int)$parts[1]>23||(int)$parts[2]>59)throw new InvalidArgumentException('A valid scheduled start is required to set late minutes.');
    $total=(int)$parts[1]*60+(int)$parts[2]+(int)$minutes;
    if($total>=1440)throw new InvalidArgumentException('These late minutes move Time In to the next date. Choose a smaller value for this same-day attendance test.');
    return sprintf('%02d:%02d',intdiv($total,60),$total%60);
}
