/*
 * Product Filter by WBW - storefront engine 4: the filter bar ("Above the products" placement).
 *
 * On wide screens the filter becomes one row: an "All filters (N)" button, then one dropdown button per block
 * ("Brand · 2 ⌄"). A dropdown button opens that block's choices in a small panel under it; "All filters" opens
 * every block in a scrollable pop-up over a dim overlay, with "Clear all" and "Show N products". The blocks are
 * moved, never copied, so picks, counts, the address and the live results work exactly as in the normal layout;
 * they go back to their place when a panel closes. Below the phone breakpoint the normal layout (or the phone
 * drawer) is used.
 *
 * @version 4.0.0
 * @since   4.0.0
 */
(function () {
	'use strict';
	var W = window.WPF4 = window.WPF4 || {};

	function el(tag, cls, html) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (html !== undefined) { n.innerHTML = html; }
		return n;
	}
	function esc(s) {
		return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}
	var CHEVRON = '<svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
	var SLIDERS = '<svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>';
	var CLOSE = '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>';

	function Bar(filter) {
		this.filter = filter;
		this.root = filter.root;
		this.form = filter.form;
		this.cfg = filter.config.bar || {};
		this.ok = !!(this.cfg.enabled && this.form);
		if (!this.ok) { return; }
		this.i18n = this.cfg.i18n || {};
		this.blocksBox = this.form.querySelector('[data-wpf4-blocks]');
		if (!this.blocksBox) { this.ok = false; return; }
		var bp = parseInt(this.cfg.breakpoint, 10) || 768;
		this.mq = window.matchMedia('(min-width: ' + bp + 'px)');
		this.openKey = null;
		this.homes = {};
		this.build();
		this.bind();
		this.apply();
	}

	Bar.prototype.blocks = function () {
		return Array.prototype.slice.call(this.root.querySelectorAll('[data-wpf4-block]'));
	};

	Bar.prototype.isHidden = function (b) {
		return !!b.hidden || b.classList.contains('is-cond-hidden') || b.getAttribute('data-cond-hidden') === '1';
	};

	Bar.prototype.titleOf = function (b) {
		var t = b.querySelector('.wpf4__legendText');
		return t ? t.textContent.trim() : (b.dataset.key || '');
	};

	Bar.prototype.build = function () {
		var self = this;
		this.row = el('div', 'wpf4-bar');
		this.row.setAttribute('role', 'toolbar');
		this.row.setAttribute('aria-label', this.i18n.all || 'All filters');
		this.allBtn = el('button', 'wpf4-bar__all', SLIDERS + '<span>' + esc(this.i18n.all || 'All filters') + '</span><span class="wpf4-bar__n" data-n hidden></span>');
		this.allBtn.type = 'button';
		this.allBtn.setAttribute('aria-haspopup', 'dialog');
		this.row.appendChild(this.allBtn);
		this.pills = {};
		this.blocks().forEach(function (b) {
			var key = b.dataset.key;
			var p = el('button', 'wpf4-bar__pill', '<span class="wpf4-bar__label">' + esc(self.titleOf(b)) + '</span><span class="wpf4-bar__n" data-n hidden></span>' + CHEVRON);
			p.type = 'button';
			p.dataset.key = key;
			p.setAttribute('aria-expanded', 'false');
			p.setAttribute('aria-haspopup', 'true');
			self.pills[key] = p;
			self.row.appendChild(p);
		});
		// The dropdown panel and the pop-up live inside the form: the filter reads its controls from the form.
		this.pop = el('div', 'wpf4-bar__pop');
		this.pop.hidden = true;
		this.pop.setAttribute('role', 'dialog');
		this.scrim = el('div', 'wpf4-bar__scrim');
		this.scrim.hidden = true;
		this.modal = el('div', 'wpf4-bar__modal');
		this.modal.hidden = true;
		this.modal.setAttribute('role', 'dialog');
		this.modal.setAttribute('aria-modal', 'true');
		this.modal.setAttribute('aria-label', this.i18n.all || 'All filters');
		this.modal.innerHTML = '<div class="wpf4-bar__mhead"><h2 class="wpf4-bar__mtitle">' + esc(this.i18n.all || 'All filters') + '</h2><button type="button" class="wpf4-bar__close" data-bar-close aria-label="' + esc(this.i18n.close || 'Close') + '">' + CLOSE + '</button></div><div class="wpf4-bar__mbody" data-bar-mbody></div><div class="wpf4-bar__mfoot"><button type="button" class="wpf4-bar__clear" data-bar-clear>' + esc(this.i18n.clear || 'Clear all') + '</button><button type="button" class="wpf4-bar__show" data-bar-close data-bar-show>' + esc(this.i18n.show || 'Show results') + '</button></div>';
		this.mbody = this.modal.querySelector('[data-bar-mbody]');
		this.form.insertBefore(this.row, this.form.firstChild);
		this.form.appendChild(this.pop);
		this.form.appendChild(this.scrim);
		this.form.appendChild(this.modal);
	};

	Bar.prototype.active = function () {
		return this.ok && this.mq.matches;
	};

	/** Turn the bar on or off with the screen width. */
	Bar.prototype.apply = function () {
		var on = this.active();
		this.closeAll();
		this.root.classList.toggle('wpf4--bar-on', on);
		this.row.hidden = !on;
		this.refresh();
	};

	/** Picks per block from the chips bar (every block kind writes its chips there), and pill visibility. */
	Bar.prototype.refresh = function () {
		if (!this.ok) { return; }
		var counts = {}, total = 0, self = this;
		this.root.querySelectorAll('[data-wpf4-chip][data-key]').forEach(function (c) {
			counts[c.dataset.key] = (counts[c.dataset.key] || 0) + 1;
			total++;
		});
		this.blocks().forEach(function (b) {
			var p = self.pills[b.dataset.key];
			if (!p) { return; }
			var n = counts[b.dataset.key] || 0;
			var badge = p.querySelector('[data-n]');
			badge.textContent = n ? String(n) : '';
			badge.hidden = !n;
			p.classList.toggle('is-picked', !!n);
			// A block the conditions hid has no button either.
			p.hidden = self.isHidden(b);
		});
		var allN = this.allBtn.querySelector('[data-n]');
		allN.textContent = total ? String(total) : '';
		allN.hidden = !total;
		this.allBtn.classList.toggle('is-picked', !!total);
		var show = this.modal.querySelector('[data-bar-show]');
		if (show && this.filter.count !== null && this.filter.count !== undefined && this.i18n.showN) {
			show.textContent = this.i18n.showN.replace('%s', String(this.filter.count));
		}
	};

	/** Move a block out of its place, remembering where it was. */
	Bar.prototype.take = function (b) {
		if (this.homes[b.dataset.key]) { return; }
		var mark = document.createComment('wpf4-bar-home');
		b.parentNode.insertBefore(mark, b);
		this.homes[b.dataset.key] = mark;
		var body = b.querySelector('.wpf4__body');
		if (body && body.hidden) { body.hidden = false; b.dataset.barWasFolded = '1'; }
	};

	Bar.prototype.giveBack = function () {
		var self = this;
		Object.keys(this.homes).forEach(function (k) {
			var mark = self.homes[k];
			var b = self.root.querySelector('[data-wpf4-block][data-key="' + k.replace(/"/g, '') + '"]');
			if (b && mark.parentNode) {
				mark.parentNode.insertBefore(b, mark);
				if (b.dataset.barWasFolded) {
					var body = b.querySelector('.wpf4__body');
					if (body) { body.hidden = true; }
					delete b.dataset.barWasFolded;
				}
			}
			if (mark.parentNode) { mark.parentNode.removeChild(mark); }
		});
		this.homes = {};
	};

	Bar.prototype.openPill = function (key) {
		if (this.openKey === key) { this.closeAll(); return; }
		this.closeAll();
		var b = this.root.querySelector('[data-wpf4-block][data-key="' + key.replace(/"/g, '') + '"]');
		var p = this.pills[key];
		if (!b || !p) { return; }
		this.take(b);
		this.pop.appendChild(b);
		this.pop.hidden = false;
		this.pop.setAttribute('aria-label', this.titleOf(b));
		this.place(p);
		p.setAttribute('aria-expanded', 'true');
		p.classList.add('is-open');
		this.openKey = key;
		if (this.filter.refold) { this.filter.refold(); }
		var first = this.pop.querySelector('input:not([disabled]):not([type="hidden"]), select, button');
		if (first) { first.focus({ preventScroll: true }); }
	};

	/** The panel sits under its button, kept inside the screen. */
	Bar.prototype.place = function (p) {
		var fr = this.form.getBoundingClientRect();
		var pr = p.getBoundingClientRect();
		this.pop.style.top = Math.round(pr.bottom - fr.top + 8) + 'px';
		this.pop.style.left = '0px';
		var w = this.pop.offsetWidth;
		var left = pr.left - fr.left;
		var maxLeft = Math.max(0, Math.min(fr.width, document.documentElement.clientWidth - fr.left - 16) - w);
		this.pop.style.left = Math.round(Math.max(0, Math.min(left, maxLeft))) + 'px';
	};

	Bar.prototype.openAll = function () {
		var self = this;
		this.closeAll();
		this.blocks().forEach(function (b) {
			if (self.isHidden(b)) { return; }
			self.take(b);
			self.mbody.appendChild(b);
		});
		this.scrim.hidden = false;
		this.modal.hidden = false;
		document.documentElement.classList.add('wpf4-bar-lock');
		this.allBtn.setAttribute('aria-expanded', 'true');
		this.lastFocus = document.activeElement;
		this.modalOpen = true;
		this.refresh();
		if (this.filter.refold) { this.filter.refold(); }
		var close = this.modal.querySelector('.wpf4-bar__close');
		if (close) { close.focus({ preventScroll: true }); }
	};

	Bar.prototype.closeAll = function () {
		var self = this;
		if (!this.ok) { return; }
		Object.keys(this.pills || {}).forEach(function (k) {
			self.pills[k].setAttribute('aria-expanded', 'false');
			self.pills[k].classList.remove('is-open');
		});
		this.openKey = null;
		this.pop.hidden = true;
		var wasModal = this.modalOpen;
		this.modal.hidden = true;
		this.scrim.hidden = true;
		this.modalOpen = false;
		document.documentElement.classList.remove('wpf4-bar-lock');
		this.allBtn.setAttribute('aria-expanded', 'false');
		this.giveBack();
		if (wasModal && this.lastFocus && this.lastFocus.focus) { this.lastFocus.focus({ preventScroll: true }); }
	};

	Bar.prototype.bind = function () {
		var self = this;
		this.row.addEventListener('click', function (e) {
			var p = e.target.closest('.wpf4-bar__pill');
			if (p) { self.openPill(p.dataset.key); return; }
			if (e.target.closest('.wpf4-bar__all')) { self.openAll(); }
		});
		this.modal.addEventListener('click', function (e) {
			if (e.target.closest('[data-bar-clear]')) {
				if (self.filter.clearAll) { self.filter.clearAll(); }
				return;
			}
			if (e.target.closest('[data-bar-close]')) { self.closeAll(); }
		});
		this.scrim.addEventListener('click', function () { self.closeAll(); });
		document.addEventListener('click', function (e) {
			if (!self.openKey) { return; }
			if (self.pop.contains(e.target) || e.target.closest('.wpf4-bar__pill')) { return; }
			self.closeAll();
		});
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape' || (!self.openKey && !self.modalOpen)) { return; }
			var back = self.openKey ? self.pills[self.openKey] : null;
			self.closeAll();
			if (back) { back.focus(); }
		});
		window.addEventListener('resize', function () {
			if (self.openKey && self.pills[self.openKey]) { self.place(self.pills[self.openKey]); }
		});
		var onMq = function () { self.apply(); };
		if (this.mq.addEventListener) { this.mq.addEventListener('change', onMq); } else if (this.mq.addListener) { this.mq.addListener(onMq); }
		var chips = this.root.querySelector('[data-wpf4-chips]');
		if (chips && window.MutationObserver) {
			new MutationObserver(function () { self.refresh(); }).observe(chips, { childList: true, subtree: true });
		}
		document.addEventListener('wpf:results', function (e) {
			if (!e.detail || String(e.detail.filterId) === String(self.filter.id)) { self.refresh(); }
		});
		document.addEventListener('wpf4:conditions', function () { self.refresh(); });
		this.form.addEventListener('change', function () { setTimeout(function () { self.refresh(); }, 0); });
	};

	W.Bar = Bar;
})();
