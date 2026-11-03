/**
 * Product Filter by WBW - Help screen (4.0)
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';
	if ( typeof window.wpfHelpData === 'undefined' ) {
		return;
	}
	var D = window.wpfHelpData, btn = document.getElementById( 'wpfSelfTestRun' ), list = document.getElementById( 'wpfSelfTestRows' );
	if ( ! btn || ! list ) {
		return;
	}
	function esc( s ) {
		return String( s || '' ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	btn.addEventListener( 'click', function () {
		btn.disabled = true;
		list.innerHTML = '<li class="wbw-muted">Running…</li>';
		var body = new FormData();
		body.append( 'action', 'woobewoo_pf_help_selftest' );
		body.append( 'mod', 'help' );
		body.append( 'pl', 'wpf' );
		body.append( 'reqType', 'ajax' );
		body.append( 'wpfNonce', D.nonce );
		fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json();
		} ).then( function ( res ) {
			var rows = ( res && res.data && res.data.rows ) ? res.data.rows : [];
			list.innerHTML = rows.map( function ( r ) {
				var tag = r.state === 'pass' ? 'wbw-tag--pass' : ( r.state === 'fail' ? 'wbw-tag--fail' : 'wbw-tag--partial' ),
					word = r.state === 'pass' ? 'Pass' : ( r.state === 'fail' ? 'Fix' : 'Check' );
				return '<li><span class="wbw-tag ' + tag + '">' + word + '</span><div><strong>' + esc( r.label ) + '</strong>' + ( r.detail ? '<span class="wbw-muted">' + esc( r.detail ) + '</span>' : '' ) + '</div></li>';
			} ).join( '' ) || '<li class="wbw-muted">Nothing to report.</li>';
		} ).catch( function () {
			list.innerHTML = '<li class="wbw-muted">Something went wrong. Try again.</li>';
		} ).then( function () {
			btn.disabled = false;
		} );
	} );
} )();
