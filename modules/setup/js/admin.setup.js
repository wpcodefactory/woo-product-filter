/**
 * Product Filter by WBW - Smart setup (4.0)
 *
 * Vanilla JS. Seven steps, one question per screen, live preview of the draft on the
 * real shop page (iframe with ?wpf_preview=<draft>), desktop / phone toggle.
 * The markup of every step follows the design canvas (Welcome, WizardFilters, WizardLook,
 * WizardMobile, WizardPublish; Place and Speed and SEO use the same frame).
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';

	if ( typeof window.wpfSetupData === 'undefined' ) {
		return;
	}
	var D = window.wpfSetupData,
		root = document.getElementById( 'wpfSetup' ),
		pane = document.getElementById( 'wpfSetupStep' ),
		frame = document.getElementById( 'wpfSetupFrame' ),
		frameWrap = document.getElementById( 'wpfSetupFrameWrap' ),
		frameLoading = document.getElementById( 'wpfSetupFrameLoading' ),
		previewKicker = document.getElementById( 'wpfSetupPreviewKicker' ),
		previewNote = document.getElementById( 'wpfSetupPreviewNote' ),
		stepButtons = Array.prototype.slice.call( document.querySelectorAll( '#wpfSetupSteps .wbw-step' ) ),
		deviceButtons = Array.prototype.slice.call( document.querySelectorAll( '#wpfSetupPreview .wbw-seg-ctl button' ) ),
		STEPS = [ 'welcome', 'filters', 'look', 'phone', 'place', 'speed', 'publish' ],
		NEXT_LABEL = {
			welcome: 'Use this filter',
			filters: 'Next: how should they look',
			look: 'Next: on a phone',
			phone: 'Next: where it goes',
			place: 'Next: speed and SEO',
			speed: 'Next: publish',
			publish: 'Publish to my shop'
		},
		state = {
			step: 'welcome',
			scan: D.scan || null,
			suggestions: D.suggestions || null,
			environment: D.environment || {},
			storeType: '',
			blocks: {},
			title: '',
			style: 'chips',
			colours: D.colours || {},
			phone: { drawer: 1, button_text: 'Filters', chips: 1, results_button: 1, remember: 1 },
			place: 'sidebar',
			selector: ( D.environment && D.environment.loop_selector ) || 'ul.products',
			display_on_page: 'both',
			speed: { wbw_cache_counts: 1, wbw_clean_links: 1, canonical_links: 1, wbw_bot_limit: 1, wbw_allow_ai_shoppers: 1 },
			draft: '',
			previewUrl: '',
			selectorTest: null,
			published: null,
			device: 'desktop',
			done: {}
		},
		previewTimer = null,
		draftSeq = 0;

	if ( ! root || ! pane ) {
		return;
	}

	/* ---------------------------------------------------------------- helpers */

	function esc( s ) {
		return String( s === null || typeof s === 'undefined' ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function num( n ) {
		return Number( n || 0 ).toLocaleString();
	}
	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'mod', 'setup' );
		body.append( 'pl', 'wpf' );
		body.append( 'reqType', 'ajax' );
		body.append( 'wpfNonce', D.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.text().then( function ( text ) {
				try {
					var json = JSON.parse( text );
					if ( json === 0 || json === -1 || json === null ) {
						throw new Error( 'notjson' );
					}
					return json;
				} catch ( e ) {
					// HTML instead of JSON (a PHP error page, a security plugin block, the login page…):
					// pass the status and the first 200 characters on, so the screen can show the reason.
					var err = new Error( 'notjson' );
					err.status = r.status;
					err.snippet = String( text || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 200 );
					throw err;
				}
			} );
		} );
	}
	function sprintf( s ) {
		var args = Array.prototype.slice.call( arguments, 1 ), i = 0;
		return String( s ).replace( /%(\d+\$)?s/g, function ( m, n ) {
			var idx = n ? parseInt( n, 10 ) - 1 : i++;
			return args[ idx ] === undefined ? '' : args[ idx ];
		} );
	}
	function icon( name, size ) {
		var paths = {
			check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
			calendar: '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>'
		};
		return '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true" style="width:' + ( size || 24 ) + 'px;height:' + ( size || 24 ) + 'px">' + ( paths[ name ] || '' ) + '</svg>';
	}
	function kicker( text ) {
		return '<span class="wbw-kicker">' + esc( text ) + '</span>';
	}
	function toggle( on, label, attrs ) {
		return '<button type="button" class="wbw-toggle" role="switch" aria-checked="' + ( on ? 'true' : 'false' ) + '" aria-label="' + esc( label ) + '" ' + ( attrs || '' ) + '></button>';
	}
	function tag( text, tone ) {
		return '<span class="wbw-tag' + ( tone ? ' wbw-tag--' + tone : '' ) + '">' + esc( text ) + '</span>';
	}
	function buttons( step, extra ) {
		var i = STEPS.indexOf( step ), html = '<div class="wbw-actions wbw-actions--pad">';
		html += '<button type="button" class="wbw-btn wbw-btn--primary" data-go="next">' + esc( NEXT_LABEL[ step ] ) + '</button>';
		if ( i > 0 ) {
			html += '<button type="button" class="wbw-btn" data-go="back">Back</button>';
		} else {
			html += '<a class="wbw-btn" href="' + esc( D.filtersUrl + '#new' ) + '">Build my own</a>';
		}
		html += extra || '';
		html += '</div>';
		return html;
	}
	function storeLabel() {
		if ( state.storeType && D.types && D.types[ state.storeType ] ) {
			return D.types[ state.storeType ];
		}
		return state.scan && state.scan.store_type ? state.scan.store_type.label : 'general';
	}
	function themeName() {
		return ( state.environment.theme && state.environment.theme.name ) || 'your theme';
	}
	function builderNames() {
		var names = { elementor: 'Elementor', divi: 'Divi', bricks: 'Bricks', breakdance: 'Breakdance', oxygen: 'Oxygen', wpbakery: 'WPBakery', avada: 'Avada', beaver: 'Beaver Builder' }, out = [];
		Object.keys( state.environment.builders || {} ).forEach( function ( k ) {
			if ( state.environment.builders[ k ] ) {
				out.push( names[ k ] || k );
			}
		} );
		return out;
	}
	function enabledItems() {
		return ( state.suggestions ? state.suggestions.items : [] ).filter( function ( it ) {
			return state.blocks[ it.key ];
		} );
	}
	function setPane( html ) {
		pane.innerHTML = html;
		var h = pane.querySelector( 'h1' );
		if ( h ) {
			h.setAttribute( 'tabindex', '-1' );
			h.focus( { preventScroll: true } );
		}
		var aside = document.getElementById( 'wpfPublishAside' );
		if ( aside ) {
			aside.hidden = state.step !== 'publish';
		}
	}

	/* ---------------------------------------------------------------- step pills */

	function paintSteps() {
		var cur = STEPS.indexOf( state.step );
		stepButtons.forEach( function ( b ) {
			var key = b.getAttribute( 'data-step' ), i = STEPS.indexOf( key ), done = state.done[ key ] || i < cur;
			b.setAttribute( 'aria-current', key === state.step ? 'step' : 'false' );
			if ( key !== state.step ) {
				b.removeAttribute( 'aria-current' );
			}
			if ( done && key !== state.step ) {
				b.innerHTML = icon( 'check', 14 ) + esc( b.getAttribute( 'data-label' ) );
			} else {
				b.textContent = b.getAttribute( 'data-n' ) + ' ' + b.getAttribute( 'data-label' );
			}
			b.disabled = ! state.scan;
		} );
		// The connectors between the pills: ink up to the current step, hairline after it.
		Array.prototype.forEach.call( document.querySelectorAll( '#wpfSetupSteps .wbw-join' ), function ( j, i ) {
			j.classList.toggle( 'done', i < cur );
		} );
		root.setAttribute( 'data-step', state.step );
	}
	stepButtons.forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			go( b.getAttribute( 'data-step' ) );
		} );
	} );

	/* ---------------------------------------------------------------- preview */

	var previewCtl = window.wbwPreview ? window.wbwPreview( frameWrap, frame ) : null;
	function setDevice( device, silent ) {
		state.device = device;
		deviceButtons.forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', b.getAttribute( 'data-device' ) === device ? 'true' : 'false' );
		} );
		if ( previewCtl ) {
			previewCtl.setDevice( device );
		}
		if ( ! silent ) {
			reloadFrame();
		}
	}
	deviceButtons.forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			setDevice( b.getAttribute( 'data-device' ) );
		} );
	} );

	function choices() {
		var blocks = enabledItems().map( function ( it ) {
			return { key: it.key, kind: it.kind, block: it.block, attr_id: it.attr_id, name: it.name, settings: it.settings || {} };
		} );
		return {
			title: state.title,
			store_type: state.storeType || ( state.scan && state.scan.store_type ? state.scan.store_type.type : '' ),
			style: state.style,
			colours: state.colours,
			phone: state.phone,
			place: state.place,
			selector: state.selector,
			display_on_page: state.display_on_page,
			blocks: blocks,
			speed: state.speed
		};
	}
	function frameDoc() {
		try {
			return frame.contentDocument;
		} catch ( e ) {
			return null;
		}
	}
	function reloadFrame() {
		if ( ! state.previewUrl ) {
			return;
		}
		frameLoading.classList.add( 'is-on' );
		frame.src = state.previewUrl;
	}
	frame.addEventListener( 'load', function () {
		frameLoading.classList.remove( 'is-on' );
		// A theme that builds the shop page from its own template prints no draft: the preview puts it on the page.
		if ( ! previewCtl || ! state.draft || ! previewCtl.missing() ) {
			return;
		}
		post( 'woobewoo_pf_setup_preview_html', { draft: state.draft } ).then( function ( res ) {
			var note = document.getElementById( 'wpfSetupPreviewNote' );
			if ( res && res.html && previewCtl.inject( res.html, res.data && res.data.css ) && note && D.i18n.previewPlaced ) {
				note.textContent = D.i18n.previewPlaced;
			}
		} ).catch( function () {} );
	} );
	/**
	 * Save the draft, then update the iframe: swap the filter HTML in place when the page
	 * already shows a draft at the same place, else reload the iframe with the draft URL.
	 */
	function schedulePreview( forceReload ) {
		clearTimeout( previewTimer );
		previewTimer = setTimeout( function () {
			var seq = ++draftSeq;
			post( 'woobewoo_pf_setup_draft', { choices: JSON.stringify( choices() ), draft: state.draft } ).then( function ( res ) {
				if ( seq !== draftSeq || ! res || ! res.data ) {
					return;
				}
				state.draft = res.data.draft;
				var newUrl = res.data.preview_url, doc = frameDoc(), current = doc && doc.querySelector( '.wpfPreviewDraft' );
				if ( previewCtl ) {
					previewCtl.live( newUrl ); // "View on my shop" and the shop tab it opened follow the draft.
				}
				if ( ! forceReload && current && state.previewUrl === newUrl ) {
					swapPreviewHtml( current );
				} else {
					state.previewUrl = newUrl;
					reloadFrame();
				}
			} ).catch( function () {} );
		}, 400 );
	}
	function swapPreviewHtml( current ) {
		post( 'woobewoo_pf_setup_preview_html', { draft: state.draft } ).then( function ( res ) {
			// Keeps the placement wrapper the page already sized (sidebar column), see wbw-preview.js.
			if ( ! res || ! res.html || ! previewCtl || ! previewCtl.swap( res.html ) ) {
				reloadFrame();
			}
		} ).catch( reloadFrame );
	}

	/* ---------------------------------------------------------------- scan */

	function paintMeter( pct ) {
		var meter = document.getElementById( 'wpfSetupMeter' );
		if ( ! meter ) {
			return;
		}
		var on = Math.round( pct / 100 * 13 );
		Array.prototype.forEach.call( meter.children, function ( s, i ) {
			s.classList.toggle( 'is-on', i < on );
		} );
		meter.setAttribute( 'aria-valuenow', pct );
	}
	function meterHtml() {
		return '<div class="wbw-seg" id="wpfSetupMeter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">' + new Array( 14 ).join( '<span></span>' ) + '</div><p class="wbw-p wbw-muted" id="wpfSetupMeterText" aria-live="polite"></p>';
	}
	function scanningPane() {
		return kicker( 'Step 1 of 7 · We did the homework' ) + '<h1 class="wbw-h1 wbw-h1--xl">' + esc( D.i18n.scanning ) + '</h1>' + meterHtml();
	}
	function paintProgress( data ) {
		paintMeter( data.progress || 0 );
		var t = document.getElementById( 'wpfSetupMeterText' );
		if ( t && data.total ) {
			t.textContent = sprintf( D.i18n.scanProgress, num( data.offset || 0 ), num( data.total ) );
		}
	}
	function scanFailedPane( reason ) {
		var html = kicker( 'Step 1 of 7' ) + '<h1 class="wbw-h1 wbw-h1--xl">' + esc( D.i18n.scanFailed ) + '</h1>';
		if ( reason ) {
			html += '<p class="wbw-p"><strong>' + esc( D.i18n.scanReason ) + '</strong></p><pre class="wbw-reason">' + esc( reason ) + '</pre>';
		}
		html += '<div class="wbw-actions"><button type="button" class="wbw-btn wbw-btn--primary" data-act="rescan">' + esc( D.i18n.tryAgain ) + '</button></div>';
		return html;
	}
	function noProductsPane( url ) {
		return kicker( 'Step 1 of 7' ) + '<h1 class="wbw-h1 wbw-h1--xl">' + esc( D.i18n.noProducts ) + '</h1><p class="wbw-p">' + esc( D.i18n.noProductsP ) + '</p><div class="wbw-actions"><a class="wbw-btn wbw-btn--primary" href="' + esc( url || D.addProductUrl ) + '">' + esc( D.i18n.addProduct ) + '</a><button type="button" class="wbw-btn" data-act="rescan">' + esc( D.i18n.checkAgain ) + '</button></div>';
	}
	/**
	 * Scan the catalogue. Each request processes up to 250 products per database round trip for
	 * at most 20 seconds, then reports progress; we call again at once until done.
	 */
	function scan( force ) {
		state.scanning = true;
		if ( ! document.getElementById( 'wpfSetupMeter' ) ) {
			setPane( scanningPane() );
		}
		paintMeter( force ? 0 : ( D.progress || 0 ) );
		post( 'woobewoo_pf_setup_scan', { force: force ? '1' : '0' } ).then( function ( res ) {
			if ( ! res || ! res.data ) {
				throw new Error( 'nodata' );
			}
			if ( res.data.error ) {
				var e = new Error( 'server' );
				e.reason = res.data.error;
				throw e;
			}
			if ( res.data.empty ) {
				state.scanning = false;
				setPane( noProductsPane( res.data.add_product_url ) );
				return;
			}
			if ( ! res.data.done ) {
				paintProgress( res.data );
				scan( false );
				return;
			}
			paintMeter( 100 );
			state.scanning = false;
			state.scan = res.data.scan;
			state.suggestions = res.data.suggestions;
			state.environment = res.data.environment || state.environment;
			D.attrsWorth = countWorth( state.scan );
			start();
		} ).catch( function ( e ) {
			state.scanning = false;
			var reason = '';
			if ( e && e.reason ) {
				reason = e.reason;
			} else if ( e && e.message === 'notjson' ) {
				reason = sprintf( D.i18n.scanNotJson, e.status || '?' ) + ' ' + ( e.snippet || '' );
			} else if ( e && e.message === 'nodata' ) {
				reason = D.i18n.scanNoData;
			} else if ( e && e.message === 'server' ) {
				reason = D.i18n.error;
			} else {
				reason = D.i18n.scanNetwork + ( e && e.message ? ' (' + e.message + ')' : '' );
			}
			setPane( scanFailedPane( reason ) );
		} );
	}
	// If the owner leaves while a scan is running, the server finishes it in the background.
	window.addEventListener( 'pagehide', function () {
		if ( ! state.scanning || ! navigator.sendBeacon ) {
			return;
		}
		var body = new FormData();
		body.append( 'action', 'woobewoo_pf_setup_scan_background' );
		body.append( 'mod', 'setup' );
		body.append( 'pl', 'wpf' );
		body.append( 'reqType', 'ajax' );
		body.append( 'wpfNonce', D.nonce );
		navigator.sendBeacon( D.ajaxUrl, body );
	} );
	function countWorth( s ) {
		var n = 0;
		( s.attributes || [] ).forEach( function ( a ) {
			if ( a.fill > 60 && a.terms >= 2 ) {
				n++;
			}
		} );
		if ( s.brands && s.brands.taxonomy && s.brands.fill > 60 && s.brands.terms >= 2 ) {
			n++;
		}
		return n;
	}
	function start() {
		state.suggestions.items.forEach( function ( it ) {
			if ( typeof state.blocks[ it.key ] === 'undefined' ) {
				state.blocks[ it.key ] = !! ( it.suggested && ! it.pro_only );
			}
		} );
		state.title = ( state.title || 'Shop filter' );
		paintSteps();
		render();
		schedulePreview( true );
		testSelector();
	}

	/* ---------------------------------------------------------------- self test (product grid) */

	function testSelector() {
		state.selectorTest = { running: true };
		post( 'woobewoo_pf_setup_test_selector', { selector: state.selector } ).then( function ( res ) {
			state.selectorTest = res && res.data ? res.data : { found: false };
			state.selectorTest.message = res && res.messages && res.messages[ 0 ] ? res.messages[ 0 ] : '';
			if ( state.selectorTest.suggest && ! state.selectorTest.found ) {
				state.selector = state.selectorTest.suggest;
				state.selectorTest.found = true;
			}
			if ( state.step === 'welcome' || state.step === 'place' ) {
				render();
			}
		} ).catch( function () {
			state.selectorTest = { found: false, error: 'network' };
		} );
	}
	function gridTag() {
		var t = state.selectorTest;
		if ( ! t || t.running ) {
			return tag( 'Theme: ' + themeName() + ', checking the product grid…', '' );
		}
		if ( t.found ) {
			return tag( 'Theme: ' + themeName() + ', product grid found', 'pass' );
		}
		if ( t.skipped ) {
			return tag( 'Theme: ' + themeName() + ', store in coming soon mode, grid check skipped', '' );
		}
		return tag( 'Theme: ' + themeName() + ', product grid not found yet', 'partial' );
	}
	function gridBanner() {
		var t = state.selectorTest;
		if ( ! t || t.running ) {
			return '<div class="wbw-banner wbw-banner--note">Checking your shop page for the product grid…</div>';
		}
		if ( t.found ) {
			return '<div class="wbw-banner wbw-banner--pass">Your product grid was found: ' + esc( state.selector ) + ' on ' + esc( themeName() ) + '.' + ( builderNames().length ? ' ' + esc( builderNames().join( ', ' ) ) + ' present.' : '' ) + ' No conflicts.</div>';
		}
		if ( t.skipped ) {
			return '<div class="wbw-banner wbw-banner--note">' + esc( t.message || 'Your store is in coming soon mode, so we could not check the product grid. The usual selector is set; run the check again once the store is live.' ) + '</div>';
		}
		return '<div class="wbw-banner wbw-banner--partial">' + esc( t.message || 'We could not find the product grid. Paste the selector of your product list below and run the check again.' ) + '</div>';
	}

	/* ---------------------------------------------------------------- steps */

	function go( step ) {
		if ( STEPS.indexOf( step ) < 0 || ! state.scan ) {
			return;
		}
		state.done[ state.step ] = true;
		state.step = step;
		if ( step === 'phone' && state.device !== 'phone' ) {
			setDevice( 'phone', true );
			reloadFrame();
		} else if ( step !== 'phone' && state.device === 'phone' && ! state.userPickedDevice ) {
			setDevice( 'desktop', true );
			reloadFrame();
		}
		paintSteps();
		render();
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	}
	function render() {
		var fn = { welcome: renderWelcome, filters: renderFilters, look: renderLook, phone: renderPhone, place: renderPlace, speed: renderSpeed, publish: renderPublish }[ state.step ];
		setPane( fn() );
		paintPreviewBar();
	}
	function paintPreviewBar() {
		var k = 'Your real shop page · live', n = 'This is your shop with real products. Nothing is published yet.';
		if ( state.step === 'filters' ) {
			n = 'The preview changes as you switch filters on and off.';
		} else if ( state.step === 'look' ) {
			k = 'Your real shop page · ' + ( D.styles[ state.style ] ? D.styles[ state.style ].label : state.style ) + ' style';
			n = 'Pick a card and the preview changes.';
		} else if ( state.step === 'phone' ) {
			k = 'Your real shop page · phone';
			n = 'When the drawer is closed, a "' + state.phone.button_text + ' (2)" button floats at the bottom.';
		} else if ( state.step === 'place' ) {
			n = 'The preview shows the filter in the spot you pick.';
		} else if ( state.step === 'speed' ) {
			n = 'These switches do not change the look. They change speed and what Google sees.';
		} else if ( state.step === 'publish' ) {
			n = state.published ? 'Published. This is the live filter.' : 'This is your shop with the draft. Publish puts it live.';
		}
		previewKicker.textContent = k;
		previewNote.textContent = n;
	}

	function renderWelcome() {
		var s = state.scan, conf = Math.round( ( s.store_type.confidence || 0 ) * 100 ), builders = builderNames();
		var html = kicker( 'Step 1 of 7 · We did the homework' );
		html += '<h1 class="wbw-h1 wbw-h1--xl">We looked at your ' + esc( num( s.products ) ) + ' products. Here is a filter built for a ' + esc( storeLabel() ) + ' store.</h1>';
		html += '<p class="wbw-lede">We counted your sizes, colours, brands and prices, and compared them with what ' + esc( storeLabel() ) + ' stores usually use. You can change anything later.</p>';
		html += '<div class="wbw-card wbw-setup__found">' + kicker( 'What we found' );
		html += '<div class="wbw-facts">';
		html += '<div><p class="wbw-big">' + esc( num( s.products ) ) + '</p><p>products, ' + esc( num( s.variations ) ) + ' variations</p></div>';
		html += '<div><p class="wbw-big">' + esc( num( D.attrsWorth ) ) + '</p><p>attributes worth a filter</p></div>';
		html += '<div><p class="wbw-big">' + esc( conf ) + '%</p><p>sure it is a ' + esc( storeLabel() ) + ' store</p></div>';
		html += '</div>';
		html += '<div class="wbw-hairline-top wbw-tags">' + gridTag();
		if ( builders.length ) {
			html += tag( builders.join( ', ' ) + ' present', 'pass' );
		}
		html += tag( Number( D.hasFilters ) ? num( D.hasFilters ) + ( Number( D.hasFilters ) === 1 ? ' filter already placed' : ' filters already placed' ) : 'No filter placed yet', '' );
		if ( state.environment.core_filters_block ) {
			html += tag( 'Core Product Filters block is also placed', 'partial' );
		}
		html += '</div></div>';
		html += '<div class="wbw-type-pick"><span>Not a ' + esc( storeLabel() ) + ' store?</span><select class="wbw-input" data-act="store-type" aria-label="Kind of store"><option value="">Pick another kind</option>';
		Object.keys( D.types || {} ).forEach( function ( k ) {
			html += '<option value="' + esc( k ) + '"' + ( k === ( state.storeType || s.store_type.type ) ? ' selected' : '' ) + '>' + esc( D.types[ k ] ) + '</option>';
		} );
		html += '</select></div>';
		html += buttons( 'welcome', '<span class="wbw-live">Nothing goes live until step 7.</span>' );
		return html;
	}

	function renderFilters() {
		var items = state.suggestions.items, on = enabledItems().length;
		var html = kicker( 'Step 2 of 7 · One question' );
		html += '<h1 class="wbw-h1">Which filters do you want?</h1>';
		html += '<p class="wbw-text">We switched on the ones ' + esc( storeLabel() ) + ' stores usually show. Each card says why, what to watch out for, and how many of your products have the value.</p>';
		html += '<p class="wbw-small">The percent on a card is what is typical for ' + esc( storeLabel() ) + ' stores, a starting value. It is not measured on your store.</p>';
		html += '<div class="wbw-sug-cards">';
		items.forEach( function ( it ) {
			var isOn = !! state.blocks[ it.key ], total = state.scan.products, have = Math.max( 0, total - ( it.missing || 0 ) );
			var fillText, fillTone = 'pass';
			if ( it.pro_only ) {
				fillText = 'Needs Pro';
				fillTone = 'partial';
			} else if ( it.fill_text ) {
				fillText = it.fill_text;
				fillTone = it.fill_tone || 'pass';
			} else if ( it.kind === 'price' ) {
				fillText = it.fill >= 99 ? 'Every product has a price' : num( have ) + ' of ' + num( total ) + ' have a price';
				fillTone = it.fill > 60 ? 'pass' : 'partial';
			} else if ( it.kind === 'sale' ) {
				fillText = num( state.scan.sale ) + ' products are on sale';
				fillTone = state.scan.sale > 0 ? 'pass' : 'partial';
			} else if ( it.kind === 'stock' ) {
				fillText = num( state.scan.stock.outofstock || 0 ) + ' products are out of stock';
				fillTone = 'pass';
			} else if ( it.kind === 'rating' ) {
				fillText = num( state.scan.rating.rated ) + ' of ' + num( total ) + ' have reviews';
				fillTone = it.suggested ? 'pass' : 'partial';
			} else if ( it.kind === 'category' || it.kind === 'tags' ) {
				fillText = num( Math.round( total * it.fill / 100 ) ) + ' of ' + num( total ) + ' have a ' + ( it.kind === 'tags' ? 'tag' : 'category' );
				fillTone = it.suggested ? 'pass' : 'partial';
			} else {
				fillText = num( have ) + ' of ' + num( total ) + ' have a ' + String( it.name ).toLowerCase();
				fillTone = it.suggested ? 'pass' : 'partial';
			}
			// The percent is a typical starting value for this kind of store (benchmarks.php), not a measurement of this store or of our users: say so.
			var bench = it.benchmark ? 'Typical for ' + storeLabel() + ' stores (' + it.benchmark + '%)' : 'Found in your products';
			html += '<article class="wbw-sug' + ( isOn ? '' : ' wbw-sug--off' ) + '">';
			html += toggle( isOn, ( isOn ? 'Switch off ' : 'Switch on ' ) + it.name, 'data-block="' + esc( it.key ) + '"' + ( it.pro_only ? ' disabled' : '' ) );
			html += '<div class="wbw-sug__body"><div class="wbw-sug__head"><span class="wbw-sug__name">' + esc( it.name ) + '</span><span class="wbw-blue">' + esc( bench ) + '</span></div>';
			html += '<p class="wbw-sug__line"><b>Why:</b> ' + esc( it.why ) + '</p>';
			html += '<p class="wbw-sug__line wbw-sug__line--watch"><b>Watch out:</b> ' + esc( it.watch_out ) + ( ! it.suggested && it.reason ? ' ' + esc( it.reason ) : '' ) + '</p>';
			html += '<div class="wbw-sug__tags">' + tag( fillText, fillTone ) + tag( /index/i.test( it.speed || '' ) ? 'Fast, uses the index' : 'Fast', '' ) + '</div>';
			html += '</div></article>';
		} );
		( state.suggestions.cards || [] ).forEach( function ( c ) {
			html += '<article class="wbw-sug"><div class="wbw-sug__body"><div class="wbw-sug__head"><span class="wbw-sug__name">' + esc( c.title ) + '</span>' + ( c.tag_text ? tag( c.tag_text, c.tag || '' ) : '' ) + '</div><p class="wbw-sug__line wbw-sug__line--watch">' + esc( c.text ) + '</p>' + ( c.url && c.button ? '<div class="wbw-actions"><a class="wbw-btn wbw-btn--small" href="' + esc( c.url ) + '">' + esc( c.button ) + '</a></div>' : '' ) + '</div></article>';
		} );
		html += '</div>';
		html += buttons( 'filters', '<span class="wbw-live" aria-live="polite">' + esc( on ) + ' of ' + esc( items.length ) + ' filters on</span>' );
		return html;
	}

	function lookPic( key ) {
		if ( key === 'clean' ) {
			return '<span class="wbw-look-pic"><span>☑ Size 42 · 58</span><span>☐ Size 43 · 61</span><span>☐ Size 44 · 40</span><span class="wbw-bar-pic"></span></span>';
		}
		if ( key === 'chips' ) {
			return '<span class="wbw-look-pic wbw-look-pic--chips"><span class="wbw-chip-pic wbw-chip-pic--on">42</span><span class="wbw-chip-pic">43</span><span class="wbw-chip-pic">44</span><span class="wbw-chip-pic">Red</span><span class="wbw-chip-pic">Under $120</span></span>';
		}
		if ( key === 'swatches' ) {
			return '<span class="wbw-look-pic" style="gap:8px"><span style="display:flex;gap:6px"><span class="wbw-mini-dot" style="background:#D63A26"></span><span class="wbw-mini-dot" style="background:#171509"></span><span class="wbw-mini-dot" style="background:#6BBFFF"></span><span class="wbw-mini-dot" style="background:#9DD84F"></span><span class="wbw-mini-dot" style="background:#FFFFFF"></span></span><span style="display:flex;gap:4px"><span class="wbw-sq-pic">40</span><span class="wbw-sq-pic wbw-sq-pic--on">42</span><span class="wbw-sq-pic">44</span></span></span>';
		}
		return '<span class="wbw-look-pic"><span class="wbw-dd-pic">Size <span>⌄</span></span><span class="wbw-dd-pic">Colour <span>⌄</span></span><span class="wbw-dd-pic">Price <span>⌄</span></span></span>';
	}
	function renderLook() {
		var texts = {
			clean: 'Lists with a tick box and a count. Calm, works everywhere.',
			chips: 'Round buttons you tap. Great on phones.',
			swatches: 'Colours as colours, sizes as squares. Shoppers recognise, not read.',
			compact: 'Dropdowns in one row. Saves space above the products.'
		};
		var html = kicker( 'Step 3 of 7 · Pick a picture, not a name' );
		html += '<h1 class="wbw-h1">How should the filter look?</h1>';
		html += '<p class="wbw-text">Colours come from your theme. Pick the shape. You can fine tune every part later in the builder.</p>';
		html += '<div class="wbw-cards" role="radiogroup" aria-label="Style">';
		[ 'clean', 'chips', 'swatches', 'compact' ].forEach( function ( key ) {
			var sel = state.style === key, label = D.styles[ key ] ? D.styles[ key ].label : key;
			html += '<button type="button" class="wbw-card-btn" role="radio" aria-checked="' + ( sel ? 'true' : 'false' ) + '" data-style="' + key + '">';
			html += '<span class="wbw-card-btn__pic" aria-hidden="true">' + lookPic( key ) + '</span>';
			html += '<span class="wbw-card-btn__title"><span>' + esc( label ) + '</span><span class="wbw-blue">' + ( sel ? 'Picked' : '' ) + '</span></span>';
			html += '<span class="wbw-card-btn__text">' + esc( texts[ key ] ) + '</span></button>';
		} );
		html += '</div>';
		var c = state.colours || {};
		html += '<div class="wbw-colours-row"><span>Colours from your theme</span><span class="wbw-swatches" aria-label="Detected theme colours"><span class="wbw-swatch" style="background:' + esc( c.ink || '#171509' ) + '"></span><span class="wbw-swatch" style="background:' + esc( c.accent || '#6BBFFF' ) + '"></span><span class="wbw-swatch wbw-swatch--outline" style="background:' + esc( c.paper || '#F7F4EA' ) + '"></span></span><span class="wbw-muted">Read from ' + esc( themeName() ) + '. Change them later in Look.</span></div>';
		html += buttons( 'look' );
		return html;
	}

	function phoneRow( title, help, key ) {
		return '<div class="wbw-row wbw-row--card"><div class="wbw-row__text"><p class="wbw-row__title">' + esc( title ) + '</p><p class="wbw-row__help">' + esc( help ) + '</p></div>' + toggle( !! state.phone[ key ], title, 'data-phone="' + key + '"' ) + '</div>';
	}
	function renderPhone() {
		var html = kicker( 'Step 4 of 7 · 7 of 10 shoppers are on a phone' );
		html += '<h1 class="wbw-h1">On a phone, the filter opens as a drawer.</h1>';
		html += '<p class="wbw-text">A button stays at the bottom of the screen. Tap it, pick, see the count, tap "Show results". It is on for every new filter.</p>';
		html += '<div class="wbw-rows">';
		html += phoneRow( 'Open as a drawer on phones', 'Under 768px wide. Desktop keeps the sidebar.', 'drawer' );
		html += '<div class="wbw-row wbw-row--card"><label class="wbw-label" for="wpfBtnText">The button says</label><input id="wpfBtnText" class="wbw-input" type="text" value="' + esc( state.phone.button_text ) + '" style="width:200px" data-phone-text="1"></div>';
		html += phoneRow( 'Show the count on the button', '"' + state.phone.button_text + ' (3)" when three things are picked.', 'results_button' );
		html += phoneRow( 'Show picked things as chips at the top', 'Each chip has an x to remove it.', 'chips' );
		html += phoneRow( 'Remember the last filters', 'Next visit: "You filtered by size 42 last time. Use it again?" Saved on the shopper\'s phone only.', 'remember' );
		html += '</div>';
		html += buttons( 'phone' );
		return html;
	}

	function placePic( key ) {
		var inner = {
			sidebar: '<rect x="2" y="2" width="22" height="44" rx="4" fill="#FFFFFF"/><rect x="30" y="2" width="30" height="20" rx="4" fill="#FFFFFF"/><rect x="66" y="2" width="30" height="20" rx="4" fill="#FFFFFF"/><rect x="30" y="26" width="30" height="20" rx="4" fill="#FFFFFF"/><rect x="66" y="26" width="30" height="20" rx="4" fill="#FFFFFF"/>',
			above: '<rect x="2" y="2" width="94" height="12" rx="6" fill="#FFFFFF"/><rect x="2" y="20" width="28" height="26" rx="4" fill="#FFFFFF"/><rect x="35" y="20" width="28" height="26" rx="4" fill="#FFFFFF"/><rect x="68" y="20" width="28" height="26" rx="4" fill="#FFFFFF"/>',
			drawer: '<rect x="2" y="2" width="28" height="26" rx="4" fill="#FFFFFF"/><rect x="35" y="2" width="28" height="26" rx="4" fill="#FFFFFF"/><rect x="68" y="2" width="28" height="26" rx="4" fill="#FFFFFF"/><rect x="24" y="34" width="50" height="12" rx="6" fill="#6BBFFF"/>',
			shortcode: '<rect x="2" y="2" width="94" height="44" rx="6" fill="#FFFFFF"/><text x="49" y="30" text-anchor="middle" font-size="12" font-family="monospace" fill="#171509">[wpf-filters]</text>'
		};
		return '<span class="wbw-place-pic" aria-hidden="true"><svg viewBox="0 0 98 48">' + inner[ key ] + '</svg></span>';
	}
	function renderPlace() {
		var cards = [
			[ 'sidebar', 'In the shop sidebar', 'Next to the products. The classic spot shoppers expect.' ],
			[ 'above', 'Above the products', 'One row of dropdown buttons above the grid, with "All filters" for everything at once.' ],
			[ 'drawer', 'Drawer only', 'A "Filters" button on every screen size opens the drawer.' ],
			[ 'shortcode', 'Shortcode or block', 'You place it yourself. We give you the code on the last step.' ]
		];
		var html = kicker( 'Step 5 of 7 · One question' );
		html += '<h1 class="wbw-h1">Where should the filter go?</h1>';
		html += '<p class="wbw-text">We looked at your shop page and found the product grid, so the filter can update it. Pick a spot; the preview shows it.</p>';
		html += '<div class="wbw-cards" role="radiogroup" aria-label="Place">';
		cards.forEach( function ( c ) {
			var sel = state.place === c[ 0 ];
			html += '<button type="button" class="wbw-card-btn" role="radio" aria-checked="' + ( sel ? 'true' : 'false' ) + '" data-place="' + c[ 0 ] + '">' + placePic( c[ 0 ] ) + '<span class="wbw-card-btn__title"><span>' + esc( c[ 1 ] ) + '</span><span class="wbw-blue">' + ( sel ? 'Picked' : '' ) + '</span></span><span class="wbw-card-btn__text">' + esc( c[ 2 ] ) + '</span></button>';
		} );
		html += '</div>';
		html += gridBanner();
		if ( state.selectorTest && ! state.selectorTest.running && ! state.selectorTest.found ) {
			html += '<div class="wbw-selector"><label class="wbw-sr" for="wpfSelector">Product list selector</label><input id="wpfSelector" class="wbw-input" type="text" value="' + esc( state.selector ) + '" data-selector="1" placeholder="ul.products"><button type="button" class="wbw-btn" data-act="retest">Run the check again</button></div>';
		}
		html += buttons( 'place' );
		return html;
	}

	function speedRow( key, title, help ) {
		var isOn = key === 'index' ? true : !! state.speed[ key ];
		return '<div class="wbw-row"><div class="wbw-check' + ( isOn ? '' : ' wbw-check--todo' ) + '"><span aria-hidden="true">' + ( isOn ? '✓' : '' ) + '</span><div class="wbw-row__text"><p class="wbw-row__title">' + esc( title ) + '</p><p class="wbw-row__help">' + esc( help ) + '</p></div></div>' + ( key === 'index' ? tag( D.health && D.health.percent >= 95 ? 'Fresh' : 'Will rebuild on publish', D.health && D.health.percent >= 95 ? 'pass' : 'partial' ) : toggle( isOn, title, 'data-speed="' + key + '"' ) ) + '</div>';
	}
	function renderSpeed() {
		var html = kicker( 'Step 6 of 7 · Set for you' );
		html += '<h1 class="wbw-h1">Fast and found by Google, from day one.</h1>';
		html += '<p class="wbw-text">Six things, all on. Each one says what it does. You can change them later in Speed and SEO.</p>';
		html += '<div class="wbw-card wbw-speed-rows" style="gap:0">';
		html += speedRow( 'index', 'Index fresh', 'A ready list of every product\'s sizes, prices and stock, so counts answer in under 50 ms.' );
		html += speedRow( 'wbw_cache_counts', 'Counts from cache', 'Counts are reused until a product changes.' );
		html += speedRow( 'wbw_clean_links', 'Clean links', 'Helper parameters stay out of the address bar.' );
		html += speedRow( 'canonical_links', 'Canonical and noindex', 'Filtered pages point Google to the plain page and ask not to be listed.' );
		html += speedRow( 'wbw_bot_limit', 'Bot speed limit', 'A crawler may load 30 filtered pages a minute. More than that waits.' );
		html += speedRow( 'wbw_allow_ai_shoppers', 'Allow AI shoppers', 'ChatGPT, Claude, Perplexity and Google\'s AI get a clean door, not every link combination.' );
		html += '</div>';
		html += '<p class="wbw-muted">Nothing here changes how your shop looks. It changes how fast it answers and what Google sees.</p>';
		html += buttons( 'speed' );
		return html;
	}

	function renderPublish() {
		var s = state.scan, names = enabledItems().map( function ( it ) {
			return it.name;
		} ), placeWords = { sidebar: 'Placed in the shop sidebar', above: 'Placed above the products', drawer: 'Placed as a drawer on every screen', shortcode: 'Placed by you with the shortcode or block' };
		var id = state.published ? state.published.id : null;
		var html = kicker( 'Step 7 of 7 · ' + ( state.published ? 'Live' : 'Ready' ) );
		html += '<h1 class="wbw-h1">' + ( state.published ? 'Your filter is live on the shop page.' : 'Everything checks out. Publish when you are ready.' ) + '</h1>';
		html += '<p class="wbw-text">' + ( state.published ? 'Open Filters to edit it, or keep going with the shortcode below for other pages.' : 'Publishing puts the filter on your shop page. You can undo it from Filters, History, at any time.' ) + '</p>';
		html += '<div class="wbw-card wbw-publish-list">';
		html += '<div class="wbw-check"><span aria-hidden="true">✓</span><span>Catalogue scanned: ' + esc( num( s.products ) ) + ' products, ' + esc( num( s.variations ) ) + ' variations</span></div>';
		html += '<div class="wbw-check"><span aria-hidden="true">✓</span><span>' + esc( names.length ) + ' filters chosen: ' + esc( names.join( ', ' ) ) + '</span></div>';
		html += '<div class="wbw-check"><span aria-hidden="true">✓</span><span>Looks good on a phone: drawer ' + ( state.phone.drawer ? 'on' : 'off' ) + '</span></div>';
		html += '<div class="wbw-check"><span aria-hidden="true">✓</span><span>' + esc( placeWords[ state.place ] ) + ': product grid ' + ( state.selectorTest && state.selectorTest.found ? 'found (' + esc( state.selector ) + ')' : 'set to ' + esc( state.selector ) ) + '</span></div>';
		html += '<div class="wbw-check"><span aria-hidden="true">✓</span><span>Speed and SEO: index ' + ( D.health && D.health.percent >= 95 ? 'fresh' : 'rebuilding' ) + ', cache ' + ( state.speed.wbw_cache_counts ? 'on' : 'off' ) + ', clean links ' + ( state.speed.wbw_clean_links ? 'on' : 'off' ) + ', bots ' + ( state.speed.wbw_bot_limit ? 'calm' : 'free' ) + '</span></div>';
		html += '<div class="wbw-check' + ( state.published ? '' : ' wbw-check--todo' ) + '"><span aria-hidden="true">' + ( state.published ? '✓' : '6' ) + '</span><span>Published</span></div>';
		if ( state.published && state.published.paused && state.published.paused.length ) {
			var names = state.published.paused.map( function ( f ) { return esc( f.title ); } ).join( ', ' );
			html += '<div class="wbw-check"><span aria-hidden="true">✓</span><span>One page, one filter: we paused ' + ( state.published.paused.length === 1 ? 'the older filter ' : state.published.paused.length + ' older filters ' ) + '(' + names + ') on the same pages. ' + ( state.published.paused.length === 1 ? 'It' : 'They' ) + ' still work where the shortcode is pasted. Undo from Filters, History.</span></div>';
		}
		html += '</div>';
		if ( state.published ) {
			html += '<div class="wbw-actions"><a class="wbw-btn wbw-btn--primary" href="' + esc( state.published.filters_link ) + '">Open Filters</a><a class="wbw-btn" href="' + esc( state.published.edit_link ) + '">Open the builder</a><a class="wbw-btn" href="' + esc( state.published.shop_url ) + '" target="_blank" rel="noopener">See it on the shop</a></div>';
		} else {
			html += '<div class="wbw-actions"><button type="button" class="wbw-btn wbw-btn--primary" data-act="publish">Publish to my shop</button><button type="button" class="wbw-btn" data-go="back">Back</button><span class="wbw-live" id="wpfPublishMsg" aria-live="polite"></span></div>';
		}
		html += '<div class="wbw-card wbw-card--ink wbw-first-week">' + icon( 'calendar', 24 ) + '<div><p class="wbw-label">Your first week card is scheduled</p><p>On ' + esc( D.firstWeekDate ) + ' we show what shoppers used and suggest the first small change, like "nobody used On sale, remove it?"</p></div></div>';
		// Right column content of the canvas sits in the preview pane on this step.
		paintPublishAside( id );
		return html;
	}
	function paintPublishAside( id ) {
		var shortcode = id ? '[wpf-filters id=' + id + ']' : '[wpf-filters id=…]';
		var aside = document.getElementById( 'wpfPublishAside' );
		if ( ! aside ) {
			aside = document.createElement( 'div' );
			aside.id = 'wpfPublishAside';
			aside.style.display = 'flex';
			aside.style.flexDirection = 'column';
			aside.style.gap = '16px';
			document.getElementById( 'wpfSetupPreview' ).parentNode.appendChild( aside );
			aside.className = 'wbw-setup__aside';
		}
		aside.innerHTML = '<div class="wbw-card">' + kicker( 'Put it somewhere else too' ) + '<p class="wbw-small">The shop page is done. For any other page, paste one of these.</p>'
			+ '<div class="wbw-code"><span>' + esc( shortcode ) + '</span><button type="button" class="wbw-btn wbw-btn--xs" data-copy="' + esc( shortcode ) + '"' + ( id ? '' : ' disabled' ) + '>Copy</button></div>'
			+ '<div class="wbw-code"><span>Block: Product Filter</span><button type="button" class="wbw-btn wbw-btn--xs" data-copy="Product Filter">Copy</button></div>'
			+ '<div class="wbw-code"><span>Elementor: Product Filter widget</span><span class="wbw-meta" style="font-weight:700">Drag it in</span></div></div>'
			+ '<div class="wbw-card">' + kicker( 'Speed and SEO, set for you' ) + '<div class="wbw-seo-grid">'
			+ seoCell( true, 'Index ' + ( D.health && D.health.percent >= 95 ? 'fresh' + ( D.health.last_run ? ', ' + agoText( D.health.last_run ) : '' ) : 'rebuilding' ) )
			+ seoCell( state.speed.wbw_cache_counts, 'Counts from cache' ) + seoCell( state.speed.wbw_clean_links, 'Clean links' ) + seoCell( state.speed.canonical_links, 'Canonical and noindex' ) + seoCell( state.speed.wbw_bot_limit, 'Bot speed limit' ) + seoCell( state.speed.wbw_allow_ai_shoppers, 'Allow AI shoppers' )
			+ '</div><a class="wbw-link wbw-link--ink" href="' + esc( D.speedUrl ) + '">Change these in Speed and SEO</a></div>';
		aside.hidden = state.step !== 'publish';
		bindCopy( aside );
	}
	function seoCell( on, text ) {
		return '<div>' + tag( on ? 'On' : 'Off', on ? 'pass' : '' ) + esc( text ) + '</div>';
	}
	function agoText( ts ) {
		var m = Math.max( 1, Math.round( ( Date.now() / 1000 - ts ) / 60 ) );
		return m < 60 ? m + ' min ago' : Math.round( m / 60 ) + ' h ago';
	}
	function bindCopy( el ) {
		Array.prototype.forEach.call( el.querySelectorAll( '[data-copy]' ), function ( b ) {
			b.addEventListener( 'click', function () {
				var t = b.getAttribute( 'data-copy' );
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( t );
				}
				b.textContent = 'Copied';
				setTimeout( function () {
					b.textContent = 'Copy';
				}, 1500 );
			} );
		} );
	}

	/* ---------------------------------------------------------------- events */

	function publish() {
		var msg = document.getElementById( 'wpfPublishMsg' ), btn = pane.querySelector( '[data-act="publish"]' );
		if ( btn ) {
			btn.disabled = true;
		}
		if ( msg ) {
			msg.textContent = 'Publishing…';
		}
		post( 'woobewoo_pf_setup_publish', { choices: JSON.stringify( choices() ) } ).then( function ( res ) {
			if ( res && res.data && res.data.id ) {
				state.published = res.data;
				state.done.publish = true;
				if ( previewCtl ) {
					previewCtl.live( res.data.shop_url, state.draft ); // The open shop tab now shows the live filter.
				}
				state.previewUrl = res.data.shop_url;
				reloadFrame();
				render();
				paintSteps();
			} else {
				throw new Error( res && res.errors ? res.errors.join( ' ' ) : 'error' );
			}
		} ).catch( function ( e ) {
			if ( msg ) {
				msg.textContent = e && e.message && e.message !== 'error' ? e.message : D.i18n.error;
			}
			if ( btn ) {
				btn.disabled = false;
			}
		} );
	}

	pane.addEventListener( 'click', onPaneClick );
	pane.addEventListener( 'input', onPaneInput );
	pane.addEventListener( 'change', onPaneChange );
	function onPaneClick( e ) {
		var t = e.target.closest( '[data-go],[data-block],[data-style],[data-phone],[data-place],[data-speed],[data-act]' );
		if ( ! t || ! pane.contains( t ) ) {
			return;
		}
		if ( t.hasAttribute( 'data-go' ) ) {
			var i = STEPS.indexOf( state.step );
			go( t.getAttribute( 'data-go' ) === 'next' ? STEPS[ Math.min( i + 1, STEPS.length - 1 ) ] : STEPS[ Math.max( i - 1, 0 ) ] );
			return;
		}
		if ( t.hasAttribute( 'data-block' ) ) {
			var key = t.getAttribute( 'data-block' );
			state.blocks[ key ] = ! state.blocks[ key ];
			setPane( renderFilters() );
			schedulePreview( false );
			return;
		}
		if ( t.hasAttribute( 'data-style' ) ) {
			state.style = t.getAttribute( 'data-style' );
			setPane( renderLook() );
			paintPreviewBar();
			schedulePreview( false );
			return;
		}
		if ( t.hasAttribute( 'data-phone' ) ) {
			var pk = t.getAttribute( 'data-phone' );
			state.phone[ pk ] = state.phone[ pk ] ? 0 : 1;
			setPane( renderPhone() );
			schedulePreview( true );
			return;
		}
		if ( t.hasAttribute( 'data-place' ) ) {
			state.place = t.getAttribute( 'data-place' );
			setPane( renderPlace() );
			schedulePreview( true );
			return;
		}
		if ( t.hasAttribute( 'data-speed' ) ) {
			var sk = t.getAttribute( 'data-speed' );
			state.speed[ sk ] = state.speed[ sk ] ? 0 : 1;
			setPane( renderSpeed() );
			return;
		}
		var act = t.getAttribute( 'data-act' );
		if ( act === 'publish' ) {
			publish();
		} else if ( act === 'retest' ) {
			var inp = pane.querySelector( '[data-selector]' );
			if ( inp ) {
				state.selector = inp.value.trim() || 'ul.products';
			}
			testSelector();
			setPane( renderPlace() );
		} else if ( act === 'rescan' ) {
			// Visibly reset the meter, then start over with force=1 (drops the cached scan).
			setPane( scanningPane() );
			paintMeter( 0 );
			D.progress = 0;
			setTimeout( function () {
				scan( true );
			}, 250 );
		}
	}
	function onPaneInput( e ) {
		if ( e.target.hasAttribute( 'data-phone-text' ) ) {
			state.phone.button_text = e.target.value;
			schedulePreview( true );
		}
		if ( e.target.hasAttribute( 'data-selector' ) ) {
			state.selector = e.target.value;
		}
	}
	function onPaneChange( e ) {
		if ( e.target.getAttribute( 'data-act' ) === 'store-type' ) {
			state.storeType = e.target.value;
			var seq = state.suggestSeq = ( state.suggestSeq || 0 ) + 1;
			post( 'woobewoo_pf_setup_suggest', { store_type: state.storeType } ).then( function ( res ) {
				// Two quick changes: only the answer for the kind now picked counts.
				if ( seq === state.suggestSeq && res && res.data && res.data.suggestions ) {
					state.suggestions = res.data.suggestions;
					state.blocks = {};
					start();
				}
			} );
		}
	}
	deviceButtons.forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			state.userPickedDevice = true;
		} );
	} );

	/* ---------------------------------------------------------------- boot */

	if ( state.scan && state.suggestions ) {
		start();
	} else {
		scan( false );
	}
} )();
