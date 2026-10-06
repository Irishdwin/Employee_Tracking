<?php
require_once __DIR__ . '/../Common/session_init.php';

// Security-check formula: simple addition, numerator + denominator = answer.
// Both numbers are randomly generated in the 1-100 range on every load and
// on every refresh, which invalidates whatever challenge was stored before.
$minValue = 1;
$maxValue = 100;

$numerator = rand($minValue, $maxValue);
$denominator = rand($minValue, $maxValue);
$answer = $numerator + $denominator;

// Store only the correct answer in session for server-side verification.
// The client never receives or controls this value.
$_SESSION['captcha_code'] = $answer;

header('Content-Type: application/json');
echo json_encode([
    'numerator' => $numerator,
    'denominator' => $denominator
]);