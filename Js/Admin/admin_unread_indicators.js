/**
 * Admin dashboard — global unread badges (Bootstrap 5 utilities).
 * Load before ow_notifications.js and ow_messenger.js.
 */
(function (global) {
  'use strict';

  var NOTIF_BADGE_IDS = ['globalNotifBadge'];
  var MSG_BADGE_IDS = ['globalMessageBadge'];

  function formatBadgeCount(count) {
    var n = parseInt(count, 10) || 0;
    if (n <= 0) return { n: 0, label: '' };
    if (n > 99) return { n: n, label: '99+' };
    return { n: n, label: String(n) };
  }

  function queryNotifBadges() {
    var nodes = [];
    NOTIF_BADGE_IDS.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) nodes.push(el);
    });
    var bell = document.getElementById('owNotifBellBtn');
    if (bell) {
      bell.querySelectorAll('[data-global-notif-badge]').forEach(function (b) { nodes.push(b); });
    }
    return nodes;
  }

  function queryMessageBadges() {
    var nodes = [];
    MSG_BADGE_IDS.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) nodes.push(el);
    });
    var fab = document.getElementById('owMessengerFab');
    if (fab) {
      fab.querySelectorAll('[data-global-message-badge]').forEach(function (b) { nodes.push(b); });
    }
    return nodes;
  }

  /**
   * @param {number} count — unread notification count
   */
  function updateGlobalNotifBadge(count) {
    var formatted = formatBadgeCount(count);
    queryNotifBadges().forEach(function (badge) {
      if (formatted.n <= 0) {
        badge.textContent = '';
        badge.classList.add('d-none');
      } else {
        badge.textContent = formatted.label;
        badge.classList.remove('d-none');
      }
    });
    var bell = document.getElementById('owNotifBellBtn');
    if (bell) {
      bell.setAttribute('aria-label', formatted.n > 0
        ? formatted.label + ' unread notifications'
        : 'Notifications');
    }
  }

  /**
   * @param {number} count — unread message count (all threads)
   */
  function updateGlobalMessageBadge(count) {
    var formatted = formatBadgeCount(count);
    queryMessageBadges().forEach(function (badge) {
      if (formatted.n <= 0) {
        badge.textContent = '';
        badge.classList.add('d-none');
      } else {
        badge.textContent = formatted.label;
        badge.classList.remove('d-none');
      }
    });
    var fab = document.getElementById('owMessengerFab');
    if (fab) {
      fab.setAttribute('aria-label', formatted.n > 0
        ? 'Open messenger, ' + formatted.label + ' unread messages'
        : 'Open messenger');
    }
  }

  global.updateGlobalNotifBadge = updateGlobalNotifBadge;
  global.updateGlobalMessageBadge = updateGlobalMessageBadge;
})(window);
