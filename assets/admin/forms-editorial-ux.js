(function () {
  'use strict';

  var SELECTOR_TEXT_MAP = {
    'Schema': 'Versión de estructura',
    'ID': 'Identificador interno',
    'Name': 'Nombre interno',
    'Autocomplete': 'Autocompletar en navegador',
    'Placeholder': 'Texto de ejemplo',
    'Componente Consumer': 'Componente especial del sitio',
    'Reply-To': 'Responder al correo de',
    'Para': 'Destinatario(s)',
    'CC': 'Copia',
    'BCC': 'Copia oculta',
    'Min. caracteres': 'Mínimo de caracteres',
    'Máx. caracteres': 'Máximo de caracteres',
    'Min. palabras': 'Mínimo de palabras',
    'Máx. palabras': 'Máximo de palabras',
    'Texto seguro': 'Bloquear contenido riesgoso',
    'Solo futuro': 'Solo fechas futuras',
    'Solo pasado': 'Solo fechas pasadas'
  };

  function directText(label) {
    var out = '';
    Array.prototype.slice.call(label.childNodes).forEach(function (node) {
      if (node.nodeType === Node.TEXT_NODE) out += node.nodeValue;
    });
    return out.trim();
  }

  function replaceDirectText(label, next) {
    var nodes = Array.prototype.slice.call(label.childNodes);
    var replaced = false;
    nodes.forEach(function (node) {
      if (!replaced && node.nodeType === Node.TEXT_NODE && node.nodeValue.trim()) {
        node.nodeValue = node.nodeValue.replace(node.nodeValue.trim(), next);
        replaced = true;
      }
    });
  }

  function addSmall(label, text) {
    if (label.querySelector('[data-editorial-help]')) return;
    var small = document.createElement('small');
    small.setAttribute('data-editorial-help', '1');
    small.textContent = text;
    small.style.display = 'block';
    small.style.fontWeight = '400';
    small.style.marginTop = '2px';
    small.style.opacity = '.72';
    label.insertBefore(small, label.querySelector('input,select,textarea'));
  }

  function relabelLabels(root) {
    (root || document).querySelectorAll('label').forEach(function (label) {
      var text = directText(label);
      if (text === 'Eyebrow') {
        replaceDirectText(label, 'Subtítulo');
        return;
      }
      if (Object.prototype.hasOwnProperty.call(SELECTOR_TEXT_MAP, text)) {
        replaceDirectText(label, SELECTOR_TEXT_MAP[text]);
      }

      var current = directText(label);
      if (current === 'Nombre interno') {
        addSmall(label, 'No lo cambies si el sitio web ya usa este formulario.');
      } else if (current === 'Identificador interno') {
        addSmall(label, 'Dato técnico de referencia. Normalmente no necesita cambios.');
      } else if (current === 'Autocompletar en navegador') {
        addSmall(label, 'Ayuda al navegador a completar datos conocidos del usuario.');
      } else if (current.indexOf('Componente especial del sitio') === 0) {
        addSmall(label, 'Opcional. Solo se usa cuando el sitio necesita un control especial.');
      }
    });
  }

  function replaceExact(selector, from, to) {
    document.querySelectorAll(selector).forEach(function (node) {
      if (node.textContent.trim() === from) node.textContent = to;
    });
  }

  function friendlyGeneralCopy() {
    replaceExact('.headless-directory-intro strong', 'Define el contrato del formulario sin acoplarlo al frontend.', 'Configura el formulario que utilizará el sitio web.');
    replaceExact('.headless-directory-intro span', 'El Consumer decide cómo se ve; Forms Core controla campos, validación, disponibilidad, seguridad y notificaciones.', 'Aquí administras los campos, mensajes, reglas, protección y correos del formulario.');
    replaceExact('.headless-forms-toggle-row p', 'Solo los formularios publicados y habilitados se exponen por REST.', 'Solo los formularios publicados y habilitados estarán disponibles para el sitio web.');
    replaceExact('.headless-forms-builder-section .headless-directory-section-heading p', 'El ancho usa una cuadrícula de 12: 12 = completo, 6 = dos columnas, 4 = tres columnas.', 'Elige si cada campo ocupa toda la fila, la mitad o un tercio.');
    replaceExact('.headless-directory-section-heading p', 'Cada envío puede disparar uno o varios correos usando plantillas reutilizables.', 'Cada envío puede generar uno o varios correos usando plantillas reutilizables.');

    document.querySelectorAll('.headless-forms-builder-section > p.description').forEach(function (node) {
      if (node.textContent.indexOf('{{field.email}}') !== -1 || node.textContent.indexOf('Reply-To') !== -1) {
        node.textContent = 'Puedes usar un correo fijo o un campo de correo del formulario como destinatario. En “Responder al correo de”, selecciona el campo de correo correspondiente.';
      }
    });
  }

  function friendlySecurity() {
    var section = null;
    document.querySelectorAll('.headless-directory-section').forEach(function (item) {
      var heading = item.querySelector('.headless-directory-section-heading h3');
      if (heading && (heading.textContent.trim() === 'Seguridad y abuso' || heading.textContent.trim() === 'Protección del formulario')) {
        section = item;
      }
    });
    if (!section) return;

    var heading = section.querySelector('.headless-directory-section-heading h3');
    var intro = section.querySelector('.headless-directory-section-heading p');
    if (heading) heading.textContent = 'Protección del formulario';
    if (intro) intro.textContent = 'Controles para reducir spam, envíos automáticos y uso excesivo.';

    section.querySelectorAll('.headless-forms-toggle-row strong').forEach(function (node) {
      if (node.textContent.trim() === 'Honeypot') node.textContent = 'Filtro antispam invisible';
      if (node.textContent.trim() === 'Rate limit') node.textContent = 'Limitar envíos repetidos';
    });

    var honeypot = section.querySelector('[name="headless_form_honeypot_field"]');
    if (honeypot) {
      honeypot.setAttribute('aria-label', 'Nombre interno del filtro antispam');
      honeypot.setAttribute('title', 'Ajuste interno. No suele necesitar cambios.');
      if (!honeypot.parentNode.querySelector('[data-honeypot-help]')) {
        var hpHelp = document.createElement('p');
        hpHelp.className = 'description';
        hpHelp.setAttribute('data-honeypot-help', '1');
        hpHelp.textContent = 'Añade una comprobación invisible para bloquear bots básicos. El usuario no verá este campo.';
        honeypot.parentNode.appendChild(hpHelp);
      }
    }

    section.querySelectorAll('.headless-forms-toggle-row p').forEach(function (node) {
      if (node.textContent.indexOf('formulario + IP hash') !== -1) {
        node.textContent = 'Evita demasiados envíos desde una misma conexión durante un período corto.';
      }
    });

    section.querySelectorAll('.headless-directory-field label').forEach(function (label) {
      var text = directText(label);
      if (text === 'Máx. payload (bytes)') replaceDirectText(label, 'Tamaño máximo del envío');
      if (text === 'Envíos permitidos') replaceDirectText(label, 'Máximo de envíos');
      if (text === 'Ventana (segundos)') replaceDirectText(label, 'Período de control (segundos)');
    });

    var payload = section.querySelector('[name="headless_form_max_payload"]');
    if (payload && !payload.parentNode.querySelector('[data-payload-help]')) {
      var payloadHelp = document.createElement('p');
      payloadHelp.className = 'description';
      payloadHelp.setAttribute('data-payload-help', '1');
      var kb = Math.round((Number(payload.value || 0) / 1024) * 10) / 10;
      payloadHelp.textContent = 'Protección técnica contra envíos demasiado grandes. Valor actual: ' + kb + ' KB. Normalmente no necesita cambios.';
      payload.parentNode.appendChild(payloadHelp);
    }

    var rateWindow = section.querySelector('[name="headless_form_rate_window"]');
    if (rateWindow && !rateWindow.parentNode.querySelector('[data-window-help]')) {
      var windowHelp = document.createElement('p');
      windowHelp.className = 'description';
      windowHelp.setAttribute('data-window-help', '1');
      var minutes = Math.round((Number(rateWindow.value || 0) / 60) * 10) / 10;
      windowHelp.textContent = 'Valor actual: ' + minutes + ' minutos.';
      rateWindow.parentNode.appendChild(windowHelp);
    }
  }

  function friendlyBuilders() {
    replaceExact('summary', 'Opciones, validación y condiciones', 'Reglas y opciones avanzadas');

    document.querySelectorAll('.headless-forms-field-card').forEach(function (card) {
      var name = card.querySelector('[data-prop="name"]');
      if (name) name.setAttribute('title', 'Identificador usado por el sitio web. Evita cambiarlo si el formulario ya está conectado.');
      var autocomplete = card.querySelector('[data-prop="autocomplete"]');
      if (autocomplete) autocomplete.setAttribute('placeholder', 'Ej.: email, name');
      var component = card.querySelector('[data-ui-prop="component"]');
      if (component) component.setAttribute('placeholder', 'Ej.: teléfono internacional');
    });

    document.querySelectorAll('[data-kind="notification"] label').forEach(function (label) {
      var text = directText(label);
      if (text === 'ID') replaceDirectText(label, 'Identificador interno');
      if (text === 'Reply-To') replaceDirectText(label, 'Responder al correo de');
      if (text === 'Para') replaceDirectText(label, 'Destinatario(s)');
      if (text === 'CC') replaceDirectText(label, 'Copia');
      if (text === 'BCC') replaceDirectText(label, 'Copia oculta');
    });
  }

  function hideReadOnlyTechnicalVersion() {
    document.querySelectorAll('.headless-directory-field label').forEach(function (label) {
      if (directText(label) !== 'Versión de estructura') return;
      var field = label.closest('.headless-directory-field');
      if (field && field.querySelector('input[readonly]')) field.style.display = 'none';
    });
  }

  function apply() {
    relabelLabels(document);
    friendlyGeneralCopy();
    friendlySecurity();
    friendlyBuilders();
    hideReadOnlyTechnicalVersion();
  }

  function boot() {
    apply();
    var queued = false;
    var observer = new MutationObserver(function () {
      if (queued) return;
      queued = true;
      window.requestAnimationFrame(function () {
        queued = false;
        apply();
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
}());
