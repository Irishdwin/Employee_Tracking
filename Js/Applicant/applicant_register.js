/**
 * Applicant Registration – OTP verification & form submit
 * Paths relative to Html/Applicant/
 */
(function () {
    'use strict';

    const API_OTP = '../../Api/Applicant/send_otp.php';
    const API_REGISTER = '../../Api/Applicant/applicant_register.php';
    const COOLDOWN_SECS = 60;

    let cooldownTimer = null;
    let cooldownRemaining = 0;

    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const alertDiv = document.getElementById('registerAlert');

        if (urlParams.has('success')) {
            alertDiv.className = 'alert alert-success';
            alertDiv.textContent = decodeURIComponent(urlParams.get('success'));
            alertDiv.classList.remove('d-none');
            setTimeout(function () {
                window.location.href = '../../index.html';
            }, 3000);
        } else if (urlParams.has('error')) {
            alertDiv.className = 'alert alert-danger';
            alertDiv.textContent = decodeURIComponent(urlParams.get('error'));
            alertDiv.classList.remove('d-none');
        }

        const sendCodeBtn = document.getElementById('sendCodeBtn');
        const emailField = document.getElementById('emailField');
        const otpCodeField = document.getElementById('otpCodeField');
        const registerForm = document.getElementById('registerForm');

        if (sendCodeBtn) sendCodeBtn.addEventListener('click', handleSendCode);
        if (otpCodeField) otpCodeField.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
        if (registerForm) registerForm.addEventListener('submit', handleSubmit);
    });

    function handleSendCode() {
        const emailField = document.getElementById('emailField');
        const sendCodeBtn = document.getElementById('sendCodeBtn');
        const otpCodeField = document.getElementById('otpCodeField');
        const email = (emailField && emailField.value || '').trim();

        if (!email) {
            Swal.fire({ icon: 'warning', title: 'Email Required', text: 'Please enter your email address first.' });
            if (emailField) emailField.focus();
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            Swal.fire({ icon: 'warning', title: 'Invalid Email', text: 'Please enter a valid email address.' });
            if (emailField) emailField.focus();
            return;
        }

        sendCodeBtn.disabled = true;
        sendCodeBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

        fetch(API_OTP, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email }),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    otpCodeField.disabled = false;
                    otpCodeField.setAttribute('required', 'required');
                    otpCodeField.focus();
                    Swal.fire({ icon: 'success', title: 'Code Sent', text: data.message || 'A 4-digit code has been sent to your email.' });
                    startCooldown();
                } else {
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.innerHTML = '<i class="bi bi-envelope-check me-1"></i> Send Code';
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to send code.' });
                }
            })
            .catch(function () {
                sendCodeBtn.disabled = false;
                sendCodeBtn.innerHTML = '<i class="bi bi-envelope-check me-1"></i> Send Code';
                Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed. Please try again.' });
            });
    }

    function startCooldown() {
        const sendCodeBtn = document.getElementById('sendCodeBtn');
        if (cooldownTimer) clearInterval(cooldownTimer);
        cooldownRemaining = COOLDOWN_SECS;

        cooldownTimer = setInterval(function () {
            cooldownRemaining--;
            sendCodeBtn.textContent = 'Resend in ' + cooldownRemaining + 's';
            if (cooldownRemaining <= 0) {
                clearInterval(cooldownTimer);
                cooldownTimer = null;
                sendCodeBtn.disabled = false;
                sendCodeBtn.innerHTML = '<i class="bi bi-envelope-check me-1"></i> Send Code';
            }
        }, 1000);
    }

    // ST-104: generic inline "This field is required." check.
    function validateRequiredField(id, errorId) {
        const input = document.getElementById(id);
        const errorEl = document.getElementById(errorId);
        const isEmpty = !input || String(input.value || '').trim() === '';
        if (errorEl) errorEl.classList.toggle('d-none', !isEmpty);
        if (input) input.classList.toggle('is-invalid', isEmpty);
        return !isEmpty;
    }

    function handleSubmit(e) {
        e.preventDefault();
        const form = document.getElementById('registerForm');
        const alertDiv = document.getElementById('registerAlert');
        if (!form) return;

        const requiredOk = [
            validateRequiredField('regFullName', 'regFullNameError'),
            validateRequiredField('emailField', 'emailFieldError'),
            validateRequiredField('otpCodeField', 'otpCodeFieldError'),
            validateRequiredField('regMobile', 'regMobileError'),
            validateRequiredField('regPassword', 'regPasswordError'),
            validateRequiredField('regConfirmPassword', 'regConfirmPasswordError'),
        ].every(Boolean);
        if (!requiredOk) {
            alertDiv.className = 'alert alert-danger';
            alertDiv.textContent = 'Please fill out all required fields.';
            alertDiv.classList.remove('d-none');
            return;
        }

        const fd = new FormData(form);
        alertDiv.classList.add('d-none');

        fetch(API_REGISTER, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { status: r.status, data: data };
                });
            })
            .then(function (result) {
                const status = result.status;
                const data = result.data;
                // IT-101: a newly created applicant account is signalled with
                // HTTP 201 Created (Api/Applicant/register_applicant.php); the
                // "already logged in" short-circuit still returns HTTP 200.
                // Both are success cases and should redirect to login.
                if (data.success && (status === 201 || status === 200)) {
                    Swal.fire({ icon: 'success', title: 'Account Created', text: data.message || 'You can now log in.' })
                        .then(function () {
                            window.location.href = '../../index.html';
                        });
                } else {
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = data.message || data.error || 'Registration failed.';
                    alertDiv.classList.remove('d-none');
                }
            })
            .catch(function () {
                alertDiv.className = 'alert alert-danger';
                alertDiv.textContent = 'Request failed. Please try again.';
                alertDiv.classList.remove('d-none');
            });
    }
})();