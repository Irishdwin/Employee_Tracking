<?php
/**
 * Shared PHP session with cookie path "/" so requests under /Employee_Tracking/Html/
 * and /Employee_Tracking/Api/ send the same session id (fixes 401 on API calls).
 */
if (session_status() === PHP_SESSION_NONE) {
    $prev = session_get_cookie_params();
    $ss = isset($prev['samesite']) && is_string($prev['samesite']) && $prev['samesite'] !== ''
        ? $prev['samesite']
        : 'Lax';
    session_set_cookie_params([
        'lifetime' => isset($prev['lifetime']) ? (int)$prev['lifetime'] : 0,
        'path' => '/',
        'domain' => isset($prev['domain']) ? (string)$prev['domain'] : '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => $ss,
    ]);
    session_start();
}
