/**
 * Team Lead Profile — assigned company details + service agreement document modal.
 */
(function (global) {
  'use strict';

  var API_COMPANY = '../../Api/TeamLead/get_tl_company_details.php';
  var API_AGREEMENT_FILE = '../../Api/TeamLead/get_agreement_file.php';

  var state = {
    company: null,
    agreementModal: null,
  };

  function esc(s) {
    if (s == null || s === '') return '—';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function setField(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value != null && value !== '' ? String(value) : '—';
  }

  function setBadge(badge) {
    var el = document.getElementById('tlAgreementStatusBadge');
    if (!el) return;
    badge = badge || {};
    el.textContent = badge.label || '—';
    el.className = 'badge ' + (badge.badge_class || 'bg-secondary');
  }

  function showState(viewState) {
    var loading = document.getElementById('tlCompanyLoading');
    var empty = document.getElementById('tlCompanyEmpty');
    var content = document.getElementById('tlCompanyContent');
    var err = document.getElementById('tlCompanyError');

    if (loading) loading.classList.toggle('d-none', viewState !== 'loading');
    if (empty) empty.classList.toggle('d-none', viewState !== 'empty');
    if (content) content.classList.toggle('d-none', viewState !== 'content');
    if (err) err.classList.toggle('d-none', viewState !== 'error');
  }

  function updateDocumentButton(company) {
    var btn = document.getElementById('btnViewServiceAgreement');
    var hint = document.getElementById('tlAgreementDocHint');
    if (!btn) return;

    var hasDoc = !!(company && String(company.signed_document_path || '').trim());
    btn.disabled = !hasDoc;
    if (hint) hint.classList.toggle('d-none', hasDoc);
  }

  function payrollBadgeClass(type) {
    return type === 'A' ? 'bg-primary' : 'bg-info text-dark';
  }

  function populateCompany(company, payroll) {
    state.company = company;
    setField('tlCompanyName', company.company_name);
    setField('tlContractNumber', company.contract_number);
    setField('tlAgreementStart', company.service_agreement_start);
    setField('tlAgreementEnd', company.service_agreement_end || 'Ongoing');
    setField('tlAgreementDuration', company.service_agreement_duration);
    setField('tlCompanyAddress', company.company_address);
    setField('tlContactPerson', company.contact_person);
    setField('tlContactNumber', company.contact_number);
    setField('tlCompanyIndustry', company.industry);
    setBadge(company.agreement_badge);
    updateDocumentButton(company);
    populatePayroll(payroll);
    showState('content');
  }

  function populatePayroll(payroll) {
    var badge = document.getElementById('tlPayrollTypeBadge');
    if (badge && payroll) {
      badge.textContent = payroll.type_label || ('Type ' + (payroll.type || 'B'));
      badge.className = 'badge ' + payrollBadgeClass(payroll.type);
    }
    setField('tlPayrollCutoff', payroll && payroll.cutoff_label);
    setField('tlPayrollRelease', payroll && payroll.release_date_display);
    var dedList = document.getElementById('tlPayrollDeductions');
    if (dedList) {
      var items = (payroll && payroll.deductions_list) || [];
      dedList.textContent = items.length ? items.join(' + ') : 'None this cutoff';
    }
  }

  function getAgreementModal() {
    if (state.agreementModal) return state.agreementModal;
    var el = document.getElementById('tlAgreementDocModal');
    if (!el || !global.bootstrap) return null;
    state.agreementModal = bootstrap.Modal.getOrCreateInstance(el);
    el.addEventListener('hidden.bs.modal', resetAgreementPreview);
    return state.agreementModal;
  }

  function resetAgreementPreview() {
    var frame = document.getElementById('tlAgreementDocFrame');
    var image = document.getElementById('tlAgreementDocImage');
    var empty = document.getElementById('tlAgreementDocEmpty');
    var loading = document.getElementById('tlAgreementDocLoading');
    if (frame) {
      frame.classList.add('d-none');
      frame.removeAttribute('src');
    }
    if (image) {
      image.classList.add('d-none');
      image.removeAttribute('src');
    }
    if (empty) empty.classList.add('d-none');
    if (loading) loading.classList.add('d-none');
  }

  function showAgreementPreview(data) {
    var titleEl = document.getElementById('tlAgreementDocModalTitle');
    var frame = document.getElementById('tlAgreementDocFrame');
    var image = document.getElementById('tlAgreementDocImage');
    var empty = document.getElementById('tlAgreementDocEmpty');
    var loading = document.getElementById('tlAgreementDocLoading');
    var download = document.getElementById('tlAgreementDocDownload');
    var modal = getAgreementModal();

    if (!frame || !modal) return;

    var contractNo = data.contract_number || (state.company && state.company.contract_number) || '';
    if (titleEl) {
      titleEl.textContent = 'Service Agreement Document' + (contractNo ? ' — ' + contractNo : '');
    }

    var viewUrl = API_AGREEMENT_FILE + '?stream=1';

    if (download) {
      download.href = viewUrl;
    }

    frame.classList.add('d-none');
    image.classList.add('d-none');
    empty.classList.add('d-none');
    frame.removeAttribute('src');
    image.removeAttribute('src');

    var ext = String(data.document_path || '').split('.').pop().toLowerCase();
    var isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].indexOf(ext) !== -1;
    var isPdf = ext === 'pdf';

    if (loading) loading.classList.add('d-none');

    if (isImage) {
      image.src = viewUrl;
      image.classList.remove('d-none');
    } else if (isPdf || data.preview_mode === 'iframe') {
      frame.src = viewUrl;
      frame.classList.remove('d-none');
    } else {
      empty.classList.remove('d-none');
    }

    modal.show();
  }

  function openAgreementDocument() {
    var modal = getAgreementModal();
    if (!modal) return;

    var loading = document.getElementById('tlAgreementDocLoading');
    var frame = document.getElementById('tlAgreementDocFrame');
    var image = document.getElementById('tlAgreementDocImage');
    var empty = document.getElementById('tlAgreementDocEmpty');

    resetAgreementPreview();
    if (loading) loading.classList.remove('d-none');
    if (frame) frame.classList.add('d-none');
    if (image) image.classList.add('d-none');
    if (empty) empty.classList.add('d-none');

    modal.show();

    fetch(API_AGREEMENT_FILE, { credentials: 'same-origin', method: 'GET' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) {
          throw new Error((data && data.message) || 'Could not load agreement document.');
        }
        showAgreementPreview(data);
      })
      .catch(function (err) {
        modal.hide();
        alert(err.message || 'Could not load agreement document.');
      });
  }

  function bindDocumentButton() {
    var btn = document.getElementById('btnViewServiceAgreement');
    if (!btn || btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', function () {
      if (btn.disabled) return;
      openAgreementDocument();
    });
  }

  function loadTlCompanyDetails() {
    var card = document.getElementById('tlCompanyProfileCard');
    if (!card) return Promise.resolve();

    showState('loading');

    return fetch(API_COMPANY, { credentials: 'same-origin', method: 'GET' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) {
          throw new Error((data && data.message) || 'Failed to load company details');
        }
        if (!data.has_company || !data.company) {
          var emptyMsg = document.getElementById('tlCompanyEmptyMessage');
          if (emptyMsg) {
            emptyMsg.textContent = data.message || 'No client company is assigned to your account.';
          }
          state.company = null;
          updateDocumentButton(null);
          showState('empty');
          return data;
        }
        populateCompany(data.company, data.payroll);
        return data;
      })
      .catch(function (err) {
        console.warn('TL company profile:', err);
        var errMsg = document.getElementById('tlCompanyErrorMessage');
        if (errMsg) errMsg.textContent = err.message || 'Could not load company details.';
        showState('error');
      });
  }

  function hookProfileView() {
    if (!document.getElementById('tlCompanyProfileCard')) return;

    bindDocumentButton();

    var attach = function () {
      if (!global.profileShowView || global.profileShowView.__tlCompanyHooked) return;
      var original = global.profileShowView;
      global.profileShowView = function (viewId, opts) {
        var result = original(viewId, opts);
        if (viewId === 'profile') {
          loadTlCompanyDetails();
        }
        return result;
      };
      global.profileShowView.__tlCompanyHooked = true;

      var profilePanel = document.getElementById('view-profile');
      if (profilePanel && !profilePanel.classList.contains('hidden')) {
        loadTlCompanyDetails();
      }
    };

    if (global.profileShowView) {
      attach();
    } else {
      var attempts = 0;
      var timer = setInterval(function () {
        attempts += 1;
        if (global.profileShowView || attempts > 50) {
          clearInterval(timer);
          attach();
        }
      }, 100);
    }
  }

  global.TlProfileCompany = {
    load: loadTlCompanyDetails,
    openAgreementDocument: openAgreementDocument,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hookProfileView);
  } else {
    hookProfileView();
  }
})(window);