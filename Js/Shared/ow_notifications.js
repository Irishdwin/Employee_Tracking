/**
 * Shared in-page notifications panel (all roles) + bell badge count.
 */
(function (global) {
  'use strict';

  var NOTIFICATIONS_API = '../../Api/Common/notifications.php';
  var COUNTERS_API = '../../Api/Common/realtime_counters.php';
  var cfg = global.OW_NOTIF_CONFIG || {};
  var pollTimer = null;

  var N = {
    list: ['owNotifList', 'adminNotifList', 'notifList'],
    details: ['owNotifDetails', 'adminNotifDetails', 'notifDetails'],
    chkUnread: ['owChkUnread', 'adminChkUnread', 'chkUnread'],
    btnMarkAll: ['owBtnMarkAll', 'adminBtnMarkAll', 'btnMarkAll'],
    btnRefresh: ['owBtnRefreshNotif', 'adminBtnRefreshNotif'],
    bell: ['owNotifBellBtn', 'ccNotifBtn', 'topbarNotifBtn', 'notif-btn', 'notificationBell'],
    dot: ['owNotifDot']
  };

  function gid(key) {
    var ids = N[key] || [];
    for (var i = 0; i < ids.length; i++) {
      var el = document.getElementById(ids[i]);
      if (el) return el;
    }
    return null;
  }

  function queryBadge(btn) {
    if (!btn) return null;
    return btn.querySelector(
      '[data-global-notif-badge], #globalNotifBadge, .ow-notif-badge, .ow-notif-dot, .cc-notif-dot, .topbar-notif-dot, .badge.bg-danger'
    ) || gid('dot');
  }

  function formatBadgeCount(count) {
    var n = parseInt(count, 10) || 0;
    if (n <= 0) return { n: 0, label: '' };
    if (n > 99) return { n: n, label: '99+' };
    return { n: n, label: String(n) };
  }

  var state = { notifications: [], activeId: null, unreadOnly: false, initialized: false, unreadCount: 0 };

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  var unreadDotHtml = '<span class="rounded-circle bg-danger flex-shrink-0 mt-1" style="width:8px;height:8px;" aria-hidden="true"></span>';

  function buildNotificationListItemHtml(n, isActive) {
    var isUnread = !n.is_read;
    var unreadDot = isUnread ? unreadDotHtml : '';
    var unreadBadge = isUnread ? '<span class="badge bg-danger rounded-pill">New</span>' : '';
    var list = gid('list');
    var useListGroup = list && list.classList && list.classList.contains('list-group');

    if (useListGroup) {
      return (
        '<button type="button" class="list-group-item list-group-item-action ow-notif-item' +
        (isActive ? ' active' : '') +
        (isUnread ? ' bg-danger bg-opacity-10 border-start border-danger border-3 fw-semibold' : '') +
        '" data-id="' + n.id + '" data-read="' + (isUnread ? '0' : '1') + '"' +
        (n.type ? ' data-type="' + esc(n.type) + '"' : '') + '>' +
        '<div class="d-flex w-100 justify-content-between align-items-start gap-2">' +
        unreadDot +
        '<div class="text-start flex-grow-1">' +
        '<div class="' + (isUnread ? 'fw-bold' : 'fw-semibold') + '">' + esc(n.title || 'Notification') + '</div>' +
        '<div class="small text-muted">' + esc(n.from || 'System') + ' · ' + esc(n.type || '') + '</div></div>' +
        unreadBadge + '</div>' +
        '<p class="mb-0 mt-1 small text-secondary text-start">' +
        esc((n.message || '').substring(0, 120)) + ((n.message || '').length > 120 ? '…' : '') +
        '</p></button>'
      );
    }

    return (
      '<div class="ow-notif-item p-3 border-bottom d-flex align-items-start gap-2' +
      (isActive && !isUnread ? ' bg-light' : '') +
      (isUnread ? ' bg-danger bg-opacity-10 border-start border-danger border-3' : '') +
      '" data-id="' + n.id + '" data-read="' + (isUnread ? '0' : '1') + '" style="cursor:pointer" role="button" tabindex="0"' +
      (n.type ? ' data-type="' + esc(n.type) + '"' : '') + '>' +
      unreadDot +
      '<div class="flex-grow-1 min-w-0">' +
      '<div class="' + (isUnread ? 'fw-bold' : 'fw-semibold') + '">' + esc(n.title || 'Notification') + '</div>' +
      '<div class="small text-muted">' + esc((n.message || '').substring(0, 100)) + '</div></div></div>'
    );
  }

  function bindNotificationItemClick(el) {
    if (!el || el.dataset.owNotifBound === '1') return;
    el.dataset.owNotifBound = '1';
    el.addEventListener('click', function () {
      var id = parseInt(el.getAttribute('data-id'), 10);
      if (id) openNotification(id);
    });
  }

  function bindAllNotificationItems(container) {
    var root = container || gid('list');
    if (!root) return;
    root.querySelectorAll('[data-id]').forEach(bindNotificationItemClick);
  }

  function prependNotification(n) {
    if (!n || !n.id) {
      pollUnreadBadge();
      if (hasNotifPanel()) loadNotifications();
      return;
    }

    var exists = state.notifications.some(function (x) { return x.id === n.id; });
    if (!exists) {
      state.notifications.unshift(n);
    }

    if (!n.is_read) {
      updateUnreadBadge(state.unreadCount + 1);
    }

    var list = gid('list');
    if (!list) return;

    var emptyRow = list.querySelector('.list-group-item.text-muted, .text-muted.p-3, .text-muted.p-4');
    if (emptyRow && !list.querySelector('[data-id]')) {
      list.innerHTML = '';
    }

    var html = buildNotificationListItemHtml(n, state.activeId === n.id);
    list.insertAdjacentHTML('afterbegin', html);
    var newEl = list.querySelector('[data-id="' + n.id + '"]');
    bindNotificationItemClick(newEl);

    if (!state.activeId) {
      state.activeId = n.id;
      renderDetails(n);
    }
  }

  function renderList(notifications) {
    var list = gid('list');
    if (!list) return;

    if (!notifications || !notifications.length) {
      if (list.classList && list.classList.contains('list-group')) {
        list.innerHTML = '<div class="list-group-item text-muted small py-4 text-center">No notifications.</div>';
      } else {
        list.innerHTML = '<div class="text-muted p-3">No notifications.</div>';
      }
      return;
    }

    list.innerHTML = notifications.map(function (n) {
      return buildNotificationListItemHtml(n, state.activeId === n.id);
    }).join('');

    bindAllNotificationItems(list);
  }

  function renderDetails(n) {
    var el = gid('details');
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
      '<span class="badge ' + (n.is_read ? 'bg-secondary' : 'bg-primary') + '">' + (n.is_read ? 'Read' : 'Unread') + '</span></div>' +
      '<div class="border rounded p-3 bg-light" style="white-space:pre-wrap;">' + esc(n.message || '') + '</div>' +
      '<p class="small text-muted mt-3 mb-0">Related: ' + related + '</p>';
    if(n.messenger_user_id && global.OwMessenger){var chatButton=document.createElement('button');chatButton.type='button';chatButton.className='btn btn-primary mt-3';chatButton.textContent='Open Conversation';chatButton.onclick=function(){global.OwMessenger.openContact(n.messenger_user_id);};el.appendChild(chatButton);}
    if (n.type === 'employee_password_setup' && global.EmployeeSecurity) {
      var settingsButton = document.createElement('button');
      settingsButton.type = 'button';
      settingsButton.className = 'btn btn-primary mt-3';
      settingsButton.textContent = 'Open Settings';
      settingsButton.addEventListener('click', function () { global.EmployeeSecurity.open(); });
      el.appendChild(settingsButton);
    }
  }

  function updateUnreadBadge(count) {
    var formatted = formatBadgeCount(count);
    state.unreadCount = formatted.n;

    if (typeof global.updateGlobalNotifBadge === 'function') {
      global.updateGlobalNotifBadge(formatted.n);
      return;
    }

    N.bell.forEach(function (bid) {
      var btn = document.getElementById(bid);
      if (!btn) return;
      var badge = queryBadge(btn);
      if (badge) {
        if (formatted.n <= 0) {
          badge.textContent = '';
          badge.classList.add('d-none');
        } else {
          badge.textContent = formatted.label;
          badge.classList.remove('d-none');
        }
      }
      btn.setAttribute('aria-label', formatted.n > 0
        ? formatted.label + ' unread notifications'
        : 'Notifications');
    });
  }

  function updateUnreadDot(count) {
    updateUnreadBadge(count);
  }

  function pollUnreadBadge() {
    return fetch(COUNTERS_API, { credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return null; }); })
      .then(function (d) {
        if (d && d.success && d.logged_in && d.counts && d.counts.notifications_unread != null) {
          updateUnreadBadge(d.counts.notifications_unread);
          document.dispatchEvent(new CustomEvent('onazul:realtime-counts', { detail: d }));
          return;
        }
        return fetch(NOTIFICATIONS_API + '?limit=1', { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (nd) {
            if (nd && nd.success) updateUnreadBadge(nd.unread_count);
          });
      })
      .catch(function () {});
  }

  function startBadgePoll() {
    var interval = cfg.pollIntervalMs != null ? cfg.pollIntervalMs : 30000;
    if (pollTimer) clearInterval(pollTimer);
    pollUnreadBadge();
    if (interval > 0) {
      pollTimer = setInterval(pollUnreadBadge, interval);
    }
  }

  function hasNotifPanel() {
    if (gid('list')) return true;
    var sectionId = cfg.sectionId || 'notifications-section';
    var altId = cfg.altSectionId || 'section-notifications';
    return !!(document.getElementById(sectionId) || document.getElementById(altId) || document.getElementById('view-notifications'));
  }

  function isNavOnlyBell(btn) {
    if (!btn || btn.tagName !== 'A') return false;
    var href = (btn.getAttribute('href') || '').trim();
    if (!href || href === '#') return false;
    if (href.charAt(0) === '#') return false;
    return true;
  }

  function loadNotifications() {
    var chk = gid('chkUnread');
    if (chk) state.unreadOnly = !!chk.checked;

    var listEl = gid('list');
    var detailsEl = gid('details');
    if (listEl) listEl.innerHTML = '<div class="text-muted p-3 text-center">Loading…</div>';
    if (detailsEl) detailsEl.innerHTML = '<p class="text-muted mb-0">Loading…</p>';

    var url = NOTIFICATIONS_API + '?unread_only=' + (state.unreadOnly ? '1' : '0') + '&limit=50';

    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) throw new Error((d && d.message) || 'Failed');
        state.notifications = d.notifications || [];
        updateUnreadBadge(d.unread_count);

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
      })
      .catch(function () {
        if (listEl) listEl.innerHTML = '<div class="text-danger p-3 text-center">Failed to load notifications.</div>';
      });
  }

  function openNotification(id) {
    var n = state.notifications.find(function (x) { return x.id === id; });
    if (!n) return;
    document.dispatchEvent(new CustomEvent('onazul:notification-open',{detail:n}));
    state.activeId = id;
    var wasUnread = !n.is_read;
    if (wasUnread) {
      n.is_read = true;
      updateUnreadBadge(Math.max(0, state.unreadCount - 1));
    }
    renderList(state.notifications);
    renderDetails(n);
    if (wasUnread) {
      fetch(NOTIFICATIONS_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'mark_read', notification_id: id })
      }).then(function () {
        pollUnreadBadge();
      }).catch(function () {
        pollUnreadBadge();
      });
    }
  }

  function initPanel() {
    if (state.initialized) return;
    state.initialized = true;
    var chk = gid('chkUnread');
    var btnMarkAll = gid('btnMarkAll');
    var btnRefresh = gid('btnRefresh');
    if (chk) chk.addEventListener('change', loadNotifications);
    if (btnRefresh) btnRefresh.addEventListener('click', loadNotifications);
    if (btnMarkAll) {
      btnMarkAll.addEventListener('click', function () {
        var confirmEl = document.getElementById('hrMarkAllNotificationsModal') || document.getElementById('tlMarkAllNotificationsModal');
        if (confirmEl && global.bootstrap) {
          var modal = global.bootstrap.Modal.getOrCreateInstance(confirmEl);
          var confirmButton = confirmEl.querySelector('[data-confirm-mark-all]') || document.getElementById('tlConfirmMarkAllNotifications');
          confirmButton.onclick = function () {
            // Disable until the modal closes to prevent repeated submissions.
            confirmButton.disabled = true;
            modal.hide();
            executeMarkAllRead();
          };
          confirmEl.addEventListener('hidden.bs.modal', function () {
            confirmButton.disabled = false;
            btnMarkAll.focus();
          }, { once: true });
          modal.show();
        } else if (typeof global.Swal !== 'undefined') {
          global.Swal.fire({
            title: 'Mark all as read?',
            text: 'Are you sure you want to mark all notifications as read?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0056b3',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, mark all'
          }).then(function (result) {
            if (result.isConfirmed) {
              executeMarkAllRead();
            }
          });
        } else {
          if (!global.confirm('Mark all notifications as read?')) return;
          executeMarkAllRead();
        }
      });
    }

    function executeMarkAllRead() {
      fetch(NOTIFICATIONS_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'mark_all_read' })
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.success) {
          loadNotifications();
          if (typeof global.Swal !== 'undefined') {
            global.Swal.fire({
              icon: 'success',
              title: 'Updated',
              text: d.message || 'All notifications marked as read.',
              timer: 1500,
              showConfirmButton: false
            });
          }
        }
      }).catch(function () {});
    }
  }

  function showNotificationsSection() {
    var sectionId = cfg.sectionId || 'notifications-section';
    var altId = cfg.altSectionId || 'section-notifications';

    if (typeof cfg.showSection === 'function') {
      cfg.showSection(sectionId);
      return;
    }
    if (global.adminShowSection) { global.adminShowSection(sectionId); return; }
    if (global.hrShowSection) { global.hrShowSection(sectionId); return; }
    if (global.profileShowView) { global.profileShowView('notifications'); return; }
    if (global.profileShowSection) { global.profileShowSection('section-notifications'); return; }

    var main = document.getElementById('main-content');
    if (main) {
      var sections = main.querySelectorAll('.dashboard-section');
      sections.forEach(function (section) {
        var show = section.id === sectionId || section.id === altId;
        section.classList.toggle('d-none', !show);
        section.classList.toggle('active', show);
      });
      document.querySelectorAll('#main-sidebar .sidebar-link[data-target], #sidebar .sidebar-link[data-target]').forEach(function (l) {
        var t = l.getAttribute('data-target');
        l.classList.toggle('active', t === sectionId || t === altId);
      });
      return;
    }

    if (global.profileShowView) global.profileShowView('notifications');
  }

  function shouldSkipBellBinding(btn) {
    if (isNavOnlyBell(btn)) return true;
    if (btn.getAttribute('data-bs-toggle') === 'dropdown') return true;
    if (btn.classList.contains('dropdown-toggle')) return true;
    return false;
  }

  function bindBells() {
    N.bell.forEach(function (bid) {
      var btn = document.getElementById(bid);
      if (!btn) return;

      ensureBadgeElement(btn);

      if (shouldSkipBellBinding(btn)) return;

      if (btn.dataset.owNotifBound === '1') return;
      btn.dataset.owNotifBound = '1';
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        showNotificationsSection();
        initPanel();
        loadNotifications();
      });
    });
  }

  function ensureBadgeElement(btn) {
    if (queryBadge(btn)) return;
    var span = document.createElement('span');
    span.className =
      'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-2 border-light d-none';
    span.setAttribute('data-global-notif-badge', '');
    span.setAttribute('aria-hidden', 'true');
    span.style.fontSize = '0.65rem';
    btn.classList.add('position-relative');
    if (getComputedStyle(btn).position === 'static' && !btn.classList.contains('relative')) {
      btn.style.position = 'relative';
    }
    btn.appendChild(span);
  }

  function init(options) {
    if (options) {
      cfg = Object.assign({}, cfg, options);
      global.OW_NOTIF_CONFIG = cfg;
    }

    var hasBell = N.bell.some(function (id) { return document.getElementById(id); });
    if (!hasBell && !hasNotifPanel()) return;

    bindBells();
    startBadgePoll();

    if (hasNotifPanel()) {
      initPanel();
      loadNotifications();
    }
  }

  function handleRealtime(payload) {
    if (!payload) return;

    if (global.OwSystemEvents && typeof OwSystemEvents.handleWebSocketEnvelope === 'function') {
      var envelope = payload.type === 'system_notification'
        ? payload
        : { type: 'system_notification', event: payload.event, payload: payload, roles: payload.roles };
      if (OwSystemEvents.handleWebSocketEnvelope(envelope)) {
        return;
      }
    }

    pollUnreadBadge();
    if (hasNotifPanel()) loadNotifications();
    if (global.OwMessenger) OwMessenger.onSystemNotification(payload);
  }

  global.OwNotifications = {
    init: init,
    initPanel: initPanel,
    bindBells: bindBells,
    load: loadNotifications,
    poll: pollUnreadBadge,
    show: showNotificationsSection,
    open: function(notification) {
      if (!notification || !notification.id) return;
      // Dropdown records may not be in the inbox's most recent loaded page.
      if (!state.notifications.some(function(n){return n.id === notification.id;})) {
        state.notifications.unshift(notification);
      }
      state.activeId = notification.id;
      showNotificationsSection();
      initPanel();
      openNotification(notification.id);
    },
    handleRealtime: handleRealtime,
    prependNotification: prependNotification,
    updateUnreadBadge: updateUnreadBadge,
    updateUnreadDot: updateUnreadDot,
    updateGlobalNotifBadge: function (c) { updateUnreadBadge(c); }
  };
  global.AdminNotifications = global.OwNotifications;
  global.loadNotifications = loadNotifications;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(); });
  } else {
    init();
  }
})(window);
