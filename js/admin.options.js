"use strict";
var wpfAdminFormChanged = [];
window.onbeforeunload = function(){
	// If there are at lease one unsaved form - show message for confirnation for page leave
	if(wpfAdminFormChanged.length)
		return 'Some changes were not-saved. Are you sure you want to leave?';
};
jQuery(document).ready(function(){
	if(typeof(wpfActiveTab) != 'undefined' && wpfActiveTab != 'main_page' && jQuery('#toplevel_page_wpf-comparison-slider').hasClass('wp-has-current-submenu')) {
		var subMenus = jQuery('#toplevel_page_wpf-comparison-slider').find('.wp-submenu li');
		subMenus.removeClass('current').each(function(){
			if(jQuery(this).find('a[href$="&tab='+ wpfActiveTab+ '"]').length) {
				jQuery(this).addClass('current');
			}
		});
	}
	
	// Timeout - is to count only user changes, because some changes can be done auto when form is loaded
	setTimeout(function() {
		// If some changes was made in those forms and they were not saved - show message for confirnation before page reload
		var formsPreventLeave = [];
		if(formsPreventLeave && formsPreventLeave.length) {
			jQuery('#'+ formsPreventLeave.join(', #')).find('input,select').change(function(){
				var formId = jQuery(this).parents('form:first').attr('id');
				changeAdminFormWpf(formId);
			});
			jQuery('#'+ formsPreventLeave.join(', #')).find('input[type=text],textarea').keyup(function(){
				var formId = jQuery(this).parents('form:first').attr('id');
				changeAdminFormWpf(formId);
			});
			jQuery('#'+ formsPreventLeave.join(', #')).submit(function(){
				adminFormSavedWpf( jQuery(this).attr('id') );
			});
		}
	}, 1000);

	if(jQuery('.wpfInputsWithDescrForm').length) {
		jQuery('.wpfInputsWithDescrForm').find('input[type=checkbox][data-optkey]').change(function(){
			var optKey = jQuery(this).data('optkey')
			,	descShell = jQuery('#wpfFormOptDetails_'+ optKey);
			if(descShell.length) {
				if(jQuery(this).is(':checked')) {
					descShell.slideDown( 300 );
				} else {
					descShell.slideUp( 300 );
				}
			}
		}).trigger('change');
	}
	wpfInitStickyItem();
	
	jQuery('.wpfFieldsetToggled').each(function(){
		var self = this;
		jQuery(self).find('.wpfFieldsetContent').hide();
		jQuery(self).find('.wpfFieldsetToggleBtn').click(function(){
			var icon = jQuery(this).find('i')
			,	show = icon.hasClass('fa-plus');
			show ? icon.removeClass('fa-plus').addClass('fa-minus') : icon.removeClass('fa-minus').addClass('fa-plus');
			jQuery(self).find('.wpfFieldsetContent').slideToggle( 300, function(){
				if(show) {
					jQuery(this).find('textarea').each(function(i, el){
						if(typeof(this.CodeMirrorEditor) !== 'undefined') {
							this.CodeMirrorEditor.refresh();
						}
					});
				}
			} );
			return false;
		});
	});

	// for checkboxHiddenVal type, see class HtmlWpf
	jQuery('input[data-hiden-input=1]').change(function() {
		var hidenInput = jQuery(this).next();

		if (jQuery(this).prop("checked")) {
			jQuery(hidenInput).val("1");
		} else {
			jQuery(hidenInput).val("0");
		}
	});

	// Go to Top button init
	if(jQuery('#wpfPopupGoToTopBtn').length) {
		jQuery('#wpfPopupGoToTopBtn').click(function(){
			jQuery('html, body').animate({
				scrollTop: 0
			}, 1000);
			jQuery(this).parents('#wpfPopupGoToTop:first').hide();
			return false;
		});
	}
	wpfInitTooltips();
	jQuery(document.body).on('changeTooltips', function (e) {
		wpfInitTooltips(e.target);
		jQuery(e.target).find('.tooltipstered').removeAttr('title');
	});
	if(jQuery('.wpfCopyTextCode').length) {
		setTimeout(function(){	// Give it some time - wait until all other elements will be initialized
			var cloneWidthElement =  jQuery('<span class="sup-shortcode" />').appendTo('.woobewoo-plugin');
			jQuery('.wpfCopyTextCode').attr('readonly', 'readonly').click(function(){
				this.setSelectionRange(0, this.value.length);
			}).focus(function(){
				this.setSelectionRange(0, this.value.length);
			});
			jQuery('input.wpfCopyTextCode').each(function(){
				cloneWidthElement.html( str_replace(jQuery(this).val(), '<', 'P') );
				var parentSelector = jQuery(this).data('parent-selector')
				,	parentWidth = (parentSelector && parentSelector != '' 
						? jQuery(this).parents(parentSelector+ ':first') 
						: jQuery(this).parent()
					).width()
				,	txtWidth = cloneWidthElement.width();
				if(parentWidth <= 0 || parentWidth > txtWidth) {
					jQuery(this).width( cloneWidthElement.width() );
				}
			});
			cloneWidthElement.remove();
		}, 500);
	}
	// Check for showing review notice after a week usage
	wpfInitPlugNotices();
	jQuery('.woobewoo-plugin-loader').css('display', 'none');
	jQuery('.woobewoo-main').css('display', 'block');
	jQuery(".woobewoo-plugin .tooltipstered").removeAttr("title");
});
function wpfInitTooltips( selector ) {
	var tooltipsterSettings = {
		contentAsHTML: true
	,	interactive: true
	,	speed: 0
	,	delay: 0
	,	maxWidth: 450
	}
	,	findPos = {
		'.woobewoo-tooltip': 'top-left'
	,	'.woobewoo-tooltip-bottom': 'bottom-left'
	,	'.woobewoo-tooltip-left': 'left'
	,	'.woobewoo-tooltip-right': 'right'
	}
	,	$findIn = selector ? jQuery( selector ) : false;
	for(var k in findPos) {
		if(typeof(k) === 'string') {
			var $tips = $findIn ? $findIn.find( k ) : jQuery( k ).not('.no-tooltip');
			if($tips && $tips.length) {
				tooltipsterSettings.position = findPos[ k ];
				// Fallback for case if library was not loaded
				if(!$tips.tooltipster) continue;
				$tips.tooltipster( tooltipsterSettings );
			}
		}
	}
}
function changeAdminFormWpf(formId) {
	if(jQuery.inArray(formId, wpfAdminFormChanged) == -1)
		wpfAdminFormChanged.push(formId);
}
function adminFormSavedWpf(formId) {
	if(wpfAdminFormChanged.length) {
		for(var i in wpfAdminFormChanged) {
			if(wpfAdminFormChanged[i] == formId) {
				wpfAdminFormChanged.pop(i);
			}
		}
	}
}
function checkAdminFormSaved() {
	if(wpfAdminFormChanged.length) {
		if(!confirm(toeLangWpf('Some changes were not-saved. Are you sure you want to leave?'))) {
			return false;
		}
		wpfAdminFormChanged = [];	// Clear unsaved forms array - if user wanted to do this
	}
	return true;
}
function isAdminFormChanged(formId) {
	if(wpfAdminFormChanged.length) {
		for(var i in wpfAdminFormChanged) {
			if(wpfAdminFormChanged[i] == formId) {
				return true;
			}
		}
	}
	return false;
}
/*Some items should be always on users screen*/
function wpfInitStickyItem() {
	jQuery(window).scroll(function(){
		var stickiItemsSelectors = ['.woobewoo-sticky']
		,	elementsUsePaddingNext = ['.woobewoo-bar']	// For example - if we stick row - then all other should not offest to top after we will place element as fixed
		,	wpTollbarHeight = 32
		,	wndScrollTop = jQuery(window).scrollTop() + wpTollbarHeight
		,	footer = jQuery('.wpfAdminFooterShell')
		,	footerHeight = footer && footer.length ? footer.height() : 0
		,	docHeight = jQuery(document).height()
		,	wasSticking = false
		,	wasUnSticking = false;
		/*if(jQuery('#wpbody-content .update-nag').length) {	// Not used for now
			wpTollbarHeight += parseInt(jQuery('#wpbody-content .update-nag').outerHeight());
		}*/
		for(var i = 0; i < stickiItemsSelectors.length; i++) {
			jQuery(stickiItemsSelectors[ i ]).each(function(){
				var element = jQuery(this);
				if(element && element.length && !element.hasClass('sticky-ignore')) {
					var scrollMinPos = element.offset().top
					,	prevScrollMinPos = parseInt(element.data('scrollMinPos'))
					,	useNextElementPadding = toeInArray(stickiItemsSelectors[ i ], elementsUsePaddingNext) !== -1 || element.hasClass('sticky-padd-next')
					,	currentScrollTop = wndScrollTop
					,	calcPrevHeight = element.data('prev-height')
					,	currentBorderHeight = wpTollbarHeight
					,	usePrevHeight = 0;
					if(calcPrevHeight) {
						usePrevHeight = jQuery(calcPrevHeight).outerHeight();
						currentBorderHeight += usePrevHeight;
					}
					if(currentScrollTop > scrollMinPos && !element.hasClass('woobewoo-sticky-active')) {	// Start sticking
						if(element.hasClass('sticky-save-width')) {
							element.width( element.width() );
						}
						element.addClass('woobewoo-sticky-active').data('scrollMinPos', scrollMinPos).css({
							'top': currentBorderHeight
						});
						if(useNextElementPadding) {
							var nextElement = element.next();
							if(nextElement && nextElement.length) {
								nextElement.data('prevPaddingTop', nextElement.css('padding-top'));
								var addToNextPadding = parseInt(element.data('next-padding-add'));
								addToNextPadding = addToNextPadding ? addToNextPadding : 0;
								nextElement.css({
									'padding-top': (element.hasClass('sticky-outer-height') ? element.outerHeight() : element.height()) + usePrevHeight + addToNextPadding
								});
							}
						}
						wasSticking = true;
						element.trigger('startSticky');
					} else if(!isNaN(prevScrollMinPos) && currentScrollTop <= prevScrollMinPos) {	// Stop sticking
						element.removeClass('woobewoo-sticky-active').data('scrollMinPos', 0).css({
							'top': 0
						});
						if(element.hasClass('sticky-save-width')) {
							if(element.hasClass('sticky-base-width-auto')) {
								element.css('width', 'auto');
							}
						}
						if(useNextElementPadding) {
							var nextElement = element.next();
							if(nextElement && nextElement.length) {
								var nextPrevPaddingTop = parseInt(nextElement.data('prevPaddingTop'));
								if(isNaN(nextPrevPaddingTop))
									nextPrevPaddingTop = 0;
								nextElement.css({
									'padding-top': nextPrevPaddingTop
								});
							}
						}
						element.trigger('stopSticky');
						wasUnSticking = true;
					} else {	// Check new stick position
						if(element.hasClass('woobewoo-sticky-active')) {
							if(footerHeight) {
								var elementHeight = element.height()
								,	heightCorrection = 32
								,	topDiff = docHeight - footerHeight - (currentScrollTop + elementHeight + heightCorrection);
								if(topDiff < 0) {
									element.css({
										'top': currentBorderHeight + topDiff
									});
								} else {
									element.css({
										'top': currentBorderHeight
									});
								}
							}
							// If at least on element is still sticking - count it as all is working
							wasSticking = wasUnSticking = false;
						}
					}
				}
			});
		}
		if(wasSticking) {
			if(jQuery('#wpfPopupGoToTop').length)
				jQuery('#wpfPopupGoToTop').show();
		} else if(wasUnSticking) {
			if(jQuery('#wpfPopupGoToTop').length)
				jQuery('#wpfPopupGoToTop').hide();
		}
	});
}
function wpfInitCustomCheckRadio(selector) {
	if(!jQuery.fn.iCheck) return;
	if(!selector)
		selector = document;
	jQuery(selector).find('input').iCheck('destroy').iCheck({
		checkboxClass: 'icheckbox_minimal'
	,	radioClass: 'iradio_minimal'
	}).on('ifChanged', function(e){
		// for checkboxHiddenVal type, see class HtmlWpf
		jQuery(this).trigger('change');
		if(jQuery(this).hasClass('cbox')) {
			var parentRow = jQuery(this).parents('.jqgrow:first');
			if(parentRow && parentRow.length) {
				jQuery(this).parents('td:first').trigger('click');
			} else {
				var checkId = jQuery(this).attr('id');
				if(checkId && checkId != '' && strpos(checkId, 'cb_') === 0) {
					var parentTblId = str_replace(checkId, 'cb_', '');
					if(parentTblId && parentTblId != '' && jQuery('#'+ parentTblId).length) {
						jQuery('#'+ parentTblId).find('input[type=checkbox]').iCheck('update');
					}
				}
			}
		}
	}).on('ifClicked', function(e){
		jQuery(this).trigger('click');
	});
}
function wpfCheckDestroy(checkbox) {
	if(!jQuery.fn.iCheck) return;
	jQuery(checkbox).iCheck('destroy');
}
function wpfCheckDestroyArea(selector) {
	if(!jQuery.fn.iCheck) return;
	jQuery(selector).find('input[type=checkbox]').iCheck('destroy');
}
function wpfCheckUpdate(checkbox) {
	if(!jQuery.fn.iCheck) return;
	jQuery(checkbox).iCheck('update');
}
function wpfCheckUpdateArea(selector) {
	if(!jQuery.fn.iCheck) return;
	jQuery(selector).find('input[type=checkbox]').iCheck('update');
}
function wpfGetTxtEditorVal(id) {
	if(typeof(tinyMCE) !== 'undefined' 
		&& tinyMCE.get( id ) 
		&& !jQuery('#'+ id).is(':visible') 
		&& tinyMCE.get( id ).getDoc 
		&& typeof(tinyMCE.get( id ).getDoc) == 'function' 
		&& tinyMCE.get( id ).getDoc()
	)
		return tinyMCE.get( id ).getContent();
	else
		return jQuery('#'+ id).val();
}
function wpfSetTxtEditorVal(id, content) {
	if(typeof(tinyMCE) !== 'undefined' 
		&& tinyMCE 
		&& tinyMCE.get( id ) 
		&& !jQuery('#'+ id).is(':visible')
		&& tinyMCE.get( id ).getDoc 
		&& typeof(tinyMCE.get( id ).getDoc) == 'function' 
		&& tinyMCE.get( id ).getDoc()
	)
		tinyMCE.get( id ).setContent(content);
	else
		jQuery('#'+ id).val( content );
}
/**
 * Add data to jqGrid object post params search
 * @param {object} param Search params to set
 * @param {string} gridSelectorId ID of grid table html element
 */
