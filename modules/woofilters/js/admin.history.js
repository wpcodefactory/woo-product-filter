/**
 * Product Filter by WBW - Version history (4.0)
 *
 * Opens from the Filters home and the builder (.wpfHistoryOpen[data-filter-id]). On the
 * Filters home the list fills the #wpfHistory card below the grid, exactly as drawn; in the
 * builder it opens as a dialog. Restore puts a version back (the current state is saved as
 * a new version first by the server).
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';
	if ( typeof window.wpfHistoryData === 'undefined' ) {
		return;
	}
	var D = window.wpfHistoryData, card = document.getElementById( 'wpfHistory' ), dialog = null, currentId = 0, currentTitle = '';

	function esc( s ) {
		return String( s || '' ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'mod', 'woofilters' );
		body.append( 'pl', 'wpf' );
		body.append( 'reqType', 'ajax' );
		body.append( 'wpfNonce', D.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json();
		} );
	}
	function rowHtml( v, i ) {
		var meta = [ v.when, v.user || '', v.summary ].filter( Boolean ).join( ' · ' );
		return '<div class="wbw-list__row"><div><span class="wbw-label">' + esc( D.i18n.version ) + ' ' + esc( v.number ) + '</span> <span class="wbw-muted">· ' + esc( meta ) + '</span></div>'
			+ ( i === 0 ? '<span class="wbw-tag wbw-tag--pass">' + esc( D.i18n.liveNow ) + '</span>' : '<button type="button" class="wbw-btn wbw-btn--small" data-restore="' + esc( v.id ) + '">' + esc( D.i18n.restore ) + '</button>' )
			+ '</div>';
	}
	function listHtml( versions ) {
		if ( ! versions.length ) {
			return '<p class="wbw-muted">' + esc( D.i18n.empty ) + '</p>';
		}
		return versions.map( rowHtml ).join( '' );
	}
	function container() {
		if ( card ) {
			return card;
		}
		if ( ! dialog ) {
			dialog = document.createElement( 'div' );
			dialog.className = 'wbw wbw-dialog';
			dialog.setAttribute( 'role', 'dialog' );
			dialog.setAttribute( 'aria-modal', 'true' );
			dialog.hidden = true;
			dialog.innerHTML = '<div class="wbw-dialog__backdrop"></div><div class="wbw-dialog__panel" style="width:min(720px,100%)"><div class="wbw-card__head"><div><span class="wbw-kicker" id="wpfHistoryKicker"></span><p class="wbw-small" style="margin-top:4px">' + esc( D.i18n.intro ) + '</p></div><button type="button" class="wbw-btn wbw-btn--round" id="wpfHistoryClose" aria-label="' + esc( D.i18n.close ) + '">×</button></div><div class="wbw-list" id="wpfHistoryList" aria-live="polite"></div></div>';
			document.body.appendChild( dialog );
			dialog.querySelector( '.wbw-dialog__backdrop' ).addEventListener( 'click', close );
			dialog.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					close();
				}
			} );
		}
		return dialog;
	}
	function open( id, title ) {
		currentId = id;
		currentTitle = title;
		var c = container(), list = c.querySelector( '#wpfHistoryList' ), kicker = c.querySelector( '#wpfHistoryKicker' );
		kicker.textContent = D.i18n.history + ' · ' + title;
		list.innerHTML = '<p class="wbw-muted">' + esc( D.i18n.loading ) + '</p>';
		c.hidden = false;
		if ( c === card ) {
			c.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
		c.querySelector( '#wpfHistoryClose' ).focus();
		post( 'woobewoo_pf_versions_list', { filter_id: id } ).then( function ( res ) {
			list.innerHTML = listHtml( res && res.data && res.data.versions ? res.data.versions : [] );
		} ).catch( function () {
			list.innerHTML = '<p class="wbw-muted">' + esc( D.i18n.error ) + '</p>';
		} );
	}
	function close() {
		var c = container();
		c.hidden = true;
	}
	document.addEventListener( 'click', function ( e ) {
		var o = e.target.closest( '.wpfHistoryOpen' ), r = e.target.closest( '[data-restore]' ), x = e.target.closest( '#wpfHistoryClose' );
		if ( o ) {
			e.preventDefault();
			open( parseInt( o.getAttribute( 'data-filter-id' ), 10 ), o.getAttribute( 'data-filter-title' ) || '' );
		}
		if ( x ) {
			close();
		}
		if ( r ) {
			r.disabled = true;
			r.textContent = D.i18n.restoring;
			post( 'woobewoo_pf_versions_restore', { version_id: r.getAttribute( 'data-restore' ) } ).then( function ( res ) {
				if ( res && res.data && res.data.versions ) {
					container().querySelector( '#wpfHistoryList' ).innerHTML = listHtml( res.data.versions );
					document.dispatchEvent( new CustomEvent( 'wpfVersionRestored', { detail: res.data } ) );
					if ( card ) {
						window.setTimeout( function () {
							window.location.reload();
						}, 600 );
					}
				} else {
					r.disabled = false;
					r.textContent = D.i18n.restore;
				}
			} );
		}
	} );
} )();
