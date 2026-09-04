<?php
/**
 * Validates redirect_uri used in OAuth token exchange (must match the
 * authorization request and the Authorized redirect URIs in Google Cloud).
 */
function google_oauth_redirect_uri_is_allowed(string $uri): bool
{
    $p = parse_url($uri);
    if (!$p || empty($p['scheme']) || ($p['scheme'] !== 'http' && $p['scheme'] !== 'https')) {
        return false;
    }

    $path = str_replace('\\', '/', $p['path'] ?? '');
    $base = strtolower(basename($path));
    $allowedBasenames = [
        'applicant_landing.html',
        'index.html',
        'unified_google_callback.html',
        'applicant_google_callback.html',
    ];

    if (!in_array($base, $allowedBasenames, true)) {
        return false;
    }

    $ch = $p['host'] ?? '';
    $cp = isset($p['port']) ? (int)$p['port'] : null;
    $clientKey = $ch . ($cp !== null && $cp !== 80 && $cp !== 443 ? ':' . $cp : '');
    $srv = $_SERVER['HTTP_HOST'] ?? 'localhost';

    if (strcasecmp($clientKey, $srv) === 0) {
        return true;
    }

    $swap = function (string $x): ?string {
        if (preg_match('/^localhost(:[0-9]+)?$/', $x)) {
            return preg_replace('/^localhost/', '127.0.0.1', $x);
        }
        if (preg_match('/^127\.0\.0\.1(:[0-9]+)?$/', $x)) {
            return preg_replace('/^127\.0\.0\.1/', 'localhost', $x);
        }
        return null;
    };

    $alt = $swap($clientKey);
    if ($alt !== null && strcasecmp($alt, $srv) === 0) {
        return true;
    }

    $alt2 = $swap($srv);
    if ($alt2 !== null && strcasecmp($alt2, $clientKey) === 0) {
        return true;
    }

    return false;
}
