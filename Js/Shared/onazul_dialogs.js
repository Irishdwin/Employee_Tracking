/**
 * window.OnazulDialogs.confirm(message) / .prompt(message, defaultValue)
 *
 * This file was referenced by Html/TeamLead/team_lead_dashboard.html
 * (`<script src="../../Js/Shared/onazul_dialogs.js">`) but did not exist
 * anywhere in the project — a genuine missing file, not a wrong path or a
 * rename (no equivalent utility exists elsewhere; the closest sibling,
 * Js/Shared/common_logout_confirm.js, only handles a single fixed logout
 * confirmation, not general confirm/prompt calls). Both
 * window.OnazulDialogs.prompt(...) and window.OnazulDialogs.confirm(...)
 * are actually called elsewhere in team_lead_dashboard.html (overtime
 * request / endorsement actions), so simply removing the <script> tag
 * would trade a console 404 for a runtime "Cannot read properties of
 * undefined" the next time those buttons are used — this file supplies the
 * two methods actually used, nothing more, styled like the existing
 * Bootstrap-modal dialogs already in the app (see
 * common_logout_confirm.js) rather than introducing a new dialog style.
 */
(function () {
    'use strict';
  
    function escapeHtml(t) {
      if (t == null) return '';
      var d = document.createElement('div');
      d.textContent = String(t);
      return d.innerHTML;
    }
  
    function ensureModal() {
      var existing = document.getElementById('onazulDialogModal');
      if (existing) {
        return { el: existing, instance: bootstrap.Modal.getOrCreateInstance(existing) };
      }
      var wrapper = document.createElement('div');
      wrapper.innerHTML = '' +
        '<div class="modal fade" id="onazulDialogModal" tabindex="-1" aria-hidden="true">' +
        '  <div class="modal-dialog modal-dialog-centered modal-sm">' +
        '    <div class="modal-content">' +
        '      <div class="modal-header py-2">' +
        '        <h5 class="modal-title" id="onazulDialogTitle" style="font-size:0.95rem;">Confirm</h5>' +
        '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
        '      </div>' +
        '      <div class="modal-body py-3">' +
        '        <p class="mb-2" id="onazulDialogMessage" style="font-size:0.9rem;"></p>' +
        '        <input type="text" class="form-control form-control-sm d-none" id="onazulDialogInput">' +
        '      </div>' +
        '      <div class="modal-footer py-2">' +
        '        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" id="onazulDialogCancel">Cancel</button>' +
        '        <button type="button" class="btn btn-primary btn-sm" id="onazulDialogOk">OK</button>' +
        '      </div>' +
        '    </div>' +
        '  </div>' +
        '</div>';
      document.body.appendChild(wrapper.firstChild);
      var el = document.getElementById('onazulDialogModal');
      return { el: el, instance: new bootstrap.Modal(el) };
    }
  
    function showDialog(message, opts) {
      opts = opts || {};
      return new Promise(function (resolve) {
        var modal = ensureModal();
        var el = modal.el;
        var msgEl = el.querySelector('#onazulDialogMessage');
        var inputEl = el.querySelector('#onazulDialogInput');
        var okBtn = el.querySelector('#onazulDialogOk');
        var cancelBtn = el.querySelector('#onazulDialogCancel');
        var titleEl = el.querySelector('#onazulDialogTitle');
  
        titleEl.textContent = opts.title || (opts.isPrompt ? 'Input Required' : 'Confirm');
        msgEl.innerHTML = escapeHtml(message || '');
        msgEl.style.whiteSpace='pre-line';okBtn.textContent=opts.confirmText||'OK';
  
        if (opts.isPrompt) {
          inputEl.classList.remove('d-none');
          inputEl.value = opts.defaultValue || '';
        } else {
          inputEl.classList.add('d-none');
          inputEl.value = '';
        }
  
        var settled = false;
        function settle(value) {
          if (settled) return;
          settled = true;
          okBtn.onclick = null;
          cancelBtn.onclick = null;
          el.removeEventListener('hidden.bs.modal', onHidden);
          modal.instance.hide();
          resolve(value);
        }
        function onHidden() {
          // Dismissed via backdrop click, Esc, or the Cancel/close button —
          // treat exactly like Cancel for both confirm() and prompt().
          settle(opts.isPrompt ? null : false);
        }
  
        okBtn.onclick = function () {
          settle(opts.isPrompt ? inputEl.value : true);
        };
        cancelBtn.onclick = function () {
          settle(opts.isPrompt ? null : false);
        };
        el.addEventListener('hidden.bs.modal', onHidden);
  
        modal.instance.show();
        if (opts.isPrompt) {
          el.addEventListener('shown.bs.modal', function focusOnce() {
            inputEl.focus();
            el.removeEventListener('shown.bs.modal', focusOnce);
          });
        }
      });
    }
  
    window.OnazulDialogs = {
      confirm: function (message, options) {
        return showDialog(message, Object.assign({ isPrompt: false },options||{}));
      },
      prompt: function (message, defaultValue) {
        return showDialog(message, { isPrompt: true, defaultValue: defaultValue });
      }
    };
  })();