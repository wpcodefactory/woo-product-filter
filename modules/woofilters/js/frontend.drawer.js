/**
 * Product Filter by WBW - Storefront mobile drawer (4.0)
 *
 * Vanilla JS, no jQuery. Under 768px (or always, when the filter is set to "drawer only")
 * the filter container is wrapped in a bottom sheet: a fixed pill button "Filters (N)",
 * active chips at the top of the sheet, a full width "Show N results" button, focus trap,
 * Escape to close, "Use your last filters again?" from localStorage, and an aria-live
 * region announcing the product count after each AJAX update.
 *
 * Loaded only when a filter has the `mobile_drawer` setting on.
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';

	var BREAKPOINT = 768,
		PRODUCT_SELECTORS = [ 'ul.products', '.products', '.wc-block-product-template', '.wp-block-woocommerce-product-collection', '.wc-block-grid' ],
		drawers = [],
		live = null;

	// Setting missing => on (filters made by the wizard); '' or '0' => off.
	function onByDefault( v ) {
		return typeof v === 'undefined' || v === '1' || v === 1 || v === true;
	}

	function text( el, fallback ) {
		return el && el.textContent ? el.textContent.trim() : fallback;
	}

	function readSettings( wrapper ) {
		var raw = wrapper.getAttribute( 'data-filter-settings' ), parsed;
		if ( ! raw ) {
			return {};
		}
		try {
			parsed = JSON.parse( raw );
		} catch ( e ) {
			// The attribute is HTML entity encoded by PHP; decode through a textarea.
			var ta = document.createElement( 'textarea' );
			ta.innerHTML = raw;
			try {
				parsed = JSON.parse( ta.value );
			} catch ( e2 ) {
				return {};
			}
		}
		return ( parsed && parsed.settings ) ? parsed.settings : {};
	}

	function productsContainer( settings ) {
		var sel = settings.product_container_selector, el = null;
		if ( sel ) {
			if ( sel.search( /\.|#/ ) === -1 ) {
				sel = '.' + sel.replace( /(\s+)(\w+)/g, ' .$2' );
			}
			try {
				el = document.querySelector( sel );
			} catch ( e ) {
				el = null;
			}
		}
		for ( var i = 0; ! el && i < PRODUCT_SELECTORS.length; i++ ) {
			el = document.querySelector( PRODUCT_SELECTORS[ i ] );
		}
		return el;
	}

	function resultCount( settings ) {
		var rc = document.querySelector( '.woocommerce-result-count' ), m, n;
		if ( rc ) {
			var t = rc.textContent.replace( / /g, ' ' );
			m = t.match( /of\s+(\d[\d.,\s]*)/i ) || t.match( /(\d[\d.,\s]*)\s*(results?|products?|items?)/i ) || t.match( /(\d[\d.,\s]*)/ );
			if ( m ) {
				n = parseInt( m[ 1 ].replace( /[^\d]/g, '' ), 10 );
				if ( ! isNaN( n ) ) {
					return n;
				}
			}
		}
		var c = productsContainer( settings );
		if ( c ) {
			return c.querySelectorAll( '.product, .wc-block-grid__product, li.wc-block-product' ).length;
		}
		return 0;
	}

	function ensureLive() {
		if ( live ) {
			return live;
		}
		live = document.createElement( 'div' );
		live.className = 'wpfDrawerLive';
		live.setAttribute( 'aria-live', 'polite' );
		live.setAttribute( 'aria-atomic', 'true' );
		document.body.appendChild( live );
		return live;
	}

	function announce( n ) {
		var el = ensureLive(), msg = n === 1 ? '1 product' : n + ' products';
		el.textContent = '';
		setTimeout( function () {
			el.textContent = msg;
		}, 50 );
	}

	function storageKey( id ) {
		return 'wpfLastFilters_' + id;
	}

	function activeChoices( wrapper ) {
		var out = [];
		Array.prototype.forEach.call( wrapper.querySelectorAll( 'input[type="checkbox"]:checked, input[type="radio"]:checked' ), function ( inp ) {
			if ( inp.closest( '.wpfFilterButtons' ) || inp.value === '' || inp.value === 'default' ) {
				return;
			}
			var label = inp.closest( 'label' ) || ( inp.id ? wrapper.querySelector( 'label[for="' + inp.id + '"]' ) : null ) || inp.parentNode,
				name = text( label ? ( label.querySelector( '.wpfDisplay, .wpfAttrLabel, .wpfCategoryTitle' ) || label ) : null, inp.value ).replace( /\(\d+\)\s*$/, '' ).trim();
			if ( name ) {
				out.push( { name: name, input: inp } );
			}
		} );
		Array.prototype.forEach.call( wrapper.querySelectorAll( 'select' ), function ( sel ) {
			Array.prototype.forEach.call( sel.selectedOptions || [], function ( opt ) {
				if ( opt.value && opt.index > 0 && ! opt.disabled ) {
					out.push( { name: opt.textContent.trim(), input: sel, option: opt } );
				}
			} );
		} );
		return out;
	}

	function fireChange( el ) {
		var ev;
		try {
			ev = new Event( 'change', { bubbles: true } );
		} catch ( e ) {
			ev = document.createEvent( 'Event' );
			ev.initEvent( 'change', true, true );
		}
		el.dispatchEvent( ev );
		if ( window.jQuery ) {
			window.jQuery( el ).trigger( 'change' );
		}
	}

	function Drawer( wrapper ) {
		this.wrapper = wrapper;
		this.settings = readSettings( wrapper );
		this.id = wrapper.getAttribute( 'data-filter' ) || '0';
		this.always = this.settings.mobile_drawer_always === '1' || this.settings.mobile_drawer_always === 1;
		this.buttonText = this.settings.mobile_drawer_text || 'Filters';
		this.useChips = onByDefault( this.settings.mobile_drawer_chips );
		this.useResults = onByDefault( this.settings.mobile_drawer_results );
		this.open = false;
		this.mounted = false;
		this.lastFocus = null;
		this.build();
		this.update();
	}

	Drawer.prototype.build = function () {
		var self = this;
		this.placeholder = document.createComment( 'wpf-drawer-' + this.id );
		this.button = document.createElement( 'button' );
		this.button.type = 'button';
		this.button.className = 'wpfDrawerButton';
		this.button.setAttribute( 'aria-haspopup', 'dialog' );
		this.button.setAttribute( 'aria-expanded', 'false' );
		this.button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><span class="wpfDrawerButtonText"></span><span class="wpfDrawerCount" hidden></span>';
		this.button.addEventListener( 'click', function () {
			self.toggle();
		} );

		this.sheet = document.createElement( 'div' );
		this.sheet.className = 'wpfDrawer';
		this.sheet.setAttribute( 'role', 'dialog' );
		this.sheet.setAttribute( 'aria-modal', 'true' );
		this.sheet.setAttribute( 'aria-label', this.buttonText );
		this.sheet.hidden = true;
		this.sheet.innerHTML = '<div class="wpfDrawerBackdrop"></div><div class="wpfDrawerPanel"><div class="wpfDrawerHead"><span class="wpfDrawerHandle" aria-hidden="true"></span><h2 class="wpfDrawerTitle"></h2><button type="button" class="wpfDrawerClose" aria-label="Close">&times;</button></div><div class="wpfDrawerChips"></div><div class="wpfDrawerBody"></div><div class="wpfDrawerFoot"><button type="button" class="wpfDrawerShow"></button></div></div>';
		this.sheet.querySelector( '.wpfDrawerTitle' ).textContent = this.buttonText;
		this.sheet.querySelector( '.wpfDrawerBackdrop' ).addEventListener( 'click', function () {
			self.close();
		} );
		this.sheet.querySelector( '.wpfDrawerClose' ).addEventListener( 'click', function () {
			self.close();
		} );
		this.sheet.querySelector( '.wpfDrawerShow' ).addEventListener( 'click', function () {
			self.close();
		} );
		this.sheet.addEventListener( 'keydown', function ( e ) {
			self.onKey( e );
		} );
		this.body = this.sheet.querySelector( '.wpfDrawerBody' );
		this.chips = this.sheet.querySelector( '.wpfDrawerChips' );
		this.showBtn = this.sheet.querySelector( '.wpfDrawerShow' );
		if ( ! this.useResults ) {
			this.sheet.querySelector( '.wpfDrawerFoot' ).hidden = true;
		}
		if ( ! this.useChips ) {
			this.chips.hidden = true;
		}
		this.wrapper.addEventListener( 'change', function () {
			setTimeout( function () {
				self.refresh();
				self.remember();
			}, 30 );
		} );
	};

	Drawer.prototype.shouldMount = function () {
		return this.always || window.innerWidth < BREAKPOINT;
	};

	Drawer.prototype.update = function () {
		if ( this.shouldMount() && ! this.mounted ) {
			this.mount();
		} else if ( ! this.shouldMount() && this.mounted ) {
			this.unmount();
		}
	};

	Drawer.prototype.mount = function () {
		var parent = this.wrapper.parentNode;
		if ( ! parent ) {
			return;
		}
		parent.insertBefore( this.placeholder, this.wrapper );
		this.body.appendChild( this.wrapper );
		document.body.appendChild( this.sheet );
		document.body.appendChild( this.button );
		document.body.classList.add( 'wpfHasDrawer' );
		this.mounted = true;
		this.refresh();
		this.offerLast();
	};

	Drawer.prototype.unmount = function () {
		if ( this.open ) {
			this.close( true );
		}
		if ( this.placeholder.parentNode ) {
			this.placeholder.parentNode.insertBefore( this.wrapper, this.placeholder );
			this.placeholder.parentNode.removeChild( this.placeholder );
		}
		if ( this.sheet.parentNode ) {
			this.sheet.parentNode.removeChild( this.sheet );
		}
		if ( this.button.parentNode ) {
			this.button.parentNode.removeChild( this.button );
		}
		document.body.classList.remove( 'wpfHasDrawer' );
		this.mounted = false;
	};

	Drawer.prototype.refresh = function () {
		var active = activeChoices( this.wrapper ), n = resultCount( this.settings ), self = this;
		this.button.querySelector( '.wpfDrawerButtonText' ).textContent = this.buttonText;
		var count = this.button.querySelector( '.wpfDrawerCount' );
		count.textContent = active.length ? '(' + active.length + ')' : '';
		count.hidden = ! active.length;
		this.button.setAttribute( 'aria-label', this.buttonText + ( active.length ? ' (' + active.length + ')' : '' ) );
		this.showBtn.textContent = n === 1 ? 'Show 1 result' : 'Show ' + n.toLocaleString() + ' results';
		if ( this.useChips ) {
			this.chips.innerHTML = '';
			active.forEach( function ( a ) {
				var chip = document.createElement( 'button' );
				chip.type = 'button';
				chip.className = 'wpfDrawerChip';
				chip.setAttribute( 'aria-label', 'Remove ' + a.name );
				chip.innerHTML = '<span></span><i aria-hidden="true">&times;</i>';
				chip.firstChild.textContent = a.name;
				chip.addEventListener( 'click', function () {
					if ( a.option ) {
						a.option.selected = false;
					} else {
						a.input.checked = false;
					}
					fireChange( a.input );
					self.refresh();
				} );
				self.chips.appendChild( chip );
			} );
		}
	};

	Drawer.prototype.remember = function () {
		try {
			var names = activeChoices( this.wrapper ).map( function ( a ) {
				return a.input.name + '=' + ( a.option ? a.option.value : a.input.value );
			} );
			if ( names.length ) {
				window.localStorage.setItem( storageKey( this.id ), JSON.stringify( names ) );
			}
		} catch ( e ) {}
	};

	Drawer.prototype.offerLast = function () {
		var self = this, saved;
		try {
			saved = JSON.parse( window.localStorage.getItem( storageKey( this.id ) ) || 'null' );
		} catch ( e ) {
			saved = null;
		}
		if ( ! saved || ! saved.length || activeChoices( this.wrapper ).length || this.chips.querySelector( '.wpfDrawerLast' ) ) {
			return;
		}
		var offer = document.createElement( 'button' );
		offer.type = 'button';
		offer.className = 'wpfDrawerChip wpfDrawerLast';
		offer.textContent = 'Use your last filters again?';
		offer.addEventListener( 'click', function () {
			var changed = null;
			saved.forEach( function ( pair ) {
				var i = pair.indexOf( '=' ), name = pair.slice( 0, i ), value = pair.slice( i + 1 ),
					inputs = self.wrapper.querySelectorAll( '[name="' + name.replace( /"/g, '\\"' ) + '"]' );
				Array.prototype.forEach.call( inputs, function ( inp ) {
					if ( inp.tagName === 'SELECT' ) {
						Array.prototype.forEach.call( inp.options, function ( o ) {
							if ( o.value === value ) {
								o.selected = true;
								changed = inp;
							}
						} );
					} else if ( inp.value === value ) {
						inp.checked = true;
						changed = inp;
					}
				} );
			} );
			offer.remove();
			if ( changed ) {
				fireChange( changed );
			}
			self.refresh();
		} );
		this.chips.hidden = false;
		this.chips.insertBefore( offer, this.chips.firstChild );
	};

	Drawer.prototype.toggle = function () {
		if ( this.open ) {
			this.close();
		} else {
			this.show();
		}
	};

	Drawer.prototype.show = function () {
		this.lastFocus = document.activeElement;
		this.sheet.hidden = false;
		this.open = true;
		this.button.setAttribute( 'aria-expanded', 'true' );
		document.body.classList.add( 'wpfDrawerOpen' );
		this.refresh();
		var self = this;
		requestAnimationFrame( function () {
			self.sheet.classList.add( 'is-open' );
			var first = self.sheet.querySelector( '.wpfDrawerClose' );
			if ( first ) {
				first.focus();
			}
		} );
	};

	Drawer.prototype.close = function ( silent ) {
		var self = this;
		this.open = false;
		this.button.setAttribute( 'aria-expanded', 'false' );
		this.sheet.classList.remove( 'is-open' );
		document.body.classList.remove( 'wpfDrawerOpen' );
		var done = function () {
			self.sheet.hidden = true;
		};
		if ( silent || window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			done();
		} else {
			setTimeout( done, 360 );
		}
		if ( this.lastFocus && this.lastFocus.focus ) {
			this.lastFocus.focus();
		} else {
			this.button.focus();
		}
	};

	Drawer.prototype.onKey = function ( e ) {
		if ( e.key === 'Escape' || e.key === 'Esc' ) {
			e.preventDefault();
			this.close();
			return;
		}
		if ( e.key !== 'Tab' ) {
			return;
		}
		var focusable = Array.prototype.filter.call( this.sheet.querySelectorAll( 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])' ), function ( el ) {
			return ! el.disabled && el.offsetParent !== null;
		} );
		if ( ! focusable.length ) {
			return;
		}
		var first = focusable[ 0 ], last = focusable[ focusable.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	};

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '.wpfMainWrapper' ), function ( wrapper ) {
			if ( wrapper.getAttribute( 'data-wpf-drawer' ) === '1' ) {
				return;
			}
			var settings = readSettings( wrapper );
			if ( settings.mobile_drawer !== '1' && settings.mobile_drawer !== 1 ) {
				return;
			}
			wrapper.setAttribute( 'data-wpf-drawer', '1' );
			drawers.push( new Drawer( wrapper ) );
		} );
	}

	function afterUpdate() {
		var n = 0;
		drawers.forEach( function ( d ) {
			d.refresh();
			n = resultCount( d.settings );
			// Focus returns to the control that changed when the plugin's own JS does not handle it.
			if ( d.lastChanged && document.body.contains( d.lastChanged ) && document.activeElement === document.body ) {
				d.lastChanged.focus();
			}
		} );
		if ( drawers.length ) {
			announce( n );
		}
	}

	var resizeTimer = null;
	window.addEventListener( 'resize', function () {
		clearTimeout( resizeTimer );
		resizeTimer = setTimeout( function () {
			drawers.forEach( function ( d ) {
				d.update();
			} );
		}, 150 );
	} );

	// The plugin's frontend JS fires these on the document around each AJAX update.
	document.addEventListener( 'wpfAjaxSuccess', afterUpdate );
	document.addEventListener( 'change', function ( e ) {
		drawers.forEach( function ( d ) {
			if ( d.wrapper.contains( e.target ) ) {
				d.lastChanged = e.target;
			}
		} );
	}, true );

	// Fallback for setups where the event does not fire: watch the products container.
	function observe() {
		if ( ! window.MutationObserver || ! drawers.length ) {
			return;
		}
		var c = productsContainer( drawers[ 0 ].settings );
		if ( ! c || ! c.parentNode ) {
			return;
		}
		var t = null, mo = new MutationObserver( function () {
			clearTimeout( t );
			t = setTimeout( afterUpdate, 200 );
		} );
		mo.observe( c.parentNode, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init();
			observe();
		} );
	} else {
		init();
		observe();
	}
	// Filters rendered late (AJAX, builders) get picked up too.
	document.addEventListener( 'wpfAjaxSuccess', init );
} )();
