<?php
/**
 * Shared PHP session with cookie path "/" so requests under /Employee_Tracking/Html/
 * and /Employee_Tracking/Api/ send the same session id (fixes 401 on API calls).
 *
 * SESSION IDLE TIMEOUT (added):
 * Every API endpoint in this project starts with `require_once .../session_init.php`,
 * so this is the single place to enforce it system-wide. `$_SESSION['last_activity']`
 * is checked here on every request, but is only ever REFRESHED by the dedicated
 * Api/Common/session_heartbeat.php endpoint — which Js/Shared/role_guard.js calls
 * only in response to real user interaction (click/keydown/mousemove/touch),
 * throttled client-side. Ordinary background polling (notification badges, sidebar
 * counters, etc.) hits its own endpoints normally and is checked against the same
 * timeout, but does NOT reset it — so a tab left open and unattended still expires
 * on schedule, even if background scripts keep running in it.
 */

// Idle timeout window. 20 minutes is the shared default agreed for this system —
// change this one constant to retune it everywhere at once.
if (!defined('SESSION_IDLE_TIMEOUT_SECONDS')) {
    define('SESSION_IDLE_TIMEOUT_SECONDS', 20 * 60);
}

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

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) >= SESSION_IDLE_TIMEOUT_SECONDS) {
        // Idle too long — expire the session the same way Api/Login/logout.php does,
        // so a stale session cookie can't just be revived by refreshing the page.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        session_start(); // fresh, empty session so the rest of this request's code
                          // (which expects $_SESSION to exist) doesn't error out —
                          // it will simply see no user_id/role, same as "never logged in".
    } elseif (!isset($_SESSION['last_activity'])) {
        // First request seen for this login (e.g. right after login.php ran) — stamp it.
        $_SESSION['last_activity'] = time();
    }
}
