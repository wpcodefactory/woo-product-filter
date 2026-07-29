/**
 * Product Filter by WBW - Admin WooFilters List JS
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */
jQuery( document ).ready( function ( $ ) {
	"use strict";
	// Fallback for case if library was not loaded
	if ( !$.fn.jqGrid ) {
		return;
	}
	var tblId = 'wpfTableTbl',
		tableObj = $( '#' + tblId ),
		grid = tableObj.jqGrid( {
			url: woobewoo_pf_admin_ajax_object.wpfTblDataUrl,
			datatype: 'json',
			autowidth: true,
			shrinkToFit: true,
			colNames: tableObj.data( 'columns' ).split( ';' ),
			colModel: [
				{ name: 'id', index: 'id', searchoptions: { sopt: [ 'eq' ] }, width: '50', align: 'center' },
				{ name: 'title', index: 'title', searchoptions: { sopt: [ 'eq' ] }, align: 'center' },
				{
					name: 'shortcode',
					index: 'shortcode',
					searchoptions: { sopt: [ 'eq' ] },
					align: 'center',
					sortable: false
				},
				{
					name: 'actions',
					index: 'actions',
					searchoptions: { sopt: [ 'eq' ] },
					align: 'center',
					sortable: false
				}
			],
			postData: {
				search: {
					text_like: $( '#' + tblId + 'SearchTxt' ).val()
				},
				wpfNonce: woobewoo_pf_admin_ajax_object.nonce
			},
			rowNum: 10,
			rowList: [ 10, 20, 30, 1000 ],
			pager: '#' + tblId + 'Nav',
			sortname: 'id',
			viewrecords: true,
			sortorder: 'desc',
			jsonReader: { repeatitems: false, id: '0' },
			caption: toeLangWpf( 'Current PopUp' ),
			height: '100%',
			emptyrecords: toeLangWpf( 'You have no Filters for now.' ),
			multiselect: true,
			onSelectRow: function ( rowid, e ) {
				var tblId = $( this ).attr( 'id' ),
					selectedRowIds = $( '#' + tblId ).jqGrid( 'getGridParam', 'selarrrow' ),
					totalRows = $( '#' + tblId ).getGridParam( 'reccount' ),
					totalRowsSelected = selectedRowIds.length;
				if ( totalRowsSelected ) {
					$( '#wpfTableRemoveGroupBtn,#wpfTableExportBtn' ).removeAttr( 'disabled' );
					if ( totalRowsSelected == totalRows ) {
						$( '#cb_' + tblId ).prop( 'indeterminate', false );
						$( '#cb_' + tblId ).prop( 'checked', true );
					} else {
						$( '#cb_' + tblId ).prop( 'indeterminate', true );
						$( '#cb_' + tblId ).prop( 'checked', false );
					}
				} else {
					$( '#wpfTableRemoveGroupBtn,#wpfTableExportBtn' ).attr( 'disabled', 'disabled' );
					$( '#cb_' + tblId ).prop( 'indeterminate', false );
					$( '#cb_' + tblId ).prop( 'checked', false );
				}
				wpfCheckUpdate( $( this ).find( 'tr:eq(' + rowid + ')' ).find( 'input[type=checkbox].cbox' ) );
				wpfCheckUpdate( '#cb_' + tblId );
			}
			, beforeRequest: function () {
				$( '#wpfTableTblNav_center .ui-pg-table' ).addClass( 'woobewoo-hidden' );
			}
			, gridComplete: function ( a, b, c ) {
				var tblId = $( this ).attr( 'id' );
				$( '#wpfTableRemoveGroupBtn,#wpfTableExportBtn' ).attr( 'disabled', 'disabled' );
				$( '#cb_' + tblId ).prop( 'indeterminate', false );
				$( '#cb_' + tblId ).prop( 'checked', false );
				// Custom checkbox manipulation
				wpfInitCustomCheckRadio( '#' + $( this ).attr( 'id' ) );
				wpfCheckUpdate( '#cb_' + $( this ).attr( 'id' ) );
				$( '#wpfTableTblNav_center .ui-pg-table' ).removeClass( 'woobewoo-hidden' );
			}
			, loadComplete: function () {
				var tblId = $( this ).attr( 'id' );
				if ( this.p.reccount === 0 ) {
					$( this ).hide();
					$( '#' + tblId + 'EmptyMsg' ).removeClass( 'woobewoo-hidden' );
				} else {
					$( this ).show();
					$( '#' + tblId + 'EmptyMsg' ).addClass( 'woobewoo-hidden' );
				}
			}
		} );

	$( window ).on( 'load resize', tableObj, function ( event ) {
		tableObj.jqGrid( 'setGridWidth', $( '#containerWrapper' ).width() );
	} );

	$( '#' + tblId ).on( 'change', '.cbox', function () {
		if ( !$( '#' + tblId + ' .cbox:checked' ).length ) {
			$( '#wpfTableRemoveGroupBtn,#wpfTableExportBtn' ).attr( 'disabled', 'disabled' );
			grid.jqGrid( 'resetSelection' );
			wpfCheckUpdate( tableObj.find( 'input[type=checkbox].cbox' ) ); // what it does?
		}
	} );

	$( '#' + tblId + 'Nav' ).find( '.ui-pg-selbox' ).insertAfter( $( '#' + tblId + 'Nav' ).find( '.ui-paging-info' ) );
	$( '#' + tblId + 'Nav' ).find( '.ui-pg-table td:first' ).remove();

	// Make navigation tabs to be with our additional buttons - in one row
	$( '#' + tblId + 'SearchTxt' ).keyup( function () {
		var searchVal = $.trim( $( this ).val() );
		if ( true ) {
			wpfGridDoListSearch( {
				text_like: searchVal
			}, tblId );
		}
	} );

	$( '#' + tblId + 'EmptyMsg' ).insertAfter( $( '#' + tblId + '' ).parent() );
	$( '#' + tblId + '' ).jqGrid( 'navGrid', '#' + tblId + 'Nav', { edit: false, add: false, del: false } );
	$( '#cb_' + tblId + '' ).change( function () { // check all
		if ( $( this ).is( ':checked' ) ) {
			$( '#wpfTableRemoveGroupBtn,#wpfTableExportBtn' ).removeAttr( 'disabled' );
			grid.jqGrid( 'resetSelection' );
			var ids = grid.getDataIDs();
			for ( var i = 0, il = ids.length; i < il; i ++ ) {
				grid.jqGrid( 'setSelection', ids[ i ], true );
			}
		} else {
			$( '#wpfTableRemoveGroupBtn,#wpfTableExportBtn' ).attr( 'disabled', 'disabled' );
			grid.jqGrid( 'resetSelection' );
			wpfCheckUpdate( tableObj.find( 'input[type=checkbox].cbox' ) );
		}
	} );

	tableObj.on( 'change', 'td input[type=checkbox].cbox', function () {
		var cbox = $( this );
		grid.jqGrid( 'setSelection', cbox.closest( 'tr' ).index(), cbox.is( ':checked' ) );
	} );

	$( '#wpfTableRemoveGroupBtn' ).click( function () {
		var selectedRowIds = $( '#wpfTableTbl' ).jqGrid( 'getGridParam', 'selarrrow' ),
			listIds = [];
		for ( var i in selectedRowIds ) {
			var rowData = $( '#wpfTableTbl' ).jqGrid( 'getRowData', selectedRowIds[ i ] );
			listIds.push( rowData.id );
		}
		var popupLabel = '';
		if ( listIds.length == 1 ) {	// In table label cell there can be some additional links
			var labelCellData = wpfGetGridColDataById( listIds[ 0 ], 'title', 'wpfTableTbl' );
			popupLabel = $( labelCellData ).text();
		}
		var confirmMsg = listIds.length > 1
			? toeLangWpf( 'Are you sur want to remove ' + listIds.length + ' Filters?' )
			: toeLangWpf( 'Are you sure want to remove "' + popupLabel + '" Filter?' );
		if ( confirm( confirmMsg ) ) {
			$.sendFormWpf( {
				btn: this,
				data: {
					mod: 'woofilters',
					action: 'woobewoo_pf_remove_group',
					listIds: listIds,
					wpfNonce: woobewoo_pf_admin_ajax_object.nonce
				},
				onSuccess: function ( res ) {
					if ( !res.error ) {
						$( '#wpfTableTbl' ).trigger( 'reloadGrid' );
					}
				}
			} );
		}
		return false;
	} );

	$( '#wpfTableExportBtn' ).on( 'click', function ( e ) {
		e.preventDefault();

		var selectedRowIds = $( '#wpfTableTbl' ).jqGrid( 'getGridParam', 'selarrrow' ),
			listIds = [];
		for ( var i in selectedRowIds ) {
			var rowData = $( '#wpfTableTbl' ).jqGrid( 'getRowData', selectedRowIds[ i ] );
			listIds.push( rowData.id );
		}

		if ( listIds.length ) {
			$.sendFormWpf( {
				btn: this,
				data: {
					mod: 'woofilterpro',
					action: 'woobewoo_pf_export_group',
					listIds: listIds,
					wpfNonce: woobewoo_pf_admin_ajax_object.nonce
				},
				onSuccess: function ( res ) {
					if ( !res.error ) {
						var blob = new Blob(
							[ res.data.tables ],
							{ type: 'text/sql' }
						);
						var fileName = 'wpf_export.sql';
						var link = document.createElement( 'a' );
						link.href = window.URL.createObjectURL( blob );
						link.download = fileName;
						link.click();

						$( '#wpfTableTbl' ).trigger( 'reloadGrid' );
						link.remove();
					}
				}
			} );
		}
		return false;
	} );

	// *******  import filters START  *******
	var $importForm = $( '#wpfImportForm' );
	$( '<input type="hidden" name="wpfNonce" value="' + woobewoo_pf_admin_ajax_object.nonce + '">' ).appendTo( $importForm );
	var $importWnd = $( '#wpfImportWnd' ).dialog( {
		modal: true,
		autoOpen: false,
		width: 500,
		height: 250,
		buttons: {
			'Import': function () {
				$importForm.submit();
			}
		},
		create: function () {
			$( this ).closest( '.ui-dialog' ).addClass( 'woobewoo-plugin' );
			if ( WPF_DATA.isWCLicense ) {
				$( this ).closest( '.ui-dialog' ).find( '.ui-dialog-buttonset button' ).addClass( 'button button-primary' );
			}
		}
	} );
	var $importSubmitBtn = $importWnd.parents( '.ui-dialog:first' ).
		find( '.ui-dialog-buttonpane .ui-dialog-buttonset' ).
		find( 'button:first' );

	$importForm.submit( function () {
		$importSubmitBtn.width( $importSubmitBtn.width() );
		$importSubmitBtn.showLoaderWpf();
		var url = '';
		if ( typeof (
			ajaxurl
		) == 'undefined' || typeof (
			ajaxurl
		) !== 'string' ) {
			url = WPF_DATA.ajaxurl;
		} else {
			url = ajaxurl;
		}

		var formData = new FormData( $importForm.get( 0 ) );

		$.ajax( {
			url: woobewoo_pf_admin_ajax_object.ajaxurl,
			data: formData,
			type: 'POST',
			processData: false,
			contentType: false,
			success: function ( res ) {
				if ( !res.error ) {
					$importWnd.dialog( 'close' );
					$( '#wpfImportInput' ).val( '' );
					$( '#wpfTableTbl' ).trigger( 'reloadGrid' );
					$importSubmitBtn.html( 'Import' )
				}
			}
		} );
		return false;
	} );

	$( '#wpfTableImportBtn' ).on( 'click', function ( e ) {
		e.preventDefault();
		$importWnd.dialog( 'open' );
		return false;
	} );

	// *******  import filters END  *******
	wpfInitCustomCheckRadio( '#' + tblId + '_cb' );

	// *******  enable/disable statistics  *******
	var $statEForm = $( '#wpfStatsEForm' ),
		$statDForm = $( '#wpfStatsDForm' ),
		$statEWnd = $( '#wpfStatsEWnd' ).dialog( {
			modal: true,
			autoOpen: false,
			width: 500,
			height: 300,
			buttons: [
				{
					text: $statEForm.attr( 'data-submit' ),
					click: function () {
						$statEForm.submit();
					}
				}
			],
			create: function () {
				$( this ).closest( '.ui-dialog' ).addClass( 'woobewoo-plugin' );
				if ( WPF_DATA.isWCLicense ) {
					$( this ).closest( '.ui-dialog' ).find( '.ui-dialog-buttonset button' ).addClass( 'button button-primary' );
				}
			}
		} ), $statDWnd = $( '#wpfStatsDWnd' ).dialog( {
			modal: true,
			autoOpen: false,
			width: 500,
			height: 250,
			buttons: [
				{
					text: $statDForm.attr( 'data-submit' ),
					click: function () {
						$statDForm.submit();
					}
				}
			],
			create: function () {
				$( this ).closest( '.ui-dialog' ).addClass( 'woobewoo-plugin' );
				if ( WPF_DATA.isWCLicense ) {
					$( this ).closest( '.ui-dialog' ).find( '.ui-dialog-buttonset button' ).addClass( 'button button-primary' );
				}
			}
		} );

	tableObj.on( 'click', '.wpf-statistics', function ( e ) {
		e.preventDefault();
		var $this = $( this ),
			id = $this.attr( 'data-id' );
		if ( $this.hasClass( 'wpf-action-on' ) ) {
			$statDWnd.dialog( 'open' );
			$statDWnd.find( 'input[name="id"]' ).val( id );
		} else {
			$statEWnd.dialog( 'open' );
			$statEWnd.find( 'input[name="id"]' ).val( id );
		}
		return false;
	} );

	$( '#wpfStatsEForm, #wpfStatsDForm' ).on( 'submit', function ( e ) {
		e.preventDefault();
		var $form = $( this ),
			$submitButton = $form.parents( '.ui-dialog:first' ).find( '.ui-dialog-buttonpane .ui-dialog-buttonset' ).find( 'button:first' );
		$submitButton.width( $submitButton.width() );
		$submitButton.showLoaderWpf();
		$form.sendFormWpf( {
			btn: $submitButton,
			data: {
				mod: 'statistics',
				action: $form.find( '[name="action"]' ).val(),
				id: $form.find( '[name="id"]' ).val(),
				wpfNonce: woobewoo_pf_admin_ajax_object.nonce
			},
			onSuccess: function ( res ) {
				if ( !res.error ) {
					var $icon = tableObj.find( '.wpf-statistics[data-id="' + $form.find( 'input[name="id"]' ).val() + '"]' );
					if ( $form.is( '#wpfStatsEForm' ) ) {
						$statEWnd.dialog( 'close' );
						$icon.addClass( 'wpf-action-on' ).removeClass( 'wpf-action-off' );
					} else {
						$statDWnd.dialog( 'close' );
						$icon.addClass( 'wpf-action-off' ).removeClass( 'wpf-action-on' );
					}
					$submitButton.html( $form.attr( 'data-submit' ) );
					$icon.blur();
				}
			}
		} );
		return false;
	} );
} );
