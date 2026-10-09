/**
 * Applicant notifications - bell icon, dropdown, polling.
 * Include this script on applicant pages. Expects API_BASE or uses ../../Api/Applicant/
 */
(function () {
  const API = (typeof API_BASE !== 'undefined' ? API_BASE : '../../Api/Applicant/') + 'notifications.php';
  const POLL_INTERVAL = 30000;
  const style=document.createElement('style');style.textContent='#notificationList .ow-notif-unread{background:#fff0f1!important;border-left:3px solid #dc3545;color:#a31929}#notificationList .ow-notif-unread strong{color:#a31929!important}#notificationList .dropdown-item:not(.ow-notif-unread){background:transparent}#notificationList .ow-notif-view{position:sticky;bottom:0;background:#edf4fc;padding:10px;display:block;border-top:1px solid #cdddef}#notificationList .ow-notif-view a{display:block;text-align:center;background:#0056b3;color:white;border-radius:8px;padding:9px;text-decoration:none;font-weight:600}.ow-applicant-notification-header{position:sticky;top:0;background:#fff!important;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:16px}.ow-applicant-notification-header a{color:#0056b3;font-weight:700}';document.head.appendChild(style);
  const viewAll='<li class="ow-notif-view"><a href="applicant_notifications.html">View all notifications</a></li>';

  function notificationDestination(n) {
    if(n.messenger_user_id)return 'applicant_notifications.html?notification='+encodeURIComponent(n.id);
    var type = String(n && n.type ? n.type : '').toLowerCase();
    // Chat notifications should route to the HR messenger inbox.
    if (type.indexOf('hr_message') !== -1) return 'inbox.html';
    // Be tolerant for any future chat-related notification types.
    if (type.indexOf('message') !== -1 || type.indexOf('chat') !== -1) return 'inbox.html';
    return 'applicant_notifications.html?notification=' + encodeURIComponent(n.id);
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
        viewAll;
      return;
    }

    list.innerHTML = notifications.map(function (n) {
      var cls = n.is_read ? '' : 'ow-notif-unread fw-semibold';
      var dot = n.is_read ? '' : '<span class="badge bg-danger rounded-circle p-1 me-1" aria-hidden="true"></span>';
      var link = notificationDestination(n);
      return '<li><a href="' + escapeHtml(link) + '" class="dropdown-item ' + cls + ' py-2 d-flex align-items-start gap-1 text-wrap" data-id="' + n.id + '">' +
        dot +
        '<span class="flex-grow-1"><strong class="d-block text-dark">' + escapeHtml(n.title || 'Notification') + '</strong>' +
        '<small class="text-muted d-block">' + escapeHtml((n.message || '').substring(0, 80)) + (n.message && n.message.length > 80 ? '…' : '') + '</small></span>' +
        '</a></li>';
    }).join('') +
      '<li><hr class="dropdown-divider"></li>' +
      viewAll;

    list.querySelectorAll('[data-id]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = parseInt(el.getAttribute('data-id'), 10);
        var href = el.getAttribute('href') || 'applicant_notifications.html';
        window.location.href = href;
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

    var header=list.closest('.dropdown-menu')?.querySelector('.dropdown-header');
    if(header){header.classList.add('ow-applicant-notification-header');header.innerHTML='Notifications <a href="applicant_notifications.html">View all</a>';}
    poll();
    setInterval(poll, POLL_INTERVAL);
    document.addEventListener('applicant-notifications-updated', poll);

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