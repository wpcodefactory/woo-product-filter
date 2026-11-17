/**
 * Product Filter by WBW - Builder (4.0)
 *
 * Built new from the design canvas. Left: blocks (drag to reorder) and "Add a block"
 * picture cards. Middle: plain words controls rendered from the schema (admin-schema.php),
 * grouped Basics / Look / Phone / Where it shows / Step by step finder / Advanced. Right:
 * live preview of the real shop with the unsaved settings (draft transient, 400ms debounce).
 * Save posts the same settings array as 3.x (settings[...] + settings[filters][order]).
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';
	if ( typeof window.wpfBuilderData === 'undefined' ) {
		return;
	}
	var D = window.wpfBuilderData, S = D.schema, T = D.i18n,
		state = {
			title: D.title || '',
			main: D.settings || {},
			order: D.order || [],
			selected: 'filter',
			device: 'desktop',
			dirty: false,
			draft: '',
			previewUrl: '',
			lastSaved: D.isDraft ? '' : 'saved'
		},
		mid = document.getElementById( 'wpfBuilderMid' ),
		list = document.getElementById( 'wpfBlockList' ),
		cardsEl = document.getElementById( 'wpfBlockCards' ),
		frame = document.getElementById( 'wpfBuilderFrame' ),
		frameWrap = document.getElementById( 'wpfBuilderFrameWrap' ),
		frameLoading = document.getElementById( 'wpfBuilderFrameLoading' ),
		countLine = document.getElementById( 'wpfBuilderCount' ),
		stateTag = document.getElementById( 'wpfBuilderState' ),
		previewCtl = window.wbwPreview ? window.wbwPreview( frameWrap, frame ) : null,
		previewTimer = null, draftSeq = 0, previewNote = '';

	if ( ! mid || ! list ) {
		return;
	}
	if ( ! state.main.mobile_overrides || typeof state.main.mobile_overrides !== 'object' ) {
		state.main.mobile_overrides = { enabled: '', layout: 'accordion', blocks: {} };
	}
	if ( ! state.main.mobile_overrides.blocks || typeof state.main.mobile_overrides.blocks !== 'object' ) {
		state.main.mobile_overrides.blocks = {};
	}
	if ( ! state.main.finder || typeof state.main.finder !== 'object' ) {
		state.main.finder = { enabled: '', steps: [], questions: {}, show_counts: '1' };
	}
	if ( ! state.main.finder.questions || typeof state.main.finder.questions !== 'object' ) {
		state.main.finder.questions = {};
	}
	var RADIO_LIST = [ 'wpfCategory', 'wpfBrand', 'wpfPerfectBrand' ];
	// 3.x blocks have no "logic": on categories and brands the 3.x "list" type was the one-choice radio list.
	state.order.forEach( function ( ob ) {
		if ( ob && ob.settings && typeof ob.settings.logic === 'undefined' ) {
			ob.settings.logic = ( RADIO_LIST.indexOf( ob.id ) >= 0 && ob.settings.f_frontend_type === 'list' ) || ob.settings.f_frontend_type === 'radio' ? 'single' : ( ob.settings.f_query_logic === 'and' ? 'and' : 'or' );
		}
	} );
	if ( ! Array.isArray( state.main.finder.steps ) ) {
		state.main.finder.steps = Object.keys( state.main.finder.steps || {} ).map( function ( k ) {
			return state.main.finder.steps[ k ];
		} );
	}
	state.order.forEach( function ( b, i ) {
		if ( ! b.uniqId ) {
			b.uniqId = 'wpf_' + Date.now().toString( 36 ) + i;
		}
		if ( ! b.settings || typeof b.settings !== 'object' || Array.isArray( b.settings ) ) {
			b.settings = {};
		}
	} );

	/* ---------------------------------------------------------------- helpers */

	function esc( s ) {
		return String( s === null || typeof s === 'undefined' ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function num( n ) {
		return Number( n || 0 ).toLocaleString();
	}
	function pickedOf( n, total ) {
		return ( T.pickedOf || '%1$s of %2$s on' ).replace( '%1$s', n ).replace( '%2$s', total );
	}
	function post( mod, action, body ) {
		body.append( 'action', action );
		body.append( 'mod', mod );
		body.append( 'pl', 'wpf' );
		body.append( 'reqType', 'ajax' );
		body.append( 'wpfNonce', D.nonce );
		return fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json();
		} );
	}
	function literalKey( key ) {
		// "f_options[]" and the block keys such as "f_stock_statuses[in]" are literal keys in the saved block settings (both engines read them so).
		return /\[\]$/.test( key ) || /^f_/.test( key );
	}
	function getPath( obj, key ) {
		if ( literalKey( key ) ) {
			return obj ? obj[ key ] : undefined;
		}
		var parts = key.replace( /\]/g, '' ).split( '[' ), cur = obj;
		for ( var i = 0; i < parts.length; i++ ) {
			if ( parts[ i ] === '' ) {
				continue;
			}
			if ( cur === null || typeof cur !== 'object' || ! ( parts[ i ] in cur ) ) {
				return undefined;
			}
			cur = cur[ parts[ i ] ];
		}
		return cur;
	}
	function setPath( obj, key, value ) {
		if ( literalKey( key ) ) {
			obj[ key ] = value;
			return;
		}
		var parts =key.replace( /\]/g, '' ).split( '[' ).filter( function ( p ) {
			return p !== '';
		} ), cur = obj;
		for ( var i = 0; i < parts.length - 1; i++ ) {
			if ( cur[ parts[ i ] ] === null || typeof cur[ parts[ i ] ] !== 'object' ) {
				cur[ parts[ i ] ] = {};
			}
			cur = cur[ parts[ i ] ];
		}
		cur[ parts[ parts.length - 1 ] ] = value;
	}
	function isOn( v ) {
		return v === '1' || v === 1 || v === true || v === 'on' || v === 'or' || v === 'yes_open';
	}
	function blockName( b ) {
		if ( b.settings && b.settings.f_title ) {
			return String( b.settings.f_title );
		}
		if ( b.id === 'wpfAttribute' && b.settings && b.settings.f_list ) {
			var a = attrById( b.settings.f_list );
			if ( a ) {
				return a.label;
			}
		}
		if ( D.blockNames[ b.id ] && D.blockNames[ b.id ].shop && ! isClassic() ) {
			return D.blockNames[ b.id ].shop; // the 4.0 storefront's own title for the block
		}
		return D.blockNames[ b.id ] ? D.blockNames[ b.id ].name : b.id;
	}
	function attrById( id ) {
		for ( var i = 0; i < D.attributes.length; i++ ) {
			if ( String( D.attributes[ i ].id ) === String( id ) ) {
				return D.attributes[ i ];
			}
		}
		return null;
	}
	function selectedBlock() {
		return typeof state.selected === 'number' ? state.order[ state.selected ] : null;
	}
	function touch( reload ) {
		state.dirty = true;
		stateTag.textContent = T.unsaved;
		stateTag.className = 'wbw-tag';
		schedulePreview( reload );
	}

	/* ---------------------------------------------------------------- legacy key mapping (3.x keys stay valid) */

	function applyDisplayType( b, type ) {
		var def = S.displayTypes[ type ] || S.displayTypes.list;
		b.settings.display_type = type;
		if ( type === 'slider' ) {
			b.settings.f_frontend_type = b.id === 'wpfPriceRange' ? 'list' : ( b.settings.f_frontend_type || '' );
		} else if ( type === 'toggle' ) {
			b.settings.f_frontend_type = 'list';
		} else {
			b.settings.f_frontend_type = def.legacy;
		}
		if ( def.legacy === 'list' && RADIO_LIST.indexOf( b.id ) >= 0 ) {
			// The classic engine reads "list" as the one-choice radio list on categories and brands, "multi" as tick boxes.
			b.settings.f_frontend_type = b.settings.logic === 'single' ? 'list' : 'multi';
		} else if ( def.legacy === 'list' && b.settings.logic === 'single' && [ 'wpfAttribute', 'wpfTags', 'wpfAuthor', 'wpfCustomField' ].indexOf( b.id ) >= 0 ) {
			b.settings.f_frontend_type = 'radio';
		}
		if ( b.id === 'wpfSortBy' ) {
			b.settings.f_frontend_type = type === 'dropdown' ? 'dropdown' : 'radio';
		}
		b.settings.f_wbw_style = def.style;
	}
	function applyLogic( b, logic ) {
		b.settings.logic = logic;
		b.settings.f_query_logic = logic === 'and' ? 'and' : 'or';
		applyDisplayType( b, b.settings.display_type || 'list' );
	}
	function currentDisplayType( b ) {
		if ( b.settings.display_type ) {
			return b.settings.display_type;
		}
		var legacy = b.settings.f_frontend_type || '';
		if ( b.settings.f_wbw_style === 'swatches' ) {
			return 'swatches';
		}
		if ( b.settings.f_wbw_style === 'chips' ) {
			return 'chips';
		}
		if ( legacy === 'dropdown' || legacy === 'mul_dropdown' ) {
			return 'dropdown';
		}
		if ( b.id === 'wpfPrice' ) {
			return 'slider';
		}
		if ( b.id === 'wpfOnSale' || b.id === 'wpfFeatured' ) {
			return 'toggle';
		}
		if ( b.id === 'wpfPriceRange' ) {
			return 'range_list';
		}
		if ( b.id === 'wpfSortBy' ) {
			return 'dropdown';
		}
		return 'list';
	}

	/* ---------------------------------------------------------------- left column */

	function pic( key, label ) {
		var inner = '';
		if ( key === 'list' ) {
			inner = '<span style="font-size:11px;line-height:1.5">☑ 42<br>☐ 43</span>';
		} else if ( key === 'chips' ) {
			inner = '<span class="wbw-mini-chip wbw-mini-chip--on">42</span><span class="wbw-mini-chip">43</span>';
		} else if ( key === 'squares' ) {
			inner = '<span class="wbw-mini-sq wbw-mini-sq--on">42</span><span class="wbw-mini-sq">43</span>';
		} else if ( key === 'swatches' ) {
			inner = '<span class="wbw-mini-dot" style="background:#D63A26;width:20px;height:20px;border-radius:6px"></span><span class="wbw-mini-dot" style="background:#171509;width:20px;height:20px;border-radius:6px"></span><span class="wbw-mini-dot" style="background:#6BBFFF;width:20px;height:20px;border-radius:6px"></span>';
		} else if ( key === 'dropdown' ) {
			inner = '<span class="wbw-mini-dd">' + esc( label || 'Size' ) + ' <span>⌄</span></span>';
		} else if ( key === 'slider' ) {
			inner = '<span class="wbw-mini-bar"></span>';
		} else if ( key === 'toggle' ) {
			inner = '<span style="display:inline-flex;width:30px;height:16px;border-radius:999px;background:#6BBFFF;position:relative"><span style="position:absolute;right:2px;top:2px;width:12px;height:12px;border-radius:999px;background:#fff"></span></span>';
		} else if ( key === 'range_list' ) {
			inner = '<span style="font-size:11px;line-height:1.5">☐ $0–50<br>☑ $50–100</span>';
		} else if ( key === 'clean' ) {
			inner = '<span style="font-size:11px;line-height:1.5">☑ Size 42 · 58<br>☐ Size 43 · 61</span>';
		} else if ( key === 'compact' ) {
			inner = '<span class="wbw-mini-dd">Size <span>⌄</span></span>';
		} else if ( key === 'sidebar' ) {
			inner = '<svg viewBox="0 0 56 28" width="56" height="28"><rect x="1" y="1" width="12" height="26" rx="3" fill="#fff"/><rect x="17" y="1" width="18" height="12" rx="3" fill="#fff"/><rect x="38" y="1" width="17" height="12" rx="3" fill="#fff"/><rect x="17" y="15" width="18" height="12" rx="3" fill="#fff"/><rect x="38" y="15" width="17" height="12" rx="3" fill="#fff"/></svg>';
		} else if ( key === 'above' ) {
			inner = '<svg viewBox="0 0 56 28" width="56" height="28"><rect x="1" y="1" width="54" height="8" rx="4" fill="#fff"/><rect x="1" y="12" width="16" height="15" rx="3" fill="#fff"/><rect x="20" y="12" width="16" height="15" rx="3" fill="#fff"/><rect x="39" y="12" width="16" height="15" rx="3" fill="#fff"/></svg>';
		} else if ( key === 'drawer' ) {
			inner = '<svg viewBox="0 0 56 28" width="56" height="28"><rect x="1" y="1" width="16" height="15" rx="3" fill="#fff"/><rect x="20" y="1" width="16" height="15" rx="3" fill="#fff"/><rect x="39" y="1" width="16" height="15" rx="3" fill="#fff"/><rect x="14" y="19" width="28" height="8" rx="4" fill="#6BBFFF"/></svg>';
		} else if ( key === '' ) {
			inner = '<span style="font-family:ui-monospace,monospace;font-size:10px">[wpf-filters]</span>';
		}
		return '<span class="wbw-pic-btn__pic" aria-hidden="true">' + inner + '</span>';
	}

	/* Value counts for the spec lines: terms per taxonomy, the price range and the authors, cached server side
	   (woofiltersEditAdmin.php, transient, 10 minutes). */
	var COUNTS = ( function () {
		try {
			var el = document.getElementById( 'wpfBuilderCounts' );
			return el ? JSON.parse( el.textContent ) : {};
		} catch ( e ) {
			return {};
		}
	} )();
	COUNTS.tax = COUNTS.tax || {};
	var KIND = { wpfAttribute: 'Attribute', wpfCategory: 'Category', wpfTags: 'Tag', wpfBrand: 'Brand', wpfPerfectBrand: 'Brand', wpfPrice: 'Price', wpfPriceRange: 'Price', wpfInStock: 'Stock', wpfOnSale: 'On sale', wpfRating: 'Rating', wpfSortBy: 'Sort', wpfFeatured: 'Featured', wpfAuthor: 'Author', wpfCustomField: 'Custom field', wpfSearchText: 'Search', wpfSearchNumber: 'Search', wpfVendors: 'Vendor' },
		TAX = { wpfCategory: 'product_cat', wpfTags: 'product_tag', wpfBrand: 'product_brand', wpfPerfectBrand: 'pwb-brand' },
		GROUP_BASICS = [ 'wpfPrice', 'wpfSortBy', 'wpfInStock', 'wpfOnSale', 'wpfRating', 'wpfFeatured', 'wpfSearchText' ],
		GROUP_PRODUCT = [ 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfCustomField', 'wpfSearchNumber', 'wpfVendors', 'wpfPriceRange' ],
		ICONS = {
			wpfPrice: '<path d="M4 12h16M8 8l-4 4 4 4M16 8l4 4-4 4"/>',
			wpfPriceRange: '<path d="M3 12h18"/><circle cx="8" cy="12" r="2.5"/><circle cx="16" cy="12" r="2.5"/>',
			wpfSortBy: '<path d="M4 6h16M4 12h10M4 18h5"/>',
			wpfInStock: '<circle cx="12" cy="12" r="8"/><path d="M8 12l3 3 5-6"/>',
			wpfOnSale: '<path d="M5 19L19 5"/><circle cx="7" cy="7" r="2"/><circle cx="17" cy="17" r="2"/>',
			wpfRating: '<path d="M12 3l2.7 5.6 6.1.7-4.5 4.2 1.2 6.1L12 16.7 6.5 19.6l1.2-6.1L3.2 9.3l6.1-.7z"/>',
			wpfFeatured: '<path d="M12 4l2 4 4 .5-3 3 .8 4.5L12 14l-3.8 2 .8-4.5-3-3L10 8z"/>',
			wpfSearchText: '<circle cx="11" cy="11" r="6"/><path d="M16 16l5 5"/>',
			wpfSearchNumber: '<path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"/>',
			wpfCategory: '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
			wpfTags: '<path d="M4 4h8l8 8-8 8-8-8z"/><circle cx="8" cy="8" r="1.2"/>',
			wpfBrand: '<path d="M3 12h18M6 6h12M6 18h12"/>',
			wpfPerfectBrand: '<path d="M3 12h18M6 6h12M6 18h12"/>',
			wpfAuthor: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
			wpfCustomField: '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M7 10h10M7 14h6"/>',
			wpfAttribute: '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M8 9h8M8 13h5"/>',
			wpfVendors: '<path d="M4 10l2-5h12l2 5"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>'
		},
		PRO_PLACEHOLDERS = [
			{ id: 'pro_searchtext', add: 'wpfSearchText', label: 'Search by text', spec: 'text', group: 'basics' },
			{ id: 'pro_cfrange', add: 'wpfSearchNumber', label: 'Custom field range', spec: 'range', group: 'product' },
			{ id: 'pro_cftext', add: 'wpfSearchText', label: 'Custom field text', spec: 'text', group: 'product' }
		],
		ATTRS_FIRST = 6,
		findInput = document.getElementById( 'wpfBlockFind' ),
		noneLine = document.getElementById( 'wpfBlockCardsNone' ),
		extraEl = document.getElementById( 'wpfBlockCardsExtra' ),
		showAllAttrs = false;

	function iconHtml( id, fallback ) {
		return ICONS[ id ] ? '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true">' + ICONS[ id ] + '</svg>' : ( fallback || '' );
	}
	function countWord( n ) {
		n = Number( n );
		return n > 500 ? '500+ values' : num( n ) + ( n === 1 ? ' value' : ' values' );
	}
	function taxCount( tax, fallback ) {
		return tax && typeof COUNTS.tax[ tax ] !== 'undefined' ? COUNTS.tax[ tax ] : fallback;
	}
	/* The "what is inside" word for a block (spec line) or a card (second line). */
	function valuesWord( id, settings, forCard ) {
		var n;
		switch ( id ) {
			case 'wpfAttribute':
				var a = settings ? attrById( settings.f_list ) : null;
				return a ? countWord( taxCount( a.taxonomy, a.terms ) ) : 'pick one below';
			case 'wpfCategory':
			case 'wpfTags':
			case 'wpfBrand':
			case 'wpfPerfectBrand':
				n = taxCount( TAX[ id ], null );
				return n === null ? 'list' : countWord( n );
			case 'wpfPrice':
			case 'wpfPriceRange':
				return forCard || ! COUNTS.price ? 'range' : COUNTS.price;
			case 'wpfInStock':
			case 'wpfOnSale':
			case 'wpfFeatured':
				return forCard ? 'yes or no' : 'Yes or no';
			case 'wpfRating':
				return '1 to 5 stars';
			case 'wpfSortBy':
				// A new block gets the first 6 orders (addBlock), so the card says what will be added.
				n = settings && settings[ 'f_options[]' ] ? String( settings[ 'f_options[]' ] ).split( ',' ).filter( Boolean ).length : Math.min( 6, Object.keys( D.sortby || {} ).length );
				return n + ( n === 1 ? ' option' : ' options' );
			case 'wpfAuthor':
				return COUNTS.authors ? countWord( COUNTS.authors ) : 'list';
			case 'wpfCustomField':
				if ( settings && settings.f_customfield_ ) {
					return 'field ' + String( D.customfields && D.customfields[ settings.f_customfield_ ] ? D.customfields[ settings.f_customfield_ ] : settings.f_customfield_ );
				}
				return forCard ? 'list' : 'no field picked yet';
			case 'wpfSearchText':
			case 'wpfSearchNumber':
				// The block: the custom field it reads when one is set. The card: what shoppers type or slide.
				var field = settings ? ( settings.f_custom_meta_field || settings.f_meta_key || ( isOn( settings.f_search_by_meta_fields ) ? settings.f_search_by_meta_fields_list : '' ) ) : '';
				if ( ! forCard && field ) {
					return 'field ' + String( field );
				}
				if ( id === 'wpfSearchText' && ( forCard || ! settings || ! isOn( settings.f_search_by_meta_fields ) || isOn( settings.f_search_by_title ) ) ) {
					return forCard ? 'text' : 'typed words';
				}
				if ( ! forCard ) {
					return 'no field picked yet'; // shoppers see nothing until a custom field is chosen
				}
				return settings && settings.f_search_logic === 'range' ? 'range' : 'number';
			case 'wpfVendors':
				return 'list';
		}
		return '';
	}
	function displayWord( b ) {
		var t = currentDisplayType( b );
		if ( t === 'toggle' || t === 'switch' ) {
			return 'Switch';
		}
		if ( S.displayTypes[ t ] && S.displayTypes[ t ].label ) {
			return S.displayTypes[ t ].label;
		}
		return t ? t.charAt( 0 ).toUpperCase() + t.slice( 1 ) : '';
	}
	/* "Attribute pa_size · 12 values · Squares · Open on phone" */
	function blockSpec( b ) {
		var parts = [], kind = KIND[ b.id ] || ( D.blockNames[ b.id ] ? D.blockNames[ b.id ].name : b.id ), v;
		if ( b.id === 'wpfAttribute' ) {
			var a = attrById( b.settings.f_list );
			kind = 'Attribute' + ( a ? ' ' + a.taxonomy : '' );
		} else if ( b.id === 'wpfSearchNumber' ) {
			kind = b.settings.f_search_logic === 'range' ? 'Custom field range' : 'Search by number';
		} else if ( b.id === 'wpfSearchText' ) {
			kind = isOn( b.settings.f_search_by_meta_fields ) && ! isOn( b.settings.f_search_by_title ) ? 'Custom field text' : 'Search by text';
		}
		parts.push( kind );
		v = valuesWord( b.id, b.settings, false );
		if ( v ) {
			parts.push( v );
		}
		v = displayWord( b );
		if ( v ) {
			parts.push( v );
		}
		if ( isOn( b.settings.f_show_hierarchical ) ) {
			parts.push( 'Tree' );
		}
		if ( Number( b.settings.show_more_top ) > 0 ) {
			parts.push( 'Top ' + Number( b.settings.show_more_top ) + ', then Show more' );
		} else if ( Number( b.settings.show_more_rows ) > 0 ) {
			parts.push( 'Show more after ' + Number( b.settings.show_more_rows ) + ' rows' );
		}
		parts.push( ( b.settings.f_enable_title_mobile || 'yes_open' ) === 'yes_close' ? 'Closed on phone' : 'Open on phone' );
		return parts.join( ' · ' );
	}

	function renderList() {
		var html = '';
		html += '<button type="button" class="wbw-block wbw-block--whole' + ( state.selected === 'filter' ? ' is-editing' : '' ) + '" data-select="filter"><svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/></svg><span class="wbw-block__text"><span class="wbw-block__title">Whole filter</span><span class="wbw-block__spec">Where it shows · Phone · Speed</span></span>' + ( state.selected === 'filter' ? '<span class="wbw-block__state">Editing</span>' : '' ) + '</button>';
		html += '<div class="wbw-hr" role="separator"></div>';
		state.order.forEach( function ( b, i ) {
			var off = b.settings.f_enable === false || b.settings.f_enable === '0' || b.settings.f_enable === '';
			html += '<div class="wbw-block wbw-sortable' + ( state.selected === i ? ' is-editing' : '' ) + ( off ? ' is-off' : '' ) + '" role="listitem" draggable="true" data-index="' + i + '" data-select="' + i + '" tabindex="0"><span class="wbw-grip" aria-hidden="true">⋮⋮</span><span class="wbw-block__text"><span class="wbw-block__title">' + esc( blockName( b ) ) + '</span><span class="wbw-block__spec">' + esc( blockSpec( b ) ) + '</span></span>' + ( state.selected === i ? '<span class="wbw-block__state">Editing</span>' : ( off ? '<span class="wbw-block__off">Off</span>' : '' ) ) + '</div>';
		} );
		list.innerHTML = html;
	}

	/* Cards printed by Pro through `woobewoo_pf_builder_block_cards_html` (hidden container): read once, drawn in the groups;
	   a click on the drawn card clicks the original button so Pro's own preset handler still runs. */
	var proHtmlCards = [];
	if ( extraEl ) {
		Array.prototype.forEach.call( extraEl.querySelectorAll( '[data-block]' ), function ( el, i ) {
			var preset = null;
			try {
				preset = JSON.parse( el.getAttribute( 'data-preset' ) || 'null' );
			} catch ( err ) {
				preset = null;
			}
			var svg = el.querySelector( 'svg' ), ps = ( preset && preset.settings ) || {}, block = el.getAttribute( 'data-block' );
			var group = el.getAttribute( 'data-group' ) || ( block === 'wpfSearchText' && ! ps.f_search_by_meta_fields ? 'basics' : 'product' );
			var spec = el.getAttribute( 'data-spec' ) || ( block === 'wpfSearchNumber' ? ( ps.f_search_logic === 'range' ? 'range' : 'number' ) : valuesWord( block, ps, true ) );
			proHtmlCards.push( { id: 'prohtml_' + i, add: block, label: ( el.textContent || '' ).trim(), icon: svg ? svg.outerHTML : '', group: group, spec: spec, preset: preset, proxy: i, pro: false } );
		} );
	}
	function isAdded( card ) {
		if ( card.attr ) {
			return state.order.some( function ( b ) {
				return b.id === 'wpfAttribute' && String( b.settings.f_list ) === String( card.attr );
			} );
		}
		if ( card.preset && card.preset.settings ) {
			var ps = card.preset.settings, keys = [ 'f_search_logic', 'f_search_by_meta_fields', 'f_search_by_title' ].filter( function ( k ) {
				return typeof ps[ k ] !== 'undefined';
			} );
			return state.order.some( function ( b ) {
				return b.id === card.add && keys.every( function ( k ) {
					return String( b.settings[ k ] || '' ) === String( ps[ k ] || '' );
				} );
			} );
		}
		return state.order.some( function ( b ) {
			return b.id === card.add;
		} );
	}
	function cardHtml( c ) {
		var added = ! c.pro && isAdded( c ), search = esc( ( c.label + ' ' + ( c.spec || '' ) + ' ' + ( c.attrTax || '' ) ).toLowerCase() );
		if ( c.pro ) {
			return '<a class="wbw-pic wbw-pic--pro" href="' + esc( c.href || D.upgradeUrl ) + '" data-wbw-tip="' + esc( T.proTip || '' ) + '" data-search="' + search + '">' + iconHtml( c.add, c.icon ) + '<span class="wbw-pic__name">' + esc( c.label ) + ' · Pro</span><span class="wbw-pic__spec">' + esc( c.spec || '' ) + '</span></a>';
		}
		return '<button type="button" class="wbw-pic' + ( added ? ' is-added' : '' ) + '" data-add="' + esc( c.add ) + '"' + ( c.attr ? ' data-attr="' + esc( c.attr ) + '"' : '' ) + ( typeof c.proxy !== 'undefined' ? ' data-proxy="' + c.proxy + '"' : '' ) + ( added ? ' aria-disabled="true" data-wbw-tip="' + esc( T.addedTip || '' ) + '"' : '' ) + ' data-search="' + search + '">' + iconHtml( c.attr ? 'wpfAttribute' : c.add, c.icon ) + '<span class="wbw-pic__name">' + esc( c.label ) + '</span><span class="wbw-pic__spec">' + esc( added ? 'Added' : ( c.spec || '' ) ) + '</span></button>';
	}
	function groupHtml( key, kicker, cards, extra ) {
		if ( ! cards.length ) {
			return '';
		}
		return '<div class="wbw-group" data-group="' + key + '"><span class="wbw-kicker wbw-kicker--group">' + esc( kicker ) + '</span><div class="wbw-pics">' + cards.join( '' ) + '</div>' + ( extra || '' ) + '</div>';
	}
	function renderCards() {
		var basics = [], product = [], attrs = [], seen = {};
		function place( c ) {
			var g = c.group || ( GROUP_BASICS.indexOf( c.add ) >= 0 ? 'basics' : 'product' );
			( g === 'basics' ? basics : product ).push( c );
			seen[ c.add ] = true;
		}
		D.cards.forEach( function ( c ) {
			if ( c.id === 'wpfAttribute' ) {
				return;
			}
			if ( c.id === 'wpfBrand' && ! ( D.blockNames.wpfBrand || D.blockNames.wpfPerfectBrand ) ) {
				return;
			}
			place( { id: c.id, add: c.id, label: c.label, icon: c.icon, pro: !! c.pro && ! D.isPro, href: c.href, group: c.group, spec: c.spec || valuesWord( c.id, null, true ) } );
		} );
		proHtmlCards.forEach( place );
		if ( ! D.isPro ) {
			PRO_PLACEHOLDERS.forEach( function ( c ) {
				if ( ! seen[ c.add ] ) {
					place( { id: c.id, add: c.add, label: c.label, spec: c.spec, group: c.group, pro: true } );
				}
			} );
		}
		function order( listA, ref ) {
			return listA.sort( function ( a, b ) {
				var ia = ref.indexOf( a.add ), ib = ref.indexOf( b.add );
				return ( ia < 0 ? 99 : ia ) - ( ib < 0 ? 99 : ib );
			} );
		}
		order( basics, GROUP_BASICS );
		order( product, GROUP_PRODUCT );
		D.attributes.forEach( function ( a ) {
			attrs.push( { id: 'attr_' + a.id, add: 'wpfAttribute', attr: a.id, attrTax: a.taxonomy, label: a.label, spec: countWord( taxCount( a.taxonomy, a.terms ) ) } );
		} );
		var attrCards = attrs.map( function ( c, i ) {
			var h = cardHtml( c );
			return i >= ATTRS_FIRST ? h.replace( 'class="wbw-pic', 'class="wbw-pic is-folded' ) : h;
		} );
		var more = attrs.length > ATTRS_FIRST ? '<button type="button" class="wbw-chip-btn" data-show-all="1" aria-expanded="' + ( showAllAttrs ? 'true' : 'false' ) + '">' + ( showAllAttrs ? 'Show fewer' : 'Show all ' + num( attrs.length ) ) + '</button>' : '';
		cardsEl.innerHTML = groupHtml( 'basics', 'Basics', basics.map( cardHtml ) ) + groupHtml( 'product', 'Product data', product.map( cardHtml ) ) + groupHtml( 'attrs', 'Attributes · ' + num( attrs.length ), attrCards, more );
		cardsEl.classList.toggle( 'is-all', showAllAttrs );
		applyFind();
	}
	/* The search field filters every card as you type; a query unfolds the attributes. */
	function applyFind() {
		var q = findInput ? findInput.value.trim().toLowerCase() : '', shown = 0;
		Array.prototype.forEach.call( cardsEl.querySelectorAll( '.wbw-pic' ), function ( c ) {
			var hit = ! q || ( c.getAttribute( 'data-search' ) || '' ).indexOf( q ) >= 0, folded = c.classList.contains( 'is-folded' ) && ! showAllAttrs && ! q;
			c.hidden = ! hit || folded;
			if ( hit ) {
				shown++;
			}
		} );
		Array.prototype.forEach.call( cardsEl.querySelectorAll( '.wbw-group' ), function ( g ) {
			g.hidden = ! g.querySelector( '.wbw-pic:not([hidden])' ) && ! ( ! q && g.querySelector( '.wbw-pic' ) );
		} );
		var more = cardsEl.querySelector( '[data-show-all]' );
		if ( more ) {
			more.hidden = !! q;
		}
		if ( noneLine ) {
			noneLine.hidden = shown > 0;
		}
	}
	if ( findInput ) {
		findInput.addEventListener( 'input', applyFind );
	}
	function renderLeft() {
		renderList();
		renderCards();
	}

	/* ---------------------------------------------------------------- middle column */

	function toggleHtml( on, attrs, label ) {
		return '<button type="button" class="wbw-toggle" role="switch" aria-checked="' + ( on ? 'true' : 'false' ) + '" aria-label="' + esc( label ) + '" ' + attrs + '></button>';
	}
	function row( title, help, control ) {
		return '<div class="wbw-row"><div class="wbw-row__text"><p class="wbw-row__title">' + esc( title ) + '</p>' + ( help ? '<p class="wbw-row__help">' + esc( help ) + '</p>' : '' ) + '</div>' + control + '</div>';
	}
	function inputHtml( type, value, attrs, cls ) {
		return '<input class="wbw-input wbw-input--sm ' + ( cls || '' ) + '" type="' + type + '" value="' + esc( value ) + '" ' + attrs + '>';
	}
	function selectHtml( options, value, attrs ) {
		var h = '<select class="wbw-input wbw-input--sm" ' + attrs + '>';
		Object.keys( options ).forEach( function ( k ) {
			h += '<option value="' + esc( k ) + '"' + ( String( value ) === String( k ) ? ' selected' : '' ) + '>' + esc( options[ k ] ) + '</option>';
		} );
		return h + '</select>';
	}
	function optionsFor( c ) {
		if ( c.options === 'attributes' ) {
			var o = {};
			D.attributes.forEach( function ( a ) {
				o[ a.id ] = a.label;
			} );
			return o;
		}
		if ( c.options === 'sortby' ) {
			return D.sortby;
		}
		if ( c.options === 'instock' ) {
			return D.instock;
		}
		if ( c.options === 'customfields' ) {
			return D.customfields;
		}
		return c.options || {};
	}
	/* Controls one storefront engine ignores: dimmed and disabled, with one plain line, while the filter runs the
	   other engine (engine4Only: the 4.0 engine reads it; classicOnly: only the classic 3.x engine does). */
	function isClassic() {
		return String( state.main.frontend_engine || '' ) === 'legacy';
	}
	function engineOffLine( c ) {
		if ( c.engine4Only && isClassic() ) {
			return T.engine4Only || 'Works with the 4.0 engine only. Switch the engine at the top of this page to use it.';
		}
		if ( c.classicOnly && ! isClassic() ) {
			return T.classicOnly || 'Works with the classic engine only. The 4.0 storefront does not use it.';
		}
		return '';
	}
	/* The longer reason, shown as a tooltip when the mouse is over a dimmed row. */
	function engineOffTip( c ) {
		if ( c.engine4Only && isClassic() ) {
			return T.engine4OnlyTip || engineOffLine( c );
		}
		return T.classicOnlyTip || engineOffLine( c );
	}
	function controlHtml( c, scope, obj, uid ) {
		var h = controlHtmlInner( c, scope, obj, uid ), line = h ? engineOffLine( c ) : '';
		if ( ! line ) {
			return h;
		}
		h = h.replace( /<(button|input|select)\b/g, '<$1 disabled aria-disabled="true"' );
		var note = '<p class="wbw-row__help wbw-row__engine">' + esc( line ) + '</p>', tip = ' data-wbw-tip="' + esc( engineOffTip( c ) ) + '"';
		if ( /^<div class="wbw-row[ "]/.test( h ) ) {
			h = h.replace( /^<div class="wbw-row/, '<div' + tip + ' class="wbw-row wbw-row--engine-off' );
			var at = h.indexOf( '</div>' );
			return h.slice( 0, at ) + note + h.slice( at );
		}
		return '<div class="wbw-row--engine-off"' + tip + '>' + note + h + '</div>';
	}
	function controlHtmlInner( c, scope, obj, uid ) {
		var value = getPath( obj, c.key ), attrs = 'data-scope="' + scope + '" data-key="' + esc( c.key ) + '" data-cid="' + esc( c.id ) + '"';
		if ( typeof value === 'undefined' || value === null ) {
			value = typeof c.default !== 'undefined' ? c.default : '';
		}
		if ( c.show_when && ! isOn( getPath( obj, c.show_when ) ) ) {
			return '';
		}
		switch ( c.type ) {
			case 'switch':
				var onV = typeof c.on !== 'undefined' ? c.on : '1', on = String( value ) === String( onV ) || ( onV === '1' && isOn( value ) );
				return row( c.label, c.help, toggleHtml( on, attrs + ' data-type="switch" data-on="' + esc( onV ) + '" data-off="' + esc( typeof c.off !== 'undefined' ? c.off : '' ) + '"', c.label ) );
			case 'text':
				return row( c.label, c.help, inputHtml( 'text', value, attrs + ' data-type="text" aria-label="' + esc( c.label ) + '"', c.mono ? 'wbw-input--mono' : '' ) );
			case 'number':
				var unit = c.unit_key ? selectHtml( { px: 'px', '%': '%' }, getPath( obj, c.unit_key ) || 'px', 'data-scope="' + scope + '" data-key="' + esc( c.unit_key ) + '" data-type="select" aria-label="unit" style="width:84px;margin-left:6px"' ) : '';
				return row( c.label, c.help, '<span style="display:inline-flex;align-items:center">' + inputHtml( 'number', value, attrs + ' data-type="text" aria-label="' + esc( c.label ) + '"', 'wbw-input--short' ) + unit + '</span>' );
			case 'select':
				return row( c.label, c.help, selectHtml( optionsFor( c ), value, attrs + ' data-type="select" aria-label="' + esc( c.label ) + '"' ) );
			case 'color':
				return row( c.label, c.help, '<span style="display:inline-flex;align-items:center;gap:8px">' + inputHtml( 'color', /^#[0-9a-f]{6}$/i.test( value ) ? value : '#6BBFFF', 'data-scope="' + scope + '" data-key="' + esc( c.key ) + '" data-type="text" aria-label="' + esc( c.label ) + '"', '' ) + '<button type="button" class="wbw-btn wbw-btn--xs" data-clear="' + esc( c.key ) + '" data-scope="' + scope + '">' + ( value ? 'Back to the default blue' : 'Default blue' ) + '</button></span>' );
			case 'multi':
				// Ticked choices as a grid of cards under the title (the "Ways to sort" list has 13).
				var opts = optionsFor( c ), keys = Object.keys( opts ), picked = String( value || '' ).split( ',' ).filter( Boolean ), h = '<div class="wbw-select-list" role="group" aria-label="' + esc( c.label ) + '">';
				keys.forEach( function ( k ) {
					var on2 = picked.indexOf( k ) >= 0;
					h += '<label class="wbw-select-list__item' + ( on2 ? ' is-on' : '' ) + '"><input type="checkbox" value="' + esc( k ) + '"' + ( on2 ? ' checked' : '' ) + ' data-scope="' + scope + '" data-key="' + esc( c.key ) + '" data-type="multi"><span class="wbw-select-list__box" aria-hidden="true"></span><span class="wbw-select-list__name">' + esc( opts[ k ] ) + '</span></label>';
				} );
				var count = keys.filter( function ( k ) {
					return picked.indexOf( k ) >= 0;
				} ).length;
				return '<div class="wbw-row wbw-row--multi"><div class="wbw-row__text"><p class="wbw-row__title">' + esc( c.label ) + ' <span class="wbw-select-list__count" data-multi-count="' + esc( c.key ) + '" data-total="' + keys.length + '">' + esc( pickedOf( count, keys.length ) ) + '</span></p>' + ( c.help ? '<p class="wbw-row__help">' + esc( c.help ) + '</p>' : '' ) + '</div>' + h + '</div></div>';
			case 'pictures':
				var ph = '<div role="radiogroup" aria-label="' + esc( c.label ) + '" class="wbw-cards wbw-cards--4">';
				Object.keys( c.options ).forEach( function ( k ) {
					ph += '<button type="button" class="wbw-pic-btn" role="radio" aria-checked="' + ( String( value ) === String( k ) ? 'true' : 'false' ) + '" data-scope="' + scope + '" data-key="' + esc( c.key ) + '" data-type="radio" data-value="' + esc( k ) + '">' + pic( k === 'chips' ? 'chips' : k === 'swatches' ? 'swatches' : k, '' ) + esc( c.options[ k ] ) + '</button>';
				} );
				return ph + '</div>';
			default:
				return '';
		}
	}

	function renderFilterPanel() {
		var m = state.main, html = '';
		function group( key, kicker, extraTop ) {
			var h = '';
			S.controls.forEach( function ( c ) {
				if ( c.scope === 'main' && c.group === key && c.type !== 'finder_steps' ) {
					h += controlHtml( c, 'main', m );
				}
			} );
			return '<div class="wbw-card">' + ( kicker ? '<span class="wbw-kicker' + ( extraTop ? ' wbw-kicker--pics' : '' ) + '">' + esc( kicker ) + '</span>' : '' ) + h + '</div>';
		}
		html += '<div class="wbw-card"><h2 class="wbw-h2">' + esc( state.title || 'Shop filter' ) + '</h2><p class="wbw-block-meta">' + esc( state.order.length ) + ' blocks · ' + esc( num( D.productCount ) ) + ' products</p><span class="wbw-kicker">Basics</span>';
		S.controls.forEach( function ( c ) {
			if ( c.scope === 'main' && c.group === 'basics' ) {
				html += controlHtml( c, 'main', m );
			}
		} );
		html += '</div>';
		html += group( 'look', 'Look · pick a picture', true );
		html += group( 'phone', 'Phone' );
		html += group( 'where', 'Where it shows' );
		html += renderFinder();
		html += renderAdvancedMain();
		mid.innerHTML = html;
	}

	function renderFinder() {
		var f = state.main.finder, h = '<div class="wbw-card"><span class="wbw-kicker">Step by step finder</span>';
		S.controls.forEach( function ( c ) {
			if ( c.scope === 'main' && c.group === 'finder' && c.type !== 'finder_steps' ) {
				h += controlHtml( c, 'main', state.main );
			}
		} );
		if ( isOn( f.enabled ) ) {
			var ids = ( f.steps || [] ).filter( function ( id ) {
				return state.order.some( function ( b ) {
					return b.uniqId === id;
				} );
			} );
			state.order.forEach( function ( b ) {
				if ( ids.indexOf( b.uniqId ) < 0 ) {
					ids.push( b.uniqId );
				}
			} );
			f.steps = ids;
			h += '<div class="wbw-row" style="flex-direction:column;align-items:stretch;gap:8px"><div class="wbw-row__text"><p class="wbw-row__title">Order of questions</p><p class="wbw-row__help">Drag to reorder. The question text is what shoppers read.</p></div><div class="wbw-finder-steps" id="wpfFinderSteps">';
			ids.forEach( function ( id, i ) {
				var b = state.order.filter( function ( x ) {
					return x.uniqId === id;
				} )[ 0 ];
				h += '<div class="wbw-block wbw-sortable" draggable="true" data-finder-index="' + i + '" data-finder-id="' + esc( id ) + '"><span class="wbw-grip" aria-hidden="true">⋮⋮</span>' + esc( ( i + 1 ) + '. ' + blockName( b ) ) + '<input class="wbw-input wbw-input--sm" type="text" placeholder="' + esc( 'What ' + blockName( b ).toLowerCase() + '?' ) + '" value="' + esc( f.questions[ id ] || '' ) + '" data-finder-q="' + esc( id ) + '" aria-label="Question for ' + esc( blockName( b ) ) + '"></div>';
			} );
			h += '</div></div>';
		}
		return h + '</div>';
	}

	/* Settings only the other engine reads, folded away at the end of an Advanced list (they still count). */
	function otherEngineGroup( html, count ) {
		if ( ! count ) {
			return '';
		}
		return '<details class="wbw-details wbw-details--off"><summary>' + esc( ( isClassic() ? T.offGroup4 : T.offGroupClassic ) || 'Only for the other engine' ) + ' · ' + count + '</summary><p class="wbw-muted">' + esc( ( isClassic() ? T.offGroup4Help : T.offGroupClassicHelp ) || '' ) + '</p>' + html + '</details>';
	}
	function renderAdvancedMain() {
		var shown = {}, sections = {}, offSections = {}, n = 0, nOff = 0;
		S.controls.forEach( function ( c ) {
			if ( c.scope === 'main' ) {
				shown[ c.key ] = true;
				if ( c.unit_key ) {
					shown[ c.unit_key ] = true;
				}
			}
		} );
		Object.keys( S.mainKeys ).forEach( function ( key ) {
			var def = S.mainKeys[ key ];
			if ( shown[ key ] || def.type === 'hidden' || key === 'frontend_engine' ) {
				return; // frontend_engine: the Filter engine stripe above the columns
			}
			var sec = def.section || 'other', c = { id: 'adv_' + key, key: key, type: def.type, label: def.label, help: def.help, options: def.options, mono: def.mono, default: def.default, classicOnly: def.classicOnly, engine4Only: def.engine4Only };
			var bucket = engineOffLine( c ) ? offSections : sections;
			bucket[ sec ] = ( bucket[ sec ] || '' ) + controlHtml( c, 'main', state.main );
			if ( bucket === offSections ) {
				nOff++;
			} else {
				n++;
			}
		} );
		var h = '<details class="wbw-details"><summary>Advanced · ' + n + ' more settings</summary><p class="wbw-muted">Every setting from version 3 is still here, in plain words. Change these only when you know why.</p>', off = '';
		Object.keys( S.sections ).forEach( function ( sec ) {
			if ( sections[ sec ] ) {
				h += '<div class="wbw-adv-group"><span class="wbw-kicker">' + esc( S.sections[ sec ] ) + '</span>' + sections[ sec ] + '</div>';
			}
			if ( offSections[ sec ] ) {
				off += '<div class="wbw-adv-group"><span class="wbw-kicker">' + esc( S.sections[ sec ] ) + '</span>' + offSections[ sec ] + '</div>';
			}
		} );
		return h + otherEngineGroup( off, nOff ) + '</details>';
	}

	function blockMeta( b ) {
		if ( b.id === 'wpfAttribute' ) {
			var a = attrById( b.settings.f_list );
			if ( a ) {
				return 'Attribute "' + a.taxonomy + '" · ' + num( a.terms ) + ' values' + ( a.with_value !== null && typeof a.with_value !== 'undefined' ? ' · ' + num( a.with_value ) + ' products have one' : '' );
			}
			return 'Attribute · pick one below';
		}
		return ( D.blockNames[ b.id ] ? D.blockNames[ b.id ].name : b.id ) + ' block';
	}

	function renderBlockPanel( b ) {
		var html = '', shown = {};
		html += '<div class="wbw-card"><h2 class="wbw-h2">' + esc( blockName( b ) ) + '</h2><p class="wbw-block-meta">' + esc( blockMeta( b ) ) + '</p><span class="wbw-kicker">Basics</span>';
		S.controls.forEach( function ( c ) {
			if ( c.scope === 'block' && c.group === 'basics' && ( ! c.blocks || c.blocks.indexOf( b.id ) >= 0 ) ) {
				if ( c.id === 'pick_many' ) {
					shown[ 'logic' ] = true;
					html += row( c.label, c.help, toggleHtml( ( b.settings.logic || 'or' ) !== 'single', 'data-scope="block" data-key="logic" data-type="logic"', c.label ) );
					return;
				}
				shown[ c.key ] = true;
				html += controlHtml( c, 'block', b.settings );
			}
		} );
		html += '</div>';

		var types = S.displayFor[ b.id ] || [ 'list', 'chips', 'dropdown' ], cur = currentDisplayType( b );
		html += '<div class="wbw-card"><span class="wbw-kicker wbw-kicker--pics">Look · pick a picture</span><div role="radiogroup" aria-label="How the choices look" class="wbw-cards wbw-cards--4">';
		types.forEach( function ( t ) {
			html += '<button type="button" class="wbw-pic-btn" role="radio" aria-checked="' + ( cur === t ? 'true' : 'false' ) + '" data-display="' + t + '">' + pic( t, blockName( b ) ) + esc( S.displayTypes[ t ] ? S.displayTypes[ t ].label : t ) + '</button>';
		} );
		html += '</div>';
		// Plain words Look controls for this block (show more rows, the link text, Pro additions).
		S.controls.forEach( function ( c ) {
			if ( c.scope === 'block' && c.group === 'look' && c.type !== 'display_type' && ( ! c.blocks || c.blocks.indexOf( b.id ) >= 0 ) ) {
				shown[ c.key ] = true;
				html += controlHtml( c, 'block', b.settings );
			}
		} );
		html += '</div>';

		var mo = state.main.mobile_overrides.blocks[ b.uniqId ];
		html += '<div class="wbw-card"><span class="wbw-kicker">Phone</span>';
		html += row( 'Open this block on phones', 'Shown first in the drawer, already open.', toggleHtml( ( b.settings.f_enable_title_mobile || 'yes_open' ) === 'yes_open', 'data-scope="block" data-key="f_enable_title_mobile" data-type="switch" data-on="yes_open" data-off="yes_close"', 'Open this block on phones' ) );
		html += row( 'Different look on phones', mo ? 'Pick the look phones get.' : 'Off: phones use the same look as desktop.', toggleHtml( !! mo, 'data-mobile-override="1"', 'Different look on phones' ) );
		if ( mo ) {
			html += '<div role="radiogroup" aria-label="Look on phones" class="wbw-cards wbw-cards--4" style="padding-top:8px">';
			types.forEach( function ( t ) {
				html += '<button type="button" class="wbw-pic-btn" role="radio" aria-checked="' + ( mo === t ? 'true' : 'false' ) + '" data-mobile-display="' + t + '">' + pic( t, blockName( b ) ) + esc( S.displayTypes[ t ] ? S.displayTypes[ t ].label : t ) + '</button>';
			} );
			html += '</div>';
		}
		html += '</div>';

		// Advanced: every remaining block key with a plain label.
		var adv = '', advOff = '', n = 0, nOff = 0;
		function addAdv( c ) {
			if ( engineOffLine( c ) ) {
				advOff += controlHtml( c, 'block', b.settings );
				nOff++;
			} else {
				adv += controlHtml( c, 'block', b.settings );
				n++;
			}
		}
		Object.keys( S.blockKeys ).forEach( function ( key ) {
			var def = S.blockKeys[ key ];
			if ( shown[ key ] || def.type === 'hidden' || ( def.blocks && def.blocks.indexOf( b.id ) < 0 ) ) {
				return;
			}
			if ( key === 'f_enable_title_mobile' ) {
				return;
			}
			addAdv( { id: 'adv_' + key, key: key, type: def.type, label: def.label, help: def.help, options: def.options, default: def.default, classicOnly: def.classicOnly, engine4Only: def.engine4Only } );
		} );
		Object.keys( b.settings ).forEach( function ( key ) {
			if ( ! S.blockKeys[ key ] && ! shown[ key ] && key.indexOf( 'f_' ) === 0 && typeof b.settings[ key ] !== 'object' ) {
				addAdv( { id: 'adv_' + key, key: key, type: 'text', label: key.replace( /^f_/, '' ).replace( /_/g, ' ' ), classicOnly: ( S.engine4BlockKeys || [] ).length > 0 && ( S.engine4BlockKeys || [] ).indexOf( key ) < 0 } );
			}
		} );
		html += '<details class="wbw-details"><summary>Advanced · ' + n + ' more settings</summary><p class="wbw-muted">Every setting from version 3 is still here: order of values, search inside the list, how many to show before "More", and more.</p>' + adv + otherEngineGroup( advOff, nOff ) + '<div class="wbw-actions" style="padding-top:16px"><button type="button" class="wbw-btn wbw-btn--small" data-block-toggle="1">' + ( b.settings.f_enable === false || b.settings.f_enable === '' ? 'Switch this block on' : 'Switch this block off' ) + '</button><button type="button" class="wbw-btn wbw-btn--small wbw-btn--danger" data-remove="1">Remove this block</button></div></details>';
		mid.innerHTML = html;
	}

	function render() {
		renderLeft();
		var b = selectedBlock();
		if ( b ) {
			renderBlockPanel( b );
		} else {
			state.selected = 'filter';
			renderFilterPanel();
		}
	}

	/* ---------------------------------------------------------------- events: middle */

	mid.addEventListener( 'click', function ( e ) {
		var b = selectedBlock(), t;
		t = e.target.closest( '.wbw-toggle[data-type="switch"]' );
		if ( t ) {
			var on = t.getAttribute( 'aria-checked' ) !== 'true', obj = t.getAttribute( 'data-scope' ) === 'block' ? b.settings : state.main;
			setPath( obj, t.getAttribute( 'data-key' ), on ? t.getAttribute( 'data-on' ) : t.getAttribute( 'data-off' ) );
			applyWrites( t.getAttribute( 'data-cid' ), on, obj );
			touch( true );
			render();
			return;
		}
		t = e.target.closest( '.wbw-toggle[data-type="logic"]' );
		if ( t && b ) {
			applyLogic( b, t.getAttribute( 'aria-checked' ) === 'true' ? 'single' : 'or' );
			touch( false );
			render();
			return;
		}
		t = e.target.closest( '[data-display]' );
		if ( t && b ) {
			applyDisplayType( b, t.getAttribute( 'data-display' ) );
			touch( false );
			render();
			return;
		}
		t = e.target.closest( '[data-mobile-override]' );
		if ( t && b ) {
			if ( state.main.mobile_overrides.blocks[ b.uniqId ] ) {
				delete state.main.mobile_overrides.blocks[ b.uniqId ];
			} else {
				state.main.mobile_overrides.blocks[ b.uniqId ] = currentDisplayType( b );
				state.main.mobile_overrides.enabled = '1';
			}
			touch( true );
			render();
			return;
		}
		t = e.target.closest( '[data-mobile-display]' );
		if ( t && b ) {
			state.main.mobile_overrides.blocks[ b.uniqId ] = t.getAttribute( 'data-mobile-display' );
			state.main.mobile_overrides.enabled = '1';
			touch( true );
			render();
			return;
		}
		t = e.target.closest( '.wbw-pic-btn[data-type="radio"]' );
		if ( t ) {
			setPath( t.getAttribute( 'data-scope' ) === 'block' ? b.settings : state.main, t.getAttribute( 'data-key' ), t.getAttribute( 'data-value' ) );
			touch( true );
			render();
			return;
		}
		t = e.target.closest( '[data-clear]' );
		if ( t ) {
			setPath( t.getAttribute( 'data-scope' ) === 'block' ? b.settings : state.main, t.getAttribute( 'data-clear' ), '' );
			touch( false );
			render();
			return;
		}
		t = e.target.closest( '[data-remove]' );
		if ( t && b ) {
			if ( window.confirm( 'Remove "' + blockName( b ) + '" from this filter?' ) ) {
				delete state.main.mobile_overrides.blocks[ b.uniqId ];
				state.order.splice( state.selected, 1 );
				state.selected = 'filter';
				touch( true );
				render();
			}
			return;
		}
		t = e.target.closest( '[data-block-toggle]' );
		if ( t && b ) {
			b.settings.f_enable = ( b.settings.f_enable === false || b.settings.f_enable === '' ) ? true : '';
			touch( true );
			render();
		}
	} );
	function applyWrites( cid, on, obj ) {
		S.controls.forEach( function ( c ) {
			if ( c.id === cid && c.writes ) {
				Object.keys( c.writes ).forEach( function ( k ) {
					var map = c.writes[ k ];
					setPath( obj, k, on ? map[ '1' ] : map[ '' ] );
				} );
			}
		} );
	}
	mid.addEventListener( 'input', function ( e ) {
		var el = e.target, b = selectedBlock();
		if ( el.hasAttribute( 'data-finder-q' ) ) {
			state.main.finder.questions[ el.getAttribute( 'data-finder-q' ) ] = el.value;
			touch( false );
			return;
		}
		if ( el.getAttribute( 'data-type' ) === 'text' ) {
			setPath( el.getAttribute( 'data-scope' ) === 'block' ? b.settings : state.main, el.getAttribute( 'data-key' ), el.value );
			if ( el.getAttribute( 'data-key' ) === 'f_title' ) {
				renderLeft();
			} else if ( el.getAttribute( 'data-scope' ) === 'block' ) {
				renderList(); // e.g. the custom field name in the spec line
			}
			touch( el.getAttribute( 'data-key' ) === 'wbw_accent' ? false : true );
		}
	} );
	mid.addEventListener( 'change', function ( e ) {
		var el = e.target, b = selectedBlock();
		if ( el.getAttribute( 'data-type' ) === 'select' ) {
			setPath( el.getAttribute( 'data-scope' ) === 'block' ? b.settings : state.main, el.getAttribute( 'data-key' ), el.value );
			if ( el.getAttribute( 'data-key' ) === 'f_list' ) {
				renderLeft();
				renderBlockPanel( b );
			} else if ( el.getAttribute( 'data-key' ) === 'frontend_engine' ) {
				render(); // the engine decides which controls work
			} else if ( el.getAttribute( 'data-scope' ) === 'block' ) {
				renderLeft(); // the spec line and the "Added" state of the cards follow the block's settings
			}
			touch( true );
		}
		if ( el.getAttribute( 'data-type' ) === 'multi' ) {
			var picked = Array.prototype.filter.call( mid.querySelectorAll( 'input[data-type="multi"][data-key="' + el.getAttribute( 'data-key' ) + '"]' ), function ( i ) {
				return i.checked;
			} ).map( function ( i ) {
				return i.value;
			} );
			setPath( el.getAttribute( 'data-scope' ) === 'block' ? b.settings : state.main, el.getAttribute( 'data-key' ), picked.join( ',' ) );
			el.closest( 'label' ).classList.toggle( 'is-on', el.checked );
			var countEl = mid.querySelector( '[data-multi-count="' + el.getAttribute( 'data-key' ) + '"]' );
			if ( countEl ) {
				countEl.textContent = pickedOf( picked.length, countEl.getAttribute( 'data-total' ) );
			}
			if ( el.getAttribute( 'data-scope' ) === 'block' ) {
				renderList(); // "Sort · 6 options" follows the ticked options
			}
			touch( true );
		}
	} );

	/* ---------------------------------------------------------------- events: left (select, add, drag) */

	list.addEventListener( 'click', function ( e ) {
		var t = e.target.closest( '[data-select]' );
		if ( t ) {
			var v = t.getAttribute( 'data-select' );
			state.selected = v === 'filter' ? 'filter' : parseInt( v, 10 );
			render();
		}
	} );
	list.addEventListener( 'keydown', function ( e ) {
		var t = e.target.closest( '[data-select]' );
		if ( ! t ) {
			return;
		}
		var i = parseInt( t.getAttribute( 'data-index' ), 10 );
		var sel = t.getAttribute( 'data-select' );
		if ( e.key === 'Enter' || e.key === ' ' ) {
			e.preventDefault();
			t.click();
			refocus( '[data-select="' + sel + '"]' );
		} else if ( ( e.key === 'ArrowUp' || e.key === 'ArrowDown' ) && ! isNaN( i ) && e.altKey ) {
			e.preventDefault();
			moveBlock( i, e.key === 'ArrowUp' ? i - 1 : i + 1 );
			refocus( '[data-index="' + Math.max( 0, Math.min( state.order.length - 1, e.key === 'ArrowUp' ? i - 1 : i + 1 ) ) + '"]' );
		}
	} );
	/* The list is redrawn on every change; keep the keyboard focus on the same row. */
	function refocus( selector ) {
		var el = list.querySelector( selector );
		if ( el ) {
			el.focus();
		}
	}
	function moveBlock( from, to ) {
		if ( to < 0 || to >= state.order.length || from === to ) {
			return;
		}
		var item = state.order.splice( from, 1 )[ 0 ];
		state.order.splice( to, 0, item );
		if ( state.selected === from ) {
			state.selected = to;
		} else if ( typeof state.selected === 'number' ) {
			if ( from < state.selected && to >= state.selected ) {
				state.selected--;
			} else if ( from > state.selected && to <= state.selected ) {
				state.selected++;
			}
		}
		touch( false );
		render();
	}
	var dragFrom = null;
	function bindDrag( container, attr, onDrop ) {
		container.addEventListener( 'dragstart', function ( e ) {
			var t = e.target.closest( '[' + attr + ']' );
			if ( ! t ) {
				return;
			}
			dragFrom = parseInt( t.getAttribute( attr ), 10 );
			t.classList.add( 'is-dragging' );
			e.dataTransfer.effectAllowed = 'move';
			try {
				e.dataTransfer.setData( 'text/plain', String( dragFrom ) );
			} catch ( err ) {}
		} );
		container.addEventListener( 'dragover', function ( e ) {
			var t = e.target.closest( '[' + attr + ']' );
			if ( ! t || dragFrom === null ) {
				return;
			}
			e.preventDefault();
			Array.prototype.forEach.call( container.querySelectorAll( '.wbw-drop-before, .wbw-drop-after' ), function ( x ) {
				x.classList.remove( 'wbw-drop-before', 'wbw-drop-after' );
			} );
			var rect = t.getBoundingClientRect();
			t.classList.add( e.clientY < rect.top + rect.height / 2 ? 'wbw-drop-before' : 'wbw-drop-after' );
		} );
		container.addEventListener( 'dragleave', function ( e ) {
			var t = e.target.closest( '[' + attr + ']' );
			if ( t ) {
				t.classList.remove( 'wbw-drop-before', 'wbw-drop-after' );
			}
		} );
		container.addEventListener( 'drop', function ( e ) {
			var t = e.target.closest( '[' + attr + ']' );
			if ( ! t || dragFrom === null ) {
				return;
			}
			e.preventDefault();
			var to = parseInt( t.getAttribute( attr ), 10 ), rect = t.getBoundingClientRect(), after = e.clientY >= rect.top + rect.height / 2;
			if ( after && to < dragFrom ) {
				to++;
			} else if ( ! after && to > dragFrom ) {
				to--;
			}
			onDrop( dragFrom, to );
			dragFrom = null;
		} );
		container.addEventListener( 'dragend', function () {
			dragFrom = null;
			Array.prototype.forEach.call( container.querySelectorAll( '.is-dragging, .wbw-drop-before, .wbw-drop-after' ), function ( x ) {
				x.classList.remove( 'is-dragging', 'wbw-drop-before', 'wbw-drop-after' );
			} );
		} );
	}
	bindDrag( list, 'data-index', moveBlock );
	bindDrag( mid, 'data-finder-index', function ( from, to ) {
		var steps = state.main.finder.steps;
		if ( to < 0 || to >= steps.length || from === to ) {
			return;
		}
		var item = steps.splice( from, 1 )[ 0 ];
		steps.splice( to, 0, item );
		touch( false );
		render();
	} );

	cardsEl.addEventListener( 'click', function ( e ) {
		var more = e.target.closest( '[data-show-all]' );
		if ( more ) {
			showAllAttrs = ! showAllAttrs;
			renderCards();
			var again = cardsEl.querySelector( '[data-show-all]' );
			if ( again ) {
				again.focus(); // the chip is redrawn; keep the keyboard on it
			}
			return;
		}
		var t = e.target.closest( '[data-add]' );
		if ( ! t || t.disabled || t.classList.contains( 'is-added' ) ) {
			return;
		}
		var byKeyboard = e.detail === 0;
		if ( t.hasAttribute( 'data-proxy' ) && extraEl ) {
			var orig = extraEl.querySelectorAll( '[data-block]' )[ parseInt( t.getAttribute( 'data-proxy' ), 10 ) ];
			if ( orig ) {
				orig.click();
			}
		} else {
			addBlock( t.getAttribute( 'data-add' ), t.getAttribute( 'data-attr' ) );
		}
		if ( byKeyboard ) {
			// Enter or Space on a card: the cards are redrawn, so the focus goes to the new block in the list.
			window.setTimeout( function () {
				var nb = list.querySelector( '.wbw-sortable[data-index="' + ( state.order.length - 1 ) + '"]' );
				if ( nb ) {
					nb.focus();
				}
			}, 0 );
		}
	} );
	function addBlock( id, attr ) {
		var b = { id: id, uniqId: 'wpf_' + Date.now().toString( 36 ) + Math.floor( Math.random() * 1000 ), settings: { f_enable: true, f_enable_title: 'yes_open', f_enable_title_mobile: 'yes_open', logic: 'or' } };
		if ( attr ) {
			var a = attrById( attr );
			b.settings.f_list = attr;
			b.settings.f_title = a ? a.label : '';
			b.settings.f_show_count = '1';
			b.settings.f_hide_empty = '1';
		}
		// Long lists start short: the five values with the most products, then "Show more" (4.0.0).
		if ( [ 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor' ].indexOf( id ) !== -1 ) {
			b.settings.show_more_top = '5';
		}
		if ( id === 'wpfSortBy' ) {
			b.settings[ 'f_options[]' ] = Object.keys( D.sortby ).slice( 0, 6 ).join( ',' );
		}
		if ( id === 'wpfInStock' ) {
			b.settings[ 'f_options[]' ] = 'instock,outofstock';
		}
		// A registered block (WooBeWoo_PF_Blocks 'defaults') starts with its own settings.
		if ( S.blockDefaults && S.blockDefaults[ id ] && typeof S.blockDefaults[ id ] === 'object' ) {
			Object.keys( S.blockDefaults[ id ] ).forEach( function ( k ) {
				b.settings[ k ] = S.blockDefaults[ id ][ k ];
			} );
		}
		applyDisplayType( b, ( S.displayFor[ id ] || [ 'list' ] )[ 0 ] );
		if ( id === 'wpfPrice' ) {
			b.settings.f_frontend_type = '';
		}
		state.order.push( b );
		state.selected = state.order.length - 1;
		touch( true );
		render();
	}
	if ( extraEl ) {
		extraEl.addEventListener( 'click', function ( e ) {
			var t = e.target.closest( '[data-block]' );
			if ( t ) {
				e.preventDefault();
				addBlock( t.getAttribute( 'data-block' ), null );
			}
		} );
	}

	/* The sticky preview sits 24px under the sticky top bar: its height goes into a CSS variable. */
	( function () {
		var top = document.getElementById( 'wpfBuilderTop' ), cols = document.getElementById( 'wpfBuilder' );
		if ( ! top || ! cols ) {
			return;
		}
		function measure() {
			cols.style.setProperty( '--wbw-top-h', top.offsetHeight + 'px' );
		}
		if ( window.ResizeObserver ) {
			new ResizeObserver( measure ).observe( top );
		} else {
			window.addEventListener( 'resize', measure );
		}
		measure();
	} )();

	/* ---------------------------------------------------------------- preview */

	function settingsForSave() {
		var out = JSON.parse( JSON.stringify( state.main ) );
		out.filters = { order: JSON.stringify( state.order ), preselect: D.preselect || '' };
		return out;
	}
	function frameDoc() {
		try {
			return frame.contentDocument;
		} catch ( e ) {
			return null;
		}
	}
	function reloadFrame() {
		frameLoading.classList.add( 'is-on' );
		frame.src = state.previewUrl || D.shopUrl;
	}
	frame.addEventListener( 'load', function () {
		frameLoading.classList.remove( 'is-on' );
		previewNote = '';
		updateCount();
		placeMissingDraft();
	} );
	/* A theme that builds the shop page from its own template prints no draft: the preview puts it on the page. */
	function placeMissingDraft() {
		if ( ! previewCtl || ! state.draft || ! previewCtl.missing() ) {
			return;
		}
		var body = new FormData();
		body.append( 'draft', state.draft );
		post( 'setup', 'woobewoo_pf_setup_preview_html', body ).then( function ( r ) {
			if ( r && r.html && previewCtl.inject( r.html, r.data && r.data.css ) ) {
				previewNote = T.previewPlaced || '';
				updateCount();
			}
		} ).catch( function () {} );
	}
	function previewFailed() {
		frameLoading.classList.remove( 'is-on' );
		countLine.textContent = T.previewFailed || T.error;
		countLine.classList.add( 'wbw-preview__note--err' );
	}
	function updateCount() {
		var doc = frameDoc(), n = null;
		if ( ! doc ) {
			return;
		}
		var rc = doc.querySelector( '.woocommerce-result-count' );
		if ( rc ) {
			var m = rc.textContent.replace( /\s+/g, ' ' ).match( /(\d[\d,.]*)\s*(results?|products?)/i ) || rc.textContent.match( /of\s+(\d[\d,.]*)/i );
			if ( m ) {
				n = m[ 1 ];
			}
		}
		if ( n === null ) {
			var items = doc.querySelectorAll( '.products .product, .wc-block-product-template .wc-block-product' );
			if ( items.length ) {
				n = items.length;
			}
		}
		countLine.classList.remove( 'wbw-preview__note--err' );
		countLine.textContent = ( n !== null ? T.match.replace( '%s', num( String( n ).replace( /[^\d]/g, '' ) ) ) : T.matchNone ) + ( previewNote ? ' ' + previewNote : '' );
	}
	function schedulePreview( forceReload ) {
		clearTimeout( previewTimer );
		previewTimer = setTimeout( function () {
			var seq = ++draftSeq, body = new FormData();
			body.append( 'title', state.title );
			body.append( 'settings_json', JSON.stringify( settingsForSave() ) );
			body.append( 'draft', state.draft );
			post( 'setup', 'woobewoo_pf_setup_draft_settings', body ).then( function ( res ) {
				if ( seq !== draftSeq ) {
					return;
				}
				if ( ! res || res.error || ! res.data || ! res.data.preview_url ) {
					previewFailed(); // A blocked request or an expired login: say so instead of showing an old preview.
					return;
				}
				state.draft = res.data.draft;
				var url = res.data.preview_url, doc = frameDoc(), current = doc && doc.querySelector( '.wpfPreviewDraft' );
				document.getElementById( 'wpfBuilderPreviewLink' ).href = url;
				if ( previewCtl ) {
					previewCtl.live( url ); // A shop tab opened with "Preview on shop" reloads with the change.
				}
				if ( ! forceReload && current && state.previewUrl === url ) {
					var b2 = new FormData();
					b2.append( 'draft', state.draft );
					post( 'setup', 'woobewoo_pf_setup_preview_html', b2 ).then( function ( r2 ) {
						// Keeps the placement wrapper the page already sized (sidebar column), see wbw-preview.js.
						if ( r2 && r2.html && previewCtl && ( previewNote ? previewCtl.inject( r2.html, r2.data && r2.data.css ) : previewCtl.swap( r2.html ) ) ) {
							updateCount();
						} else {
							reloadFrame();
						}
					} ).catch( reloadFrame );
				} else {
					state.previewUrl = url;
					reloadFrame();
				}
			} ).catch( function () {
				if ( seq === draftSeq ) {
					previewFailed();
				}
			} );
		}, 400 );
	}
	Array.prototype.forEach.call( document.querySelectorAll( '.wbw-right .wbw-seg-ctl button' ), function ( b ) {
		b.addEventListener( 'click', function () {
			state.device = b.getAttribute( 'data-device' );
			Array.prototype.forEach.call( document.querySelectorAll( '.wbw-right .wbw-seg-ctl button' ), function ( x ) {
				x.setAttribute( 'aria-pressed', x === b ? 'true' : 'false' );
			} );
			if ( previewCtl ) {
				previewCtl.setDevice( state.device );
			}
			reloadFrame();
		} );
	} );

	/* ---------------------------------------------------------------- Filter engine stripe */

	var engineStripe = document.getElementById( 'wpfEngineStripe' );
	function paintEngine() {
		if ( ! engineStripe ) {
			return;
		}
		Array.prototype.forEach.call( engineStripe.querySelectorAll( '[data-engine]' ), function ( btn ) {
			btn.setAttribute( 'aria-checked', ( btn.getAttribute( 'data-engine' ) === 'legacy' ) === isClassic() ? 'true' : 'false' );
		} );
		engineStripe.classList.toggle( 'is-classic', isClassic() );
	}
	if ( engineStripe ) {
		engineStripe.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-engine]' );
			if ( ! btn || btn.getAttribute( 'aria-checked' ) === 'true' ) {
				return;
			}
			state.main.frontend_engine = btn.getAttribute( 'data-engine' );
			paintEngine();
			render(); // the engine decides which controls work
			touch( true );
		} );
		paintEngine();
	}
	/* ---------------------------------------------------------------- tooltips ([data-wbw-tip]) */

	// One tooltip for the dimmed rows and cards (it stays open while the mouse is on it, so it can be read).
	var tipEl = document.createElement( 'div' ), tipFor = null, tipTimer = null;
	tipEl.className = 'wbw-tip';
	tipEl.setAttribute( 'role', 'tooltip' );
	tipEl.id = 'wbwTip';
	tipEl.hidden = true;
	document.body.appendChild( tipEl );
	function showTip( el ) {
		clearTimeout( tipTimer );
		if ( tipFor === el && ! tipEl.hidden ) {
			return;
		}
		tipFor = el;
		tipEl.innerHTML = '<p>' + esc( el.getAttribute( 'data-wbw-tip' ) ) + '</p>';
		tipEl.hidden = false;
		var r = el.getBoundingClientRect(), w = tipEl.offsetWidth, h = tipEl.offsetHeight;
		var left = Math.min( Math.max( 8, r.left + Math.min( r.width, 360 ) / 2 - w / 2 ), window.innerWidth - w - 8 );
		var top = r.top - h - 8 < 8 ? r.bottom + 8 : r.top - h - 8;
		tipEl.style.left = Math.round( left ) + 'px';
		tipEl.style.top = Math.round( top ) + 'px';
	}
	function hideTipSoon() {
		clearTimeout( tipTimer );
		tipTimer = setTimeout( function () {
			tipEl.hidden = true;
			tipFor = null;
		}, 250 );
	}
	document.addEventListener( 'mouseover', function ( e ) {
		var el = e.target.closest && e.target.closest( '[data-wbw-tip]' );
		if ( el && el.getAttribute( 'data-wbw-tip' ) ) {
			showTip( el );
		} else if ( e.target.closest && e.target.closest( '.wbw-tip' ) ) {
			clearTimeout( tipTimer );
		} else if ( ! tipEl.hidden ) {
			hideTipSoon();
		}
	} );
	document.addEventListener( 'focusin', function ( e ) {
		var el = e.target.closest && e.target.closest( '[data-wbw-tip]' );
		if ( el && el.getAttribute( 'data-wbw-tip' ) ) {
			showTip( el );
		}
	} );
	window.addEventListener( 'scroll', function () {
		if ( ! tipEl.hidden && ! tipEl.matches( ':hover' ) ) {
			tipEl.hidden = true;
			tipFor = null;
		}
	}, true );

	/* ---------------------------------------------------------------- top bar: name, save */

	var nameInput = document.getElementById( 'wpfFilterName' );
	nameInput.addEventListener( 'input', function () {
		state.title = nameInput.value;
		if ( state.selected === 'filter' ) {
			var h = mid.querySelector( 'h2' );
			if ( h ) {
				h.textContent = state.title || 'Shop filter';
			}
		}
		state.dirty = true;
		stateTag.textContent = T.unsaved;
		stateTag.className = 'wbw-tag';
	} );
	function save() {
		var btn = document.getElementById( 'wpfBuilderSave' ), body = new FormData(), s = settingsForSave();
		btn.disabled = true;
		stateTag.textContent = T.saving;
		body.append( 'id', D.filterId );
		body.append( 'title', state.title || 'Shop filter' );
		( function add( prefix, v ) {
			if ( v !== null && typeof v === 'object' && ! Array.isArray( v ) ) {
				Object.keys( v ).forEach( function ( k ) {
					add( prefix + '[' + k + ']', v[ k ] );
				} );
			} else if ( Array.isArray( v ) ) {
				v.forEach( function ( item, i ) {
					add( prefix + '[' + i + ']', item );
				} );
			} else {
				body.append( prefix, v === null || typeof v === 'undefined' ? '' : ( v === true ? '1' : ( v === false ? '' : String( v ) ) ) );
			}
		} )( 'settings', s );
		post( 'woofilters', 'woobewoo_pf_save', body ).then( function ( res ) {
			btn.disabled = false;
			if ( res && res.data && res.data.filter ) {
				state.dirty = false;
				stateTag.textContent = T.saved;
				stateTag.className = 'wbw-tag wbw-tag--pass';
				var hist = document.querySelector( '.wpfHistoryOpen' );
				if ( hist ) {
					D.versions = ( D.versions || 0 ) + 1;
					hist.textContent = 'History (' + D.versions + ')';
				}
				document.dispatchEvent( new CustomEvent( 'wpfBuilderSaved', { detail: res.data } ) );
			} else {
				stateTag.textContent = ( res && res.errors && res.errors[ 0 ] ) || T.error;
				stateTag.className = 'wbw-tag wbw-tag--fail';
			}
		} ).catch( function () {
			btn.disabled = false;
			stateTag.textContent = T.error;
			stateTag.className = 'wbw-tag wbw-tag--fail';
		} );
	}
	document.getElementById( 'wpfBuilderSave' ).addEventListener( 'click', save );
	document.addEventListener( 'keydown', function ( e ) {
		if ( ( e.metaKey || e.ctrlKey ) && e.key === 's' ) {
			e.preventDefault();
			save();
		}
	} );
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( state.dirty ) {
			e.preventDefault();
			e.returnValue = T.leave;
		}
	} );
	document.addEventListener( 'wpfVersionRestored', function () {
		window.location.reload();
	} );

	/* ---------------------------------------------------------------- boot */

	render();
	schedulePreview( true );
} )();
