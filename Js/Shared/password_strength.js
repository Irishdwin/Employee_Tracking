/**
 * Shared password strength status bar.
 *
 * getPasswordStrength(password) mirrors the server-side policy in
 * Api/Helpers/password_policy.php (UT-102): format checks (length, upper,
 * lower, digit, special) PLUS the common/predictable-password check, so a
 * password like "Admin@123" correctly shows as Weak even though it passes
 * every character-class rule.
 *
 * attachPasswordStrengthMeter(inputEl) builds a small track+fill+label bar
 * right after the given password <input> and keeps it in sync on every
 * keystroke. It's additive UI only — it never blocks submission; the
 * existing per-field validation (and the server) still enforce the policy.
 */
(function (global) {
  'use strict';
  if (typeof global.getPasswordStrength === 'function') {
    return; // already loaded elsewhere on this page
  }

  var COMMON_BASES = [
    'admin', 'administrator', 'password', 'welcome', 'qwerty', 'qwertyuiop',
    'letmein', 'changeme', 'test', 'testing', 'user', 'guest', 'login',
    'default', 'onazul', 'company', 'employee', 'hr', 'root', 'iloveyou',
    'monkey', 'dragon', 'football', 'master', 'zxcvbnm', 'asdfgh'
  ];

  function isCommonOrPredictable(password) {
    var lower = password.toLowerCase();
    var alnum = lower.replace(/[^a-z0-9]/g, '');
    var base = alnum.replace(/\d+$/, '');
    var normalizedBase = base
      .replace(/0/g, 'o').replace(/1/g, 'i').replace(/3/g, 'e')
      .replace(/4/g, 'a').replace(/5/g, 's').replace(/7/g, 't');

    if (COMMON_BASES.indexOf(base) !== -1
      || COMMON_BASES.indexOf(normalizedBase) !== -1
      || COMMON_BASES.indexOf(alnum) !== -1) {
      return true;
    }
    if (/0123456789|1234567890|12345678|23456789|87654321|98765432/.test(alnum)) return true;
    if (/qwertyui|asdfghjk|zxcvbnm|qazwsx/.test(alnum)) return true;
    if (/(.)\1{4,}/.test(alnum)) return true;
    return false;
  }

  /**
   * Returns { level: 'empty' | 'weak' | 'moderate' | 'strong', label: string }
   */
  function getPasswordStrength(password) {
    password = password || '';
    if (password.length === 0) {
      return { level: 'empty', label: '' };
    }

    var checks = [
      password.length >= 8,
      /[A-Z]/.test(password),
      /[a-z]/.test(password),
      /\d/.test(password),
      /[^A-Za-z0-9]/.test(password)
    ];
    var passedCount = checks.filter(Boolean).length;
    var formatOk = passedCount === checks.length;

    if (!formatOk) {
      return passedCount <= 2
        ? { level: 'weak', label: 'Weak' }
        : { level: 'moderate', label: 'Moderate' };
    }

    if (isCommonOrPredictable(password)) {
      return { level: 'weak', label: 'Weak — too common or predictable' };
    }

    return password.length >= 10
      ? { level: 'strong', label: 'Strong' }
      : { level: 'moderate', label: 'Moderate' };
  }

  /**
   * Builds (once) a status bar right under inputEl and keeps it updated.
   * If the input sits inside a Bootstrap .input-group (e.g. paired with a
   * show/hide password button), the bar is placed after the whole group
   * instead of between the input and the button, so it renders directly
   * below the field rather than squeezed inside the group.
   * Safe to call multiple times on the same input.
   */
  function attachPasswordStrengthMeter(inputEl) {
    if (!inputEl || inputEl.dataset.strengthMeterAttached === '1') {
      return;
    }
    inputEl.dataset.strengthMeterAttached = '1';

    var wrapper = document.createElement('div');
    wrapper.className = 'password-strength-meter';
    wrapper.innerHTML =
      '<div class="password-strength-track">' +
      '<div class="password-strength-fill"></div>' +
      '</div>' +
      '<small class="password-strength-label"></small>';

    var anchor = (inputEl.closest && inputEl.closest('.input-group')) || inputEl;
    anchor.insertAdjacentElement('afterend', wrapper);

    var fillEl = wrapper.querySelector('.password-strength-fill');
    var labelEl = wrapper.querySelector('.password-strength-label');

    function update() {
      var result = getPasswordStrength(inputEl.value);
      fillEl.className = 'password-strength-fill' + (result.level !== 'empty' ? ' ' + result.level : '');
      labelEl.className = 'password-strength-label' + (result.level !== 'empty' ? ' ' + result.level : '');
      labelEl.textContent = result.label;
    }

    inputEl.addEventListener('input', update);
    update();
  }

  function attachPasswordStrengthMeterById(inputId) {
    attachPasswordStrengthMeter(document.getElementById(inputId));
  }

  global.getPasswordStrength = getPasswordStrength;
  global.attachPasswordStrengthMeter = attachPasswordStrengthMeter;
  global.attachPasswordStrengthMeterById = attachPasswordStrengthMeterById;
})(typeof window !== 'undefined' ? window : this);
