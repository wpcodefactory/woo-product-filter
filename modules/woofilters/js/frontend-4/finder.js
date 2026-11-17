/*
 * Product Filter by WBW - storefront engine 4: the step by step finder.
 * One question per step, progress segments, chips of answers, counts per option, back, skip to all.
 */
(function () {
	'use strict';
	var W = window.WPF4 = window.WPF4 || {};

	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

	/** A step answered from a list of counted values: a terms block, or a registered block kind (config.steps[].custom). */
	function isList(step) {
		return !!step && (step.kind === 'terms' || step.custom === true);
	}

	function Finder(filter) {
		this.filter = filter;
		this.root = filter.root;
		this.cfg = filter.config.finder || {};
		this.el = this.root.querySelector('[data-wpf4-finder]');
		this.openBtn = this.root.querySelector('[data-wpf4-finder-open]');
		this.ok = !!(this.el && this.openBtn && this.cfg.steps && this.cfg.steps.length);
		if (!this.ok) { return; }
		this.steps = this.cfg.steps;
		this.answers = {};
		this.step = 0;
		this.open = false;
		this.facets = null;
		this.count = null;
		this.lastFocus = null;
		this.q = {
			steps: this.el.querySelector('[data-wpf4-finder-steps]'),
			answers: this.el.querySelector('[data-wpf4-finder-answers]'),
			kicker: this.el.querySelector('[data-wpf4-finder-kicker]'),
			question: this.el.querySelector('[data-wpf4-finder-question]'),
			help: this.el.querySelector('[data-wpf4-finder-help]'),
			options: this.el.querySelector('[data-wpf4-finder-options]'),
			next: this.el.querySelector('[data-wpf4-finder-next]'),
			back: this.el.querySelector('[data-wpf4-finder-back]'),
			skip: this.el.querySelector('[data-wpf4-finder-skip]')
		};
		this.bind();
	}

	Finder.prototype.bind = function () {
		var self = this;
		this.openBtn.addEventListener('click', function () { self.show(); });
		this.q.skip.addEventListener('click', function () {
			self.answers = {};
			self.hide();
			self.filter.clearAll();
		});
		this.q.back.addEventListener('click', function () { if (self.step > 0) { self.step--; self.render(); } });
		this.q.next.addEventListener('click', function () { self.advance(); });
		this.el.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { e.preventDefault(); self.hide(); return; }
			if (e.key !== 'Tab') { return; }
			var nodes = Array.prototype.filter.call(self.el.querySelectorAll(FOCUSABLE), function (n) { return n.offsetParent !== null; });
			if (!nodes.length) { return; }
			var first = nodes[0], last = nodes[nodes.length - 1];
			if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
		});
		this.q.options.addEventListener('click', function (e) {
			var btn = e.target.closest('.wpf4-finder__opt');
			if (!btn) { return; }
			var step = self.steps[self.step];
			var value = btn.dataset.value;
			if (step.single || !isList(step)) {
				self.answers[step.key] = value === '' ? null : { value: value, label: btn.dataset.label };
				self.paintPressed();
				// A single answer moves on by itself after the pick is visible.
				setTimeout(function () { self.advance(); }, W.reducedMotion() ? 0 : 220);
			} else {
				var cur = self.answers[step.key] || { values: [], labels: {} };
				var i = cur.values.indexOf(value);
				if (i >= 0) { cur.values.splice(i, 1); delete cur.labels[value]; } else { cur.values.push(value); cur.labels[value] = btn.dataset.label; }
				self.answers[step.key] = cur.values.length ? cur : null;
				self.paintPressed();
			}
		});
	};

	Finder.prototype.show = function () {
		if (!this.ok) { return; }
		if (this.open) { return; }
		this.open = true;
		this.lastFocus = document.activeElement;
		// Start from what is already picked in the filter.
		this.answers = this.fromParams(this.filter.collect());
		this.step = 0;
		this.el.hidden = false;
		document.body.classList.add('wpf4-locked');
		this.render();
		document.dispatchEvent(new CustomEvent('wpf:finder', { detail: { open: true, filterId: this.filter.id } }));
	};

	Finder.prototype.hide = function () {
		if (!this.ok) { return; }
		if (!this.open) { return; }
		this.open = false;
		this.el.hidden = true;
		document.body.classList.remove('wpf4-locked');
		if (this.lastFocus && document.contains(this.lastFocus)) { this.lastFocus.focus(); } else { this.openBtn.focus(); }
		document.dispatchEvent(new CustomEvent('wpf:finder', { detail: { open: false, filterId: this.filter.id } }));
	};

	Finder.prototype.fromParams = function (params) {
		var out = {};
		this.steps.forEach(function (s) {
			var v = params['wpf_' + s.key];
			if (!v) { return; }
			if (isList(s) && !s.single) {
				var labels = {};
				v.split(',').forEach(function (x) { labels[x] = x; });
				out[s.key] = { values: v.split(','), labels: labels };
			} else {
				out[s.key] = { value: v, label: v };
			}
		});
		return out;
	};

	/**
	 * Canonical params from the answers given so far (up to and excluding the current step when asked).
	 */
	Finder.prototype.toParams = function (uptoStep) {
		var params = {};
		var self = this;
		this.steps.forEach(function (s, i) {
			if (typeof uptoStep === 'number' && i >= uptoStep) { return; }
			var a = self.answers[s.key];
			if (!a) { return; }
			params['wpf_' + s.key] = a.values ? a.values.join(',') : a.value;
		});
		return params;
	};

	Finder.prototype.advance = function () {
		if (this.step < this.steps.length - 1) {
			this.step++;
			this.render();
		} else {
			var params = this.toParams();
			this.hide();
			this.filter.applyParams(params);
			this.filter.request('finder');
		}
	};

	Finder.prototype.render = function () {
		if (!this.ok) { return; }
		var self = this;
		var step = this.steps[this.step];
		var total = this.steps.length;
		// Steps.
		this.q.steps.innerHTML = '';
		this.steps.forEach(function (s, i) {
			var seg = document.createElement('span');
			seg.className = 'wpf4-finder__step' + (i < self.step ? ' is-done' : (i === self.step ? ' is-current' : ''));
			seg.setAttribute('role', 'img');
			seg.setAttribute('aria-label', i < self.step ? W.t('stepDone', i + 1) : (i === self.step ? W.t('stepCurrent', i + 1) : W.t('step', i + 1)));
			if (i === self.step) { seg.setAttribute('aria-current', 'step'); }
			self.q.steps.appendChild(seg);
		});
		// Answer chips (answers of earlier steps).
		this.q.answers.innerHTML = '';
		this.steps.forEach(function (s, i) {
			if (i >= self.step) { return; }
			var a = self.answers[s.key];
			if (!a) { return; }
			var labels = a.values ? a.values.map(function (v) { return a.labels[v] || v; }) : [a.label || a.value];
			labels.forEach(function (l) {
				var chip = document.createElement('span');
				chip.className = 'wpf4-finder__answer';
				chip.textContent = (s.kind === 'terms' && /^\d/.test(l)) ? s.title + ' ' + l : l;
				self.q.answers.appendChild(chip);
			});
		});
		this.q.kicker.textContent = W.t('question', this.step + 1, total);
		this.q.question.textContent = step.question || step.title;
		this.q.help.textContent = W.t('loading');
		this.q.back.hidden = this.step === 0;
		this.q.next.textContent = this.step < total - 1 ? W.t('next', (this.steps[this.step + 1].title || '').toLowerCase()) : W.t('showAll');
		this.q.options.innerHTML = '';
		this.q.question.focus();
		// Counts for this step given the answers so far.
		this.filter.fetchFacets(this.toParams(this.step)).then(function (res) {
			if (!self.open || self.steps[self.step] !== step) { return; }
			self.facets = res.facets || {};
			self.count = res.count;
			self.q.help.textContent = W.t('finderHelp', W.formatCount(res.count));
			self.renderOptions(step, self.facets[step.key] || {});
		}).catch(function () {
			self.q.help.textContent = W.t('error');
		});
	};

	Finder.prototype.renderOptions = function (step, facet) {
		var self = this;
		var opts = [];
		var showCounts = this.cfg.showCounts !== false;
		if (isList(step)) {
			var counts = facet.counts || {};
			var labels = facet.labels || {};
			// Keep the order of the filter's own block when it is on the page.
			var block = this.root.querySelector('[data-wpf4-block][data-key="' + step.key + '"]');
			var order = [];
			var swatches = {};
			if (block) {
				block.querySelectorAll('.wpf4__options--desktop input[data-wpf4-control], .wpf4__options--desktop option').forEach(function (i) {
					if (i.value !== '' && order.indexOf(i.value) < 0) { order.push(i.value); }
					var sw = i.parentNode && i.parentNode.querySelector ? i.parentNode.querySelector('.wpf4__swatchColor') : null;
					if (sw) { swatches[i.value] = sw.getAttribute('style'); }
					if (i.dataset.label) { labels[i.value] = labels[i.value] || i.dataset.label; }
				});
			}
			Object.keys(counts).forEach(function (k) { if (order.indexOf(k) < 0) { order.push(k); } });
			order.forEach(function (slug) {
				var c = counts[slug] || 0;
				if (!c) { return; }
				opts.push({ value: slug, label: labels[slug] || slug, count: c, swatch: swatches[slug] });
			});
		} else if (step.kind === 'price') {
			var ranges = facet.ranges || [];
			ranges.forEach(function (r) {
				if (!r.count) { return; }
				opts.push({ value: r.value, label: r.label, count: r.count });
			});
		} else if (step.kind === 'stock') {
			var sc = facet.counts || {};
			opts.push({ value: 'in', label: W.t('inStock'), count: sc['in'] || 0 });
		} else if (step.kind === 'rating') {
			var rc = facet.counts || {};
			[4, 3, 2].forEach(function (n) {
				if (rc[String(n)]) { opts.push({ value: String(n), label: W.t('starsUp', n), count: rc[String(n)] }); }
			});
		}
		var frag = document.createDocumentFragment();
		opts.forEach(function (o) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'wpf4-finder__opt';
			b.dataset.value = o.value;
			b.dataset.label = o.label;
			b.setAttribute(step.single || !isList(step) ? 'aria-pressed' : 'aria-checked', 'false');
			if (!(step.single || !isList(step))) { b.setAttribute('role', 'checkbox'); }
			var lab = document.createElement('span');
			lab.className = 'wpf4-finder__optLabel';
			if (o.swatch) {
				var sw = document.createElement('span');
				sw.className = 'wpf4-finder__optSwatch';
				sw.setAttribute('style', o.swatch);
				sw.setAttribute('aria-hidden', 'true');
				lab.appendChild(sw);
			}
			lab.appendChild(document.createTextNode(o.label));
			b.appendChild(lab);
			if (showCounts) {
				var c = document.createElement('span');
				c.className = 'wpf4-finder__optCount';
				c.textContent = W.formatCount(o.count);
				b.appendChild(c);
			}
			frag.appendChild(b);
		});
		if (!opts.length) {
			// Nothing to choose at this step (one product left, or a flat price): offer "Any" so the shopper can go on.
			var any = document.createElement('button');
			any.type = 'button';
			any.className = 'wpf4-finder__opt';
			any.dataset.value = '';
			any.dataset.label = W.t('any');
			any.setAttribute('aria-pressed', 'false');
			var al = document.createElement('span');
			al.className = 'wpf4-finder__optLabel';
			al.textContent = W.t('any');
			any.appendChild(al);
			frag.appendChild(any);
			var p = document.createElement('p');
			p.className = 'wpf4-finder__help';
			p.textContent = W.t('noOptions');
			frag.appendChild(p);
		}
		this.q.options.innerHTML = '';
		this.q.options.appendChild(frag);
		this.paintPressed();
	};

	Finder.prototype.paintPressed = function () {
		var step = this.steps[this.step];
		var a = this.answers[step.key];
		this.q.options.querySelectorAll('.wpf4-finder__opt').forEach(function (b) {
			var on = a ? (a.values ? a.values.indexOf(b.dataset.value) >= 0 : a.value === b.dataset.value) : false;
			b.setAttribute(b.hasAttribute('aria-checked') ? 'aria-checked' : 'aria-pressed', on ? 'true' : 'false');
		});
	};

	W.Finder = Finder;
})();
