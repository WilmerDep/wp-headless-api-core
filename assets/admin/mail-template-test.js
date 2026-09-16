(function () {
  'use strict';

  function hidden(name, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-mail-test-send]');
    if (!button) return;

    var root = button.closest('[data-mail-template-test]');
    if (!root) return;

    var actionUrl = root.getAttribute('data-action-url') || '';
    var action = root.getAttribute('data-action') || '';
    var templateId = root.getAttribute('data-template-id') || '';
    var nonceName = root.getAttribute('data-nonce-name') || '';
    var nonce = root.getAttribute('data-nonce') || '';
    var recipientField = root.querySelector('[data-mail-test-recipient]');
    var sampleField = root.querySelector('[data-mail-test-form]');
    var recipient = recipientField ? recipientField.value.trim() : '';
    var formId = sampleField ? sampleField.value : '0';

    if (!actionUrl || !action || !templateId || !nonceName || !nonce) return;

    var form = document.createElement('form');
    form.method = 'post';
    form.action = actionUrl;
    form.style.display = 'none';
    form.appendChild(hidden('action', action));
    form.appendChild(hidden('template_id', templateId));
    form.appendChild(hidden(nonceName, nonce));
    form.appendChild(hidden('recipient', recipient));
    form.appendChild(hidden('form_id', formId));
    document.body.appendChild(form);
    form.submit();
  });
})();
