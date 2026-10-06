/**
 * Shared pagination control renderer — Bootstrap 5 `.pagination` component,
 * matching the existing look of the rest of the Admin dashboard (same button
 * classes/badges style already used for the System Logs Previous/Next pair
 * in Js/Admin/admin_dashboard.js). Used by:
 *   - Html/Admin/user_management.html (client-side pages over a loaded list)
 *   - Html/Admin/admin_dashboard.html: Client Companies + Service Agreements
 *     (server-side pages — each page change re-fetches from the API)
 *
 * One shared renderer instead of three near-identical copies, so all three
 * sections behave and look the same and any future tweak (e.g. window size)
 * only needs to change in one place.
 */
(function (global) {
    'use strict';
  
    /**
     * @param {HTMLElement|string} container  Element or element id to render into.
     * @param {{page:number, pages:number, total:number}} info
     * @param {(page:number)=>void} onPageChange  Called with the 1-based page to go to.
     * @param {string} [itemLabel]  e.g. "users", "companies", "agreements" — used in the summary text.
     */
    function renderPaginationControls(container, info, onPageChange, itemLabel) {
      var el = typeof container === 'string' ? document.getElementById(container) : container;
      if (!el) return;
  
      var page = Math.max(1, parseInt(info && info.page, 10) || 1);
      var pages = Math.max(1, parseInt(info && info.pages, 10) || 1);
      var total = Math.max(0, parseInt(info && info.total, 10) || 0);
      if (page > pages) page = pages;
  
      var label = itemLabel || 'records';
      var summary = total === 0
        ? 'No ' + label + ' found'
        : 'Page ' + page + ' of ' + pages + ' (' + total + ' total ' + label + ')';
  
      // Window of page-number buttons around the current page (max 5 numbers),
      // plus first/last when they fall outside the window.
      var windowSize = 5;
      var start = Math.max(1, page - Math.floor(windowSize / 2));
      var end = Math.min(pages, start + windowSize - 1);
      start = Math.max(1, Math.min(start, end - windowSize + 1));
  
      var items = [];
  
      items.push(
        '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '">' +
        '<button type="button" class="page-link" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>Previous</button>' +
        '</li>'
      );
  
      if (start > 1) {
        items.push('<li class="page-item"><button type="button" class="page-link" data-page="1">1</button></li>');
        if (start > 2) items.push('<li class="page-item disabled"><span class="page-link">&hellip;</span></li>');
      }
  
      for (var p = start; p <= end; p++) {
        items.push(
          '<li class="page-item' + (p === page ? ' active' : '') + '">' +
          '<button type="button" class="page-link" data-page="' + p + '">' + p + '</button>' +
          '</li>'
        );
      }
  
      if (end < pages) {
        if (end < pages - 1) items.push('<li class="page-item disabled"><span class="page-link">&hellip;</span></li>');
        items.push('<li class="page-item"><button type="button" class="page-link" data-page="' + pages + '">' + pages + '</button></li>');
      }
  
      items.push(
        '<li class="page-item' + (page >= pages ? ' disabled' : '') + '">' +
        '<button type="button" class="page-link" data-page="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + '>Next</button>' +
        '</li>'
      );
  
      el.innerHTML =
        '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">' +
        '<span class="text-muted small">' + summary + '</span>' +
        '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">' + items.join('') + '</ul></nav>' +
        '</div>';
  
      el.querySelectorAll('.page-link[data-page]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (btn.hasAttribute('disabled')) return;
          var target = parseInt(btn.getAttribute('data-page'), 10);
          if (!target || target === page) return;
          if (target < 1 || target > pages) return;
          onPageChange(target);
        });
      });
    }
  
    global.renderPaginationControls = renderPaginationControls;
  })(window);