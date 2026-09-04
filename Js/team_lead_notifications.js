/**
 * Team Lead — bell dropdown + polling (uses shared Api/notifications.php).
 */
(function () {
  var API = '../../Api/Common/notifications.php';
  var POLL_MS = 12000;

  function escapeHtml(t) {
    if (t == null) return '';
    var d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
  }

  function render(notifications, unreadCount) {
    var list = document.getElementById('tlNotifList');
    var badge = document.getElementById('tlNotifBadge');
    if (badge) {
      badge.textContent = String(unreadCount || 0);
      badge.style.display = (unreadCount > 0) ? 'inline' : 'none';
    }
    if (!list) return;

    if (!notifications || notifications.length === 0) {
      list.innerHTML = '<div class="text-muted py-2 px-1 small">No notifications yet.</div>';
      return;
    }

    list.innerHTML = notifications.slice(0, 12).map(function (n) {
      var unread = !n.is_read;
      return (
        '<div class="border-bottom py-2 px-1 tl-notif-item' + (unread ? ' bg-light' : '') + '" data-id="' + n.id + '" style="cursor:pointer;">' +
        '<div class="fw-semibold small">' + escapeHtml(n.title || 'Notification') + '</div>' +
        '<div class="text-muted" style="font-size:0.78rem;">' + escapeHtml((n.message || '').substring(0, 140)) + (n.message && n.message.length > 140 ? '…' : '') + '</div>' +
        '</div>'
      );
    }).join('');

    list.querySelectorAll('.tl-notif-item').forEach(function (el) {
      el.addEventListener('click', function () {
        var id = parseInt(el.getAttribute('data-id'), 10);
        if (!id) return;
        fetch(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'mark_read', notification_id: id }),
          credentials: 'include'
        }).catch(function () {});
        poll();
      });
    });
  }

  function poll() {
    fetch(API + '?limit=15', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          render(data.notifications || [], data.unread_count || 0);
        }
      })
      .catch(function () {});
  }

  function init() {
    var bell = document.getElementById('tlNotifBell');
    if (!bell) return;
    poll();
    setInterval(poll, POLL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
