/**
 * Example WebSocket hub (Node.js) — run separately: node websocket/admin_ws_hub_example.js
 *
 * Routes SERVICE_AGREEMENT_UPLOADED ONLY to Admin, HR, TeamLead sockets.
 * Install: npm install ws
 */
const WebSocket = require('ws');

const PORT = 8080;
const ALLOWED_SA_ROLES = new Set(['Admin', 'HR', 'TeamLead']);
const SA_EVENTS = new Set(['SERVICE_AGREEMENT_UPLOADED', 'service_agreement_uploaded']);

const wss = new WebSocket.Server({ port: PORT });
const clients = new Set();

wss.on('connection', function (socket) {
  socket.userRole = null;
  socket.userId = null;
  clients.add(socket);

  socket.on('message', function (raw) {
    var data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      return;
    }

    if (data.type === 'auth') {
      socket.userRole = data.role || null;
      socket.userId = data.user_id != null ? parseInt(data.user_id, 10) : null;
      return;
    }

    if (data.type === 'system_notification' && SA_EVENTS.has(String(data.event || ''))) {
      broadcastServiceAgreement(data);
      return;
    }

    if (data.type === 'chat_message') {
      broadcastChat(data, socket);
    }
  });

  socket.on('close', function () {
    clients.delete(socket);
  });
});

/**
 * Role-based routing pseudo-code:
 *
 * foreach client in connectedClients:
 *   if client.role NOT IN ['Admin','HR','TeamLead']: continue
 *   if envelope.recipient_payloads:
 *     match client.userId to recipient.user_id → send personalized payload
 *   else:
 *     if client.role IN envelope.roles → send shared payload
 */
function broadcastServiceAgreement(envelope) {
  var roles = envelope.roles || ['Admin', 'HR', 'TeamLead'];
  var recipients = envelope.recipient_payloads || [];

  if (recipients.length) {
    recipients.forEach(function (recipient) {
      var targetUserId = parseInt(recipient.user_id, 10);
      var payload = recipient.payload || envelope.payload || {};
      if (recipient.notification_id) {
        payload.notification_id = recipient.notification_id;
        payload.id = recipient.notification_id;
      }

      clients.forEach(function (client) {
        if (client.readyState !== WebSocket.OPEN) return;
        if (!client.userRole || !roles.includes(client.userRole)) return;
        if (!ALLOWED_SA_ROLES.has(client.userRole)) return;
        if (client.userId !== targetUserId) return;

        client.send(JSON.stringify({
          type: 'system_notification',
          event: 'SERVICE_AGREEMENT_UPLOADED',
          roles: roles,
          payload: payload
        }));
      });
    });
    return;
  }

  var sharedPayload = envelope.payload || {};
  var msg = JSON.stringify({
    type: 'system_notification',
    event: 'SERVICE_AGREEMENT_UPLOADED',
    roles: roles,
    payload: sharedPayload
  });

  clients.forEach(function (client) {
    if (client.readyState !== WebSocket.OPEN) return;
    if (!client.userRole || !roles.includes(client.userRole)) return;
    if (!ALLOWED_SA_ROLES.has(client.userRole)) return;
    client.send(msg);
  });
}

function broadcastChat(data, sender) {
  var msg = JSON.stringify({
    type: 'chat_message',
    thread_id: data.thread_id,
    sender_user_id: sender.userId,
    message_text: data.message_text,
    sender_name: 'User'
  });
  clients.forEach(function (client) {
    if (client !== sender && client.readyState === WebSocket.OPEN) {
      client.send(msg);
    }
  });
}

console.log('Admin WS hub listening on ws://127.0.0.1:' + PORT);
