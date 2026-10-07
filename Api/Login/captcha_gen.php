<?php
require_once __DIR__.'/../Common/session_init.php';
require_once __DIR__.'/captcha_helper.php';
login_captcha_headers();
echo json_encode(login_captcha_generate($_POST['replace_challenge_id'] ?? null));
