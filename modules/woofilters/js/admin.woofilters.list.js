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

	wpfInitCustomCheckRadio( '#' + tblId + '_cb' );
} );
