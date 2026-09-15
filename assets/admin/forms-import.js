(function () {
  'use strict';

  function text(node) {
    return node ? String(node.textContent || '').trim() : '';
  }

  function enhanceFileStep(root) {
    var input = root.querySelector('#headless-forms-package-file');
    if (!input) return;

    var field = input.closest('.headless-directory-field');
    var form = input.closest('form');
    if (!field || !form) return;

    var label = field.querySelector('label');
    if (label) label.hidden = true;

    var dropzone = document.createElement('div');
    dropzone.className = 'headless-directory-dropzone';
    dropzone.tabIndex = 0;
    dropzone.setAttribute('role', 'button');
    dropzone.setAttribute('aria-label', 'Seleccionar paquete JSON');

    var icon = document.createElement('span');
    icon.className = 'dashicons dashicons-upload';
    icon.setAttribute('aria-hidden', 'true');

    var strong = document.createElement('strong');
    strong.textContent = 'Arrastra aquí tu paquete JSON';

    var hint = document.createElement('span');
    hint.textContent = 'o haz clic para seleccionar .json';

    input.classList.add('headless-directory-import-file');
    dropzone.appendChild(icon);
    dropzone.appendChild(strong);
    dropzone.appendChild(hint);
    dropzone.appendChild(input);
    field.appendChild(dropzone);

    var fileName = document.createElement('div');
    fileName.className = 'headless-directory-import-file-name';
    fileName.setAttribute('aria-live', 'polite');
    field.appendChild(fileName);

    var submit = form.querySelector('input[type="submit"], button[type="submit"]');
    if (submit) submit.disabled = !input.files || !input.files.length;

    function updateFile() {
      var file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) {
        fileName.textContent = '';
        if (submit) submit.disabled = true;
        return;
      }
      var size = file.size >= 1048576
        ? (file.size / 1048576).toFixed(2) + ' MB'
        : Math.max(1, Math.round(file.size / 1024)) + ' KB';
      fileName.textContent = file.name + ' · ' + size;
      if (submit) submit.disabled = false;
    }

    input.addEventListener('change', updateFile);
    dropzone.addEventListener('keydown', function (event) {
      if ((event.key === 'Enter' || event.key === ' ') && event.target === dropzone) {
        event.preventDefault();
        input.click();
      }
    });

    ['dragenter', 'dragover'].forEach(function (eventName) {
      dropzone.addEventListener(eventName, function () { dropzone.classList.add('is-dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (eventName) {
      dropzone.addEventListener(eventName, function () { dropzone.classList.remove('is-dragover'); });
    });

    updateFile();
  }

  function enhancePreview(root) {
    var sections = Array.prototype.slice.call(root.querySelectorAll('.headless-directory-section'));
    var preview = sections.find(function (section) {
      var heading = section.querySelector('h2, h3');
      return text(heading).indexOf('2.') === 0;
    });
    if (!preview) return;

    var grid = preview.querySelector('.headless-forms-grid-3');
    if (grid && !preview.querySelector('.headless-directory-import-kpis')) {
      var kpis = document.createElement('div');
      kpis.className = 'headless-directory-import-kpis';
      Array.prototype.slice.call(grid.querySelectorAll('.headless-directory-field')).forEach(function (field, index) {
        var valueNode = field.querySelector('input');
        var value = valueNode ? Number(valueNode.value || 0) : 0;
        var label = text(field.querySelector('label'));
        var card = document.createElement('div');
        card.className = 'headless-directory-import-kpi ' + (index === 2 && value > 0 ? 'is-warning' : 'is-ready');
        var strong = document.createElement('strong');
        strong.textContent = String(value);
        var span = document.createElement('span');
        span.textContent = label;
        card.appendChild(strong);
        card.appendChild(span);
        kpis.appendChild(card);
      });
      grid.replaceWith(kpis);
    }

    var table = preview.querySelector('table.widefat');
    if (table && !table.closest('.headless-directory-import-table-wrap')) {
      var wrap = document.createElement('div');
      wrap.className = 'headless-directory-import-table-wrap';
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    }

    Array.prototype.slice.call(preview.querySelectorAll('tbody tr')).forEach(function (row) {
      var cell = row.lastElementChild;
      if (!cell || cell.querySelector('.headless-directory-validation-pill')) return;
      var value = text(cell);
      var state = value === 'Error' ? 'is-error' : (value === 'Advertencia' ? 'is-warning' : 'is-ready');
      var pill = document.createElement('span');
      pill.className = 'headless-directory-validation-pill ' + state;
      pill.textContent = value;
      cell.textContent = '';
      cell.appendChild(pill);
    });

    var importForm = preview.querySelector('form[action*="admin-post.php"]');
    if (importForm && !root.querySelector('.headless-forms-package-import-step')) {
      var importSection = document.createElement('section');
      importSection.className = 'headless-directory-section headless-directory-import-options headless-forms-package-import-step';

      var headingWrap = document.createElement('div');
      headingWrap.className = 'headless-directory-section-heading';
      headingWrap.innerHTML = '<div><h2>3. Importar</h2><p>Elige cómo tratar contratos con el mismo slug antes de escribir en WordPress.</p></div>';
      importSection.appendChild(headingWrap);

      importForm.style.marginTop = '';
      var modeField = importForm.querySelector('.headless-directory-field');
      if (modeField) {
        modeField.style.maxWidth = '';
        var row = document.createElement('div');
        row.className = 'headless-directory-form-row';
        modeField.parentNode.insertBefore(row, modeField);
        row.appendChild(modeField);
      }
      importSection.appendChild(importForm);
      preview.parentNode.insertBefore(importSection, preview.nextSibling);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var input = document.querySelector('#headless-forms-package-file');
    if (!input) return;

    var wrap = input.closest('.wrap');
    if (!wrap) return;
    wrap.classList.add('headless-directory-workspace', 'headless-directory-import-page');

    var editor = wrap.querySelector('.headless-directory-editor.headless-forms-editor');
    if (!editor) return;

    var intro = editor.querySelector('.headless-directory-intro');
    if (intro) {
      var lead = document.createElement('p');
      lead.className = 'headless-directory-lead';
      var strong = text(intro.querySelector('strong'));
      var span = text(intro.querySelector('span'));
      lead.textContent = [strong, span].filter(Boolean).join(' ');
      intro.replaceWith(lead);
    }

    editor.classList.remove('headless-directory-editor', 'headless-forms-editor');
    editor.classList.add('headless-directory-import-layout');

    enhanceFileStep(editor);
    enhancePreview(editor);
  });
}());
