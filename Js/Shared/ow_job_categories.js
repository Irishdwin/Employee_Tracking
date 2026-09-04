/**
 * Dynamic job categories — load dropdowns + Add New Category modal (Admin/HR/Team Lead).
 */
(function (global) {
  'use strict';

  var API = '../../Api/Common/manage_categories.php';
  var categories = [];
  var modalInjected = false;
  var DEFAULT_PLACEHOLDER = '<option value="" selected disabled>-- Select Job Position --</option>';

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function categorySelects() {
    return document.querySelectorAll(
      'select[data-job-category-select], select[name="job_category"], #jor-category, #joJobCategory'
    );
  }

  function injectModal() {
    if (modalInjected || document.getElementById('owAddCategoryModal')) {
      modalInjected = true;
      return;
    }
    modalInjected = true;
    var wrap = document.createElement('div');
    wrap.innerHTML =
      '<div class="modal fade" id="owAddCategoryModal" tabindex="-1" aria-labelledby="owAddCategoryModalLabel" aria-hidden="true">' +
      '  <div class="modal-dialog modal-dialog-centered">' +
      '    <div class="modal-content">' +
      '      <div class="modal-header">' +
      '        <h5 class="modal-title" id="owAddCategoryModalLabel">Add New Job Category</h5>' +
      '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
      '      </div>' +
      '      <form id="owAddCategoryForm">' +
      '        <div class="modal-body">' +
      '          <div id="owAddCategoryAlert" class="alert d-none" role="alert"></div>' +
      '          <label for="owCategoryNameInput" class="form-label">Category name</label>' +
      '          <input type="text" class="form-control" id="owCategoryNameInput" name="category_name" ' +
      '            placeholder="e.g. Retail & Sales" maxlength="100" required autocomplete="off">' +
      '          <p class="form-text text-muted mb-0">Examples: Sales Clerk, Merchandiser, Delivery Driver.</p>' +
      '        </div>' +
      '        <div class="modal-footer">' +
      '          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
      '          <button type="submit" class="btn btn-primary" id="owSaveCategoryBtn">Save Category</button>' +
      '        </div>' +
      '      </form>' +
      '    </div>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(wrap.firstElementChild);
    bindModalForm();
  }

  function bindModalForm() {
    var form = document.getElementById('owAddCategoryForm');
    if (!form || form.dataset.bound === '1') return;
    form.dataset.bound = '1';
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = document.getElementById('owCategoryNameInput');
      var alertEl = document.getElementById('owAddCategoryAlert');
      var btn = document.getElementById('owSaveCategoryBtn');
      var name = input ? String(input.value || '').trim() : '';
      if (!name) return;

      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving…';
      }
      if (alertEl) alertEl.classList.add('d-none');

      fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ category_name: name })
      })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (resp) {
          var data = resp.data || {};
          if (!resp.ok || !data.success) {
            if (alertEl) {
              alertEl.className = 'alert alert-danger';
              alertEl.textContent = data.message || 'Could not save category.';
              alertEl.classList.remove('d-none');
            }
            return;
          }
          var cat = data.category;
          if (cat && cat.id) {
            var exists = categories.some(function (c) { return c.id === cat.id; });
            if (!exists) {
              categories.push(cat);
              categories.sort(function (a, b) {
                return a.category_name.localeCompare(b.category_name, 'en');
              });
            }
            populateSelects(cat.id);
          } else {
            loadCategories().then(function () { populateSelects(); });
          }
          window.alert(data.message || 'Category saved successfully.');
          if (input) input.value = '';
          var modalEl = document.getElementById('owAddCategoryModal');
          if (modalEl && global.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          }
        })
        .catch(function () {
          if (alertEl) {
            alertEl.className = 'alert alert-danger';
            alertEl.textContent = 'Network error while saving category.';
            alertEl.classList.remove('d-none');
          }
        })
        .finally(function () {
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Save Category';
          }
        });
    });
  }

  function placeholderHtmlFor(sel) {
    var custom = sel.getAttribute('data-category-placeholder');
    if (custom) {
      return '<option value="" selected disabled>' + esc(custom) + '</option>';
    }
    return DEFAULT_PLACEHOLDER;
  }

  function populateSelects(selectId) {
    categorySelects().forEach(function (sel) {
      var current = selectId != null ? String(selectId) : String(sel.value || '');
      sel.innerHTML = placeholderHtmlFor(sel) + categories.map(function (c) {
        return '<option value="' + c.id + '">' + esc(c.category_name) + '</option>';
      }).join('');
      if (current && current !== '0') {
        sel.value = current;
      } else {
        sel.value = '';
      }
    });
  }

  function injectAddCategoryLinks() {
    categorySelects().forEach(function (sel) {
      var parent = sel.closest('.mb-3, .space-y-3, div');
      if (!parent || parent.querySelector('.ow-add-category-link')) return;
      var link = document.createElement('button');
      link.type = 'button';
      link.className = 'btn btn-link btn-sm p-0 ow-add-category-link';
      link.textContent = '+ Add New Category';
      link.addEventListener('click', function (e) {
        e.preventDefault();
        openAddModal();
      });
      if (sel.nextElementSibling) {
        sel.parentNode.insertBefore(link, sel.nextElementSibling);
      } else {
        sel.parentNode.appendChild(link);
      }
    });
  }

  function openAddModal() {
    injectModal();
    var modalEl = document.getElementById('owAddCategoryModal');
    if (modalEl && global.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  function loadCategories() {
    return fetch(API, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.success && Array.isArray(data.categories)) {
          categories = data.categories;
        }
        return categories;
      })
      .catch(function () {
        categories = [];
        return categories;
      });
  }

  function showSubmissionAlert(container, message, type) {
    if (!container) return;
    var el = document.getElementById('owJobCategorySubmissionAlert');
    if (!el) {
      el = document.createElement('div');
      el.id = 'owJobCategorySubmissionAlert';
      el.setAttribute('role', 'alert');
      container.insertBefore(el, container.firstChild);
    }
    el.className = 'alert alert-' + (type || 'warning') + ' mb-3';
    el.innerHTML = esc(message);
  }

  function init(options) {
    if (options && options.apiUrl) API = options.apiUrl;
    injectModal();
    return loadCategories().then(function () {
      populateSelects();
      injectAddCategoryLinks();
    });
  }

  global.OwJobCategories = {
    init: init,
    load: loadCategories,
    populate: populateSelects,
    openAddModal: openAddModal,
    showSubmissionAlert: showSubmissionAlert,
    getCategories: function () { return categories.slice(); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(); });
  } else {
    init();
  }
})(window);
