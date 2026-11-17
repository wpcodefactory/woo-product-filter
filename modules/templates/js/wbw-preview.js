/**
 * Product Filter by WBW - Preview pane helper
 *
 * Shows the real shop page inside an iframe at a real width (desktop 1024px, phone 390px).
 *
 * Desktop: the pane zooms in on the filter (close-up), so its text is readable: the filter
 * column plus about one and a half product cards when the filter sits beside the products,
 * or the whole filter bar when it sits above them. The page itself keeps its desktop layout;
 * only the view is zoomed and moved. When no filter is found the whole page width is shown.
 *
 * window.wbwPreview( frameWrap, iframe ) returns { setDevice( 'desktop'|'phone' ), fit(), focus( scrollToFilter ), swap( html ), live( url, draft ),
 * missing(), inject( html, css ) }.
 *
 * missing() / inject( html, css ): some themes build the shop page from their own template, without the WooCommerce
 * hooks the draft is printed on, so the page loads without the draft (and may show a published filter instead).
 * missing() says so once the page loaded with ?wpf_preview; inject() then puts the draft HTML (from
 * woobewoo_pf_setup_preview_html) in place of the filter the page shows, or before the product grid, adds the
 * storefront stylesheet when the page has none and lets the storefront script start it (wpfPreviewUpdated).
 *
 * live( url, draft ): the draft was saved. Points the card's "View on my shop" link ([data-wbw-preview-open]) at
 * the new preview URL and tells any shop tab that link opened (same draft) to reload, so a second tab follows
 * every change. Sent on a BroadcastChannel and, for older browsers, as a localStorage event.
 *
 * When the scroll puts the filter at the top, a sticky or fixed site header would cover it: the scroll stops
 * below the header.
 *
 * swap( html ) puts fresh draft HTML (from woobewoo_pf_setup_preview_html) in place of the draft
 * on the page. It keeps the placement wrapper (.wpfAutoPlace) the page already has, because the
 * storefront script sizes that wrapper (sidebar column width) once, at page load: replacing the
 * wrapper itself made the filter column grow across the products. Returns false when the page
 * needs a reload instead (no draft on the page, or the placement changed).
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';
	window.wbwPreview = function ( wrap, frame ) {
		var device = 'desktop', DESKTOP_W = 1024, PHONE_W = 390, PHONE_H = 758;
		var MAX_ZOOM = 1.6, PAD = 20, CARDS = 1.5;
		var view = { scale: 1, x: 0 };
		var card = wrap.closest ? wrap.closest( '.wbw-preview' ) : null;
		var openLinks = card ? card.querySelectorAll( '[data-wbw-preview-open]' ) : [];
		var channel = null;

		function doc() {
			try {
				return frame.contentDocument;
			} catch ( e ) {
				return null;
			}
		}
		/** Pane height: data-height (default 420) at least, taller on tall screens so more of the filter shows. */
		function paneHeight() {
			var base = parseInt( wrap.getAttribute( 'data-height' ) || '420', 10 );
			return Math.round( Math.max( base, Math.min( 720, window.innerHeight - 240 ) ) );
		}
		function innerWidth() {
			return Math.max( 200, wrap.clientWidth - 32 );
		}
		function apply() {
			var h = paneHeight();
			frame.style.width = DESKTOP_W + 'px';
			frame.style.height = Math.round( h / view.scale ) + 'px';
			frame.style.transformOrigin = '0 0';
			frame.style.transform = 'translate(' + ( -Math.round( view.x * view.scale ) ) + 'px,0) scale(' + view.scale + ')';
			wrap.style.height = ( h + 32 ) + 'px';
		}

		/**
		 * The part of the page to show, in page pixels: { left, right, top } or null.
		 */
		function region() {
			var d = doc();
			if ( ! d || ! d.body ) {
				return null;
			}
			var win = d.defaultView, sx = win.pageXOffset || 0, sy = win.pageYOffset || 0;
			var draft = d.querySelector( '.wpfPreviewDraft' );
			var target = draft ? ( draft.closest( '.wpfAutoPlace' ) || draft ) : d.querySelector( '[data-wpf4]' );
			if ( ! target ) {
				return null;
			}
			var fr = target.getBoundingClientRect();
			if ( fr.width < 1 || fr.height < 1 ) {
				return null; // Drawer only, or hidden on this screen.
			}
			var grid = null, list = d.querySelectorAll( 'ul.products, .wc-block-product-template, .products' );
			for ( var i = 0; i < list.length; i++ ) {
				if ( ! list[ i ].closest( 'nav, header, footer, .wpfAutoPlace' ) && list[ i ].getBoundingClientRect().width > 100 ) {
					grid = list[ i ];
					break;
				}
			}
			var left = fr.left + sx, right = fr.right + sx, top = fr.top + sy;
			if ( grid ) {
				var gr = grid.getBoundingClientRect();
				var card = grid.querySelector( 'li.product, .wc-block-product, .product' );
				var cw = card ? card.getBoundingClientRect().width : gr.width / 4;
				if ( fr.right <= gr.left + 8 && cw > 0 ) {
					// Filter beside the products: the filter column and one and a half cards.
					right = Math.min( gr.right, gr.left + cw * CARDS + 16 ) + sx;
					top = Math.min( fr.top, gr.top ) + sy;
				} else if ( fr.left >= gr.right - 8 && cw > 0 ) {
					// Filter on the right of the products.
					left = Math.max( gr.left, gr.right - cw * CARDS - 16 ) + sx;
				}
			}
			left = Math.max( 0, left - PAD );
			right = Math.min( DESKTOP_W, right + PAD );
			if ( right - left < 240 ) {
				return null;
			}
			return { left: left, right: right, top: Math.max( 0, top - PAD ), target: target };
		}

		/**
		 * Bottom edge (viewport pixels) of a sticky or fixed bar at the top of the page, such as a site header,
		 * or 0. The filter itself (a sticky sidebar) does not count.
		 */
		function stuckTop( d, target ) {
			var win = d.defaultView, max = 0;
			if ( ! d.elementsFromPoint ) {
				return 0;
			}
			[ 0.2, 0.5, 0.8 ].forEach( function ( fx ) {
				var list = d.elementsFromPoint( Math.round( ( win.innerWidth || DESKTOP_W ) * fx ), 2 );
				for ( var i = 0; i < list.length; i++ ) {
					for ( var n = list[ i ]; n && n !== d.body && n !== d.documentElement; n = n.parentElement ) {
						if ( target && ( n.contains( target ) || target.contains( n ) ) ) {
							break;
						}
						var pos = win.getComputedStyle( n ).position;
						if ( pos === 'fixed' || pos === 'sticky' ) {
							var r = n.getBoundingClientRect();
							if ( r.top <= 4 && r.bottom > 0 && r.height < ( win.innerHeight || 800 ) / 2 ) {
								max = Math.max( max, r.bottom );
							}
							break;
						}
					}
				}
			} );
			return Math.round( max );
		}

		/**
		 * Zoom on the filter. scrollToFilter: also scroll the page so the filter is at the top
		 * (on load); later updates keep the shopper's scroll position.
		 */
		function focus( scrollToFilter ) {
			if ( device !== 'desktop' ) {
				return;
			}
			var fitScale = Math.min( 1, innerWidth() / DESKTOP_W ), r = region();
			if ( ! r ) {
				view.scale = fitScale;
				view.x = 0;
				apply();
				return;
			}
			var scale = Math.min( MAX_ZOOM, Math.max( fitScale, innerWidth() / ( r.right - r.left ) ) );
			// Never leave empty space on the right: the shown slice must end inside the page.
			var x = Math.min( r.left, Math.max( 0, DESKTOP_W - innerWidth() / scale ) );
			view.scale = scale;
			view.x = x;
			apply();
			if ( scrollToFilter ) {
				var d = doc();
				if ( d && d.defaultView ) {
					d.defaultView.scrollTo( 0, r.top );
					var off = stuckTop( d, r.target );
					if ( off > 0 ) {
						d.defaultView.scrollTo( 0, Math.max( 0, r.top - off - 8 ) );
					}
				}
			}
		}

		function fit() {
			if ( device === 'phone' ) {
				var screen = frame.parentNode;
				if ( ! screen ) {
					return;
				}
				var s = screen.clientWidth / PHONE_W;
				frame.style.width = PHONE_W + 'px';
				frame.style.height = PHONE_H + 'px';
				frame.style.transform = 'scale(' + s + ')';
				frame.style.transformOrigin = '0 0';
				return;
			}
			focus( false );
		}

		function setDevice( d ) {
			device = d;
			if ( d === 'phone' ) {
				if ( ! wrap.classList.contains( 'is-phone' ) ) {
					wrap.classList.add( 'is-phone' );
					wrap.style.height = '';
					var bezel = document.createElement( 'div' );
					bezel.className = 'wbw-preview__phone';
					bezel.innerHTML = '<div class="wbw-preview__phone-screen"></div>';
					bezel.querySelector( '.wbw-preview__phone-screen' ).appendChild( frame );
					wrap.insertBefore( bezel, wrap.firstChild );
				}
			} else if ( wrap.classList.contains( 'is-phone' ) ) {
				wrap.classList.remove( 'is-phone' );
				var old = wrap.querySelector( '.wbw-preview__phone' );
				wrap.insertBefore( frame, wrap.firstChild );
				if ( old ) {
					old.remove();
				}
			}
			fit();
		}

		function placeOf( el ) {
			var m = el && el.className ? String( el.className ).match( /wpfAutoPlace-(sidebar|above|drawer)/ ) : null;
			return m ? m[ 1 ] : '';
		}

		function swap( html ) {
			var d = doc();
			var current = d && d.querySelector( '.wpfPreviewDraft' );
			if ( ! current || ! html ) {
				return false;
			}
			var tmp = d.createElement( 'div' );
			tmp.innerHTML = html;
			var freshWrap = tmp.firstElementChild;
			var freshDraft = freshWrap && ( freshWrap.classList.contains( 'wpfPreviewDraft' ) ? freshWrap : freshWrap.querySelector( '.wpfPreviewDraft' ) );
			var wrapEl = current.closest( '.wpfAutoPlace' );
			if ( ! freshDraft ) {
				return false;
			}
			if ( wrapEl && placeOf( wrapEl ) !== placeOf( freshWrap ) ) {
				return false; // The placement changed: the page layout must be rebuilt.
			}
			var keys = blockKeys( current );
			current.parentNode.replaceChild( freshDraft, current );
			try {
				d.dispatchEvent( new d.defaultView.CustomEvent( 'wpfPreviewUpdated' ) );
			} catch ( e ) {}
			// Let the storefront script draw the new blocks, then zoom on the new size and show the block
			// that was just added (it may sit below the visible part of the close-up).
			setTimeout( function () {
				focus( false );
				var added = null, list = freshDraft.querySelectorAll( '[data-wpf4-block]' );
				for ( var i = 0; i < list.length && ! added; i++ ) {
					if ( keys.indexOf( list[ i ].getAttribute( 'data-key' ) ) === -1 && list[ i ].getBoundingClientRect().height > 0 ) {
						added = list[ i ];
					}
				}
				if ( added ) {
					reveal( added );
				}
			}, 80 );
			return true;
		}

		function blockKeys( root ) {
			var out = [], list = root.querySelectorAll( '[data-wpf4-block]' );
			for ( var i = 0; i < list.length; i++ ) {
				out.push( list[ i ].getAttribute( 'data-key' ) );
			}
			return out;
		}

		/** Scroll the page in the preview so the block is in view, and outline it for a moment. */
		function reveal( el ) {
			var d = doc();
			if ( ! d || ! d.defaultView || device !== 'desktop' ) {
				return;
			}
			var win = d.defaultView, r = el.getBoundingClientRect(), viewH = paneHeight() / view.scale, off = stuckTop( d, el );
			if ( r.top < off || r.bottom > viewH ) {
				win.scrollTo( 0, Math.max( 0, ( win.pageYOffset || 0 ) + r.top - off - 40 ) );
			}
			var old = el.style.boxShadow;
			el.style.transition = 'box-shadow .3s';
			el.style.boxShadow = '0 0 0 3px rgba(23,113,227,.55)';
			el.style.borderRadius = el.style.borderRadius || '10px';
			setTimeout( function () {
				el.style.boxShadow = old;
			}, 1400 );
		}

		frame.addEventListener( 'load', function () {
			var d = doc();
			if ( ! d ) {
				return;
			}
			focus( true );
			// Images, fonts and the storefront layout script can change sizes after load.
			setTimeout( function () {
				focus( true );
			}, 400 );
			if ( d.defaultView && d.defaultView.ResizeObserver && d.body ) {
				var t = null;
				new d.defaultView.ResizeObserver( function () {
					clearTimeout( t );
					t = setTimeout( function () {
						focus( false );
					}, 120 );
				} ).observe( d.body );
			}
		} );
		if ( window.ResizeObserver ) {
			new ResizeObserver( fit ).observe( wrap );
		}
		window.addEventListener( 'resize', fit );
		fit();

		function draftOf( url ) {
			var m = String( url || '' ).match( /[?&]wpf_preview=([a-z0-9]+)/ );
			return m ? m[ 1 ] : '';
		}
		/** The draft was saved (or published): update the link and the shop tabs it opened. */
		function live( url, draft ) {
			if ( ! url ) {
				return;
			}
			for ( var i = 0; i < openLinks.length; i++ ) {
				openLinks[ i ].href = url;
				openLinks[ i ].removeAttribute( 'aria-disabled' );
			}
			var msg = { draft: draft || draftOf( url ), url: url, t: Date.now() };
			if ( ! msg.draft ) {
				return;
			}
			try {
				channel = channel || new BroadcastChannel( 'wpf-preview' );
				channel.postMessage( msg );
			} catch ( e ) {}
			try {
				window.localStorage.setItem( 'wpf-preview-live', JSON.stringify( msg ) );
			} catch ( e ) {}
		}
		/** The page was loaded as a draft preview but does not show the draft. */
		function missing() {
			var d = doc();
			if ( ! d || ! d.body || ! /[?&]wpf_preview=/.test( String( d.location && d.location.search ) ) ) {
				return false;
			}
			return ! d.querySelector( '.wpfPreviewDraft' );
		}
		function inject( html, css ) {
			var d = doc();
			if ( ! d || ! d.body || ! html ) {
				return false;
			}
			if ( d.querySelector( '.wpfPreviewDraft' ) ) {
				return swap( html );
			}
			var tmp = d.createElement( 'div' );
			tmp.innerHTML = html;
			var fresh = tmp.firstElementChild;
			if ( ! fresh ) {
				return false;
			}
			if ( css && ! d.getElementById( 'woobewoo-pf-frontend-4-css' ) ) {
				var link = d.createElement( 'link' );
				link.rel = 'stylesheet';
				link.id = 'woobewoo-pf-frontend-4-css';
				link.href = css;
				d.head.appendChild( link );
			}
			// A published filter the theme prints itself (shortcode, widget): the draft takes its place.
			var shown = d.querySelector( '[data-wpf4], .wpfMainWrapper' );
			shown = shown && ( shown.closest( '.wpfAutoPlace' ) || shown );
			if ( shown && shown.parentNode ) {
				shown.parentNode.replaceChild( fresh, shown );
			} else {
				var sel = [ 'ul.products', '.wc-block-product-template', '.products', 'main', '#main', '.site-main' ], loop = null;
				for ( var i = 0; i < sel.length && ! loop; i++ ) {
					var el = d.querySelector( sel[ i ] );
					if ( el && ! el.closest( 'header, nav, footer' ) ) {
						loop = el;
					}
				}
				if ( loop && loop.matches( 'main, #main, .site-main' ) ) {
					loop.insertBefore( fresh, loop.firstChild );
				} else if ( loop && loop.parentNode ) {
					loop.parentNode.insertBefore( fresh, loop );
				} else {
					d.body.insertBefore( fresh, d.body.firstChild );
				}
			}
			try {
				d.dispatchEvent( new d.defaultView.CustomEvent( 'wpfPreviewUpdated' ) );
			} catch ( e ) {}
			setTimeout( function () {
				focus( true );
			}, 80 );
			return true;
		}
		return { setDevice: setDevice, fit: fit, focus: focus, swap: swap, live: live, missing: missing, inject: inject };
	};
} )();
