(function ($) {
	'use strict';

	$(function () {
		$('.headless-hero-mobile-image-control').each(function () {
			var $control = $(this);
			var frame = null;

			$control.on('click', '.headless-hero-select-mobile-image', function (event) {
				event.preventDefault();

				if (frame) {
					frame.open();
					return;
				}

				frame = wp.media({
					title: 'Select mobile image',
					button: { text: 'Use this image' },
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var previewUrl = attachment.url;

					if (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) {
						previewUrl = attachment.sizes.medium.url;
					}

					$control.find('.headless-hero-mobile-image-id').val(attachment.id);
					$control.find('.headless-hero-mobile-image-preview').html(
						$('<img>', {
							src: previewUrl,
							alt: '',
							class: 'headless-hero-mobile-image-preview-image'
						})
					);
					$control.find('.headless-hero-remove-mobile-image').removeAttr('hidden');
				});

				frame.open();
			});

			$control.on('click', '.headless-hero-remove-mobile-image', function (event) {
				event.preventDefault();
				$control.find('.headless-hero-mobile-image-id').val('');
				$control.find('.headless-hero-mobile-image-preview').empty();
				$(this).attr('hidden', 'hidden');
			});
		});
	});
})(jQuery);
