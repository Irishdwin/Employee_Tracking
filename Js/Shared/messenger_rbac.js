/**
 * Messenger RBAC — who may see / message whom.
 * Role keys use DB RoleName values (TeamLead, Accounting, …).
 */
(function (global) {
  'use strict';

  /** Canonical role names (after normalization). */
  var ROLES = {
    ADMIN: 'Admin',
    HR: 'HR',
    ACCOUNTING: 'Accounting',
    TEAM_LEAD: 'TeamLead',
    EMPLOYEE: 'Employee',
    APPLICANT: 'Applicant'
  };

  /**
   * Allowed target roles per viewer role.
   * HR: '*' = every role in the system.
   * TeamLead Employee rows require assignment check (see filterAllowedContacts).
   */
  var chatPermissions = {
    Admin: '*',
    HR: [ROLES.ADMIN, ROLES.ACCOUNTING, ROLES.TEAM_LEAD, ROLES.APPLICANT, ROLES.EMPLOYEE],
    Accounting: [ROLES.ADMIN, ROLES.HR, ROLES.TEAM_LEAD, ROLES.EMPLOYEE],
    TeamLead: [ROLES.EMPLOYEE, ROLES.ACCOUNTING, ROLES.HR],
    Applicant: [ROLES.HR],
    Employee: [ROLES.EMPLOYEE, ROLES.ACCOUNTING, ROLES.TEAM_LEAD, ROLES.HR]
  };

  /** Display / API aliases → canonical RoleName */
  var ROLE_ALIASES = {
    'accounting staff': ROLES.ACCOUNTING,
    accounting: ROLES.ACCOUNTING,
    'team lead': ROLES.TEAM_LEAD,
    teamlead: ROLES.TEAM_LEAD,
    admin: ROLES.ADMIN,
    hr: ROLES.HR,
    employee: ROLES.EMPLOYEE,
    applicant: ROLES.APPLICANT
  };

  function normalizeRole(role) {
    if (role == null || role === '') return '';
    var key = String(role).trim().toLowerCase();
    if (ROLE_ALIASES[key]) return ROLE_ALIASES[key];
    var raw = String(role).trim();
    if (raw === 'Team Lead') return ROLES.TEAM_LEAD;
    if (raw === 'Accounting Staff') return ROLES.ACCOUNTING;
    return raw;
  }

  function currentUserId(currentUser) {
    if (!currentUser) return null;
    var id = currentUser.id != null ? currentUser.id : currentUser.user_id;
    return id != null ? parseInt(id, 10) : null;
  }

  /**
   * Team Lead may only message Employees deployed under them.
   * Supports team_lead_id, managerId, manager_id, assigned_team_lead_id,
   * is_assigned_to_current_team_lead from the contacts API.
   */
  function isEmployeeAssignedToTeamLead(contact, viewerId) {
    if (!contact || viewerId == null) return false;

    if (contact.is_assigned_to_current_team_lead === true) return true;
    if (contact.is_assigned_to_current_team_lead === false) return false;

    var tlId = contact.team_lead_id != null ? contact.team_lead_id
      : contact.assigned_team_lead_id != null ? contact.assigned_team_lead_id
      : contact.managerId != null ? contact.managerId
      : contact.manager_id;

    if (tlId == null) return false;
    return parseInt(tlId, 10) === parseInt(viewerId, 10);
  }

  function roleIsAllowed(viewerRole, contactRole) {
    var allowed = chatPermissions[viewerRole];
    if (!allowed) return false;
    if (allowed === '*') return true;
    if (!Array.isArray(allowed)) return false;
    return allowed.indexOf(contactRole) !== -1;
  }

  /**
   * @param {object} currentUser - { id|user_id, role }
   * @param {object[]} allUsersList - system users (must include role; employees need team_lead_id / managerId)
   * @returns {object[]}
   */
  function filterAllowedContacts(currentUser, allUsersList) {
    if (!currentUser || !Array.isArray(allUsersList)) return [];

    var viewerRole = normalizeRole(currentUser.role);
    var viewerId = currentUserId(currentUser);
    if (!viewerRole) return [];

    return allUsersList.filter(function (contact) {
      var contactId = currentUserId(contact);
      if (viewerId != null && contactId != null && contactId === viewerId) {
        return false;
      }

      if (contact.server_authorized === true) return true;
      var contactRole = normalizeRole(contact.role);
      if (!contactRole) return false;

      if (!roleIsAllowed(viewerRole, contactRole)) {
        return false;
      }

      if (viewerRole === ROLES.TEAM_LEAD && contactRole === ROLES.EMPLOYEE) {
        return isEmployeeAssignedToTeamLead(contact, viewerId);
      }

      return true;
    });
  }

  function escapeHtml(str) {
    if (str == null) return '';
    var d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
  }

  function contactInitials(name) {
    if (!name) return '?';
    var parts = String(name).trim().split(/\s+/).filter(Boolean);
    var a = parts[0] ? parts[0].charAt(0) : '';
    var b = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
    return (a + b).toUpperCase() || '?';
  }

  /**
   * Render contact list into the messenger sidebar container.
   * @param {HTMLElement|string} container - scrollable contact area
   * @param {object[]} contacts - already RBAC-filtered list
   * @param {object} options
   * @param {number|null} options.activeUserId
   * @param {function} options.onSelect - called with contact object
   * @param {string} options.emptyMessage
   * @param {string} options.itemClass - e.g. 'admin-msgr-contact'
   */
  function renderMessengerContactList(container, contacts, options) {
    options = options || {};
    var el = typeof container === 'string' ? document.getElementById(container) : container;
    if (!el) return;

    var list = contacts || [];
    var activeId = options.activeUserId != null ? parseInt(options.activeUserId, 10) : null;
    var itemClass = options.itemClass || 'messenger-contact';
    var emptyMsg = options.emptyMessage || 'No contacts available for your role.';
    var unreadState = (options.unreadData && options.unreadData.byContact)
      ? options.unreadData.byContact
      : (options.unreadMessageState || {});

    if (!list.length) {
      el.innerHTML = '<p class="messenger-contacts-empty text-muted small text-center p-3">' + escapeHtml(emptyMsg) + '</p>';
      return;
    }

    el.innerHTML = list.map(function (c) {
      var uid = c.user_id != null ? c.user_id : c.id;
      var isActive = activeId != null && parseInt(uid, 10) === activeId;
      var unread = unreadState[String(uid)] != null
        ? parseInt(unreadState[String(uid)], 10) || 0
        : parseInt(c.unread_count, 10) || 0;
      var hasUnread = unread > 0;
      var company = c.company || c.company_name || 'Onazul Manpower';
      var roleLabel = c.role || '';
      var unreadDot = hasUnread
        ? '<span class="msgr-unread-dot px-2 py-0 bg-danger text-white border border-light rounded-circle flex-shrink-0 d-inline-block" ' +
          'title="' + unread + ' unread" aria-label="' + unread + ' unread messages">' + unread + '</span>'
        : '';
      return (
        '<button type="button" class="' + itemClass +
        ' d-flex align-items-center gap-2 w-100 border-0' +
        (isActive ? ' is-active' : '') +
        (hasUnread ? ' has-unread' : '') +
        '" data-user-id="' + uid + '"' +
        (hasUnread ? ' data-unread="1"' : '') + '>' +
        '<span class="messenger-contact-avatar rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:2.25rem;height:2.25rem;font-size:0.75rem;">' +
        escapeHtml(contactInitials(c.full_name)) + '</span>' +
        '<span class="messenger-contact-text flex-grow-1 min-w-0 text-start">' +
        '<span class="messenger-contact-name d-flex align-items-center gap-2 ' +
        (hasUnread ? 'fw-bold' : 'fw-semibold') + '">' +
        '<span class="text-truncate">' + escapeHtml(c.full_name || 'User') + '</span>' +
        unreadDot + '</span>' +
        '<span class="messenger-contact-presence small d-block ' + (c.is_online ? 'text-success' : 'text-muted') + '">' + (c.is_online ? '● Online' : '○ Offline') + '</span>' +
        '<span class="messenger-contact-meta small text-muted text-truncate d-block">' +
        escapeHtml(roleLabel) + ' · ' + escapeHtml(company) + '</span>' +
        '</span></button>'
      );
    }).join('');

    el.querySelectorAll('.' + itemClass.split(' ')[0]).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-user-id'), 10);
        var contact = list.find(function (x) {
          var xid = x.user_id != null ? x.user_id : x.id;
          return parseInt(xid, 10) === id;
        });
        if (contact && typeof options.onSelect === 'function') {
          options.onSelect(contact);
        }
      });
    });
  }

  /**
   * Text search on an already RBAC-filtered list (name, role, company, email).
   */
  function filterContactsBySearch(contacts, query) {
    var q = (query || '').trim().toLowerCase();
    if (!q) return (contacts || []).slice();
    return (contacts || []).filter(function (c) {
      return (
        (c.full_name || '').toLowerCase().indexOf(q) !== -1 ||
        (c.role || '').toLowerCase().indexOf(q) !== -1 ||
        (c.company || c.company_name || '').toLowerCase().indexOf(q) !== -1 ||
        (c.email || '').toLowerCase().indexOf(q) !== -1
      );
    });
  }

  global.MessengerRbac = {
    ROLES: ROLES,
    chatPermissions: chatPermissions,
    normalizeRole: normalizeRole,
    filterAllowedContacts: filterAllowedContacts,
    isEmployeeAssignedToTeamLead: isEmployeeAssignedToTeamLead,
    renderMessengerContactList: renderMessengerContactList,
    filterContactsBySearch: filterContactsBySearch,
    contactInitials: contactInitials,
    escapeHtml: escapeHtml
  };

  /* Global aliases for tests / inline scripts */
  global.chatPermissions = chatPermissions;
  global.filterAllowedContacts = filterAllowedContacts;
})(window);
