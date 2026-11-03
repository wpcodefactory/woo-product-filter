/**
 * Product Filter by WBW - Switch from another filter screen (4.0.1)
 *
 * Guided switch per filter set: 1) check what comes over (name, blocks kept, titles, look; side by side
 * preview), 2) choose where ours replaces theirs and whether their plugin is turned off, 3) done, with undo.
 */
( function () {
	'use strict';
	if ( typeof window.wpfImportersData === 'undefined' ) {
		return;
	}
	var D = window.wpfImportersData, T = D.i18n, root = document.getElementById( 'wpfImporters' );
	if ( ! root ) {
		return;
	}
	var live = document.getElementById( 'wpfImportersLive' );
	var wiz = document.getElementById( 'wpfSwitch' ), body = document.getElementById( 'wpfSwitchBody' );
	var state = null;

	function post( action, data ) {
		var fd = new FormData();
		fd.append( 'action', action );
		fd.append( 'mod', 'importers' );
		fd.append( 'pl', 'wpf' );
		fd.append( 'reqType', 'ajax' );
		fd.append( 'wpfNonce', D.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			fd.append( k, data[ k ] );
		} );
		return fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } ).then( function ( r ) {
			return r.json();
		} );
	}

	function esc( s ) {
		return String( s === undefined || s === null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function fmt( s, v ) {
		return String( s ).replace( '%s', v ).replace( '%d', v );
	}

	function failed( res ) {
		// wp_send_json_error() answers (bad nonce, no rights) carry success: false and data.message.
		if ( res && ! res.error && false !== res.success && res.data ) {
			return false;
		}
		var msg = res && res.errors && res.errors.join ? res.errors.join( ' ' ) : ( res && res.data && res.data.message ) || T.failed;
		say( msg );
		return true;
	}

	function say( msg ) {
		if ( live ) {
			live.textContent = msg || '';
		}
	}

	function busy( btn, on ) {
		if ( ! btn ) {
			return;
		}
		btn.disabled = on;
		btn.classList.toggle( 'is-busy', on );
		say( on ? T.working : '' );
	}

	function table( rows, cols, tag ) {
		if ( ! rows || ! rows.length ) {
			return '';
		}
		var h = '<table class="wbw-table"><thead><tr>';
		cols.forEach( function ( c ) {
			h += '<th>' + esc( c[ 1 ] ) + '</th>';
		} );
		h += '</tr></thead><tbody>';
		rows.forEach( function ( r ) {
			h += '<tr>';
			cols.forEach( function ( c ) {
				h += '<td>' + esc( r[ c[ 0 ] ] ) + '</td>';
			} );
			h += '</tr>';
		} );
		return h + '</tbody></table>';
	}

	function frame( url, note ) {
		return url ? '<iframe src="' + esc( url ) + '" title="" loading="lazy"></iframe>' : '<div class="wbw-preview__note">' + esc( note || '' ) + '</div>';
	}

	function step( n ) {
		Array.prototype.forEach.call( wiz.querySelectorAll( '.wbw-wizard__steps li' ), function ( li ) {
			var s = +li.getAttribute( 'data-step' );
			li.classList.toggle( 'is-current', s === n );
			li.classList.toggle( 'is-done', s < n );
			if ( s === n ) {
				li.setAttribute( 'aria-current', 'step' );
			} else {
				li.removeAttribute( 'aria-current' );
			}
		} );
	}

	// New step content replaced the focused button: keyboard and screen reader users continue from its first control.
	function focusFirst() {
		var el = body.querySelector( 'input, select, button, a[href]' );
		if ( el ) {
			el.focus();
		}
	}

	/* ---------------------------------------------------------------- step 1: review */

	function choices() {
		var out = { title: ( body.querySelector( '#wpfSwitchName' ) || {} ).value || '', blocks: {} };
		Array.prototype.forEach.call( body.querySelectorAll( 'tr[data-i]' ), function ( tr ) {
			var i = tr.getAttribute( 'data-i' ), look = tr.querySelector( 'select' );
			out.blocks[ i ] = {
				include: tr.querySelector( 'input[type=checkbox]' ).checked,
				title: tr.querySelector( 'input[type=text]' ).value,
				display: look ? look.value : ''
			};
		} );
		return out;
	}

	function renderReview( d ) {
		var h = '';
		if ( d.existing ) {
			h += '<div class="wbw-notice">' + esc( fmt( T.existing, d.existing.id ) ) + ' <a href="' + esc( d.existing.edit ) + '">' + esc( T.openOld ) + '</a></div>';
		}
		h += '<label class="wbw-field"><span class="wbw-field__label">' + esc( T.name ) + '</span><input type="text" id="wpfSwitchName" class="wbw-input" value="' + esc( d.title ) + '" maxlength="128"><span class="wbw-meta">' + esc( T.nameHelp ) + '</span></label>';
		h += '<table class="wbw-table wbw-map"><thead><tr><th>' + esc( T.keep ) + '</th><th>' + esc( T.theirName ) + '</th><th>' + esc( T.becomes ) + '</th><th>' + esc( T.titleCol ) + '</th><th>' + esc( T.lookCol ) + '</th></tr></thead><tbody>';
		d.blocks.forEach( function ( b ) {
			var id = 'wpfSwitchB' + b.i, look = esc( T.fixed );
			if ( b.choices && b.choices.length ) {
				look = '<select class="wbw-select" aria-label="' + esc( T.lookCol + ': ' + b.title ) + '">';
				b.choices.forEach( function ( c ) {
					look += '<option value="' + esc( c ) + '"' + ( c === b.display ? ' selected' : '' ) + '>' + esc( T.looks[ c ] || c ) + '</option>';
				} );
				look += '</select>';
			}
			h += '<tr data-i="' + b.i + '"><td><input type="checkbox" id="' + id + '" checked aria-label="' + esc( T.keep + ': ' + b.source ) + '"></td>';
			h += '<td><label for="' + id + '">' + esc( b.source ) + '</label></td><td>' + esc( b.kind ) + '</td>';
			h += '<td><input type="text" class="wbw-input" value="' + esc( b.title ) + '" aria-label="' + esc( T.titleCol + ': ' + b.source ) + '"></td><td>' + look + '</td></tr>';
		} );
		h += '</tbody></table>';
		h += '<details class="wbw-details"' + ( d.unmapped.length ? '' : ' hidden' ) + '><summary>' + esc( T.notCome ) + ' <span class="wbw-tag wbw-tag--partial">' + d.unmapped.length + '</span></summary>';
		h += table( d.unmapped, [ [ 'setting', T.setting ], [ 'value', T.value ], [ 'ours', T.reason ] ] ) + '</details>';
		if ( ! d.unmapped.length ) {
			h += '<p class="wbw-meta">' + esc( T.allCome ) + '</p>';
		}
		h += '<div class="wbw-preview__frames" id="wpfSwitchFrames" hidden><div class="wbw-preview__col"><span class="wbw-meta">' + esc( T.theirs ) + '</span><div class="wbw-preview__frame" data-f="theirs"></div></div><div class="wbw-preview__col"><span class="wbw-meta">' + esc( T.ours ) + '</span><div class="wbw-preview__frame" data-f="ours"></div></div></div>';
		h += '<div class="wbw-actions"><button type="button" class="wbw-btn" data-act="w-preview">' + esc( T.preview ) + '</button><button type="button" class="wbw-btn wbw-btn--primary" data-act="w-create">' + esc( T.create ) + '</button></div>';
		body.innerHTML = h;
		step( 1 );
	}

	function start( btn ) {
		state = { rival: btn.getAttribute( 'data-rival' ), set: btn.getAttribute( 'data-set' ), name: btn.getAttribute( 'data-name' ) };
		busy( btn, true );
		post( 'woobewoo_pf_importers_review', { rival: state.rival, set: state.set } ).then( function ( res ) {
			busy( btn, false );
			if ( failed( res ) ) {
				return;
			}
			state.review = res.data;
			wiz.hidden = false;
			document.getElementById( 'wpfSwitchKicker' ).textContent = fmt( T.kicker, res.data.name );
			document.getElementById( 'wpfSwitchTitle' ).textContent = state.name;
			renderReview( res.data );
			wiz.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			var first = body.querySelector( '#wpfSwitchName' );
			if ( first ) {
				first.focus();
			}
		} ).catch( function () {
			busy( btn, false );
			say( T.failed );
		} );
	}

	function keptCount( c ) {
		return Object.keys( c.blocks ).filter( function ( k ) {
			return c.blocks[ k ].include;
		} ).length;
	}

	function preview( btn ) {
		var c = choices();
		if ( ! keptCount( c ) ) {
			say( T.noBlocks );
			return;
		}
		busy( btn, true );
		post( 'woobewoo_pf_importers_preview', { rival: state.rival, set: state.set, choices: JSON.stringify( c ) } ).then( function ( res ) {
			busy( btn, false );
			if ( failed( res ) ) {
				return;
			}
			var f = document.getElementById( 'wpfSwitchFrames' );
			f.hidden = false;
			btn.focus(); // the button was disabled while loading, which dropped the keyboard focus
			f.querySelector( '[data-f=theirs]' ).innerHTML = frame( res.data.theirs, res.data.note );
			f.querySelector( '[data-f=ours]' ).innerHTML = frame( res.data.ours, T.nothing );
		} ).catch( function () {
			busy( btn, false );
			say( T.failed );
		} );
	}

	function create( btn ) {
		var c = choices();
		if ( ! keptCount( c ) ) {
			say( T.noBlocks );
			return;
		}
		busy( btn, true );
		post( 'woobewoo_pf_importers_import', { rival: state.rival, set: state.set, choices: JSON.stringify( c ) } ).then( function ( res ) {
			busy( btn, false );
			if ( failed( res ) ) {
				return;
			}
			var r = res.data.imported && res.data.imported[ 0 ];
			if ( ! r || r.error ) {
				say( r && r.error ? r.error : T.failed );
				return;
			}
			state.filter = r.id;
			state.edit = r.edit;
			placements();
		} ).catch( function () {
			busy( btn, false );
			say( T.failed );
		} );
	}

	/* ---------------------------------------------------------------- step 2: where it shows */

	function placements() {
		step( 2 );
		body.innerHTML = '<p class="wbw-meta">' + esc( T.scanning ) + '…</p>';
		post( 'woobewoo_pf_importers_placements', { rival: state.rival, set: state.set } ).then( function ( res ) {
			if ( failed( res ) ) {
				return;
			}
			var d = res.data, h = '';
			h += '<p>' + esc( d.places.length ? T.placesH : T.noPlaces ) + '</p><ul class="wbw-places" role="list">';
			d.places.forEach( function ( p, n ) {
				var id = 'wpfSwitchP' + n;
				h += '<li><input type="checkbox" id="' + id + '" value="' + esc( p.key ) + '" checked> <label for="' + id + '"><strong>' + esc( p.label ) + '</strong> <span class="wbw-meta">' + esc( p.where ) + ( p.count > 1 ? ' · ' + p.count : '' ) + '</span></label>';
				if ( p.view ) {
					h += ' <a href="' + esc( p.view ) + '" target="_blank" rel="noopener">' + esc( T.view ) + '</a>';
				}
				if ( p.edit ) {
					h += ' <a href="' + esc( p.edit ) + '" target="_blank" rel="noopener">' + esc( T.edit ) + '</a>';
				}
				if ( p.note ) {
					h += '<div class="wbw-notice">' + esc( p.note ) + '</div>';
				}
				h += '</li>';
			} );
			if ( ! d.places.length ) {
				h += '<li><input type="checkbox" id="wpfSwitchAuto" value="ourAuto" checked> <label for="wpfSwitchAuto">' + esc( T.autoShop ) + '</label></li>';
			}
			h += '</ul>';
			if ( d.active ) {
				h += '<p><input type="checkbox" id="wpfSwitchOff"' + ( d.sets > 1 ? '' : ' checked' ) + '> <label for="wpfSwitchOff">' + esc( fmt( T.deact, state.review.name ) ) + '</label>';
				if ( d.sets > 1 ) {
					h += '<br><span class="wbw-meta">' + esc( T.deactMore ) + '</span>';
				}
				h += '</p>';
			}
			h += '<div class="wbw-actions"><button type="button" class="wbw-btn" data-act="w-later">' + esc( T.later ) + '</button><button type="button" class="wbw-btn wbw-btn--primary" data-act="w-switch">' + esc( T.switchNow ) + '</button></div>';
			body.innerHTML = h;
			focusFirst();
		} ).catch( function () {
			say( T.failed );
		} );
	}

	function doSwitch( btn ) {
		var keys = [], ourAuto = false;
		Array.prototype.forEach.call( body.querySelectorAll( '.wbw-places input:checked' ), function ( i ) {
			if ( i.value === 'ourAuto' ) {
				ourAuto = true;
			} else {
				keys.push( i.value );
			}
		} );
		var off = body.querySelector( '#wpfSwitchOff' );
		busy( btn, true );
		post( 'woobewoo_pf_importers_switch', { rival: state.rival, set: state.set, filter: state.filter, places: JSON.stringify( ourAuto ? keys.concat( [ 'ourAuto' ] ) : keys ), deactivate: off && off.checked ? '1' : '0' } ).then( function ( res ) {
			busy( btn, false );
			if ( failed( res ) ) {
				return;
			}
			done( res.data.switch );
		} ).catch( function () {
			busy( btn, false );
			say( T.failed );
		} );
	}

	/* ---------------------------------------------------------------- step 3: done */

	function done( sw ) {
		step( 3 );
		var h = '';
		if ( sw ) {
			h += '<h3>' + esc( T.doneH ) + '</h3><p>' + sw.places + ' ' + esc( T.changed ) + '. ' + ( sw.plugins ? esc( T.pluginOff ) + ' ' : '' ) + esc( T.checkIt ) + '</p>';
			if ( sw.skipped && sw.skipped.length ) {
				h += '<p class="wbw-meta">' + esc( T.skipped ) + ': ' + sw.skipped.map( function ( s ) {
					return esc( s.why );
				} ).join( ', ' ) + '</p>';
			}
		} else {
			h += '<h3>' + esc( T.draftH ) + '</h3><p>' + esc( T.draftP ) + '</p>';
		}
		h += '<div class="wbw-actions"><a class="wbw-btn wbw-btn--primary" href="' + esc( state.edit ) + '">' + esc( T.open ) + '</a>';
		if ( sw ) {
			h += '<button type="button" class="wbw-btn" data-act="undo" data-switch="' + esc( sw.id ) + '">' + esc( T.undo ) + '</button>';
		}
		h += '</div>';
		body.innerHTML = h;
		focusFirst();
		say( sw ? T.doneH : T.draftH );
	}

	function undo( btn ) {
		if ( ! window.confirm( T.undoAsk ) ) {
			return;
		}
		busy( btn, true );
		post( 'woobewoo_pf_importers_undo', { 'switch': btn.getAttribute( 'data-switch' ) } ).then( function ( res ) {
			busy( btn, false );
			if ( failed( res ) ) {
				return;
			}
			say( res.messages && res.messages.join ? res.messages.join( ' ' ) : '' );
			btn.outerHTML = '<span class="wbw-tag">' + esc( res.messages && res.messages.join ? res.messages.join( ' ' ) : '' ) + '</span>';
		} ).catch( function () {
			busy( btn, false );
			say( T.failed );
		} );
	}

	/* ---------------------------------------------------------------- "Import all as drafts" (kept) */

	function importAll( btn ) {
		busy( btn, true );
		post( 'woobewoo_pf_importers_import', { rival: btn.getAttribute( 'data-rival' ), set: 'all' } ).then( function ( res ) {
			busy( btn, false );
			var panel = document.getElementById( 'wpfImportersReport' ), out = document.getElementById( 'wpfImportersReportBody' );
			if ( failed( res ) || ! res.data.imported ) {
				return;
			}
			panel.hidden = false;
			var h = '';
			res.data.imported.forEach( function ( r ) {
				if ( r.error ) {
					h += '<div class="wbw-report__item"><span class="wbw-tag wbw-tag--fail">' + esc( r.error ) + '</span></div>';
					return;
				}
				h += '<div class="wbw-report__item"><div class="wbw-report__item-head"><strong>' + esc( r.title ) + '</strong> <span class="wbw-tag wbw-tag--publish">' + esc( T.imported ) + '</span> <span class="wbw-meta">#' + r.id + ' · ' + r.blocks + ' ' + esc( T.blocks ) + '</span>';
				if ( r.edit ) {
					h += ' <a class="wbw-btn wbw-btn--small" href="' + esc( r.edit ) + '">' + esc( T.open ) + '</a>';
				}
				h += '</div>' + table( r.unmapped, [ [ 'setting', T.setting ], [ 'value', T.value ], [ 'ours', T.reason ] ] ) + '</div>';
			} );
			out.innerHTML = h;
			say( res.messages && res.messages.join ? res.messages.join( ' ' ) : '' );
			panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} ).catch( function () {
			busy( btn, false );
			say( T.failed );
		} );
	}

	root.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '[data-act]' ) : null;
		if ( ! btn ) {
			return;
		}
		var act = btn.getAttribute( 'data-act' );
		if ( act === 'start' ) {
			start( btn );
		} else if ( act === 'w-preview' ) {
			preview( btn );
		} else if ( act === 'w-create' ) {
			create( btn );
		} else if ( act === 'w-switch' ) {
			doSwitch( btn );
		} else if ( act === 'w-later' ) {
			done( null );
		} else if ( act === 'undo' ) {
			undo( btn );
		} else if ( act === 'close' ) {
			wiz.hidden = true;
			state = null;
		} else if ( act === 'import' ) {
			importAll( btn );
		}
	} );
} )();
