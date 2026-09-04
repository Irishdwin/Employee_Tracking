<?php
/**
 * HMAC for applicant_terms.html → accept_applicant_registration_terms.php when the
 * PHP session cookie is not sent with fetch (SameSite / new-tab edge cases).
 * Optional: create applicant_terms_signing.key.php returning a non-empty secret string.
 */

function applicant_terms_signing_key(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $path = __DIR__ . '/applicant_terms_signing.key.php';
    if (is_readable($path)) {
        $k = require $path;
        if (is_string($k) && $k !== '') {
            $cached = $k;
            return $cached;
        }
    }
    $cached = hash('sha256', 'onazul_terms_v1_default_change_in_production_' . __DIR__, true);
    return $cached;
}

function applicant_terms_make_sig(string $email, int $userId): string
{
    $norm = function_exists('mb_strtolower')
        ? mb_strtolower(trim($email), 'UTF-8')
        : strtolower(trim($email));
    return hash_hmac('sha256', $norm . '|' . $userId, applicant_terms_signing_key());
}

function applicant_terms_verify_sig(string $email, int $userId, string $sig): bool
{
    if ($sig === '' || $userId <= 0) {
        return false;
    }
    $expected = applicant_terms_make_sig($email, $userId);
    return hash_equals($expected, $sig);
}