function wpfGridSetListSearch(param, gridSelectorId) {
	jQuery('#'+ gridSelectorId).setGridParam({
		postData: {
			search: param
		}
	});
}
/**
 * Set data to jqGrid object post params search and trigger search
 * @param {object} param Search params to set
 * @param {string} gridSelectorId ID of grid table html element
 */
function wpfGridDoListSearch(param, gridSelectorId) {
	wpfGridSetListSearch(param, gridSelectorId);
	jQuery('#'+ gridSelectorId).trigger( 'reloadGrid' );
}
/**
 * Get row data from jqGrid
 * @param {number} id Item ID (from database for example)
 * @param {string} gridSelectorId ID of grid table html element
 * @return {object} Row data
 */
function wpfGetGridDataById(id, gridSelectorId) {
	var rowId = getGridRowId(id, gridSelectorId);
	if(rowId) {
		return jQuery('#'+ gridSelectorId).jqGrid ('getRowData', rowId);
	}
	return false;
}
/**
 * Get cell data from jqGrid
 * @param {number} id Item ID (from database for example)
 * @param {string} column Column name
 * @param {string} gridSelectorId ID of grid table html element
 * @return {string} Cell data
 */
function wpfGetGridColDataById(id, column, gridSelectorId) {
	var rowId = getGridRowId(id, gridSelectorId);
	if(rowId) {
		return jQuery('#'+ gridSelectorId).jqGrid ('getCell', rowId, column);
	}
	return false;
}
/**
 * Get grid row ID (ID of table row) from item ID (from database ID for example)
 * @param {number} id Item ID (from database for example)
 * @param {string} gridSelectorId ID of grid table html element
 * @return {number} Table row ID
 */
