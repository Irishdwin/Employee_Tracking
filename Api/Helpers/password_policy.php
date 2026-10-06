<?php
/**
 * Shared password strength policy (UT-102).
 *
 * A password is NOT accepted merely because it satisfies a character-format
 * pattern (length + upper + lower + digit + special). It must also fail to
 * match a short list of common/default/predictable bases and obvious
 * keyboard/sequential patterns.
 *
 * This is the single authoritative, server-side implementation. All
 * account-creation / password-change / password-reset endpoints must call
 * validate_strong_password() instead of keeping their own copy of this logic.
 */

if (!function_exists('validate_strong_password')) {
    function validate_strong_password(string $password): ?string {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must include at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must include at least one lowercase letter.';
        }
        if (!preg_match('/\d/', $password)) {
            return 'Password must include at least one number.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must include at least one special character.';
        }
        if (is_common_or_predictable_password($password)) {
            return 'Password is too common or predictable. Please choose a stronger password.';
        }
        return null;
    }
}

if (!function_exists('is_common_or_predictable_password')) {
    /**
     * Detects default/common/predictable passwords that would otherwise pass
     * the character-class checks above (e.g. "Admin@123", "Welcome@123").
     *
     * Approach: normalize away case, punctuation/symbols, and a trailing
     * run of digits, then compare the remaining "base" word against a short
     * denylist of common/default bases. This catches predictable variants
     * like Admin@123, admin@123, ADMIN@123, Admin@1234, and Admin#123
     * without relying purely on exact-string matches. Also flags obvious
     * keyboard-row runs, numeric sequences, and long repeated-character runs.
     */
    function is_common_or_predictable_password(string $password): bool {
        static $commonBases = [
            'admin', 'administrator', 'password', 'welcome', 'qwerty', 'qwertyuiop',
            'letmein', 'changeme', 'test', 'testing', 'user', 'guest', 'login',
            'default', 'onazul', 'company', 'employee', 'hr', 'root', 'iloveyou',
            'monkey', 'dragon', 'football', 'master', 'zxcvbnm', 'asdfgh',
        ];

        $lower = strtolower($password);
        $alnum = preg_replace('/[^a-z0-9]/', '', $lower);
        $base = preg_replace('/\d+$/', '', $alnum);
        $normalizedBase = strtr($base, [
            '0' => 'o',
            '1' => 'i',
            '3' => 'e',
            '4' => 'a',
            '5' => 's',
            '7' => 't',
        ]);

        if (in_array($base, $commonBases, true)
            || in_array($normalizedBase, $commonBases, true)
            || in_array($alnum, $commonBases, true)
        ) {
            return true;
        }

        if (preg_match('/0123456789|1234567890|12345678|23456789|87654321|98765432/', $alnum)) {
            return true;
        }
        if (preg_match('/qwertyui|asdfghjk|zxcvbnm|qazwsx/', $alnum)) {
            return true;
        }
        if (preg_match('/(.)\1{4,}/', $alnum)) {
            return true;
        }

        return false;
    }
}
