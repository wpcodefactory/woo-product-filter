/*
 * Product Filter by WBW - swatch fields on the term screens (Products > Attributes > terms).
 */
(function () {
	'use strict';
	var cfg = window.wpfSwatchesAdmin || {};
	var i18n = cfg.i18n || {};

	function t(key, a, b) {
		return (i18n[key] || key).replace('%1$s', a).replace('%2$s', b).replace('%s', a);
	}

	function init() {
		var pick = document.getElementById('wpf_swatch_color_pick');
		var text = document.getElementById('wpf_swatch_color');
		if (pick && text) {
			pick.addEventListener('input', function () { text.value = pick.value.toUpperCase(); });
			text.addEventListener('input', function () {
				var v = text.value.trim();
				if (/^#?[0-9a-fA-F]{6}$/.test(v)) { pick.value = v[0] === '#' ? v : '#' + v; }
			});
		}
		var pickBtn = document.querySelector('[data-wpf-swatch-pick]');
		var hidden = document.getElementById('wpf_swatch_image');
		var preview = document.querySelector('.wpf-swatch-preview');
		var removeBtn = document.querySelector('[data-wpf-swatch-remove]');
		if (pickBtn && hidden && window.wp && window.wp.media) {
			var frame;
			pickBtn.addEventListener('click', function () {
				if (!frame) {
					frame = window.wp.media({ title: t('choose'), button: { text: t('use') }, multiple: false, library: { type: 'image' } });
					frame.on('select', function () {
						var att = frame.state().get('selection').first().toJSON();
						hidden.value = att.id;
						var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
						preview.innerHTML = '<img src="' + url + '" alt="">';
						if (removeBtn) { removeBtn.hidden = false; }
					});
				}
				frame.open();
			});
		}
		if (removeBtn && hidden) {
			removeBtn.addEventListener('click', function () {
				hidden.value = '';
				if (preview) { preview.innerHTML = ''; }
				removeBtn.hidden = true;
			});
		}
		var detect = document.querySelector('[data-wpf-swatch-detect]');
		if (detect) {
			detect.addEventListener('click', function () {
				var out = document.querySelector('[data-wpf-swatch-auto-text]');
				detect.disabled = true;
				if (out) { out.textContent = t('working'); }
				var url = cfg.ajax + '?action=' + encodeURIComponent(cfg.action) + '&term=' + encodeURIComponent(detect.dataset.term) + '&_wpnonce=' + encodeURIComponent(cfg.nonce);
				fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
					detect.disabled = false;
					if (res && res.success && res.data && res.data.color) {
						if (out) { out.textContent = t('found', res.data.color, res.data.source === 'image' ? t('image') : t('name')); }
						var dot = out && out.previousElementSibling && out.previousElementSibling.classList.contains('wpf-swatch-dot') ? out.previousElementSibling : null;
						if (!dot && out) { dot = document.createElement('span'); dot.className = 'wpf-swatch-dot'; dot.setAttribute('aria-hidden', 'true'); out.parentNode.insertBefore(dot, out); }
						if (dot) { dot.style.background = res.data.color; }
						if (pick && text && !text.value) { pick.value = res.data.color; }
					} else if (out) {
						out.textContent = (res && res.data && res.data.message) || t('nothing');
					}
				}).catch(function () {
					detect.disabled = false;
					if (out) { out.textContent = t('nothing'); }
				});
			});
		}
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
