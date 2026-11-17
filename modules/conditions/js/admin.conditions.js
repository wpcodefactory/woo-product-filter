/*
 * Product Filter by WBW - builder: block conditions.
 * The four rows come from the schema (blockKeys). This script keeps the "Show this block only after a pick in"
 * select in step with the builder: the 4.0 rows stay live on a 4.0 filter, the list of blocks is rebuilt from the
 * saved settings after every save (new blocks, renamed titles), and a block can never depend on itself.
 */
( function () {
	'use strict';
	var D = window.wpfBuilderData, X = window.wpfConditionsData;
	if ( ! D || ! D.schema || ! X ) {
		return;
	}
	var keys = D.schema.blockKeys || {};
	( X.keys || [] ).forEach( function ( k ) {
		if ( keys[ k ] ) {
			keys[ k ].classicOnly = false; // the 4.0 engine reads these; the schema flagging only knows the core keys
		}
	} );
	var mid = document.getElementById( 'wpfBuilderMid' );
	if ( ! mid || ! keys[ X.parentKey ] ) {
		return;
	}

	function attrLabel( id ) {
		var found = null;
		( D.attributes || [] ).forEach( function ( a ) {
			if ( String( a.id ) === String( id ) ) {
				found = a.label;
			}
		} );
		return found;
	}
	function blockName( b ) {
		if ( b.settings && b.settings.f_title ) {
			return String( b.settings.f_title );
		}
		if ( b.id === 'wpfAttribute' && b.settings && b.settings.f_list ) {
			var a = attrLabel( b.settings.f_list );
			if ( a ) {
				return a;
			}
		}
		var n = D.blockNames && D.blockNames[ b.id ];
		return n ? ( n.shop || n.name || b.id ) : b.id;
	}

	/** Rebuild the select's options from a saved settings object ({ filters: { order: '[...]' } }). */
	function refresh( settings ) {
		var order;
		try {
			order = JSON.parse( settings.filters.order );
		} catch ( e ) {
			return;
		}
		if ( ! Array.isArray( order ) ) {
			return;
		}
		var opts = {};
		opts[ '' ] = X.always || 'Always';
		order.forEach( function ( b ) {
			if ( ! b || ! b.id || ! b.uniqId ) {
				return;
			}
			var s = b.settings || {};
			if ( s.f_enable === false || s.f_enable === '' || s.f_enable === '0' ) {
				return;
			}
			opts[ b.uniqId ] = blockName( b );
		} );
		keys[ X.parentKey ].options = opts;
	}

	/** The block being edited cannot be its own parent. */
	function fix() {
		var sel = mid.querySelector( 'select[data-key="' + X.parentKey + '"]' );
		if ( ! sel ) {
			return;
		}
		var h2 = mid.querySelector( '.wbw-h2' ), me = h2 ? h2.textContent.trim() : '';
		Array.prototype.forEach.call( sel.options, function ( o ) {
			if ( o.value !== '' && o.textContent.trim() === me ) {
				o.disabled = true;
				if ( o.selected ) {
					sel.value = '';
				}
			}
		} );
	}

	if ( window.MutationObserver ) {
		new MutationObserver( fix ).observe( mid, { childList: true } );
	}
	document.addEventListener( 'wpfBuilderSaved', function ( e ) {
		var fs = e.detail && e.detail.filterSettings;
		var s = fs && fs.settings ? fs.settings : fs;
		if ( s && s.filters && s.filters.order ) {
			refresh( s );
		}
	} );
	fix();
} )();
