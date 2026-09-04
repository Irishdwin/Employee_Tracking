<?php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

$answer = isset($_POST['answer']) ? trim($_POST['answer']) : '';
$expected = isset($_SESSION['captcha_code']) ? (int)$_SESSION['captcha_code'] : null;

$isValid = ctype_digit($answer) && (int)$answer === $expected;

echo json_encode([
    'valid' => $isValid
]);

?>

