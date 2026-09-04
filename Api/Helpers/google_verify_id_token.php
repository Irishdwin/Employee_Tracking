<?php
/**
 * Minimal Google ID Token verification for RS256 JWTs.
 * Verifies signature with Google's JWKS and validates basic claims.
 */

function b64url_decode($input) {
    $remainder = strlen($input) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $input .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($input, '-_', '+/'));
}

function google_fetch_jwks() {
    $url = 'https://www.googleapis.com/oauth2/v3/certs';
    $json = @file_get_contents($url);
    if (!$json) {
        throw new Exception('Failed to fetch Google JWKS.');
    }
    $jwks = json_decode($json, true);
    if (!$jwks || !isset($jwks['keys'])) {
        throw new Exception('Invalid JWKS response.');
    }
    return $jwks;
}

function google_verify_via_tokeninfo($idToken, $expectedAudience) {
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode((string)$idToken);
    $json = @file_get_contents($url);
    if (!$json) {
        throw new Exception('Google tokeninfo verification failed.');
    }
    $claims = json_decode($json, true);
    if (!$claims || isset($claims['error_description']) || isset($claims['error'])) {
        $msg = '';
        if (isset($claims['error_description'])) $msg = (string)$claims['error_description'];
        elseif (isset($claims['error'])) $msg = (string)$claims['error'];
        throw new Exception('Google tokeninfo rejected id_token' . ($msg !== '' ? (': ' . $msg) : '.'));
    }

    $iss = $claims['iss'] ?? null;
    if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
        throw new Exception('Invalid JWT issuer.');
    }

    $aud = $claims['aud'] ?? null;
    if ((string)$aud !== (string)$expectedAudience) {
        throw new Exception('Invalid JWT audience.');
    }

    $exp = isset($claims['exp']) ? (int)$claims['exp'] : 0;
    if ($exp <= 0 || time() >= $exp) {
        throw new Exception('JWT expired.');
    }

    if (isset($claims['email_verified'])) {
        $ev = strtolower((string)$claims['email_verified']);
        $claims['email_verified'] = ($ev === 'true' || $ev === '1');
    }

    return $claims;
}

function google_cert_to_pem($x5c0) {
    // $x5c0 is base64-encoded DER cert.
    $pem = "-----BEGIN CERTIFICATE-----\n";
    $pem .= chunk_split($x5c0, 64, "\n");
    $pem .= "\n-----END CERTIFICATE-----\n";
    return $pem;
}

function verify_google_id_token($idToken, $expectedAudience) {
    if (!is_string($idToken) || $idToken === '') {
        throw new Exception('Missing id_token.');
    }

    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        throw new Exception('Invalid JWT format.');
    }

    list($headerB64, $payloadB64, $sigB64) = $parts;
    $headerRaw = b64url_decode($headerB64);
    $payloadRaw = b64url_decode($payloadB64);
    $signature = b64url_decode($sigB64);

    $header = json_decode($headerRaw, true);
    $payload = json_decode($payloadRaw, true);
    if (!$header || !$payload) {
        throw new Exception('Invalid JWT header/payload.');
    }

    $kid = $header['kid'] ?? null;
    $alg = $header['alg'] ?? null;
    if (!$kid) {
        throw new Exception('JWT kid missing.');
    }
    if ($alg !== 'RS256') {
        throw new Exception('Unsupported JWT alg: ' . (string)$alg);
    }

    $iss = $payload['iss'] ?? null;
    if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
        throw new Exception('Invalid JWT issuer.');
    }

    $aud = $payload['aud'] ?? null;
    if (!$aud) {
        throw new Exception('JWT aud missing.');
    }
    // aud can be string or array (usually string).
    if (is_array($aud)) {
        if (!in_array($expectedAudience, $aud, true)) {
            throw new Exception('Invalid JWT audience.');
        }
    } else {
        if ((string)$aud !== (string)$expectedAudience) {
            throw new Exception('Invalid JWT audience.');
        }
    }

    $exp = isset($payload['exp']) ? (int)$payload['exp'] : 0;
    if ($exp <= 0 || time() >= $exp) {
        throw new Exception('JWT expired.');
    }

    // Verify signature
    $jwks = google_fetch_jwks();
    $keys = $jwks['keys'];
    $matchedKey = null;
    foreach ($keys as $k) {
        if (isset($k['kid']) && $k['kid'] === $kid) {
            $matchedKey = $k;
            break;
        }
    }
    if ($matchedKey && !empty($matchedKey['x5c'][0])) {
        $publicKeyPem = google_cert_to_pem($matchedKey['x5c'][0]);
        $signingInput = $headerB64 . '.' . $payloadB64;
        $verified = openssl_verify($signingInput, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
        if ($verified === 1) {
            return $payload;
        }
    }

    // Fallback: ask Google to verify the id_token directly.
    // This handles key-format changes / rotations that can break local JWKS parsing.
    return google_verify_via_tokeninfo($idToken, $expectedAudience);
}

