/**
 * Admin dashboard — in-page notifications section (#notifications-section).
 */
(function (global) {
  'use strict';

  var NOTIFICATIONS_API = '../../Api/Common/notifications.php';
  var state = {
    notifications: [],
    activeId: null,
    unreadOnly: false,
    initialized: false
  };

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function renderList(notifications) {
    var list = document.getElementById('adminNotifList');
    if (!list) return;

    if (!notifications || !notifications.length) {
      list.innerHTML = '<div class="list-group-item text-muted small py-4 text-center">No notifications.</div>';
      return;
    }

    list.innerHTML = notifications.map(function (n) {
      var isActive = state.activeId === n.id;
      var unreadBadge = n.is_read ? '' : '<span class="badge bg-primary rounded-pill">New</span>';
      return (
        '<button type="button" class="list-group-item list-group-item-action admin-notif-item' +
        (isActive ? ' active' : '') + (n.is_read ? '' : ' admin-notif-unread') + '" data-id="' + n.id + '">' +
        '<div class="d-flex w-100 justify-content-between align-items-start gap-2">' +
        '<div class="text-start">' +
        '<div class="fw-semibold">' + esc(n.title || 'Notification') + '</div>' +
        '<div class="small text-muted">' + esc(n.from || 'System') + ' · ' + esc(n.type || '') + '</div>' +
        '</div>' + unreadBadge + '</div>' +
        '<p class="mb-0 mt-1 small text-secondary text-start">' +
        esc((n.message || '').substring(0, 120)) + ((n.message || '').length > 120 ? '…' : '') +
        '</p></button>'
      );
    }).join('');

    list.querySelectorAll('.admin-notif-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openNotification(parseInt(btn.getAttribute('data-id'), 10));
      });
    });
  }

  function renderDetails(n) {
    var el = document.getElementById('adminNotifDetails');
    if (!el) return;

    if (!n) {
      el.innerHTML = '<p class="text-muted mb-0">Select a notification to view details.</p>';
      return;
    }

    var related = n.related_record
      ? esc(n.related_module || '') + ' #' + esc(n.related_record)
      : esc(n.related_module || '—');

    el.innerHTML =
      '<div class="d-flex justify-content-between align-items-start gap-3 mb-3">' +
      '<div><h2 class="h5 fw-semibold mb-1">' + esc(n.title || 'Notification') + '</h2>' +
      '<p class="small text-muted mb-0">' + esc(n.from || 'System') + ' · ' + esc(n.type || '') + '</p></div>' +
      '<span class="badge ' + (n.is_read ? 'bg-secondary' : 'bg-primary') + '">' +
      (n.is_read ? 'Read' : 'Unread') + '</span></div>' +
      '<div class="border rounded p-3 bg-light" style="white-space:pre-wrap;">' + esc(n.message || '') + '</div>' +
      '<p class="small text-muted mt-3 mb-0">Related: ' + related + '</p>';
  }

  function loadNotifications() {
    var chk = document.getElementById('adminChkUnread');
    if (chk) state.unreadOnly = !!chk.checked;

    var listEl = document.getElementById('adminNotifList');
    var detailsEl = document.getElementById('adminNotifDetails');
    if (listEl) {
      listEl.innerHTML = '<div class="list-group-item text-muted small py-4 text-center">Loading…</div>';
    }
    if (detailsEl) {
      detailsEl.innerHTML = '<p class="text-muted mb-0">Loading…</p>';
    }

    var url = NOTIFICATIONS_API + '?unread_only=' + (state.unreadOnly ? '1' : '0') + '&limit=50';

    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) throw new Error((d && d.message) || 'Failed to load');

        state.notifications = d.notifications || [];

        if (state.activeId && state.notifications.some(function (x) { return x.id === state.activeId; })) {
          renderDetails(state.notifications.find(function (x) { return x.id === state.activeId; }));
        } else if (state.notifications.length) {
          state.activeId = state.notifications[0].id;
          renderDetails(state.notifications[0]);
        } else {
          state.activeId = null;
          renderDetails(null);
        }

        renderList(state.notifications);

        if (global.AdminSidebarBadges && d.unread_count != null) {
          AdminSidebarBadges.apply({ notifications_unread: d.unread_count });
        }
      })
      .catch(function () {
        if (listEl) {
          listEl.innerHTML = '<div class="list-group-item text-danger small py-4 text-center">Failed to load notifications.</div>';
        }
      });
  }

  function openNotification(id) {
    var n = state.notifications.find(function (x) { return x.id === id; });
    if (!n) return;

    state.activeId = id;
    renderList(state.notifications);
    renderDetails(n);

    if (!n.is_read) {
      fetch(NOTIFICATIONS_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'mark_read', notification_id: id })
      })
        .then(function () {
          n.is_read = true;
          renderList(state.notifications);
          renderDetails(n);
          loadNotifications();
        })
        .catch(function () {});
    }
  }

  function initPanel() {
    if (state.initialized) return;
    state.initialized = true;

    var chk = document.getElementById('adminChkUnread');
    var btnMarkAll = document.getElementById('adminBtnMarkAll');
    var btnRefresh = document.getElementById('adminBtnRefreshNotif');

    if (chk) {
      chk.addEventListener('change', loadNotifications);
    }
    if (btnRefresh) {
      btnRefresh.addEventListener('click', loadNotifications);
    }
    if (btnMarkAll) {
      btnMarkAll.addEventListener('click', function () {
        if (!global.confirm('Mark all notifications as read?')) return;
        fetch(NOTIFICATIONS_API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ action: 'mark_all_read' })
        })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d && d.success) loadNotifications();
          })
          .catch(function () {});
      });
    }
  }

  function initBell(showSectionFn) {
    var btn = document.getElementById('ccNotifBtn');
    if (!btn || btn.dataset.adminNotifBound === '1') return;
    btn.dataset.adminNotifBound = '1';

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      if (typeof showSectionFn === 'function') {
        showSectionFn('notifications-section');
      } else if (global.adminShowSection) {
        global.adminShowSection('notifications-section');
      }
      initPanel();
      loadNotifications();
    });
  }

  function handleRealtimeNotification(payload) {
    if (!payload || !payload.message) return;
    loadNotifications();
    if (global.AdminMessenger && typeof AdminMessenger.onSystemNotification === 'function') {
      AdminMessenger.onSystemNotification(payload);
    }
  }

  global.AdminNotifications = {
    load: loadNotifications,
    initPanel: initPanel,
    initBell: initBell,
    handleRealtime: handleRealtimeNotification
  };
})(window);
