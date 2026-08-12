/**
 * Product Filter by WBW - Create Filter JS
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

( function ( $ ) {
	"use strict";
	$( document ).ready( function () {
		$( 'a[href$="admin.php?page=wpf-filters&tab=woofilters#wpfadd"]' ).attr( 'href', '#wpfadd' );

		if ( $( '#wpfAddDialog' ).length ) {
			var $createBtn = $( '.create-table' ),
				$error = $( '#formError' ),
				$input = $( '#addDialog_title' ),
				$list = $( '#addDialog_list' ),
				$inputDuplicateId = $( '#addDialog_duplicateid' ),
				$dialog = $( '#wpfAddDialog' ).dialog( {
					width: 480,
					modal: true,
					autoOpen: false,
					open: function () {
						$( '#wpfAddDialog' ).keypress( function ( e ) {
							if ( e.keyCode == $.ui.keyCode.ENTER ) {
								e.preventDefault();
								$( '.wpfDialogSave' ).click();
							}
						} );
					},
					close: function () {
						window.location.hash = '';
					},
					buttons: [
						{
							text: $( '#wpfAddDialog' ).attr( 'data-button' ),
							click: function ( event ) {
								$error.fadeOut();
								$( this ).closest( ".ui-dialog" ).find( '.wpfDialogSave' ).prop( 'disabled', true ).attr( 'disabled', true );
								$( this ).closest( ".ui-dialog" ).find( '.wpfDialogSave' ).prepend( '<i class="fa fa-refresh wpfIconRotate360" aria-hidden="true"></i>' );
								var order = [];
								$list.find( 'input:checked' ).each( function () {
									var $filter = $( this ),
										id = $filter.data( 'value' ),
										o = {
											'id': id,
											'uniqId': $filter.data( 'unique-id' ),
											'settings': { 'f_enable': true }
										};
									if ( id == 'wpfSortBy' ) {
										o.settings[ 'f_options[]' ] = 'default,popularity';
									} else if ( id == 'wpfInStock' ) {
										o.settings[ 'f_options[]' ] = 'instock,outofstock';
									}
									order.push( o );
								} );
								var settings = order.length ? { 'filters': { 'order': JSON.stringify( order ) } } : {};
								$.sendFormWpf( {
									data: {
										mod: 'woofilters',
										action: 'woobewoo_pf_save',
										title: $input.val(),
										duplicateId: $inputDuplicateId.val(),
										settings: settings,
										wpfNonce: woobewoo_pf_admin_ajax_object.nonce
									},
									onSuccess: function ( res ) {
										if ( !res.error ) {
											var currentUrl = window.location.href;
											if ( res.data.edit_link && currentUrl !== res.data.edit_link ) {
												toeRedirect( res.data.edit_link );
											}
											$( this ).closest( ".ui-dialog" ).find( '.wpfDialogSave' ).prop( 'disabled', false ).attr( 'disabled', false );
											$( this ).closest( ".ui-dialog" ).find( '.wpfDialogSave' ).find( '.wpfIconRotate360' ).remove();
										} else {
											$error.find( 'p' ).text( res.errors.title );
											$error.fadeIn();
										}
									}
								} );
							}
						}
					],
					create: function () {
						$( this ).closest( ".ui-dialog" ).addClass( 'woobewoo-plugin' ).find( ".ui-dialog-buttonset button" ).first().addClass( "wpfDialogSave" );
						if ( WPF_DATA.isWCLicense ) {
							$( this ).closest( '.ui-dialog' ).find( '.ui-dialog-buttonset button' ).addClass( 'button button-primary' );
						}
					}
				} );

			$input.on( 'focus', function () {
				$error.fadeOut();
			} );

			$createBtn.on( 'click', function () {
				$dialog.dialog( 'open' );
			} );
		}

		if ( $( '#wpfDuplicateDialog' ).length ) {
			var $createBtn = $( '.create-table' ),
				$error = $( '#formError' ),
				$inputDuplicate = $( '#addDialog_titleDuplicate' ),
				$list = $( '#addDialog_list' ),
				$inputDuplicateId = $( '#addDialog_duplicateid' ),
				$dialog2 = $( '#wpfDuplicateDialog' ).dialog( {
					width: 480,
					modal: true,
					autoOpen: false,
					open: function () {
						$( '#wpfDuplicateDialog' ).keypress( function ( e ) {
							if ( e.keyCode == $.ui.keyCode.ENTER ) {
								e.preventDefault();
								$( '.wpfDialogSave' ).click();
							}
						} );
					},
					close: function () {
						window.location.hash = '';
					},
					buttons: [
						{
							text: $( '#wpfDuplicateDialog' ).attr( 'data-button' ),
							click: function ( event ) {
								$error.fadeOut();
								$( this ).closest( ".ui-dialog" ).find( '.wpfDialogSave' ).prop( 'disabled', true ).attr( 'disabled', true );
								$( this ).closest( ".ui-dialog" ).find( '.wpfDialogSave' ).prepend( '<i class="fa fa-refresh wpfIconRotate360" aria-hidden="true"></i>' );
								var order = [];
								$list.find( 'input:checked' ).each( function () {
									var $filter = $( this ),
										id = $filter.data( 'value' ),
										o = {
											'id': id,
											'uniqId': $filter.data( 'unique-id' ),
											'settings': { 'f_enable': true }
										};
									if ( id == 'wpfSortBy' ) {
										o.settings[ 'f_options[]' ] = 'default,popularity';
									} else if ( id == 'wpfInStock' ) {
										o.settings[ 'f_options[]' ] = 'instock,outofstock';
									}
									order.push( o );
								} );
								var settings = order.length ? { 'filters': { 'order': JSON.stringify( order ) } } : {};
								$.sendFormWpf( {
									data: {
										mod: 'woofilters',
										action: 'woobewoo_pf_save',
										title: $inputDuplicate.val(),
										duplicateId: $( '#addDialog_duplicateid' ).val(),
										settings: settings,
										wpfNonce: woobewoo_pf_admin_ajax_object.nonce
									},
									onSuccess: function ( res ) {
										if ( !res.error ) {
											var currentUrl = window.location.href;
											if ( res.data.edit_link && currentUrl !== res.data.edit_link ) {
												toeRedirect( res.data.edit_link );
											}
											$( this ).closest( ".ui-dialog" ).find( '.wpfDialogSave' ).prop( 'disabled', false ).attr( 'disabled', false );
											$( this ).closest( ".ui-dialog" ).find( '.wpfDialogSave' ).find( '.wpfIconRotate360' ).remove();
										} else {
											$error.find( 'p' ).text( res.errors.title );
											$error.fadeIn();
										}
									}
								} );
							}
						}
					],
					create: function () {
						$( this ).closest( ".ui-dialog" ).addClass( 'woobewoo-plugin' ).find( ".ui-dialog-buttonset button" ).first().addClass( "wpfDialogSave" );
						if ( WPF_DATA.isWCLicense ) {
							$( this ).closest( '.ui-dialog' ).find( '.ui-dialog-buttonset button' ).addClass( 'button button-primary' );
						}
					}
				} );

			$inputDuplicate.on( 'focus', function () {
				$error.fadeOut();
			} );

			$createBtn.on( 'click', function () {
				$dialog2.dialog( 'open' );
			} );
		}

		if ( window.location.hash === '#wpfadd' ) {
			// To prevent error if data not loaded completely
			setTimeout( function () {
				if ( typeof $dialog !== 'undefined' ) {
					$dialog.dialog( 'open' );
				}
			}, 500 );
		}

		$( '[href="#wpfadd"]' ).click( function () {
			setTimeout( function () {
				if ( typeof $dialog !== 'undefined' ) {
					$dialog.dialog( 'open' );
				}
			}, 500 );
		} );

		$( '#toplevel_page_wpf-filters .wp-submenu-wrap li:has(a[href$="admin.php?page=wpf-filters"])' ).on( 'click', function ( e ) {
			e.preventDefault();
			showAddDialog();
		} );

		$( document.body ).off( 'click', '.woobewoo-navigation li:has(a[href$="admin.php?page=wpf-filters&tab=woofilters#wpfadd"])' );
		$( document.body ).on( 'click', '.woobewoo-navigation li:has(a[href$="admin.php?page=wpf-filters&tab=woofilters#wpfadd"])', function ( e ) {
			e.preventDefault();
			showAddDialog();
		} );

		function showAddDialog() {
			setTimeout( function () {
				$dialog.dialog( 'open' );
			}, 500 );
		}

		function showDuplicateDialog() {
			setTimeout( function () {
				$dialog2.dialog( 'open' );
			}, 500 );
		}

		$( document.body ).on( 'click', '.wpfDuplicateFilter', function ( e ) {
			e.preventDefault();
			var duplicateFilterId = $( this ).attr( "data-filter-id" );
			$( '#addDialog_duplicateid' ).val( duplicateFilterId );
			showDuplicateDialog();
			return false;
		} )

	} );
	//gray out past filters when creating new filter
	document.addEventListener( 'DOMContentLoaded', function () {
		// Select the input field and the filter list
		const filterInput = document.querySelector( '#addDialog_title' );
		const filterCheckboxes = document.querySelectorAll( '#addDialog_list input[type="checkbox"]' );
		const filterLabels = document.querySelectorAll( '#addDialog_list label' );

		// Function to toggle checkbox and label states
		function toggleCheckboxState() {
			// Check if the filter name field has content
			const inputValue = filterInput.value.trim();

			if ( inputValue === "" ) {
				// If input is empty, disable the checkboxes and labels (gray out)
				filterCheckboxes.forEach( function ( checkbox ) {
					checkbox.disabled = true; // Disable checkboxes
					checkbox.style.opacity = 0.5; // Gray out checkboxes
					checkbox.style.pointerEvents = 'none'; // Disable pointer events
				} );

				filterLabels.forEach( function ( label ) {
					label.style.color = '#999'; // Gray out labels
					label.style.pointerEvents = 'none'; // Disable pointer events on labels
				} );
			} else {
				// If input has content, enable the checkboxes and labels
				filterCheckboxes.forEach( function ( checkbox ) {
					checkbox.disabled = false; // Enable checkboxes
					checkbox.style.opacity = 1; // Restore opacity
					checkbox.style.pointerEvents = 'auto'; // Enable pointer events
				} );

				filterLabels.forEach( function ( label ) {
					label.style.color = '#333'; // Restore normal label color
					label.style.pointerEvents = 'auto'; // Enable pointer events on labels
				} );
			}
		}

		// Listen for changes in the input field and call the toggleCheckboxState function
		filterInput.addEventListener( 'input', toggleCheckboxState );

		// Initialize on page load in case the input field has content already
		toggleCheckboxState();
	} );

} )( jQuery );
