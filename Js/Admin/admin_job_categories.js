/**
 * Admin > Job Categories management (Command Center section-job-categories).
 * Category -> Classification -> Sub-classification, each independently
 * addable, editable inline, and toggleable active/inactive.
 */
(function (global) {
    'use strict';
  
    var CATEGORIES_API = '../../Api/Common/manage_categories.php';
    var CLASSIFICATIONS_API = '../../Api/Common/manage_classifications.php';
    var SUB_CLASSIFICATIONS_API = '../../Api/Common/manage_sub_classifications.php';
  
    var state = {
      selectedCategoryId: null,
      selectedClassificationId: null
    };
  
    function $(id) { return document.getElementById(id); }
  
    function esc(text) {
      if (text == null) return '';
      var d = document.createElement('div');
      d.textContent = String(text);
      return d.innerHTML;
    }
  
    function showAlert(message, type) {
      var el = $('jobCategoriesPageAlert');
      if (!el) return;
      el.className = 'alert alert-' + (type || 'info');
      el.textContent = message;
      el.classList.remove('d-none');
      setTimeout(function () { el.classList.add('d-none'); }, 4000);
    }
  
    function postJson(url, body) {
      return fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }).then(function (r) { return r.json().catch(function () { return { success: false, message: 'Invalid server response.' }; }); });
    }
  
    function getJson(url) {
      return fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return { success: false, message: 'Invalid server response.' }; }); });
    }
  
    /**
     * Renders a manageable list (category, classification, or sub-classification
     * rows) into a container: click a row to select it, pencil icon to rename
     * inline (no native prompt()), switch to toggle active/inactive, optional
     * star to mark the row as the category's default classification.
     */
    function renderList(container, items, opts) {
      container.innerHTML = '';
      if (!items.length) {
        container.innerHTML = '<div class="text-center text-muted py-4">' + esc(opts.emptyText) + '</div>';
        return;
      }
  
      items.forEach(function (item) {
        var row = document.createElement('div');
        row.className = 'list-group-item d-flex align-items-center justify-content-between gap-2 px-2 py-2' +
          (opts.isSelected && opts.isSelected(item) ? ' active-row' : '');
        if (opts.isSelected && opts.isSelected(item)) {
          row.style.background = 'rgba(0,86,179,0.06)';
          row.style.borderRadius = '6px';
        }
  
        var left = document.createElement('div');
        left.className = 'd-flex align-items-center gap-2 flex-grow-1';
        left.style.cursor = opts.onSelect ? 'pointer' : 'default';
        left.style.opacity = item.is_active === false ? '0.55' : '1';
  
        var label = document.createElement('span');
        label.textContent = opts.getName(item);
        left.appendChild(label);
  
        if (opts.isDefault && opts.isDefault(item)) {
          var badge = document.createElement('span');
          badge.className = 'badge bg-primary-subtle text-primary';
          badge.textContent = 'Default';
          left.appendChild(badge);
        }
  
        if (opts.onSelect) {
          left.addEventListener('click', function () { opts.onSelect(item); });
        }
        row.appendChild(left);
  
        var actions = document.createElement('div');
        actions.className = 'd-flex align-items-center gap-2';
  
        if (opts.onSetDefault && !(opts.isDefault && opts.isDefault(item))) {
          var starBtn = document.createElement('button');
          starBtn.type = 'button';
          starBtn.className = 'btn btn-sm btn-link p-0 text-muted';
          starBtn.title = 'Make default for this category';
          starBtn.innerHTML = '<i class="bi bi-star"></i>';
          starBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            opts.onSetDefault(item);
          });
          actions.appendChild(starBtn);
        }
  
        var editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'btn btn-sm btn-link p-0 text-muted';
        editBtn.title = 'Rename';
        editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
        editBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          startInlineEdit(row, left, label, item, opts);
        });
        actions.appendChild(editBtn);
  
        var toggle = document.createElement('div');
        toggle.className = 'form-check form-switch mb-0';
        var toggleInput = document.createElement('input');
        toggleInput.type = 'checkbox';
        toggleInput.className = 'form-check-input';
        toggleInput.checked = item.is_active !== false;
        toggleInput.title = toggleInput.checked ? 'Active — click to deactivate' : 'Inactive — click to activate';
        toggleInput.addEventListener('click', function (e) { e.stopPropagation(); });
        toggleInput.addEventListener('change', function () {
          opts.onToggleActive(item, toggleInput.checked);
        });
        toggle.appendChild(toggleInput);
        actions.appendChild(toggle);
  
        row.appendChild(actions);
        container.appendChild(row);
      });
    }
  
    /** Swaps a row's label for a text input + Save/Cancel, in place — no native prompt(). */
    function startInlineEdit(row, left, label, item, opts) {
      if (row.querySelector('input[type="text"]')) return; // already editing
      var input = document.createElement('input');
      input.type = 'text';
      input.className = 'form-control form-control-sm';
      input.value = opts.getName(item);
      label.replaceWith(input);
      input.focus();
      input.select();
  
      var saveBtn = document.createElement('button');
      saveBtn.type = 'button';
      saveBtn.className = 'btn btn-sm btn-success py-0 px-2';
      saveBtn.innerHTML = '<i class="bi bi-check"></i>';
      var cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'btn btn-sm btn-outline-secondary py-0 px-2';
      cancelBtn.innerHTML = '<i class="bi bi-x"></i>';
  
      var editControls = document.createElement('div');
      editControls.className = 'd-flex gap-1';
      editControls.appendChild(saveBtn);
      editControls.appendChild(cancelBtn);
      left.appendChild(editControls);
  
      function finish() { opts.onRefresh(); }
      saveBtn.addEventListener('click', function () {
        var newName = input.value.trim();
        if (!newName) return;
        opts.onRename(item, newName).then(finish);
      });
      cancelBtn.addEventListener('click', finish);
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); saveBtn.click(); }
        if (e.key === 'Escape') { e.preventDefault(); cancelBtn.click(); }
      });
    }
  
    // ---------------- Categories (column 1) ----------------
  
    function loadCategories() {
      var container = $('categoriesList');
      if (!container) return;
      getJson(CATEGORIES_API).then(function (res) {
        var categories = (res && res.success && Array.isArray(res.categories)) ? res.categories : [];
        renderList(container, categories, {
          getName: function (c) { return c.category_name; },
          emptyText: 'No categories yet.',
          isSelected: function (c) { return c.id === state.selectedCategoryId; },
          onSelect: function (c) { selectCategory(c.id); },
          onToggleActive: function (c, active) {
            postJson(CATEGORIES_API, { action: 'toggle_active', id: c.id, is_active: active ? 1 : 0 })
              .then(function (r) {
                if (!r.success) showAlert(r.message || 'Failed to update category.', 'danger');
                loadCategories();
              });
          },
          onRename: function (c, newName) {
            return postJson(CATEGORIES_API, { action: 'edit', id: c.id, category_name: newName })
              .then(function (r) {
                if (!r.success) showAlert(r.message || 'Failed to rename category.', 'danger');
                loadCategories();
              });
          }
        });
      });
    }
  
    function selectCategory(categoryId) {
      state.selectedCategoryId = categoryId;
      state.selectedClassificationId = null;
      loadCategories();
      loadClassifications();
      var subForm = $('addSubClassificationForm');
      if (subForm) subForm.classList.add('d-none');
      var subList = $('subClassificationsList');
      if (subList) subList.innerHTML = '<div class="text-center text-muted py-4">Select a classification to view its sub-classifications.</div>';
      var subLabel = $('subClassificationsForLabel');
      if (subLabel) subLabel.textContent = 'Select a classification';
    }
  
    // ---------------- Classifications (column 2) ----------------
  
    function loadClassifications() {
      var container = $('classificationsList');
      var form = $('addClassificationForm');
      var label = $('classificationsForLabel');
      if (!container) return;
  
      if (!state.selectedCategoryId) {
        container.innerHTML = '<div class="text-center text-muted py-4">Select a category to view its classifications.</div>';
        if (form) form.classList.add('d-none');
        if (label) label.textContent = 'Select a category';
        return;
      }
      if (form) form.classList.remove('d-none');
  
      getJson(CLASSIFICATIONS_API + '?category_id=' + encodeURIComponent(state.selectedCategoryId)).then(function (res) {
        var list = (res && res.success && Array.isArray(res.classifications)) ? res.classifications : [];
        if (label) label.textContent = list.length + ' classification' + (list.length === 1 ? '' : 's');
        renderList(container, list, {
          getName: function (c) { return c.classification_name; },
          emptyText: 'No classifications yet for this category.',
          isSelected: function (c) { return c.id === state.selectedClassificationId; },
          isDefault: function (c) { return c.is_default; },
          onSelect: function (c) { selectClassification(c.id); },
          onSetDefault: function (c) {
            postJson(CLASSIFICATIONS_API, { action: 'set_default', id: c.id }).then(function (r) {
              if (!r.success) showAlert(r.message || 'Failed to set default.', 'danger');
              loadClassifications();
            });
          },
          onToggleActive: function (c, active) {
            postJson(CLASSIFICATIONS_API, { action: 'toggle_active', id: c.id, is_active: active ? 1 : 0 })
              .then(function (r) {
                if (!r.success) showAlert(r.message || 'Failed to update classification.', 'danger');
                loadClassifications();
              });
          },
          onRename: function (c, newName) {
            return postJson(CLASSIFICATIONS_API, { action: 'edit', id: c.id, classification_name: newName })
              .then(function (r) {
                if (!r.success) showAlert(r.message || 'Failed to rename classification.', 'danger');
                loadClassifications();
              });
          }
        });
      });
    }
  
    function selectClassification(classificationId) {
      state.selectedClassificationId = classificationId;
      loadClassifications();
      loadSubClassifications();
    }
  
    // ---------------- Sub-classifications (column 3) ----------------
  
    function loadSubClassifications() {
      var container = $('subClassificationsList');
      var form = $('addSubClassificationForm');
      var label = $('subClassificationsForLabel');
      if (!container) return;
  
      if (!state.selectedClassificationId) {
        container.innerHTML = '<div class="text-center text-muted py-4">Select a classification to view its sub-classifications.</div>';
        if (form) form.classList.add('d-none');
        if (label) label.textContent = 'Select a classification';
        return;
      }
      if (form) form.classList.remove('d-none');
  
      getJson(SUB_CLASSIFICATIONS_API + '?classification_id=' + encodeURIComponent(state.selectedClassificationId)).then(function (res) {
        var list = (res && res.success && Array.isArray(res.sub_classifications)) ? res.sub_classifications : [];
        if (label) label.textContent = list.length + ' sub-classification' + (list.length === 1 ? '' : 's');
        renderList(container, list, {
          getName: function (s) { return s.sub_classification_name; },
          emptyText: 'No sub-classifications yet.',
          onToggleActive: function (s, active) {
            postJson(SUB_CLASSIFICATIONS_API, { action: 'toggle_active', id: s.id, is_active: active ? 1 : 0 })
              .then(function (r) {
                if (!r.success) showAlert(r.message || 'Failed to update sub-classification.', 'danger');
                loadSubClassifications();
              });
          },
          onRename: function (s, newName) {
            return postJson(SUB_CLASSIFICATIONS_API, { action: 'edit', id: s.id, sub_classification_name: newName })
              .then(function (r) {
                if (!r.success) showAlert(r.message || 'Failed to rename sub-classification.', 'danger');
                loadSubClassifications();
              });
          }
        });
      });
    }
  
    // ---------------- Add forms ----------------
  
    function bindAddForms() {
      var addCategoryForm = $('addCategoryForm');
      if (addCategoryForm) {
        addCategoryForm.addEventListener('submit', function (e) {
          e.preventDefault();
          var input = $('newCategoryName');
          var name = input.value.trim();
          if (!name) return;
          postJson(CATEGORIES_API, { action: 'add', category_name: name }).then(function (r) {
            if (!r.success) { showAlert(r.message || 'Failed to add category.', 'danger'); return; }
            input.value = '';
            loadCategories();
          });
        });
      }
  
      var addClassificationForm = $('addClassificationForm');
      if (addClassificationForm) {
        addClassificationForm.addEventListener('submit', function (e) {
          e.preventDefault();
          if (!state.selectedCategoryId) return;
          var input = $('newClassificationName');
          var name = input.value.trim();
          if (!name) return;
          postJson(CLASSIFICATIONS_API, { action: 'add', category_id: state.selectedCategoryId, classification_name: name }).then(function (r) {
            if (!r.success) { showAlert(r.message || 'Failed to add classification.', 'danger'); return; }
            input.value = '';
            loadClassifications();
          });
        });
      }
  
      var addSubClassificationForm = $('addSubClassificationForm');
      if (addSubClassificationForm) {
        addSubClassificationForm.addEventListener('submit', function (e) {
          e.preventDefault();
          if (!state.selectedClassificationId) return;
          var input = $('newSubClassificationName');
          var name = input.value.trim();
          if (!name) return;
          postJson(SUB_CLASSIFICATIONS_API, { action: 'add', classification_id: state.selectedClassificationId, sub_classification_name: name }).then(function (r) {
            if (!r.success) { showAlert(r.message || 'Failed to add sub-classification.', 'danger'); return; }
            input.value = '';
            loadSubClassifications();
          });
        });
      }
    }
  
    var bound = false;
    function load() {
      if (!bound) {
        bindAddForms();
        bound = true;
      }
      loadCategories();
      loadClassifications();
      loadSubClassifications();
    }
  
    global.JobCategoriesAdmin = { load: load };
  })(window);