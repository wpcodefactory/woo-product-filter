/**
 * Product Filter by WBW - Admin Notice Dismiss JS
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */
jQuery( document ).ready( function ($) {
	"use strict";

	$( document ).on( 'click', '.wpf-notice-dismis .notice-dismiss', function () {
		$.sendFormWpf( {
			data: {
				mod: 'overview',
				action: 'woobewoo_pf_dismiss_notice',
				'slug': $( this ).closest( '.wpf-notice-dismis' ).attr( 'data-disslug' )
			}
		} );
	} );
	$( document ).on( 'click', '.wpf-notice-dismis .button-dismiss', function () {
		$( this ).closest( '.wpf-notice-dismis' ).find( '.notice-dismiss' ).trigger( 'click' );
	} );
	$( document ).on( 'click', '.wpf-notice-dismis .button-approve', function () {
		var $wrapper = $( this ).closest( '.wpf-notice-dismis' );
		$.sendFormWpf( {
			data: { mod: 'overview', action: 'woobewoo_pf_approve_notice', 'slug': $wrapper.attr( 'data-disslug' ) }
		} );
		$wrapper.find( '.notice-dismiss' ).trigger( 'click' );
	} );
} );
