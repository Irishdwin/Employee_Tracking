/**
 * Applicant Job Wall — render multi-deployment slot rows with FILLED state.
 */
(function (global) {
  'use strict';

  function esc(text) {
    if (text == null) return '';
    var d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }

  function isSlotFilled(slot) {
    return !!(slot && (slot.is_filled === true || slot.status === 'filled'));
  }

  function renderDeploymentSlots(slots, options) {
    options = options || {};
    if (!Array.isArray(slots) || !slots.length) {
      return '';
    }
    var multi = slots.length > 1 || (slots[0] && slots[0].detail_id);
    if (!multi) {
      return '';
    }

    var title = options.title || 'Open deployment slots';
    var cards = slots.map(function (slot) {
      var filled = isSlotFilled(slot);
      var pos = slot.position_title || slot.position || 'Role';
      var site = slot.deployment_site || slot.site || 'Site TBD';
      var schedule = slot.work_schedule || '—';
      var workers = parseInt(slot.required_workers, 10) || 1;
      var filledCount = parseInt(slot.filled_slots, 10) || 0;
      return (
        '<div class="jw-slot-card' + (filled ? ' is-filled' : '') + '">' +
        (filled ? '<span class="jw-slot-filled-badge">FILLED</span>' : '') +
        '<div class="jw-slot-card-title">' + esc(pos) + '</div>' +
        '<div class="jw-slot-card-meta">' + esc(site) + ' · ' + esc(workers + ' slot' + (workers === 1 ? '' : 's')) +
        (filled ? ' · ' + esc(filledCount + ' hired') : '') + '</div>' +
        '<div class="jw-slot-card-schedule"><i class="bi bi-clock"></i> ' + esc(schedule) + '</div>' +
        '</div>'
      );
    }).join('');

    return (
      '<div class="jw-deployment-slots">' +
      '<h3 class="jw-deployment-slots-title">' + esc(title) + '</h3>' +
      '<div class="jw-deployment-slots-grid">' + cards + '</div>' +
      '</div>'
    );
  }

  function openPlacements(placements) {
    return (placements || []).filter(function (p) {
      return !isSlotFilled(p);
    });
  }

  global.JobWallSlots = {
    esc: esc,
    isSlotFilled: isSlotFilled,
    renderDeploymentSlots: renderDeploymentSlots,
    openPlacements: openPlacements,
  };
})(window);
