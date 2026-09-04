(function () {
  'use strict';

  var API_BASE = window.API_BASE || '../../Api/Admin/';
  var GET_API = API_BASE + 'get_deduction_rates.php';
  var UPDATE_API = API_BASE + 'update_deduction_rates.php';

  var govForm = document.getElementById('govRatesForm');
  var sssTableBody = document.getElementById('sssBracketsBody');
  var pageAlert = document.getElementById('deductionPageAlert') || document.getElementById('pageAlert');
  var editModalEl = document.getElementById('editSssModal');
  var addModalEl = document.getElementById('addSssModal');
  var editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
  var addModal = addModalEl ? new bootstrap.Modal(addModalEl) : null;

  var bracketsCache = [];

  function money(n) {
    var val = Number(n);
    if (!isFinite(val)) return '—';
    return '₱' + val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function pct(n) {
    var val = Number(n);
    if (!isFinite(val)) return '—';
    return val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) + '%';
  }

  function formatRange(row) {
    var min = money(row.salary_min);
    if (row.salary_max === null || row.salary_max === undefined || row.salary_max === '') {
      return min.replace('₱', '₱') + ' and above';
    }
    return min + ' – ' + money(row.salary_max);
  }

  function showAlert(message, type) {
    if (!pageAlert) return;
    pageAlert.className = 'alert alert-' + (type || 'info');
    pageAlert.textContent = message;
    pageAlert.classList.remove('d-none');
    window.setTimeout(function () {
      pageAlert.classList.add('d-none');
    }, 5000);
  }

  function bindGovRates(gov) {
    if (!gov || !govForm) return;
    document.getElementById('philhealthEePct').value = gov.philhealth_ee_pct;
    document.getElementById('philhealthErPct').value = gov.philhealth_er_pct;
    document.getElementById('pagibigEeFixed').value = gov.pagibig_ee_fixed;
    document.getElementById('pagibigErFixed').value = gov.pagibig_er_fixed;
  }

  function renderSssTable(rows) {
    if (!sssTableBody) return;
    bracketsCache = rows || [];

    if (!rows.length) {
      sssTableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No SSS brackets configured.</td></tr>';
      return;
    }

    sssTableBody.innerHTML = rows.map(function (row) {
      return (
        '<tr data-id="' + row.id + '">' +
          '<td class="text-nowrap">' + formatRange(row) + '</td>' +
          '<td class="text-end">' + money(row.monthly_salary_credit) + '</td>' +
          '<td class="text-end">' + money(row.regular_ss_er) + '</td>' +
          '<td class="text-end">' + money(row.regular_ss_ee) + '</td>' +
          '<td class="text-end">' + money(row.mpf_er) + '</td>' +
          '<td class="text-end">' + money(row.mpf_ee) + '</td>' +
          '<td class="text-end">' + money(row.ec_er) + '</td>' +
          '<td class="text-end fw-semibold">' + money(
            Number(row.regular_ss_er) + Number(row.regular_ss_ee) +
            Number(row.mpf_er) + Number(row.mpf_ee) + Number(row.ec_er)
          ) + '</td>' +
          '<td class="text-center"><span class="badge bg-primary-subtle text-primary border border-primary-subtle">' + row.effective_year + '</span></td>' +
          '<td class="text-end text-nowrap">' +
            '<button type="button" class="btn btn-sm btn-outline-primary btn-edit-sss" data-id="' + row.id + '">' +
              '<i class="bi bi-pencil-square me-1"></i>Edit' +
            '</button>' +
          '</td>' +
        '</tr>'
      );
    }).join('');
  }

  function loadConfig() {
    if (sssTableBody) {
      sssTableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Loading SSS brackets…</td></tr>';
    }

    fetch(GET_API, { credentials: 'include' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.success) {
          throw new Error(data.message || 'Failed to load deduction settings.');
        }
        bindGovRates(data.gov_rates);
        renderSssTable(data.sss_brackets || []);
      })
      .catch(function (err) {
        showAlert(err.message || 'Unable to load deduction settings.', 'danger');
        if (sssTableBody) {
          sssTableBody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Failed to load data.</td></tr>';
        }
      });
  }

  function readBracketForm(form) {
    var salaryMaxRaw = form.querySelector('[name="salary_max"]').value.trim();
    return {
      salary_min: parseFloat(form.querySelector('[name="salary_min"]').value),
      salary_max: salaryMaxRaw === '' ? null : parseFloat(salaryMaxRaw),
      monthly_salary_credit: parseFloat(form.querySelector('[name="monthly_salary_credit"]').value),
      regular_ss_er: parseFloat(form.querySelector('[name="regular_ss_er"]').value),
      regular_ss_ee: parseFloat(form.querySelector('[name="regular_ss_ee"]').value),
      mpf_er: parseFloat(form.querySelector('[name="mpf_er"]').value),
      mpf_ee: parseFloat(form.querySelector('[name="mpf_ee"]').value),
      ec_er: parseFloat(form.querySelector('[name="ec_er"]').value)
    };
  }

  function fillBracketForm(form, row) {
    form.querySelector('[name="salary_min"]').value = row.salary_min;
    form.querySelector('[name="salary_max"]').value = row.salary_max === null ? '' : row.salary_max;
    form.querySelector('[name="monthly_salary_credit"]').value = row.monthly_salary_credit;
    form.querySelector('[name="regular_ss_er"]').value = row.regular_ss_er;
    form.querySelector('[name="regular_ss_ee"]').value = row.regular_ss_ee;
    form.querySelector('[name="mpf_er"]').value = row.mpf_er;
    form.querySelector('[name="mpf_ee"]').value = row.mpf_ee;
    form.querySelector('[name="ec_er"]').value = row.ec_er;
  }

  function postUpdate(payload) {
    return fetch(UPDATE_API, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok || !data.success) {
          throw new Error(data.message || 'Update failed.');
        }
        return data;
      });
    });
  }

  if (govForm) {
    govForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = document.getElementById('btnSaveGovRates');
      if (btn) btn.disabled = true;

      postUpdate({
        action: 'update_gov_rates',
        philhealth_ee_pct: parseFloat(document.getElementById('philhealthEePct').value),
        philhealth_er_pct: parseFloat(document.getElementById('philhealthErPct').value),
        pagibig_ee_fixed: parseFloat(document.getElementById('pagibigEeFixed').value),
        pagibig_er_fixed: parseFloat(document.getElementById('pagibigErFixed').value)
      })
        .then(function (data) {
          showAlert(data.message || 'Government rates saved.', 'success');
        })
        .catch(function (err) {
          showAlert(err.message, 'danger');
        })
        .finally(function () {
          if (btn) btn.disabled = false;
        });
    });
  }

  if (sssTableBody) {
    sssTableBody.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-edit-sss');
      if (!btn) return;
      var id = parseInt(btn.getAttribute('data-id'), 10);
      var row = bracketsCache.find(function (item) { return item.id === id; });
      if (!row || !editModal) return;

      var form = document.getElementById('editSssForm');
      document.getElementById('editSssId').value = id;
      fillBracketForm(form, row);
      editModal.show();
    });
  }

  var btnSaveEdit = document.getElementById('btnSaveEditSss');
  if (btnSaveEdit) {
    btnSaveEdit.addEventListener('click', function () {
      var form = document.getElementById('editSssForm');
      var payload = readBracketForm(form);
      payload.action = 'update_sss_bracket';
      payload.id = parseInt(document.getElementById('editSssId').value, 10);
      btnSaveEdit.disabled = true;

      postUpdate(payload)
        .then(function (data) {
          showAlert(data.message || 'SSS bracket updated.', 'success');
          if (editModal) editModal.hide();
          loadConfig();
        })
        .catch(function (err) {
          showAlert(err.message, 'danger');
        })
        .finally(function () {
          btnSaveEdit.disabled = false;
        });
    });
  }

  var btnSaveAdd = document.getElementById('btnSaveAddSss');
  if (btnSaveAdd) {
    btnSaveAdd.addEventListener('click', function () {
      var form = document.getElementById('addSssForm');
      var payload = readBracketForm(form);
      payload.action = 'add_sss_bracket';
      btnSaveAdd.disabled = true;

      postUpdate(payload)
        .then(function (data) {
          showAlert(data.message || 'SSS bracket added.', 'success');
          if (addModal) addModal.hide();
          form.reset();
          loadConfig();
        })
        .catch(function (err) {
          showAlert(err.message, 'danger');
        })
        .finally(function () {
          btnSaveAdd.disabled = false;
        });
    });
  }

  var btnOpenAdd = document.getElementById('btnOpenAddSss');
  if (btnOpenAdd && addModal) {
    btnOpenAdd.addEventListener('click', function () {
      document.getElementById('addSssForm').reset();
      addModal.show();
    });
  }

  var btnAutoCompute = document.querySelectorAll('.btn-auto-compute-sss');
  btnAutoCompute.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var formId = btn.getAttribute('data-form');
      var form = document.getElementById(formId);
      if (!form) return;

      var msc = parseFloat(form.querySelector('[name="monthly_salary_credit"]').value);
      if (!isFinite(msc) || msc <= 0) {
        showAlert('Enter a valid Monthly Salary Credit first.', 'warning');
        return;
      }

      var regularBase = Math.min(msc, 20000);
      var mpfBase = Math.max(0, msc - 20000);
      form.querySelector('[name="regular_ss_ee"]').value = (regularBase * 0.05).toFixed(2);
      form.querySelector('[name="regular_ss_er"]').value = (regularBase * 0.10).toFixed(2);
      form.querySelector('[name="mpf_ee"]').value = (mpfBase * 0.05).toFixed(2);
      form.querySelector('[name="mpf_er"]').value = (mpfBase * 0.10).toFixed(2);
      form.querySelector('[name="ec_er"]').value = msc <= 14500 ? '10.00' : '30.00';
    });
  });

  window.DeductionSettings = { load: loadConfig };

  if (document.getElementById('section-deduction-settings') && document.getElementById('section-deduction-settings').classList.contains('active')) {
    loadConfig();
  }
})();