function getGridRowId(id, gridSelectorId) {
	var rowId = parseInt(jQuery('#'+ gridSelectorId).find('[aria-describedby='+ gridSelectorId+ '_id][title='+ id+ ']').parent('tr:first').index());
	if(!rowId) {
		console.log('CAN NOT FIND ITEM WITH ID  '+ id);
		return false;
	}
	return rowId;
}
function prepareToPlotDate(data) {
	if(typeof(data) === 'string') {
		if(data) {
			data = str_replace(data, '/', '-');
			return (new Date(data)).getTime();
		}
	}
	return data;
}
function wpfInitPlugNotices() {
	var $notices = jQuery('.woobewoo-admin-notice');
	if($notices && $notices.length) {
		$notices.each(function(){
			jQuery(this).find('.notice-dismiss').click(function(){
				var $notice = jQuery(this).parents('.woobewoo-admin-notice');
				if(!$notice.data('stats-sent')) {
					// User closed this message - that is his choise, let's respect this and save it's saved status
					jQuery.sendFormWpf({
						data: {mod: 'promo', action: 'addNoticeAction', code: $notice.data('code'), choice: 'hide'}
					});
				}
			});
			jQuery(this).find('[data-statistic-code]').click(function(){
				var href = jQuery(this).attr('href')
				,	$notice = jQuery(this).parents('.woobewoo-admin-notice');
				jQuery.sendFormWpf({
					data: {mod: 'promo', action: 'addNoticeAction', code: $notice.data('code'), choice: jQuery(this).data('statistic-code')}
				});
				$notice.data('stats-sent', 1).find('.notice-dismiss').trigger('click');
				if(!href || href === '' || href === '#')
					return false;
			});
			var $enbStatsBtn = jQuery(this).find('.wpfEnbStatsAdBtn');
			if($enbStatsBtn && $enbStatsBtn.length) {
				$enbStatsBtn.click(function(){
					jQuery.sendFormWpf({
						data: {mod: 'promo', action: 'enbStatsOpt'}
					});
					return false;
				});
			}
		});
	}
}
/**
 * Main promo popup will show each time user will try to modify PRO option with free version only
 */
