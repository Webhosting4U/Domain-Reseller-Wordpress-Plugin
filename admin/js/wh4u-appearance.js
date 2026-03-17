/**
 * WH4U Domains - Appearance Settings Live Preview
 *
 * Sends control values to the preview iframe via postMessage.
 * Uses debouncing for smooth slider/color-picker interaction.
 */
(function($) {
	'use strict';

	var iframe = document.getElementById('wh4u-appearance-preview-frame');
	if (!iframe) return;

	var debounceTimer;
	var pending = {};

	function sendToPreview(data) {
		if (!iframe.contentWindow) return;
		iframe.contentWindow.postMessage({ type: 'wh4u-preview', data: data }, window.location.origin);
	}

	function flushPending() {
		if (Object.keys(pending).length > 0) {
			sendToPreview(pending);
			pending = {};
		}
	}

	function queueUpdate(key, value) {
		pending[key] = value;
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(flushPending, 150);
	}

	function collectVisibleFields() {
		var fields = [];
		$('[data-preview="visible_field"]').each(function() {
			if (this.checked) {
				fields.push(this.value);
			}
		});
		$('input[type="hidden"][name="' + wh4uAppearance.optionKey + '[visible_fields][]"]').each(function() {
			if (fields.indexOf(this.value) === -1) {
				fields.push(this.value);
			}
		});
		return fields;
	}

	function collectPeriodOptions() {
		var periods = [];
		$('[data-preview="period_option"]').each(function() {
			if (this.checked) {
				periods.push(parseInt(this.value, 10));
			}
		});
		return periods;
	}

	// Range sliders: update label and queue preview
	$(document).on('input', '#wh4u-font-size-range', function() {
		$('#wh4u-font-size-value').text(this.value + 'px');
		queueUpdate('font_size', parseInt(this.value, 10));
	});

	$(document).on('input', '#wh4u-max-width-range', function() {
		$('#wh4u-max-width-value').text(this.value + 'px');
		queueUpdate('max_width', parseInt(this.value, 10));
	});

	$(document).on('input', '#wh4u-border-radius-range', function() {
		$('#wh4u-border-radius-value').text(this.value + 'px');
		queueUpdate('border_radius', parseInt(this.value, 10));
	});

	$(document).on('input', '#wh4u-suggestion-count-range', function() {
		$('#wh4u-suggestion-count-value').text(this.value);
		queueUpdate('suggestion_count', parseInt(this.value, 10));
	});

	// Selects and text inputs with data-preview
	$(document).on('change input', '[data-preview]', function() {
		var key = $(this).data('preview');
		if (!key) return;

		// Skip range sliders (handled above) and special types
		if (this.type === 'range') return;
		if (key === 'visible_field' || key === 'period_option') return;

		var value;
		if (this.type === 'checkbox') {
			value = this.checked;
		} else {
			value = this.value;
		}

		queueUpdate(key, value);
	});

	// Visible fields checkboxes
	$(document).on('change', '[data-preview="visible_field"]', function() {
		queueUpdate('visible_fields', collectVisibleFields());
	});

	// Period options checkboxes
	$(document).on('change', '[data-preview="period_option"]', function() {
		queueUpdate('period_options', collectPeriodOptions());
	});

	// Font family toggle for custom font row
	$(document).on('change', '#wh4u-font-family-select', function() {
		var row = $('#wh4u-custom-font-row');
		if (this.value === 'custom') {
			row.show();
		} else {
			row.hide();
		}
	});

	// wp-color-picker integration
	$(function() {
		$('.wh4u-color-picker').each(function() {
			var $input = $(this);
			var previewKey = $input.data('preview');

			$input.wpColorPicker({
				change: function(event, ui) {
					if (previewKey) {
						queueUpdate(previewKey, ui.color.toString());
					}
				},
				clear: function() {
					if (previewKey) {
						queueUpdate(previewKey, '');
					}
				}
			});
		});

		// Send initial state to iframe once it loads.
		// Text/textarea fields that are blank are intentionally skipped so the
		// PHP-rendered defaults (placeholder, button text, form title, etc.)
		// are preserved rather than overwritten with an empty string.
		iframe.addEventListener('load', function() {
			var data = {};
			$('[data-preview]').each(function() {
				var key = $(this).data('preview');
				if (!key || key === 'visible_field' || key === 'period_option') return;
				if (this.type === 'checkbox') {
					data[key] = this.checked;
				} else if (this.type !== 'range') {
					// Skip empty text/textarea values on initial load; PHP defaults
					// are already rendered correctly in the iframe HTML.
					if ((this.type === 'text' || this.tagName.toLowerCase() === 'textarea') && this.value === '') {
						return;
					}
					data[key] = this.value;
				}
			});

			// Ranges
			data.font_size = parseInt($('#wh4u-font-size-range').val(), 10);
			data.max_width = parseInt($('#wh4u-max-width-range').val(), 10);
			data.border_radius = parseInt($('#wh4u-border-radius-range').val(), 10);
			data.suggestion_count = parseInt($('#wh4u-suggestion-count-range').val(), 10);

			// Color pickers (read from the actual inputs, not the wp-color-picker widget)
			$('.wh4u-color-picker').each(function() {
				var k = $(this).data('preview');
				if (k) data[k] = this.value || '';
			});

			data.visible_fields = collectVisibleFields();
			data.period_options = collectPeriodOptions();

			sendToPreview(data);
		});
	});

})(jQuery);
