/**
 * Global system events (all dashboards) — Service Agreement upload alerts.
 *
 * WebSocket sample envelope (SERVICE_AGREEMENT_UPLOADED):
 * {
 *   "type": "system_notification",
 *   "event": "SERVICE_AGREEMENT_UPLOADED",
 *   "roles": ["Admin", "HR", "TeamLead"],
 *   "payload": {
 *     "id": 42,
 *     "notification_id": 42,
 *     "title": "New Active Service Agreement",
 *     "message": "Agreement SA-2026-001 is now active. Contract duration: 2026-01-01 to 2026-12-31.",
 *     "document_name": "SA-2026-001",
 *     "start_date": "2026-01-01",
 *     "expiration_date": "2026-12-31",
 *     "service_agreement_id": 7,
 *     "type": "service_agreement_uploaded",
 *     "from": "System",
 *     "is_read": false
 *   }
 * }
 */
(function (global) {
  'use strict';

  var SA_EVENT = 'SERVICE_AGREEMENT_UPLOADED';
  var SA_LEGACY_EVENT = 'service_agreement_uploaded';
  var SA_ALLOWED_ROLES = ['Admin', 'HR', 'TeamLead'];
  var SESSION_API = '../../Api/Login/check_session.php';

  var viewer = { user_id: null, role: '' };

  function normalizeEventName(name) {
    return String(name || '').toUpperCase().replace(/-/g, '_');
  }

  function isServiceAgreementEvent(eventName) {
    var n = normalizeEventName(eventName);
    return n === SA_EVENT || n === normalizeEventName(SA_LEGACY_EVENT);
  }

  function roleAllowed(role) {
    return SA_ALLOWED_ROLES.indexOf(String(role || '').trim()) !== -1;
  }

  function loadViewerSession() {
    return fetch(SESSION_API, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.success) {
          viewer.user_id = parseInt(d.user_id, 10) || null;
          viewer.role = d.role || '';
          global.OW_VIEWER_ROLE = viewer.role;
          global.OW_VIEWER_USER_ID = viewer.user_id;
        }
        return viewer;
      })
      .catch(function () { return viewer; });
  }

  function buildNotificationFromPayload(payload) {
    payload = payload || {};
    var id = payload.id || payload.notification_id || null;
    return {
      id: id,
      type: payload.type || SA_LEGACY_EVENT,
      title: payload.title || 'New Active Service Agreement',
      message: payload.message || '',
      from: payload.from || 'System',
      is_read: !!payload.is_read,
      related_module: 'service_agreement',
      related_record: payload.service_agreement_id || null,
      document_name: payload.document_name || '',
      start_date: payload.start_date || '',
      expiration_date: payload.expiration_date || ''
    };
  }

  function ensureViewerLoaded() {
    if (viewer.role) {
      return Promise.resolve(viewer);
    }
    return loadViewerSession();
  }

  /**
   * Handle SERVICE_AGREEMENT_UPLOADED for the logged-in viewer.
   * @param {object} envelope - full WebSocket message or websocket_broadcast from API
   */
  function handleServiceAgreementUploaded(envelope) {
    envelope = envelope || {};
    if (!isServiceAgreementEvent(envelope.event)) {
      return false;
    }

    var payload = envelope.payload || envelope;
    var roles = envelope.roles || SA_ALLOWED_ROLES;

    if (payload.user_id != null && viewer.user_id != null &&
        parseInt(payload.user_id, 10) !== parseInt(viewer.user_id, 10)) {
      return false;
    }

    if (!roleAllowed(viewer.role) || roles.indexOf(viewer.role) === -1) {
      return false;
    }

    var notification = buildNotificationFromPayload(payload);

    if (global.OwNotifications) {
      if (typeof OwNotifications.prependNotification === 'function') {
        OwNotifications.prependNotification(notification);
      } else if (typeof OwNotifications.handleRealtime === 'function') {
        OwNotifications.handleRealtime(payload);
      }
    }

    return true;
  }

  /**
   * Route any system_notification WebSocket frame.
   */
  function handleWebSocketEnvelope(data) {
    if (!data || data.type !== 'system_notification') {
      return Promise.resolve(false);
    }

    return ensureViewerLoaded().then(function () {
      if (isServiceAgreementEvent(data.event)) {
        return handleServiceAgreementUploaded(data);
      }

      if (global.OwNotifications && typeof OwNotifications.handleRealtime === 'function') {
        OwNotifications.handleRealtime(data.payload || data);
        return true;
      }

      return false;
    });
  }

  /**
   * Relay broadcast envelope to the WebSocket hub (after upload on Admin client).
   */
  function relayBroadcast(envelope) {
    if (!envelope) return;
    var relay = global.OwMessenger || global.AdminMessenger;
    if (relay && typeof relay.broadcastSystemEvent === 'function') {
      relay.broadcastSystemEvent(envelope);
    }
  }

  /**
   * Client-side trigger (legacy / manual) after upload form.
   */
  function triggerServiceAgreementNotification(documentName, startDate, expirationDate, options) {
    options = options || {};
    var body = {
      document_name: documentName,
      start_date: startDate,
      expiration_date: expirationDate || '',
      company_name: options.company_name || '',
      service_agreement_id: options.service_agreement_id || 0
    };

    return fetch('../../Api/Admin/service_agreement_notify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body)
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) {
          throw new Error((data && data.message) || 'Notification request failed');
        }
        relayBroadcast(data.websocket_broadcast);
        handleServiceAgreementUploaded(data.websocket_broadcast);
        if (global.OwNotifications && typeof OwNotifications.load === 'function') {
          OwNotifications.load();
        }
        return data;
      });
  }

  function afterUploadResponse(apiResponse) {
    if (!apiResponse) return;
    if (apiResponse.websocket_broadcast) {
      relayBroadcast(apiResponse.websocket_broadcast);
    }
    if (global.OwNotifications && typeof OwNotifications.load === 'function') {
      OwNotifications.load();
    }
  }

  function init() {
    loadViewerSession();
  }

  global.OwSystemEvents = {
    init: init,
    SA_EVENT: SA_EVENT,
    SA_ALLOWED_ROLES: SA_ALLOWED_ROLES,
    loadViewerSession: loadViewerSession,
    handleWebSocketEnvelope: handleWebSocketEnvelope,
    handleServiceAgreementUploaded: handleServiceAgreementUploaded,
    relayBroadcast: relayBroadcast,
    afterUploadResponse: afterUploadResponse,
    triggerServiceAgreementNotification: triggerServiceAgreementNotification,
    isServiceAgreementEvent: isServiceAgreementEvent
  };

  global.triggerServiceAgreementNotification = function (expirationDate, options) {
    options = options || {};
    var docName = options.document_name || options.contract_number || 'Service Agreement';
    var start = options.start_date || options.contract_start_date || '';
    return triggerServiceAgreementNotification(docName, start, expirationDate, options);
  };

  global.AdminSystemEvents = global.OwSystemEvents;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);
