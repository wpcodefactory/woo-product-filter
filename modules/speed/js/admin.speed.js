/**
 * Product Filter by WBW - Speed and SEO + Help screen (4.0)
 *
 * Switches save as you change them. Index rebuild with a live segmented meter. Self test,
 * ticket form, AI shopping readiness card, accessibility checks per filter, legacy options.
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';
	if ( typeof window.wpfSpeedData === 'undefined' ) {
		return;
	}
	var D = window.wpfSpeedData, T = D.i18n;

	function esc( s ) {
		return String( s || '' ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function post( mod, action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'mod', mod );
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

	/* switches: save one */
	document.addEventListener( 'click', function ( e ) {
		var t = e.target.closest( '.wbw-toggle[data-opt]' );
		if ( ! t ) {
			return;
		}
		var on = t.getAttribute( 'aria-checked' ) !== 'true';
		t.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		post( 'speed', 'woobewoo_pf_speed_save_one', { key: t.getAttribute( 'data-opt' ), value: on ? '1' : '0' } ).then( function ( res ) {
			if ( res && res.data && res.data.robots ) {
				var pre = document.getElementById( 'wpfRobotsPreview' );
				if ( pre ) {
					pre.textContent = res.data.robots;
				}
			}
		} ).catch( function () {
			t.setAttribute( 'aria-checked', on ? 'false' : 'true' );
		} );
	} );

	/* index rebuild with live progress */
	var meter = document.getElementById( 'wpfIndexMeter' ), msg = document.getElementById( 'wpfIndexMsg' ), state = document.getElementById( 'wpfIndexState' ), card = document.getElementById( 'wpfIndexCard' ), rebuild = document.getElementById( 'wpfIndexRebuild' ), pollTimer = null;
	function paint( h ) {
		if ( ! h || ! meter ) {
			return;
		}
		var on = Math.round( ( h.percent || 0 ) / 100 * 13 );
		Array.prototype.forEach.call( meter.children, function ( s, i ) {
			s.classList.toggle( 'is-on', i < on );
		} );
		meter.setAttribute( 'aria-valuenow', h.percent || 0 );
		document.getElementById( 'wpfIndexIndexed' ).textContent = Number( h.indexed || 0 ).toLocaleString();
		if ( h.last_query_ms ) {
			document.getElementById( 'wpfIndexQuery' ).textContent = Math.round( h.last_query_ms ) + ' ms';
		}
		state.textContent = h.running ? T.rebuilding : ( h.percent >= 95 ? T.fresh : T.needs );
		state.className = 'wbw-tag ' + ( h.running || h.percent < 95 ? 'wbw-tag--partial' : 'wbw-tag--pass' );
		card.setAttribute( 'data-running', h.running ? '1' : '0' );
		rebuild.disabled = !! h.running;
		if ( h.running || h.scheduled ) {
			clearTimeout( pollTimer );
			pollTimer = setTimeout( poll, 2500 );
		} else if ( msg.textContent ) {
			msg.textContent = T.done;
			document.getElementById( 'wpfIndexLast' ).textContent = T.justNow;
		}
	}
	function poll() {
		post( 'speed', 'woobewoo_pf_speed_index_status', {} ).then( function ( res ) {
			if ( res && res.data ) {
				paint( res.data );
			}
		} ).catch( function () {} );
	}
	if ( rebuild ) {
		rebuild.addEventListener( 'click', function () {
			rebuild.disabled = true;
			msg.textContent = T.started;
			post( 'speed', 'woobewoo_pf_speed_rebuild', {} ).then( function ( res ) {
				if ( res && res.data ) {
					res.data.scheduled = true;
					paint( res.data );
				}
			} ).catch( function () {
				msg.textContent = T.error;
				rebuild.disabled = false;
			} );
		} );
		if ( card.getAttribute( 'data-running' ) === '1' ) {
			pollTimer = setTimeout( poll, 2500 );
		}
	}

	/* clear saved counts */
	var clearBtn = document.getElementById( 'wpfClearCache' );
	if ( clearBtn ) {
		clearBtn.addEventListener( 'click', function () {
			var out = document.getElementById( 'wpfIndexMsg' );
			clearBtn.disabled = true;
			post( 'speed', 'woobewoo_pf_speed_clear_cache', {} ).then( function ( res ) {
				clearBtn.disabled = false;
				if ( out ) {
					out.textContent = res && res.messages && res.messages[ 0 ] ? res.messages[ 0 ] : ( res && res.errors && res.errors[ 0 ] ? res.errors[ 0 ] : '' );
				}
			} ).catch( function () {
				clearBtn.disabled = false;
				if ( out ) {
					out.textContent = T.error;
				}
			} );
		} );
	}

	/* self test */
	var selfBtn = document.getElementById( 'wpfSelfTestRun' ), selfOut = document.getElementById( 'wpfSelfTestResult' );
	if ( selfBtn ) {
		selfBtn.addEventListener( 'click', function () {
			selfBtn.disabled = true;
			selfOut.innerHTML = '<div class="wbw-banner wbw-banner--note">' + esc( T.running ) + '</div>';
			post( 'help', 'woobewoo_pf_help_selftest', {} ).then( function ( res ) {
				var rows = ( res && res.data && res.data.rows ) ? res.data.rows : [], fails = rows.filter( function ( r ) {
					return r.state === 'fail';
				} ), partial = rows.filter( function ( r ) {
					return r.state === 'partial';
				} ), loop = rows.filter( function ( r ) {
					return /loop|grid/i.test( r.label );
				} )[ 0 ];
				var tone = fails.length ? 'fail' : ( partial.length ? 'partial' : 'pass' ), text;
				if ( tone === 'pass' ) {
					text = ( loop && loop.detail ? 'Your product grid was found: ' + loop.detail + '. ' : '' ) + 'No conflicts.';
				} else {
					// Say what the check is for first (the grid), then what needs a look.
					text = ( loop && loop.state === 'pass' ? 'Your product grid was found' + ( loop.detail ? ': ' + loop.detail : '' ) + '. ' : '' ) + ( fails.concat( partial ) ).map( function ( r ) {
						return r.label + ( r.detail ? ': ' + r.detail : '' );
					} ).join( ' · ' );
				}
				selfOut.innerHTML = '<div class="wbw-banner wbw-banner--' + tone + '">' + esc( text ) + '</div>';
			} ).catch( function () {
				selfOut.innerHTML = '<div class="wbw-banner wbw-banner--fail">' + esc( T.error ) + '</div>';
			} ).then( function () {
				selfBtn.disabled = false;
			} );
		} );
	}

	/* ticket */
	var open = document.getElementById( 'wpfTicketOpen' ), form = document.getElementById( 'wpfTicketForm' );
	if ( open && form ) {
		open.addEventListener( 'click', function () {
			form.hidden = ! form.hidden;
			if ( ! form.hidden ) {
				document.getElementById( 'wpfTicketSubject' ).focus();
			}
		} );
		var attach = document.getElementById( 'wpfTicketAttach' );
		attach.addEventListener( 'click', function () {
			attach.setAttribute( 'aria-checked', attach.getAttribute( 'aria-checked' ) === 'true' ? 'false' : 'true' );
		} );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var out = document.getElementById( 'wpfTicketMsg' ), btn = document.getElementById( 'wpfTicketSend' );
			btn.disabled = true;
			out.textContent = T.sending;
			post( 'speed', 'woobewoo_pf_speed_ticket', {
				subject: document.getElementById( 'wpfTicketSubject' ).value,
				message: document.getElementById( 'wpfTicketMessage' ).value,
				email: document.getElementById( 'wpfTicketEmail' ).value,
				to: document.getElementById( 'wpfTicketTo' ).value,
				attach: attach.getAttribute( 'aria-checked' ) === 'true' ? '1' : '0'
			} ).then( function ( res ) {
				out.textContent = ( res && ( ( res.messages && res.messages[ 0 ] ) || ( res.errors && res.errors[ 0 ] ) ) ) || T.error;
				if ( res && res.data && res.data.sent ) {
					form.reset();
				}
			} ).catch( function () {
				out.textContent = T.error;
			} ).then( function () {
				btn.disabled = false;
			} );
		} );
	}

	/* accessibility per filter, copy buttons */
	document.addEventListener( 'click', function ( e ) {
		var run = e.target.closest( '[data-a11y-run]' ), copy = e.target.closest( '[data-copy]' );
		if ( copy ) {
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( copy.getAttribute( 'data-copy' ) );
			}
			copy.textContent = T.copied;
			setTimeout( function () {
				copy.textContent = T.copy;
			}, 1500 );
		}
		if ( run ) {
			var row = run.closest( '.wbw-list__row' ), tag = row.querySelector( '[data-a11y-tag]' ), detail = row.querySelector( '[data-a11y-detail]' );
			run.disabled = true;
			tag.textContent = T.checking;
			post( 'speed', 'woobewoo_pf_speed_a11y', { filter_id: run.getAttribute( 'data-a11y-run' ) } ).then( function ( res ) {
				var rows = ( res && res.data && res.data.rows ) ? res.data.rows : [], failed = rows.filter( function ( r ) {
					return ! r.pass;
				} );
				tag.textContent = failed.length ? failed.length + ' to fix' : T.pass;
				tag.className = 'wbw-tag ' + ( failed.length ? 'wbw-tag--partial' : 'wbw-tag--pass' );
				detail.innerHTML = rows.map( function ( r ) {
					return '<div class="wbw-list__row"><span class="wbw-small">' + esc( r.label ) + ( r.detail ? ' · ' + esc( r.detail ) : '' ) + '</span><span class="wbw-tag ' + ( r.pass ? 'wbw-tag--pass' : 'wbw-tag--partial' ) + '">' + ( r.pass ? T.pass : T.fail ) + '</span></div>';
				} ).join( '' );
			} ).catch( function () {
				tag.textContent = T.error;
			} ).then( function () {
				run.disabled = false;
			} );
		}
	} );

	/* AI shopping readiness: score out of 100 from the Free readiness model (abilities module), fixes per line,
	   "Check again" and the one click llms.txt creator (shown while the llms check fails). */
	var rdRun = document.getElementById( 'wpfReadinessRun' ), rdLlms = document.getElementById( 'wpfReadinessLlms' ), rdMsg = document.getElementById( 'wpfReadinessMsg' ),
		rdTag = document.getElementById( 'wpfReadinessTag' ), rdMeter = document.getElementById( 'wpfReadinessMeter' ), rdRows = document.getElementById( 'wpfReadinessRows' );
	function paintReadiness( d ) {
		var score = Math.max( 0, Math.min( 100, parseInt( d.score, 10 ) || 0 ) ), checks = Array.isArray( d.checks ) ? d.checks : [], llmsFails = false;
		rdTag.textContent = T.score.replace( '%d', String( score ) );
		rdTag.className = 'wbw-tag ' + ( score >= 80 ? 'wbw-tag--pass' : ( score >= 40 ? 'wbw-tag--partial' : 'wbw-tag--fail' ) );
		var on = Math.round( score / 100 * 13 );
		Array.prototype.forEach.call( rdMeter.children, function ( s, i ) {
			s.classList.toggle( 'is-on', i < on );
		} );
		rdMeter.setAttribute( 'aria-valuenow', score );
		rdRows.innerHTML = checks.map( function ( c ) {
			var pass = c.status === 'pass';
			if ( c.id === 'llms' && ! pass ) {
				llmsFails = true;
			}
			return '<div class="wbw-list__row"><span class="wbw-small">' + esc( c.label ) + ( ! pass && c.fix ? ' · ' + esc( c.fix ) : '' ) + '</span><span class="wbw-tag ' + ( pass ? 'wbw-tag--pass' : 'wbw-tag--partial' ) + '">' + ( pass ? T.pass : T.fail ) + '</span></div>';
		} ).join( '' );
		if ( rdLlms ) {
			rdLlms.hidden = ! llmsFails;
		}
	}
	function checkReadiness() {
		rdRun.disabled = true;
		rdMsg.textContent = T.checking;
		return post( 'abilities', 'woobewoo_pf_readiness', {} ).then( function ( res ) {
			if ( res && res.data && typeof res.data.score !== 'undefined' ) {
				paintReadiness( res.data );
				rdMsg.textContent = '';
			} else {
				rdMsg.textContent = ( res && res.errors && res.errors[ 0 ] ) || T.error;
			}
		} ).catch( function () {
			rdMsg.textContent = T.error;
		} ).then( function () {
			rdRun.disabled = false;
		} );
	}
	if ( rdRun && rdTag && rdMeter && rdRows ) {
		rdRun.addEventListener( 'click', checkReadiness );
		if ( rdLlms ) {
			rdLlms.addEventListener( 'click', function () {
				rdLlms.disabled = true;
				rdMsg.textContent = T.saving;
				post( 'abilities', 'woobewoo_pf_create_llms', {} ).then( function ( res ) {
					rdMsg.textContent = ( res && res.messages && res.messages[ 0 ] ) || ( res && res.errors && res.errors[ 0 ] ) || T.llmsDone;
					return checkReadiness();
				} ).catch( function () {
					rdMsg.textContent = T.error;
				} ).then( function () {
					rdLlms.disabled = false;
				} );
			} );
		}
	}

	/* legacy options: save as you change */
	var legacyMsg = document.getElementById( 'wpfLegacyMsg' );
	function saveLegacy( key, value ) {
		legacyMsg.textContent = T.saving;
		post( 'speed', 'woobewoo_pf_speed_save_legacy', { key: key, value: value } ).then( function ( res ) {
			legacyMsg.textContent = ( res && res.messages && res.messages[ 0 ] ) || T.saved;
		} ).catch( function () {
			legacyMsg.textContent = T.error;
		} );
	}
	document.addEventListener( 'click', function ( e ) {
		var t = e.target.closest( '.wbw-toggle[data-legacy]' ), a = e.target.closest( '[data-legacy-action]' );
		if ( t ) {
			var on = t.getAttribute( 'aria-checked' ) !== 'true';
			t.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			saveLegacy( t.getAttribute( 'data-legacy' ), on ? '1' : '0' );
		}
		if ( a && rebuild ) {
			rebuild.click();
			card.scrollIntoView( { behavior: 'smooth' } );
		}
	} );
	document.addEventListener( 'change', function ( e ) {
		var el = e.target;
		if ( el.matches( 'select[data-legacy], input[data-legacy]' ) ) {
			saveLegacy( el.getAttribute( 'data-legacy' ), el.value );
		}
	} );

	if ( D.screen === 'help' && window.location.hash !== '#advanced' ) {
		var helpCard = document.getElementById( 'help' );
		if ( helpCard ) {
			helpCard.scrollIntoView( { block: 'start' } );
		}
	}
	if ( D.screen === 'settings' ) {
		var adv = document.getElementById( 'advanced' );
		if ( adv ) {
			adv.open = true;
			adv.scrollIntoView( { block: 'start' } );
		}
	}
} )();
