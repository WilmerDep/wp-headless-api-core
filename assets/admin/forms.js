(function ($) {
  'use strict';

  var config = window.HeadlessFormsAdmin || {};
  var fieldTypes = config.fieldTypes || {};
  var templateOptions = config.templates || [];
  var strings = config.strings || {};

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function parseJson(value, fallback) {
    try {
      var parsed = JSON.parse(value || '');
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (error) {
      return fallback;
    }
  }

  function slug(value, fallback) {
    var output = String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^A-Za-z0-9_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
    return output || fallback || '';
  }

  function splitRecipients(value) {
    return String(value || '')
      .split(/[\n,;]+/)
      .map(function (item) { return item.trim(); })
      .filter(Boolean);
  }

  function optionsToText(options) {
    return Array.isArray(options)
      ? options.map(function (item) { return (item.value || '') + ' | ' + (item.label || ''); }).join('\n')
      : '';
  }

  function textToOptions(text) {
    return String(text || '')
      .split('\n')
      .map(function (line) {
        var parts = line.split('|');
        var value = (parts.shift() || '').trim();
        var label = parts.join('|').trim();
        if (!value || !label) return null;
        return { value: value, label: label };
      })
      .filter(Boolean);
  }

  function typeOptions(selected) {
    return Object.keys(fieldTypes).map(function (key) {
      return '<option value="' + esc(key) + '"' + (key === selected ? ' selected' : '') + '>' + esc(fieldTypes[key]) + '</option>';
    }).join('');
  }

  function widthOptions(selected) {
    var choices = [
      { value: 12, label: '1 columna · ancho completo' },
      { value: 6, label: '2 columnas · 1/2' },
      { value: 4, label: '3 columnas · 1/3' },
    ];
    return choices.map(function (choice) {
      return '<option value="' + choice.value + '"' + (Number(selected || 12) === choice.value ? ' selected' : '') + '>' + esc(choice.label) + '</option>';
    }).join('');
  }

  function operatorOptions(selected) {
    var choices = {
      equals: 'Es igual a',
      not_equals: 'No es igual a',
      in: 'Está en',
      not_in: 'No está en',
      not_empty: 'No está vacío',
      empty: 'Está vacío',
    };
    return Object.keys(choices).map(function (key) {
      return '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' + esc(choices[key]) + '</option>';
    }).join('');
  }

  function templateSelectOptions(selected) {
    var html = '<option value="">— Seleccionar plantilla —</option>';
    templateOptions.forEach(function (item) {
      html += '<option value="' + esc(item.value) + '"' + (item.value === selected ? ' selected' : '') + '>' + esc(item.label) + '</option>';
    });
    return html;
  }

  function stateFor(name) {
    var $input = $('[data-forms-json="' + name + '"]');
    var fallback = [];
    return { input: $input, items: parseJson($input.val(), fallback) };
  }

  var sectionsState = stateFor('sections');
  var fieldsState = stateFor('fields');
  var notificationsState = stateFor('notifications');

  function sectionOptions(selected) {
    var html = '<option value="">' + esc(strings.noSection || 'Sin sección') + '</option>';
    sectionsState.items.forEach(function (section) {
      html += '<option value="' + esc(section.id) + '"' + (section.id === selected ? ' selected' : '') + '>' + esc(section.title || section.id) + '</option>';
    });
    return html;
  }

  function fieldReferenceOptions(selected, onlyEmail) {
    var html = '<option value="">— Ninguno —</option>';
    fieldsState.items.forEach(function (field) {
      if (onlyEmail && field.type !== 'email') return;
      html += '<option value="' + esc(field.name) + '"' + (field.name === selected ? ' selected' : '') + '>' + esc((field.label || field.name) + ' · ' + field.name) + '</option>';
    });
    return html;
  }

  function sync() {
    sectionsState.items.forEach(function (item, index) { item.order = index; });
    fieldsState.items.forEach(function (item, index) { item.order = index; });
    sectionsState.input.val(JSON.stringify(sectionsState.items));
    fieldsState.input.val(JSON.stringify(fieldsState.items));
    notificationsState.input.val(JSON.stringify(notificationsState.items));
  }

  function ensureFieldShape(field, index) {
    field = field && typeof field === 'object' ? field : {};
    field.name = field.name || ('field' + (index + 1));
    field.id = field.id || slug(field.name, 'field-' + (index + 1));
    field.type = field.type || 'text';
    field.label = field.label || (strings.untitledField || 'Nuevo campo');
    field.placeholder = field.placeholder || '';
    field.required = !!field.required;
    field.section = field.section || '';
    field.width = Number(field.width || 12);
    field.hidden = !!field.hidden;
    field.helper = field.helper || '';
    field.defaultValue = field.defaultValue || '';
    field.autocomplete = field.autocomplete || '';
    field.options = Array.isArray(field.options) ? field.options : [];
    field.validation = field.validation && typeof field.validation === 'object' ? field.validation : {};
    field.visibility = field.visibility && typeof field.visibility === 'object' ? field.visibility : null;
    field.ui = field.ui && typeof field.ui === 'object' ? field.ui : {};
    return field;
  }

  sectionsState.items = sectionsState.items.map(function (section, index) {
    section = section && typeof section === 'object' ? section : {};
    section.id = section.id || ('section-' + (index + 1));
    section.eyebrow = section.eyebrow || '';
    section.title = section.title || (strings.untitledSection || 'Nueva sección');
    section.description = section.description || '';
    return section;
  });
  fieldsState.items = fieldsState.items.map(ensureFieldShape);
  notificationsState.items = notificationsState.items.map(function (notice, index) {
    notice = notice && typeof notice === 'object' ? notice : {};
    notice.id = notice.id || ('notification-' + (index + 1));
    notice.enabled = notice.enabled !== false;
    notice.template = notice.template || '';
    notice.to = Array.isArray(notice.to) ? notice.to : [];
    notice.cc = Array.isArray(notice.cc) ? notice.cc : [];
    notice.bcc = Array.isArray(notice.bcc) ? notice.bcc : [];
    notice.replyToField = notice.replyToField || '';
    notice.subject = notice.subject || '';
    return notice;
  });

  function renderSections() {
    var $root = $('[data-forms-builder="sections"]');
    if (!$root.length) return;
    if (!sectionsState.items.length) {
      $root.html('<div class="headless-forms-empty">No hay secciones. Puedes crear campos sin sección o añadir una para organizar el formulario.</div>');
      sync();
      renderFields();
      return;
    }
    var html = sectionsState.items.map(function (section, index) {
      return '<article class="headless-forms-builder-card" data-kind="section" data-index="' + index + '">' +
        '<header><span class="dashicons dashicons-menu headless-forms-drag" aria-hidden="true"></span><div><strong>' + esc(section.title || section.id) + '</strong><small>' + esc(section.id) + '</small></div><button type="button" class="button-link-delete" data-forms-remove>Quitar</button></header>' +
        '<div class="headless-forms-grid-3">' +
          '<label>ID<input class="widefat" data-prop="id" value="' + esc(section.id) + '"></label>' +
          '<label>Eyebrow<input class="widefat" data-prop="eyebrow" value="' + esc(section.eyebrow) + '" placeholder="PASO 1"></label>' +
          '<label>Título<input class="widefat" data-prop="title" value="' + esc(section.title) + '"></label>' +
        '</div>' +
        '<label class="headless-forms-block-label">Descripción<textarea class="widefat" rows="2" data-prop="description">' + esc(section.description) + '</textarea></label>' +
      '</article>';
    }).join('');
    $root.html(html);
    makeSortable($root, sectionsState, renderSections);
    sync();
    renderFields();
  }

  function renderConditions(field) {
    var rules = field.visibility && Array.isArray(field.visibility.all) ? field.visibility.all : [];
    if (!rules.length) {
      return '<div class="headless-forms-empty is-compact">Sin condiciones. El campo se muestra normalmente.</div>';
    }
    return rules.map(function (rule, ruleIndex) {
      var valueDisabled = rule.operator === 'empty' || rule.operator === 'not_empty';
      return '<div class="headless-forms-condition" data-condition-index="' + ruleIndex + '">' +
        '<select data-condition-prop="field">' + fieldReferenceOptions(rule.field || '', false) + '</select>' +
        '<select data-condition-prop="operator">' + operatorOptions(rule.operator || 'equals') + '</select>' +
        '<input type="text" data-condition-prop="value" value="' + esc(Array.isArray(rule.value) ? rule.value.join(',') : (rule.value || '')) + '"' + (valueDisabled ? ' disabled' : '') + ' placeholder="valor o valor1,valor2">' +
        '<button type="button" class="button-link-delete" data-condition-remove>×</button>' +
      '</div>';
    }).join('');
  }

  function renderFields() {
    var $root = $('[data-forms-builder="fields"]');
    if (!$root.length) return;
    if (!fieldsState.items.length) {
      $root.html('<div class="headless-forms-empty">Todavía no hay campos. Añade el primero para construir el contrato público.</div>');
      sync();
      renderNotifications();
      return;
    }

    var html = fieldsState.items.map(function (field, index) {
      field = ensureFieldShape(field, index);
      var validation = field.validation || {};
      var ui = field.ui || {};
      var visibilityMode = field.visibility && field.visibility.mode === 'hide' ? 'hide' : 'show';
      return '<article class="headless-forms-builder-card headless-forms-field-card" data-kind="field" data-index="' + index + '">' +
        '<header><span class="dashicons dashicons-menu headless-forms-drag" aria-hidden="true"></span><div><strong>' + esc(field.label || field.name) + '</strong><small>' + esc(field.name) + ' · ' + esc(fieldTypes[field.type] || field.type) + ' · ' + esc(field.width) + '/12</small></div><button type="button" class="button-link-delete" data-forms-remove>Quitar</button></header>' +
        '<div class="headless-forms-grid-3">' +
          '<label>Tipo<select class="widefat" data-prop="type">' + typeOptions(field.type) + '</select></label>' +
          '<label>Name<input class="widefat" data-prop="name" value="' + esc(field.name) + '" placeholder="fullName"></label>' +
          '<label>Etiqueta<input class="widefat" data-prop="label" value="' + esc(field.label) + '"></label>' +
          '<label>Ancho<select class="widefat" data-prop="width">' + widthOptions(field.width) + '</select></label>' +
          '<label>Sección<select class="widefat" data-prop="section">' + sectionOptions(field.section) + '</select></label>' +
          '<label>Autocomplete<input class="widefat" data-prop="autocomplete" value="' + esc(field.autocomplete) + '" placeholder="email, name…"></label>' +
        '</div>' +
        '<div class="headless-forms-grid-2">' +
          '<label>Placeholder<input class="widefat" data-prop="placeholder" value="' + esc(field.placeholder) + '"></label>' +
          '<label>Ayuda<input class="widefat" data-prop="helper" value="' + esc(field.helper) + '"></label>' +
          '<label>Valor por defecto<input class="widefat" data-prop="defaultValue" value="' + esc(field.defaultValue) + '"></label>' +
          '<label>Componente Consumer<input class="widefat" data-ui-prop="component" value="' + esc(ui.component || '') + '" placeholder="international-phone"></label>' +
        '</div>' +
        '<div class="headless-forms-checks">' +
          '<label><input type="checkbox" data-prop="required"' + (field.required ? ' checked' : '') + '> Obligatorio</label>' +
          '<label><input type="checkbox" data-prop="hidden"' + (field.hidden ? ' checked' : '') + '> Oculto</label>' +
          '<label><input type="checkbox" data-validation-prop="safeText"' + (validation.safeText ? ' checked' : '') + '> Texto seguro</label>' +
          '<label><input type="checkbox" data-validation-prop="futureOnly"' + (validation.futureOnly ? ' checked' : '') + '> Solo futuro</label>' +
          '<label><input type="checkbox" data-validation-prop="pastOnly"' + (validation.pastOnly ? ' checked' : '') + '> Solo pasado</label>' +
        '</div>' +
        '<details class="headless-forms-advanced"><summary>Opciones, validación y condiciones</summary>' +
          '<div class="headless-forms-grid-2">' +
            '<label>Opciones <small>una por línea: valor | etiqueta</small><textarea class="widefat" rows="5" data-options>' + esc(optionsToText(field.options)) + '</textarea></label>' +
            '<div><div class="headless-forms-grid-2 is-tight">' +
              '<label>Min. caracteres<input class="widefat" type="number" min="0" data-validation-prop="minLength" value="' + esc(validation.minLength == null ? '' : validation.minLength) + '"></label>' +
              '<label>Máx. caracteres<input class="widefat" type="number" min="0" data-validation-prop="maxLength" value="' + esc(validation.maxLength == null ? '' : validation.maxLength) + '"></label>' +
              '<label>Min. palabras<input class="widefat" type="number" min="0" data-validation-prop="minWords" value="' + esc(validation.minWords == null ? '' : validation.minWords) + '"></label>' +
              '<label>Máx. palabras<input class="widefat" type="number" min="0" data-validation-prop="maxWords" value="' + esc(validation.maxWords == null ? '' : validation.maxWords) + '"></label>' +
              '<label>Mínimo numérico<input class="widefat" type="number" step="any" data-validation-prop="min" value="' + esc(validation.min == null ? '' : validation.min) + '"></label>' +
              '<label>Máximo numérico<input class="widefat" type="number" step="any" data-validation-prop="max" value="' + esc(validation.max == null ? '' : validation.max) + '"></label>' +
              '<label>Patrón<select class="widefat" data-validation-prop="pattern"><option value="">Ninguno</option><option value="name"' + (validation.pattern === 'name' ? ' selected' : '') + '>Nombre</option><option value="digits"' + (validation.pattern === 'digits' ? ' selected' : '') + '>Solo dígitos</option><option value="document"' + (validation.pattern === 'document' ? ' selected' : '') + '>Documento</option></select></label>' +
              '<label>Variante Consumer<input class="widefat" data-ui-prop="variant" value="' + esc(ui.variant || '') + '"></label>' +
            '</div></div>' +
          '</div>' +
          '<div class="headless-forms-conditions-head"><strong>Visibilidad condicional</strong><label>Si coincide: <select data-visibility-mode><option value="show"' + (visibilityMode === 'show' ? ' selected' : '') + '>Mostrar</option><option value="hide"' + (visibilityMode === 'hide' ? ' selected' : '') + '>Ocultar</option></select></label><button type="button" class="button button-small" data-condition-add>Añadir condición</button></div>' +
          '<div class="headless-forms-conditions">' + renderConditions(field) + '</div>' +
        '</details>' +
      '</article>';
    }).join('');

    $root.html(html);
    makeSortable($root, fieldsState, renderFields);
    sync();
    renderNotifications();
  }

  function renderNotifications() {
    var $root = $('[data-forms-builder="notifications"]');
    if (!$root.length) return;
    if (!notificationsState.items.length) {
      $root.html('<div class="headless-forms-empty">Sin notificaciones. El formulario puede validar/aceptar datos, pero no enviará correo.</div>');
      sync();
      return;
    }
    var html = notificationsState.items.map(function (notice, index) {
      return '<article class="headless-forms-builder-card" data-kind="notification" data-index="' + index + '">' +
        '<header><span class="dashicons dashicons-email-alt headless-forms-static-icon" aria-hidden="true"></span><div><strong>' + esc(notice.id || strings.untitledNotice || 'Notificación') + '</strong><small>' + (notice.enabled ? 'Activa' : 'Inactiva') + '</small></div><button type="button" class="button-link-delete" data-forms-remove>Quitar</button></header>' +
        '<div class="headless-forms-grid-3">' +
          '<label>ID<input class="widefat" data-notice-prop="id" value="' + esc(notice.id) + '"></label>' +
          '<label>Plantilla<select class="widefat" data-notice-prop="template">' + templateSelectOptions(notice.template) + '</select></label>' +
          '<label>Reply-To<select class="widefat" data-notice-prop="replyToField">' + fieldReferenceOptions(notice.replyToField, true) + '</select></label>' +
        '</div>' +
        '<label class="headless-forms-block-label">Asunto<input class="widefat" data-notice-prop="subject" value="' + esc(notice.subject) + '" placeholder="Nueva solicitud — {{field.fullName}}"></label>' +
        '<div class="headless-forms-grid-3">' +
          '<label>Para<textarea class="widefat" rows="3" data-recipient-prop="to">' + esc((notice.to || []).join('\n')) + '</textarea></label>' +
          '<label>CC<textarea class="widefat" rows="3" data-recipient-prop="cc">' + esc((notice.cc || []).join('\n')) + '</textarea></label>' +
          '<label>BCC<textarea class="widefat" rows="3" data-recipient-prop="bcc">' + esc((notice.bcc || []).join('\n')) + '</textarea></label>' +
        '</div>' +
        '<label class="headless-forms-inline-check"><input type="checkbox" data-notice-prop="enabled"' + (notice.enabled ? ' checked' : '') + '> Notificación activa</label>' +
      '</article>';
    }).join('');
    $root.html(html);
    sync();
  }

  function makeSortable($root, state, renderFn) {
    if (!$.fn.sortable || !$root.length) return;
    $root.sortable({
      items: '> .headless-forms-builder-card',
      handle: '.headless-forms-drag',
      placeholder: 'headless-forms-sort-placeholder',
      update: function () {
        var reordered = [];
        $root.children('.headless-forms-builder-card').each(function () {
          reordered.push(state.items[Number($(this).attr('data-index'))]);
        });
        state.items = reordered.filter(Boolean);
        renderFn();
      },
    });
  }

  function addSection() {
    var number = sectionsState.items.length + 1;
    sectionsState.items.push({ id: 'section-' + number, eyebrow: '', title: strings.untitledSection || 'Nueva sección', description: '', order: number - 1 });
    renderSections();
  }

  function addField() {
    var number = fieldsState.items.length + 1;
    fieldsState.items.push(ensureFieldShape({ id: 'field-' + number, name: 'field' + number, type: 'text', label: strings.untitledField || 'Nuevo campo', width: 12 }, number - 1));
    renderFields();
  }

  function addNotification() {
    var number = notificationsState.items.length + 1;
    notificationsState.items.push({ id: 'notification-' + number, enabled: true, template: '', to: [], cc: [], bcc: [], replyToField: '', subject: '' });
    renderNotifications();
  }

  $(document).on('click', '[data-forms-add="section"]', addSection);
  $(document).on('click', '[data-forms-add="field"]', addField);
  $(document).on('click', '[data-forms-add="notification"]', addNotification);

  $(document).on('click', '[data-forms-remove]', function () {
    if (!window.confirm(strings.confirmRemove || '¿Quitar este elemento?')) return;
    var $card = $(this).closest('[data-kind]');
    var index = Number($card.attr('data-index'));
    var kind = $card.attr('data-kind');
    if (kind === 'section') { sectionsState.items.splice(index, 1); renderSections(); }
    if (kind === 'field') { fieldsState.items.splice(index, 1); renderFields(); }
    if (kind === 'notification') { notificationsState.items.splice(index, 1); renderNotifications(); }
  });

  $(document).on('input change', '[data-kind="section"] [data-prop]', function () {
    var $card = $(this).closest('[data-kind="section"]');
    var item = sectionsState.items[Number($card.attr('data-index'))];
    if (!item) return;
    var prop = $(this).attr('data-prop');
    item[prop] = $(this).val();
    if (prop === 'id') item.id = slug(item.id, 'section');
    sync();
    if (prop === 'id' || prop === 'title') renderFields();
  });

  $(document).on('input change', '[data-kind="field"] [data-prop]', function () {
    var $card = $(this).closest('[data-kind="field"]');
    var item = fieldsState.items[Number($card.attr('data-index'))];
    if (!item) return;
    var prop = $(this).attr('data-prop');
    if ($(this).is(':checkbox')) item[prop] = $(this).is(':checked');
    else if (prop === 'width') item[prop] = Number($(this).val());
    else item[prop] = $(this).val();
    if (prop === 'name') {
      item.name = String(item.name || '').replace(/[^A-Za-z0-9_-]/g, '');
      item.id = slug(item.name, item.id || 'field');
    }
    sync();
    if (prop === 'name' || prop === 'label' || prop === 'type') renderNotifications();
  });

  $(document).on('input change', '[data-kind="field"] [data-ui-prop]', function () {
    var $card = $(this).closest('[data-kind="field"]');
    var item = fieldsState.items[Number($card.attr('data-index'))];
    if (!item) return;
    item.ui = item.ui || {};
    var prop = $(this).attr('data-ui-prop');
    var value = $(this).val();
    if (value) item.ui[prop] = value;
    else delete item.ui[prop];
    sync();
  });

  $(document).on('input change', '[data-kind="field"] [data-validation-prop]', function () {
    var $card = $(this).closest('[data-kind="field"]');
    var item = fieldsState.items[Number($card.attr('data-index'))];
    if (!item) return;
    item.validation = item.validation || {};
    var prop = $(this).attr('data-validation-prop');
    var value = $(this).is(':checkbox') ? $(this).is(':checked') : $(this).val();
    if (value === '' || value === false) delete item.validation[prop];
    else item.validation[prop] = $(this).attr('type') === 'number' ? Number(value) : value;
    sync();
  });

  $(document).on('input', '[data-kind="field"] [data-options]', function () {
    var $card = $(this).closest('[data-kind="field"]');
    var item = fieldsState.items[Number($card.attr('data-index'))];
    if (!item) return;
    item.options = textToOptions($(this).val());
    sync();
  });

  $(document).on('change', '[data-kind="field"] [data-visibility-mode]', function () {
    var $card = $(this).closest('[data-kind="field"]');
    var item = fieldsState.items[Number($card.attr('data-index'))];
    if (!item) return;
    item.visibility = item.visibility || { mode: 'show', all: [] };
    item.visibility.mode = $(this).val() === 'hide' ? 'hide' : 'show';
    sync();
  });

  $(document).on('click', '[data-kind="field"] [data-condition-add]', function () {
    var $card = $(this).closest('[data-kind="field"]');
    var index = Number($card.attr('data-index'));
    var item = fieldsState.items[index];
    if (!item) return;
    item.visibility = item.visibility || { mode: 'show', all: [] };
    item.visibility.all = Array.isArray(item.visibility.all) ? item.visibility.all : [];
    item.visibility.all.push({ field: '', operator: 'equals', value: '' });
    renderFields();
    var $target = $('[data-kind="field"][data-index="' + index + '"] details');
    if ($target.length) $target.prop('open', true);
  });

  $(document).on('click', '[data-kind="field"] [data-condition-remove]', function () {
    var $card = $(this).closest('[data-kind="field"]');
    var item = fieldsState.items[Number($card.attr('data-index'))];
    var ruleIndex = Number($(this).closest('[data-condition-index]').attr('data-condition-index'));
    if (!item || !item.visibility || !Array.isArray(item.visibility.all)) return;
    item.visibility.all.splice(ruleIndex, 1);
    if (!item.visibility.all.length) item.visibility = null;
    renderFields();
  });

  $(document).on('input change', '[data-kind="field"] [data-condition-prop]', function () {
    var $card = $(this).closest('[data-kind="field"]');
    var item = fieldsState.items[Number($card.attr('data-index'))];
    var $row = $(this).closest('[data-condition-index]');
    var ruleIndex = Number($row.attr('data-condition-index'));
    if (!item || !item.visibility || !item.visibility.all[ruleIndex]) return;
    var prop = $(this).attr('data-condition-prop');
    var value = $(this).val();
    if (prop === 'value' && ['in', 'not_in'].indexOf(item.visibility.all[ruleIndex].operator) !== -1) {
      value = String(value).split(',').map(function (part) { return part.trim(); }).filter(Boolean);
    }
    item.visibility.all[ruleIndex][prop] = value;
    sync();
    if (prop === 'operator') renderFields();
  });

  $(document).on('input change', '[data-kind="notification"] [data-notice-prop]', function () {
    var $card = $(this).closest('[data-kind="notification"]');
    var item = notificationsState.items[Number($card.attr('data-index'))];
    if (!item) return;
    var prop = $(this).attr('data-notice-prop');
    item[prop] = $(this).is(':checkbox') ? $(this).is(':checked') : $(this).val();
    if (prop === 'id') item.id = slug(item.id, 'notification');
    sync();
  });

  $(document).on('input', '[data-kind="notification"] [data-recipient-prop]', function () {
    var $card = $(this).closest('[data-kind="notification"]');
    var item = notificationsState.items[Number($card.attr('data-index'))];
    if (!item) return;
    item[$(this).attr('data-recipient-prop')] = splitRecipients($(this).val());
    sync();
  });

  function currentTemplateMode() {
    return $('input[name="headless_template_mode"]:checked').val() || 'visual';
  }

  function toggleTemplateMode() {
    var mode = currentTemplateMode();
    $('[data-template-panel="visual"]').toggle(mode === 'visual');
    $('[data-template-panel="html"]').toggle(mode === 'html');
  }

  function syncColor($source) {
    var key = $source.attr('data-template-setting') || $source.attr('data-template-color-text');
    if (!key) return;
    var value = $source.val();
    $('[data-template-setting="' + key + '"]').val(value);
    $('[data-template-color-text="' + key + '"]').val(value);
  }

  function previewTemplate() {
    var $preview = $('[data-template-preview]');
    if (!$preview.length) return;
    var get = function (key, fallback) {
      var value = $('[data-template-setting="' + key + '"]').first().val();
      return value || fallback || '';
    };
    var outer = get('outerBackground', '#f5f8fb');
    var header = get('headerBackground', '#06477f');
    var label = get('labelColor', '#123f68');
    var value = get('valueColor', '#344f67');
    var separator = get('separatorColor', '#e8eef3');
    var logo = get('logoUrl', '');
    var eyebrow = get('eyebrow', '{{form.name}}');
    var heading = get('heading', 'Nueva solicitud');
    var intro = get('intro', '');
    var footer = get('footer', '');

    var html = '<div class="headless-forms-email-canvas" style="background:' + esc(outer) + '"><div class="headless-forms-email-card">' +
      '<div class="headless-forms-email-header" style="background:' + esc(header) + '"><div><small>' + esc(eyebrow) + '</small><strong>' + esc(heading) + '</strong></div>' +
      (logo ? '<img src="' + esc(logo) + '" alt="">' : '') + '</div>' +
      '<div class="headless-forms-email-body">' + (intro ? '<p>' + esc(intro) + '</p>' : '') +
      '<div class="headless-forms-email-row" style="border-color:' + esc(separator) + '"><b style="color:' + esc(label) + '">Nombre</b><span style="color:' + esc(value) + '">Ana Pérez</span></div>' +
      '<div class="headless-forms-email-row" style="border-color:' + esc(separator) + '"><b style="color:' + esc(label) + '">Correo</b><span style="color:' + esc(value) + '">ana@example.org</span></div>' +
      '<div class="headless-forms-email-row" style="border-color:' + esc(separator) + '"><b style="color:' + esc(label) + '">Mensaje</b><span style="color:' + esc(value) + '">Ejemplo de datos enviados.</span></div>' +
      '</div>' + (footer ? '<div class="headless-forms-email-footer">' + esc(footer) + '</div>' : '') + '</div></div>';
    $preview.html(html);
  }

  $(document).on('change', 'input[name="headless_template_mode"]', toggleTemplateMode);
  $(document).on('input change', '[data-template-setting], [data-template-color-text]', function () {
    if ($(this).attr('data-template-color-text') || $(this).attr('type') === 'color') syncColor($(this));
    previewTemplate();
  });

  $(document).on('click', '[data-template-media]', function () {
    if (!window.wp || !wp.media) return;
    var frame = wp.media({ title: 'Seleccionar logo', button: { text: 'Usar esta imagen' }, multiple: false, library: { type: 'image' } });
    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      $('input[name="headless_template_logo_url"]').val(attachment.url).trigger('input');
    });
    frame.open();
  });

  renderSections();
  renderFields();
  renderNotifications();
  toggleTemplateMode();
  previewTemplate();
})(jQuery);
