/*
 * Product Filter by WBW - storefront engine 4: the filter.
 * Vanilla JS: collects controls, builds canonical params, fetches JSON, replaces the product loop, updates counts,
 * chips, URL (pushState / popstate without reload), live region, focus, pagination modes, remembered filters.
 * Events on document: wpf:change, wpf:request, wpf:results, wpf:error.
 */
(function () {
	'use strict';
	var W = window.WPF4 = window.WPF4 || {};
	var cfg = window.wpf4Config || {};
	var I18N = cfg.i18n || {};
	var EXTRA = { and: 'and', inStock: 'In stock only', starsUp: '%d stars and up', noOptions: 'Nothing left to pick here.', showLess: 'Show less', clear: 'Clear', moreCount: '%1$s (%2$s)' };
	// Grids the engine knows besides the configured selectors: builders and block themes.
	var GRIDS = ['ul.products', '.wc-block-product-template', '.wc-block-grid__products', '.e-loop-container', '.elementor-loop-container', '.woocommerce-loop', '.products'];
	var GRID_MATCH = 'ul.products, .wc-block-product-template, .wc-block-grid__products, .e-loop-container, .elementor-loop-container, .woocommerce-loop, .wp-block-woocommerce-product-collection';
	var GRID_ITEMS = ':scope > .product, :scope > li.product, :scope > .e-loop-item, :scope > .wc-block-grid__product, :scope > .wc-block-product, :scope > li, :scope > .wc-block-product-template__item';
	var uidCounter = 0;

	W.t = function (key, a, b) {
		var s = I18N[key] || EXTRA[key] || key;
		return s.replace(/%1\$[sd]/g, a).replace(/%2\$[sd]/g, b).replace(/%[sd]/, a);
	};
	W.esc = function (s) {
		return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	};
	W.formatCount = function (n) {
		try { return Number(n).toLocaleString(document.documentElement.lang || undefined); } catch (e) { return String(n); }
	};
	W.reducedMotion = function () {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	};
	W.filters = [];
	W.get = function (id) {
		for (var i = 0; i < W.filters.length; i++) { if (String(W.filters[i].id) === String(id)) { return W.filters[i]; } }
		return null;
	};

	function debounce(fn, ms) {
		var t;
		return function () {
			var args = arguments, self = this;
			clearTimeout(t);
			t = setTimeout(function () { fn.apply(self, args); }, ms);
		};
	}

	function Filter(root) {
		this.root = root;
		this.id = String(root.dataset.filter || '');
		this.config = JSON.parse(root.dataset.config || '{}');
		if (this.config.i18n) {
			Object.keys(this.config.i18n).forEach(function (k) { if (!I18N[k]) { I18N[k] = this.config.i18n[k]; } }, this);
		}
		this.ctx = JSON.parse(root.dataset.ctx || '{}');
		this.params = JSON.parse(root.dataset.params || '{}');
		this.form = root.querySelector('[data-wpf4-form]');
		if (!this.form) { return; }
		this.live = root.querySelector('[data-wpf4-live]');
		this.chips = root.querySelector('[data-wpf4-chips]');
		this.clearBtns = root.querySelectorAll('[data-wpf4-clear]');
		this.sliders = [];
		this.page = parseInt(this.params.wpf_page, 10) || 1;
		this.count = null;
		this.seq = 0;
		this.loading = false;
		this.container = null;
		this.init();
	}

	Filter.prototype.init = function () {
		var self = this;
		if (this.config.autoUpdate) { this.root.classList.add('wpf4--auto'); }
		this.root.querySelectorAll('[data-wpf4-slider]').forEach(function (el) { self.sliders.push(new W.Slider(el, self)); });
		if (W.Swatches) { this.swatches = new W.Swatches(this); }
		this.container = this.findContainer();
		this.setupAddon();
		this.fixLayout();
		this.bindForm();
		this.bindChips();
		this.bindFolds();
		this.bindSearchLists();
		this.bindStars();
		this.bindPagination();
		this.bindHistory();
		this.setupMobileOverrides();
		if (W.Drawer) { this.drawer = new W.Drawer(this); }
		if (W.Bar) { this.bar = new W.Bar(this); }
		if (W.Finder) { this.finder = new W.Finder(this); }
		this.setupShowMore();
		this.syncStars();
		this.renderChips();
		this.bindRememberDesk();
		this.count = this.readCountFromPage();
		if (this.count !== null) { this.announce(this.count, true); }
		// Two copies of the same filter on a page (sidebar + above the loop) share one set of paging controls.
		if (!W.pagingOwner && this.container) {
			W.pagingOwner = this;
			if (this.config.pagination === 'infinite') { this.setupInfinite(); }
			if (this.config.pagination === 'load_more') { this.setupLoadMore(); }
		}
		this.liftChecked();
		W.filters.push(this);
		document.dispatchEvent(new CustomEvent('wpf:ready', { detail: { filterId: this.id, filter: this } }));
	};

	/* ---------------------------------------------------------------- desktop layout */

	/**
	 * Automatic "sidebar" placement on a theme without a real sidebar (or with a very narrow one): a row with the
	 * filter in a fixed column (side_width, 220 to 400 px, default 280) and the products in the rest, on screens of
	 * 900px and up. The row, the side and the main column get their sizes inline as well as from the stylesheet, so no
	 * theme rule can let the filter column grow. The theme's own sidebar is kept when it is at least 220px wide.
	 *
	 * Main column: an ancestor of the grid that sits right beside the placement (the theme's content column) when it
	 * is at least 60% as wide as the row that replaces it and at least 300px wide; else the nearest ancestor of the
	 * grid (the grid itself first) that does not hold the placement and passes the same test. When nothing passes,
	 * the grid's own parent is used. A placement that landed inside the grid (or in a grid item) is moved before the grid first.
	 * When the row is too narrow for a 300px product column the two stack, the filter keeping its column width.
	 */
	Filter.prototype.fixLayout = function () {
		var self = this;
		var place = this.root.closest('.wpfAutoPlace-sidebar');
		if (!place || !this.container || this.root.closest('.wpf4-layout')) { return; }
		var sideW = Math.max(220, Math.min(400, parseInt(this.config.sideWidth, 10) || 280));
		var MIN_MAIN = 300;
		var GAP = 32;
		var row = null, mainCol = null;
		var contentWidth = function (el) {
			var cs = window.getComputedStyle(el);
			return el.clientWidth - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.paddingRight) || 0);
		};
		var setMode = function (mode) {
			row.classList.toggle('wpf4-layout--stack', mode === 'stack');
			row.classList.toggle('wpf4-layout--block', mode === 'block');
			if (mode === 'side') {
				row.style.cssText = 'display:flex;align-items:flex-start;gap:' + GAP + 'px;width:100%;max-width:100%;min-width:0;clear:both;box-sizing:border-box;--wpf4-side:' + sideW + 'px;';
				place.style.cssText = 'flex:0 0 ' + sideW + 'px;width:' + sideW + 'px;max-width:' + sideW + 'px;min-width:' + Math.min(240, sideW) + 'px;float:none;margin:0;padding:0;position:static;box-sizing:border-box;';
				mainCol.style.cssText = 'flex:1 1 0;width:auto;min-width:0;max-width:none;float:none;margin:0;padding:0;position:static;box-sizing:border-box;';
			} else {
				row.style.cssText = 'display:block;width:100%;max-width:100%;min-width:0;clear:both;box-sizing:border-box;--wpf4-side:' + sideW + 'px;';
				place.style.cssText = 'width:auto;max-width:' + (mode === 'stack' ? sideW + 'px' : 'none') + ';float:none;margin:0 0 24px;padding:0;position:static;box-sizing:border-box;';
				mainCol.style.cssText = 'width:auto;min-width:0;max-width:none;float:none;margin:0;padding:0;position:static;box-sizing:border-box;';
			}
			row.dataset.mode = mode;
		};
		var mode = function () {
			if (window.innerWidth < 900) { return 'block'; }
			var w = row.parentElement ? contentWidth(row.parentElement) : row.clientWidth;
			return w > 0 && w < sideW + GAP + MIN_MAIN ? 'stack' : 'side';
		};
		var markInner = function (el) {
			el.classList.add('wpf4-layout__inner');
			var cs = window.getComputedStyle(el);
			var css = 'float:none;width:auto;max-width:none;min-width:0;box-sizing:border-box;';
			if (cs.cssFloat !== 'none' || cs.position === 'absolute') { css += 'margin-left:0;margin-right:0;position:static;'; }
			el.style.cssText += ';' + css;
		};
		this.markInner = markInner;
		var apply = function () {
			if (row) { setMode(mode()); return; }
			var grid = self.container;
			if (!grid || !document.contains(grid)) { grid = self.container = self.findContainer(); }
			if (!grid || !grid.parentElement) { return; }
			// 0. The placement landed inside the grid or in a grid item: move it before the grid, on every screen size.
			if (grid.contains(place)) { grid.parentElement.insertBefore(place, grid); }
			if (window.innerWidth < 900) { return; }
			// A real sidebar from the theme (widget area beside the content): keep it.
			var col = place.closest('#secondary, .widget-area, aside, .wbw-shop__side');
			if (col && col !== place && !col.contains(grid) && col.getBoundingClientRect().width >= 220) { return; }
			var pw = place.getBoundingClientRect().width;
			if (pw >= 220 && pw <= 480 && place.parentElement && place.parentElement.getBoundingClientRect().width > pw + 400 && !place.parentElement.contains(grid)) { return; }
			var main = null, cur = grid;
			var fits = function (el) {
				var w = el.getBoundingClientRect().width;
				return w >= MIN_MAIN && w >= 0.6 * contentWidth(el.parentElement);
			};
			// 1a. An ancestor of the grid that sits right beside the placement (same parent) is the theme's content
			//     column: split that parent, so a floated column never stays floated next to the row.
			if (place.parentElement) {
				var sib = grid;
				while (sib && sib.parentElement && sib.parentElement !== place.parentElement) { sib = sib.parentElement; }
				if (sib && sib !== place && sib.parentElement === place.parentElement && !sib.contains(place) && fits(sib)) { main = sib; }
			}
			// 1b. Otherwise the nearest ancestor (the grid itself first) that does not hold the placement and is the real content column.
			while (!main && cur && cur !== document.body && cur !== document.documentElement && cur.parentElement) {
				if (!cur.contains(place) && !place.contains(cur) && fits(cur)) { main = cur; break; }
				cur = cur.parentElement;
			}
			// 2. Nothing passed: the grid's own parent (never the body).
			if (!main) { main = grid.parentElement !== document.body ? grid.parentElement : grid; }
			if (main.contains(place)) {
				if (!main.parentElement || main.parentElement === document.documentElement) { return; }
				main.parentElement.insertBefore(place, main);
			}
			if (!main.parentElement) { return; }
			row = document.createElement('div');
			row.className = 'wpf4-layout';
			mainCol = document.createElement('div');
			mainCol.className = 'wpf4-layout__main';
			main.parentElement.insertBefore(row, main);
			place.classList.add('wpf4-layout__side');
			markInner(main);
			mainCol.appendChild(main);
			row.appendChild(place);
			row.appendChild(mainCol);
			self.layoutRow = row;
			self.layoutMain = mainCol;
			setMode(mode());
			self.placeMore();
			self.refold();
			document.dispatchEvent(new CustomEvent('wpf:layout', { detail: { filterId: self.id, row: row, side: place, main: mainCol, mode: row.dataset.mode } }));
		};
		apply();
		var t;
		window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(apply, 150); });
	};

	/* ---------------------------------------------------------------- products container */

	/**
	 * The element is a product grid: a known grid class, or something holding product cards; never part of a menu,
	 * header, footer or the filter itself (a menu item with class "products" must not be taken for the loop).
	 */
	Filter.prototype.isGrid = function (el) {
		if (!el || this.root.contains(el) || el.closest('[data-wpf4], .wpf4-drawer, .wpf4-finder, nav, header, footer, .menu, .site-header, .site-footer')) { return false; }
		if (el.matches(GRID_MATCH)) { return true; }
		try { return !!el.querySelector(GRID_ITEMS); } catch (e) { return el.children.length > 0; }
	};

	Filter.prototype.findContainer = function () {
		var self = this;
		var list = this.config.selector ? [this.config.selector] : [];
		(cfg.selectors || []).forEach(function (s) { if (list.indexOf(s) < 0) { list.push(s); } });
		GRIDS.forEach(function (s) { if (list.indexOf(s) < 0) { list.push(s); } });
		for (var i = 0; i < list.length; i++) {
			var nodes;
			try { nodes = document.querySelectorAll(list[i]); } catch (e) { continue; }
			for (var j = 0; j < nodes.length; j++) {
				if (self.isGrid(nodes[j])) { return nodes[j]; }
			}
		}
		return null;
	};

	Filter.prototype.readCountFromPage = function () {
		var rc = document.querySelector('.woocommerce-result-count');
		if (rc) {
			var nums = rc.textContent.replace(/[,.  ](?=\d{3})/g, '').match(/\d+/g);
			if (nums && nums.length) { return parseInt(nums[nums.length - 1], 10); }
		}
		if (this.container) { return this.container.querySelectorAll('.product').length; }
		return null;
	};

	Filter.prototype.scrollToProducts = function () {
		var target = this.container || document.querySelector('.woocommerce-products-header, main');
		if (target && target.getBoundingClientRect().top < 0) {
			target.scrollIntoView({ behavior: W.reducedMotion() ? 'auto' : 'smooth', block: 'start' });
		}
	};

	/* ---------------------------------------------------------------- page-builder grids */

	/**
	 * A page-builder product grid on the page (Essential Addons, Ultimate Addons, Element Pack, JetWooBuilder, Divi
	 * Plus): remember its wrapper. Results then come from the filtered page (the widget renders them), and its
	 * load-more / pagination AJAX calls carry the filter.
	 */
	Filter.prototype.setupAddon = function () {
		var addon = cfg.addon || {};
		this.addonWrapper = null;
		var self = this;
		(addon.wrappers || []).forEach(function (sel) {
			if (self.addonWrapper) { return; }
			var nodes;
			try { nodes = document.querySelectorAll(sel); } catch (e) { return; }
			for (var i = 0; i < nodes.length; i++) {
				if (!self.root.contains(nodes[i]) && !nodes[i].closest('[data-wpf4]')) { self.addonWrapper = nodes[i]; self.addonSelector = sel; break; }
			}
		});
		if (this.addonWrapper && !this.container) { this.container = this.addonWrapper; }
		if (this.addonWrapper) { W.installAddonHook(); }
	};

	/** Replace every add-on grid wrapper, and the WooCommerce loop(s), with the same elements from the filtered page HTML. */
	Filter.prototype.swapAddon = function (html) {
		if (!this.addonSelector) { return; }
		var doc;
		try { doc = new DOMParser().parseFromString(html, 'text/html'); } catch (e) { return; }
		var self = this;
		var sels = ((cfg.addon && cfg.addon.wrappers) || []).slice();
		if (sels.indexOf(this.addonSelector) < 0) { sels.unshift(this.addonSelector); }
		// The ordinary product loop(s) on the page ([products] shortcode, theme loop) come from the same page.
		(this.config.selector ? [this.config.selector] : []).concat(cfg.selectors || [], GRIDS).forEach(function (s) { if (sels.indexOf(s) < 0) { sels.push(s); } });
		var done = [];
		sels.forEach(function (sel) {
			var mine, theirs;
			try { mine = document.querySelectorAll(sel); theirs = doc.querySelectorAll(sel); } catch (e) { return; }
			for (var i = 0; i < mine.length; i++) {
				var el = mine[i];
				if (!theirs[i] || self.root.contains(el) || el.closest('[data-wpf4]') || done.some(function (d) { return d === el || d.contains(el) || el.contains(d); })) { continue; }
				var fresh = document.importNode(theirs[i], true);
				el.replaceWith(fresh);
				done.push(fresh);
				if (el === self.addonWrapper) { self.addonWrapper = fresh; }
				if (el === self.container) { self.container = fresh; }
			}
		});
		var rc = document.querySelectorAll('.woocommerce-result-count'), rc2 = doc.querySelectorAll('.woocommerce-result-count');
		rc.forEach(function (n, i) { if (rc2[i] && !n.closest('[data-wpf4]')) { n.replaceWith(document.importNode(rc2[i], true)); } });
		var pg = document.querySelectorAll('.woocommerce-pagination'), pg2 = doc.querySelectorAll('.woocommerce-pagination');
		pg.forEach(function (n, i) { if (!n.closest('[data-wpf4]')) { if (pg2[i]) { n.replaceWith(document.importNode(pg2[i], true)); } else { n.remove(); } } });
		// Widgets with their own scripts (EA masonry, UAE grid) re-init on this event; jQuery widgets listen too.
		document.dispatchEvent(new CustomEvent('wpf:addon-swapped', { detail: { filterId: this.id, selector: this.addonSelector } }));
		if (window.jQuery) { window.jQuery(document.body).trigger('post-load'); }
		if (window.elementorFrontend && window.elementorFrontend.hooks && this.addonWrapper) {
			var widget = this.addonWrapper.closest('.elementor-widget');
			if (widget && window.jQuery) { try { window.elementorFrontend.elementsHandler.runReadyTrigger(window.jQuery(widget)); } catch (e) { /* older Elementor */ } }
		}
	};

	/** Current filter state as a query string (what the filtered page URL carries). */
	Filter.prototype.queryString = function () {
		// What is applied (not what is ticked but not yet applied when auto update is off).
		var params = this.appliedParams || this.params || {};
		try { return new URL(this.buildUrl(params)).search.replace(/^\?/, ''); } catch (e) { return ''; }
	};

	/**
	 * Once per page: add-on grid AJAX calls (load more, pagination) get the filter appended, so the next page is
	 * filtered server side (WooBeWoo_PF_Frontend4::readAddonParams).
	 */
	W.installAddonHook = function () {
		if (W.addonHooked) { return; }
		W.addonHooked = true;
		var actions = (cfg.addon && cfg.addon.actions) || [];
		if (!actions.length) { return; }
		var current = function () {
			var f = W.filters.filter(function (x) { return x.addonWrapper; })[0] || W.filters[0];
			return f ? { qs: f.queryString(), id: f.id } : null;
		};
		var hasAction = function (str) {
			var m = /(?:^|&)action=([^&]+)/.exec(str || '');
			return m && actions.indexOf(decodeURIComponent(m[1])) >= 0;
		};
		var send = XMLHttpRequest.prototype.send;
		XMLHttpRequest.prototype.send = function (body) {
			try {
				var c = current();
				if (c && c.qs) {
					if (typeof body === 'string' && hasAction(body) && body.indexOf('with_wpf_filter=') < 0) {
						body += '&with_wpf_filter=' + encodeURIComponent(c.qs) + '&wpf4_filter=' + encodeURIComponent(c.id);
					} else if (body instanceof FormData && actions.indexOf(body.get('action')) >= 0 && !body.has('with_wpf_filter')) {
						body.append('with_wpf_filter', c.qs);
						body.append('wpf4_filter', c.id);
					}
				}
			} catch (e) { /* never break the host request */ }
			return send.call(this, body);
		};
		if (window.fetch) {
			var origFetch = window.fetch;
			window.fetch = function (input, init) {
				try {
					var c = current();
					if (c && c.qs && init && init.body) {
						if (typeof init.body === 'string' && hasAction(init.body) && init.body.indexOf('with_wpf_filter=') < 0) {
							init.body += '&with_wpf_filter=' + encodeURIComponent(c.qs) + '&wpf4_filter=' + encodeURIComponent(c.id);
						} else if (init.body instanceof FormData && actions.indexOf(init.body.get('action')) >= 0 && !init.body.has('with_wpf_filter')) {
							init.body.append('with_wpf_filter', c.qs);
							init.body.append('wpf4_filter', c.id);
						}
					}
				} catch (e) { /* never break the host request */ }
				return origFetch.call(this, input, init);
			};
		}
	};

	/** "Move picked choices to the top": picked options first in each list, in their original order among themselves. */
	Filter.prototype.liftChecked = function () {
		if (!this.config.checkedTop) { return; }
		this.root.querySelectorAll('[data-wpf4-block]').forEach(function (block) {
			var lists = block.querySelectorAll('.wpf4__list, .wpf4__options--chips, .wpf4__options--squares, .wpf4__options--swatches');
			lists.forEach(function (list) {
				var opts = [].slice.call(list.children).filter(function (el) { return el.classList && el.classList.contains('wpf4__opt'); });
				var checked = opts.filter(function (el) { return el.classList.contains('is-checked') && !el.classList.contains('wpf4__opt--child'); });
				if (!checked.length) { return; }
				checked.reverse().forEach(function (el) { list.insertBefore(el, list.firstChild); });
			});
		});
	};

	/* ---------------------------------------------------------------- collecting values */

	Filter.prototype.controls = function () {
		return Array.prototype.filter.call(this.form.querySelectorAll('[data-wpf4-control]'), function (c) {
			return !c.disabled;
		});
	};

	/**
	 * Canonical params from the controls (wpf_<key>=a,b, wpf_price=min-max, ...). Page is kept separately.
	 */
	Filter.prototype.collect = function () {
		var params = {};
		var ranges = {};
		this.controls().forEach(function (c) {
			var name = c.name;
			if (!name) { return; }
			if (c.dataset.range === '1') {
				var m = name.match(/^wpf_(.+)_(min|max)$/);
				if (!m) { return; }
				ranges[m[1]] = ranges[m[1]] || {};
				ranges[m[1]][m[2]] = c.value;
				return;
			}
			if (c.type === 'checkbox' || c.type === 'radio') {
				if (!c.checked || c.value === '') { return; }
				params[name] = params[name] ? params[name] + ',' + c.value : c.value;
				return;
			}
			if (c.tagName === 'SELECT' || c.type === 'search' || c.type === 'text' || c.type === 'number') {
				if (c.value !== '') { params[name] = c.value; }
			}
		});
		var self = this;
		Object.keys(ranges).forEach(function (key) {
			var slider = self.sliderFor(key);
			var r = ranges[key];
			if (slider) {
				if (slider.isDefault()) { return; }
				var v = slider.values();
				params['wpf_' + key] = (v[0] > slider.min ? v[0] : '') + '-' + (v[1] < slider.max ? v[1] : '');
			} else if (r.min !== '' || r.max !== '') {
				params['wpf_' + key] = (r.min || '') + '-' + (r.max || '');
			}
		});
		if (params.wpf_sort === '' || params.wpf_sort === 'default') { delete params.wpf_sort; }
		if (this.page > 1) { params.wpf_page = String(this.page); }
		return params;
	};

	Filter.prototype.sliderFor = function (key) {
		for (var i = 0; i < this.sliders.length; i++) { if (this.sliders[i].key === key && !this.sliders[i].rMin.disabled) { return this.sliders[i]; } }
		return null;
	};

	Filter.prototype.pickedCount = function () {
		var p = this.collect();
		var n = 0;
		Object.keys(p).forEach(function (k) {
			if (k === 'wpf_page' || k === 'wpf_sort') { return; }
			n += k === 'wpf_price' || /^wpf_(q\d*|cf_.+)$/.test(k) ? 1 : p[k].split(',').length;
		});
		return n;
	};

	Filter.prototype.hasFilters = function () {
		return this.pickedCount() > 0;
	};

	/**
	 * Set the controls from canonical params (popstate, chips, remembered filters, finder).
	 */
	Filter.prototype.applyParams = function (params) {
		params = params || {};
		var self = this;
		this.controls().forEach(function (c) {
			var name = c.name;
			if (!name || c.dataset.range === '1') { return; }
			var v = params[name];
			if (c.type === 'checkbox' || c.type === 'radio') {
				var values = v ? String(v).split(',') : [];
				var on = c.value === '' ? !values.length : values.indexOf(c.value) >= 0;
				if (c.checked !== on) {
					c.checked = on;
					if (c.getAttribute('role') === 'switch') { c.setAttribute('aria-checked', on ? 'true' : 'false'); }
					var label = c.closest('.wpf4__opt');
					if (label) { label.classList.toggle('is-checked', on); }
				}
			} else if (c.tagName === 'SELECT' || c.type === 'search' || c.type === 'text') {
				c.value = v === undefined ? '' : v;
			}
		});
		this.sliders.forEach(function (s) {
			var v = params['wpf_' + s.key];
			if (!v) { s.set(s.min, s.max, true); return; }
			var parts = String(v).split('-');
			s.set(parts[0] === '' ? s.min : parseFloat(parts[0]), parts[1] === '' || parts[1] === undefined ? s.max : parseFloat(parts[1]), true);
		});
		this.page = parseInt(params.wpf_page, 10) || 1;
		this.updateDropdownLabels();
		this.syncStars();
		this.renderChips();
		this.syncMobileOptions(true);
		this.refold();
	};

	Filter.prototype.clearAll = function () {
		this.page = 1;
		this.applyParams({});
		this.request('clear');
	};

	/* ---------------------------------------------------------------- events */

	Filter.prototype.bindForm = function () {
		var self = this;
		var debounced = debounce(function () { self.request('change'); }, 150);
		this.form.addEventListener('change', function (e) {
			var c = e.target;
			if (!c || !c.matches || !c.matches('[data-wpf4-control]')) { return; }
			if (c.getAttribute('role') === 'switch') { c.setAttribute('aria-checked', c.checked ? 'true' : 'false'); }
			self.page = 1;
			self.lastChanged = c;
			self.mirror(c);
			self.updateDropdownLabels();
			self.syncStars();
			self.renderChips();
			document.dispatchEvent(new CustomEvent('wpf:change', { detail: { filterId: self.id, control: c, params: self.collect() } }));
			if (self.config.autoUpdate) { debounced(); }
		});
		var typing = debounce(function (c) { self.page = 1; self.lastChanged = c; if (self.config.autoUpdate) { self.request('change'); } }, 400);
		this.form.addEventListener('input', function (e) {
			var c = e.target;
			if (c && c.matches && c.matches('[data-wpf4-control][type="search"], [data-wpf4-control][type="text"]')) {
				self.renderChips();
				typing(c);
			}
		});
		this.form.addEventListener('submit', function (e) {
			e.preventDefault();
			self.page = 1;
			self.request('submit');
		});
		this.clearBtns.forEach(function (b) { b.addEventListener('click', function () { self.lastChanged = b; self.clearAll(); }); });
	};

	Filter.prototype.bindChips = function () {
		var self = this;
		this.root.addEventListener('click', function (e) {
			var chip = e.target.closest('[data-wpf4-chip]');
			if (!chip || !self.root.contains(chip)) { return; }
			self.removeValue(chip.dataset.key, chip.dataset.value);
			var next = chip.nextElementSibling || chip.previousElementSibling;
			self.lastChanged = next && next.matches('[data-wpf4-chip]') ? next : self.form.querySelector('[data-wpf4-control]');
			chip.remove();
			self.page = 1;
			self.renderChips();
			self.request('chip');
		});
	};

	Filter.prototype.removeValue = function (key, value) {
		var name = 'wpf_' + key;
		var slider = this.sliderFor(key);
		if (slider) { slider.set(slider.min, slider.max, true); return; }
		this.controls().forEach(function (c) {
			if (c.name !== name) { return; }
			if (c.type === 'checkbox' || c.type === 'radio') {
				if (c.value === value || c.type === 'radio') {
					c.checked = c.value === '' && c.type === 'radio';
					if (c.getAttribute('role') === 'switch') { c.setAttribute('aria-checked', 'false'); }
					var label = c.closest('.wpf4__opt');
					if (label) { label.classList.toggle('is-checked', c.checked); }
				}
			} else {
				c.value = '';
			}
		});
		this.updateDropdownLabels();
	};

	Filter.prototype.bindFolds = function () {
		var self = this;
		this.root.querySelectorAll('.wpf4__legend.wpf4__sr').forEach(function (l) { l.dataset.deskSr = '1'; });
		this.root.querySelectorAll('[data-wpf4-fold]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var open = btn.getAttribute('aria-expanded') === 'true';
				var block = btn.closest('.wpf4__block');
				self.setFold(block, btn, open);
				// Folded, one open at a time (phones): opening a block folds the others.
				if (!open && self.accordion) {
					self.root.querySelectorAll('[data-wpf4-block]').forEach(function (other) {
						var ob = other.querySelector('[data-wpf4-fold]');
						if (other !== block && ob) { self.setFold(other, ob, true); }
					});
				}
			});
		});
	};

	Filter.prototype.bindSearchLists = function () {
		this.root.querySelectorAll('[data-wpf4-list-search]').forEach(function (input) {
			var list = input.closest('.wpf4__options').querySelector('.wpf4__list');
			input.addEventListener('input', function () {
				var q = input.value.trim().toLowerCase();
				list.querySelectorAll('.wpf4__opt').forEach(function (opt) {
					var text = (opt.querySelector('.wpf4__optText') || opt).textContent.toLowerCase();
					opt.hidden = q !== '' && text.indexOf(q) < 0;
				});
			});
		});
	};

	Filter.prototype.updateDropdownLabels = function () {
		this.root.querySelectorAll('[data-wpf4-dd]').forEach(function (dd) {
			var label = dd.querySelector('[data-wpf4-dd-label]');
			var picked = Array.prototype.map.call(dd.querySelectorAll('input:checked'), function (i) { return i.dataset.label; });
			label.textContent = picked.length ? picked.join(', ') : (label.dataset.any || '');
		});
	};

	/* ---------------------------------------------------------------- phone overrides */

	Filter.prototype.setupMobileOverrides = function () {
		var self = this;
		var bp = (this.config.drawer && parseInt(this.config.drawer.breakpoint, 10)) || 768;
		this.mq = window.matchMedia('(max-width: ' + (bp - 1) + 'px)');
		var apply = function () {
			var phone = self.mq.matches || !!(self.config.drawer && self.config.drawer.always);
			self.root.classList.toggle('wpf4--phone', phone);
			self.syncMobileOptions();
			self.phoneBlocks(phone);
		};
		if (this.mq.addEventListener) { this.mq.addEventListener('change', apply); } else { this.mq.addListener(apply); }
		apply();
	};

	/**
	 * Phone layout of the blocks: per block "Open this block on phones" (data-phone-title closed / no) and, with
	 * "Different look on phones" on, "Blocks on phones: Folded, one open at a time" (accordion). Desktop states come back
	 * when the screen grows again.
	 */
	Filter.prototype.phoneBlocks = function (phone) {
		var self = this;
		var acc = phone && this.config.mobileOverrides && this.config.mobileOverrides.enabled && this.config.mobileOverrides.layout === 'accordion';
		this.accordion = !!acc;
		var opened = false;
		this.root.querySelectorAll('[data-wpf4-block]').forEach(function (block) {
			var btn = block.querySelector('[data-wpf4-fold]');
			var legend = block.querySelector('.wpf4__legend');
			if (block.dataset.deskCollapsed === undefined) { block.dataset.deskCollapsed = block.classList.contains('is-collapsed') ? '1' : '0'; }
			if (legend && block.dataset.phoneTitle === 'no') { legend.classList.toggle('wpf4__sr', phone || legend.dataset.deskSr === '1'); }
			if (!btn) { return; }
			var closed;
			if (!phone) {
				closed = block.dataset.deskCollapsed === '1';
			} else if (acc) {
				closed = opened || block.dataset.phoneTitle === 'closed';
				if (!closed) { opened = true; }
			} else {
				closed = block.dataset.phoneTitle === 'closed';
			}
			self.setFold(block, btn, closed);
		});
	};

	Filter.prototype.setFold = function (block, btn, closed) {
		var body = document.getElementById(btn.getAttribute('aria-controls'));
		btn.setAttribute('aria-expanded', closed ? 'false' : 'true');
		if (body) { body.hidden = closed; }
		block.classList.toggle('is-collapsed', closed);
	};

	/**
	 * Blocks with a phone display type carry two option sets; only the visible one is enabled and both stay in sync.
	 */
	Filter.prototype.syncMobileOptions = function (fromParams) {
		var phone = this.root.classList.contains('wpf4--phone');
		var self = this;
		this.root.querySelectorAll('[data-wpf4-block][data-display-mobile]').forEach(function (block) {
			var desk = block.querySelector('.wpf4__options--desktop');
			var mob = block.querySelector('.wpf4__options--mobile');
			if (!desk || !mob) { return; }
			var from = phone ? desk : mob, to = phone ? mob : desk;
			if (!fromParams) { self.copyValues(from, to); }
			from.querySelectorAll('input, select').forEach(function (c) { c.disabled = true; });
			to.querySelectorAll('input, select').forEach(function (c) { c.disabled = false; });
		});
	};

	Filter.prototype.copyValues = function (from, to) {
		from.querySelectorAll('input, select').forEach(function (src) {
			var sel = src.type === 'checkbox' || src.type === 'radio' ? 'input[name="' + src.name + '"][value="' + src.value.replace(/"/g, '\\"') + '"]' : '[name="' + src.name + '"]';
			var dst = to.querySelector(sel);
			if (!dst) { return; }
			if (src.type === 'checkbox' || src.type === 'radio') { dst.checked = src.checked; var l = dst.closest('.wpf4__opt'); if (l) { l.classList.toggle('is-checked', dst.checked); } }
			else { dst.value = src.value; }
		});
	};

	Filter.prototype.mirror = function (c) {
		var block = c.closest('[data-wpf4-block][data-display-mobile]');
		if (!block) { return; }
		var from = c.closest('.wpf4__options');
		var to = from.classList.contains('wpf4__options--mobile') ? block.querySelector('.wpf4__options--desktop') : block.querySelector('.wpf4__options--mobile');
		if (to) { this.copyValues(from, to); }
	};

	/* ---------------------------------------------------------------- chips */

	Filter.prototype.chipList = function () {
		var out = [];
		var seen = {};
		var self = this;
		this.controls().forEach(function (c) {
			if (c.dataset.range === '1') { return; }
			var key = c.name.replace(/^wpf_/, '');
			if (key === 'sort' || key === 'page') { return; }
			if (c.type === 'checkbox' || c.type === 'radio') {
				if (!c.checked || c.value === '') { return; }
				var id = key + '=' + c.value;
				if (seen[id]) { return; }
				seen[id] = 1;
				var label = c.dataset.label || c.value;
				var block = c.closest('[data-wpf4-block]');
				var title = block ? block.querySelector('.wpf4__legendText').textContent : '';
				if (block && block.dataset.kind === 'terms' && /^\d/.test(label)) { label = title + ' ' + label; }
				if (key === 'price') { label = self.rangeLabel(c.value, true); }
				out.push({ key: key, value: c.value, label: label });
			} else if (c.tagName === 'SELECT') {
				if (c.value === '') { return; }
				var opt = c.options[c.selectedIndex];
				out.push({ key: key, value: c.value, label: opt ? (opt.dataset.label || opt.textContent.replace(/\s*\(\d+\)$/, '')) : c.value });
			} else if (c.value !== '') {
				out.push({ key: key, value: c.value, label: '"' + c.value + '"' });
			}
		});
		this.sliders.forEach(function (s) {
			if (s.rMin.disabled || s.isDefault()) { return; }
			var v = s.values();
			var label = v[0] > s.min && v[1] < s.max ? W.t('to', s.format(v[0]), s.format(v[1])) : (v[1] < s.max ? W.t('upTo', s.format(v[1])) : W.t('from', s.format(v[0])));
			out.push({ key: s.key, value: (v[0] > s.min ? v[0] : '') + '-' + (v[1] < s.max ? v[1] : ''), label: label });
		});
		return out;
	};

	Filter.prototype.rangeLabel = function (value, price) {
		var parts = String(value).split('-');
		var f = price ? W.formatPrice : function (x) { return x; };
		if (parts[0] !== '' && parts[1] !== '' && parts[1] !== undefined) { return W.t('to', f(parts[0]), f(parts[1])); }
		if (parts[1] !== '' && parts[1] !== undefined) { return W.t('upTo', f(parts[1])); }
		return W.t('from', f(parts[0]));
	};

	Filter.prototype.renderChips = function () {
		var list = this.chipList();
		var self = this;
		if (this.chips && this.config.showChips !== false) {
			var html = list.map(function (c) {
				return '<button type="button" class="wpf4__chip" data-wpf4-chip data-key="' + W.esc(c.key) + '" data-value="' + W.esc(c.value) + '">' + W.esc(c.label) + ' <span aria-hidden="true">×</span><span class="wpf4__sr">' + W.esc(W.t('remove')) + '</span></button>';
			}).join('');
			// Same chips: keep the buttons. Re-rendering on the "change" that a blur fires (a typed search box losing
			// focus to a chip) would remove the chip under the pointer between mousedown and click, so the click was lost.
			if (html !== this.chipsHtml) {
				this.chips.innerHTML = html;
				this.chipsHtml = html;
			}
			this.chips.hidden = !list.length;
		}
		this.clearBtns.forEach(function (b) { if (b.closest('.wpf4__head')) { b.hidden = !list.length; } });
		this.lastLabels = list.map(function (c) { return c.label; });
		if (this.drawer && this.drawer.ok) { this.drawer.updateCount(); }
	};

	/* ---------------------------------------------------------------- requests */

	Filter.prototype.endpoint = function (params, extra) {
		var url = new URL(cfg.ajax, window.location.href);
		url.searchParams.set('action', cfg.action || 'woobewoo_pf_query');
		url.searchParams.set('filter', this.id);
		url.searchParams.set('ctx', JSON.stringify(this.ctx));
		// Multilingual stores (Polylang, WPML): the language of the page, so results and labels come back in it.
		if (this.ctx && this.ctx.lang) { url.searchParams.set('lang', this.ctx.lang); }
		Object.keys(params).forEach(function (k) { url.searchParams.set(k, params[k]); });
		Object.keys(extra || {}).forEach(function (k) { url.searchParams.set(k, extra[k]); });
		return url.toString();
	};

	Filter.prototype.fetchFacets = function (params) {
		return fetch(this.endpoint(params, { facets_only: 1 }), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status); }
			return r.json();
		});
	};

	/**
	 * Fetch results for the current controls and apply them. opts: { push:false, append:false, scrollY:n }
	 */
	Filter.prototype.request = function (reason, opts) {
		opts = opts || {};
		var self = this;
		var params = this.collect();
		if ((cfg.reload || this.config.reload) && reason !== 'more' && reason !== 'facets') {
			// "Update results without reloading" is off: load the filtered page (Back and links work as plain pages).
			window.location.href = this.buildUrl(params);
			return Promise.resolve(null);
		}
		var seq = ++this.seq;
		var focusEl = document.activeElement;
		var focusId = focusEl && this.root.contains(focusEl) ? focusEl.id : '';
		this.loading = true;
		this.root.classList.add('is-loading');
		this.root.setAttribute('aria-busy', 'true');
		var dim = this.config.overlay !== false && reason !== 'facets' ? (this.addonWrapper || this.container) : null;
		if (dim) { dim.classList.add('wpf4-loading'); }
		document.dispatchEvent(new CustomEvent('wpf:request', { detail: { filterId: this.id, params: params, reason: reason } }));
		var pageMode = !!this.addonWrapper && reason !== 'facets';
		var jsonReq = fetch(this.endpoint(params, pageMode ? { facets_only: 1 } : {}), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); });
		// Page-builder grid (Essential Addons, Ultimate Addons, Element Pack...): the grid is rendered by its widget,
		// so the filtered page is fetched and the wrapper's content swapped, as the classic engine did.
		var pageReq = pageMode ? fetch(this.buildUrl(params), { credentials: 'same-origin', headers: { Accept: 'text/html' } })
			.then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.text(); }) : Promise.resolve(null);
		return Promise.all([jsonReq, pageReq])
			.then(function (both) {
				var res = both[0];
				if (seq !== self.seq) { return res; }
				if (pageMode && both[1] !== null) { self.swapAddon(both[1]); }
				self.apply(res, params, opts, reason);
				if (focusId) {
					var el = document.getElementById(focusId);
					if (el && document.activeElement !== el && !el.disabled) { el.focus({ preventScroll: true }); }
				}
				return res;
			})
			.catch(function (err) {
				if (seq !== self.seq) { return; }
				self.announce(null, false, W.t('error'));
				document.dispatchEvent(new CustomEvent('wpf:error', { detail: { filterId: self.id, error: err, params: params } }));
			})
			.then(function (res) {
				if (seq === self.seq) {
					self.loading = false;
					if (dim) { dim.classList.remove('wpf4-loading'); }
					self.root.classList.remove('is-loading');
					self.root.removeAttribute('aria-busy');
				}
				return res;
			});
	};

	Filter.prototype.apply = function (res, params, opts, reason) {
		this.count = res.count;
		this.lastResponse = res;
		this.appliedParams = params;
		if (res.html !== undefined) {
			this.replaceProducts(res, opts.append);
		}
		this.applyFacets(res.facets || {});
		this.liftChecked();
		this.renderChips();
		this.announce(res.count);
		if (this.config.updateTitle && res.title) { document.title = res.title; }
		if (opts.push !== false) { this.pushUrl(res.url || this.buildUrl(params), params); }
		if (opts.scrollY !== undefined) { window.scrollTo(0, opts.scrollY); }
		this.rememberSave(params);
		document.dispatchEvent(new CustomEvent('wpf:results', { detail: { filterId: this.id, count: res.count, params: params, response: res, reason: reason } }));
		var self = this;
		W.filters.forEach(function (other) {
			if (other !== self && other.id === self.id) {
				other.params = params;
				other.page = self.page;
				other.applyParams(params);
				other.applyFacets(res.facets || {});
				other.count = res.count;
			}
		});
	};

	Filter.prototype.replaceProducts = function (res, append) {
		var tpl = document.createElement('div');
		tpl.innerHTML = res.html;
		var fresh = tpl.firstElementChild;
		if (!this.container || !document.contains(this.container)) {
			this.container = this.findContainer();
		}
		if (!this.container) {
			// Nothing matched before: the theme shows a notice instead of the list.
			var notice = document.querySelector('.woocommerce-no-products-found, .wc-block-product-template__no-results, .woocommerce-info');
			if (notice && fresh) { notice.replaceWith(fresh); this.container = fresh; }
		} else if (fresh) {
			if (append && this.container.tagName === fresh.tagName) {
				while (fresh.firstChild) { this.container.appendChild(fresh.firstChild); }
			} else {
				var wasInner = this.container.classList.contains('wpf4-layout__inner');
				this.container.replaceWith(fresh);
				this.container = fresh;
				if (wasInner && this.markInner) { this.markInner(fresh); }
			}
		}
		var rc = document.querySelectorAll('.woocommerce-result-count');
		if (res.result_count_html !== undefined) {
			rc.forEach(function (n) { var t = document.createElement('div'); t.innerHTML = res.result_count_html; if (t.firstElementChild) { n.replaceWith(t.firstElementChild); } });
		}
		var pag = document.querySelectorAll('.woocommerce-pagination');
		if (this.config.pagination === 'pages') {
			if (pag.length) {
				pag.forEach(function (n, i) { if (i === 0 && res.pagination_html) { var t = document.createElement('div'); t.innerHTML = res.pagination_html; n.replaceWith(t.firstElementChild); } else { n.remove(); } });
			} else if (res.pagination_html && this.container) {
				var t = document.createElement('div');
				t.innerHTML = res.pagination_html;
				this.container.insertAdjacentElement('afterend', t.firstElementChild);
			}
		} else {
			pag.forEach(function (n) { n.remove(); });
			this.updateMore(res);
		}
	};

	Filter.prototype.applyFacets = function (facets) {
		var self = this;
		this.root.querySelectorAll('[data-wpf4-block]').forEach(function (block) {
			var key = block.dataset.key;
			var f = facets[key];
			if (!f) { return; }
			var logic = block.dataset.logic;
			if (f.counts) {
				block.querySelectorAll('input[data-wpf4-control]').forEach(function (input) {
					if (input.value === '' || input.dataset.range === '1') { return; }
					var n = f.counts[input.value] !== undefined ? f.counts[input.value] : 0;
					if (block.dataset.kind === 'sale' || block.dataset.kind === 'featured') { n = f.counts['1'] || 0; }
					input.dataset.count = n;
					var label = input.closest('.wpf4__opt, .wpf4__toggle');
					if (label) {
						var cnt = label.querySelector('.wpf4__count');
						if (cnt) { cnt.textContent = W.formatCount(n); }
						var empty = n === 0 && !input.checked;
						label.classList.toggle('is-empty', empty);
						if (block.dataset.hideEmpty === '1' && label.matches('.wpf4__opt')) { label.hidden = empty; }
						if (block.dataset.kind === 'terms' || block.dataset.kind === 'author') { input.setAttribute('aria-disabled', empty ? 'true' : 'false'); }
					}
				});
				block.querySelectorAll('select[data-wpf4-control] option').forEach(function (opt) {
					if (opt.value === '' || block.dataset.kind === 'sort') { return; }
					var n = f.counts[opt.value] !== undefined ? f.counts[opt.value] : 0;
					opt.disabled = n === 0 && !opt.selected;
					if (opt.dataset.label) { opt.textContent = opt.dataset.label + (opt.dataset.count !== undefined ? ' (' + n + ')' : ''); }
					opt.dataset.count = n;
				});
			}
			if (f.kind === 'price' || f.kind === 'meta') {
				self.sliders.forEach(function (s) {
					if (s.key !== key) { return; }
					s.setBounds(parseFloat(f.min), parseFloat(f.max));
					if (f.hist !== undefined && s.setHist) { s.setHist(f.hist, parseFloat(f.min), parseFloat(f.max)); }
				});
			}
			var hint = block.querySelector('[data-wpf4-hint]');
			if (hint && f.hint !== undefined) { hint.textContent = f.hint ? ' · ' + f.hint : ''; }
		});
		this.syncStars();
		this.refold();
	};

	/* ---------------------------------------------------------------- rating pills */

	/**
	 * The star pills are native radios; "Clear" shows only while one is picked and unpicks it (there is no "Any" radio).
	 */
	Filter.prototype.bindStars = function () {
		var self = this;
		this.root.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-wpf4-star-clear]');
			if (!btn || !self.root.contains(btn)) { return; }
			var box = btn.closest('.wpf4__options--stars');
			var first = null;
			box.querySelectorAll('input[type="radio"]').forEach(function (r) {
				if (!first) { first = r; }
				r.checked = false;
				var l = r.closest('.wpf4__opt');
				if (l) { l.classList.remove('is-checked'); }
			});
			var block = btn.closest('[data-wpf4-block]');
			if (block && block.dataset.displayMobile) { self.mirror(first); }
			btn.hidden = true;
			self.page = 1;
			self.lastChanged = first || btn;
			self.renderChips();
			document.dispatchEvent(new CustomEvent('wpf:change', { detail: { filterId: self.id, control: first, params: self.collect() } }));
			if (self.config.autoUpdate) { self.request('change'); }
		});
	};

	Filter.prototype.syncStars = function () {
		this.root.querySelectorAll('.wpf4__options--stars').forEach(function (box) {
			var any = false;
			box.querySelectorAll('input[type="radio"]').forEach(function (r) {
				var l = r.closest('.wpf4__opt');
				if (l) { l.classList.toggle('is-checked', r.checked); if (r.checked) { l.classList.remove('is-empty'); l.hidden = false; } }
				if (r.checked) { any = true; }
			});
			var clear = box.querySelector('[data-wpf4-star-clear]');
			if (clear) { clear.hidden = !any; }
		});
	};

	/* ---------------------------------------------------------------- show more after N rows */

	Filter.prototype.setupShowMore = function () {
		var self = this;
		this.folds = [];
		this.root.querySelectorAll('[data-wpf4-block][data-show-more]').forEach(function (block) {
			block.querySelectorAll('.wpf4__options--chips .wpf4__list, .wpf4__options--squares .wpf4__list, .wpf4__options--swatches .wpf4__list, .wpf4__options--list .wpf4__list').forEach(function (list) {
				if (!list.closest('.wpf4__dd')) { self.folds.push(new Fold(self, block, list)); }
			});
		});
		if (!this.folds.length) { return; }
		var t;
		window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(function () { self.refold(); }, 150); });
		document.addEventListener('wpf:drawer', function (e) {
			if (e.detail && e.detail.open && e.detail.filterId === self.id) { requestAnimationFrame(function () { self.refold(); }); }
		});
		this.root.addEventListener('input', function (e) {
			if (e.target && e.target.matches && e.target.matches('[data-wpf4-list-search]')) { self.refold(); }
		});
		this.root.querySelectorAll('[data-wpf4-fold]').forEach(function (btn) {
			btn.addEventListener('click', function () { requestAnimationFrame(function () { self.refold(); }); });
		});
	};

	Filter.prototype.refold = function () {
		(this.folds || []).forEach(function (f) { f.update(); });
	};

	/**
	 * One value list folded after N rendered rows. The rows are measured from the real positions of the values, so
	 * any chip, square, swatch or list row height works. Folded values leave the flow and the tab order; picked
	 * values that would fall behind the fold are pulled to the front. The pill keeps focus when it toggles.
	 */
	function Fold(filter, block, list) {
		this.filter = filter;
		this.block = block;
		this.list = list;
		this.rows = parseInt(block.dataset.showMore, 10) || 0;
		this.label = block.dataset.showMoreLabel || W.t('showMore');
		this.open = false;
		this.items = Array.prototype.filter.call(list.children, function (c) { return c.classList.contains('wpf4__opt'); });
		// 4.0.0 "top N": the N values with the most products show first, the rest fold. The ranking is taken once,
		// from the counts the page was drawn with, so the shown values do not jump around while the shopper picks.
		// Lists with child values (a category tree) and swatches keep the row rule.
		this.top = parseInt(block.dataset.showMoreTop, 10) || 0;
		var tree = this.items.some(function (i) { return i.classList.contains('wpf4__opt--child'); });
		// Sizes and numbers (S, M, XL, 38, 42) read in order, so a popularity pick would leave gaps: rows for them.
		var sizeLike = /^(xx?[sl]|[sml]|xxx?l|\d+([.,]\d+)?\s*\w{0,3}|one size|small|medium|large|x-?large|x-?small)$/i;
		var ordered = this.items.length > 0 && this.items.every(function (i) {
			var inp = i.querySelector('input');
			return sizeLike.test(((inp && inp.dataset.label) || i.textContent || '').trim());
		});
		if (this.top && (ordered || block.dataset.display === 'swatches' || list.closest('.wpf4__options--swatches'))) { this.top = 0; }
		if (this.top && tree) {
			// A category tree keeps its order: the first N values show, the rest fold.
			this.topSet = this.items.slice(0, this.top);
		} else if (this.top) {
			var rank = this.items.map(function (i, n) {
				var inp = i.querySelector('input');
				return { item: i, n: n, c: inp ? (parseInt(inp.dataset.count, 10) || 0) : 0 };
			});
			rank.sort(function (a, b) { return b.c - a.c || a.n - b.n; });
			this.topSet = rank.slice(0, this.top).map(function (r) { return r.item; });
		}
		if ((!this.rows && !this.top) || this.items.length < 2) { this.rows = 0; this.top = 0; return; }
		if (!list.id) { list.id = (filter.root.id || 'wpf4') + '-list-' + (++uidCounter); }
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'wpf4__more';
		btn.setAttribute('aria-expanded', 'false');
		btn.setAttribute('aria-controls', list.id);
		btn.hidden = true;
		list.insertAdjacentElement('afterend', btn);
		this.btn = btn;
		var self = this;
		btn.addEventListener('click', function () {
			self.open = !self.open;
			self.update();
			btn.focus({ preventScroll: true });
		});
		this.update();
	}

	Fold.prototype.unfold = function () {
		var list = this.list;
		this.items.forEach(function (i) {
			i.classList.remove('wpf4__opt--folded');
			if (i.parentNode === list) { list.appendChild(i); }
		});
	};

	Fold.prototype.visible = function () {
		return this.items.filter(function (i) { return i.parentNode && !i.hidden && i.offsetParent !== null; });
	};

	/**
	 * Values that sit beyond the first N rows (row = distinct top edge, 2px tolerance).
	 */
	Fold.prototype.beyond = function (vis) {
		var tops = [];
		var pos = vis.map(function (i) { return Math.round(i.getBoundingClientRect().top); });
		pos.forEach(function (t) {
			for (var k = 0; k < tops.length; k++) { if (Math.abs(tops[k] - t) <= 2) { return; } }
			tops.push(t);
		});
		tops.sort(function (a, b) { return a - b; });
		if (tops.length <= this.rows) { return []; }
		var limit = tops[this.rows] - 2;
		return vis.filter(function (i, n) { return pos[n] >= limit; });
	};

	Fold.prototype.update = function () {
		if ((!this.rows && !this.top) || !this.btn) { return; }
		var list = this.list;
		if (list.offsetParent === null) { return; }
		var search = this.block.querySelector('[data-wpf4-list-search]');
		var searching = !!(search && search.value.trim() !== '');
		this.unfold();
		if (this.open || searching) {
			this.btn.hidden = searching && !this.open;
			this.btn.textContent = W.t('showLess');
			this.btn.setAttribute('aria-expanded', 'true');
			return;
		}
		var vis = this.visible();
		if (this.top) {
			// Top N mode: everything outside the most popular N folds, except picked values (they stay where they are).
			var keep = this.topSet;
			var rest = vis.filter(function (i) { var inp = i.querySelector('input'); return keep.indexOf(i) === -1 && !(inp && inp.checked); });
			if (rest.length < 2) { rest = []; } // Never hide one value behind a "Show more (1)" pill.
			rest.forEach(function (i) { i.classList.add('wpf4__opt--folded'); });
			this.btn.hidden = !rest.length;
			this.btn.textContent = W.t('moreCount', this.label, W.formatCount(rest.length));
			this.btn.setAttribute('aria-expanded', 'false');
			return;
		}
		var fold = this.beyond(vis);
		var picked = fold.filter(function (i) { var inp = i.querySelector('input'); return inp && inp.checked; });
		if (picked.length) {
			// Picked values behind the fold come to the front, then the rows are measured again.
			picked.reverse().forEach(function (i) { list.insertBefore(i, list.firstChild); });
			vis = this.visible();
			fold = this.beyond(vis);
		}
		fold = fold.filter(function (i) { var inp = i.querySelector('input'); return !(inp && inp.checked); });
		fold.forEach(function (i) { i.classList.add('wpf4__opt--folded'); });
		this.btn.hidden = !fold.length;
		this.btn.textContent = W.t('moreCount', this.label, W.formatCount(fold.length));
		this.btn.setAttribute('aria-expanded', 'false');
	};

	W.Fold = Fold;

	Filter.prototype.announce = function (count, silent, text) {
		if (!this.live || this.config.live === false) { return; }
		var msg = text || (count === null ? '' : W.t(count === 1 ? 'product' : 'products', W.formatCount(count)));
		if (silent) { this.live.textContent = msg; return; }
		var self = this;
		this.live.textContent = '';
		setTimeout(function () { self.live.textContent = msg; }, 50);
	};

	/* ---------------------------------------------------------------- URL and history */

	Filter.prototype.buildUrl = function (params) {
		var base = this.ctx.base || window.location.pathname;
		var url = new URL(base, window.location.href);
		Object.keys(params).forEach(function (k) { url.searchParams.set(k, params[k]); });
		return url.toString().replace(/%2C/g, ',');
	};

	Filter.prototype.pushUrl = function (url, params) {
		if (!window.history || !window.history.pushState) { return; }
		if (url === window.location.href) { return; }
		// Keep the scroll position of the view we are leaving so Back can bring it back.
		var prev = window.history.state && window.history.state.wpf4 ? window.history.state : { wpf4: { id: this.id, params: this.params } };
		prev.wpf4.scrollY = window.scrollY;
		window.history.replaceState(prev, '', window.location.href);
		window.history.pushState({ wpf4: { id: this.id, params: params, scrollY: window.scrollY } }, '', url);
		this.params = params;
	};

	Filter.prototype.bindHistory = function () {
		var self = this;
		if (!window.history || !window.history.replaceState) { return; }
		if ('scrollRestoration' in window.history) { window.history.scrollRestoration = 'manual'; }
		if (!window.history.state || !window.history.state.wpf4) {
			window.history.replaceState({ wpf4: { id: this.id, params: this.params, scrollY: window.scrollY } }, '', window.location.href);
		}
		window.addEventListener('popstate', function (e) {
			var state = e.state && e.state.wpf4;
			if (!state || state.id !== self.id) { return; }
			self.applyParams(state.params || {});
			self.params = state.params || {};
			self.request('popstate', { push: false, scrollY: state.scrollY || 0 });
		});
	};

	/* ---------------------------------------------------------------- pagination */

	Filter.prototype.bindPagination = function () {
		var self = this;
		document.addEventListener('click', function (e) {
			var a = e.target.closest('.wpf4-pagination a[data-page]');
			if (!a) { return; }
			e.preventDefault();
			self.page = parseInt(a.dataset.page, 10) || 1;
			self.lastChanged = a;
			self.request('page').then(function () { self.scrollToProducts(); });
		});
	};

	Filter.prototype.setupLoadMore = function () {
		var self = this;
		var wrap = document.createElement('div');
		wrap.className = 'wpf4-loadMore';
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'wpf4-loadMore__btn';
		btn.textContent = W.t('showMore');
		wrap.appendChild(btn);
		this.more = wrap;
		btn.addEventListener('click', function () {
			self.page = (self.page || 1) + 1;
			btn.disabled = true;
			btn.textContent = W.t('loading');
			self.request('more', { append: true }).then(function () { btn.disabled = false; btn.textContent = W.t('showMore'); });
		});
		this.placeMore();
		this.updateMore({ has_more: this.initialHasMore() });
	};

	Filter.prototype.setupInfinite = function () {
		var self = this;
		var sentinel = document.createElement('div');
		sentinel.className = 'wpf4-sentinel';
		sentinel.setAttribute('aria-hidden', 'true');
		this.more = sentinel;
		this.placeMore();
		this.hasMore = this.initialHasMore();
		if (!('IntersectionObserver' in window)) { return; }
		this.observer = new IntersectionObserver(function (entries) {
			if (!entries[0].isIntersecting || self.loading || !self.hasMore) { return; }
			self.page = (self.page || 1) + 1;
			self.request('more', { append: true });
		}, { rootMargin: '400px 0px' });
		this.observer.observe(sentinel);
	};

	Filter.prototype.initialHasMore = function () {
		return !!document.querySelector('.woocommerce-pagination a.next, .woocommerce-pagination .next');
	};

	Filter.prototype.placeMore = function () {
		if (!this.more) { return; }
		var c = this.container || this.findContainer();
		if (c && this.more.parentNode !== c.parentNode) { c.insertAdjacentElement('afterend', this.more); }
	};

	Filter.prototype.updateMore = function (res) {
		if (!this.more) { return; }
		this.hasMore = !!res.has_more;
		this.placeMore();
		if (this.config.pagination === 'load_more') { this.more.hidden = !this.hasMore; }
	};

	/* ---------------------------------------------------------------- remembered filters */

	Filter.prototype.rememberKey = function () { return 'wpf4:last:' + this.id; };

	/**
	 * Desktop "You filtered by … last time. Use it again?" (the phone drawer shows the same in the sheet).
	 */
	Filter.prototype.bindRememberDesk = function () {
		var btn = this.root.querySelector('[data-wpf4-remember-desk]');
		if (!btn) { return; }
		var self = this;
		var saved = this.remembered();
		if (!saved || !saved.labels || !saved.labels.length || !saved.params || this.pickedCount() > 0) { btn.hidden = true; return; }
		var names = saved.labels.map(function (l) { return '<b>' + W.esc(l) + '</b>'; });
		var list = names.length > 1 ? names.slice(0, -1).join(', ') + ' ' + W.t('and') + ' ' + names[names.length - 1] : names[0];
		btn.querySelector('[data-wpf4-remember-text]').innerHTML = W.t('remembered', list) + ' <b>' + W.esc(W.t('useAgain')) + '</b>';
		btn.hidden = false;
		btn.addEventListener('click', function () {
			btn.hidden = true;
			self.applyParams(saved.params);
			self.request('remember');
		});
		document.addEventListener('wpf:change', function (e) { if (e.detail && e.detail.filterId === self.id) { btn.hidden = true; } });
	};

	Filter.prototype.remembered = function () {
		if (!this.config.remember) { return null; }
		try { return JSON.parse(window.localStorage.getItem(this.rememberKey()) || 'null'); } catch (e) { return null; }
	};

	Filter.prototype.rememberSave = function (params) {
		if (!this.config.remember) { return; }
		var p = {};
		Object.keys(params).forEach(function (k) { if (k !== 'wpf_page') { p[k] = params[k]; } });
		try {
			if (Object.keys(p).length) {
				window.localStorage.setItem(this.rememberKey(), JSON.stringify({ params: p, labels: this.lastLabels || [], time: Date.now() }));
			}
		} catch (e) { /* storage blocked: nothing to remember */ }
	};

	W.Filter = Filter;

	// A filter with a Sort by block replaces the shop's own "Default sorting" dropdown: two sort menus confuse
	// shoppers. filter.css hides it with :has(); this covers browsers without :has().
	function hideShopSort() {
		if (!document.querySelector('[data-wpf4] .wpf4__block--sort')) { return; }
		document.querySelectorAll('.woocommerce-ordering, .wc-block-catalog-sorting').forEach(function (el) {
			if (!el.closest('[data-wpf4]')) { el.style.display = 'none'; }
		});
	}
	function boot() {
		document.querySelectorAll('[data-wpf4]').forEach(function (root) {
			if (!root.wpf4) { root.wpf4 = new Filter(root); }
		});
		hideShopSort();
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	// The builder and the setup wizard swap new filter HTML into their preview without a reload: start it.
	document.addEventListener('wpfPreviewUpdated', boot);
	W.boot = boot;
})();
