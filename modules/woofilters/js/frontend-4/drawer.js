/*
 * Product Filter by WBW - storefront engine 4: the phone drawer.
 * Sticky "Filters (N)" pill, bottom sheet (role=dialog), chips, "Show N results", remembered last filters,
 * focus trap, Escape, focus return. The form itself moves into the sheet while the phone layout is active.
 */
(function () {
	'use strict';
	var W = window.WPF4 = window.WPF4 || {};

	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

	function Drawer(filter) {
		this.filter = filter;
		this.root = filter.root;
		this.cfg = filter.config.drawer || {};
		this.btn = this.root.querySelector('[data-wpf4-drawer-open]');
		this.sheet = this.root.querySelector('[data-wpf4-drawer]');
		this.scrim = this.root.querySelector('[data-wpf4-scrim]');
		this.ok = !!(this.btn && this.sheet && this.scrim && filter.form);
		if (!this.ok) { return; }
		this.body = this.sheet.querySelector('[data-wpf4-drawer-body]');
		this.show = this.sheet.querySelector('[data-wpf4-drawer-show]');
		this.live = this.sheet.querySelector('[data-wpf4-drawer-live]');
		this.remember = this.sheet.querySelector('[data-wpf4-remember]');
		this.n = this.btn.querySelector('[data-wpf4-drawer-n]');
		this.formHome = document.createComment('wpf4-form-home');
		this.open = false;
		this.active = false;
		this.lastFocus = null;
		var bp = parseInt(this.cfg.breakpoint, 10) || 768;
		this.mq = window.matchMedia('(max-width: ' + (bp - 1) + 'px)');
		this.bind();
		this.update();
		this.updateCount();
		this.placeButton();
	}

	/**
	 * Themes pin bars to the bottom on phones (Storefront's handheld bar, mini carts): sit above them.
	 */
	Drawer.prototype.placeButton = function () {
		if (!this.ok) { return; }
		var offset = 16;
		var vh = window.innerHeight;
		var vw = window.innerWidth;
		var self = this;
		var wasHidden = this.btn.hidden;
		if (!document.elementsFromPoint) { return; }
		this.btn.hidden = true;
		[0.2, 0.5, 0.8].forEach(function (f) {
			var stack = document.elementsFromPoint(Math.round(vw * f), vh - 4);
			for (var i = 0; i < stack.length; i++) {
				var el = stack[i];
				if (el === document.documentElement || el === document.body || self.root.contains(el)) { continue; }
				var fixed = el;
				while (fixed && fixed !== document.body && window.getComputedStyle(fixed).position !== 'fixed') { fixed = fixed.parentElement; }
				if (!fixed || fixed === document.body) { continue; }
				var r = fixed.getBoundingClientRect();
				if (r.height > 0 && r.height < 200 && Math.abs(r.bottom - vh) < 2) {
					offset = Math.max(offset, Math.round(r.height) + 12);
				}
			}
		});
		this.btn.hidden = wasHidden;
		// Two filters with a phone drawer on one page: stack their buttons instead of putting one on top of the other.
		var others = document.querySelectorAll('[data-wpf4-drawer-open]');
		for (var k = 0; k < others.length && others[k] !== this.btn; k++) {
			if (!others[k].hidden && window.getComputedStyle(others[k]).display !== 'none') {
				offset += (others[k].offsetHeight || 48) + 8;
			}
		}
		this.btn.style.bottom = offset + 'px';
		this.sheet.style.paddingBottom = Math.max(20, offset - 16 + 20) + 'px';
	};

	Drawer.prototype.isPhone = function () {
		return this.cfg.always || this.mq.matches;
	};

	/**
	 * Enter or leave the phone layout: the form lives in the sheet while the drawer is active.
	 */
	Drawer.prototype.update = function () {
		if (!this.ok) { return; }
		var want = this.isPhone();
		if (want === this.active) { return; }
		this.active = want;
		var form = this.filter.form;
		if (want) {
			form.parentNode.insertBefore(this.formHome, form);
			this.body.appendChild(form);
			this.root.classList.add('wpf4--drawer-moved', 'wpf4--phone');
			// "Show picked things as chips at the top" off: no chips in the sheet.
			this.root.classList.toggle('wpf4--drawer-nochips', this.cfg.chips === false);
			this.btn.hidden = false;
			this.sheet.hidden = true;
			this.scrim.hidden = true;
			this.sheet.setAttribute('aria-hidden', 'true');
			this.renderRemembered();
		} else {
			if (this.open) { this.close(true); }
			if (this.formHome.parentNode) {
				this.formHome.parentNode.insertBefore(form, this.formHome);
				this.formHome.parentNode.removeChild(this.formHome);
			}
			this.root.classList.remove('wpf4--drawer-moved');
			if (!this.mq.matches) { this.root.classList.remove('wpf4--phone'); }
			this.btn.hidden = true;
		}
		this.filter.syncMobileOptions();
	};

	Drawer.prototype.updateCount = function () {
		if (!this.ok) { return; }
		// "Show the count on the button" off: the button only says its words.
		var n = this.cfg.results === false ? 0 : this.filter.pickedCount();
		if (this.n) { this.n.textContent = n ? '(' + n + ')' : ''; }
		this.btn.setAttribute('aria-label', (this.cfg.text || W.t('filters')) + (n ? ' (' + n + ')' : ''));
	};

	Drawer.prototype.updateResults = function (count) {
		if (!this.ok) { return; }
		if (this.show && this.cfg.results !== false) {
			this.show.textContent = W.t(count === 1 ? 'showResult' : 'showResults', W.formatCount(count));
			this.show.hidden = false;
		} else if (this.show) {
			this.show.hidden = true;
		}
		if (this.live) { this.live.textContent = W.t('match', W.formatCount(count)); }
	};

	Drawer.prototype.renderRemembered = function () {
		if (!this.ok) { return; }
		if (!this.remember) { return; }
		var saved = this.filter.remembered();
		var current = this.filter.pickedCount();
		if (!saved || !saved.labels || !saved.labels.length || current > 0 || !this.filter.config.remember) {
			this.remember.hidden = true;
			return;
		}
		var text = this.remember.querySelector('[data-wpf4-remember-text]');
		var names = saved.labels.map(function (l) { return '<b>' + W.esc(l) + '</b>'; });
		var list = names.length > 1 ? names.slice(0, -1).join(', ') + ' ' + W.t('and') + ' ' + names[names.length - 1] : names[0];
		text.innerHTML = W.t('remembered', list) + ' <b>' + W.esc(W.t('useAgain')) + '</b>';
		this.remember.hidden = false;
	};

	Drawer.prototype.openSheet = function () {
		if (!this.ok) { return; }
		if (this.open) { return; }
		this.open = true;
		this.lastFocus = document.activeElement;
		this.sheet.hidden = false;
		this.scrim.hidden = false;
		this.sheet.removeAttribute('aria-hidden');
		this.btn.setAttribute('aria-expanded', 'true');
		document.body.classList.add('wpf4-locked');
		this.renderRemembered();
		this.updateResults(this.filter.count);
		var self = this;
		requestAnimationFrame(function () {
			self.sheet.classList.add('is-open');
			self.scrim.classList.add('is-open');
			var first = self.sheet.querySelector('.wpf4-drawer__close');
			if (first) { first.focus(); }
		});
		document.dispatchEvent(new CustomEvent('wpf:drawer', { detail: { open: true, filterId: this.filter.id } }));
	};

	Drawer.prototype.close = function (silent) {
		if (!this.ok) { return; }
		if (!this.open) { return; }
		this.open = false;
		this.sheet.classList.remove('is-open');
		this.scrim.classList.remove('is-open');
		this.btn.setAttribute('aria-expanded', 'false');
		document.body.classList.remove('wpf4-locked');
		var self = this;
		var done = function () {
			self.sheet.hidden = true;
			self.scrim.hidden = true;
			self.sheet.setAttribute('aria-hidden', 'true');
		};
		if (silent || W.reducedMotion()) { done(); } else { setTimeout(done, 370); }
		if (!silent && this.lastFocus && document.contains(this.lastFocus)) {
			this.lastFocus.focus();
		} else if (!silent) {
			this.btn.focus();
		}
		document.dispatchEvent(new CustomEvent('wpf:drawer', { detail: { open: false, filterId: this.filter.id } }));
	};

	Drawer.prototype.trap = function (e) {
		if (e.key === 'Escape') {
			e.preventDefault();
			this.close();
			return;
		}
		if (e.key !== 'Tab') { return; }
		var nodes = Array.prototype.filter.call(this.sheet.querySelectorAll(FOCUSABLE), function (n) {
			return n.offsetParent !== null || n === document.activeElement;
		});
		if (!nodes.length) { return; }
		var first = nodes[0], last = nodes[nodes.length - 1];
		if (e.shiftKey && (document.activeElement === first || !this.sheet.contains(document.activeElement))) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	};

	Drawer.prototype.bind = function () {
		var self = this;
		this.btn.addEventListener('click', function () { self.openSheet(); });
		this.scrim.addEventListener('click', function () { self.close(); });
		this.sheet.addEventListener('keydown', function (e) { self.trap(e); });
		var closeBtn = this.sheet.querySelector('[data-wpf4-drawer-close]');
		if (closeBtn) { closeBtn.addEventListener('click', function () { self.close(); }); }
		if (this.show) {
			this.show.addEventListener('click', function () {
				self.close();
				self.filter.scrollToProducts();
			});
		}
		if (this.remember) {
			this.remember.addEventListener('click', function () {
				var saved = self.filter.remembered();
				if (saved && saved.params) {
					self.filter.applyParams(saved.params);
					self.filter.request('remember');
					self.remember.hidden = true;
				}
			});
		}
		var onMq = function () { self.update(); self.placeButton(); };
		window.addEventListener('resize', function () { self.placeButton(); });
		window.addEventListener('load', function () { self.placeButton(); });
		if (this.mq.addEventListener) { this.mq.addEventListener('change', onMq); } else { this.mq.addListener(onMq); }
		document.addEventListener('wpf:results', function (e) {
			if (e.detail && e.detail.filterId === self.filter.id) {
				self.updateResults(e.detail.count);
				self.updateCount();
			}
		});
		document.addEventListener('wpf:change', function (e) {
			if (e.detail && e.detail.filterId === self.filter.id) { self.updateCount(); }
		});
	};

	W.Drawer = Drawer;
})();
