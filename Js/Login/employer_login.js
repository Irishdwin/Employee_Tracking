document.addEventListener('DOMContentLoaded', () => {
    const FP_API = 'Api/Login/forgot_password.php';
  
    // --- 0. Check and Display Error Messages from Query Parameters ---
    const params = new URLSearchParams(window.location.search);
    const error = params.get('error');
    if (error) {
        const msg = decodeURIComponent(error);
        const modalEl = document.getElementById('loginErrorModal');
        const modalMsg = document.getElementById('loginErrorModalMessage');
        if (modalMsg) modalMsg.textContent = msg || 'Incorrect email / username or password.';
  
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            const m = new bootstrap.Modal(modalEl);
            m.show();
            modalEl.addEventListener('hidden.bs.modal', () => {
                const url = new URL(window.location.href);
                url.searchParams.delete('error');
                window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
            }, { once: true });
        } else {
            const errorAlert = document.getElementById('errorAlert');
            if (errorAlert) {
                errorAlert.textContent = msg;
                errorAlert.classList.remove('d-none');
            }
        }
    }
  
    // --- 1. Password Visibility Toggle ---
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const passwordField = document.getElementById('passwordField');
    const toggleIcon = document.getElementById('toggleIcon');
  
    if (toggleBtn && passwordField && toggleIcon) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordField.type === 'password';
            passwordField.type = isPassword ? 'text' : 'password';
  
            if (isPassword) {
                toggleIcon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                toggleIcon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        });
    }
  
    // --- 1b. Forgot Password: New/Confirm Password Visibility Toggles ---
    const makePasswordToggle = (inputId, btnId, iconId) => {
        const input = document.getElementById(inputId);
        const btn = document.getElementById(btnId);
        const icon = document.getElementById(iconId);
        if (!input || !btn || !icon) return;
        btn.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            if (isPassword) {
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        });
    };
    makePasswordToggle('fpNewPassword', 'toggleFpNewPasswordBtn', 'toggleFpNewPasswordIcon');
    makePasswordToggle('fpConfirmPassword', 'toggleFpConfirmPasswordBtn', 'toggleFpConfirmPasswordIcon');

    // --- 2. Forgot Password Modal Workflow ---
    const forgotModalEl = document.getElementById('forgotPasswordModal');
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const forgotModal = (forgotModalEl && window.bootstrap && window.bootstrap.Modal)
        ? new bootstrap.Modal(forgotModalEl)
        : null;
    const fpEmail = document.getElementById('fpEmail');
    const fpCode = document.getElementById('fpCode');
    const fpNewPassword = document.getElementById('fpNewPassword');
    const fpConfirmPassword = document.getElementById('fpConfirmPassword');
    const fpAlert = document.getElementById('forgotPasswordAlert');
    let fpCodeVerified = false;
  
    const showFpAlert = (message, type = 'danger') => {
        if (!fpAlert) return;
        fpAlert.textContent = message;
        fpAlert.className = `alert alert-${type}`;
    };
  
    const hideFpAlert = () => {
        if (!fpAlert) return;
        fpAlert.className = 'alert d-none';
        fpAlert.textContent = '';
    };
  
    // Client-side mirror of Api/Helpers/password_policy.php::is_common_or_predictable_password().
    // This is a UX hint only — the server is the authoritative check (UT-102).
    const isCommonOrPredictablePassword = (password) => {
        const commonBases = [
            'admin', 'administrator', 'password', 'welcome', 'qwerty', 'qwertyuiop',
            'letmein', 'changeme', 'test', 'testing', 'user', 'guest', 'login',
            'default', 'onazul', 'company', 'employee', 'hr', 'root', 'iloveyou',
            'monkey', 'dragon', 'football', 'master', 'zxcvbnm', 'asdfgh'
        ];
        const lower = password.toLowerCase();
        const alnum = lower.replace(/[^a-z0-9]/g, '');
        const base = alnum.replace(/\d+$/, '');
        const normalizedBase = base
            .replace(/0/g, 'o').replace(/1/g, 'i').replace(/3/g, 'e')
            .replace(/4/g, 'a').replace(/5/g, 's').replace(/7/g, 't');
  
        if (commonBases.includes(base) || commonBases.includes(normalizedBase) || commonBases.includes(alnum)) {
            return true;
        }
        if (/0123456789|1234567890|12345678|23456789|87654321|98765432/.test(alnum)) return true;
        if (/qwertyui|asdfghjk|zxcvbnm|qazwsx/.test(alnum)) return true;
        if (/(.)\1{4,}/.test(alnum)) return true;
        return false;
    };
  
    const validateStrongPassword = (password) => {
        const checks = [
            { id: 'fpRuleLen', ok: password.length >= 8 },
            { id: 'fpRuleUpper', ok: /[A-Z]/.test(password) },
            { id: 'fpRuleLower', ok: /[a-z]/.test(password) },
            { id: 'fpRuleNumber', ok: /\d/.test(password) },
            { id: 'fpRuleSpecial', ok: /[^A-Za-z0-9]/.test(password) }
        ];
        checks.forEach((rule) => {
            const el = document.getElementById(rule.id);
            if (!el) return;
            el.classList.remove('text-muted', 'text-danger', 'text-success');
            if (!password) {
                el.classList.add('text-muted');
            } else {
                el.classList.add(rule.ok ? 'text-success' : 'text-danger');
            }
        });
        const formatOk = checks.every(c => c.ok);
        return formatOk && !isCommonOrPredictablePassword(password);
    };
  
    const resetForgotForm = () => {
        fpCodeVerified = false;
        if (fpEmail) fpEmail.value = '';
        if (fpCode) fpCode.value = '';
        if (fpNewPassword) fpNewPassword.value = '';
        if (fpConfirmPassword) fpConfirmPassword.value = '';
        validateStrongPassword('');
        hideFpAlert();
    };
  
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', (e) => {
            e.preventDefault();
            resetForgotForm();
            if (forgotModal) forgotModal.show();
        });
    }
  
    if (fpEmail) {
        fpEmail.addEventListener('input', () => { fpCodeVerified = false; });
    }
  
    if (fpNewPassword) {
        fpNewPassword.addEventListener('input', () => validateStrongPassword(fpNewPassword.value || ''));
        if (typeof attachPasswordStrengthMeter === 'function') {
            attachPasswordStrengthMeter(fpNewPassword);
        }
    }
  
    if (document.getElementById('btnSendResetCode')) {
        document.getElementById('btnSendResetCode').addEventListener('click', async () => {
            hideFpAlert();
            const email = fpEmail ? fpEmail.value.trim() : '';
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFpAlert('Please enter a valid email address.', 'warning');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'send_code');
            fd.append('email', email);
            const btn = document.getElementById('btnSendResetCode');
            if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }
            try {
                const res = await fetch(FP_API, { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json().catch(() => null);
                if (data && data.success) {
                    fpCodeVerified = false;
                    showFpAlert(data.message || 'Code sent.', 'success');
                } else {
                    showFpAlert((data && data.message) ? data.message : 'Unable to send code.', 'danger');
                }
            } catch (err) {
                showFpAlert('Unable to send code. Please try again.', 'danger');
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = 'Send code'; }
            }
        });
    }
  
    if (document.getElementById('btnVerifyResetCode')) {
        document.getElementById('btnVerifyResetCode').addEventListener('click', async () => {
            hideFpAlert();
            const email = fpEmail ? fpEmail.value.trim() : '';
            const code = fpCode ? fpCode.value.trim() : '';
            if (!email || !code) {
                showFpAlert('Please enter your email and the 6-digit code.', 'warning');
                return;
            }
            if (!/^\d{6}$/.test(code)) {
                showFpAlert('Verification code must be 6 digits.', 'warning');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'verify_code');
            fd.append('email', email);
            fd.append('code', code);
            const btn = document.getElementById('btnVerifyResetCode');
            if (btn) { btn.disabled = true; btn.textContent = 'Verifying...'; }
            try {
                const res = await fetch(FP_API, { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json().catch(() => null);
                if (data && data.success) {
                    fpCodeVerified = true;
                    showFpAlert(data.message || 'Code verified.', 'success');
                } else {
                    fpCodeVerified = false;
                    showFpAlert((data && data.message) ? data.message : 'Invalid code.', 'danger');
                }
            } catch (err) {
                showFpAlert('Verification failed. Please try again.', 'danger');
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = 'Verify'; }
            }
        });
    }
  
    if (document.getElementById('btnResetPassword')) {
        document.getElementById('btnResetPassword').addEventListener('click', async () => {
            hideFpAlert();
            const email = fpEmail ? fpEmail.value.trim() : '';
            const newPassword = fpNewPassword ? fpNewPassword.value : '';
            const confirmPassword = fpConfirmPassword ? fpConfirmPassword.value : '';
            if (!fpCodeVerified) {
                showFpAlert('Please verify the 6-digit code first.', 'warning');
                return;
            }
            if (!validateStrongPassword(newPassword)) {
                const msg = isCommonOrPredictablePassword(newPassword)
                    ? 'Password is too common or predictable. Please choose a stronger password.'
                    : 'New password does not meet the policy.';
                showFpAlert(msg, 'warning');
                return;
            }
            if (newPassword !== confirmPassword) {
                showFpAlert('Passwords do not match.', 'warning');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'reset_password');
            fd.append('email', email);
            fd.append('new_password', newPassword);
            fd.append('confirm_password', confirmPassword);
            const btn = document.getElementById('btnResetPassword');
            if (btn) { btn.disabled = true; btn.textContent = 'Resetting...'; }
            try {
                const res = await fetch(FP_API, { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json().catch(() => null);
                if (data && data.success) {
                    showFpAlert(data.message || 'Password reset successful.', 'success');
                    setTimeout(() => { if (forgotModal) forgotModal.hide(); }, 1200);
                } else {
                    showFpAlert((data && data.message) ? data.message : 'Password reset failed.', 'danger');
                }
            } catch (err) {
                showFpAlert('Password reset failed. Please try again.', 'danger');
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = 'Reset Password'; }
            }
        });
    }
  
    // --- 3. Math CAPTCHA Logic (numerator + denominator = answer, both 1-100) ---
    const refreshBtn = document.getElementById('refreshCaptchaBtn');
    const captchaNumerator = document.getElementById('captchaNumerator');
    const captchaDenominator = document.getElementById('captchaDenominator');
    const captchaInput = document.getElementById('captchaInput');
    const captchaMessage = document.getElementById('captchaMessage');
  
    const clearCaptchaState = () => {
        if (captchaInput) captchaInput.classList.remove('is-valid', 'is-invalid');
        if (captchaMessage) {
            captchaMessage.textContent = '';
            captchaMessage.classList.remove('text-success', 'text-danger', 'text-secondary');
        }
    };
  
    const captchaChallenge = document.getElementById('captchaChallengeId');
    let challengeGeneration = 0, validationGeneration = 0, validationTimer;
    let captchaLoading = false, loginPending = false, captchaRefreshTimer, captchaRefreshAt = 0;
    const syncCaptchaControls = () => {
        if (refreshBtn) refreshBtn.disabled = captchaLoading || loginPending;
        if (captchaInput) captchaInput.disabled = captchaLoading || loginPending;
        const submit = document.getElementById('btnLogin');
        if (submit) submit.disabled = captchaLoading || loginPending || !captchaChallenge || !captchaChallenge.value;
    };
    const loadCaptcha = async () => {
        const generation = ++challengeGeneration;
        clearTimeout(captchaRefreshTimer); captchaRefreshAt = 0;
        ++validationGeneration; clearTimeout(validationTimer);
        const previous = captchaChallenge ? captchaChallenge.value : '';
        if (captchaChallenge) captchaChallenge.value = '';
        clearCaptchaState();
        if (captchaInput) captchaInput.value = '';
        if (captchaNumerator) captchaNumerator.value = '';
        if (captchaDenominator) captchaDenominator.value = '';
        captchaLoading = true; syncCaptchaControls();
        try {
            const response = await fetch('Api/Login/captcha_gen.php', {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ replace_challenge_id: previous }).toString()
            });
            const data = await response.json();
            if (generation !== challengeGeneration) return;
            if (!response.ok || !/^[a-f0-9]{64}$/.test(data.challenge_id)) throw new Error('Invalid challenge');
            captchaChallenge.value = data.challenge_id;
            captchaNumerator.value = data.numerator; captchaDenominator.value = data.denominator;
            captchaRefreshAt = Date.now() + Math.max(1000, (Number(data.expires_in) || 600) * 1000 - 30000);
            captchaRefreshTimer = setTimeout(() => { if (!loginPending && !document.hidden) loadCaptcha(); }, captchaRefreshAt - Date.now());
        } catch (error) {
            if (generation !== challengeGeneration) return;
            if (captchaMessage) {
                captchaMessage.textContent = 'Unable to load security check. Please refresh and try again.';
                captchaMessage.classList.add('text-danger');
            }
        } finally {
            if (generation === challengeGeneration) { captchaLoading = false; syncCaptchaControls(); }
        }
    };
    const refreshExpiredCaptcha = async () => {
        await loadCaptcha();
        if (captchaChallenge.value && captchaMessage) {
            captchaMessage.textContent = 'Security check refreshed. Please answer the new question.';
            captchaMessage.classList.add('text-secondary');
            captchaInput.focus();
        }
    };
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !loginPending && !captchaLoading && captchaRefreshAt && Date.now() >= captchaRefreshAt) loadCaptcha();
    });
    const validateCaptcha = () => {
        const generation = ++validationGeneration;
        clearTimeout(validationTimer); clearCaptchaState();
        if (!captchaInput || !captchaChallenge || !captchaChallenge.value || loginPending) return;
        const value = captchaInput.value.trim(), challenge = captchaChallenge.value;
        if (!value) return;
        validationTimer = setTimeout(async () => {
            try {
                const response = await fetch('Api/Login/check_captcha.php', {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ answer: value, challenge_id: challenge }).toString()
                });
                const data = await response.json();
                if (generation !== validationGeneration || challenge !== captchaChallenge.value || value !== captchaInput.value.trim() || loginPending) return;
                if (!response.ok) throw new Error('Validation unavailable');
                if (data.code === 'expired') { await refreshExpiredCaptcha(); return; }
                captchaInput.classList.add(data.valid ? 'is-valid' : 'is-invalid');
                captchaMessage.textContent = data.valid ? '' : data.message;
                captchaMessage.classList.add(data.valid ? 'text-success' : 'text-danger');
            } catch (error) {
                if (generation === validationGeneration) clearCaptchaState();
            }
        }, 180);
    };

    if (refreshBtn) refreshBtn.addEventListener('click', loadCaptcha);
    if (captchaInput) captchaInput.addEventListener('input', validateCaptcha);
  
    // Initial CAPTCHA Load
    loadCaptcha();
    window.addEventListener('pageshow', (event) => { if (event.persisted) loadCaptcha(); });
  
    function showLoginCooldown(seconds) {
        const until=Date.now()+Number(seconds)*1000;
        let el=document.getElementById('loginCooldown');
        if(!el){
            el=document.createElement('div');el.id='loginCooldown';el.className='alert alert-warning py-2 px-3 mt-2 mb-2 small';el.setAttribute('role','status');
            document.getElementById('btnLogin').before(el);
        }
        let modalTimer=document.getElementById('loginModalCooldown');
        if(!modalTimer){modalTimer=document.createElement('div');modalTimer.id='loginModalCooldown';modalTimer.className='alert alert-warning py-2 px-3 mt-2 mb-0 small';document.querySelector('#loginErrorModal .modal-body').appendChild(modalTimer);}
        clearInterval(window.onazulLoginCooldown);
        const update=()=>{
            const left=Math.max(0,Math.ceil((until-Date.now())/1000));
            const remaining=String(Math.floor(left/60)).padStart(2,'0')+':'+String(left%60).padStart(2,'0');
            const content='<div class="d-flex align-items-center justify-content-between gap-2"><span>'+(left?'Try again in':'Cooldown finished')+'</span><span class="fw-bold" style="font-variant-numeric:tabular-nums">'+remaining+'</span></div>';
            el.innerHTML=content;modalTimer.innerHTML=content;
            if(!left)clearInterval(window.onazulLoginCooldown);
        };
        update();window.onazulLoginCooldown=setInterval(update,1000);
    }
    if(new URLSearchParams(location.search).get('forgot')==='1' && document.getElementById('forgotPasswordLink'))document.getElementById('forgotPasswordLink').click();
    // --- 4. AJAX Login Form Submission ---
    const loginForm = document.getElementById('loginForm');
    const btnLogin = document.getElementById('btnLogin');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
  
            if (loginPending || captchaLoading || !captchaChallenge || !captchaChallenge.value) return;
            if (captchaRefreshAt && Date.now() >= captchaRefreshAt) { await refreshExpiredCaptcha(); return; }
            const captchaVal = (captchaInput && captchaInput.value) ? captchaInput.value.trim() : '';
            if (!captchaVal) {
                if (captchaMessage) {
                    captchaMessage.textContent = 'Please fill the security check.';
                    captchaMessage.classList.add('text-danger');
                }
                return;
            }
  
            const fd = new FormData(loginForm);
            loginPending = true; ++validationGeneration; clearTimeout(validationTimer); syncCaptchaControls();
            if (btnLogin) {
                btnLogin.disabled = true;
                btnLogin.textContent = 'LOGGING IN...';
            }
  
            try {
                const r = await fetch(loginForm.action, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
  
                const data = await r.json().catch(() => null);
                if (data && data.success && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
  
                if (data && data.code === 'captcha_expired') { await refreshExpiredCaptcha(); return; }
                if(data && data.retry_after) showLoginCooldown(data.retry_after);
                const msg = (data && data.message) ? data.message : 'Incorrect email / username or password.';
                const modalEl = document.getElementById('loginErrorModal');
                const modalMsg = document.getElementById('loginErrorModalMessage');
                if (modalMsg) modalMsg.textContent = msg;
                if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                    new bootstrap.Modal(modalEl).show();
                } else {
                    const errorAlert = document.getElementById('errorAlert');
                    if (errorAlert) {
                        errorAlert.textContent = msg;
                        errorAlert.classList.remove('d-none');
                    }
                }
                // ST-102: wipe the password field clean after a failed attempt.
                if (passwordField) passwordField.value = '';
                loadCaptcha();
            } catch (err) {
                const modalEl = document.getElementById('loginErrorModal');
                const modalMsg = document.getElementById('loginErrorModalMessage');
                if (modalMsg) modalMsg.textContent = 'Login failed. Please try again.';
                if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                    new bootstrap.Modal(modalEl).show();
                }
                loadCaptcha();
            } finally {
                loginPending = false; syncCaptchaControls();
                if (btnLogin) {
                    btnLogin.textContent = 'LOGIN TO DASHBOARD';
                }
            }
        });
    }
  });
