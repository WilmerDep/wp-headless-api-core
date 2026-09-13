(function ($) {
	'use strict';

	var cfg = window.HeadlessDirectoryAdmin || {};
	var importState = {
		file: null,
		token: '',
		total: 0,
		result: { created: 0, updated: 0, skipped: 0, failed: 0 }
	};

	function text(key, fallback) {
		return cfg.strings && cfg.strings[key] ? cfg.strings[key] : fallback;
	}

	function setSaveState($el, state, message) {
		$el.removeClass('is-saving is-saved is-error').addClass('is-' + state).text(message);
	}

	function initMediaPicker() {
		$(document).on('click', '.headless-directory-select-image', function (event) {
			event.preventDefault();

			var $button = $(this);
			var $control = $button.closest('.headless-directory-image-control');
			var frame = wp.media({
				title: text('chooseImage', 'Seleccionar retrato'),
				button: { text: text('useImage', 'Usar esta imagen') },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var preview = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				var width = attachment.width || 0;
				var height = attachment.height || 0;

				$control.find('.headless-directory-image-id').val(attachment.id);
				$control.find('.headless-directory-image-preview').addClass('has-image');
				$control.find('.headless-directory-image-empty').prop('hidden', true);
				$control.find('.headless-directory-image-preview-image').attr('src', preview).prop('hidden', false);
				$control.find('.headless-directory-remove-image').prop('hidden', false);
				$control.find('.headless-directory-image-actual').text('Actual: ' + width + ' × ' + height + ' px').prop('hidden', false);
				$button.text($button.data('selected-label') || 'Cambiar imagen');
			});

			frame.open();
		});

		$(document).on('click', '.headless-directory-remove-image', function (event) {
			event.preventDefault();
			var $control = $(this).closest('.headless-directory-image-control');
			var $button = $control.find('.headless-directory-select-image');
			$control.find('.headless-directory-image-id').val('');
			$control.find('.headless-directory-image-preview').removeClass('has-image');
			$control.find('.headless-directory-image-empty').prop('hidden', false);
			$control.find('.headless-directory-image-preview-image').removeAttr('src').prop('hidden', true);
			$control.find('.headless-directory-image-actual').text('').prop('hidden', true);
			$(this).prop('hidden', true);
			$button.text($button.data('empty-label') || 'Seleccionar imagen');
		});
	}

	function saveSortOrder($list) {
		var type = $list.data('order-type');
		var ids = $list.children('.headless-directory-sortable-item').map(function () {
			return $(this).data('id');
		}).get();
		var $state = $list.siblings('.headless-directory-save-state').first();

		if (!$state.length) {
			$state = $list.prevAll('.headless-directory-save-state').first();
		}

		setSaveState($state, 'saving', text('saving', 'Guardando…'));

		$.post(cfg.ajaxUrl, {
			action: type === 'groups' ? 'headless_directory_save_group_order' : 'headless_directory_save_person_order',
			nonce: cfg.orderNonce,
			ids: ids,
			groupId: type === 'people' ? ($list.data('group-id') || 0) : 0
		}).done(function (response) {
			if (response && response.success) {
				setSaveState($state, 'saved', (response.data && response.data.message) || text('saved', 'Guardado'));
				window.setTimeout(function () { $state.text('').removeClass('is-saved'); }, 1800);
			} else {
				setSaveState($state, 'error', (response && response.data && response.data.message) || text('error', 'No se pudo guardar.'));
			}
		}).fail(function () {
			setSaveState($state, 'error', text('error', 'No se pudo guardar.'));
		});
	}

	function initOrdering() {
		$('.headless-directory-sortable').each(function () {
			var $list = $(this);
			$list.sortable({
				handle: '.headless-directory-drag-handle',
				placeholder: 'ui-sortable-placeholder',
				forcePlaceholderSize: true,
				update: function () { saveSortOrder($list); }
			});
		});

		$(document).on('change', '#headless-directory-order-group', function () {
			var base = $(this).data('base-url');
			var id = parseInt($(this).val(), 10) || 0;
			window.location.href = base + (id > 0 ? '&group=' + encodeURIComponent(id) : '');
		});
	}

	function setImportFile(file) {
		importState.file = file || null;
		$('.headless-directory-import-file-name').text(file ? file.name : '');
		$('.headless-directory-validate-import').prop('disabled', !file);
	}

	function initDropzone() {
		var $drop = $('.headless-directory-dropzone');
		var $input = $('.headless-directory-import-file');
		if (!$drop.length) {
			return;
		}

		$input.on('change', function () {
			setImportFile(this.files && this.files[0] ? this.files[0] : null);
		});

		$drop.on('dragenter dragover', function (event) {
			event.preventDefault();
			$drop.addClass('is-dragover');
		}).on('dragleave dragend drop', function (event) {
			event.preventDefault();
			$drop.removeClass('is-dragover');
		});

		$drop.on('drop', function (event) {
			var files = event.originalEvent.dataTransfer && event.originalEvent.dataTransfer.files;
			if (files && files[0]) {
				setImportFile(files[0]);
			}
		});
	}

	function renderPreview(data) {
		importState.token = data.token;
		importState.total = data.totalRows || 0;
		importState.result = { created: 0, updated: 0, skipped: 0, failed: 0 };

		var summary = data.summary || { ready: 0, warning: 0, error: 0 };
		$('.headless-directory-import-kpis').html(
			'<div class="headless-directory-import-kpi is-ready"><strong>' + summary.ready + '</strong><span>Listas</span></div>' +
			'<div class="headless-directory-import-kpi is-warning"><strong>' + summary.warning + '</strong><span>Con advertencias</span></div>' +
			'<div class="headless-directory-import-kpi is-error"><strong>' + summary.error + '</strong><span>Con errores</span></div>'
		);

		var html = '';
		(data.rows || []).forEach(function (row) {
			var validation = 'ready';
			var label = 'Lista';
			var message = '';
			if (row.errors && row.errors.length) {
				validation = 'error';
				label = 'Error';
				message = row.errors.join(' ');
			} else if (row.warnings && row.warnings.length) {
				validation = 'warning';
				label = 'Revisar';
				message = row.warnings.join(' ');
			}

			html += '<tr>' +
				'<td>' + escapeHtml(row.row) + '</td>' +
				'<td><strong>' + escapeHtml(row.name || '') + '</strong></td>' +
				'<td>' + escapeHtml(row.role || '') + '</td>' +
				'<td>' + escapeHtml((row.groups || []).join(', ')) + '</td>' +
				'<td>' + escapeHtml(row.status || '') + '</td>' +
				'<td><span class="headless-directory-validation-pill is-' + validation + '">' + label + '</span>' + (message ? '<div class="description">' + escapeHtml(message) + '</div>' : '') + '</td>' +
			'</tr>';
		});

		$('.headless-directory-import-preview tbody').html(html);
		$('.headless-directory-import-preview, .headless-directory-import-options').prop('hidden', false);
		$('.headless-directory-import-result').empty();
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function validateImport() {
		if (!importState.file) {
			return;
		}

		var formData = new FormData();
		formData.append('action', 'headless_directory_preview_import');
		formData.append('nonce', cfg.importNonce);
		formData.append('file', importState.file);

		var $button = $('.headless-directory-validate-import');
		$button.prop('disabled', true).text(text('validating', 'Validando archivo…'));

		$.ajax({
			url: cfg.ajaxUrl,
			method: 'POST',
			data: formData,
			processData: false,
			contentType: false
		}).done(function (response) {
			if (response && response.success) {
				renderPreview(response.data);
			} else {
				window.alert((response && response.data && response.data.message) || 'No se pudo validar el archivo.');
			}
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'No se pudo validar el archivo.';
			window.alert(message);
		}).always(function () {
			$button.prop('disabled', false).text('Validar archivo');
		});
	}

	function mergeBatchResult(batch) {
		['created', 'updated', 'skipped', 'failed'].forEach(function (key) {
			importState.result[key] += batch && batch[key] ? parseInt(batch[key], 10) : 0;
		});
	}

	function renderImportResult() {
		var r = importState.result;
		$('.headless-directory-import-result').html(
			'<div class="headless-directory-result-card"><strong>Importación completada</strong>' +
			'<p>' + r.created + ' creadas · ' + r.updated + ' actualizadas · ' + r.skipped + ' omitidas · ' + r.failed + ' fallidas</p></div>'
		);
	}

	function importBatch(offset) {
		var mode = $('#headless-directory-import-mode').val() || 'create_only';
		return $.post(cfg.ajaxUrl, {
			action: 'headless_directory_import_batch',
			nonce: cfg.importNonce,
			token: importState.token,
			offset: offset,
			batchSize: 10,
			mode: mode,
			createGroups: $('.headless-directory-create-groups').is(':checked') ? 1 : 0,
			importImages: $('.headless-directory-import-images').is(':checked') ? 1 : 0
		}).then(function (response) {
			if (!response || !response.success) {
				return $.Deferred().reject(response).promise();
			}

			var data = response.data || {};
			mergeBatchResult(data.batchResult || {});
			var total = data.total || importState.total || 1;
			var progress = Math.min(100, Math.round(((data.offset || 0) / total) * 100));
			$('.headless-directory-progress-bar span').css('width', progress + '%');
			$('.headless-directory-progress-label').text(progress + '%');

			if (data.done) {
				renderImportResult();
				return response;
			}

			return importBatch(data.offset || 0);
		});
	}

	function runImport() {
		if (!importState.token) {
			return;
		}
		importState.result = { created: 0, updated: 0, skipped: 0, failed: 0 };
		var $button = $('.headless-directory-run-import');
		$button.prop('disabled', true).text(text('importing', 'Importando…'));
		$('.headless-directory-progress').prop('hidden', false);
		$('.headless-directory-progress-bar span').css('width', '0%');
		$('.headless-directory-progress-label').text('0%');
		$('.headless-directory-import-result').empty();

		importBatch(0).fail(function (response) {
			var message = response && response.data && response.data.message ? response.data.message : 'La importación se detuvo por un error.';
			$('.headless-directory-import-result').html('<div class="notice notice-error inline"><p>' + escapeHtml(message) + '</p></div>');
		}).always(function () {
			$button.prop('disabled', false).text('Comenzar importación');
		});
	}

	function initImporter() {
		initDropzone();
		$(document).on('click', '.headless-directory-validate-import', validateImport);
		$(document).on('click', '.headless-directory-run-import', runImport);
	}

	$(function () {
		initMediaPicker();
		initOrdering();
		initImporter();
	});
})(jQuery);
