/*
 * Product Filter by WBW - storefront engine 4: swatches, squares and chip options.
 * Native inputs carry the role and state; this adds arrow key movement, Enter to toggle and the checked class.
 */
(function () {
	'use strict';
	var W = window.WPF4 = window.WPF4 || {};

	function Swatches(filter) {
		this.filter = filter;
		this.root = filter.root;
		this.bind();
		this.sync();
	}

	Swatches.prototype.lists = function () {
		return this.root.querySelectorAll('.wpf4__options--swatches .wpf4__list, .wpf4__options--squares .wpf4__list, .wpf4__options--chips .wpf4__list');
	};

	/**
	 * Mirror the native checked state on the label (for browsers without :has()).
	 */
	Swatches.prototype.sync = function () {
		var inputs = this.root.querySelectorAll('.wpf4__opt input');
		for (var i = 0; i < inputs.length; i++) {
			var label = inputs[i].closest('.wpf4__opt');
			if (label) { label.classList.toggle('is-checked', inputs[i].checked); }
		}
	};

	Swatches.prototype.bind = function () {
		var self = this;
		this.root.addEventListener('change', function (e) {
			if (e.target && e.target.matches && e.target.matches('.wpf4__opt input')) {
				var list = e.target.closest('.wpf4__list');
				if (list) {
					var all = list.querySelectorAll('input');
					for (var i = 0; i < all.length; i++) {
						var l = all[i].closest('.wpf4__opt');
						if (l) { l.classList.toggle('is-checked', all[i].checked); }
					}
				}
			}
		});
		this.root.addEventListener('keydown', function (e) {
			var input = e.target;
			if (!input || !input.matches || !input.matches('.wpf4__chipOpt input, .wpf4__square input, .wpf4__swatch input, .wpf4__star input')) { return; }
			var list = input.closest('.wpf4__list, .wpf4__stars');
			if (!list) { return; }
			var inputs = Array.prototype.filter.call(list.querySelectorAll('input:not(:disabled)'), function (i) { var l = i.closest('.wpf4__opt'); return !l || (!l.hidden && !l.classList.contains('wpf4__opt--folded')); });
			var idx = inputs.indexOf(input);
			var next = null;
			switch (e.key) {
				case 'ArrowRight':
				case 'ArrowDown':
					next = inputs[(idx + 1) % inputs.length];
					break;
				case 'ArrowLeft':
				case 'ArrowUp':
					next = inputs[(idx - 1 + inputs.length) % inputs.length];
					break;
				case 'Home':
					next = inputs[0];
					break;
				case 'End':
					next = inputs[inputs.length - 1];
					break;
				case 'Enter':
					e.preventDefault();
					if (input.type === 'checkbox') {
						input.checked = !input.checked;
					} else {
						input.checked = true;
					}
					input.dispatchEvent(new Event('change', { bubbles: true }));
					return;
				default:
					return;
			}
			if (next) {
				e.preventDefault();
				next.focus();
				if (next.closest('.wpf4__stars') && next.closest('.wpf4__opt').scrollIntoView) { next.closest('.wpf4__opt').scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
				// Radios: moving focus with arrows also picks, like native radio groups.
				if (next.type === 'radio' && !next.checked) {
					next.checked = true;
					next.dispatchEvent(new Event('change', { bubbles: true }));
				}
			}
		});
	};

	W.Swatches = Swatches;
})();
