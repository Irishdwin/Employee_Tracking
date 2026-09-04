// Reusable Bootstrap modal-based logout confirmation.
// Provides window.showLogoutConfirm(onConfirm) and also wires basic sidebar <a> Logout links.
(function () {
  function ensureLogoutModal() {
    var existing = document.getElementById('logoutConfirmModal');
    if (existing) {
      return new bootstrap.Modal(existing);
    }
    var wrapper = document.createElement('div');
    wrapper.innerHTML = '' +
      '<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">' +
      '  <div class="modal-dialog modal-dialog-centered modal-sm">' +
      '    <div class="modal-content">' +
      '      <div class="modal-header py-2">' +
      '        <h5 class="modal-title" style="font-size:0.95rem;">Confirm Logout</h5>' +
      '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
      '      </div>' +
      '      <div class="modal-body py-3">' +
      '        <p class="mb-0" style="font-size:0.9rem;">Are you sure you want to logout?</p>' +
      '      </div>' +
      '      <div class="modal-footer py-2">' +
      '        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>' +
      '        <button type="button" class="btn btn-primary btn-sm" data-logout-confirm>Logout</button>' +
      '      </div>' +
      '    </div>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(wrapper.firstChild);
    return new bootstrap.Modal(document.getElementById('logoutConfirmModal'));
  }

  window.showLogoutConfirm = function (onConfirm) {
    var modalInstance = ensureLogoutModal();
    var modalEl = document.getElementById('logoutConfirmModal');
    var confirmBtn = modalEl.querySelector('[data-logout-confirm]');
    confirmBtn.onclick = function () {
      modalInstance.hide();
      if (typeof onConfirm === 'function') onConfirm();
    };
    modalInstance.show();
  };

  document.addEventListener('DOMContentLoaded', function () {
    // Attach to simple sidebar Logout links (that navigate via href), excluding custom JS buttons (id=logoutBtn)
    var links = document.querySelectorAll('.sidebar-nav a, #sidebar a');
    links.forEach(function (link) {
      var text = (link.textContent || '').toLowerCase().trim();
      if (text.indexOf('logout') === -1) return;
      if (link.id === 'logoutBtn') return; // handled by page-specific JS

      link.addEventListener('click', function (e) {
        e.preventDefault();
        window.showLogoutConfirm(function () {
          fetch('/Employee_Tracking/Api/Login/logout.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
          })
            .then(function () { window.location.href = '/Employee_Tracking/index.html'; })
            .catch(function () { window.location.href = '/Employee_Tracking/index.html'; });
        });
      });
    });
  });
})();

