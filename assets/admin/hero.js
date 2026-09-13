(function ($) {
	'use strict';

	$(function () {
		$('.headless-hero-image-control').each(function () {
			var $control = $(this);
			var frame = null;
			var $input = $control.find('.headless-hero-image-id');
			var $preview = $control.find('.headless-hero-image-preview');
			var $previewImage = $control.find('.headless-hero-image-preview-image');
			var $empty = $control.find('.headless-hero-image-empty');
			var $select = $control.find('.headless-hero-select-image');
			var $remove = $control.find('.headless-hero-remove-image');
			var imageKey = String($control.data('image-key') || '');
			var recommendedSize = imageKey === 'mobile' ? '1200 × 750 px' : '1920 × 800 px';
			var recommendedContext = imageKey === 'mobile' ? 'Móvil' : 'Escritorio, laptop y tablet';
			var $guidance = $('<div class="headless-hero-image-guidance" />');
			var $recommended = $('<span class="headless-hero-size-chip is-recommended" />').text('Recomendado: ' + recommendedSize + ' · ' + recommendedContext);
			var $actualSize = $('<span class="headless-hero-size-chip is-actual headless-hero-actual-size" hidden />');

			$guidance.append($recommended, $actualSize);
			$control.children('.description').first().after($guidance);

			function setActualSize(width, height) {
				width = parseInt(width, 10);
				height = parseInt(height, 10);

				if (!width || !height) {
					$actualSize.text('').attr('hidden', 'hidden');
					return;
				}

				$actualSize.text('Actual: ' + width + ' × ' + height + ' px').removeAttr('hidden');
			}

			function loadCurrentAttachmentSize() {
				var attachmentId = parseInt($input.val(), 10);

				if (!attachmentId || !window.wp || !wp.media || !wp.media.attachment) {
					setActualSize(0, 0);
					return;
				}

				var attachment = wp.media.attachment(attachmentId);
				var currentWidth = attachment.get('width');
				var currentHeight = attachment.get('height');

				if (currentWidth && currentHeight) {
					setActualSize(currentWidth, currentHeight);
					return;
				}

				attachment.fetch().done(function () {
					setActualSize(attachment.get('width'), attachment.get('height'));
				});
			}

			function setSelectedState(attachment) {
				var previewUrl = attachment.url;

				if (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) {
					previewUrl = attachment.sizes.medium.url;
				}

				$input.val(attachment.id);
				$previewImage.attr('src', previewUrl).removeAttr('hidden');
				$empty.attr('hidden', 'hidden');
				$preview.addClass('has-image');
				$remove.removeAttr('hidden');
				$select.text($select.data('selected-label'));
				setActualSize(attachment.width, attachment.height);
			}

			function setEmptyState() {
				$input.val('');
				$previewImage.removeAttr('src').attr('hidden', 'hidden');
				$empty.removeAttr('hidden');
				$preview.removeClass('has-image');
				$remove.attr('hidden', 'hidden');
				$select.text($select.data('empty-label'));
				setActualSize(0, 0);
			}

			$control.on('click', '.headless-hero-select-image', function (event) {
				event.preventDefault();

				if (frame) {
					frame.open();
					return;
				}

				frame = wp.media({
					title: $select.data('frame-title'),
					button: { text: $select.data('frame-button') },
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					setSelectedState(attachment);
				});

				frame.open();
			});

			$control.on('click', '.headless-hero-remove-image', function (event) {
				event.preventDefault();
				setEmptyState();
			});

			loadCurrentAttachmentSize();
		});

		var $linkMode = $('.headless-hero-link-mode');
		var $linkTarget = $('.headless-hero-link-target');
		var $linkInput = $('#headless-hero-href');
		var $linkHelp = $('.headless-hero-link-help');

		function updateLinkFields() {
			var mode = $linkMode.val();

			if (mode === 'none') {
				$linkTarget.attr('hidden', 'hidden');
				return;
			}

			$linkTarget.removeAttr('hidden');

			if (mode === 'internal') {
				$linkInput.attr('placeholder', '/servicios');
				$linkHelp.text('Ejemplo: /servicios. No necesitas escribir el dominio.');
			} else {
				$linkInput.attr('placeholder', 'https://example.org/path');
				$linkHelp.text('Escribe la dirección completa, por ejemplo https://example.org.');
			}
		}

		function inferLinkModeFromValue() {
			var value = $.trim($linkInput.val());

			if (!value || $linkMode.val() === 'none') {
				return;
			}

			if (/^https?:\/\//i.test(value)) {
				$linkMode.val('external');
				updateLinkFields();
				return;
			}

			if (/^\/(?!\/)/.test(value)) {
				$linkMode.val('internal');
				updateLinkFields();
			}
		}

		$linkMode.on('change', updateLinkFields);
		$linkInput.on('change blur', inferLinkModeFromValue);
		updateLinkFields();

		var $positionPreset = $('.headless-hero-position-preset');
		var $customPosition = $('.headless-hero-custom-position');

		function updatePositionFields() {
			if ($positionPreset.val() === 'custom') {
				$customPosition.removeAttr('hidden');
			} else {
				$customPosition.attr('hidden', 'hidden');
			}
		}

		$positionPreset.on('change', updatePositionFields);
		updatePositionFields();
	});
})(jQuery);
