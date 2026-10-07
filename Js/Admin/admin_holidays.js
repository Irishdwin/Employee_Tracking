(function () {
    'use strict';
  
    var API = (window.API_BASE || '../../Api/Admin/') + 'manage_holidays.php';
  
    var tableBody = document.getElementById('holidaysTableBody');
    var pageAlert = document.getElementById('holidaysPageAlert');
    var modalEl = document.getElementById('holidayModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var modalTitle = document.getElementById('holidayModalTitle');
    var modalFormAlert = document.getElementById('holidayModalAlert');
    var form = document.getElementById('holidayForm');
    var btnOpenAdd = document.getElementById('btnOpenAddHoliday');
    var btnSave = document.getElementById('btnSaveHoliday');
  
    var holidaysCache = [];
    var loaded = false;
    var csrfToken = '';
  
    function showAlert(message, type) {
      if (!pageAlert) return;
      pageAlert.className = 'alert alert-' + (type || 'info');
      pageAlert.textContent = message;
      pageAlert.classList.remove('d-none');
      window.setTimeout(function () {
        pageAlert.classList.add('d-none');
      }, 5000);
    }
  
    function showFormError(message) {
      if (!modalFormAlert) return;
      modalFormAlert.textContent = message;
      modalFormAlert.classList.remove('d-none');
    }
  
    function clearFormError() {
      if (!modalFormAlert) return;
      modalFormAlert.classList.add('d-none');
      modalFormAlert.textContent = '';
    }
  
    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }
  
    function formatDate(iso) {
      if (!iso) return '—';
      var parts = iso.split('-');
      if (parts.length !== 3) return esc(iso);
      var d = new Date(Date.UTC(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10)));
      return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' });
    }
  
    function isUpcomingOrToday(iso) {
      if (!iso) return true;
      var today = new Date();
      var todayIso = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
      return iso >= todayIso;
    }
  
    function statusBadge(iso) {
      return isUpcomingOrToday(iso)
        ? '<span class="badge text-bg-success">Upcoming</span>'
        : '<span class="badge text-bg-secondary">Past</span>';
    }
  
    function renderTable(rows) {
      if (!tableBody) return;
      if (!rows.length) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No holidays defined yet. Click "Add Holiday" to create one.</td></tr>';
        return;
      }
      var html = '';
      rows.forEach(function (h) {
        html += '<tr data-id="' + h.id + '">' +
          '<td><strong>' + esc(h.name) + '</strong>' + (h.region ? '<div class="small text-muted">' + esc(h.region) + '</div>' : '') + '</td>' +
          '<td>' + formatDate(h.date) + '</td>' +
          '<td>' + esc(h.type || '—') + '</td>' +
          '<td class="text-end">' + Number(h.pay_multiplier).toFixed(2) + 'x</td>' +
          '<td class="text-center">' + statusBadge(h.date) + '</td>' +
          '<td class="text-end">' +
          '<button type="button" class="btn btn-sm btn-outline-primary btn-edit-holiday me-1" data-id="' + h.id + '" title="Edit"><i class="bi bi-pencil"></i></button>' +
          '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-holiday" data-id="' + h.id + '" title="Delete"><i class="bi bi-trash"></i></button>' +
          '</td>' +
          '</tr>';
      });
      tableBody.innerHTML = html;
    }
  
    function load() {
      if (!tableBody) return;
      tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading holidays…</td></tr>';
  
      fetch(API, { credentials: 'include' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.success) {
            throw new Error(data.message || 'Failed to load holidays.');
          }
          csrfToken = data.csrf_token || '';
          holidaysCache = data.holidays || [];
          var scope = form.querySelector('[name="site_ids"]');
          if (!scope) { var block = document.createElement('div'); block.className = 'mb-3'; block.innerHTML = '<label class="form-label" for="holiday-site-scope">Applicable deployment sites (local/company holidays)</label><select multiple id="holiday-site-scope" name="site_ids" class="form-select" size="4"></select><div class="form-text">Nationwide holidays apply to everyone. For local holidays, select the applicable sites; without a site selection, the region must match the deployment address.</div>'; form.appendChild(block); scope = block.querySelector('select'); }
          scope.innerHTML = (data.sites || []).map(function (site) { return '<option value="' + Number(site.SiteID) + '">' + esc(site.company + ' / ' + site.Name) + '</option>'; }).join('');
          loaded = true;
          renderTable(holidaysCache);
        })
        .catch(function (err) {
          tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load holidays.</td></tr>';
          showAlert(err.message || 'Unable to load holidays.', 'danger');
        });
    }
  
    function readForm() {
      var fd = new FormData(form);
      return {
        id: fd.get('id') ? parseInt(fd.get('id'), 10) : null,
        name: (fd.get('name') || '').trim(),
        date: fd.get('date') || '',
        type: fd.get('type') || '',
        pay_multiplier: parseFloat(fd.get('pay_multiplier')),
        region: (fd.get('region') || '').trim(),
        is_nationwide: form.querySelector('[name="is_nationwide"]').checked,
        site_ids: fd.getAll('site_ids').map(Number),
        description: (fd.get('description') || '').trim()
      };
    }
  
    function fillForm(h) {
      form.querySelector('[name="id"]').value = h ? h.id : '';
      form.querySelector('[name="name"]').value = h ? h.name : '';
      form.querySelector('[name="date"]').value = h ? h.date : '';
      form.querySelector('[name="type"]').value = h ? (h.type || '') : '';
      form.querySelector('[name="pay_multiplier"]').value = h ? h.pay_multiplier : '2.00';
      form.querySelector('[name="region"]').value = h ? (h.region || '') : '';
      form.querySelector('[name="is_nationwide"]').checked = h ? !!h.is_nationwide : true;
      form.querySelector('[name="description"]').value = h ? (h.description || '') : '';
      var scope = form.querySelector('[name="site_ids"]'); if (scope) Array.from(scope.options).forEach(function (option) { option.selected = !!h && (h.site_ids || []).indexOf(Number(option.value)) !== -1; });
      if (multiplierInput) delete multiplierInput.dataset.userEdited;
    }
  
    function openAdd() {
      clearFormError();
      if (modalTitle) modalTitle.textContent = 'Add Holiday';
      fillForm(null);
      if (modal) modal.show();
    }
  
    function openEdit(id) {
      var h = holidaysCache.find(function (item) { return item.id === id; });
      if (!h) return;
      clearFormError();
      if (modalTitle) modalTitle.textContent = 'Edit Holiday';
      fillForm(h);
      if (modal) modal.show();
    }
  
    function save() {
      if (!form.reportValidity()) return;
      clearFormError();
      var payload = readForm();
      payload.action = payload.id ? 'edit' : 'add';
  
      if (btnSave) btnSave.disabled = true;
      fetch(API, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-Payroll-CSRF': csrfToken },
        body: JSON.stringify(payload)
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (r) {
          if (!r.ok || !r.data.success) {
            throw new Error(r.data.message || 'Could not save the holiday.');
          }
          showAlert(payload.action === 'add' ? 'Holiday added.' : 'Holiday updated.', 'success');
          if (modal) modal.hide();
          load();
        })
        .catch(function (err) {
          showFormError(err.message || 'Could not save the holiday.');
        })
        .finally(function () {
          if (btnSave) btnSave.disabled = false;
        });
    }
  
    function remove(id) {
      var h = holidaysCache.find(function (item) { return item.id === id; });
      var label = h ? h.name + ' (' + h.date + ')' : 'this holiday';
      if (!window.confirm('Delete ' + label + '? This cannot be undone.')) return;
  
      fetch(API, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-Payroll-CSRF': csrfToken },
        body: JSON.stringify({ action: 'delete', id: id })
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (r) {
          if (!r.ok || !r.data.success) {
            throw new Error(r.data.message || 'Could not delete the holiday.');
          }
          showAlert('Holiday deleted.', 'success');
          load();
        })
        .catch(function (err) {
          showAlert(err.message || 'Could not delete the holiday.', 'danger');
        });
    }
  
    if (btnOpenAdd) btnOpenAdd.addEventListener('click', openAdd);
    if (btnSave) btnSave.addEventListener('click', save);
  
    // Suggested multiplier per type, taken from the client's own payroll
    // template (Templates/SAMPLE.xlsx): Regular Holiday = 200% total,
    // Special Non-working Day = 130% total. Only auto-fills when the
    // multiplier still has its untouched default, so it never overwrites a
    // value Admin has deliberately customized.
    var TYPE_DEFAULT_MULTIPLIER = {
      'Regular Holiday': '2.00',
      'Special Non-working Day': '1.30',
      'Special Working Day': '1.00',
      'Local/Company Holiday': '1.00'
    };
    var typeSelect = form ? form.querySelector('[name="type"]') : null;
    var multiplierInput = form ? form.querySelector('[name="pay_multiplier"]') : null;
    if (typeSelect && multiplierInput) {
      typeSelect.addEventListener('change', function () {
        var suggested = TYPE_DEFAULT_MULTIPLIER[typeSelect.value];
        if (suggested && !multiplierInput.dataset.userEdited) {
          multiplierInput.value = suggested;
        }
      });
      multiplierInput.addEventListener('input', function () {
        multiplierInput.dataset.userEdited = '1';
      });
    }
  
    if (tableBody) {
      tableBody.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.btn-edit-holiday');
        if (editBtn) {
          openEdit(parseInt(editBtn.getAttribute('data-id'), 10));
          return;
        }
        var delBtn = e.target.closest('.btn-delete-holiday');
        if (delBtn) {
          remove(parseInt(delBtn.getAttribute('data-id'), 10));
        }
      });
    }
  
    window.HolidaysAdmin = {
      load: function () {
        // Section is lazily shown; reload every time it's opened so edits from
        // another tab/session are reflected, matching the pattern used by the
        // other Admin CRUD sections in this dashboard.
        load();
      }
    };
  })();