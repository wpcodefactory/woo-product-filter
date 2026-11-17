/*
 * Product Filter by WBW - results module, storefront engine 4.
 * Extends WPF4.Filter (filter.js): results page redirect, products per page block, live found count, skeleton
 * loader. Config per filter comes from the hidden [data-wpf-results] span the module prints before the form.
 * Classic (3.x) filters never reach this file: WPF4 only exists with engine 4 markup on the page.
 */
(function () {
	'use strict';
	var W = window.WPF4;
	if (!W || !W.Filter) { return; }
	var P = W.Filter.prototype;

	/** The module's config of a filter (null when the filter has none). Cached on the filter. */
	function cfgOf(filter) {
		if (filter.wpfResults !== undefined) { return filter.wpfResults; }
		var el = filter.root ? filter.root.querySelector('[data-wpf-results]') : null;
		var cfg = null;
		if (el) {
			try { cfg = JSON.parse(el.getAttribute('data-wpf-results') || 'null'); } catch (e) { cfg = null; }
		}
		filter.wpfResults = cfg && typeof cfg === 'object' ? cfg : null;
		return filter.wpfResults;
	}
	W.resultsCfg = cfgOf;

	/* ---------------------------------------------------------------- results page */

	function trimPath(p) { return String(p || '').replace(/\/+$/, ''); }

	/** Is the results page this very page (or a pretty sub path of it)? Then the filter works in place. */
	function onTarget(url) {
		var t;
		try { t = new URL(url, window.location.href); } catch (e) { return true; }
		if (t.origin !== window.location.origin) { return false; }
		var tp = trimPath(t.pathname), lp = trimPath(window.location.pathname);
		return tp === '' ? lp === '' : (lp === tp || lp.indexOf(tp + '/') === 0);
	}

	function queryString(params) {
		return Object.keys(params).map(function (k) {
			return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]).replace(/%2C/g, ',');
		}).join('&');
	}

	/** Target address with the canonical wpf_* params: pretty (links module) for an archive with clean links on. */
	function resultsUrl(cfg, params) {
		var qs = queryString(params);
		var api = window.wpfLinksApi;
		if (cfg.pretty && api && api.prettyUrl && api.canonicalFromSearch) {
			try {
				var canon = api.canonicalFromSearch('?' + qs);
				if (canon) { return api.prettyUrl(cfg.url.split('?')[0], canon); }
			} catch (e) { /* fall back to the query string */ }
		}
		if (!qs) { return cfg.url; }
		return cfg.url + (cfg.url.indexOf('?') >= 0 ? '&' : '?') + qs;
	}
	W.resultsUrl = resultsUrl;

	// Reasons that never leave the page: count refreshes, Back/Forward, paging and "Clear".
	var STAY = { facets: 1, popstate: 1, more: 1, page: 1, clear: 1 };
	var origRequest = P.request;
	P.request = function (reason, opts) {
		var cfg = cfgOf(this);
		if (cfg && cfg.url && !STAY[reason] && !onTarget(cfg.url)) {
			var params = this.collect();
			delete params.wpf_page;
			var url = resultsUrl(cfg, params);
			document.dispatchEvent(new CustomEvent('wpf:redirect', { detail: { filterId: this.id, params: params, url: url, reason: reason } }));
			window.location.href = url;
			return Promise.resolve(null);
		}
		return origRequest.call(this, reason, opts);
	};

	/* ---------------------------------------------------------------- products per page */

	// The store default is not sent: the address stays clean until shoppers pick another size.
	var origCollect = P.collect;
	P.collect = function () {
		var p = origCollect.call(this);
		if (p.wpf_per_page !== undefined) {
			var cfg = cfgOf(this);
			var n = parseInt(p.wpf_per_page, 10);
			if (!(n > 0) || (cfg && cfg.perPage && n === parseInt(cfg.perPage.def, 10))) { delete p.wpf_per_page; }
		}
		return p;
	};

	// Not a filter: no chip, not counted on the Clear button or the drawer badge.
	var origChipList = P.chipList;
	P.chipList = function () {
		return origChipList.call(this).filter(function (c) { return c.key !== 'per_page'; });
	};
	var origPickedCount = P.pickedCount;
	P.pickedCount = function () {
		var n = origPickedCount.call(this);
		return this.collect().wpf_per_page !== undefined ? Math.max(0, n - 1) : n;
	};

	/** Mark the applied size (the store default when the params carry none): after results, Back/Forward, Clear. */
	function syncPerPage(filter, params) {
		var cfg = cfgOf(filter);
		var v = params && params.wpf_per_page !== undefined ? String(params.wpf_per_page) : (cfg && cfg.perPage ? String(cfg.perPage.def) : '');
		if (!v || !filter.root) { return; }
		filter.root.querySelectorAll('[data-wpf-per-page]').forEach(function (box) {
			if (box.tagName === 'SELECT') {
				var has = Array.prototype.some.call(box.options, function (o) { return o.value === v; });
				if (has && box.value !== v) { box.value = v; }
				return;
			}
			box.querySelectorAll('input[type="radio"]').forEach(function (r) {
				var on = r.value === v;
				if (r.checked !== on) { r.checked = on; }
				var l = r.closest('.wpf4__opt');
				if (l) { l.classList.toggle('is-checked', on); }
			});
		});
	}

	/* ---------------------------------------------------------------- found count */

	function fill(pattern, n) {
		return String(pattern).replace(/%1\$[sd]|%[sd]/g, function () { return W.formatCount(n); });
	}

	/** "128 products" everywhere on the page: the filter's own line and every [wpf-found-count]. */
	function updateFound(n) {
		if (typeof n !== 'number' || isNaN(n)) { return; }
		document.querySelectorAll('[data-wpf-found-count]').forEach(function (el) {
			var one = el.getAttribute('data-one'), many = el.getAttribute('data-many');
			var p = n === 1 ? (one || many) : (many || one);
			if (p) { el.textContent = fill(p, n); }
		});
	}
	W.updateFoundCount = updateFound;

	/* ---------------------------------------------------------------- skeleton loader */

	function skeletonOn(filter) {
		var cfg = cfgOf(filter);
		if (!cfg || !cfg.skeleton) { return; }
		var grid = filter.addonWrapper || filter.container;
		if (grid) { grid.classList.add('wpf-skeleton'); }
		if (filter.root) { filter.root.classList.add('wpf-skeleton-counts'); }
	}

	function skeletonOff() {
		document.querySelectorAll('.wpf-skeleton').forEach(function (el) { el.classList.remove('wpf-skeleton'); });
		document.querySelectorAll('.wpf-skeleton-counts').forEach(function (el) { el.classList.remove('wpf-skeleton-counts'); });
	}

	/* ---------------------------------------------------------------- wiring */

	function setupFilter(filter) {
		var cfg = cfgOf(filter);
		if (!cfg || !filter.root) { return; }
		if (cfg.url && cfg.button && !onTarget(cfg.url)) {
			filter.root.classList.add('wpf4--results-page');
			filter.root.querySelectorAll('[data-wpf4-apply]').forEach(function (b) { b.textContent = cfg.button; });
		}
		syncPerPage(filter, filter.params);
	}

	document.addEventListener('wpf:ready', function (e) {
		if (e.detail && e.detail.filter) { setupFilter(e.detail.filter); }
	});
	W.filters.forEach(setupFilter);

	document.addEventListener('wpf:request', function (e) {
		var d = e.detail || {};
		if (d.reason === 'facets' || d.reason === 'more') { return; }
		W.filters.forEach(function (f) { if (String(f.id) === String(d.filterId)) { skeletonOn(f); } });
	});
	document.addEventListener('wpf:results', function (e) {
		var d = e.detail || {};
		skeletonOff();
		updateFound(Number(d.count));
		W.filters.forEach(function (f) { if (String(f.id) === String(d.filterId)) { syncPerPage(f, d.params); } });
	});
	document.addEventListener('wpf:error', skeletonOff);
})();
