/**
 * Applicant auth modal — dual landing + email login + registration sub-views.
 */
(function (global) {
  'use strict';

  var cfg = {
    statusApi: '../../Api/Applicant/get_profile_status.php',
    loginApi: '../../Api/Applicant/applicant_login.php',
    registerApi: '../../Api/Applicant/register_applicant.php',
    sendOtpApi: '../../Api/Applicant/send_verification_code.php',
    checkEmailApi: '../../Api/Applicant/check_email.php',
    googleConfigApi: '../../Api/config/google_oauth_public.php',
  };

  var PASSWORD_RULE = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
  var EMAIL_RULE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var MOBILE_RULE = /^\+639\d{9}$/;

  // Client-side mirror of Api/Helpers/password_policy.php::is_common_or_predictable_password().
  // UX hint only — register_applicant.php performs the authoritative check server-side (UT-102).
  var COMMON_PASSWORD_BASES = [
    'admin', 'administrator', 'password', 'welcome', 'qwerty', 'qwertyuiop',
    'letmein', 'changeme', 'test', 'testing', 'user', 'guest', 'login',
    'default', 'onazul', 'company', 'employee', 'hr', 'root', 'iloveyou',
    'monkey', 'dragon', 'football', 'master', 'zxcvbnm', 'asdfgh'
  ];
  function isCommonOrPredictablePassword(password) {
    var lower = password.toLowerCase();
    var alnum = lower.replace(/[^a-z0-9]/g, '');
    var base = alnum.replace(/\d+$/, '');
    var normalizedBase = base
      .replace(/0/g, 'o').replace(/1/g, 'i').replace(/3/g, 'e')
      .replace(/4/g, 'a').replace(/5/g, 's').replace(/7/g, 't');

    if (COMMON_PASSWORD_BASES.indexOf(base) !== -1
      || COMMON_PASSWORD_BASES.indexOf(normalizedBase) !== -1
      || COMMON_PASSWORD_BASES.indexOf(alnum) !== -1) {
      return true;
    }
    if (/0123456789|1234567890|12345678|23456789|87654321|98765432/.test(alnum)) return true;
    if (/qwertyui|asdfghjk|zxcvbnm|qazwsx/.test(alnum)) return true;
    if (/(.)\1{4,}/.test(alnum)) return true;
    return false;
  }

  var state = {
    loggedIn: false,
    profile: null,
    pendingCallback: null,
    googleClientId: '',
    googleRedirectUri: '',
    registerCooldown: 0,
    registerCooldownTimer: null,
    registerCodeSent: false,
    emailExists: false,
  };

  var els = {};

  function $(id) {
    return id ? document.getElementById(id) : null;
  }

  function esc(text) {
    if (text == null) return '';
    var d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }

  function cacheElements() {
    els.overlay = $('authOverlay');
    els.toast = $('signinToast');
    els.title = $('authTitle');
    els.landing = $('auth-landing-view');
    els.email = $('auth-email-login-view');
    els.register = $('auth-register-view');
    els.footerLinks = $('authFooterLinks');
    els.btnOpen = $('btnOpenSignIn');
    els.btnGoogle = $('btnContinueGoogle');
    els.btnEmail = $('btnContinueEmail');
    els.btnLogin = $('btnEmailPasswordLogin');
    els.btnRegister = $('btnModalRegister');
    els.btnSendRegCode = $('btnModalSendRegCode');
    els.linkRegister = $('linkRegisterFromModal');
    els.linkWhy = $('linkWhySignIn');
    els.backEmail = $('btnBackFromEmailLogin');
    els.backRegister = $('btnBackFromRegister');
    els.loginEmail = $('authLoginEmail');
    els.loginPassword = $('authLoginPassword');
    els.loginForm = $('authEmailLoginForm');
    els.registerForm = $('authRegisterForm');
    els.regFirstName = $('authRegFirstName');
    els.regMiddleName = $('authRegMiddleName');
    els.regLastName = $('authRegLastName');
    els.regHasSuffix = $('authRegHasSuffix');
    els.regSuffixWrap = $('authRegSuffixWrap');
    els.regSuffixSelect = $('authRegSuffixSelect');
    els.regEmail = $('authRegEmail');
    els.regOtp = $('authRegOtp');
    els.regMobile = $('authRegMobile');
    els.regPassword = $('authRegPassword');
    els.regConfirm = $('authRegConfirm');
    els.regErrors = {
      first_name: $('authRegFirstNameError'),
      middle_name: $('authRegMiddleNameError'),
      last_name: $('authRegLastNameError'),
      email: $('authRegEmailError'),
      verification_code: $('authRegOtpError'),
      mobile_number: $('authRegMobileError'),
      password: $('authRegPasswordError'),
      confirm_password: $('authRegConfirmError'),
    };
    els.regInputs = {
      first_name: els.regFirstName,
      middle_name: els.regMiddleName,
      last_name: els.regLastName,
      email: els.regEmail,
      verification_code: els.regOtp,
      mobile_number: els.regMobile,
      password: els.regPassword,
      confirm_password: els.regConfirm,
    };
  }

  function clearRegisterFieldErrors() {
    Object.keys(els.regErrors || {}).forEach(function (key) {
      var err = els.regErrors[key];
      var input = els.regInputs[key];
      if (err) {
        err.textContent = '';
        err.classList.remove('is-visible');
      }
      if (input) input.classList.remove('is-invalid');
    });
  }

  function setRegisterFieldError(field, message) {
    var err = els.regErrors && els.regErrors[field];
    var input = els.regInputs && els.regInputs[field];
    if (err) {
      err.textContent = message || '';
      err.classList.toggle('is-visible', !!message);
    }
    if (input) input.classList.toggle('is-invalid', !!message);
  }

  function checkEmailUniqueness(email) {
    if (!email || !EMAIL_RULE.test(email)) {
      state.emailExists = false;
      return Promise.resolve(false);
    }
    return fetch(cfg.checkEmailApi, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: email }),
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (res) {
        if (res && res.success && res.exists) {
          state.emailExists = true;
          setRegisterFieldError('email', 'This Gmail address is already registered. Please use another Gmail.');
          if (els.btnSendRegCode) els.btnSendRegCode.disabled = true;
          return true;
        }
        state.emailExists = false;
        if (els.btnSendRegCode && state.registerCooldown <= 0) {
          els.btnSendRegCode.disabled = false;
        }
        return false;
      })
      .catch(function () {
        return false;
      });
  }

  function handleMobileInput() {
    if (!els.regMobile) return;
    var val = els.regMobile.value;
    if (!val.startsWith('+63')) {
      val = '+63' + val.replace(/^\+?6?3?/, '');
    }
    var prefix = '+63';
    var rest = val.slice(3).replace(/\D/g, '').slice(0, 10);
    els.regMobile.value = prefix + rest;

    if (rest.length > 0 && rest[0] !== '9') {
      setRegisterFieldError('mobile_number', 'Mobile number must start with +639.');
    } else if (rest.length > 0 && rest.length < 10) {
      setRegisterFieldError('mobile_number', 'Format: +639XXXXXXXXX (10 digits required after +63).');
    } else if (rest.length === 10 && !MOBILE_RULE.test(els.regMobile.value)) {
      setRegisterFieldError('mobile_number', 'Invalid mobile number format.');
    } else {
      setRegisterFieldError('mobile_number', '');
    }
  }

  function validateRegisterForm() {
    clearRegisterFieldErrors();
    var valid = true;
    var firstName = (els.regFirstName && els.regFirstName.value || '').trim();
    var lastName = (els.regLastName && els.regLastName.value || '').trim();
    var email = (els.regEmail && els.regEmail.value || '').trim();
    var otp = (els.regOtp && els.regOtp.value || '').trim();
    var mobile = (els.regMobile && els.regMobile.value || '').trim();
    var password = els.regPassword ? els.regPassword.value : '';
    var confirm = els.regConfirm ? els.regConfirm.value : '';

    if (!firstName) {
      setRegisterFieldError('first_name', 'First name is required.');
      valid = false;
    }
    if (!lastName) {
      setRegisterFieldError('last_name', 'Last name is required.');
      valid = false;
    }

    if (!email) {
      setRegisterFieldError('email', 'Email address is required.');
      valid = false;
    } else if (!EMAIL_RULE.test(email)) {
      setRegisterFieldError('email', 'Please enter a valid email address.');
      valid = false;
    } else if (state.emailExists) {
      setRegisterFieldError('email', 'This Gmail address is already registered. Please use another Gmail.');
      valid = false;
    }

    if (!otp) {
      setRegisterFieldError('verification_code', 'Enter the 4-digit verification code.');
      valid = false;
    } else if (!/^\d{4}$/.test(otp)) {
      setRegisterFieldError('verification_code', 'Verification code must be exactly 4 digits.');
      valid = false;
    } else if (els.regOtp && els.regOtp.disabled) {
      setRegisterFieldError('verification_code', 'Click "Send code" first to receive your verification code.');
      valid = false;
    }

    if (!mobile) {
      setRegisterFieldError('mobile_number', 'Mobile number is required.');
      valid = false;
    } else if (!MOBILE_RULE.test(mobile)) {
      setRegisterFieldError('mobile_number', 'Mobile number must be in format +639XXXXXXXXX.');
      valid = false;
    }

    if (!password) {
      setRegisterFieldError('password', 'Password is required.');
      valid = false;
    } else if (!PASSWORD_RULE.test(password)) {
      setRegisterFieldError('password', 'Use uppercase, lowercase, number, and special character (min. 8 chars).');
      valid = false;
    } else if (isCommonOrPredictablePassword(password)) {
      setRegisterFieldError('password', 'Password is too common or predictable. Please choose a stronger password.');
      valid = false;
    }

    if (!confirm) {
      setRegisterFieldError('confirm_password', 'Please confirm your password.');
      valid = false;
    } else if (password !== confirm) {
      setRegisterFieldError('confirm_password', 'Passwords do not match.');
      valid = false;
    }

    return valid;
  }

  function showToast(msg, type) {
    if (!els.toast) return;
    var t = type || 'danger';
    els.toast.classList.remove('alert-danger', 'alert-success', 'alert-info', 'alert-warning', 'd-none');
    els.toast.classList.add('alert-' + t);
    els.toast.textContent = msg || '';
    window.clearTimeout(showToast._t);
    showToast._t = window.setTimeout(function () {
      els.toast.classList.add('d-none');
    }, 5500);
  }

  function setTitle(text) {
    if (els.title) els.title.textContent = text || 'Sign in to find jobs matched to you';
  }

  function showView(view) {
    var views = {
      landing: els.landing,
      email: els.email,
      register: els.register,
    };
    Object.keys(views).forEach(function (key) {
      var node = views[key];
      if (!node) return;
      node.classList.toggle('is-active', key === view);
      node.classList.toggle('d-none', key !== view);
    });
    if (els.footerLinks) {
      els.footerLinks.classList.toggle('d-none', view !== 'landing');
    }
    if (view === 'register') {
      if (els.regMobile && !els.regMobile.value) {
        els.regMobile.value = '+63';
      }
    }
    if (view === 'landing') {
      setTitle('Sign in to find jobs matched to you');
    } else if (view === 'email') {
      setTitle('Sign in with email');
    } else if (view === 'register') {
      setTitle('Create your applicant account');
    }
  }

  function open(view) {
    if (!els.overlay) return;
    if (els.overlay.parentElement !== document.body) {
      document.body.appendChild(els.overlay);
    }
    showView(view || 'landing');
    els.overlay.classList.add('show');
    els.overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('auth-modal-open');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    if (!els.overlay) return;
    els.overlay.classList.remove('show');
    els.overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('auth-modal-open');
    document.body.style.overflow = '';
    showView('landing');
  }

  function isLoggedIn() {
    return !!state.loggedIn;
  }

  function requireAuth(callback, view) {
    if (state.loggedIn) {
      if (typeof callback === 'function') callback(state.profile);
      return true;
    }
    state.pendingCallback = typeof callback === 'function' ? callback : null;
    open(view || 'landing');
    return false;
  }

  function dispatchAuthSuccess(data) {
    state.loggedIn = true;
    state.profile = data || { logged_in: true };
    document.dispatchEvent(new CustomEvent('onazul-auth-success', { detail: state.profile }));
    if (state.pendingCallback) {
      var cb = state.pendingCallback;
      state.pendingCallback = null;
      try { cb(state.profile); } catch (e) { console.error(e); }
    }
  }

  function refreshSession() {
    return fetch(cfg.statusApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        state.loggedIn = !!(data && data.success && data.logged_in);
        state.profile = data;
        return data;
      })
      .catch(function () {
        state.loggedIn = false;
        state.profile = null;
        return null;
      });
  }

  function handleAuthPayload(data) {
    if (!data || !data.success) {
      showToast((data && data.message) || 'Authentication failed.', 'danger');
      return;
    }
    showToast(data.message || 'Signed in successfully.', 'success');
    close();
    dispatchAuthSuccess(data);
  }

  function submitEmailLogin(e) {
    if (e) e.preventDefault();
    var email = (els.loginEmail && els.loginEmail.value || '').trim();
    var password = els.loginPassword ? els.loginPassword.value : '';
    if (!email || !password) {
      showToast('Email and password are required.', 'warning');
      return;
    }
    if (els.btnLogin) {
      els.btnLogin.disabled = true;
      els.btnLogin.textContent = 'Signing in…';
    }
    fetch(cfg.loginApi, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ email: email, password: password }),
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (!res.ok || !res.data || !res.data.success) {
          showToast((res.data && res.data.message) || 'Sign in failed.', 'danger');
          return;
        }
        handleAuthPayload(res.data);
      })
      .catch(function () {
        showToast('Sign in request failed. Please try again.', 'danger');
      })
      .finally(function () {
        if (els.btnLogin) {
          els.btnLogin.disabled = false;
          els.btnLogin.textContent = 'Sign in';
        }
      });
  }

  function startRegisterCooldown(resendLabel) {
    if (!els.btnSendRegCode) return;
    state.registerCooldown = 60;
    els.btnSendRegCode.disabled = true;
    els.btnSendRegCode.textContent = 'Resend in ' + state.registerCooldown + 's';
    if (state.registerCooldownTimer) clearInterval(state.registerCooldownTimer);
    state.registerCooldownTimer = setInterval(function () {
      state.registerCooldown -= 1;
      if (state.registerCooldown <= 0) {
        clearInterval(state.registerCooldownTimer);
        state.registerCooldownTimer = null;
        if (!state.emailExists) {
          els.btnSendRegCode.disabled = false;
        }
        els.btnSendRegCode.textContent = resendLabel || 'Resend';
        return;
      }
      els.btnSendRegCode.textContent = 'Resend in ' + state.registerCooldown + 's';
    }, 1000);
  }

  function sendRegisterCode() {
    clearRegisterFieldErrors();
    var email = (els.regEmail && els.regEmail.value || '').trim();
    if (!email) {
      setRegisterFieldError('email', 'Enter your email address first.');
      if (els.regEmail) els.regEmail.focus();
      return;
    }
    if (!EMAIL_RULE.test(email)) {
      setRegisterFieldError('email', 'Please enter a valid email address.');
      if (els.regEmail) els.regEmail.focus();
      return;
    }
    if (state.emailExists) {
      setRegisterFieldError('email', 'This Gmail address is already registered. Please use another Gmail.');
      return;
    }

    if (els.btnSendRegCode) {
      els.btnSendRegCode.disabled = true;
      els.btnSendRegCode.textContent = 'Sending…';
    }

    checkEmailUniqueness(email).then(function (exists) {
      if (exists) {
        if (els.btnSendRegCode) els.btnSendRegCode.disabled = true;
        return;
      }

      fetch(cfg.sendOtpApi, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email: email }),
      })
        .then(function (r) {
          return r.text().then(function (text) {
            var data = null;
            try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
            return { ok: r.ok, data: data };
          });
        })
        .then(function (res) {
          var data = res.data || {};
          if (!res.ok || !data.success) {
            setRegisterFieldError('email', (data && data.message) || 'Could not send verification code.');
            showToast((data && data.message) || 'Could not send verification code.', 'danger');
            if (els.btnSendRegCode) {
              els.btnSendRegCode.disabled = false;
              els.btnSendRegCode.textContent = state.registerCodeSent ? 'Resend' : 'Send code';
            }
            return;
          }
          state.registerCodeSent = true;
          if (els.regOtp) {
            els.regOtp.disabled = false;
            els.regOtp.value = '';
            els.regOtp.focus();
          }
          var toastMsg = data.message || 'Verification code sent.';
          if (data.dev_code) {
            toastMsg += ' (Dev code: ' + data.dev_code + ')';
          }
          showToast(toastMsg, 'success');
          startRegisterCooldown('Resend');
        })
        .catch(function () {
          setRegisterFieldError('email', 'Request failed. Please try again.');
          showToast('Request failed. Please try again.', 'danger');
          if (els.btnSendRegCode) {
            els.btnSendRegCode.disabled = false;
            els.btnSendRegCode.textContent = state.registerCodeSent ? 'Resend' : 'Send code';
          }
        });
    });
  }

  function submitRegister(e) {
    if (e) e.preventDefault();
    if (!els.registerForm) return;
    if (!validateRegisterForm()) return;

    if (els.btnRegister) {
      els.btnRegister.disabled = true;
      els.btnRegister.textContent = 'Creating account…';
    }

    var hasSuffix = els.regHasSuffix ? els.regHasSuffix.checked : false;
    var suffixVal = (hasSuffix && els.regSuffixSelect) ? els.regSuffixSelect.value : '';

    var payload = {
      first_name: (els.regFirstName && els.regFirstName.value || '').trim(),
      middle_name: (els.regMiddleName && els.regMiddleName.value || '').trim(),
      last_name: (els.regLastName && els.regLastName.value || '').trim(),
      has_suffix: hasSuffix ? 1 : 0,
      suffix: suffixVal,
      email: (els.regEmail && els.regEmail.value || '').trim(),
      verification_code: (els.regOtp && els.regOtp.value || '').trim(),
      mobile_number: (els.regMobile && els.regMobile.value || '').trim(),
      password: els.regPassword ? els.regPassword.value : '',
      confirm_password: els.regConfirm ? els.regConfirm.value : '',
    };

    fetch(cfg.registerApi, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    })
      .then(function (r) {
        return r.text().then(function (text) {
          var data = null;
          try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        var data = res.data || {};
        if (!res.ok || !data.success) {
          if (data.field_errors && typeof data.field_errors === 'object') {
            Object.keys(data.field_errors).forEach(function (field) {
              setRegisterFieldError(field, data.field_errors[field]);
            });
          }
          showToast((data && data.message) || 'Registration failed.', 'danger');
          return;
        }
        handleAuthPayload(data);
      })
      .catch(function () {
        showToast('Registration request failed.', 'danger');
      })
      .finally(function () {
        if (els.btnRegister) {
          els.btnRegister.disabled = false;
          els.btnRegister.textContent = 'Create account';
        }
      });
  }

  function loadGoogleConfig() {
    fetch(cfg.googleConfigApi, { credentials: 'include' })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (d && d.success && d.client_id) {
          state.googleClientId = d.client_id;
          global.GOOGLE_CLIENT_ID = d.client_id;
          if (d.redirect_uri) state.googleRedirectUri = d.redirect_uri;
        }
      })
      .catch(function () {});
  }

  var googleOAuthPopup = null;
  var googleOAuthDone = false;
  var googleOAuthPopupReloadScheduled = false;

  function openGoogleOAuth() {
    if (!state.googleClientId) {
      showToast('Google sign-in is not configured yet.', 'danger');
      return;
    }
    // Must be the deterministic, server-computed URI (Api/config/google_oauth.php),
    // NOT the raw current-page URL — the latter can drift from what's registered in
    // Google Cloud Console (e.g. folder index requests, trailing slash, stray query
    // params) and causes Error 400: redirect_uri_mismatch. This also has to match
    // exactly what Html/Applicant/applicant_google_callback.html sends back during
    // the server-side token exchange (it uses the same fixed landingUrl()).
    var redirectUri = state.googleRedirectUri || window.location.href.replace(/[?#].*$/, '');
    var oauthState = String(Date.now()) + '_' + Math.random().toString(16).slice(2);
    try { window.sessionStorage.setItem('google_oauth_state', oauthState); } catch (err) {}
    var url = 'https://accounts.google.com/o/oauth2/v2/auth' +
      '?client_id=' + encodeURIComponent(state.googleClientId) +
      '&redirect_uri=' + encodeURIComponent(redirectUri) +
      '&response_type=code' +
      '&scope=' + encodeURIComponent('openid email profile') +
      '&prompt=' + encodeURIComponent('select_account') +
      '&state=' + encodeURIComponent(oauthState);
    googleOAuthPopupReloadScheduled = false;
    googleOAuthDone = false;
    var popup = window.open(url, 'google_oauth', 'width=600,height=720');
    googleOAuthPopup = popup || null;
    if (!popup || popup.closed) {
      showToast('Pop-up blocked. Allow pop-ups, then try again.', 'warning');
      return;
    }
    try { popup.focus(); } catch (e) {}
  }

  function bindEvents() {
    if (els.btnOpen) {
      els.btnOpen.addEventListener('click', function (e) {
        e.preventDefault();
        open('landing');
      });
    }
    if (els.overlay) {
      els.overlay.addEventListener('click', function (e) {
        if (e.target === els.overlay) close();
      });
    }
    window.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && els.overlay && els.overlay.classList.contains('show')) close();
    });

    if (els.btnGoogle) {
      els.btnGoogle.addEventListener('click', function (e) {
        e.preventDefault();
        openGoogleOAuth();
      });
    }
    if (els.btnEmail) {
      els.btnEmail.addEventListener('click', function (e) {
        e.preventDefault();
        showView('email');
        if (els.loginEmail) els.loginEmail.focus();
      });
    }
    if (els.linkRegister) {
      els.linkRegister.addEventListener('click', function (e) {
        e.preventDefault();
        clearRegisterFieldErrors();
        open('register');
      });
    }
    if (els.linkWhy) {
      els.linkWhy.addEventListener('click', function (e) {
        e.preventDefault();
        showToast('Sign in to apply, save roles, and get job matches tailored to your profile.', 'info');
      });
    }
    if (els.backEmail) {
      els.backEmail.addEventListener('click', function (e) {
        e.preventDefault();
        showView('landing');
      });
    }
    if (els.backRegister) {
      els.backRegister.addEventListener('click', function (e) {
        e.preventDefault();
        showView('landing');
      });
    }
    if (els.loginForm) els.loginForm.addEventListener('submit', submitEmailLogin);
    if (els.registerForm) els.registerForm.addEventListener('submit', submitRegister);
    if (els.btnSendRegCode) els.btnSendRegCode.addEventListener('click', sendRegisterCode);

    if (els.regHasSuffix && els.regSuffixWrap) {
      els.regHasSuffix.addEventListener('change', function () {
        var checked = this.checked;
        els.regSuffixWrap.classList.toggle('d-none', !checked);
        if (!checked && els.regSuffixSelect) {
          els.regSuffixSelect.value = '';
        }
      });
    }

    if (els.regEmail) {
      els.regEmail.addEventListener('input', function () {
        setRegisterFieldError('email', '');
      });
      els.regEmail.addEventListener('blur', function () {
        var email = (els.regEmail.value || '').trim();
        if (email) {
          checkEmailUniqueness(email);
        }
      });
    }

    if (els.regMobile) {
      els.regMobile.addEventListener('input', handleMobileInput);
      els.regMobile.addEventListener('focus', function () {
        if (!this.value) {
          this.value = '+63';
        }
      });
    }

    if (els.regOtp) {
      els.regOtp.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
        setRegisterFieldError('verification_code', '');
      });
    }

    window.addEventListener('message', function (ev) {
      if (ev.origin !== window.location.origin) return;
      var d = ev.data;
      if (!d || !d.type) return;
      if (d.type === 'onazul_google_oauth' && d.redirect) {
        googleOAuthDone = true;
        close();
        window.location.href = d.redirect;
      }
    });

    window.setInterval(function () {
      if (googleOAuthPopup && googleOAuthPopup.closed) {
        if (googleOAuthDone) {
          googleOAuthPopup = null;
          return;
        }
        if (!googleOAuthPopupReloadScheduled) {
          googleOAuthPopupReloadScheduled = true;
          refreshSession().then(function (data) {
            if (data && data.logged_in) {
              googleOAuthDone = true;
              dispatchAuthSuccess(data);
            } else {
              try { window.location.reload(); } catch (e) {}
            }
          });
        }
        googleOAuthPopup = null;
      }
    }, 600);
  }

  function init(options) {
    if (options) {
      Object.keys(options).forEach(function (k) {
        if (options[k] !== undefined) cfg[k] = options[k];
      });
    }
    cacheElements();
    if (els.overlay && els.overlay.parentElement !== document.body) {
      document.body.appendChild(els.overlay);
    }
    bindEvents();
    loadGoogleConfig();
    return refreshSession();
  }

  global.OnazulAuth = {
    init: init,
    open: open,
    close: close,
    showView: showView,
    requireAuth: requireAuth,
    isLoggedIn: isLoggedIn,
    refreshSession: refreshSession,
  };
})(window);