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
      return checks.every(c => c.ok);
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
              showFpAlert('Please enter your email and the 4-digit code.', 'warning');
              return;
          }
          if (!/^\d{4}$/.test(code)) {
              showFpAlert('Verification code must be 4 digits.', 'warning');
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
              showFpAlert('Please verify the 4-digit code first.', 'warning');
              return;
          }
          if (!validateStrongPassword(newPassword)) {
              showFpAlert('New password does not meet the policy.', 'warning');
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

  // --- 3. Math CAPTCHA Logic ---
  const refreshBtn = document.getElementById('refreshCaptchaBtn');
  const captchaNum1 = document.getElementById('captchaNum1');
  const captchaNum2 = document.getElementById('captchaNum2');
  const captchaInput = document.getElementById('captchaInput');
  const captchaMessage = document.getElementById('captchaMessage');

  const clearCaptchaState = () => {
      if (captchaInput) captchaInput.classList.remove('is-valid', 'is-invalid');
      if (captchaMessage) {
          captchaMessage.textContent = '';
          captchaMessage.classList.remove('text-success', 'text-danger');
      }
  };

  const loadCaptcha = () => {
      clearCaptchaState();
      if (captchaInput) captchaInput.value = '';

      fetch(`Api/Login/captcha_gen.php?ts=${Date.now()}`)
          .then(response => response.json())
          .then(data => {
              if (captchaNum1) captchaNum1.value = data.a;
              if (captchaNum2) captchaNum2.value = data.b;
          })
          .catch(() => {
              if (captchaMessage) {
                  captchaMessage.textContent = 'Unable to load security check.';
                  captchaMessage.classList.add('text-danger');
              }
          });
  };

  const validateCaptcha = () => {
      if (!captchaInput) return;
      const value = captchaInput.value.trim();
      if (!value) {
          clearCaptchaState();
          return;
      }

      const params = new URLSearchParams();
      params.append('answer', value);

      fetch('Api/Login/check_captcha.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: params.toString()
      })
          .then(response => response.json())
          .then(data => {
              captchaInput.classList.remove('is-valid', 'is-invalid');
              captchaMessage.classList.remove('text-success', 'text-danger');

              if (data.valid) {
                  captchaInput.classList.add('is-valid');
                  captchaMessage.textContent = '';
                  captchaMessage.classList.add('text-success');
              } else {
                  captchaInput.classList.add('is-invalid');
                  captchaMessage.textContent = 'Please fill correct value';
                  captchaMessage.classList.add('text-danger');
              }
          })
          .catch(() => {
              clearCaptchaState();
          });
  };

  if (refreshBtn) refreshBtn.addEventListener('click', loadCaptcha);
  if (captchaInput) captchaInput.addEventListener('input', validateCaptcha);

  // Initial CAPTCHA Load
  loadCaptcha();

  // --- 4. AJAX Login Form Submission ---
  const loginForm = document.getElementById('loginForm');
  const btnLogin = document.getElementById('btnLogin');
  if (loginForm) {
      loginForm.addEventListener('submit', async (e) => {
          e.preventDefault();

          const captchaVal = (captchaInput && captchaInput.value) ? captchaInput.value.trim() : '';
          if (!captchaVal) {
              if (captchaMessage) {
                  captchaMessage.textContent = 'Please fill the security check.';
                  captchaMessage.classList.add('text-danger');
              }
              return;
          }

          const fd = new FormData(loginForm);
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
              if (btnLogin) {
                  btnLogin.disabled = false;
                  btnLogin.textContent = 'LOGIN TO DASHBOARD';
              }
          }
      });
  }
});