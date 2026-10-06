/**
 * Shared HTML-escaping helper.
 *
 * Referenced by Html/Admin/admin_dashboard.html and Html/Applicant/applicant_landing.html
 * (<script src="../../Js/Shared/html_escape.js">) but the file itself did not exist,
 * producing a 404 on every page load. Several other files already implement the exact
 * same function locally (Js/Admin/admin_dashboard.js, Js/HR/hr_dashboard.js,
 * Js/Shared/messenger_rbac.js, Js/Applicant/*.js) — this is that same implementation,
 * exposed as a single shared global so pages that load this file don't need their own copy.
 *
 * Safe to load alongside pages that already define their own local/global escapeHtml():
 * this only assigns window.escapeHtml if it isn't already defined, so it never overwrites
 * a page-specific implementation loaded before or after it.
 */
(function (global) {
    'use strict';
  
    function escapeHtml(str) {
      if (str == null) return '';
      var d = document.createElement('div');
      d.textContent = String(str);
      return d.innerHTML;
    }
  
    if (typeof global.escapeHtml !== 'function') {
      global.escapeHtml = escapeHtml;
    }
  })(window);