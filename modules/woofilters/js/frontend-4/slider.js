/*
 * Product Filter by WBW - storefront engine 4: price / number slider.
 * Two native <input type="range"> on one track plus two number inputs with visible values. No jQuery.
 */
(function () {
	'use strict';
	var W = window.WPF4 = window.WPF4 || {};
	var cfg = window.wpf4Config || {};

	function formatNumber(n, decimals, dec, thou) {
		var fixed = Number(n).toFixed(decimals);
		var parts = fixed.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thou || ',');
		return parts.length > 1 ? parts[0] + (dec || '.') + parts[1] : parts[0];
	}

	W.formatPrice = function (n) {
		var c = cfg.currency || { symbol: '$', position: 'left', decimals: 2, dec: '.', thou: ',' };
		var decimals = Number.isInteger(Number(n)) ? 0 : (c.decimals || 0);
		var s = formatNumber(n, decimals, c.dec, c.thou);
		switch (c.position) {
			case 'right': return s + c.symbol;
			case 'left_space': return c.symbol + ' ' + s;
			case 'right_space': return s + ' ' + c.symbol;
			default: return c.symbol + s;
		}
	};

	function Slider(el, filter) {
		this.el = el;
		this.filter = filter;
		this.key = el.dataset.key;
		this.isPrice = el.dataset.price === '1';
		this.min = parseFloat(el.dataset.min) || 0;
		this.max = parseFloat(el.dataset.max) || 0;
		this.step = parseFloat(el.dataset.step) || 1;
		this.rMin = el.querySelector('[data-role="min"]');
		this.rMax = el.querySelector('[data-role="max"]');
		this.fill = el.querySelector('.wpf4__sliderFill');
		var box = el.parentNode;
		this.nMin = box.querySelector('input[type="number"][data-role="min"]');
		this.nMax = box.querySelector('input[type="number"][data-role="max"]');
		this.out = box.querySelector('[data-wpf4-slider-out]');
		this.bounds = box.querySelectorAll('.wpf4__sliderBounds span');
		this.hist = box.querySelector('[data-wpf4-hist]');
		this.bind();
		this.paint();
	}

	Slider.prototype.format = function (v) {
		return this.isPrice ? W.formatPrice(v) : String(Math.round(v * 100) / 100);
	};

	Slider.prototype.values = function () {
		return [parseFloat(this.rMin.value), parseFloat(this.rMax.value)];
	};

	Slider.prototype.isDefault = function () {
		var v = this.values();
		return v[0] <= this.min && v[1] >= this.max;
	};

	Slider.prototype.set = function (lo, hi, silent) {
		lo = isNaN(lo) ? this.min : Math.max(this.min, Math.min(this.max, lo));
		hi = isNaN(hi) ? this.max : Math.max(this.min, Math.min(this.max, hi));
		if (lo > hi) { var t = lo; lo = hi; hi = t; }
		this.rMin.value = lo;
		this.rMax.value = hi;
		this.paint();
		if (!silent) { this.emit(); }
	};

	Slider.prototype.setBounds = function (min, max) {
		if (isNaN(min) || isNaN(max) || max < min) { return; }
		var v = this.values();
		var wasDefault = this.isDefault();
		this.min = Math.floor(min);
		this.max = Math.ceil(max);
		[this.rMin, this.rMax, this.nMin, this.nMax].forEach(function (i) { i.min = this.min; i.max = this.max; }, this);
		this.el.dataset.min = this.min;
		this.el.dataset.max = this.max;
		if (this.bounds.length === 2) { this.bounds[0].textContent = this.format(this.min); this.bounds[1].textContent = this.format(this.max); }
		this.set(wasDefault ? this.min : v[0], wasDefault ? this.max : v[1], true);
	};

	Slider.prototype.paint = function () {
		var v = this.values();
		var span = this.max - this.min || 1;
		var pLo = (v[0] - this.min) / span * 100;
		var pHi = (v[1] - this.min) / span * 100;
		if (this.fill) { this.fill.style.left = pLo + '%'; this.fill.style.right = (100 - pHi) + '%'; }
		this.rMin.setAttribute('aria-valuetext', this.format(v[0]));
		this.rMax.setAttribute('aria-valuetext', this.format(v[1]));
		// The upper thumb must stay reachable when both sit at the same end.
		this.rMin.style.zIndex = v[0] >= this.max - this.step ? 3 : 2;
		if (this.nMin && document.activeElement !== this.nMin) { this.nMin.value = v[0]; }
		if (this.nMax && document.activeElement !== this.nMax) { this.nMax.value = v[1]; }
		if (this.out) { this.out.textContent = W.t('to', this.format(v[0]), this.format(v[1])); }
		this.paintHist(v);
	};

	/* 4.0.0: the price bars. A bar is coloured when the middle of its bucket is inside the picked range. */
	Slider.prototype.paintHist = function (v) {
		if (!this.hist) { return; }
		var bars = this.hist.children, n = bars.length;
		var lo = parseFloat(this.hist.dataset.min), hi = parseFloat(this.hist.dataset.max);
		if (!n || isNaN(lo) || isNaN(hi) || hi <= lo) { return; }
		var w = (hi - lo) / n;
		for (var i = 0; i < n; i++) {
			var mid = lo + (i + 0.5) * w;
			bars[i].classList.toggle('is-in', mid >= v[0] && mid <= v[1]);
		}
	};

	Slider.prototype.setHist = function (hist, min, max) {
		if (!this.hist) { return; }
		if (!hist || !hist.length) { this.hist.hidden = true; return; }
		var top = Math.max.apply(null, hist.concat([1]));
		var html = '';
		hist.forEach(function (c) { html += '<span class="wpf4__histBar" style="height:' + (c > 0 ? Math.max(6, Math.round(c / top * 100)) : 0) + '%"></span>'; });
		this.hist.innerHTML = html;
		this.hist.dataset.min = min;
		this.hist.dataset.max = max;
		this.hist.hidden = false;
		this.paintHist(this.values());
	};

	Slider.prototype.emit = function () {
		// One change event on the number input carries the new range to the filter.
		var ev;
		try { ev = new CustomEvent('change', { bubbles: true }); } catch (e) { ev = document.createEvent('Event'); ev.initEvent('change', true, true); }
		this.nMax.dispatchEvent(ev);
	};

	Slider.prototype.bind = function () {
		var self = this;
		function onInput(which) {
			return function () {
				var lo = parseFloat(self.rMin.value), hi = parseFloat(self.rMax.value);
				if (which === 'min' && lo > hi) { self.rMax.value = lo; }
				if (which === 'max' && hi < lo) { self.rMin.value = hi; }
				self.paint();
			};
		}
		this.rMin.addEventListener('input', onInput('min'));
		this.rMax.addEventListener('input', onInput('max'));
		// Native change fires on release (mouse, touch) and after each keyboard step.
		this.rMin.addEventListener('change', function (e) { e.stopPropagation(); self.emit(); });
		this.rMax.addEventListener('change', function (e) { e.stopPropagation(); self.emit(); });
		[this.nMin, this.nMax].forEach(function (n) {
			if (!n) { return; }
			n.addEventListener('change', function (e) {
				if (e.wpf4FromSlider) { return; }
				var lo = parseFloat(self.nMin.value), hi = parseFloat(self.nMax.value);
				self.set(lo, hi, true);
			});
			n.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); n.dispatchEvent(new Event('change', { bubbles: true })); }
			});
		});
	};

	W.Slider = Slider;
})();
