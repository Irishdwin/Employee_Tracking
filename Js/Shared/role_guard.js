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

    if (!allowedRoles.length) return;

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
    var loginUrl = absUrl('../../index.html');

    fetch(sessionUrl, { credentials: 'include' })
        .then(function (r) {
            return r.json().catch(function () { return { role: null }; });
        })
        .then(function (data) {
            if (!data || !data.role) {
                window.location.href = loginUrl;
                return;
            }
            var roleNow = String(data.role).trim().toLowerCase();
            if (allowedRoles.indexOf(roleNow) === -1) {
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
        })
        .catch(function () {
            window.location.href = loginUrl;
        });
})();
