<?php
require_once __DIR__.'/../Common/session_init.php';
require_once __DIR__.'/captcha_helper.php';
login_captcha_headers();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
echo json_encode(login_captcha_validate($_POST['challenge_id'] ?? null,$_POST['answer'] ?? null));
