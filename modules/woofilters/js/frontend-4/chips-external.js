/**
 * Product Filter by WBW - external chips bar for the 4.0 engine (4.0.0)
 *
 * Fills every `[data-wpf4-chips-external]` container (printed by the `[wpf-selected-filters]`
 * shortcode) with the picked values of the filter named in data-filter, using the same chip
 * markup as the filter's own bar, and keeps it in step: renderChips() of every filter is wrapped
 * once so each render of the inner bar also paints the external ones. A click on an external
 * chip removes that value from the filter and runs the request, exactly as the inner chips do.
 *
 * Loaded after filter.js (dependency); does nothing when no container is on the page.
 */
(function () {
	'use strict';
	var W = window.WPF4;
	if (!W) { return; }

	function containers(filter) {
		return Array.prototype.filter.call(document.querySelectorAll('[data-wpf4-chips-external]'), function (el) {
			var id = el.getAttribute('data-filter');
			return !id || id === '0' || String(id) === String(filter.id);
		});
	}

	function paint(filter) {
		var list = filter.chipList();
		var html = list.map(function (c) {
			return '<button type="button" class="wpf4__chip" data-wpf4-chip-external data-key="' + W.esc(c.key) + '" data-value="' + W.esc(c.value) + '">' + W.esc(c.label) + ' <span aria-hidden="true">×</span><span class="wpf4__sr">' + W.esc(W.t('remove')) + '</span></button>';
		}).join('');
		containers(filter).forEach(function (el) {
			if (el.wpf4ChipsHtml !== html) {
				el.innerHTML = html;
				el.wpf4ChipsHtml = html;
			}
			el.hidden = !list.length;
			el.wpf4Owner = filter;
		});
	}

	function wrap(filter) {
		var proto = Object.getPrototypeOf(filter);
		if (!proto || proto.wpf4ExternalChips) { return; }
		var orig = proto.renderChips;
		proto.renderChips = function () {
			var out = orig.apply(this, arguments);
			paint(this);
			return out;
		};
		proto.wpf4ExternalChips = true;
	}

	function attach(filter) {
		if (!filter || !containers(filter).length) { return; }
		wrap(filter);
		paint(filter);
	}

	document.addEventListener('click', function (e) {
		var chip = e.target.closest('[data-wpf4-chip-external]');
		if (!chip) { return; }
		var el = chip.closest('[data-wpf4-chips-external]');
		var filter = el && el.wpf4Owner;
		if (!filter) { return; }
		filter.removeValue(chip.dataset.key, chip.dataset.value);
		var next = chip.nextElementSibling || chip.previousElementSibling;
		filter.lastChanged = next && next.matches('[data-wpf4-chip-external]') ? next : filter.form.querySelector('[data-wpf4-control]');
		chip.remove();
		filter.page = 1;
		filter.renderChips();
		filter.request('chip');
	});

	document.addEventListener('wpf:ready', function (e) {
		attach(e.detail && e.detail.filter);
	});
	(W.filters || []).forEach(attach);
})();
