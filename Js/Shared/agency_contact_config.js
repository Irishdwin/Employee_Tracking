(function () {
    'use strict';
  
    var API = '../../Api/Common/agency_contact.php';
  
    var form = document.getElementById('agencyContactForm');
    var pageAlert = document.getElementById('agencyContactAlert');
  
    function showAlert(message, type) {
      if (!pageAlert) return;
      pageAlert.className = 'alert alert-' + (type || 'info');
      pageAlert.textContent = message;
      pageAlert.classList.remove('d-none');
      window.setTimeout(function () {
        pageAlert.classList.add('d-none');
      }, 5000);
    }
  
    function bindContact(contact) {
      if (!contact) return;
      var nameEl = document.getElementById('agencyContactName');
      var emailEl = document.getElementById('agencyContactEmail');
      var phoneEl = document.getElementById('agencyContactPhone');
      if (nameEl) nameEl.value = contact.name || '';
      if (emailEl) emailEl.value = contact.email || '';
      if (phoneEl) phoneEl.value = contact.phone || '';
    }
  
    function loadConfig() {
      fetch(API, { credentials: 'include' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.success) {
            throw new Error(data.message || 'Failed to load Agency Contact.');
          }
          bindContact(data.contact);
        })
        .catch(function (err) {
          showAlert(err.message || 'Unable to load Agency Contact.', 'danger');
        });
    }
  
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btnSaveAgencyContact');
        if (btn) btn.disabled = true;
  
        var payload = {
          name: document.getElementById('agencyContactName').value.trim(),
          email: document.getElementById('agencyContactEmail').value.trim(),
          phone: document.getElementById('agencyContactPhone').value.trim(),
        };
  
        fetch(API, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        })
          .then(function (res) {
            return res.json().then(function (data) {
              if (!res.ok || !data.success) {
                throw new Error(data.message || 'Update failed.');
              }
              return data;
            });
          })
          .then(function (data) {
            showAlert(data.message || 'Agency Contact saved.', 'success');
            bindContact(data.contact);
          })
          .catch(function (err) {
            showAlert(err.message, 'danger');
          })
          .finally(function () {
            if (btn) btn.disabled = false;
          });
      });
    }
  
    window.AgencyContactSettings = { load: loadConfig };
  
    var section = document.getElementById('section-settings');
    if (section && section.classList.contains('active')) {
      loadConfig();
    }
  })();