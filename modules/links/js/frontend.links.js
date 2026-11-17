/**
 * Product Filter by WBW - storefront links (4.0)
 *
 * 1. Keeps the address bar pretty after each AJAX update when "Clean links" is on
 *    (/shop/size-42,43/colour-red/price-50-120/).
 * 2. Share button: asks REST wpf/v1/short-links for a short link, copies it and shows a QR code.
 *
 * Listens to the 4.0 engine events (wpf:results) and the 3.x event (wpfAjaxSuccess).
 */
( function () {
	'use strict';
	if ( typeof window.wpfLinks === 'undefined' ) {
		return;
	}
	var L = window.wpfLinks;
	L.clean = L.clean === true || L.clean === 1 || L.clean === '1'; // wp_localize_script turns 0 into the string "0", which is truthy
	var FIXED = [ 'category', 'tag', 'brand', 'author', 'price', 'stock', 'sale', 'featured', 'rating', 'sort', 'page', 'q' ];

	function termSlug( key, value ) {
		var v = String( value ).replace( /"/g, '' );
		if ( ! /^\d+$/.test( v ) ) {
			return value;
		}
		// Numeric values are ids for categories, but attribute slugs such as "42" are numeric too:
		// a control carrying that slug wins, then a control carrying that id.
		if ( document.querySelector( '[data-term-slug="' + v + '"]' ) ) {
			return v;
		}
		var el = document.querySelector( '[data-term-id="' + v + '"][data-term-slug], option[value="' + v + '"][data-term-slug]' );
		if ( el ) {
			return el.getAttribute( 'data-term-slug' );
		}
		return key === 'category' || key === 'tag' || key === 'brand' ? null : value;
	}

	function splitList( v ) {
		return String( v ).replace( /^!/, '' ).split( /[|,]/ ).filter( Boolean );
	}

	/** Query string (legacy or canonical) => canonical params, or null when a term id cannot be resolved. */
	function canonicalFromSearch( search ) {
		var params = {}, ok = true, rest = [];
		var qs = new URLSearchParams( search );
		// 3.x engine internals: dropped from the address. Anything else this file does not turn into a path segment
		// (a custom field text box, a second search box, the builder preview, other plugins' arguments) stays in the query.
		var ignore = [ 'wpf_count', 'wpf_fbv', 'wpf_dpv', 'wpf_skip', 'wpf_fid', 'wpf_id', 'wpf_ebv', 'wpf_oistock', 'wpf_reload', 'wpf_preselects', 'wpf_filter_tax_block_logic', 'wpf_order', '_' ];
		var mapSlugs = function ( key, list ) {
			var out = [];
			list.forEach( function ( v ) {
				var s = termSlug( key, v );
				if ( s === null ) {
					ok = false;
				} else {
					out.push( s );
				}
			} );
			return out;
		};
		qs.forEach( function ( value, key ) {
			if ( ! value || ignore.indexOf( key ) !== -1 ) {
				return;
			}
			var list = splitList( value ), m, before = JSON.stringify( params );
			if ( key.indexOf( 'wpf_filter_cat' ) === 0 || key === 'filter_cat' ) {
				params.category = mapSlugs( 'category', list );
			} else if ( key.indexOf( 'product_tag' ) === 0 ) {
				params.tag = mapSlugs( 'tag', list );
			} else if ( key.indexOf( 'product_brand' ) === 0 || key.indexOf( 'wpf_filter_brand' ) === 0 || key.indexOf( 'wpf_filter_pwb' ) === 0 ) {
				params.brand = mapSlugs( 'brand', list );
			} else if ( key === 'wpf_min_price' || key === 'wpf_max_price' ) {
				var cur = ( params.price || '-' ).split( '-' );
				if ( key === 'wpf_min_price' ) {
					cur[ 0 ] = value;
				} else {
					cur[ 1 ] = value;
				}
				params.price = cur[ 0 ] + '-' + cur[ 1 ];
			} else if ( key === 'pr_stock' ) {
				var sm = { instock: 'in', outofstock: 'out', onbackorder: 'backorder' }, s = [];
				list.forEach( function ( x ) {
					if ( sm[ x ] ) {
						s.push( sm[ x ] );
					}
				} );
				if ( s.length ) {
					params.stock = s.join( ',' );
				}
			} else if ( key === 'pr_onsale' ) {
				params.sale = '1';
			} else if ( key === 'pr_featured' ) {
				params.featured = '1';
			} else if ( key === 'pr_rating' ) {
				params.rating = String( parseInt( value, 10 ) );
			} else if ( key === 'pr_author' ) {
				params.author = list;
			} else if ( key === 'orderby' || key === 'wpf_sort' ) {
				params.sort = value;
			} else if ( key === 'product-page' || key === 'paged' || key === 'wpf_page' ) {
				if ( parseInt( value, 10 ) > 1 ) {
					params.page = String( parseInt( value, 10 ) );
				}
			} else if ( key === 's' || key === 'wpf_q' || key === 'wpf_filter_text' ) {
				params.q = value;
			} else if ( ( m = key.match( /^(?:pr_)?wpf_filter_(.+?)(?:_\d+)?$/ ) ) && L.attributes.indexOf( m[ 1 ] ) !== -1 ) {
				params[ m[ 1 ] ] = mapSlugs( m[ 1 ], list );
			} else if ( key.indexOf( 'wpf_' ) === 0 ) {
				var k = key.substr( 4 );
				if ( FIXED.indexOf( k ) !== -1 && [ 'category', 'tag', 'brand', 'author' ].indexOf( k ) === -1 ) {
					params[ k ] = value;
				} else if ( L.metaKeys.indexOf( k ) !== -1 ) {
					params[ k ] = value;
				} else if ( L.attributes.indexOf( k ) !== -1 || [ 'category', 'tag', 'brand', 'author' ].indexOf( k ) !== -1 ) {
					params[ k ] = list;
				}
			}
			if ( JSON.stringify( params ) === before && ! L.segments[ key.replace( /^wpf_/, '' ) ] ) {
				rest.push( [ key, value ] );
			}
		} );
		if ( ok && rest.length ) {
			Object.defineProperty( params, '__rest', { value: rest, enumerable: false } );
		}
		return ok ? params : null;
	}

	function prettyUrl( base, params ) {
		var parts = [], keys = Object.keys( params ).filter( function ( k ) {
			return FIXED.indexOf( k ) === -1;
		} );
		FIXED.forEach( function ( k ) {
			if ( params[ k ] !== undefined ) {
				keys.push( k );
			}
		} );
		keys.forEach( function ( k ) {
			var seg = L.segments[ k ], v = params[ k ];
			if ( ! seg || v === undefined || v === null || v === '' || ( Array.isArray( v ) && ! v.length ) ) {
				return;
			}
			if ( Array.isArray( v ) ) {
				v = v.map( encodeURIComponent ).join( ',' );
			} else if ( k === 'q' ) {
				v = encodeURIComponent( String( v ).replace( / /g, '+' ) );
			} else {
				v = encodeURIComponent( String( v ) );
			}
			parts.push( seg + '-' + v );
		} );
		var url = base.replace( /\/?$/, '/' );
		if ( parts.length ) {
			url += parts.join( '/' ) + '/';
		}
		if ( params.__rest && params.__rest.length ) {
			url += '?' + params.__rest.map( function ( kv ) {
				return encodeURIComponent( kv[ 0 ] ) + '=' + encodeURIComponent( kv[ 1 ] ).replace( /%2C/g, ',' );
			} ).join( '&' );
		}
		return url;
	}

	/** Pretty address for the current page state (null when it cannot be built). */
	function currentPretty() {
		var loc = window.location;
		var params = canonicalFromSearch( loc.search );
		// Only archives (shop, product taxonomies: L.base is set) have pretty path rewrite rules. On any other page
		// (a [wpf-filters] page, a page builder page) /page/size-42/ would 404 on reload, so keep the query string.
		if ( ! params || ! L.base ) {
			return null;
		}
		// Segments already in the path (page loaded from a pretty URL) win unless the query says otherwise.
		var segs = loc.pathname.split( '/' ).filter( Boolean ), basePath = [];
		var words = Object.keys( L.segments ).map( function ( k ) {
			return L.segments[ k ];
		} );
		segs.forEach( function ( seg ) {
			var hit = null;
			words.forEach( function ( w ) {
				if ( seg.indexOf( w + '-' ) === 0 && ( ! hit || w.length > hit.length ) ) {
					hit = w;
				}
			} );
			if ( ! hit ) {
				basePath.push( seg );
			} else if ( loc.search.length < 2 ) {
				var key = Object.keys( L.segments ).filter( function ( k ) {
					return L.segments[ k ] === hit;
				} )[ 0 ];
				var val = decodeURIComponent( seg.substr( hit.length + 1 ) );
				params[ key ] = ( FIXED.indexOf( key ) !== -1 && [ 'category', 'tag', 'brand', 'author' ].indexOf( key ) === -1 ) ? val : val.split( ',' );
			}
		} );
		var base = L.base || ( loc.origin + '/' + basePath.join( '/' ) + '/' );
		return prettyUrl( base, params );
	}

	function replaceAddress() {
		if ( ! L.clean || ! window.history || ! window.history.replaceState ) {
			return;
		}
		// A classic (3.x engine) filter on the page builds its URLs from the query string of the current address:
		// moving its picks into the path breaks "Clear" and reloads. Clean links apply to 4.0 filters only.
		if ( document.querySelector( '.wpfMainWrapper' ) ) {
			return;
		}
		var url = currentPretty();
		if ( url && url !== window.location.href ) {
			try {
				window.history.replaceState( window.history.state, '', url );
			} catch ( e ) {
				/* cross origin or malformed: keep the address as is */
			}
		}
	}
	document.addEventListener( 'wpf:results', replaceAddress );
	document.addEventListener( 'wpfAjaxSuccess', replaceAddress );
	// The classic frontend writes its own query string URL after its success event; tidy up after any pushState/replaceState too.
	if ( L.clean && window.history && window.history.pushState ) {
		var tidying = false, wrap = function ( name ) {
			var orig = window.history[ name ];
			window.history[ name ] = function () {
				var r = orig.apply( this, arguments );
				if ( ! tidying ) {
					tidying = true;
					window.setTimeout( function () {
						try {
							replaceAddress();
						} finally {
							tidying = false;
						}
					}, 0 );
				}
				return r;
			};
		};
		wrap( 'pushState' );
		wrap( 'replaceState' );
	}
	if ( L.clean && window.location.search.length > 1 ) {
		replaceAddress();
	}

	/* ---------------------------------------------------------------- share */

	function setLive( box, text ) {
		var live = box.querySelector( '.wpf-share__live' );
		if ( live ) {
			live.textContent = text;
		}
	}

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.setAttribute( 'readonly', '' );
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.select();
			var ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch ( e ) {
				ok = false;
			}
			document.body.removeChild( ta );
			ok ? resolve() : reject();
		} );
	}

	function openShare( box ) {
		var btn = box.querySelector( '.wpf-share__btn' ), pop = box.querySelector( '.wpf-share__pop' ), input = box.querySelector( '.wpf-share__url' ), qr = box.querySelector( '.wpf-share__qr' );
		pop.hidden = false;
		btn.setAttribute( 'aria-expanded', 'true' );
		setLive( box, L.i18n.working );
		var url = currentPretty() || window.location.href;
		fetch( L.rest, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': L.nonce },
			body: JSON.stringify( { url: url, filter_id: parseInt( box.getAttribute( 'data-filter' ) || '0', 10 ) } )
		} ).then( function ( r ) {
			return r.json().then( function ( data ) {
				if ( ! r.ok ) {
					throw new Error( data && data.message ? data.message : L.i18n.failed );
				}
				return data;
			} );
		} ).then( function ( data ) {
			input.value = data.short_url;
			if ( data.qr && data.qr.indexOf( '<svg' ) === 0 ) {
				qr.innerHTML = data.qr;
				qr.setAttribute( 'aria-hidden', 'false' );
				qr.setAttribute( 'role', 'img' );
				qr.setAttribute( 'aria-label', L.i18n.qr );
			}
			return copyText( data.short_url ).then( function () {
				setLive( box, L.i18n.copied );
			}, function () {
				setLive( box, '' );
				input.focus();
				input.select();
			} );
		} ).catch( function ( e ) {
			setLive( box, e && e.message ? e.message : L.i18n.failed );
		} );
	}

	function closeShare( box ) {
		var btn = box.querySelector( '.wpf-share__btn' ), pop = box.querySelector( '.wpf-share__pop' );
		pop.hidden = true;
		btn.setAttribute( 'aria-expanded', 'false' );
		btn.focus();
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target.closest ? e.target.closest( '.wpf-share__btn, .wpf-share__copy, .wpf-share__close' ) : null;
		if ( ! t ) {
			document.querySelectorAll( '.wpf-share__pop:not([hidden])' ).forEach( function ( pop ) {
				if ( ! pop.parentNode.contains( e.target ) ) {
					closeShare( pop.parentNode );
				}
			} );
			return;
		}
		var box = t.closest( '.wpf-share' );
		if ( t.classList.contains( 'wpf-share__btn' ) ) {
			if ( box.querySelector( '.wpf-share__pop' ).hidden ) {
				openShare( box );
			} else {
				closeShare( box );
			}
		} else if ( t.classList.contains( 'wpf-share__copy' ) ) {
			var v = box.querySelector( '.wpf-share__url' ).value;
			if ( v ) {
				copyText( v ).then( function () {
					setLive( box, L.i18n.copied );
				} );
			}
		} else {
			closeShare( box );
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			document.querySelectorAll( '.wpf-share__pop:not([hidden])' ).forEach( function ( pop ) {
				closeShare( pop.parentNode );
			} );
		}
	} );

	window.wpfLinksApi = { prettyUrl: prettyUrl, canonicalFromSearch: canonicalFromSearch, currentPretty: currentPretty };
} )();
