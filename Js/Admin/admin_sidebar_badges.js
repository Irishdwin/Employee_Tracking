/**
 * Polls realtime_counters.php and updates Admin sidebar badges + header notification dot.
 */
(function (global) {
  'use strict';

  var BADGE_CONFIG = [
    { key: 'clients_without_agreement', id: 'adminClientsNoAgreementBadge', singular: '1 company needs a service agreement', plural: 'companies need a service agreement' },
    { key: 'service_agreements_expiring', id: 'adminServiceAgreementsExpiringBadge', singular: '1 service agreement expiring within 15 days', plural: 'service agreements expiring within 15 days' },
    { key: 'payroll_pending', id: 'adminPayrollPendingBadge', singular: '1 payroll run pending approval', plural: 'payroll runs pending approval' },
    { key: 'job_orders', id: 'hrJobOrdersBadge', singular: '1 job order request to post', plural: 'job order requests to post' },
    { key: 'applicant_review_pending', id: 'adminApplicantReviewBadge', singular: '1 application needs HR review', plural: 'applications need HR review' },
    { key: 'applicant_contracts_pending', id: 'adminApplicantContractsBadge', singular: '1 applicant needs contract upload', plural: 'applicants need contract upload' },
    { key: 'employee_conversion_ready', id: 'adminEmployeeConversionBadge', singular: '1 applicant ready to hire', plural: 'applicants ready to hire' }
  ];

  function updateSidebarBadge(badgeId, count, singular, plural) {
    var badge = document.getElementById(badgeId);
    if (!badge) return;
    var n = parseInt(count, 10) || 0;
    badge.textContent = n > 99 ? '99+' : String(n);
    badge.classList.toggle('d-none', n <= 0);
    if (singular && plural) {
      badge.setAttribute('aria-label', n === 1 ? singular : n + ' ' + plural);
    }
  }

  function updateHeaderNotifDot(unreadCount) {
    if (global.OwNotifications && typeof global.OwNotifications.updateUnreadBadge === 'function') {
      global.OwNotifications.updateUnreadBadge(unreadCount);
      return;
    }
    var n = parseInt(unreadCount, 10) || 0;
    var label = n > 99 ? '99+' : String(n);
    ['ccNotifBtn', 'owNotifBellBtn'].forEach(function (id) {
      var btn = document.getElementById(id);
      if (!btn) return;
      var badge = btn.querySelector(
        '[data-global-notif-badge], #globalNotifBadge, .ow-notif-badge, .cc-notif-dot, .ow-notif-dot, .badge.bg-danger'
      );
      if (badge) {
        badge.textContent = n > 0 ? label : '';
        badge.classList.toggle('d-none', n <= 0);
      }
      btn.setAttribute('aria-label', n > 0 ? label + ' unread notifications' : 'Notifications');
    });
  }

  function applyAdminCounts(counts) {
    if (!counts) return;
    BADGE_CONFIG.forEach(function (cfg) {
      if (counts[cfg.key] == null) return;
      updateSidebarBadge(cfg.id, counts[cfg.key], cfg.singular, cfg.plural);
    });
    if (counts.notifications_unread != null) {
      updateHeaderNotifDot(counts.notifications_unread);
    }
  }

  function pollAdminSidebarBadges(apiUrl) {
    return fetch(apiUrl, { credentials: 'include' })
      .then(function (r) { return r.json().catch(function () { return null; }); })
      .then(function (d) {
        if (!d || !d.success || !d.logged_in) return;
        applyAdminCounts(d.counts || {});
      })
      .catch(function () {});
  }

  function initAdminSidebarBadges(apiUrl, intervalMs) {
    if (!apiUrl) return;
    function tick() { pollAdminSidebarBadges(apiUrl); }
    tick();
    if (intervalMs > 0) window.setInterval(tick, intervalMs);
  }

  function initAdminNotifButton(notificationsUrl) {
    var btn = document.getElementById('ccNotifBtn');
    if (!btn || btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', function () {
      var target = notificationsUrl || '../notifications.html';
      window.location.href = target;
    });
  }

  global.AdminSidebarBadges = {
    apply: applyAdminCounts,
    poll: pollAdminSidebarBadges,
    init: initAdminSidebarBadges,
    initNotifButton: initAdminNotifButton
  };
})(window);
