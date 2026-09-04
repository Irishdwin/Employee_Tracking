/**
 * Searchable select — trigger + dropdown panel with search input and option list.
 * Matches combobox UX: click trigger → panel opens → type to filter → click option to select.
 */
(function (global) {
  'use strict';

  var openInstance = null;

  function esc(text) {
    var d = document.createElement('div');
    d.textContent = text == null ? '' : String(text);
    return d.innerHTML;
  }

  /**
   * @param {HTMLElement} root
   * @param {{ placeholder?: string, onSelect?: function(string): void }} config
   */
  function SearchableSelect(root, config) {
    if (!root) throw new Error('SearchableSelect: root element required');
    this.root = root;
    this.trigger = root.querySelector('.searchable-select__trigger');
    this.triggerLabel = root.querySelector('.searchable-select__trigger-label');
    this.panel = root.querySelector('.searchable-select__panel');
    this.searchInput = root.querySelector('.searchable-select__search');
    this.list = root.querySelector('.searchable-select__list');
    this.placeholder = (config && config.placeholder) || 'Select option';
    this.onSelect = (config && config.onSelect) || function () {};
    this.options = [];
    this.selectedValue = '';
    this.highlightIndex = -1;
    this.disabled = false;

    if (this.triggerLabel) this.triggerLabel.textContent = this.placeholder;
    if (this.searchInput) {
      this.searchInput.setAttribute('autocomplete', 'off');
      this.searchInput.placeholder = 'Search…';
    }
    if (this.trigger) {
      this.trigger.setAttribute('type', 'button');
      this.trigger.setAttribute('aria-haspopup', 'listbox');
      this.trigger.setAttribute('aria-expanded', 'false');
    }

    this._bindEvents();
  }

  SearchableSelect.prototype.setOptions = function (items) {
    this.options = (items || []).slice().sort(function (a, b) {
      return String(a).localeCompare(String(b), undefined, { sensitivity: 'base' });
    });
    this.highlightIndex = -1;
    if (this.isOpen()) this._renderList(this._filterText());
  };

  SearchableSelect.prototype.getValue = function () {
    return this.selectedValue;
  };

  SearchableSelect.prototype.setValue = function (value, silent) {
    var val = value == null ? '' : String(value).trim();
    this.selectedValue = val;
    this._syncTriggerLabel();
    if (!silent && val) this.onSelect(val);
  };

  SearchableSelect.prototype.clear = function () {
    this.selectedValue = '';
    this.highlightIndex = -1;
    if (this.searchInput) this.searchInput.value = '';
    this._syncTriggerLabel();
    if (this.list) this.list.innerHTML = '';
    this.close();
  };

  SearchableSelect.prototype.setDisabled = function (disabled) {
    this.disabled = !!disabled;
    this.root.classList.toggle('is-disabled', this.disabled);
    if (this.trigger) this.trigger.disabled = this.disabled;
    if (this.searchInput) this.searchInput.disabled = this.disabled;
    if (this.disabled) this.close();
  };

  SearchableSelect.prototype.isOpen = function () {
    return this.root.classList.contains('is-open');
  };

  SearchableSelect.prototype.open = function () {
    if (this.disabled) return;
    if (openInstance && openInstance !== this) openInstance.close();
    openInstance = this;
    this.root.classList.add('is-open');
    if (this.trigger) this.trigger.setAttribute('aria-expanded', 'true');
    if (this.searchInput) {
      this.searchInput.value = '';
      this.searchInput.disabled = false;
    }
    this.highlightIndex = -1;
    this._renderList('');
    if (this.searchInput) {
      window.requestAnimationFrame(function () {
        if (openInstance === this) this.searchInput.focus();
      }.bind(this));
    }
  };

  SearchableSelect.prototype.close = function () {
    this.root.classList.remove('is-open');
    if (this.trigger) this.trigger.setAttribute('aria-expanded', 'false');
    if (this.searchInput) this.searchInput.value = '';
    this.highlightIndex = -1;
    if (openInstance === this) openInstance = null;
  };

  SearchableSelect.prototype._syncTriggerLabel = function () {
    if (!this.triggerLabel) return;
    if (this.selectedValue) {
      this.triggerLabel.textContent = this.selectedValue;
      this.triggerLabel.classList.remove('is-placeholder');
    } else {
      this.triggerLabel.textContent = this.placeholder;
      this.triggerLabel.classList.add('is-placeholder');
    }
  };

  SearchableSelect.prototype._filterText = function () {
    return this.searchInput ? (this.searchInput.value || '').trim() : '';
  };

  SearchableSelect.prototype._renderList = function (query) {
    if (!this.list) return;
    var q = (query || '').toLowerCase();
    var filtered = this.options.filter(function (opt) {
      return !q || String(opt).toLowerCase().indexOf(q) !== -1;
    });

    if (!this.options.length) {
      this.list.innerHTML = '<li class="searchable-select__empty">No options available</li>';
      return;
    }

    if (!filtered.length) {
      this.list.innerHTML = '<li class="searchable-select__empty">No matches found</li>';
      return;
    }

    var self = this;
    var html = '';
    filtered.forEach(function (opt, idx) {
      var selected = opt === self.selectedValue;
      var highlighted = idx === self.highlightIndex;
      html += '<li class="searchable-select__option' +
        (selected ? ' is-selected' : '') +
        (highlighted ? ' is-highlighted' : '') +
        '" role="option" data-value="' + esc(opt) + '">' + esc(opt) + '</li>';
    });
    this.list.innerHTML = html;
  };

  SearchableSelect.prototype._selectOption = function (value) {
    if (!value) return;
    this.selectedValue = value;
    this._syncTriggerLabel();
    this.close();
    this.onSelect(value);
  };

  SearchableSelect.prototype._visibleOptions = function () {
    if (!this.list) return [];
    return Array.prototype.slice.call(this.list.querySelectorAll('.searchable-select__option[data-value]'));
  };

  SearchableSelect.prototype._bindEvents = function () {
    var self = this;

    if (this.trigger) {
      this.trigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (self.disabled) return;
        if (self.isOpen()) self.close();
        else self.open();
      });
    }

    if (this.searchInput) {
      this.searchInput.addEventListener('click', function (e) {
        e.stopPropagation();
      });

      this.searchInput.addEventListener('input', function () {
        self.highlightIndex = -1;
        self._renderList(self._filterText());
      });

      this.searchInput.addEventListener('keydown', function (e) {
        var items = self._visibleOptions();
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          self.highlightIndex = Math.min(self.highlightIndex + 1, items.length - 1);
          self._renderList(self._filterText());
          items = self._visibleOptions();
          if (items[self.highlightIndex]) items[self.highlightIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          self.highlightIndex = Math.max(self.highlightIndex - 1, 0);
          self._renderList(self._filterText());
          items = self._visibleOptions();
          if (items[self.highlightIndex]) items[self.highlightIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
          e.preventDefault();
          items = self._visibleOptions();
          if (self.highlightIndex >= 0 && items[self.highlightIndex]) {
            self._selectOption(items[self.highlightIndex].getAttribute('data-value'));
          } else if (items.length === 1) {
            self._selectOption(items[0].getAttribute('data-value'));
          }
        } else if (e.key === 'Escape') {
          e.preventDefault();
          self.close();
        }
      });
    }

    if (this.panel) {
      this.panel.addEventListener('click', function (e) {
        e.stopPropagation();
      });
    }

    if (this.list) {
      this.list.addEventListener('click', function (e) {
        var opt = e.target.closest('.searchable-select__option[data-value]');
        if (!opt) return;
        e.stopPropagation();
        self._selectOption(opt.getAttribute('data-value'));
      });
    }
  };

  document.addEventListener('click', function (e) {
    if (!openInstance) return;
    if (!openInstance.root.contains(e.target)) openInstance.close();
  });

  global.SearchableSelect = SearchableSelect;
})(window);
