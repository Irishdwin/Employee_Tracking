/**
 * Global message toast for signed-in applicants (any page that includes this script).
 * Polls applicant_poll_messages.php every 5s; shows a toast when unread count increases.
 */
(function () {
    var POLL_MS = 5000;
    var API = 'Api/Applicant/applicant_poll_messages.php';
    var lastUnread = -1;
    var lastToastMsgId = 0;
    var toastEl = null;

    function ensureToastHost() {
        if (toastEl && document.body.contains(toastEl)) {
            return;
        }
        toastEl = document.createElement('div');
        toastEl.id = 'onazulMsgToast';
        toastEl.setAttribute('role', 'status');
        toastEl.setAttribute('aria-live', 'polite');
        toastEl.style.cssText = [
            'position:fixed', 'bottom:24px', 'right:24px', 'z-index:2147483000',
            'max-width:min(380px,94vw)', 'padding:14px 18px', 'border-radius:16px',
            'font-weight:650', 'font-size:14px', 'font-family:system-ui,Segoe UI,sans-serif',
            'box-shadow:0 18px 50px rgba(15,23,42,0.22)', 'border:1px solid rgba(15,23,42,0.08)',
            'background:#fff', 'color:#0f172a', 'display:none', 'cursor:pointer'
        ].join(';');
        document.body.appendChild(toastEl);
        toastEl.addEventListener('click', function () {
            var base = window.location.pathname.indexOf('/Html/') !== -1 ? '' : 'Html/Applicant/';
            window.location.href = base + 'inbox.html';
        });
    }

    function showToast(title, body) {
        ensureToastHost();
        toastEl.innerHTML =
            '<div style="font-family:Sora,system-ui,sans-serif;font-weight:800;font-size:15px;margin-bottom:6px;color:#003366;">' +
            escapeHtml(title) +
            '</div>' +
            '<div style="color:#475569;font-size:13px;line-height:1.4;">' +
            escapeHtml(body) +
            '</div>' +
            '<div style="margin-top:10px;font-size:12px;color:#e60278;font-weight:700;">Open Messages →</div>';
        toastEl.style.display = 'block';
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () {
            toastEl.style.display = 'none';
        }, 9000);
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function tick() {
        var url = API;
        if (window.location.pathname.indexOf('/Html/') !== -1) {
            url = '../../Api/Applicant/applicant_poll_messages.php';
        }
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                if (!d || !d.success) {
                    return;
                }
                var u = parseInt(d.total_unread, 10) || 0;
                var prev = lastUnread;
                lastUnread = u;
                if (prev < 0) {
                    return;
                }
                if (u > prev && d.preview && d.preview.message_id) {
                    var mid = parseInt(d.preview.message_id, 10);
                    if (mid !== lastToastMsgId) {
                        lastToastMsgId = mid;
                        var who = d.preview.channel === 'admin' ? 'Administrator' : (d.preview.channel === 'hr' ? 'HR' : (d.preview.sender_name || 'Onazul'));
                        var line = (d.preview.preview || '').trim();
                        showToast('New message from ' + who, line || 'You have a new message.');
                    }
                }
            })
            .catch(function () {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(tick, 1200);
            setInterval(tick, POLL_MS);
        });
    } else {
        setTimeout(tick, 1200);
        setInterval(tick, POLL_MS);
    }
})();
