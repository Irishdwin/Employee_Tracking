<?php
declare(strict_types=1);
const LOGIN_CAPTCHA_TTL = 600;
const LOGIN_CAPTCHA_LIMIT = 64;
function login_captcha_prune(): void {
    $now=time();
    if(!isset($_SESSION['login_captchas']) || !is_array($_SESSION['login_captchas']))$_SESSION['login_captchas']=[];
    foreach($_SESSION['login_captchas'] as $id=>$challenge)
        if(!is_array($challenge) || ($challenge['expires'] ?? 0)<=$now)unset($_SESSION['login_captchas'][$id]);
    unset($_SESSION['captcha_code']);
}
function login_captcha_generate($replace=null): array {
    login_captcha_prune();
    if(is_string($replace))unset($_SESSION['login_captchas'][$replace]);
    while(count($_SESSION['login_captchas'])>=LOGIN_CAPTCHA_LIMIT)array_shift($_SESSION['login_captchas']);
    $a=random_int(1,100);$b=random_int(1,100);$id=bin2hex(random_bytes(32));
    $_SESSION['login_captchas'][$id]=['answer'=>$a+$b,'expires'=>time()+LOGIN_CAPTCHA_TTL];
    return ['challenge_id'=>$id,'numerator'=>$a,'denominator'=>$b];
}
/** Feedback does not consume a challenge; every login validation attempt does. */
function login_captcha_validate($id,$answer,bool $consume=false): array {
    login_captcha_prune();
    if(!is_string($id) || !preg_match('/\A[a-f0-9]{64}\z/D',$id) || !isset($_SESSION['login_captchas'][$id]))
        return ['valid'=>false,'code'=>'expired','message'=>'Security check expired. Please refresh the security check and try again.'];
    $challenge=$_SESSION['login_captchas'][$id];
    if($consume)unset($_SESSION['login_captchas'][$id]);
    $normalized=is_string($answer)?trim($answer):'';
    $valid=(bool)preg_match('/\A[0-9]{1,3}\z/D',$normalized) && (int)$normalized===$challenge['answer'];
    return ['valid'=>$valid,'code'=>$valid?'valid':'incorrect','message'=>$valid?'':'Please enter the correct security check answer.'];
}
function login_captcha_headers(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
}
