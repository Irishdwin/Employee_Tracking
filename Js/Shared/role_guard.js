// Role guard: redirect if current session role does not match this page's required role.
// Set window.REQUIRED_ROLE or window.REQUIRED_ROLES before loading this script.
// All URLs are resolved relative to the current document (works in subfolders and iframes).
(function () {
    var requiredRolesRaw = window.REQUIRED_ROLES;
    var requiredRoleSingle = window.REQUIRED_ROLE;

    var allowedRoles = [];
    if (requiredRolesRaw) {
        if (Array.isArray(requiredRolesRaw)) {
            allowedRoles = requiredRolesRaw.map(function (r) { return String(r).trim(); }).filter(Boolean);
        } else if (typeof requiredRolesRaw === 'string') {
            allowedRoles = requiredRolesRaw.split(',').map(function (r) { return String(r).trim(); }).filter(Boolean);
        } else {
            allowedRoles = [String(requiredRolesRaw).trim()].filter(Boolean);
        }
    } else if (requiredRoleSingle) {
        allowedRoles = [String(requiredRoleSingle).trim()];
    }

    var optionalSession = window.SESSION_TIMEOUT_OPTIONAL === true;
    if (!allowedRoles.length) return;
    // Shared pages/partial includes must not install another controller or watcher.
    if (window.__onazulRoleGuardStarted) return;
    window.__onazulRoleGuardStarted = true;
    var resolveController;
    window.__onazulSessionControllerReady = new Promise(function (resolve) { resolveController = resolve; });

    allowedRoles = allowedRoles.map(function (r) { return String(r).trim().toLowerCase(); });

    function absUrl(relativePath) {
        try {
            return new URL(relativePath, window.location.href).href;
        } catch (e) {
            return relativePath;
        }
    }

    function dashboardForRole(roleLower) {
        var dashByRole = {
            admin: '../Admin/admin_dashboard.html',
            hr: '../HR/hr_dashboard.html',
            accounting: '../Accounting/accounting_dashboard.html',
            teamlead: '../TeamLead/team_lead_dashboard.html',
            applicant: '../../Html/Applicant/applicant_landing.html',
            employee: '../Employee/employee_dashboard.html'
        };
        var rel = dashByRole[roleLower] || null;
        if (!rel) return absUrl('../../index.html');
        return absUrl(rel);
    }

    var sessionUrl = absUrl('../../Api/Login/check_session.php');
    var loginUrl = allowedRoles.length === 1 && allowedRoles[0] === 'applicant'
        ? dashboardForRole('applicant')
        : absUrl('../../index.html');

    function checkCurrentSession() {
    if (window.__onazulSessionTimeoutStarted) return;
    var sessionCheckStartedAt = Date.now();
    fetch(sessionUrl, { credentials: 'include', cache: 'no-store' })
        .then(function (r) {
            return r.json().catch(function () { return { role: null }; });
        })
        .then(function (data) {
            if (!data || !data.role) {
                if (optionalSession) return; // Public job wall remains accessible to guests.
                window.location.href = loginUrl;
                return;
            }
            var roleNow = String(data.role).trim().toLowerCase();
            if (allowedRoles.indexOf(roleNow) === -1) {
                if (optionalSession) return;
                if (window.ROLE_GUARD_DEFER_ELEVATION && roleNow === 'employee') {
                    return;
                }
                window.location.href = dashboardForRole(roleNow);
                return;
            }
            if (typeof data.full_name === 'string' && data.full_name) {
                var el = document.getElementById('userName') || document.getElementById('adminName');
                if (el) el.textContent = data.full_name;
            }

            if (window.__onazulSessionTimeoutStarted) return;
            window.__onazulSessionTimeoutStarted = true;
            // Embedded authenticated modules keep their own role validation, but
            // share the dashboard's inactivity lifecycle, even if its check is
            // still pending. Otherwise an iframe's old timer can undo renewal.
            var owner = null;
            try {
                var ancestor = window.parent;
                while (ancestor && ancestor !== window) {
                    if (ancestor.__onazulSessionControllerReady) owner = ancestor;
                    if (ancestor.parent === ancestor) break;
                    ancestor = ancestor.parent;
                }
            } catch (e) { /* Cross-origin frames retain their own controller. */ }
            if (owner) {
                owner.__onazulSessionControllerReady.then(function (controller) {
                    ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (evt) {
                        document.addEventListener(evt, controller.activity, { passive: true });
                    });
                    var frameFetch = window.fetch;
                    window.fetch = function () {
                        var url = arguments.length ? String(arguments[0].url || arguments[0]) : '';
                        if (url.indexOf('logout.php') !== -1) controller.stop();
                        return frameFetch.apply(this, arguments);
                    };
                    resolveController(controller);
                });
            } else {
                var timeoutReturnUrl = roleNow === 'applicant' ? dashboardForRole('applicant') : loginUrl;
                resolveController(initSessionIdleTimeout(timeoutReturnUrl, data, sessionCheckStartedAt));
            }
        })
        .catch(function () {
            if (optionalSession) return;
            window.location.href = loginUrl;
        });
    }
    checkCurrentSession();
    if (optionalSession) document.addEventListener('onazul-auth-success', checkCurrentSession);

    /**
     * Session idle-timeout UI: warns the user shortly before their session
     * expires from inactivity, and logs them out automatically if they don't
     * respond. Only started after role_guard's own check above confirms an
     * active, valid session — never runs on the login page itself.
     *
     * Mirrors the server-side window in Api/Common/session_init.php
     * (SESSION_IDLE_TIMEOUT_SECONDS). Keep the two in step if you retune one.
     */
    function initSessionIdleTimeout(loginPageUrl, sessionData, checkedAt) {
        var IDLE_TIMEOUT_MS = (Number(sessionData.timeout_seconds) || 20 * 60) * 1000;
        var WARNING_BEFORE_MS = Math.min(60 * 1000, IDLE_TIMEOUT_MS / 2); // Production: one minute; short developer runs: half the cycle.
        var HEARTBEAT_MIN_INTERVAL_MS = 60 * 1000; // don't ping the server more than once a minute

        var heartbeatUrl = absUrl('../../Api/Common/session_heartbeat.php');
        var logoutUrl = absUrl('../../Api/Login/logout.php');

        var idleTimer = null;
        var warningTimer = null;
        var countdownInterval = null;
        var lastHeartbeatAt = 0;
        var warningVisible = false;
        var sessionExpiredHandled = false;
        var deadline = 0;
        var cycle = 0;
        var renewal = null;
        var confirmation = null;
        var renewalTimeout = null;
        var renewalController = null;
        var stayButton = null;
        var renewalError = null;
        var warningShownAt = 0;
        var redirectTimer = null;

        // Reconfirm background 401 responses against the current session.
        // A confirmation from an older renewal cycle cannot revoke a newer one.
        var nativeFetch = window.fetch;
        if (nativeFetch && !nativeFetch.__onazulAuthWatched) {
            window.fetch = function () {
                var requestUrl = arguments.length ? String(arguments[0].url || arguments[0]) : '';
                // Existing dashboard logout handlers use fetch too. Stop their idle
                // controller before the request, so an in-flight heartbeat cannot
                // install new timers while manual logout is completing.
                if (requestUrl.indexOf('logout.php') !== -1) stopController();
                else reconcileWarning();
                return nativeFetch.apply(this, arguments).then(function (response) {
                    if (response && response.status === 401 && !sessionExpiredHandled) {
                        var url = requestUrl || (response.url || '');
                        // Don't treat logout.php's own response, or re-entrant
                        // calls once we're already logging out, as a trigger.
                        if (url.indexOf('logout.php') === -1) {
                            // A single 401 isn't trustworthy on its own: background
                            // polling (notification badges, sidebar counters, etc.)
                            // runs on its own schedule, uncoordinated with the idle
                            // timer/heartbeat, so a request sent just before a
                            // successful "Stay Signed In" heartbeat can still land a
                            // stale 401 a moment afterward. Re-confirm against the
                            // actual current session before forcing a logout, so a
                            // genuinely dead session still logs out, but a stray/
                            // racy 401 can no longer override a session the user
                            // just extended.
                            if (confirmation) return response;
                            var confirmedCycle = cycle;
                            confirmation = nativeFetch(sessionUrl, { credentials: 'include', cache: 'no-store' })
                                .then(function (r) { return r.json().catch(function () { return { role: null }; }); })
                                .then(function (data) {
                                    if ((!data || !data.role) && !sessionExpiredHandled && confirmedCycle === cycle && !renewal) {
                                        doLogout(true);
                                    }
                                })
                                .catch(function () {
                                    // A network failure is not proof that authentication expired.
                                })
                                .then(function () { confirmation = null; });
                        }
                    }
                    return response;
                });
            };
            window.fetch.__onazulAuthWatched = true;
        }

        function sendHeartbeat(force) {
            if (sessionExpiredHandled) return Promise.resolve(false);
            if (renewal) return renewal;
            var now = Date.now();
            if (!force && now - lastHeartbeatAt < HEARTBEAT_MIN_INTERVAL_MS) {
                return Promise.resolve(false);
            }
            lastHeartbeatAt = now; // Failed activity requests are throttled too; explicit retry bypasses this.
            if (stayButton) stayButton.disabled = true;
            if (renewalError) renewalError.textContent = '';
            var controller = new AbortController();
            renewalController = controller;
            var request = nativeFetch(heartbeatUrl, { method: 'POST', credentials: 'include', cache: 'no-store', signal: controller.signal });
            // Bound a stalled request while deferring the old expiry callback.
            var timeout = new Promise(function (_, reject) {
                renewalTimeout = window.setTimeout(function () { controller.abort(); reject(new Error('Session renewal timed out.')); }, 10000);
            });
            renewal = Promise.race([request, timeout])
                .then(function (response) {
                    if (response.status === 401) { doLogout(true); return null; }
                    if (!response.ok) throw new Error('Session renewal failed.');
                    return response.json();
                })
                .then(function (data) {
                    if (!data || sessionExpiredHandled) return false;
                    if (data.success !== true || data.authenticated !== true) throw new Error('Session renewal was not confirmed.');
                    lastHeartbeatAt = now;
                    resetIdleTimer(data.remaining_seconds, now);
                    return true;
                })
                .catch(function () {
                    if (renewalError && !sessionExpiredHandled) renewalError.textContent = 'Unable to renew your session. Please try again.';
                    return false;
                })
                .then(function (success) {
                    window.clearTimeout(renewalTimeout); renewalTimeout = null; renewal = null; renewalController = null;
                    if (stayButton) stayButton.disabled = false;
                    if (!sessionExpiredHandled && Date.now() >= deadline) doLogout(true);
                    return success;
                });
            return renewal;
        }

        function stopController(preserveWarning) {
            if (sessionExpiredHandled) return;
            sessionExpiredHandled = true;
            cycle++;
            if (renewalController) renewalController.abort();
            if (!preserveWarning) hideWarning();
            window.clearTimeout(redirectTimer);
            window.clearTimeout(renewalTimeout);
            if (countdownInterval) window.clearInterval(countdownInterval);
            if (idleTimer) window.clearTimeout(idleTimer);
            if (warningTimer) window.clearTimeout(warningTimer);
        }

        function doLogout(expired) {
            if (sessionExpiredHandled) return;
            // A suspended tab can deliver a 401 or both timers in the same
            // task after the deadline. Previously the overlay was appended
            // and removed before the browser could paint it. Keep an honest
            // expired notice visible briefly, without extending authentication.
            var needsNotice = expired === true && (!warningVisible || Date.now() - warningShownAt < 1500);
            if (needsNotice) showWarning(true);
            if (expired === true && warningVisible) {
                if (stayButton) stayButton.disabled = true;
                if (renewalError) renewalError.textContent = 'Your session has expired. Please sign in again.';
                var count = document.getElementById('session-timeout-countdown');
                if (count) count.textContent = '00:00';
            }
            stopController(needsNotice);
            nativeFetch(logoutUrl, {
                method: 'GET',
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .catch(function () {})
                .then(function () {
                    function redirect() { window.location.href = expired === true ? loginPageUrl + (loginPageUrl.indexOf('?') === -1 ? '?' : '&') + 'timeout=1' : loginPageUrl; }
                    function finishLogout() {
                        var noticeDelay = needsNotice ? Math.max(0, warningShownAt + 1500 - Date.now()) : 0;
                        if (noticeDelay) redirectTimer = window.setTimeout(redirect, noticeDelay);
                        else redirect();
                    }
                    if (needsNotice && !document.body) document.addEventListener('DOMContentLoaded', finishLogout, { once: true });
                    else finishLogout();
                });
        }

        function buildWarningModal() {
            var overlay = document.createElement('div');
            overlay.id = 'session-timeout-overlay';
            overlay.setAttribute('role', 'alertdialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'session-timeout-title');
            overlay.setAttribute('style',
                'position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;' +
                'justify-content:center;background:rgba(15,23,42,0.55);font-family:-apple-system,' +
                '"Segoe UI",Roboto,Arial,sans-serif;');

            var card = document.createElement('div');
            card.setAttribute('style',
                'background:#fff;border-radius:12px;max-width:380px;width:90%;padding:24px;' +
                'box-shadow:0 10px 40px rgba(0,0,0,0.25);text-align:center;');

            var title = document.createElement('h3');
            title.id = 'session-timeout-title';
            title.textContent = 'Session Timeout Warning';
            title.setAttribute('style', 'margin:0 0 8px;font-size:1.1rem;font-weight:700;color:#1e293b;');

            var body = document.createElement('p');
            body.setAttribute('style', 'margin:0 0 18px;font-size:0.9rem;color:#475569;');
            var countdownSpan = document.createElement('span');
            countdownSpan.id = 'session-timeout-countdown';
            countdownSpan.style.fontWeight = '700';
            body.appendChild(document.createTextNode("You've been inactive for a while. For your security, you'll be logged out in "));
            body.appendChild(countdownSpan);
            body.appendChild(document.createTextNode('.'));

            var btnRow = document.createElement('div');
            btnRow.setAttribute('style', 'display:flex;gap:8px;justify-content:center;');

            var stayBtn = document.createElement('button');
            stayButton = stayBtn;
            stayBtn.type = 'button';
            stayBtn.textContent = 'Stay Logged In';
            stayBtn.setAttribute('style',
                'background:#2563eb;color:#fff;border:none;border-radius:8px;padding:9px 18px;' +
                'font-size:0.9rem;font-weight:600;cursor:pointer;');
            stayBtn.addEventListener('click', function () {
                sendHeartbeat(true);
            });

            var logoutBtn = document.createElement('button');
            logoutBtn.type = 'button';
            logoutBtn.textContent = 'Logout';
            logoutBtn.setAttribute('style',
                'background:#fff;color:#475569;border:1px solid #cbd5e1;border-radius:8px;' +
                'padding:9px 18px;font-size:0.9rem;font-weight:600;cursor:pointer;');
            logoutBtn.addEventListener('click', function () { doLogout(false); });

            btnRow.appendChild(stayBtn);
            btnRow.appendChild(logoutBtn);
            card.appendChild(title);
            card.appendChild(body);
            renewalError = document.createElement('p');
            renewalError.setAttribute('role', 'alert');
            card.appendChild(renewalError);
            card.appendChild(btnRow);
            overlay.appendChild(card);
            return overlay;
        }

        function showWarning(alreadyExpired) {
            if (warningVisible) return;
            if (!document.body) {
                document.addEventListener('DOMContentLoaded', function () { if (!sessionExpiredHandled || alreadyExpired === true) showWarning(alreadyExpired); }, { once: true });
                return;
            }
            warningVisible = true;
            warningShownAt = Date.now();
            var overlay = buildWarningModal();
            document.body.appendChild(overlay);
            if (alreadyExpired === true) {
                stayButton.disabled = true;
                renewalError.textContent = 'Your session has expired. Please sign in again.';
            }

            var countdownEl = document.getElementById('session-timeout-countdown');
            function updateCountdown() {
                var secondsLeft = alreadyExpired === true ? 0 : Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
                if (countdownEl) countdownEl.textContent = String(Math.floor(secondsLeft / 60)).padStart(2, '0') + ':' + String(secondsLeft % 60).padStart(2, '0');
                if (secondsLeft <= 0 && !renewal && alreadyExpired !== true) doLogout(true);
            }
            updateCountdown();

            if (sessionExpiredHandled || alreadyExpired === true) return;
            countdownInterval = window.setInterval(function () {
                updateCountdown();
            }, 1000);
        }

        function hideWarning() {
            warningVisible = false;
            if (countdownInterval) {
                window.clearInterval(countdownInterval);
                countdownInterval = null;
            }
            var overlay = document.getElementById('session-timeout-overlay');
            if (overlay && overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }

        function resetIdleTimer(remainingSeconds, startedAt) {
            if (idleTimer) window.clearTimeout(idleTimer);
            if (warningTimer) window.clearTimeout(warningTimer);
            hideWarning();
            cycle++;
            var version = cycle;
            var remaining = Number(remainingSeconds);
            if (!Number.isFinite(remaining) || remaining < 0) remaining = IDLE_TIMEOUT_MS / 1000;
            deadline = startedAt + remaining * 1000;
            var delay = Math.max(0, deadline - Date.now());
            warningTimer = window.setTimeout(function () { if (version === cycle && !sessionExpiredHandled) showWarning(); }, Math.max(0, delay - WARNING_BEFORE_MS));
            idleTimer = window.setTimeout(function () { if (version === cycle && !sessionExpiredHandled && !renewal) doLogout(true); }, delay);
        }

        function reconcileWarning() {
            if (sessionExpiredHandled || renewal) return;
            if (Date.now() >= deadline) doLogout(true);
            else if (Date.now() >= deadline - WARNING_BEFORE_MS) showWarning();
        }

        function onUserActivity(event) {
            if (sessionExpiredHandled || (event && event.isTrusted === false)) return;
            reconcileWarning();
            if (warningVisible) return; // let the warning's own buttons drive the decision once shown
            sendHeartbeat(false);
        }

        ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (evt) {
            document.addEventListener(evt, onUserActivity, { passive: true });
        });

        resetIdleTimer(sessionData.remaining_seconds, checkedAt);
        document.addEventListener('visibilitychange', reconcileWarning);
        window.addEventListener('focus', reconcileWarning);
        window.addEventListener('pageshow', reconcileWarning);
        return { activity: onUserActivity, stop: stopController, expire: function () { doLogout(true); } };
    }
})();

