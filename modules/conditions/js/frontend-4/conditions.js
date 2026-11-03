/*
 * Product Filter by WBW - storefront engine 4: block conditions.
 * "Show this block only after a pick in X", "Hide when fewer than N products", "Hide when it has only one choice".
 * Rules come from the filter config (config.conditions) or the fieldset's data-cond-* attributes; they are evaluated
 * on load, on every change and on every facets response. A hidden block gets the `hidden` attribute and aria-hidden,
 * its picks are cleared (chips go with them) and a request runs when something was cleared. The desktop copy and the
 * phone copy (the drawer moves the same form) follow. The finder skips a dependent step while its parent has no answer.
 * API: window.wpf4.conditions.evaluate( filter ) (also window.WPF4.conditions). Event on document: wpf4:conditions
 * { filterId, filter, visible: { key: bool }, hidden: [keys just hidden], shown: [keys just shown], cleared: [keys] }.
 */
(function () {
	'use strict';
	var W = window.WPF4 = window.WPF4 || {};
	var C = {};

	/** Rules of a filter: key => { parent, min, single }. */
	C.rules = function (filter) {
		if (filter._condRules) { return filter._condRules; }
		var rules = {};
		var cfg = (filter.config && filter.config.conditions) || {};
		Object.keys(cfg).forEach(function (k) {
			var r = cfg[k] || {};
			rules[k] = { parent: String(r.parent || ''), min: parseInt(r.min, 10) || 0, single: !!r.single };
		});
		filter.root.querySelectorAll('[data-wpf4-block]').forEach(function (b) {
			var k = b.dataset.key;
			if (!k || rules[k] || (!b.dataset.condParent && !b.dataset.condMin && !b.dataset.condSingle)) { return; }
			rules[k] = { parent: b.dataset.condParent || '', min: parseInt(b.dataset.condMin, 10) || 0, single: b.dataset.condSingle === '1' };
		});
		filter._condRules = rules;
		return rules;
	};

	function blocksOf(filter, key) {
		return Array.prototype.slice.call(filter.root.querySelectorAll('[data-wpf4-block][data-key="' + key.replace(/"/g, '\\"') + '"]'));
	}

	/** Is anything picked in a block (the enabled controls of the form)? */
	C.picked = function (filter, key) {
		var name = 'wpf_' + key;
		var slider = filter.sliderFor(key);
		if (slider && !slider.isDefault()) { return true; }
		var any = false;
		filter.controls().forEach(function (c) {
			if (any || c.name !== name || c.dataset.range === '1') { return; }
			if (c.type === 'checkbox' || c.type === 'radio') {
				if (c.checked && c.value !== '') { any = true; }
			} else if (c.value !== '' && !(key === 'sort' && c.value === 'default')) {
				any = true;
			}
		});
		return any;
	};

	/** Reset every control of a block (desktop and phone copies). Returns true when something was picked. */
	C.clear = function (filter, key) {
		var changed = false;
		var slider = filter.sliderFor(key);
		if (slider && !slider.isDefault()) { slider.set(slider.min, slider.max, true); changed = true; }
		blocksOf(filter, key).forEach(function (block) {
			block.querySelectorAll('input, select').forEach(function (c) {
				if (!c.name || c.dataset.range === '1') { return; }
				if (c.type === 'checkbox' || c.type === 'radio') {
					var want = c.type === 'radio' && c.value === '';
					if (c.checked !== want) {
						if (c.checked && c.value !== '') { changed = true; }
						c.checked = want;
						if (c.getAttribute('role') === 'switch') { c.setAttribute('aria-checked', want ? 'true' : 'false'); }
						var label = c.closest('.wpf4__opt');
						if (label) { label.classList.toggle('is-checked', want); }
					}
				} else if (c.value !== '' && !(key === 'sort' && c.value === 'default')) {
					c.value = '';
					changed = true;
				}
			});
		});
		return changed;
	};

	/** Number of choices with products in a block, from the facets or the rendered counts; null when not countable. */
	C.choices = function (filter, key, facets) {
		var block = blocksOf(filter, key)[0];
		if (!block) { return null; }
		var kind = block.dataset.kind;
		if (kind === 'sale' || kind === 'featured' || kind === 'sort' || kind === 'q' || kind === 'text') { return null; }
		var f = facets && facets[key];
		if (f && f.counts) {
			return Object.keys(f.counts).filter(function (v) { return parseInt(f.counts[v], 10) > 0; }).length;
		}
		if (f && (kind === 'price' || kind === 'meta') && f.min !== undefined && f.max !== undefined) {
			return parseFloat(f.max) > parseFloat(f.min) ? 2 : 1;
		}
		var counted = block.querySelectorAll('.wpf4__options--desktop [data-wpf4-control][data-count], .wpf4__options--desktop option[data-count]');
		if (!counted.length) { return null; }
		var n = 0;
		var seen = {};
		counted.forEach(function (c) {
			if (c.value === '' || seen[c.value]) { return; }
			seen[c.value] = 1;
			if (parseInt(c.dataset.count, 10) > 0) { n++; }
		});
		return n;
	};

	/**
	 * Evaluate every rule of a filter and show or hide its blocks.
	 * opts: { facets, count } override what the filter last received; { request: false } never sends a request.
	 */
	C.evaluate = function (filter, opts) {
		opts = opts || {};
		if (!filter || !filter.root) { return null; }
		var rules = C.rules(filter);
		var keys = Object.keys(rules);
		var facets = opts.facets || (filter.lastResponse && filter.lastResponse.facets) || null;
		var count = typeof opts.count === 'number' ? opts.count : (typeof filter.count === 'number' ? filter.count : null);
		var visible = {};
		var hidden = [], shown = [], cleared = [];
		var isVisible = function (key, stack) {
			if (visible[key] !== undefined) { return visible[key]; }
			var r = rules[key];
			if (!r) { return true; }
			stack = stack || {};
			if (stack[key]) { return true; } // a loop of parents: nobody waits for anybody
			stack[key] = 1;
			var v = true;
			if (r.parent && r.parent !== key && blocksOf(filter, r.parent).length) {
				v = isVisible(r.parent, stack) && C.picked(filter, r.parent);
			}
			if (v && r.min > 0 && count !== null && count < r.min) { v = false; }
			if (v && r.single) {
				var n = C.choices(filter, key, facets);
				if (n !== null && n <= 1) { v = false; }
			}
			visible[key] = v;
			return v;
		};
		keys.forEach(function (key) {
			var v = isVisible(key);
			blocksOf(filter, key).forEach(function (block) {
				delete block.dataset.condStart;
				if (!v) {
					if (!block.hidden) {
						block.hidden = true;
						block.setAttribute('aria-hidden', 'true');
						block.classList.add('is-cond-hidden');
						hidden.push(key);
					}
				} else if (block.classList.contains('is-cond-hidden')) {
					block.hidden = false;
					block.removeAttribute('aria-hidden');
					block.classList.remove('is-cond-hidden');
					shown.push(key);
				}
			});
			if (!v && C.picked(filter, key) && C.clear(filter, key)) { cleared.push(key); }
		});
		if (cleared.length) {
			filter.page = 1;
			filter.updateDropdownLabels();
			filter.syncStars();
			filter.renderChips();
			if (opts.request !== false) { filter.request('conditions'); }
		} else if (shown.length || hidden.length) {
			filter.refold();
			if (filter.drawer && filter.drawer.ok) { filter.drawer.updateCount(); }
		}
		if (keys.length) {
			document.dispatchEvent(new CustomEvent('wpf4:conditions', { detail: { filterId: filter.id, filter: filter, visible: visible, hidden: hidden, shown: shown, cleared: cleared } }));
		}
		return visible;
	};

	/** Every filter of an id on the page (a sidebar copy and a copy above the loop share one state). */
	function twins(id) {
		return W.filters.filter(function (f) { return String(f.id) === String(id); });
	}

	document.addEventListener('wpf:ready', function (e) {
		if (e.detail && e.detail.filter) { C.evaluate(e.detail.filter); }
	});
	// A change: a cleared parent empties and hides its children before the request collects the controls.
	document.addEventListener('wpf:change', function (e) {
		if (!e.detail) { return; }
		twins(e.detail.filterId).forEach(function (f) { C.evaluate(f, { request: false }); });
	});
	// New counts: "fewer than N" and "only one choice" follow the results; the first copy sends the request when a
	// hidden block still had picks.
	document.addEventListener('wpf:results', function (e) {
		if (!e.detail) { return; }
		var res = e.detail.response || {};
		var all = twins(e.detail.filterId);
		all.forEach(function (f, i) {
			C.evaluate(f, { facets: res.facets || null, count: e.detail.count, request: i === 0 });
		});
	});

	/* ---------------------------------------------------------------- finder: a dependent step waits for its parent */

	if (W.Finder && W.Finder.prototype) {
		var skipped = function (finder, step) {
			if (!step) { return false; }
			var r = C.rules(finder.filter)[step.key];
			if (!r || !r.parent || r.parent === step.key) { return false; }
			var isStep = finder.steps.some(function (s) { return s.key === r.parent; });
			if (isStep) { return !finder.answers[r.parent]; }
			return blocksOf(finder.filter, r.parent).length > 0 && !C.picked(finder.filter, r.parent);
		};
		var origRender = W.Finder.prototype.render;
		W.Finder.prototype.render = function () {
			if (this.ok && this.steps && this.steps.length) {
				var dir = typeof this._condPrev === 'number' && this.step < this._condPrev ? -1 : 1;
				var i = this.step;
				while (i >= 0 && i < this.steps.length && skipped(this, this.steps[i])) { i += dir; }
				if (i < 0) {
					i = this.step;
					while (i < this.steps.length && skipped(this, this.steps[i])) { i++; }
				}
				if (i >= this.steps.length) {
					// Nothing left to ask: show the results, as the last "Next" does.
					var params = this.toParams();
					this.hide();
					this.filter.applyParams(params);
					this.filter.request('finder');
					return;
				}
				this.step = i;
				this._condPrev = i;
			}
			var out = origRender.apply(this, arguments);
			if (this.ok && this.q && this.q.next) {
				var next = null;
				for (var j = this.step + 1; j < this.steps.length; j++) {
					if (!skipped(this, this.steps[j])) { next = this.steps[j]; break; }
				}
				this.q.next.textContent = next ? W.t('next', (next.title || '').toLowerCase()) : W.t('showAll');
			}
			return out;
		};
	}

	W.conditions = C;
	window.wpf4 = window.wpf4 || {};
	window.wpf4.conditions = C;

	// filter.js booted before this file ran: evaluate the filters already on the page.
	(W.filters || []).forEach(function (f) { C.evaluate(f); });
})();
