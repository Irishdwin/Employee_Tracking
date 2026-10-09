(function () {
  'use strict';
  let queue = Promise.resolve();
  function show(kind, message, value, title, label) {
    const task = () => new Promise(resolve => {
      const previous = document.activeElement;
      const dialog = document.createElement('dialog');
      dialog.className = 'app-feedback-dialog';
      const heading = document.createElement('h2');
      heading.textContent = title || (kind === 'alert' ? 'System message' : kind === 'prompt' ? 'Enter details' : 'Please confirm');
      const description = document.createElement('p'); description.textContent = message;
      const form = document.createElement('form'); form.method = 'dialog';
      let input;
      if (kind === 'prompt') {
        input = document.createElement('input'); input.className = 'form-control'; input.value = value || '';
        input.type = /end date/i.test(message) ? 'date' : 'text'; input.required = true;
        input.setAttribute('aria-label', message); form.append(input);
      }
      const actions = document.createElement('div'); actions.className = 'app-feedback-actions';
      if (kind !== 'alert') {
        const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn btn-outline-secondary'; cancel.textContent = 'Cancel';
        cancel.onclick = () => dialog.close('cancel'); actions.append(cancel);
      }
      const accept = document.createElement('button'); accept.type = 'submit'; accept.className = 'btn btn-primary'; accept.textContent = label || (kind === 'alert' ? 'OK' : 'Continue');
      actions.append(accept); form.append(actions); dialog.append(heading, description, form);
      const titleId = 'app-feedback-title'; heading.id = titleId; dialog.setAttribute('aria-labelledby', titleId);
      form.onsubmit = event => { event.preventDefault(); dialog.close('accept'); };
      dialog.addEventListener('close', () => { const result = dialog.returnValue === 'accept'; dialog.remove(); if (previous && previous.isConnected) previous.focus(); resolve(kind === 'prompt' ? (result ? input.value : null) : result); }, {once:true});
      document.body.append(dialog); dialog.showModal(); (input || accept).focus();
    });
    const result = queue.then(task); queue = result.catch(() => {}); return result;
  }
  window.AppDialog = {alert: (message, title) => show('alert', message, null, title), confirm: (message, title, label) => show('confirm', message, null, title, label), prompt: (message, value) => show('prompt', message, value)};
})();
