/* global wh4uPreview */
(function(){
	var allowedOrigin = wh4uPreview.allowedOrigin;

	window.addEventListener('message', function(e) {
		if (e.origin !== allowedOrigin) return;
		if (!e.data || e.data.type !== 'wh4u-preview') return;

		var d = e.data.data;
		var root = document.querySelector('.wh4u-domains');
		if (!root) return;

		if (d.accent_color !== undefined) root.style.setProperty('--wh4u-accent', d.accent_color || '');
		if (d.text_color !== undefined) root.style.setProperty('--wh4u-text', d.text_color || '');
		if (d.bg_color !== undefined) root.style.setProperty('--wh4u-bg', d.bg_color || '');
		if (d.border_color !== undefined) root.style.setProperty('--wh4u-border', d.border_color || '');
		if (d.available_color !== undefined) root.style.setProperty('--wh4u-available', d.available_color || '');
		if (d.unavailable_color !== undefined) root.style.setProperty('--wh4u-unavailable', d.unavailable_color || '');
		if (d.border_radius !== undefined) root.style.setProperty('--wh4u-radius', d.border_radius + 'px');
		if (d.max_width !== undefined) root.style.maxWidth = d.max_width + 'px';
		if (d.font_size !== undefined) root.style.fontSize = d.font_size + 'px';

		if (d.font_family !== undefined) {
			var ff = 'inherit';
			if (d.font_family === 'system') ff = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
			else if (d.font_family === 'custom' && d.custom_font) {
				var safeFontName = String(d.custom_font).replace(/[";{}\\<>]/g, '');
				if (safeFontName) ff = '"' + safeFontName + '", sans-serif';
			}
			root.style.fontFamily = ff;
		}

		if (d.button_font_weight !== undefined) {
			var btns = root.querySelectorAll('.wh4u-domains__btn, .wh4u-domains__btn-primary, .wh4u-domains__register-btn');
			for (var i = 0; i < btns.length; i++) btns[i].style.fontWeight = d.button_font_weight;
		}

		if (d.spacing !== undefined) {
			var gap = d.spacing === 'compact' ? '0.75rem' : (d.spacing === 'relaxed' ? '1.5rem' : '1rem');
			root.style.setProperty('--wh4u-gap', gap);
		}

		if (d.style_variant !== undefined && ['elevated','flat','bordered','minimal'].indexOf(d.style_variant) !== -1) {
			root.className = root.className.replace(/wh4u-domains--\S+/g, '');
			root.classList.add('wh4u-domains--' + d.style_variant);
		}

		var input = root.querySelector('.wh4u-domains__input');
		if (d.placeholder !== undefined && input) input.placeholder = d.placeholder || '';

		var btnText = root.querySelector('.wh4u-domains__btn .wh4u-domains__btn-text');
		if (d.button_text !== undefined && btnText) btnText.textContent = d.button_text || '';

		var formTitle = root.querySelector('.wh4u-domains__form-title');
		if (d.form_title !== undefined && formTitle) formTitle.textContent = d.form_title || '';

		var formDesc = root.querySelector('.wh4u-domains__form-desc');
		if (d.form_description !== undefined && formDesc) formDesc.textContent = d.form_description || '';

		var icon = root.querySelector('.wh4u-domains__search-icon');
		if (d.show_search_icon !== undefined && icon) icon.style.display = d.show_search_icon ? '' : 'none';

		if (d.visible_fields !== undefined) {
			var fieldMap = {
				'firstName': 'wh4u-reg-firstname', 'lastName': 'wh4u-reg-lastname',
				'email': 'wh4u-reg-email', 'phone': 'wh4u-reg-phone',
				'company': 'wh4u-reg-company', 'address': 'wh4u-reg-address',
				'city': 'wh4u-reg-city', 'state': 'wh4u-reg-state',
				'country': 'wh4u-reg-country', 'zip': 'wh4u-reg-zip'
			};
			for (var fname in fieldMap) {
				var el = document.getElementById(fieldMap[fname]);
				if (el) {
					var wrap = el.closest('.wh4u-domains__field');
					if (wrap) wrap.style.display = d.visible_fields.indexOf(fname) !== -1 ? '' : 'none';
				}
			}
		}

		if (d.css_mode !== undefined) {
			var fullLink = document.getElementById('wh4u-preview-full-css-css');
			var minLink = document.getElementById('wh4u-preview-minimal-css-css');
			if (fullLink) fullLink.disabled = (d.css_mode !== 'full');
			if (minLink) minLink.disabled = (d.css_mode !== 'minimal');
		}

		var formSection = root.querySelector('.wh4u-domains__form-section');
		if (formSection && (d.visible_fields !== undefined || d.form_title !== undefined || d.form_description !== undefined)) {
			formSection.style.display = '';
		}
	});

	var fs = document.querySelector('.wh4u-domains__form-section');
	if (fs) fs.style.display = '';
})();
