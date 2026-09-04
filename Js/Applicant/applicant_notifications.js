/**
 * Applicant notifications - bell icon, dropdown, polling.
 * Include this script on applicant pages. Expects API_BASE or uses ../../Api/Applicant/
 */
(function () {
  const API = (typeof API_BASE !== 'undefined' ? API_BASE : '../../Api/Applicant/') + 'notifications.php';
  const POLL_INTERVAL = 30000;

  function notificationDestination(n) {
    var type = String(n && n.type ? n.type : '').toLowerCase();
    // Chat notifications should route to the HR messenger inbox.
    if (type.indexOf('hr_message') !== -1) return 'inbox.html';
    // Be tolerant for any future chat-related notification types.
    if (type.indexOf('message') !== -1 || type.indexOf('chat') !== -1) return 'inbox.html';
    return 'applicant_notifications.html';
  }

  function renderList(notifications, unreadCount) {
    var list = document.getElementById('notificationList');
    var badge = document.getElementById('notificationBadge');
    if (!list) return;

    if (badge) {
      if (window.OwNotifications && typeof OwNotifications.updateUnreadBadge === 'function') {
        OwNotifications.updateUnreadBadge(unreadCount);
      } else {
        var n = parseInt(unreadCount, 10) || 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.classList.toggle('d-none', n <= 0);
        badge.style.display = n > 0 ? 'inline-flex' : 'none';
      }
    }

    if (!notifications || notifications.length === 0) {
      list.innerHTML = '<li class="dropdown-item text-muted small">No notifications.</li>' +
        '<li><a href="applicant_notifications.html" class="dropdown-item small text-center fw-bold text-primary">View all notifications</a></li>';
      return;
    }

    list.innerHTML = notifications.map(function (n) {
      var cls = n.is_read ? '' : 'bg-light ow-notif-unread fw-semibold';
      var dot = n.is_read ? '' : '<span class="badge bg-primary rounded-circle p-1 me-1" aria-hidden="true"></span>';
      var link = notificationDestination(n);
      return '<li><a href="' + escapeHtml(link) + '" class="dropdown-item ' + cls + ' py-2 d-flex align-items-start gap-1 text-wrap" data-id="' + n.id + '">' +
        dot +
        '<span class="flex-grow-1"><strong class="d-block text-dark">' + escapeHtml(n.title || 'Notification') + '</strong>' +
        '<small class="text-muted d-block">' + escapeHtml((n.message || '').substring(0, 80)) + (n.message && n.message.length > 80 ? '…' : '') + '</small></span>' +
        '</a></li>';
    }).join('') +
      '<li><hr class="dropdown-divider"></li>' +
      '<li><a href="applicant_notifications.html" class="dropdown-item small text-center fw-bold text-primary py-2">View all notifications</a></li>';

    list.querySelectorAll('[data-id]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = parseInt(el.getAttribute('data-id'), 10);
        var href = el.getAttribute('href') || 'applicant_notifications.html';
        fetch(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'mark_read', notification_id: id }),
          credentials: 'include'
        })
          .catch(function () {})
          .then(function () {
            window.location.href = href;
          });
      });
    });
  }

  function escapeHtml(text) {
    if (text == null) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function poll() {
    fetch(API + '?limit=10', { credentials: 'include', method: 'GET' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          renderList(data.notifications || [], data.unread_count || 0);
        }
      })
      .catch(function () {});
  }

  function init() {
    var bell = document.getElementById('notificationBell');
    var list = document.getElementById('notificationList');
    if (!bell && !list) return;

    poll();
    setInterval(poll, POLL_INTERVAL);

    var markAll = document.getElementById('markAllRead');
    if (markAll) {
      markAll.addEventListener('click', function (e) {
        e.preventDefault();
        fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'mark_all_read' }), credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (d.success) poll(); });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();