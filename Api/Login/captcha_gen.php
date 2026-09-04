<?php
require_once __DIR__ . '/../Common/session_init.php';

// Generate simple math CAPTCHA numbers (e.g. 92 + 4)
$minA = 10;  // first number 10–99
$maxA = 99;
$minB = 1;   // second number 1–9
$maxB = 9;

$num1 = rand($minA, $maxA);
$num2 = rand($minB, $maxB);
$answer = $num1 + $num2;

// Store the correct answer in session for server-side verification
$_SESSION['captcha_code'] = $answer;

header('Content-Type: application/json');
echo json_encode([
    'a' => $num1,
    'b' => $num2
]);
?>