function wpfGetMainPromoPopup() {
	if(jQuery('#wpfOptInProWnd').hasClass('ui-dialog-content')) {
		return jQuery('#wpfOptInProWnd');
	}
	return jQuery('#wpfOptInProWnd').dialog({
		modal:    true
	,	autoOpen: false
	,	width: 540
	,	height: 200
	,	open: function() {
			jQuery('#wpfOptWndTemplateTxt').hide();
			jQuery('#wpfOptWndOptionTxt').show();
		}
	});
}
function wpfInitMainPromoPopup() {
	if(!WPF_DATA.isPro) {
		var $proOptWnd = wpfGetMainPromoPopup();
		jQuery('.wpfProOpt').change(function(e){
			e.stopPropagation();
			var needShow = true
			,	isRadio = jQuery(this).attr('type') == 'radio'
			,	isCheck = jQuery(this).attr('type') == 'checkbox';
			if(isRadio && !jQuery(this).attr('checked')) {
				needShow = false;
			}
			if(!needShow) {
				return;
			}
			if(isRadio) {
				jQuery('input[name="'+ jQuery(this).attr('name')+ '"]:first').parents('label:first').click();
				if(jQuery(this).parents('.iradio_minimal:first').length) {
					var self = this;
					setTimeout(function(){
						jQuery(self).parents('.iradio_minimal:first').removeClass('checked');
					}, 10);
				}
			}
			var parent = null;
			if(jQuery(this).parents('#wpfPopupMainOpts').length) {
				parent = jQuery(this).parents('label:first');
			} else if(jQuery(this).parents('.wpfPopupOptRow:first').length) {
				parent = jQuery(this).parents('.wpfPopupOptRow:first');
			} else {
				parent = jQuery(this).parents('tr:first');
			}
			if(!parent.length) return;
			var promoLink = parent.find('.wpfProOptMiniLabel a').attr('href');
			if(promoLink && promoLink != '') {
				jQuery('#wpfOptInProWnd a').attr('href', promoLink);
			}
			$proOptWnd.dialog('open');
			return false;
		});
	}
}
