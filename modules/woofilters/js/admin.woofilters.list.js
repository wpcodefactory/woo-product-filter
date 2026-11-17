/**
 * Product Filter by WBW - Filters home (4.0)
 *
 * Search, New filter, Duplicate, Delete, the first week card and the Import or export card
 * (drop zone, Free JSON import through woobewoo_pf_save, export and pick mode through the
 * handlers printed in the card: Free's own `woobewoo_pf_export_filters` since 4.0.0, or the ones
 * Pro registers on `woobewoo_pf_filters_import_export`, which win when present). Vanilla JS; the save,
 * duplicate and delete requests go to the same endpoints as 3.x.
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';
	if ( typeof window.wpfListData === 'undefined' ) {
		return;
	}
	var D = window.wpfListData, I18N = {
		ieAdding: 'Adding %1$s of %2$s…', ieUploading: 'Reading the file…', ieAdded: '%s added.', ieSkipped: '%s skipped: no blocks or not a filter.', ieDone: 'Done.',
		ieEmpty: 'No filters in this file.', ieNotJson: 'That is not a .json file.', ieNotExport: 'This is not a filter export file. Export one from Filters first.',
		ieExporting: 'Making the file…', ieExported: '%s exported.', ieExportSelected: 'Export %s selected', iePick: 'Pick filters to export', ieStopPicking: 'Stop picking',
		imported: 'Imported filter', filterOne: 'filter', filterMany: 'filters'
	};
	D.i18n = D.i18n || {};
	Object.keys( I18N ).forEach( function ( k ) {
		if ( typeof D.i18n[ k ] === 'undefined' ) {
			D.i18n[ k ] = I18N[ k ];
		}
	} );

	function post( mod, action, data ) {
		var body = data instanceof FormData ? data : new FormData();
		body.append( 'action', action );
		body.append( 'mod', mod );
		body.append( 'pl', 'wpf' );
		body.append( 'reqType', 'ajax' );
		body.append( 'wpfNonce', D.nonce );
		if ( ! ( data instanceof FormData ) ) {
			Object.keys( data || {} ).forEach( function ( k ) {
				body.append( k, data[ k ] );
			} );
		}
		return fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json();
		} );
	}

	/* search */
	var search = document.getElementById( 'wpfFilterSearch' ), cards = document.getElementById( 'wpfFilterCards' ), noMatch = document.getElementById( 'wpfFilterCardsNoMatch' );
	if ( search && cards ) {
		search.addEventListener( 'input', function () {
			var q = search.value.trim().toLowerCase(), shown = 0;
			Array.prototype.forEach.call( cards.querySelectorAll( '.wbw-fcard' ), function ( c ) {
				var hit = ! q || ( c.getAttribute( 'data-title' ) || '' ).indexOf( q ) >= 0 || c.textContent.toLowerCase().indexOf( q ) >= 0;
				c.hidden = ! hit;
				if ( hit ) {
					shown++;
				}
			} );
			noMatch.hidden = shown > 0;
		} );
	}

	/* name dialog (new / duplicate) */
	var dialog = document.getElementById( 'wpfNameDialog' ), input = document.getElementById( 'wpfNameInput' ), dupId = document.getElementById( 'wpfNameDuplicateId' ), msg = document.getElementById( 'wpfNameMsg' ), lastFocus = null;
	function openDialog( title, duplicateId, name ) {
		if ( ! dialog ) {
			return;
		}
		lastFocus = document.activeElement;
		document.getElementById( 'wpfNameDialogKicker' ).textContent = duplicateId ? D.i18n.duplicate : D.i18n.newFilter;
		document.getElementById( 'wpfNameDialogTitle' ).textContent = duplicateId ? D.i18n.duplicateTitle : D.i18n.newTitle;
		document.getElementById( 'wpfNameSubmit' ).textContent = duplicateId ? D.i18n.duplicateBtn : D.i18n.newBtn;
		input.value = name || '';
		dupId.value = duplicateId || '';
		msg.textContent = '';
		dialog.hidden = false;
		input.focus();
	}
	function closeDialog() {
		if ( dialog ) {
			dialog.hidden = true;
		}
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}
	[ 'wpfNewFilter', 'wpfNewFilter2' ].forEach( function ( id ) {
		var b = document.getElementById( id );
		if ( b ) {
			b.addEventListener( 'click', function () {
				openDialog( '', '', '' );
			} );
		}
	} );
	if ( window.location.hash === '#new' ) {
		openDialog( '', '', '' );
	}
	if ( dialog ) {
		document.getElementById( 'wpfNameCancel' ).addEventListener( 'click', closeDialog );
		dialog.querySelector( '.wbw-dialog__backdrop' ).addEventListener( 'click', closeDialog );
		dialog.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				closeDialog();
			}
		} );
		document.getElementById( 'wpfNameForm' ).addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var data = { title: input.value.trim() || D.i18n.defaultName, 'settings[filters][order]': '[]' };
			if ( dupId.value ) {
				data.duplicateId = dupId.value;
				delete data[ 'settings[filters][order]' ];
			}
			msg.textContent = D.i18n.saving;
			post( 'woofilters', 'woobewoo_pf_save', data ).then( function ( res ) {
				if ( res && res.data && res.data.edit_link ) {
					window.location.href = dupId.value ? window.location.href.replace( /#.*$/, '' ) : res.data.edit_link;
					if ( dupId.value ) {
						window.location.reload();
					}
				} else {
					msg.textContent = ( res && res.errors && res.errors[ 0 ] ) || D.i18n.error;
				}
			} ).catch( function () {
				msg.textContent = D.i18n.error;
			} );
		} );
	}

	/* duplicate, delete */
	document.addEventListener( 'click', function ( e ) {
		var dup = e.target.closest( '.wpfDuplicateFilter' ), del = e.target.closest( '.wpfDeleteFilter' );
		if ( dup ) {
			openDialog( '', dup.getAttribute( 'data-filter-id' ), dup.getAttribute( 'data-filter-title' ) + ' ' + D.i18n.copy );
		}
		if ( del ) {
			if ( ! window.confirm( D.i18n.confirmDelete.replace( '%s', del.getAttribute( 'data-filter-title' ) ) ) ) {
				return;
			}
			del.disabled = true;
			post( 'woofilters', 'woobewoo_pf_delete_by_id', { id: del.getAttribute( 'data-filter-id' ) } ).then( function () {
				var card = del.closest( '.wbw-fcard' );
				if ( card ) {
					card.remove();
				}
				if ( ! document.querySelector( '#wpfFilterCards .wbw-fcard' ) ) {
					window.location.reload();
				}
			} );
		}
	} );

	/* ---------------------------------------------------------------- import or export card */
	var ie = document.getElementById( 'wpfImportExport' ), ieToggle = document.getElementById( 'wpfImportExportToggle' ),
		drop = document.getElementById( 'wpfImportDrop' ), fileInput = document.getElementById( 'wpfImportFile' ), importStatus = document.getElementById( 'wpfImportStatus' ),
		exportAll = document.getElementById( 'wpfExportAll' ), exportPick = document.getElementById( 'wpfExportPick' ), exportSelected = document.getElementById( 'wpfExportSelected' ), exportStatus = document.getElementById( 'wpfExportStatus' ),
		handlers = { import: null, export: null }, busy = false;
	/* Handlers: every <div class="wpf-ie-handler" data-handlers="{json}"> printed inside the card (Free export first, then Pro). Last one wins per key. */
	Array.prototype.forEach.call( document.querySelectorAll( '.wpf-ie-handler[data-handlers]' ), function ( el ) {
		try {
			var h = JSON.parse( el.getAttribute( 'data-handlers' ) || '{}' );
			if ( h.import && h.import.action ) {
				handlers.import = h.import;
			}
			if ( h.export && h.export.action ) {
				handlers.export = h.export;
			}
		} catch ( err ) {}
	} );
	function status( el, text, tone ) {
		if ( ! el ) {
			return;
		}
		el.textContent = text || '';
		el.className = 'wbw-ie__status' + ( tone ? ' is-' + tone : '' );
	}
	function plural( n, one, many ) {
		return String( n ) + ' ' + ( n === 1 ? one : many );
	}
	/* Open or close the card; closing also leaves pick mode and gives the focus back to the header pill. */
	function setCard( open, focusBack ) {
		ie.hidden = ! open;
		ieToggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( ! open && document.body.classList.contains( 'is-selecting' ) ) {
			setSelecting( false );
		}
		if ( ! open && focusBack ) {
			ieToggle.focus();
		}
	}
	if ( ie && ieToggle ) {
		ieToggle.addEventListener( 'click', function () {
			setCard( ie.hidden, false );
		} );
		var ieClose = document.getElementById( 'wpfImportExportClose' );
		if ( ieClose ) {
			ieClose.addEventListener( 'click', function () {
				setCard( false, true );
			} );
		}
		document.addEventListener( 'keydown', function ( e ) {
			var dlg = document.getElementById( 'wpfNameDialog' );
			if ( e.key !== 'Escape' || ie.hidden || ( dlg && ! dlg.hidden ) ) {
				return;
			}
			setCard( false, true );
		} );
		if ( window.location.hash === '#import' ) {
			ieToggle.click();
		}
	}

	/* Import: a Pro handler posts the file to its own endpoint; without one, Free reads the JSON here and saves every
	   filter through woobewoo_pf_save (new rows only, nothing overwritten). */
	function readFile( file ) {
		return new Promise( function ( resolve, reject ) {
			var r = new FileReader();
			r.onload = function () {
				resolve( String( r.result || '' ) );
			};
			r.onerror = function () {
				reject( new Error( 'read' ) );
			};
			r.readAsText( file );
		} );
	}
	function parseExport( text ) {
		var data;
		try {
			data = JSON.parse( text );
		} catch ( err ) {
			return null;
		}
		if ( ! data || typeof data !== 'object' || typeof data.format !== 'string' || data.format.indexOf( 'wpf-filters/' ) !== 0 || ! Array.isArray( data.filters ) ) {
			return null;
		}
		return data;
	}
	function cleanOrder( order ) {
		var list = order;
		if ( typeof list === 'string' ) {
			try {
				list = JSON.parse( list );
			} catch ( err ) {
				list = [];
			}
		}
		if ( ! Array.isArray( list ) ) {
			list = Object.keys( list || {} ).map( function ( k ) {
				return list[ k ];
			} );
		}
		return list.filter( function ( b ) {
			return b && typeof b === 'object' && /^wpf[A-Za-z]+$/.test( String( b.id || '' ) );
		} ).map( function ( b, i ) {
			// The block id is kept when well formed: finder steps, questions and phone looks are keyed by it.
			var uniq = /^wpf_[A-Za-z0-9_]{1,40}$/.test( String( b.uniqId || '' ) ) ? String( b.uniqId ) : 'wpf_' + Date.now().toString( 36 ) + i + Math.floor( Math.random() * 1000 );
			return { id: String( b.id ), uniqId: uniq, settings:b.settings && typeof b.settings === 'object' && ! Array.isArray( b.settings ) ? b.settings : {} };
		} );
	}
	function appendDeep( body, prefix, v ) {
		if ( v !== null && typeof v === 'object' && ! Array.isArray( v ) ) {
			Object.keys( v ).forEach( function ( k ) {
				appendDeep( body, prefix + '[' + k + ']', v[ k ] );
			} );
		} else if ( Array.isArray( v ) ) {
			v.forEach( function ( item, i ) {
				appendDeep( body, prefix + '[' + i + ']', item );
			} );
		} else {
			body.append( prefix, v === null || typeof v === 'undefined' ? '' : ( v === true ? '1' : ( v === false ? '' : String( v ) ) ) );
		}
	}
	function importFree( data ) {
		var filters = data.filters.filter( function ( f ) {
			return f && typeof f === 'object' && f.settings && typeof f.settings === 'object';
		} ), added = 0, skipped = data.filters.length - filters.length, i = 0;
		if ( ! filters.length ) {
			status( importStatus, D.i18n.ieEmpty, 'err' );
			return Promise.resolve( false );
		}
		function next() {
			if ( i >= filters.length ) {
				status( importStatus, D.i18n.ieAdded.replace( '%s', plural( added, D.i18n.filterOne, D.i18n.filterMany ) ) + ( skipped ? ' ' + D.i18n.ieSkipped.replace( '%s', String( skipped ) ) : '' ), 'ok' );
				return true;
			}
			var f = filters[ i++ ], settings = JSON.parse( JSON.stringify( f.settings ) ), order = cleanOrder( settings.filters && settings.filters.order ? settings.filters.order : [] );
			if ( ! order.length ) {
				skipped++;
				return next();
			}
			settings.filters = { order: JSON.stringify( order ), preselect: settings.filters && settings.filters.preselect ? String( settings.filters.preselect ) : '' };
			delete settings.wbw_draft;
			status( importStatus, D.i18n.ieAdding.replace( '%1$s', String( i ) ).replace( '%2$s', String( filters.length ) ) );
			var body = new FormData();
			body.append( 'title', String( f.title || D.i18n.imported ) );
			appendDeep( body, 'settings', settings );
			return post( 'woofilters', 'woobewoo_pf_save', body ).then( function ( res ) {
				if ( res && res.data && res.data.filter ) {
					added++;
				} else {
					skipped++;
				}
				return next();
			} );
		}
		return next();
	}
	function importHandler( file ) {
		var h = handlers.import, body = new FormData();
		body.append( h.fileKey || 'import_file', file, file.name );
		status( importStatus, D.i18n.ieUploading );
		return post( h.mod, h.action, body ).then( function ( res ) {
			if ( ! res || res.error || ( res.errors && res.errors.length ) ) {
				status( importStatus, ( res && res.errors && res.errors[ 0 ] ) || ( res && res.data && res.data.message ) || D.i18n.error, 'err' );
				return false;
			}
			var r = res.data && res.data.result ? res.data.result : null, n = r && typeof r.imported !== 'undefined' ? parseInt( r.imported, 10 ) : null;
			status( importStatus, n !== null ? D.i18n.ieAdded.replace( '%s', plural( n, D.i18n.filterOne, D.i18n.filterMany ) ) + ( r.skipped ? ' ' + D.i18n.ieSkipped.replace( '%s', String( r.skipped ) ) : '' ) : ( ( res.messages && res.messages[ 0 ] ) || D.i18n.ieDone ), 'ok' );
			return true;
		} );
	}
	function importFile( file ) {
		if ( ! file || busy ) {
			return;
		}
		if ( ! /\.json$/i.test( file.name ) && file.type !== 'application/json' ) {
			status( importStatus, D.i18n.ieNotJson, 'err' );
			return;
		}
		busy = true;
		readFile( file ).then( function ( text ) {
			var data = parseExport( text );
			if ( ! data ) {
				status( importStatus, D.i18n.ieNotExport, 'err' );
				return false;
			}
			return handlers.import ? importHandler( file ) : importFree( data );
		} ).then( function ( ok ) {
			busy = false;
			if ( fileInput ) {
				fileInput.value = '';
			}
			if ( ok ) {
				window.setTimeout( function () {
					window.location.href = window.location.href.replace( /#.*$/, '' ) + '#import';
					window.location.reload();
				}, 900 );
			}
		} ).catch( function () {
			busy = false;
			status( importStatus, D.i18n.error, 'err' );
		} );
	}
	if ( drop && fileInput ) {
		var choose = document.getElementById( 'wpfImportChoose' );
		if ( choose ) {
			choose.addEventListener( 'click', function () {
				fileInput.click();
			} );
		}
		fileInput.addEventListener( 'change', function () {
			importFile( fileInput.files && fileInput.files[ 0 ] );
		} );
		[ 'dragenter', 'dragover' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) {
				e.preventDefault();
				drop.classList.add( 'is-over' );
			} );
		} );
		[ 'dragleave', 'drop' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) {
				e.preventDefault();
				drop.classList.remove( 'is-over' );
			} );
		} );
		drop.addEventListener( 'drop', function ( e ) {
			importFile( e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[ 0 ] );
		} );
	}

	/* Export: the handler's endpoint returns the JSON text; it is saved as a file here. */
	function allIds() {
		return Array.prototype.map.call( document.querySelectorAll( '#wpfFilterCards .wbw-fcard' ), function ( c ) {
			return c.getAttribute( 'data-id' );
		} );
	}
	function pickedIds() {
		return Array.prototype.map.call( document.querySelectorAll( '.wpfPickFilter:checked' ), function ( i ) {
			return i.value;
		} );
	}
	window.wpfSelectedFilterIds = pickedIds;
	function download( text, name ) {
		var blob = new Blob( [ text ], { type: 'application/json' } ), a = document.createElement( 'a' );
		a.href = window.URL.createObjectURL( blob );
		a.download = name;
		document.body.appendChild( a );
		a.click();
		window.setTimeout( function () {
			window.URL.revokeObjectURL( a.href );
			a.remove();
		}, 1000 );
	}
	function exportIds( ids, btn ) {
		var h = handlers.export;
		if ( ! h || ! ids.length || busy ) {
			return;
		}
		busy = true;
		btn.disabled = true;
		status( exportStatus, D.i18n.ieExporting );
		var body = new FormData(), key = h.idsKey || 'listIds';
		ids.forEach( function ( id ) {
			body.append( key + '[]', id );
		} );
		post( h.mod, h.action, body ).then( function ( res ) {
			busy = false;
			btn.disabled = false;
			var text = res && res.data ? res.data[ h.dataKey || 'tables' ] : null;
			if ( ! text || res.error ) {
				status( exportStatus, ( res && res.errors && res.errors[ 0 ] ) || D.i18n.error, 'err' );
				return;
			}
			download( typeof text === 'string' ? text : JSON.stringify( text ), h.fileName || 'wpf-filters-export.json' );
			status( exportStatus, D.i18n.ieExported.replace( '%s', plural( ids.length, D.i18n.filterOne, D.i18n.filterMany ) ), 'ok' );
		} ).catch( function () {
			busy = false;
			btn.disabled = false;
			status( exportStatus, D.i18n.error, 'err' );
		} );
	}
	function paintPicked() {
		var n = pickedIds().length;
		Array.prototype.forEach.call( document.querySelectorAll( '.wpfPickFilter' ), function ( i ) {
			i.parentNode.classList.toggle( 'is-on', i.checked );
			i.closest( '.wbw-fcard' ).classList.toggle( 'is-picked', i.checked );
		} );
		if ( exportSelected ) {
			exportSelected.textContent = D.i18n.ieExportSelected.replace( '%s', String( n ) );
			exportSelected.disabled = n === 0;
		}
	}
	/* Pick mode: a tick box on every filter card; the "Pick filters to export" chip turns into the dark
	   "Export N selected" pill (following the count) with a "Stop picking" chip beside it. */
	var exportPickStop = document.getElementById( 'wpfExportPickStop' );
	function setSelecting( on ) {
		var wasOn = document.body.classList.contains( 'is-selecting' );
		document.body.classList.toggle( 'is-selecting', on );
		if ( exportPick ) {
			exportPick.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			exportPick.hidden = on;
		}
		if ( exportSelected ) {
			exportSelected.hidden = ! on;
		}
		if ( exportPickStop ) {
			exportPickStop.hidden = ! on;
		}
		if ( on && ! wasOn ) {
			var first = document.querySelector( '.wpfPickFilter' );
			if ( first ) {
				first.focus();
			}
		} else if ( ! on && wasOn && exportPick && ie && ! ie.hidden ) {
			exportPick.focus();
		}
		if ( ! on ) {
			Array.prototype.forEach.call( document.querySelectorAll( '.wpfPickFilter' ), function ( i ) {
				i.checked = false;
			} );
		}
		paintPicked();
	}
	if ( exportAll ) {
		exportAll.addEventListener( 'click', function () {
			exportIds( allIds(), exportAll );
		} );
	}
	if ( exportPick ) {
		exportPick.addEventListener( 'click', function () {
			setSelecting( ! document.body.classList.contains( 'is-selecting' ) );
		} );
	}
	if ( exportSelected ) {
		exportSelected.addEventListener( 'click', function () {
			exportIds( pickedIds(), exportSelected );
		} );
	}
	if ( exportPickStop ) {
		exportPickStop.addEventListener( 'click', function () {
			setSelecting( false );
		} );
	}
	document.addEventListener( 'change', function ( e ) {
		if ( e.target.classList && e.target.classList.contains( 'wpfPickFilter' ) ) {
			paintPicked();
		}
	} );

	/* first week card */
	var fw = document.getElementById( 'wpfFirstWeekDismiss' );
	if ( fw ) {
		fw.addEventListener( 'click', function () {
			post( 'setup', 'woobewoo_pf_setup_dismiss_first_week', {} ).then( function () {
				var c = document.getElementById( 'wpfFirstWeekCard' );
				if ( c ) {
					c.remove();
				}
			} );
		} );
	}
} )();
