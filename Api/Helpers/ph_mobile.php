<?php
/** Validate Philippine mobile formats and store the canonical international number. */
function ph_mobile_normalize(string $number): ?string {
    $number = trim($number);
    if (!preg_match('/^(09|\+639|639)\d{9}$/D', $number)) return null;
    if (str_starts_with($number, '09')) return '+63' . substr($number, 1);
    return str_starts_with($number, '+') ? $number : '+' . $number;
}
