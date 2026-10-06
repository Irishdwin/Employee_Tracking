/**
 * Shared messenger widget (all roles) — RBAC contacts, live search, real-time chat.
 *
 * Backend hooks:
 *   SEND_API      — POST JSON { thread_id, message_text }
 *   MESSAGES_API  — GET ?thread_id=
 *   THREAD_API    — GET ?user_id= (1:1 thread)
 *   WS_URL        — window.OW_WS_URL or ws://127.0.0.1:8080
 */
(function (global) {
  'use strict';

  var CONTACTS_API = '../../Api/Common/messenger_contacts.php';
  var THREAD_API = '../../Api/Common/messenger_thread.php';
  var MESSAGES_API = '../../Api/Common/messages_messages.php';
  var SEND_API = '../../Api/Common/messages_send.php';
  var UNREAD_API = '../../Api/Common/get_unread_counts.php';
  var MARK_READ_API = '../../Api/Common/mark_messages_read.php';
  var COUNTERS_API = '../../Api/Common/realtime_counters.php';
  var WS_URL = global.OW_WS_URL || global.ADMIN_WS_URL || 'ws://127.0.0.1:8080';
  var WS_ENABLED = false; // Persisted, authorized API polling is the transport. Browser-claimed WebSocket identities are not trusted.
  var WS_MAX_RETRIES = 3;

  var E = {
    fab: ['owMessengerFab'],
    panel: ['owMessengerPanel'],
    close: ['owMessengerClose'],
    search: ['owMessengerSearch'],
    contacts: ['owMessengerContacts'],
    chatTitle: ['owMessengerChatTitle'],
    chatSub: ['owMessengerChatSubtitle'],
    messages: ['owMessengerMessages'],
    input: ['owMessengerInput'],
    send: ['owMessengerSend'],
    composeForm: ['owMessengerComposeForm']
  };

  function gid(key) {
    var ids = E[key] || [key];
    for (var i = 0; i < ids.length; i++) {
      var el = document.getElementById(ids[i]);
      if (el) return el;
    }
    return null;
  }

  /** Global unread state: total + per-sender (contact) counts. */
  var unreadData = { total: 0, byContact: {} };
  var unreadRevision = 0;
  var latestUnread = {};

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
    wsRetryCount: 0,
    wsDisabled: false,
    reconnectTimer: null,
    sending: false,
    csrf: null,
    history: [],
    moreHistory: false,
    messagesUnread: 0,
    badgePollTimer: null
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

  function viewerId() {
    if (!state.currentUser) return null;
    return parseInt(state.currentUser.user_id || state.currentUser.id, 10) || null;
  }

  function formatBadgeCount(count) {
    var n = parseInt(count, 10) || 0;
    if (n <= 0) return { n: 0, label: '' };
    return { n: n, label: String(n) };
  }

  function getContactUnreadCount(userId) {
    return parseInt(unreadData.byContact[String(userId)], 10) || 0;
  }

  function applyUnreadPayload(payload) {
    if (!payload) return;
    unreadData.total = parseInt(payload.total, 10) || 0;
    latestUnread = payload.latest_unread || {};
    Object.keys(unreadData.byContact).forEach(function (k) {
      delete unreadData.byContact[k];
    });
    var breakdown = payload.by_contact || payload.byContact || payload.sender_id || {};
    Object.keys(breakdown).forEach(function (key) {
      var n = parseInt(breakdown[key], 10) || 0;
      if (n > 0) {
        unreadData.byContact[String(key)] = n;
      }
    });
    syncUnreadStateToContacts();
    updateMessengerFabBadge(unreadData.total);
  }

  function syncUnreadStateToContacts() {
    function walk(arr) {
      (arr || []).forEach(function (c) {
        var key = String(c.user_id != null ? c.user_id : c.id);
        var n = unreadData.byContact[key] || 0;
        c.unread_count = n;
        c.latest_unread = latestUnread[key] || null;
      });
    }
    walk(state.allUsers);
    walk(state.contacts);
  }

  function fetchUnreadCounts() {
    var revision = unreadRevision;
    return fetch(UNREAD_API, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json().catch(function () { return null; }); })
      .then(function (d) {
        if (d && d.success && revision === unreadRevision) {
          applyUnreadPayload(d);
          if (gid('contacts') && state.filteredContacts.length) {
            onSearchInput();
          }
        }
        return d;
      })
      .catch(function (err) {
        console.warn('fetchUnreadCounts:', err);
      });
  }

  function markMessagesReadOnServer(senderId) {
    var sid = parseInt(senderId, 10);
    if (!sid) return Promise.resolve();
    return fetch(MARK_READ_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ sender_id: sid })
    })
      .then(function (r) { return r.json().catch(function () { return null; }); })
      .then(function (d) {
        if (d && d.success) {
          applyUnreadPayload(d);
        }
        return d;
      })
      .catch(function (err) {
        console.warn('markMessagesReadOnServer:', err);
        return fetchUnreadCounts();
      });
  }

  function markContactReadOptimistic(senderId) {
    var key = String(senderId);
    var prev = getContactUnreadCount(senderId);
    if (prev <= 0) return 0;
    unreadData.total = Math.max(0, unreadData.total - prev);
    delete unreadData.byContact[key];
    syncUnreadStateToContacts();
    updateMessengerFabBadge(unreadData.total);
    onSearchInput();
    return prev;
  }

  function isChatOpenWithSender(senderId) {
    if (!state.activeContact || senderId == null) return false;
    var activeId = parseInt(
      state.activeContact.user_id != null ? state.activeContact.user_id : state.activeContact.id,
      10
    );
    return activeId === parseInt(senderId, 10);
  }

  function incrementUnreadForSender(senderId, threadId) {
    var key = String(senderId);
    unreadData.byContact[key] = getContactUnreadCount(senderId) + 1;
    unreadData.total += 1;
    patchContactUnread(senderId, function (c) {
      c.unread_count = unreadData.byContact[key];
      if (threadId) c.thread_id = threadId;
    });
    syncUnreadStateToContacts();
    updateMessengerFabBadge(unreadData.total);
    onSearchInput();
  }

  function ensureMessengerFabBadge() {
    var fab = gid('fab');
    if (!fab) return null;
    var badge = fab.querySelector('[data-global-message-badge]');
    if (!badge) {
      badge = document.createElement('span');
      badge.className =
        'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-2 border-light d-none';
      badge.setAttribute('data-global-message-badge', '');
      badge.setAttribute('aria-hidden', 'true');
      badge.style.fontSize = '0.65rem';
      fab.appendChild(badge);
    }
    return badge;
  }

  function updateMessengerFabBadge(count) {
    var formatted = formatBadgeCount(count);
    state.messagesUnread = formatted.n;
    // Messenger owns this badge; dashboard notification renderers must not
    // change its message count or truncate it to a notification-style label.
    var badge = ensureMessengerFabBadge();
    var fab = gid('fab');
    if (!badge || !fab) return;
    if (formatted.n <= 0) {
      badge.textContent = '';
      badge.classList.add('d-none');
    } else {
      badge.textContent = formatted.label;
      badge.classList.remove('d-none');
    }
    fab.setAttribute('aria-label', formatted.n > 0
      ? 'Open messenger, ' + formatted.label + ' unread messages'
      : 'Open messenger');
  }

  function patchContactUnread(userId, patchFn) {
    var uid = parseInt(userId, 10);
    if (!uid) return;
    function walk(arr) {
      (arr || []).forEach(function (c) {
        var cid = parseInt(c.user_id != null ? c.user_id : c.id, 10);
        if (cid === uid) patchFn(c);
      });
    }
    walk(state.allUsers);
    walk(state.contacts);
  }

  function pollUnreadMessages() {
    return fetchUnreadCounts();
  }

  function startMessengerBadgePoll() {
    if (state.badgePollTimer) clearInterval(state.badgePollTimer);
    fetchUnreadCounts();
    var refreshing=false;
    state.badgePollTimer = setInterval(function(){
      if(refreshing)return;refreshing=true;
      var refresh=state.open?loadContacts().then(function(){return state.activeThreadId&&!state.sending?loadThreadMessages(state.activeThreadId):fetchUnreadCounts();}):fetchUnreadCounts();
      refresh.finally(function(){refreshing=false;});
    },5000);
  }

  /* ---------- Live search (RBAC list only, no API reload) ---------- */

  function onSearchInput() {
    var search = gid('search');
    applyContactFilters(search ? search.value : '');
  }

  function applyContactFilters(searchQuery) {
    var R = rbac();
    if (R && state.currentUser) {
      state.contacts = R.filterAllowedContacts(state.currentUser, state.allUsers);
      state.filteredContacts = R.filterContactsBySearch(state.contacts, searchQuery);
    } else {
      state.filteredContacts = state.contacts.slice();
    }
    state.filteredContacts.sort(function(a,b){
      var admin=state.currentUser&&state.currentUser.role==='HR'?((a.role==='Admin'?0:1)-(b.role==='Admin'?0:1)):0;
      return admin || Number(b.unread_count>0)-Number(a.unread_count>0) || String(b.latest_unread?.created_at||'').localeCompare(String(a.latest_unread?.created_at||'')) || (Number(b.latest_unread?.message_id||0)-Number(a.latest_unread?.message_id||0)) || String(a.full_name||'').localeCompare(String(b.full_name||'')) || Number(a.user_id)-Number(b.user_id);
    });
    renderContacts(state.filteredContacts);
  }

  function renderContacts(list) {
    var el = gid('contacts');
    if (!el) return;
    var R = rbac();
    if (R && typeof R.renderMessengerContactList === 'function') {
      R.renderMessengerContactList(el, list, {
        activeUserId: state.activeContact ? state.activeContact.user_id : null,
        itemClass: 'ow-msgr-contact messenger-contact',
        unreadData: unreadData,
        unreadMessageState: unreadData.byContact,
        emptyMessage: list && list.length === 0 && (gid('search') && gid('search').value.trim())
          ? 'No contacts match your search.'
          : (state.currentUser?.role==='Applicant'?'No HR contact is currently available.':'No authorized contacts found.'),
        onSelect: selectContact
      });
      return;
    }
    if (!list || !list.length) {
      el.innerHTML = '<p class="ow-msgr-empty">No contacts available for your role.</p>';
      return;
    }
    el.innerHTML = list.map(function (c) {
      return '<button type="button" class="ow-msgr-contact" data-user-id="' + c.user_id + '">' +
        esc(c.full_name) + '</button>';
    }).join('');
  }

  /* ---------- Chat DOM (optimistic + WebSocket) ---------- */

  function scrollChatToBottom() {
    var body = gid('messages');
    if (!body) return;
    body.scrollTop = body.scrollHeight;
  }

  function clearChatPlaceholder() {
    var body = gid('messages');
    if (!body) return;
    body.querySelectorAll('.ow-msgr-placeholder').forEach(function (n) { n.remove(); });
  }

  function createMessageBubble(opts) {
    opts = opts || {};
    var mine = !!opts.isOutgoing;
    var div = document.createElement('div');
    div.className = 'ow-msgr-bubble' + (mine ? ' is-out' : ' is-in');
    if (opts.pending) div.classList.add('is-pending');
    if (opts.failed) div.classList.add('is-failed');
    if (opts.tempId) div.setAttribute('data-temp-id', opts.tempId);
    if (opts.messageId) div.setAttribute('data-message-id', String(opts.messageId));
    var meta = opts.senderName || (mine ? 'You' : 'User');
    div.innerHTML =
      '<div class="ow-msgr-bubble-meta">' + esc(meta) + '</div>' +
      '<div class="ow-msgr-bubble-text">' + esc(opts.text || '') + '</div>' +
      (opts.failed ? '<div class="ow-msgr-bubble-meta text-danger">Failed to send</div>' : '');
    return div;
  }

  function appendMessageToChat(opts) {
    var body = gid('messages');
    if (!body || !state.activeContact) return null;
    clearChatPlaceholder();
    var bubble = createMessageBubble(opts);
    body.appendChild(bubble);
    scrollChatToBottom();
    return bubble;
  }

  function appendOutgoingMessage(text, tempId) {
    return appendMessageToChat({
      text: text,
      isOutgoing: true,
      senderName: 'You',
      tempId: tempId,
      pending: true
    });
  }

  function appendIncomingMessage(data) {
    var body = gid('messages');
    if (!body) return;
    var vid = viewerId();
    if (vid && data.sender_user_id != null && parseInt(data.sender_user_id, 10) === vid) {
      return;
    }
    if (data.message_id) {
      var dup = body.querySelector('[data-message-id="' + data.message_id + '"]');
      if (dup) return;
    }
    appendMessageToChat({
      text: data.message_text || '',
      isOutgoing: false,
      senderName: data.sender_name || 'User',
      messageId: data.message_id
    });
  }

  function markBubbleSent(tempId, messageId) {
    var body = gid('messages');
    if (!body || !tempId) return;
    var el = body.querySelector('[data-temp-id="' + tempId + '"]');
    if (!el) return;
    el.classList.remove('is-pending', 'is-failed');
    if (messageId) {
      el.setAttribute('data-message-id', String(messageId));
      el.removeAttribute('data-temp-id');
    }
  }

  function markBubbleFailed(tempId, message) {
    var body = gid('messages');
    if (!body || !tempId) return;
    var el = body.querySelector('[data-temp-id="' + tempId + '"]');
    if (!el) return;
    el.classList.remove('is-pending');
    el.classList.add('is-failed');
    var err = el.querySelector('.text-danger');
    if (!err) {
      var hint = document.createElement('div');
      hint.className = 'ow-msgr-bubble-meta text-danger';
      hint.textContent = 'Failed to send. ' + (message || 'Please try again.');
      el.appendChild(hint);
    }
  }

  function renderMessages(messages) {
    var body = gid('messages');
    if (!body) return;
    if (!state.activeContact) {
      body.innerHTML = '<p class="ow-msgr-placeholder">Select a contact to start chatting.</p>';
      return;
    }
    if (!messages || !messages.length) {
      body.innerHTML = '<p class="ow-msgr-placeholder">No messages yet. Say hello!</p>';
      return;
    }
    var vid = viewerId();
    body.innerHTML = messages.map(function (m) {
      var mine = vid && parseInt(m.sender_user_id, 10) === vid;
      return (
        '<div class="ow-msgr-bubble' + (mine ? ' is-out' : ' is-in') + '"' +
        (m.message_id ? ' data-message-id="' + m.message_id + '"' : '') + '>' +
        '<div class="ow-msgr-bubble-meta">' + esc(mine ? 'You' : (m.sender_name || 'User')) + '</div>' +
        '<div class="ow-msgr-bubble-text">' + esc(m.message_text || '') + '</div></div>'
      );
    }).join('');
    scrollChatToBottom();
  }

  function setChatHeader(contact) {
    var title = gid('chatTitle');
    var sub = gid('chatSub');
    if (!contact) {
      if (title) title.textContent = 'Messenger';
      if (sub) sub.textContent = 'Select a contact';
      return;
    }
    if (title) title.textContent = contact.full_name;
    if (sub) sub.textContent = (contact.is_online ? '● Online' : '○ Offline') + ' · ' + contact.role + ' · ' + (contact.company || contact.company_name || '');
  }

  /* ---------- Data loading ---------- */

  function setOpen(open) {
    state.open = open;
    var panel = gid('panel');
    var fab = gid('fab');
    if (panel) panel.classList.toggle('is-open', open);
    if (fab) fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) loadContacts().then(fetchUnreadCounts);
    if (open && shouldUseWebSocket() && !state.wsConnected) connectWebSocket();
  }

  function loadSessionUser() {
    return fetch('../../Api/Login/check_session.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.success) {
          state.currentUser = {
            user_id: parseInt(d.user_id, 10) || null,
            id: parseInt(d.user_id, 10) || null,
            role: d.role || '',
            full_name: d.full_name || ''
          };
        }
      })
      .catch(function () {});
  }

  function loadContacts() {
    return Promise.resolve()
      .then(function () { if (!state.currentUser) return loadSessionUser(); })
      .then(function () { return fetch(CONTACTS_API+'?q='+encodeURIComponent(gid('search')?.value||'')+'&role='+encodeURIComponent(document.getElementById('owMessengerRole')?.value||''), { credentials: 'same-origin',cache:'no-store' }); })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) throw new Error((d && d.message) || 'contacts');
        if (d.viewer) {
          state.currentUser = { user_id: d.viewer.user_id, id: d.viewer.user_id, role: d.viewer.role };
        }
        state.csrf=d.csrf_token;
        var roleSelect=document.getElementById('owMessengerRole');
        if(roleSelect){var selectedRole=roleSelect.value;roleSelect.innerHTML='<option value="">All contacts</option>'+(d.roles||[]).map(function(r){return '<option value="'+esc(r)+'">'+esc(r==='TeamLead'?'Team Lead':r)+'</option>';}).join('');roleSelect.value=selectedRole;}
        state.allUsers = d.contacts || [];
        if(state.activeContact){var refreshed=state.allUsers.find(function(c){return Number(c.user_id)===Number(state.activeContact.user_id);});if(refreshed){state.activeContact=refreshed;setChatHeader(refreshed);}}
        // Counts have their own request lifecycle; an older contacts response
        // must not restore a badge after history has marked messages read.
        syncUnreadStateToContacts();
        return Promise.resolve();
      })
      .then(function () {
        applyContactFilters(gid('search') ? gid('search').value : '');
      })
      .catch(function (err) {
        console.warn('Messenger contacts:', err);
        state.allUsers = [];
        state.contacts = [];
        var el = gid('contacts');
        if (el) el.innerHTML = '<p class="ow-msgr-empty">Could not load contacts. Refresh or sign in again.</p>';
      });
  }

  function loadThreadMessages(threadId,before) {
    if (!threadId) {
      renderMessages([]);
      return Promise.resolve();
    }
    return fetch(MESSAGES_API + '?thread_id=' + threadId + (before?'&before_message_id='+before:''), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) throw new Error('messages');
        if(state.activeThreadId!==threadId)return;
        var body=gid('messages'),oldHeight=body.scrollHeight,oldTop=body.scrollTop,atBottom=oldHeight-oldTop-body.clientHeight<60;
        var byId=new Map(state.history.map(function(m){return [m.message_id,m];}));(d.messages||[]).forEach(function(m){byId.set(m.message_id,m);});
        var previousOldest=state.history[0]?.message_id;state.history=Array.from(byId.values()).sort(function(a,b){return a.message_id-b.message_id;});
        if(before||!previousOldest)state.moreHistory=!!d.has_more;
        renderMessages(state.history);
        if(state.moreHistory&&state.history.length){var earlier=document.createElement('button');earlier.type='button';earlier.className='btn btn-outline-primary btn-sm mb-2';earlier.textContent='Load earlier messages';earlier.onclick=function(){earlier.disabled=true;loadThreadMessages(threadId,state.history[0].message_id);};body.prepend(earlier);}
        if(before)body.scrollTop=body.scrollHeight-oldHeight+oldTop;else if(!atBottom)body.scrollTop=oldTop;
        // Successful history loading advances the server's observed-message
        // cursor. Clear visible badges now, then reconcile any newer arrivals.
        unreadRevision++;
        if(d.read_confirmed&&d.unread){applyUnreadPayload(d.unread);onSearchInput();return;}
        return fetchUnreadCounts();
      })
      .catch(function () {var body=gid('messages');if(body)body.innerHTML='<p class="ow-msgr-placeholder">Unable to load this conversation. Refresh contacts or try again.</p>';setComposerEnabled(false);});
  }

  function ensureActiveThread() {
    if (state.activeThreadId) return Promise.resolve(state.activeThreadId);
    if (!state.activeContact) return Promise.reject(new Error('No contact'));
    return fetch(THREAD_API + '?user_id=' + state.activeContact.user_id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) throw new Error('thread');
        state.activeThreadId = d.thread_id;
        return state.activeThreadId;
      });
  }

  function setComposerEnabled(enabled) {
    var input = gid('input');
    var sendBtn = gid('send');
    if (input) input.disabled = !enabled;
    if (sendBtn) sendBtn.disabled = !enabled || state.sending;
  }

  function selectContact(contact) {
    var R = rbac();
    if (R && state.currentUser) {
      if (!R.filterAllowedContacts(state.currentUser, [contact]).length) return Promise.resolve();
    }
    state.history=[];state.moreHistory=false;
    state.activeContact = contact;
    state.activeThreadId = null;
    setChatHeader(contact);

    var senderId = contact.user_id != null ? contact.user_id : contact.id;
    // Mark read only after the history request succeeds and is displayed.

    setComposerEnabled(true);
    onSearchInput();
    renderMessages([]);
    return fetch(THREAD_API + '?user_id=' + contact.user_id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) throw new Error('thread');
        state.activeThreadId = d.thread_id;
        return loadThreadMessages(state.activeThreadId);
      })
      .catch(function () {var body=gid('messages');if(body)body.innerHTML='<p class="ow-msgr-placeholder">Unable to load this conversation. Refresh contacts or try again.</p>';setComposerEnabled(false);});
  }

  /* ---------- Send (no page reload, optimistic UI) ---------- */

  function sendViaWebSocket(text, threadId) {
    if (!state.ws || state.ws.readyState !== WebSocket.OPEN) return;
    if (!state.activeContact) return;
    try {
      state.ws.send(JSON.stringify({
        type: 'chat_message',
        thread_id: threadId,
        message_text: text,
        recipient_user_id: state.activeContact.user_id,
        sender_user_id: viewerId(),
        sender_name: state.currentUser ? state.currentUser.full_name : 'You'
      }));
    } catch (e) {
      console.warn('WebSocket send:', e);
    }
  }

  function persistMessage(text, threadId, requestId) {
    function refreshToken() {
      return fetch(CONTACTS_API, {credentials:'same-origin',cache:'no-store'})
        .then(function(r){return r.json();})
        .then(function(d){if(!d||!d.success||!d.csrf_token)throw new Error((d&&d.message)||'Please sign in again before sending a message.');state.csrf=d.csrf_token;});
    }
    function post() {return fetch(SEND_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ thread_id: threadId, message_text: text, csrf_token: state.csrf, request_id: requestId })
    }).then(function (r) { return r.json(); });}
    return (state.csrf?Promise.resolve():refreshToken()).then(post).then(function(d){
      // Refresh only a rejected CSRF token, never a recipient permission error.
      // Reuse the request ID so a retry cannot create a duplicate message.
      if(d&&d.code==='MESSENGER_CSRF_EXPIRED')return refreshToken().then(post);
      return d;
    });
  }

  function sendMessage(evt) {
    if (evt && typeof evt.preventDefault === 'function') {
      evt.preventDefault();
    }

    var input = gid('input');
    if (!input || !state.activeContact || state.sending) return;

    var text = (input.value || '').trim();
    if (!text) return;

    var tempId = 'tmp-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
    state.sending = true;
    setComposerEnabled(true);

    appendOutgoingMessage(text, tempId);
    input.value = '';

    ensureActiveThread()
      .then(function (threadId) {
        return persistMessage(text, threadId, tempId).then(function (d) {
          return { d: d, threadId: threadId };
        });
      })
      .then(function (result) {
        var d = result.d;
        var threadId = result.threadId;
        if (!d || !d.success) throw new Error((d && d.message) || 'Send failed');
        if (d.thread_id) state.activeThreadId = d.thread_id;
        markBubbleSent(tempId, d.message_id);
        sendViaWebSocket(text, state.activeThreadId || threadId);
      })
      .catch(function (err) {
        console.warn('Messenger send:', err);
        markBubbleFailed(tempId, err instanceof TypeError ? 'Check your connection and try again.' : err.message);
      })
      .finally(function () {
        state.sending = false;
        setComposerEnabled(!!state.activeContact);
        if (input) input.focus();
      });
  }

  function shouldUseWebSocket() {
    return WS_ENABLED && !state.wsDisabled;
  }

  /* ---------- WebSocket receive ---------- */

  function connectWebSocket() {
    if (!shouldUseWebSocket()) return;
    if (state.ws && (state.ws.readyState === WebSocket.OPEN || state.ws.readyState === WebSocket.CONNECTING)) {
      return;
    }
    try {
      state.ws = new WebSocket(WS_URL);
    } catch (e) {
      state.wsDisabled = true;
      return;
    }

    state.ws.onopen = function () {
      state.wsConnected = true;
      state.wsRetryCount = 0;
      try {
        state.ws.send(JSON.stringify({
          type: 'auth',
          role: state.currentUser ? state.currentUser.role : '',
          user_id: viewerId(),
          channel: 'dashboard'
        }));
      } catch (e) {}
    };

    state.ws.onmessage = function (evt) {
      try {
        handleWebSocketMessage(JSON.parse(evt.data));
      } catch (e) {
        console.warn('WebSocket message parse error:', e);
      }
    };

    state.ws.onclose = function () {
      state.wsConnected = false;
      state.wsRetryCount += 1;
      if (state.reconnectTimer) clearTimeout(state.reconnectTimer);
      if (state.wsRetryCount >= WS_MAX_RETRIES) {
        state.wsDisabled = true;
        return;
      }
      if (state.open && shouldUseWebSocket()) {
        state.reconnectTimer = setTimeout(function () {
          if (state.open) connectWebSocket();
        }, 5000);
      }
    };

    state.ws.onerror = function () {
      state.wsConnected = false;
    };
  }

  function handleWebSocketMessage(data) {
    if (!data || !data.type) return;

    if (data.type === 'chat_message') {
      handleIncomingChatMessage(data);
      return;
    }

    if (data.type === 'system_notification') {
      if (global.OwSystemEvents && typeof OwSystemEvents.handleWebSocketEnvelope === 'function') {
        OwSystemEvents.handleWebSocketEnvelope(data);
      } else if (global.OwNotifications) {
        OwNotifications.handleRealtime(data.payload || data);
      }
      onSystemNotification(data.payload || data);
      return;
    }
  }

  function handleIncomingChatMessage(data) {
    var threadId = parseInt(data.thread_id, 10);
    var senderId = data.sender_user_id != null ? parseInt(data.sender_user_id, 10) : null;
    var vid = viewerId();
    if (!senderId || !vid || senderId === vid) {
      return;
    }

    var chatIsOpen = isChatOpenWithSender(senderId) ||
      (state.activeThreadId && threadId === state.activeThreadId);

    if (chatIsOpen) {
      appendIncomingMessage(data);
      markMessagesReadOnServer(senderId);
      return;
    }

    incrementUnreadForSender(senderId, threadId);
  }

  function onSystemNotification(payload) {
    if (!payload) return;
    var sub = gid('chatSub');
    if (sub && payload.message) {
      sub.textContent = payload.message;
      sub.classList.add('text-warning');
      setTimeout(function () { sub.classList.remove('text-warning'); }, 8000);
    }
  }

  function broadcastSystemEvent(wsPayload) {
    if (wsPayload && state.ws && state.ws.readyState === WebSocket.OPEN) {
      state.ws.send(JSON.stringify(wsPayload));
    }
  }

  /* ---------- Init ---------- */

  function bindComposeForm() {
    var form = gid('composeForm');
    if (form && form.dataset.owMsgrBound !== '1') {
      form.dataset.owMsgrBound = '1';
      form.addEventListener('submit', sendMessage);
    }
    var footer = document.querySelector('.ow-msgr-footer');
    if (!form && footer && footer.dataset.owMsgrBound !== '1') {
      footer.dataset.owMsgrBound = '1';
      footer.addEventListener('submit', function (e) {
        if (e.target && e.target.tagName === 'FORM') return;
      });
    }
  }

  function init() {
    if (!gid('fab')) return;

    var fab = gid('fab');
    var closeBtn = gid('close');
    var sendBtn = gid('send');
    var input = gid('input');
    var search = gid('search');

    if (fab) fab.addEventListener('click', function () { setOpen(!state.open); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    if (sendBtn) sendBtn.addEventListener('click', function (e) { sendMessage(e); });
    if (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendMessage(e);
        }
      });
    }
    if (search) {
      var timer;search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(loadContacts,250);});
      var roleSelect=document.getElementById('owMessengerRole');
      if(!roleSelect){roleSelect=document.createElement('select');roleSelect.id='owMessengerRole';roleSelect.className='form-select form-select-sm mt-2';roleSelect.setAttribute('aria-label','Filter contacts by role');roleSelect.innerHTML='<option value="">All contacts</option>';search.parentNode.appendChild(roleSelect);}
      roleSelect.addEventListener('change',loadContacts);
      search.addEventListener('input', onSearchInput);
      search.addEventListener('search', onSearchInput);
    }

    bindComposeForm();
    ensureMessengerFabBadge();
    loadSessionUser().then(function () {
      if(!viewerId()){if(gid('fab'))gid('fab').style.display='none';return;}
      fetchUnreadCounts();
      startMessengerBadgePoll();
    });
  }

  global.unreadData = unreadData;
  global.unreadMessageState = unreadData.byContact;
  global.fetchUnreadCounts = fetchUnreadCounts;

  global.OwMessenger = {
    init: init,
    open: function(){setOpen(true);},
    openContact: function(id){setOpen(true);return loadContacts().then(function(){var c=state.contacts.find(function(c){return Number(c.user_id)===Number(id);});if(c)return selectContact(c);});},
    loadContacts: loadContacts,
    fetchUnreadCounts: fetchUnreadCounts,
    pollUnreadMessages: pollUnreadMessages,
    updateMessengerFabBadge: updateMessengerFabBadge,
    updateGlobalMessageBadge: updateMessengerFabBadge,
    getUnreadData: function () { return unreadData; },
    getUnreadMessageState: function () { return unreadData.byContact; },
    markContactReadOptimistic: markContactReadOptimistic,
    markMessagesReadOnServer: markMessagesReadOnServer,
    handleIncomingChatMessage: handleIncomingChatMessage,
    incrementUnreadForSender: incrementUnreadForSender,
    sendMessage: sendMessage,
    onSearchInput: onSearchInput,
    applyContactFilters: applyContactFilters,
    appendIncomingMessage: appendIncomingMessage,
    connectWebSocket: connectWebSocket,
    handleWebSocketMessage: handleWebSocketMessage,
    onSystemNotification: onSystemNotification,
    broadcastSystemEvent: broadcastSystemEvent,
    getState: function () { return state; }
  };
  global.AdminMessenger = global.OwMessenger;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);
