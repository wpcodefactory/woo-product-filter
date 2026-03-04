/**
 * Product Filter by WBW - Create Filter JS
 *
 * @version 2.9.7
 *
 * @author  woobewoo
 */

(function($) {
"use strict";
	$(document).ready(function () {
		jQuery('a[href$="admin.php?page=wpf-filters&tab=woofilters#wpfadd"]').attr('href', '#wpfadd');

		if( jQuery('#wpfAddDialog').length ) {
			var $createBtn = jQuery('.create-table'),
				$error = jQuery('#formError'),
				$input = jQuery('#addDialog_title'),
				$list = jQuery('#addDialog_list'),
				$inputDuplicateId = jQuery('#addDialog_duplicateid'),
				$dialog = jQuery('#wpfAddDialog').dialog({
					width: 480,
					modal: true,
					autoOpen: false,
					open: function () {
						jQuery('#wpfAddDialog').keypress(function(e) {
							if (e.keyCode == $.ui.keyCode.ENTER) {
								e.preventDefault();
								jQuery('.wpfDialogSave').click();
							}
						});
					},
					close: function () {
						window.location.hash = '';
					},
					buttons: [{
						text: jQuery('#wpfAddDialog').attr('data-button'),
						click: function (event) {
							$error.fadeOut();
							jQuery(this).closest(".ui-dialog").find('.wpfDialogSave').prop('disabled',true).attr('disabled',true);
							jQuery(this).closest(".ui-dialog").find('.wpfDialogSave').prepend('<i class="fa fa-refresh wpfIconRotate360" aria-hidden="true"></i>');
							var order = [];
							$list.find('input:checked').each(function() {
								var $filter = jQuery(this),
									id = $filter.data('value'),
									o = {'id':id,'uniqId':$filter.data('unique-id'),'settings':{'f_enable':true}};
								if (id == 'wpfSortBy') o.settings['f_options[]'] = 'default,popularity';
								else if (id == 'wpfInStock') o.settings['f_options[]'] = 'instock,outofstock';
								order.push(o);
							});
							var settings = order.length ? {'filters':{'order':JSON.stringify(order)}} : {};
							jQuery.sendFormWpf({
								data: {
									mod: 'woofilters',
									action: 'save',
									title: $input.val(),
									duplicateId: $inputDuplicateId.val(),
									settings: settings,
									wpfNonce: window.wpfNonce
								},
								onSuccess: function(res) {
									if(!res.error) {
										var currentUrl = window.location.href;
										if (res.data.edit_link && currentUrl !== res.data.edit_link) {
											toeRedirect(res.data.edit_link);
										}
										jQuery(this).closest(".ui-dialog").find('.wpfDialogSave').prop('disabled',false).attr('disabled',false);
										jQuery(this).closest(".ui-dialog").find('.wpfDialogSave').find('.wpfIconRotate360').remove();
									}else{
										$error.find('p').text(res.errors.title);
										$error.fadeIn();
									}
								}
							});
						}
					}],
					create:function () {
						jQuery(this).closest(".ui-dialog").addClass('woobewoo-plugin').find(".ui-dialog-buttonset button").first().addClass("wpfDialogSave");
						if (WPF_DATA.isWCLicense) jQuery(this).closest('.ui-dialog').find('.ui-dialog-buttonset button').addClass('button button-primary');
					}
				});

			$input.on('focus', function () {
				$error.fadeOut();
			});

			$createBtn.on('click', function () {
				$dialog.dialog('open');
			});
		}

		if( jQuery('#wpfDuplicateDialog').length ) {
			var $createBtn = jQuery('.create-table'),
				$error = jQuery('#formError'),
				$inputDuplicate = jQuery('#addDialog_titleDuplicate'),
				$list = jQuery('#addDialog_list'),
				$inputDuplicateId = jQuery('#addDialog_duplicateid'),
				$dialog2 = jQuery('#wpfDuplicateDialog').dialog({
					width: 480,
					modal: true,
					autoOpen: false,
					open: function () {
						jQuery('#wpfDuplicateDialog').keypress(function(e) {
							if (e.keyCode == $.ui.keyCode.ENTER) {
								e.preventDefault();
								jQuery('.wpfDialogSave').click();
							}
						});
					},
					close: function () {
						window.location.hash = '';
					},
					buttons: [{
						text: jQuery('#wpfDuplicateDialog').attr('data-button'),
						click: function (event) {
							$error.fadeOut();
							jQuery(this).closest(".ui-dialog").find('.wpfDialogSave').prop('disabled',true).attr('disabled',true);
							jQuery(this).closest(".ui-dialog").find('.wpfDialogSave').prepend('<i class="fa fa-refresh wpfIconRotate360" aria-hidden="true"></i>');
							var order = [];
							$list.find('input:checked').each(function() {
								var $filter = jQuery(this),
									id = $filter.data('value'),
									o = {'id':id,'uniqId':$filter.data('unique-id'),'settings':{'f_enable':true}};
								if (id == 'wpfSortBy') o.settings['f_options[]'] = 'default,popularity';
								else if (id == 'wpfInStock') o.settings['f_options[]'] = 'instock,outofstock';
								order.push(o);
							});
							var settings = order.length ? {'filters':{'order':JSON.stringify(order)}} : {};
							jQuery.sendFormWpf({
								data: {
									mod: 'woofilters',
									action: 'save',
									title: $inputDuplicate.val(),
									duplicateId: jQuery('#addDialog_duplicateid').val(),
									settings: settings,
									wpfNonce: window.wpfNonce
								},
								onSuccess: function(res) {
									if(!res.error) {
										var currentUrl = window.location.href;
										if (res.data.edit_link && currentUrl !== res.data.edit_link) {
											toeRedirect(res.data.edit_link);
										}
										jQuery(this).closest(".ui-dialog").find('.wpfDialogSave').prop('disabled',false).attr('disabled',false);
										jQuery(this).closest(".ui-dialog").find('.wpfDialogSave').find('.wpfIconRotate360').remove();
									}else{
										$error.find('p').text(res.errors.title);
										$error.fadeIn();
									}
								}
							});
						}
					}],
					create:function () {
						jQuery(this).closest(".ui-dialog").addClass('woobewoo-plugin').find(".ui-dialog-buttonset button").first().addClass("wpfDialogSave");
						if (WPF_DATA.isWCLicense) jQuery(this).closest('.ui-dialog').find('.ui-dialog-buttonset button').addClass('button button-primary');
					}
				});

			$inputDuplicate.on('focus', function () {
				$error.fadeOut();
			});

			$createBtn.on('click', function () {
				$dialog2.dialog('open');
			});
		}

		if (window.location.hash === '#wpfadd') {
			// To prevent error if data not loaded completely
			setTimeout(function() {
				if(typeof $dialog !== 'undefined'){
					$dialog.dialog('open');
				}
			}, 500);
		}

		jQuery('[href="#wpfadd"]').click(function(){
				setTimeout(function() {
					if(typeof $dialog !== 'undefined'){
						$dialog.dialog('open');
					}
				}, 500);
		});

		jQuery('#toplevel_page_wpf-filters .wp-submenu-wrap li:has(a[href$="admin.php?page=wpf-filters"])').on('click', function(e){
			e.preventDefault();
			showAddDialog();
		});

		jQuery(document.body).off('click', '.woobewoo-navigation li:has(a[href$="admin.php?page=wpf-filters&tab=woofilters#wpfadd"])');
		jQuery(document.body).on('click', '.woobewoo-navigation li:has(a[href$="admin.php?page=wpf-filters&tab=woofilters#wpfadd"])', function(e){
			e.preventDefault();
			showAddDialog();
		});

		function showAddDialog(){
			setTimeout(function() {
				$dialog.dialog('open');
			}, 500);
		}

		function showDuplicateDialog(){
			setTimeout(function() {
				$dialog2.dialog('open');
			}, 500);
		}

		jQuery(document.body).on('click','.wpfDuplicateFilter',function(e){
			e.preventDefault();
			var duplicateFilterId = jQuery(this).attr("data-filter-id");
			jQuery('#addDialog_duplicateid').val(duplicateFilterId);
			showDuplicateDialog();
			return false;
		})

	});
})(jQuery);
