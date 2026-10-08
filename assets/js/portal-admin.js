/* Portal Display settings: media pickers, color pickers, benefit repeaters. */
(function ($) {
	'use strict';

	$(function () {
		$('.rwdpa-color').wpColorPicker();

		$(document).on('click', '.rwdpa-media-select', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.rwdpa-media-field');
			var frame = wp.media({ multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
				$field.find('.rwdpa-media-id').val(attachment.id);
				$field.find('.rwdpa-media-preview').attr('src', url).show();
				$field.find('.rwdpa-media-remove').show();
			});
			frame.open();
		});

		$(document).on('click', '.rwdpa-media-remove', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.rwdpa-media-field');
			$field.find('.rwdpa-media-id').val('0');
			$field.find('.rwdpa-media-preview').attr('src', '').hide();
			$(this).hide();
		});

		// Rows are renumbered on every change so PHP receives a clean list.
		function renumber($repeater) {
			var name = $repeater.data('name');
			$repeater.find('.rwdpa-repeater-row').each(function (i) {
				$(this).find('input').attr('name', name + '[' + i + '][text]');
			});
		}

		$(document).on('click', '.rwdpa-repeater-add', function (e) {
			e.preventDefault();
			var $repeater = $(this).closest('.rwdpa-repeater');
			var $row = $repeater.find('.rwdpa-repeater-row').first().clone();
			$row.find('input').val('');
			$row.insertBefore(this);
			renumber($repeater);
		});

		$(document).on('click', '.rwdpa-repeater-remove', function (e) {
			e.preventDefault();
			var $repeater = $(this).closest('.rwdpa-repeater');
			if ($repeater.find('.rwdpa-repeater-row').length > 1) {
				$(this).closest('.rwdpa-repeater-row').remove();
			} else {
				$(this).closest('.rwdpa-repeater-row').find('input').val('');
			}
			renumber($repeater);
		});
	});
})(jQuery);
