/**
 * Admin floating messenger — RBAC contact list, chat, WebSocket boilerplate.
 */
(function (global) {
  'use strict';

  var CONTACTS_API = '../../Api/Common/messenger_contacts.php';
  var THREAD_API = '../../Api/Common/messenger_thread.php';
  var MESSAGES_API = '../../Api/Common/messages_messages.php';
  var SEND_API = '../../Api/Common/messages_send.php';

  var WS_URL = global.ADMIN_WS_URL || 'ws://127.0.0.1:8080';
  var ALLOWED_SA_ROLES = ['Admin', 'HR', 'TeamLead'];

  var state = {
    open: false,
    currentUser: null,
    allUsers: [],
    contacts: [],
    filteredContacts: [],
    activeContact: null,
    activeThreadId: null,
    ws: null,
    wsConnected: false,
    reconnectTimer: null
  };

  function rbac() {
    return global.MessengerRbac || null;
  }

  function esc(s) {
    var R = rbac();
    if (R && R.escapeHtml) return R.escapeHtml(s);
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  /* ---------- UI ---------- */

  function setOpen(open) {
    state.open = open;
    var panel = document.getElementById('adminMessengerPanel');
    var fab = document.getElementById('adminMessengerFab');
    if (panel) panel.classList.toggle('is-open', open);
    if (fab) fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && !state.contacts.length) {
      loadContacts();
    }
    if (open && !state.wsConnected) {
      connectWebSocket();
    }
  }

  /** RBAC-filtered list + search → render sidebar */
  function applyContactFilters(searchQuery) {
    var R = rbac();
    if (!R || !state.currentUser) {
      state.filteredContacts = state.contacts.slice();
    } else {
      state.contacts = R.filterAllowedContacts(state.currentUser, state.allUsers);
      state.filteredContacts = R.filterContactsBySearch(state.contacts, searchQuery);
    }
    renderContacts(state.filteredContacts);
  }

  function renderContacts(list) {
    var el = document.getElementById('adminMessengerContacts');
    if (!el) return;

    var R = rbac();
    if (R && typeof R.renderMessengerContactList === 'function') {
      R.renderMessengerContactList(el, list, {
        activeUserId: state.activeContact ? state.activeContact.user_id : null,
        itemClass: 'admin-msgr-contact',
        emptyMessage: 'No contacts available for your role.',
        onSelect: function (contact) {
          selectContact(contact);
        }
      });
      return;
    }

    if (!list.length) {
      el.innerHTML = '<p class="admin-msgr-empty">No contacts available for your role.</p>';
      return;
    }
  }

  function renderMessages(messages) {
    var body = document.getElementById('adminMessengerMessages');
    if (!body) return;

    if (!state.activeContact) {
      body.innerHTML = '<p class="admin-msgr-placeholder">Select a contact to start chatting.</p>';
      return;
    }

    if (!messages || !messages.length) {
      body.innerHTML = '<p class="admin-msgr-placeholder">No messages yet. Say hello!</p>';
      return;
    }

    var viewerId = state.currentUser ? (state.currentUser.user_id || state.currentUser.id) : null;

    body.innerHTML = messages.map(function (m) {
      var mine = viewerId && parseInt(m.sender_user_id, 10) === parseInt(viewerId, 10);
      return (
        '<div class="admin-msgr-bubble' + (mine ? ' is-out' : ' is-in') + '">' +
        '<div class="admin-msgr-bubble-meta">' + esc(mine ? 'You' : (m.sender_name || 'User')) + '</div>' +
        '<div>' + esc(m.message_text || '') + '</div>' +
        '</div>'
      );
    }).join('');

    body.scrollTop = body.scrollHeight;
  }

  function setChatHeader(contact) {
    var title = document.getElementById('adminMessengerChatTitle');
    var sub = document.getElementById('adminMessengerChatSubtitle');
    if (!contact) {
      if (title) title.textContent = 'Messenger';
      if (sub) sub.textContent = 'Select a contact';
      return;
    }
    if (title) title.textContent = contact.full_name;
    if (sub) sub.textContent = contact.role + ' · ' + contact.company;
  }

  function filterContacts(query) {
    applyContactFilters(query);
  }

  /* ---------- Data ---------- */

  function loadSessionUser() {
    return fetch('../../Api/Login/check_session.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.success) {
          state.currentUser = {
            user_id: parseInt(d.user_id, 10) || null,
            id: parseInt(d.user_id, 10) || null,
            role: d.role || 'Admin',
            full_name: d.full_name || ''
          };
        }
      })
      .catch(function () {});
  }

  function loadContacts() {
    return Promise.resolve()
      .then(function () {
        if (!state.currentUser) return loadSessionUser();
      })
      .then(function () {
        return fetch(CONTACTS_API, { credentials: 'same-origin' });
      })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) {
          throw new Error((d && d.message) ? d.message : 'contacts');
        }

        if (d.viewer) {
          state.currentUser = {
            user_id: d.viewer.user_id,
            id: d.viewer.user_id,
            role: d.viewer.role
          };
        }

        state.allUsers = d.all_users || d.contacts || [];

        var R = rbac();
        if (R && state.currentUser) {
          state.contacts = R.filterAllowedContacts(state.currentUser, state.allUsers);
        } else {
          state.contacts = d.contacts || [];
        }

        var searchEl = document.getElementById('adminMessengerSearch');
        applyContactFilters(searchEl ? searchEl.value : '');

        if (!state.contacts.length && d.meta) {
          console.info('Messenger: ' + (d.meta.allowed_contacts || 0) + ' allowed of ' + (d.meta.total_users || 0) + ' users');
        }
      })
      .catch(function (err) {
        console.warn('Messenger contacts:', err);
        state.allUsers = [];
        state.contacts = [];
        var el = document.getElementById('adminMessengerContacts');
        if (el) {
          el.innerHTML = '<p class="admin-msgr-empty">Could not load contacts. Refresh the page or sign in again.</p>';
        }
      });
  }

  function loadThreadMessages(threadId) {
    if (!threadId) {
      renderMessages([]);
      return Promise.resolve();
    }
    return fetch(MESSAGES_API + '?thread_id=' + threadId, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) throw new Error('messages');
        renderMessages(d.messages || []);
      })
      .catch(function () {
        renderMessages([]);
      });
  }

  function setComposerEnabled(enabled) {
    var input = document.getElementById('adminMessengerInput');
    var sendBtn = document.getElementById('adminMessengerSend');
    if (input) input.disabled = !enabled;
    if (sendBtn) sendBtn.disabled = !enabled;
  }

  function selectContact(contact) {
    var R = rbac();
    if (R && state.currentUser) {
      var allowed = R.filterAllowedContacts(state.currentUser, [contact]);
      if (!allowed.length) {
        console.warn('Messenger RBAC: contact not permitted');
        return Promise.resolve();
      }
    }

    state.activeContact = contact;
    state.activeThreadId = null;
    setChatHeader(contact);
    setComposerEnabled(true);
        var searchOnSelect = document.getElementById('adminMessengerSearch');
        applyContactFilters(searchOnSelect ? searchOnSelect.value : '');
    renderMessages([]);

    return fetch(THREAD_API + '?user_id=' + contact.user_id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) throw new Error('thread');
        state.activeThreadId = d.thread_id;
        return loadThreadMessages(state.activeThreadId);
      })
      .catch(function () {
        renderMessages([]);
      });
  }

  function sendMessage() {
    var input = document.getElementById('adminMessengerInput');
    if (!input || !state.activeContact) return;

    var text = (input.value || '').trim();
    if (!text) return;

    var payload = { message_text: text };
    if (state.activeThreadId) {
      payload.thread_id = state.activeThreadId;
    }

    input.value = '';
    input.disabled = true;

    fetch(SEND_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) throw new Error((d && d.message) || 'Send failed');
        if (d.thread_id) state.activeThreadId = d.thread_id;

        if (state.ws && state.ws.readyState === WebSocket.OPEN) {
          state.ws.send(JSON.stringify({
            type: 'chat_message',
            thread_id: state.activeThreadId,
            message_text: text,
            recipient_user_id: state.activeContact.user_id
          }));
        }

        return loadThreadMessages(state.activeThreadId);
      })
      .catch(function (err) {
        console.warn('Messenger send:', err);
      })
      .finally(function () {
        input.disabled = false;
        input.focus();
      });
  }

  /* ---------- WebSocket ---------- */

  function connectWebSocket() {
    if (state.ws && (state.ws.readyState === WebSocket.OPEN || state.ws.readyState === WebSocket.CONNECTING)) {
      return;
    }

    try {
      state.ws = new WebSocket(WS_URL);
    } catch (e) {
      console.warn('WebSocket unavailable:', e);
      return;
    }

    state.ws.onopen = function () {
      state.wsConnected = true;
      var role = state.currentUser ? state.currentUser.role : 'Admin';
      state.ws.send(JSON.stringify({
        type: 'auth',
        role: role,
        user_id: state.currentUser ? state.currentUser.user_id : null,
        channel: 'admin_dashboard'
      }));
    };

    state.ws.onmessage = function (evt) {
      var data;
      try {
        data = JSON.parse(evt.data);
      } catch (e) {
        return;
      }
      handleWebSocketMessage(data);
    };

    state.ws.onclose = function () {
      state.wsConnected = false;
      if (state.reconnectTimer) clearTimeout(state.reconnectTimer);
      state.reconnectTimer = setTimeout(function () {
        if (state.open) connectWebSocket();
      }, 5000);
    };

    state.ws.onerror = function () {
      state.wsConnected = false;
    };
  }

  function handleWebSocketMessage(data) {
    if (!data || !data.type) return;

    if (data.type === 'chat_message') {
      var threadId = parseInt(data.thread_id, 10);
      if (state.activeThreadId === threadId) {
        appendIncomingMessage(data);
      }
      return;
    }

    if (data.type === 'system_notification') {
      var event = data.event || '';
      var roles = data.roles || ALLOWED_SA_ROLES;
      if (event === 'service_agreement_uploaded' && roles.indexOf('Admin') !== -1) {
        onSystemNotification(data.payload || data);
      }
    }
  }

  function appendIncomingMessage(data) {
    var body = document.getElementById('adminMessengerMessages');
    if (!body) return;

    var placeholder = body.querySelector('.admin-msgr-placeholder');
    if (placeholder) placeholder.remove();

    var viewerId = state.currentUser ? state.currentUser.user_id : null;
    var mine = viewerId && parseInt(data.sender_user_id, 10) === parseInt(viewerId, 10);
    if (mine) return;

    var div = document.createElement('div');
    div.className = 'admin-msgr-bubble is-in';
    div.innerHTML =
      '<div class="admin-msgr-bubble-meta">' + esc(data.sender_name || 'User') + '</div>' +
      '<div>' + esc(data.message_text || '') + '</div>';
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
  }

  function onSystemNotification(payload) {
    if (!payload) return;
    var sub = document.getElementById('adminMessengerChatSubtitle');
    if (sub && payload.message) {
      sub.textContent = payload.message;
      sub.classList.add('text-warning');
      setTimeout(function () { sub.classList.remove('text-warning'); }, 8000);
    }
    if (global.AdminNotifications) {
      AdminNotifications.handleRealtime(payload);
    }
  }

  function broadcastSystemEvent(wsPayload) {
    if (!wsPayload) return;
    if (state.ws && state.ws.readyState === WebSocket.OPEN) {
      state.ws.send(JSON.stringify(wsPayload));
    }
  }

  function init() {
    var fab = document.getElementById('adminMessengerFab');
    var closeBtn = document.getElementById('adminMessengerClose');
    var sendBtn = document.getElementById('adminMessengerSend');
    var input = document.getElementById('adminMessengerInput');
    var search = document.getElementById('adminMessengerSearch');

    if (fab) {
      fab.addEventListener('click', function () {
        setOpen(!state.open);
      });
    }
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        setOpen(false);
      });
    }
    if (sendBtn) {
      sendBtn.addEventListener('click', sendMessage);
    }
    if (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendMessage();
        }
      });
    }
    if (search) {
      search.addEventListener('input', function () {
        filterContacts(search.value);
      });
    }

    loadSessionUser();
  }

  global.AdminMessenger = {
    init: init,
    connect: connectWebSocket,
    sendMessage: sendMessage,
    filterContacts: filterContacts,
    loadContacts: loadContacts,
    onSystemNotification: onSystemNotification,
    broadcastSystemEvent: broadcastSystemEvent,
    handleWebSocketMessage: handleWebSocketMessage,
    getState: function () { return state; }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);
