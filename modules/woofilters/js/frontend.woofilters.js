/**
 * Product Filter by WBW - Frontend Woofilters JS
 *
 * @version 2.8.6
 *
 * @author  woobewoo
 */

/**
 * Main function.
 *
 * @version 2.8.6
 */
(function ($, app) {
	"use strict";

	/**
	 * WpfFrontendPage.
	 *
	 * @version 2.8.6
	 */
	function WpfFrontendPage() {
		this.$obj = this;
		this.noWoo = this.$obj.checkNoWooPage();
		this.readyFuncs = ['.berocket_load_more_preload', 'woocommerce-product-bundle-hide', 'show_variation', 'woo_variation_swatches_pro_init', '.variations_form', 'yith_infs_start', 'flatsome_infinite_scroll','.dipl_woo_products_pagination_wrapper', 'divi_filter_loadmore_ajax_handler'];
		this.isSafari = navigator.vendor && navigator.vendor.indexOf('Apple') > -1 && ((navigator.userAgent && navigator.userAgent.indexOf('CriOS') == -1 && navigator.userAgent.indexOf('FxiOS') == -1) || (navigator.platform && /iPhone|iPad|iPod/.test(navigator.platform)));
		this.disableScrollJs = true;
		this.lastFids = [];
		return this.$obj;
	}

	WpfFrontendPage.prototype.init = (function () {
		var _thisObj = this.$obj;
		app.wpfNewUrl = '';
		window.wpfDoNotLoadMore = false;
		_thisObj.filterClick = true;
		_thisObj.filteringId = 0;

		_thisObj.setCurrentLocation();
		_thisObj.filterLoadTypes = [];
		_thisObj.defaultProductSelector = 'ul.products';
		_thisObj.isAdminPreview = jQuery('#wpfFiltersEditForm').length > 0 || (typeof isElementorPreview != 'undefined' && isElementorPreview == 1);
		_thisObj.moveFloatingElements();
		_thisObj.checkForceFilters();
		_thisObj.eventsPriceFilter();
		_thisObj.disableLeerOptions();
		_thisObj.eventsFrontend();
		_thisObj.changeSlugByUrl();
		_thisObj.runCustomJs();
		_thisObj.addCustomCss();
		_thisObj.chageRangeFieldWidth();
		_thisObj.addSpecificPluginActions();
		_thisObj.resizeWindow();
		_thisObj.changeOrderBy();
		_thisObj.copySelectsForSafari();

		jQuery('.wpfMainWrapper').each(function() {
			_thisObj.markCheckboxSelected(jQuery(this), true);
		});

		if (_thisObj.isAdminPreview) {
			_thisObj.hideFiltersLoader();
		} else {
			setTimeout(function () {
				_thisObj.hideFiltersLoader();
			}, 100);
        }
	});
	WpfFrontendPage.prototype.moveFloatingElements = (function () {
		var _thisObj = this.$obj;
		if (!_thisObj.isAdminPreview && jQuery('.wpfFloatingBlock').length == 0 && jQuery('.wpfFloatingWrapper').length == 1) {
			jQuery('body').append('<div class="wpfFloatingBlock"></div>');
			if (jQuery('.wpfFloatingSwitcher').length == 1) {
				var button = $('.wpfFloatingSwitcher');
				if (button.hasClass('wpfSwitcherRealFloat')) {
					button.detach();
					$('.wpfFloatingBlock').append(button);
				}
			}
			if (jQuery('.wpfFloatingOverlay').length == 1) {
				var overlay = $('.wpfFloatingOverlay').detach();
				$('.wpfFloatingBlock').append(overlay);
			}
			if ($('.wpfFloatingWrapper').closest('.wpfFilterForWtbp').length) $('.wpfFloatingBlock').addClass('wpfFilterForWtbpFloating');
			var block = $('.wpfFloatingWrapper').detach();
			$('.wpfFloatingBlock').append(block);

		}
	});

	WpfFrontendPage.prototype.changeOrderBy = (function () {
		jQuery('.woocommerce-ordering select').on('change', function (e) {
			e.preventDefault();
			var orderBy = jQuery(this).val(),
				curUrl = window.location.href;

			if ((curUrl.indexOf('?') === -1)) {
				curUrl += '?orderby=' + orderBy;
				jQuery('.wpfMainWrapper').each(function () {
					var filter = $(this).data('filter-settings');
					if (typeof filter.settings.filters.defaults !== 'undefined') {
						curUrl += '&' + filter.settings.filters.defaults.replace(';', '&').replace('|', '%7C');
					}
				});
			} else {
				if (curUrl.indexOf('orderby') === -1) {
					curUrl += ((curUrl.indexOf('?') === -1) ? '?' : '&') + 'orderby=' + orderBy;
				} else {
					curUrl = curUrl.replace(/(orderby=)[^&]*/, '$1' + orderBy);
				}
			}
			jQuery(location).attr('href', curUrl);
			return false;
		});
	});

	WpfFrontendPage.prototype.resizeWindow = (function() {
		var _thisObj = this.$obj;
		_thisObj.filterOptionsForDevices();
		jQuery( window ).on('resize', function() {
			_thisObj.filterOptionsForDevices();
		});
	});

	WpfFrontendPage.prototype.copySelectsForSafari = (function() {
		var _thisObj = this.$obj;
		if (!_thisObj.isSafari) return;

		jQuery('.wpfMainWrapper').each(function () {
			var $wrapper = jQuery(this),
				$selectsWrapper = $wrapper.find('.wpfSelectCopies');
			if ($selectsWrapper.length == 0) {
				jQuery('<div/>').addClass('wpfSelectCopies').appendTo($wrapper);
				$selectsWrapper = $wrapper.find('.wpfSelectCopies');
			}
			if ($selectsWrapper.length == 0) return;

			$wrapper.find('.wpfFilterWrapper[data-display-type="dropdown"] select').each(function() {
				var $select = jQuery(this),
					$filter = $select.closest('.wpfFilterWrapper'),
					blockId = $filter.attr('id');
				if ($selectsWrapper.find('select[data-block="' + blockId + '"]').length == 0) {
					$select.clone().attr('data-block', blockId).appendTo($selectsWrapper);
				}
			});
			_thisObj.removeHiddenOptionsForSafari($wrapper);
		});
	});

	WpfFrontendPage.prototype.restoreSelectsForSafari = (function() {
		var _thisObj = this.$obj;
		if (!_thisObj.isSafari) return;

		jQuery('.wpfMainWrapper').each(function () {
			var $wrapper = jQuery(this),
			$selectsWrapper = $wrapper.find('.wpfSelectCopies');
			if ($selectsWrapper.length == 1) {

				$selectsWrapper.find('select').each(function() {
					var $select = jQuery(this),
						blockId = $select.attr('data-block'),
						$filterSelect = $wrapper.find('#' + blockId + ' select');
					if ($filterSelect.length == 1) {
						var value = $filterSelect.val();
						$filterSelect.html($select.html());
						$filterSelect.val(value);
					}
				});
			}
		});
	});

	/**
	 * removeHiddenOptionsForSafari.
	 *
	 * @version 2.8.6
	 */
	WpfFrontendPage.prototype.removeHiddenOptionsForSafari = (function() {
		var _thisObj = this.$obj;
		if (!_thisObj.isSafari) return;

		var $found = jQuery('.wpfFilterWrapper[data-display-type="dropdown"] select:visible option[style*="none"]');
		if ($found.length) $found.remove();
		else {
			jQuery('.wpfFilterWrapper[data-display-type="dropdown"] select:visible option').each(function() {
				if (jQuery(this).css('display') == 'none') jQuery(this).remove();
			});
		}
	});

	WpfFrontendPage.prototype.checkForceFilters = (function() {
		var forceShowFilter = jQuery('.wpfMainWrapper[data-force="1"]');
		if (!forceShowFilter.length) return;

		jQuery('.wpfMainWrapper').each(function () {
			var wrapper = jQuery(this),
				forceShowCurrent = wrapper.attr('data-force');
			if (!forceShowCurrent){
				wrapper.remove();
				if (wrapper.closest('.WpfWoofiltersWidget').length) {
					wrapper.closest('.WpfWoofiltersWidget').remove();
				}
			}
		});
	});

	WpfFrontendPage.prototype.showFiltersLoader = (function() {
		jQuery('.wpfMainWrapper').each(function () {
			var wrapper = jQuery(this);
			wrapper.css('position','relative');
			if (!wrapper.find('.wpfLoaderLayout').length){
				jQuery('<div/>').addClass('wpfLoaderLayout').appendTo(wrapper);
				wrapper.find('.wpfLoaderLayout').append('<i class="fa fa-spinner fa-pulse fa-3x fa-fw"/>');
			}

			wrapper.find('.wpfLoaderLayout').show();
		});
	});

	WpfFrontendPage.prototype.hideFiltersLoader = (function() {
		jQuery('.wpfMainWrapper').each(function () {
			var wrapper = jQuery(this);

			hideFilterLoader(wrapper);
		});
	});

	WpfFrontendPage.prototype.runCustomJs = (function () {
		var _thisObj = this.$obj;
		jQuery('.wpfMainWrapper').each(function () {
			var wrapper = jQuery(this),
				jsCodeStr = '',
				settings = _thisObj.getFilterMainSettings(wrapper);
			if(settings){
				settings = settings.settings;
				jsCodeStr = settings.js_editor;
			}
			if(jsCodeStr.length > 0){
				try {
					eval(jsCodeStr);
				}catch(e) {
					console.log(e);
				}

			}
		});
	});

	WpfFrontendPage.prototype.addCustomCss = (function () {
		if (jQuery('style#wpfCustomCss').length === 0) {
			var cssCodeStr = '';

			jQuery('.wpfMainWrapper').each(function () {
				var wrapper = jQuery(this),
					customCss = jQuery('style#wpfCustomCss-' + wrapper.attr('data-viewid'));
				if (customCss.length) {
					cssCodeStr += customCss.html();
					customCss.remove();
				}
			});
			if (cssCodeStr.length > 0) {
				jQuery('<style type="text/css" id="wpfCustomCss">' + cssCodeStr + '</style>').appendTo('head');
			}
		}
	});

	WpfFrontendPage.prototype.chageRangeFieldWidth = (function () {
		var _thisObj = this.$obj;
		jQuery('.wpfFilterWrapper[data-filter-type="wpfPrice"]').each(function () {
			var filter = jQuery(this),
				input1 = filter.find('#wpfMinPrice'),
				input2 = filter.find('#wpfMaxPrice'),
				fontSize1 = input1.css('font-size'),
				fontSize2 = input2.css('font-size'),
				visbleBuffer1 = filter.find('.wpfVisibleBufferMin'),
				visbleBuffer2 = filter.find('.wpfVisibleBufferMax');
			if(fontSize1) visbleBuffer1.css('font-size', fontSize1);
			if(fontSize2) visbleBuffer2.css('font-size', fontSize2);

			jQuery(visbleBuffer1).text(input1.val());
			jQuery(visbleBuffer2).text(input2.val());

			if (navigator.userAgent.toLowerCase().indexOf('firefox') > -1) {
				jQuery(input1).width(visbleBuffer1.width()+20);
				jQuery(input2).width(visbleBuffer2.width()+20);
			} else {
				jQuery(input1).width(visbleBuffer1.width()+10);
				jQuery(input2).width(visbleBuffer2.width()+10);
			}
		});
	});

	WpfFrontendPage.prototype.eventsPriceFilter = (function () {
		var _thisObj = this.$obj;

		jQuery('.wpfFilterWrapper[data-filter-type="wpfPrice"]').each(function () {
			_thisObj.initDefaultSlider(jQuery(this));
		});

		//change price filters
		jQuery('.wpfFilterWrapper[data-filter-type="wpfPrice"]').on('wpfPriceChange', function(event){
			var filter = jQuery(this),
				mainWrapper = filter.closest('.wpfMainWrapper');

			mainWrapper.find('.wpfFilterWrapper[data-filter-type="wpfPriceRange"] input').prop('checked', false);
			mainWrapper.find('.wpfFilterWrapper[data-filter-type="wpfPriceRange"] select')
				.val(mainWrapper.find('.wpfFilterWrapper[data-filter-type="wpfPriceRange"] select option:first').val());

			filter.removeClass('wpfNotActive');
			mainWrapper.find('.wpfFilterWrapper[data-filter-type="wpfPriceRange"]').addClass('wpfNotActive');
		});

		//change price range
		jQuery('.wpfFilterWrapper[data-filter-type="wpfPriceRange"] input, .wpfFilterWrapper[data-filter-type="wpfPriceRange"] select').on('change', function(e){
			e.preventDefault();

			jQuery('.wpfFilterWrapper[data-filter-type="wpfPrice"]').addClass('wpfNotActive');
		});

	});

	WpfFrontendPage.prototype.initDefaultSlider = (function (filter, type) {
		var _thisObj = this.$obj,
			wrapper = filter.closest('.wpfMainWrapper'),
			filterType = typeof type !== 'undefined' ? type : 'price',
			getAttr = filter.data('get-attribute'),
			minInputId = '#wpfMinPrice',
			maxInputId = '#wpfMaxPrice',
			triggerName = 'wpfPriceChange';

		if (filterType === 'attr') {
			minInputId = '#wpfMinAttrNum';
			maxInputId = '#wpfMaxAttrNum';
			triggerName = 'wpfAttrSliderChange';
		}

		var minSelector = wrapper.find(minInputId),
			maxSelector = wrapper.find(maxInputId),
			wpfDataStep = wrapper.find('#wpfDataStep').val()
		if (wpfDataStep == '0.001') {
			wpfDataStep = '0.00000001';
		}
		wpfDataStep = Number(wpfDataStep);

		var valMin = parseFloat(minSelector.attr('min')),
			valMax = parseFloat(maxSelector.attr('max')),
			curUrl = window.location.href,
			urlParams = _thisObj.findGetParameter(curUrl),
			rate = filter.data('rate');

		urlParams = _thisObj.getConvertedPrices(urlParams, rate);

		var	minPriceGetParams = urlParams.wpf_min_price ? parseFloat(urlParams.wpf_min_price) : valMin,
			maxPriceGetParams = urlParams.wpf_max_price ? parseFloat(urlParams.wpf_max_price) : valMax;

		if (filterType === 'attr') {
			if (urlParams[getAttr]) {
				var idsAnd = urlParams[getAttr].split(','),
					idsOr = urlParams[getAttr].split('|'),
					isAnd = idsAnd.length > idsOr.length;
				var filterTypeValues = isAnd ? idsAnd : idsOr;
			}
			minPriceGetParams = urlParams[getAttr] ? parseFloat(filterTypeValues[0]) : valMin;
			maxPriceGetParams = urlParams[getAttr] ? parseFloat(filterTypeValues.pop()) : valMax;
		}

		var sliderWrapper = filter.find("#wpfSliderRange"),
			autoFilteringEnable = (wrapper.find('.wpfFilterButton').length == 0),
			skin = filter.attr('data-price-skin');
		if(skin === 'default'){
			sliderWrapper.slider({
				range: true,
				orientation: "horizontal",
				min: valMin,
				max: valMax,
				step: wpfDataStep,
				values: [minPriceGetParams, maxPriceGetParams],
				slide: function (event, ui) {
					minSelector.val(ui.values[0]);
					maxSelector.val(ui.values[1]);
					filter.trigger(triggerName);
				},
				start: function () {
					filter.trigger(triggerName);
				},
				stop: function () {
					if(autoFilteringEnable){
						_thisObj.setCurrentLocation();
						_thisObj.filtering(wrapper);
					}
				},
			});
			minSelector.val(sliderWrapper.slider("values", 0));
			maxSelector.val(sliderWrapper.slider("values", 1));
		}
	});

	// add/remove get query param
	WpfFrontendPage.prototype.QStringWork = (function ($attr, $value, $noWooPage, $filterWrapper, $type) {

		$noWooPage = false;
		if (window.wpfAdminPage) {
			$noWooPage = true;
		}
		if($type === 'change'){
			var curUrl = changeUrl($attr, $value, $noWooPage, $filterWrapper );
			$filterWrapper.attr('data-hide-url', decodeURI(curUrl));
		}else if($type === 'remove'){
			var curUrl = removeQString($attr, $noWooPage, $filterWrapper);
			$filterWrapper.attr('data-hide-url', decodeURI(curUrl));
		}
	});

	WpfFrontendPage.prototype.eventChangeFilter = (function (e) {
		var _thisObj = this.$obj,
			_this = jQuery(e.target),
			mainWrapper = _this.closest('.wpfMainWrapper'),
			settings = _thisObj.getFilterMainSettings(mainWrapper);
		_thisObj.setCurrentLocation();

		_this.closest('.wpfFilterWrapper').removeClass('wpfNotActive');
		if(typeof(_thisObj.eventChangeFilterPro) == 'function') {
			_thisObj.eventChangeFilterPro(_this, settings);
		}

		var redirectOnlyClick = Number(settings.settings.redirect_only_click),
		    autoUpdateFilter  = Number(settings.settings.auto_update_filter),
			loaderEnable  = Number(settings.settings.filter_loader_icon_onload_enable),
			isButton = ( mainWrapper.find('.wpfFilterButton').length > 0 ),
			isCheckbox = _this.attr('type') == 'checkbox',
			redirectLink = isCheckbox ? _this.closest('li' ).attr('data-link') : _this.find('option:selected').attr('data-link');

		if (typeof redirectLink !== 'undefined' && isCheckbox) {
			var filter = _this.closest('.wpfFilterWrapper');
			if (filter.attr('data-display-type') != 'list' && _this.is(':checked')){
				filter.find('input').prop('checked', false);
				_this.prop('checked', true);
			}
		}

		if (isButton) {
			// if there is a button and autoUpdateFilter is selected, then we only change the filter.
			// If redirectOnlyClick is selected, then we change the filter and products, but do not redirect until the button is clicked

			if (autoUpdateFilter || redirectOnlyClick) {
				if (loaderEnable) {
					mainWrapper.find('.wpfLoaderLayout').show();
				}
				_thisObj.filterClick = false;
				_thisObj.filtering(mainWrapper, false, redirectLink);
			}

		} else {
			_thisObj.filterClick = true;
			_thisObj.filtering(mainWrapper, false, redirectLink);
		}
	});


	WpfFrontendPage.prototype.eventsFrontend = (function () {
		var _thisObj = this.$obj,
			searchParams = jQuery.toQueryParams(window.location.search);

		//for Impreza LoadMore Pagination
		if (searchParams['all_products_filtering'] && searchParams['all_products_filtering'] == '1') {
			jQuery( document ).ajaxSend(function( event, jqxhr, settings ) {
				if (settings.data) {
					var response = _thisObj.unserializeStr(settings.data);
					if (response['action'] && response['action'] == 'us_ajax_grid') {
						if (response['template_vars'] && response['template_vars'].length) {
							try {
								var query = JSON.parse(response['template_vars']);
							}catch(e){
								var query = false;
							}
							if (query && query['query_args']) {
								var args = query['query_args'];
								if (args['product_cat']) delete args.product_cat;
								if (args['product_tag']) delete args.product_tag;
								response['template_vars'] = JSON.stringify(query);
								var result = [];
								jQuery.each(response, function(key, val) {
									result.push(encodeURIComponent(key) + "=" + encodeURIComponent(val));
								});
								settings.data = result.join("&").replace(/%20/g, "+");
							}
						}
					}
				}
			});
		}
		//for Divi Filter LoadMore / eael-pagination / premium-woo-products
		if (jQuery('.divi-filter-archive-loop, .eael-woo-pagination, .premium-woo-products-pagination, .uael-woocommerce-pagination').length) {
			var actions = ['divi_filter_loadmore_ajax_handler', 'woo_product_pagination_product', 'get_woo_products', 'uael_get_products'];
			jQuery( document ).ajaxSend(function( event, jqxhr, settings ) {
				if (settings.data) {
					var response = _thisObj.unserializeStr(settings.data);
					if (response['action'] && actions.indexOf(response['action']) != -1) {
						var s = window.location.search;
						if (s && s.length > 1) {
							response['with_wpf_filter'] = s.replace('?','');
							var result = [];
							jQuery.each(response, function(key, val) {
								result.push(encodeURIComponent(key) + "=" + encodeURIComponent(val));
							});
							settings.data = result.join("&").replace(/%20/g, "+");
						}
					}
				}
			});
		}
		//for woocommerce-blocks (All products and others)
		if (typeof window.wpfFetchHookCreated == 'undefined' || window.wpfFetchHookCreated != 1) {
			window.fetch = new Proxy(window.fetch, {
				apply(fetch, that, args) {
					var url = args.length ? args[0] : '';
					if (typeof url === 'string' && url.length) {
						if (url.indexOf('wp-json/wc/store/') != -1 && url.indexOf('/products?') != -1 && url.indexOf('per_page=') != -1) {
							var s = window.location.search;
							if (s.length) args[0] += s.replace('?','&');
							else {
								var urlPreselects = '';
								jQuery('.wpfMainWrapper').each(function() {
									var settings = _thisObj.getFilterMainSettings(jQuery(this)),
										preselects = typeof settings.settings.filters.preselect !== 'undefined' ? settings.settings.filters.preselect : '';
									if (preselects.length) {
										urlPreselects += '&'+preselects.replace(/;/g,'&');
										if (settings.settings.filtering_by_variations=='1') urlPreselects += '&wpf_fbv=1';
										if (settings.settings.exclude_backorder_variations=='1') urlPreselects += '&wpf_ebv=1';
										if (settings.settings.display_product_variations=='1') urlPreselects += '&wpf_dpv=1';
									}
								});
								if (urlPreselects.length) {
									args[0] += urlPreselects + '&wpf_preselects=1';

								}
							}
						} else {
							//Elementor Loop Load More
							if ((jQuery('.e-load-more-anchor').length == 1) && jQuery('.e-load-more-spinner').length && url.indexOf('?') == -1) {
								var $elementorLoadMoreAnchor = jQuery('.e-load-more-anchor'),
									s = window.location.search;
								if (s.length) {
									var nextPage = $elementorLoadMoreAnchor.attr('data-next-page');
									if (nextPage == url) args[0]+= s;
								}

							}
						}
					}
					const result = fetch.apply(that, args);
					return result;
				}
			});
			window.wpfFetchHookCreated = 1;
		}

		//for themes with ajax-paginations, ajax-ordering
		jQuery(document).ajaxComplete(function(event, xhr, options) {
			setTimeout(function() {
				if (jQuery('.wpfLoaderLayout:visible').length) {
					window.wpfFrontendPage.init();
					if (typeof(window.wpfFrontendPage.eventsFrontendPro) == 'function') {
						window.wpfFrontendPage.eventsFrontendPro();
					}
				}
			}, 500);
			//if (jQuery('.wpfLoaderLayout:visible').length) window.wpfFrontendPage.init();
		});

		jQuery('.wpfMainWrapper').find('select[multiple]').each(function(){
			var select = jQuery(this),
				selectAll = select.attr('data-placeholder'),
				search = JSON.parse(select.attr('data-search')),
			    singleSelect = select.data('single-select'),
				showCheckbox = (typeof select.data('hide-checkboxes') === 'undefined');

			setTimeout(function () {
				select.multiselect({
					search: search.show,
					columns: 1,
					placeholder: selectAll ? selectAll : 'Select options',
					optionAttributes: ['style', 'data-term-id'],
					searchOptions: {'default': search.placeholder},
					showCheckbox: showCheckbox,
					onOptionClick: function (element, option) {
						if (typeof singleSelect !== 'undefined') {
							var value = jQuery(option).val();
							jQuery(option).closest('ul').find('input[type="checkbox"][value!=' + value + ']').prop('checked', false);
							jQuery(element).val(value);
							jQuery(element).siblings('.ms-options-wrap').find('.ms-options:visible').hide();
						}
					},
				});
			}, 100);

			if (search.show) {
				jQuery('.ms-options-wrap').on('click', 'button', function () {
					$(this).next('.ms-options').find('.ms-search input').focus();
				});
			}

		});

		if(jQuery('.wpfFilterWrapper[data-filter-type="wpfSortBy"]').length == 0) {
			jQuery('.woocommerce-ordering').css('display', 'block');
		}

		jQuery('.wpfFilterWrapper[data-hide-single="1"]').each(function(){
			var filter = jQuery(this),
				selector = filter.find('.wpfColorsFilter').length ? 'li[data-term-slug]' : '[data-term-id]',
				visible = 0;
			if (filter.attr('data-display-type') == 'slider') {
				var idsDef = filter.attr('data-ids-without-filtering').replaceAll(', ',',').split(',');
				if (idsDef.length <= 1) filter.hide();
			} else {
				filter.find(selector).each(function(){
					if (jQuery(this).css('display') != 'none') visible++;
				});
				if (visible <= 1) {
					filter.hide();
				}
			}
		});

		//if no enabled filters hide all html
		if(jQuery('.wpfFilterWrapper').length < 1){
			jQuery('.wpfMainWrapper').addClass('wpfHidden');
		}

		//Start filtering
		jQuery('body').on('mousedown', '.wpfFilterButton, .js-wpfFilterButtonSearch', function (e) {
			e.preventDefault();
			var $this = jQuery(this),
				mainWrapper = $this.closest('.wpfMainWrapper'),
				inputSearch = mainWrapper.find('.js-passiveFilterSearch');
			if (inputSearch.length) {
				inputSearch.each(function() {
					var $sElem = jQuery(this);
					if ($sElem.val() !== '') {
						$sElem.closest('.wpfFilterWrapper').removeClass('wpfNotActive');
					}
					$sElem.trigger('blur');
				});

			}
			applyFilter(_thisObj, $this);
		});

		jQuery('input.js-passiveFilterSearch').on('keydown', function (e) {
			var char_code = e.which;
			if (parseInt(char_code) == 13) {
				e.preventDefault();
				var $this = jQuery(this);
				$this.closest('.wpfFilterWrapper').removeClass('wpfNotActive');
				$this.trigger('blur');
				applyFilter(_thisObj, $this);
			}
		});


		//Clear filters
		jQuery('body').on('click', '.wpfClearButton', function (e) {
			e.preventDefault();
			var $filterWrapper = jQuery(this).closest('.wpfMainWrapper'),
				settings = _thisObj.getFilterMainSettings($filterWrapper),
				resetAllFilters = typeof settings.settings.reset_all_filters !== 'undefined' ? settings.settings.reset_all_filters : 0;
			_thisObj.setCurrentLocation();

			if (resetAllFilters !== '0') {
				jQuery('.wpfMainWrapper').each(function(){
					_thisObj.clearFilters(jQuery(this).find('.wpfFilterWrapper'), true);
				});
			} else {
				_thisObj.clearFilters($filterWrapper.find('.wpfFilterWrapper'), true);
			}
			if (Number(settings.settings.redirect_after_select) || Number(settings.settings.redirect_only_click)) {
				_thisObj.filterClick = false;
			}
			_thisObj.filtering($filterWrapper, true);

			if (typeof (_thisObj.initOneByOne) == 'function') {
				_thisObj.initOneByOne($filterWrapper);
			}

		});

		//price range choose only one checkbox
		jQuery('.wpfFilterWrapper[data-filter-type="wpfPriceRange"] .wpfFilterContent input').on('change', function (e) {
			e.preventDefault();
			var input = jQuery(this),
				inputs = input.closest('.wpfFilterWrapper').find('input');
			if(input.is(":checked")){
				inputs.prop('checked', false);
				input.prop('checked', true);
			}
		});

		//category/brand list choose only one checkbox
		jQuery('.wpfFilterWrapper[data-filter-type="wpfCategory"], .wpfFilterWrapper[data-filter-type="wpfPerfectBrand"], .wpfFilterWrapper[data-filter-type="wpfAttribute"]').each(function() {
			var categoryFilter = jQuery(this),
				displayType = categoryFilter.data('display-type'),
				categoryMulti = displayType == 'multi';
			if (categoryFilter.data('filter-type') == 'wpfAttribute') {
				if (displayType == 'list' || displayType == 'switch') categoryMulti = true;
			}

			categoryFilter.find('.wpfFilterContent input').on('change', function (e) {
				e.preventDefault();
				var input = jQuery(this);
				if(categoryMulti) {
					var mainWrapper = input.closest('.wpfMainWrapper'),
						filterWrapper = input.closest('.wpfFilterWrapper'),
						expandSelectedToChild = _thisObj.getFilterParam('f_multi_extend_parent_select', mainWrapper, filterWrapper);

					if(expandSelectedToChild && input.is(':checked')) {
						input.closest('li').find('ul input').prop('checked', true);
					}

					if (expandSelectedToChild && ! input.is(':checked') ) {
						input.closest('li').find('ul input').prop('checked', false);
					}
				}
			});
		});

		//rating choose only one checkbox
		jQuery('.wpfFilterWrapper[data-filter-type="wpfRating"] .wpfFilterContent input').on('change', function (e) {
			e.preventDefault();
			var input = jQuery(this),
				inputs = input.closest('.wpfFilterWrapper').find('input');
			if(input.is(":checked")){
				inputs.prop('checked', false);
				input.prop('checked', true);
			}
		});

		//after change input or dropdown make this filter active
		//check if ajax mode enable and filtering on filter elements change
		jQuery('body').off('change', '.wpfFilterWrapper select, .wpfFilterWrapper input:not(.passiveFilter)').on('change', '.wpfFilterWrapper select, .wpfFilterWrapper input:not(.passiveFilter)', function (e) {
			e.preventDefault();
			var isExeptionCase = _thisObj.checkExeptionCasesBeforeFiltering(this);
			// exeption checkbox in multidropdown
			if (!isExeptionCase) {
				var $this = jQuery(this);
				if ($this.is('input') && $this.closest('.wpfFilterWrapper').data('display-type') == 'mul_dropdown') {
					isExeptionCase = true;
				}
			}

			if (!isExeptionCase) {
				setTimeout(function() {
					_thisObj.eventChangeFilter(e);
				}, 100);
			}
		});

		//after change input or dropdown make this filter active
		jQuery('.wpfFilterWrapper input:not(.passiveFilter)').on('change', function (e) {
			e.preventDefault();
			// check if input change move to top
			_thisObj.moveCheckedToTop(jQuery(this));
			// check multy or single input (radio or checkbox)
			_thisObj.detectSingleCheckbox(jQuery(this))
			// Mark selected
			_thisObj.markCheckboxSelected(jQuery(this).closest('.wpfFilterWrapper'));
		});

		jQuery('.wpfMainWrapper').each(function() {
			var mainWrapper = jQuery(this),
				settings = _thisObj.getFilterMainSettings(mainWrapper);
			if (settings && settings.settings && settings.settings.checked_items_top === '1') {
				mainWrapper.find('.wpfFilterWrapper input').attr('autocomplete', 'off');
				mainWrapper.find('.wpfFilterWrapper input:checked').each(function() {
					_thisObj.moveCheckedToTop(jQuery(this), false);
				});
			}
		});

		/*jQuery('.wpfFilterWrapper input:checked').each(function() {
			_thisObj.moveCheckedToTop(jQuery(this), false);
		});*/

		//search field work
		jQuery('.wpfFilterWrapper .wpfSearchFieldsFilter').on('keyup', function (e) {
			var _this = jQuery(this),
				wrapper = _this.closest('.wpfFilterWrapper'),
				searchVal = _this.val().toLowerCase(),
				isIconFunc = typeof (_thisObj.getIcons) == 'function',
				unfolding = (searchVal.length && _this.attr('data-unfolding') == '1' && isIconFunc),
				collapse = (searchVal.length == 0 && _this.attr('data-collapse-search') == '1' && isIconFunc);
			wrapper.find('.wpfFilterContent li:not(.wpfShowFewerWrapper)').filter(function() {
				var $li = jQuery(this);
				if ($li.find('.wpfValue').text().toLowerCase().indexOf(searchVal) > -1) {
					$li.removeClass('wpfSearchHidden');
					if (unfolding || collapse) {
						var $parentLi = $li.closest('li');
						while ($parentLi.length && $parentLi.closest('.wpfFilterWrapper')) {
							var $label = $parentLi.children('label'),
							    $icon = $label.length ? $label.find('i.fa, svg') : '';
							if ($icon.length) {
								var $icons = _thisObj.getIcons($icon.eq(0));
								if ($icons.collapsed && unfolding || !$icons.collapsed && collapse) {
									_thisObj.collapsibleToggle($icon.eq(0), $icons, $parentLi);
								}
							}
							$parentLi = $parentLi.closest('ul').closest('li');
						}
					}
				} else {
					$li.addClass('wpfSearchHidden');
				}
			});
			if(typeof(_thisObj.initShowMore) == 'function') {
				_thisObj.initShowMore(wrapper.find('.wpfFilterVerScroll'));
			}
		});
		/*jQuery('.wpfFilterWrapper .wpfSearchFieldsFilter').on('change', function (e) {
			jQuery(this).closest('.wpfFilterWrapper').find('.wpfFilterContent li.wpfSearchHidden .wpfCheckbox input').prop('checked', false);
		});*/

		//uncheck one slug
		jQuery('body').off('click', '.wpfSlugDelete').on('click', '.wpfSlugDelete', function(){
			var _this = jQuery(this),
				wrapper = _this.closest('.wpfSlug'),
				filterType = wrapper.attr('data-filter-type'),
				filterAttr = wrapper.attr('data-get-attribute'),
				filterWrapper = false;
			_thisObj.setCurrentLocation();

			jQuery('.wpfFilterWrapper[data-filter-type="'+filterType+'"][data-get-attribute="'+filterAttr+'"]').each(function(){
				var $this = jQuery(this),
					filterType = $this.attr("data-filter-type");
				if (filterType == 'wpfPrice' || filterType == 'wpfPriceRange') {
					_thisObj.clearFilters($("[data-filter-type='wpfPrice']:not(.wpfSelectedParameter)"));
					_thisObj.clearFilters($("[data-filter-type='wpfPriceRange']:not(.wpfSelectedParameter)"));
				} else {
					_thisObj.clearFilters($this);
				}
				if(filterWrapper == false) {
					filterWrapper = $this.closest('.wpfMainWrapper');
				}
			});
			if(filterWrapper != false) {
				_thisObj.filtering(filterWrapper);
			}
		});

		jQuery('body').off('click', '.wpfFilterWrapper .wpfFilterTitle').on('click', '.wpfFilterWrapper .wpfFilterTitle', function (e) {
			e.preventDefault();
			var _this = jQuery(this),
				wrapper = _this.closest('.wpfMainWrapper'),
				settings = _thisObj.getFilterMainSettings(wrapper),
				content = _this.closest('.wpfFilterWrapper').find('.wpfFilterContent');

			setTimeout(function () {
				var toggle = _this.find('i.wpfTitleToggle, svg'),
					icons  = {};
				if (toggle.length) {
					if (typeof (_thisObj.getIcons) == 'function') {
						icons = _thisObj.getIcons(toggle);
					} else {
						icons = {collapsed: toggle.hasClass('fa-plus'), plusIcon: 'fa-plus', minusIcon: 'fa-minus'};
					}

					if (settings.settings.hide_filter_icon !== '0') {
						if (icons.collapsed) {
							_thisObj.openFilterToggle(toggle, content, true, icons);
						} else {
							_thisObj.closeFilterToggle(toggle, content, true, icons);
						}
					}
				}
			}, 100);
		});

		jQuery('body').off('click', '.wpfFilterWrapper .wpfBlockClear').on('click', '.wpfFilterWrapper .wpfBlockClear',  function(){
			var parent = jQuery(this).closest(".wpfFilterWrapper"),
				parentAttr = parent.attr("data-filter-type");
			_thisObj.setCurrentLocation();
			if (parentAttr == 'wpfPrice' || parentAttr == 'wpfPriceRange') {
				_thisObj.clearFilters($("[data-filter-type='wpfPrice']:not(.wpfSelectedParameter)"));
				_thisObj.clearFilters($("[data-filter-type='wpfPriceRange']:not(.wpfSelectedParameter)"));
			} else {
				_thisObj.clearFilters(parent);
			}
			_thisObj.filtering(parent.closest('.wpfMainWrapper'));
			return false;
		});

		jQuery('body').off('wpffiltering').on('wpffiltering', function () {
			_thisObj.setPagination(1);
			_thisObj.setCurrentLocation();
			_thisObj.filtering();
			_thisObj.setPagination(0);
		});

		//click on new pagination link with page number
		jQuery('body').off('click', '.wpfNoWooPage .woocommerce-pagination a.page-numbers').on('click', '.wpfNoWooPage .woocommerce-pagination a.page-numbers', function (e) {
			e.preventDefault();
			var _this = jQuery(this),
				paginationWrapper = _this.closest('.woocommerce-pagination'),
				currentNumber = paginationWrapper.find('.current').text();
			if(!_this.hasClass('next') && !_this.hasClass('prev') ){
				var number = _this.text();
			}else if(_this.hasClass('next')){
				var number = parseInt(currentNumber) + 1;
			}else if(_this.hasClass('prev')){
				var number = (parseInt(currentNumber) - 1) < 1 ? parseInt(currentNumber) - 1 : 1;
			}
			var wrapper = jQuery('.wpfMainWrapper').first(),
				$queryVars = wrapper.attr('data-settings');
			try{
				var settings = JSON.parse($queryVars);
			}catch(e){
				var settings = false;
			}
			if(settings){
				settings.paged = number;
				settings.pagination = 1;
				wrapper.attr('data-settings', JSON.stringify(settings) );
			}
			_thisObj.setCurrentLocation();

			// todo: testing for two+ filters on page
			_thisObj.filtering( jQuery('.wpfMainWrapper') );
			_thisObj.setPagination(0);
		});

		var prevLocation = location.href.split('#').shift();
		jQuery(window).on('popstate', function (e) {

			var currentLocation = location.href.split('#').shift();
			if (location.href.split('#').length > 1 && prevLocation === currentLocation) {
				return;
			}

			if (typeof window.wpfAdminPage === 'undefined' && !_thisObj.isSafari) {
				location.reload();
			}
		});
		jQuery('.wpfPreselected:not([data-filter-type="wpfPriceRange"]) .wpfCheckbox input').prop('checked', true);

		jQuery('#wpfOverlay:not([data-filter]').each(function() {
			var $overlay = jQuery(this),
				$wrapper = $overlay.closest('.wpfMainWrapper');
			$overlay.attr('data-filter-for', $wrapper.attr('id')).attr('data-filter',$wrapper.attr('data-filter'));
			$overlay.appendTo('body');
		});

	});

	function applyFilter(_thisObj, $this) {
		var mainWrapper = $this.closest('.wpfMainWrapper');
		_thisObj.setCurrentLocation();

		_thisObj.filterClick = true;
		_thisObj.filtering(mainWrapper);
	}

	WpfFrontendPage.prototype.filterOptionsForDevices = (function () {
		var _thisObj = this.$obj;
		jQuery('.wpfMainWrapper .wpfFilterWrapper').each(function () {
			var _this = jQuery(this),
				wrapper = _this.closest('.wpfMainWrapper'),
				settings = _thisObj.getFilterMainSettings(wrapper),
				isMobile = false,
				screenSize = jQuery(window).width();

			if (settings.settings !== undefined) {
				var isMobileBreakpoint = settings.settings.desctop_mobile_breakpoint_switcher,
					mobileBreakpoinWidth = isMobileBreakpoint && isMobileBreakpoint == '1' ? settings.settings.desctop_mobile_breakpoint_width : '0',

					displayFor = settings.settings.display_for,

					filterWidthDesktop          = settings.settings.filter_width,
					filterWidthDesktopUnit      = settings.settings.filter_width_in,
					filterWidthMobile           = settings.settings.filter_width_mobile,
					filterWidthMobileUnit       = settings.settings.filter_width_in_mobile,
					filterBlockWidthDesktop     = settings.settings.filter_block_width,
					filterBlockWidthDesktopUnit = settings.settings.filter_block_width_in,
					filterBlockWidthMobile      = settings.settings.filter_block_width_mobile,
					filterBlockWidthMobileUnit  = settings.settings.filter_block_width_in_mobile;

				if (mobileBreakpoinWidth && '0' !== mobileBreakpoinWidth) {
					if (screenSize <= mobileBreakpoinWidth) {
						isMobile = true;
					}

					// "Filter Width" and "Filter Block Width" options
					if (isMobile && filterBlockWidthMobile != '0') {
						wrapper.css('width', filterWidthMobile+filterWidthMobileUnit);
						_this.css('width', filterBlockWidthMobile+filterBlockWidthMobileUnit);
						if (filterBlockWidthMobile+filterBlockWidthMobileUnit != '100%') {
							_this.css('float', 'left');
						}
					} else if (!isMobile && filterBlockWidthDesktop != '0') {
						wrapper.css('width', filterWidthDesktop+filterWidthDesktopUnit);
						_this.css('width', filterBlockWidthDesktop+filterBlockWidthDesktopUnit);
						if (filterBlockWidthDesktop+filterBlockWidthDesktopUnit != '100%') {
							_this.css('float', 'left');
						}
					}

					// "Display filter on"  option
					if (isMobile && displayFor == 'desktop') {
						wrapper.hide();
					} else if (!isMobile && displayFor == 'mobile') {
						wrapper.hide();
					}

					// filters title
					_this.find('.wpfFilterTitle[data-show-on-mobile]').each(function() {
						jQuery(this).closest('.wpfFilterMainWrapper').find('wpfLoaderLayout').show();
							var showDesctop = jQuery(this).data('show-on-desctop'),
								showMobile = jQuery(this).data('show-on-mobile'),
								content = jQuery(this).closest('.wpfFilterWrapper').find('.wpfFilterContent'),
								title = jQuery(this).find('.wfpTitle'),
								icons = {};
						title.show();

						var toggle = jQuery(this).closest('.wpfFilterWrapper').find('i.wpfTitleToggle, svg');
						setTimeout(function () {
							if (toggle.length) {
								toggle.show();
								if (typeof (_thisObj.getIcons) == 'function') {
									icons = _thisObj.getIcons(toggle);
								} else {
									icons = {collapsed: toggle.hasClass('fa-plus'), plusIcon: 'fa-plus', minusIcon: 'fa-minus'};
								}

								if (isMobile) {
									if (showMobile == 'yes_open') {
										_thisObj.openFilterToggle(toggle, content, false, icons);
									} else if (showMobile == 'yes_close') {
										_thisObj.closeFilterToggle(toggle, content, false, icons);
									} else if (showMobile == 'no') {
										_thisObj.openFilterToggle(toggle, content, false, icons);
										toggle.hide();
										title.hide();
									}
								} else {
									if (showDesctop == 'yes_open') {
										_thisObj.openFilterToggle(toggle, content, false, icons);
									} else if (showDesctop == 'yes_close') {
										_thisObj.closeFilterToggle(toggle, content, false, icons);
									} else if (showDesctop == 'no') {
										_thisObj.openFilterToggle(toggle, content, false, icons);
										toggle.hide();
										title.hide();
									}
								}
							}
						}, 100);
					});
				}
			}
		});
	});

	WpfFrontendPage.prototype.checkExeptionCasesBeforeFiltering = (function (filterInput) {
		var isExeption = false;
		// exeption when custom price do not set but input activated
		if ( jQuery(filterInput).parent().hasClass('wpfPriceCheckboxCustom')) {
			var customPriceWrapper = jQuery(filterInput).closest('li'),
				customMin = customPriceWrapper.find('input[name=wpf_custom_min]').val(),
				customMax = customPriceWrapper.find('input[name=wpf_custom_max]').val();

			if (!customMin && !customMax) {
				isExeption = true;
			}
		}
		return isExeption;
	});

	WpfFrontendPage.prototype.detectSingleCheckbox = (function (checkedInput) {
		var filterWrapper = checkedInput.closest('.wpfFilterWrapper'),
			displayType = filterWrapper.data('display-type'),
			filterType = filterWrapper.data('filter-type');

		if (filterType == 'wpfCategory' || filterType == 'wpfPerfectBrand'|| filterType == 'wpfBrand') {
			var isOne = displayType == 'list';
		} else {
			var isOne = displayType == 'radio';
		}

		if (isOne) {
			var inputs = filterWrapper.find('input');

			if (checkedInput.is(':checked')) {
				inputs.prop('checked', false);
				checkedInput.prop('checked', true);
			}
		}
	});

	WpfFrontendPage.prototype.moveCheckedToTop = (function (checkedInput, setPause) {
		var _thisObj = this.$obj,
			sPause = setPause === false ? 0 : 200;
		setTimeout(function() {
			var checkboxWrapper = checkedInput.closest('li'),
				mainWrapper = checkedInput.closest('.wpfMainWrapper'),
				filterWrapper = checkedInput.closest('.wpfFilterWrapper'),
				isHierarchical = filterWrapper.data('show-hierarchical'),
				settings = _thisObj.getFilterMainSettings(mainWrapper),
				checkboxesWrapper = checkedInput.closest(isHierarchical ? 'ul' : '.wpfFilterVerScroll');
			//if(settings && !isHierarchical) {
			if(settings && checkboxesWrapper.length) {
				settings = settings.settings;
				var checkedItemsTop = settings.checked_items_top === '1',
					isExeptionCase = _thisObj.checkExeptionCasesBeforeFiltering(checkedInput);
				if(checkedItemsTop && !isExeptionCase){
					if (checkedInput.is(":checked")) {
						checkboxesWrapper.prepend(checkboxWrapper);
					} else {
						checkboxesWrapper.append(checkboxWrapper);
						checkboxesWrapper.append(checkboxesWrapper.find('.wpfShowFewerWrapper,.wpfShowMoreWrapper'));
					}
					checkboxesWrapper.scrollTop(0);
				}
			}
		}, sPause);
	});

	WpfFrontendPage.prototype.closeFilterToggle = (function (toggle, content, isTimeout, icons) {
		if (toggle.hasClass(icons.minusIcon)) {
			toggle.removeClass(icons.minusIcon);
			toggle.addClass(icons.plusIcon);
			content.addClass('wpfBlockAnimated');
			if (typeof isTimeout !== 'undefined' && isTimeout) {
				setTimeout(function () {
					if (content.hasClass('wpfBlockAnimated')) content.addClass('wpfHide');
				}, 10);
			} else {
				if (content.hasClass('wpfBlockAnimated')) content.addClass('wpfHide');
			}
		}
	});

	WpfFrontendPage.prototype.openFilterToggle = (function (toggle, content, isTimeout, icons) {
		if (toggle.hasClass(icons.plusIcon)) {
			toggle.removeClass(icons.plusIcon);
			toggle.addClass(icons.minusIcon);
			content.removeClass('wpfHide');
			if (typeof isTimeout !== 'undefined' && isTimeout) {
				setTimeout(function () {
					if (!content.hasClass('wpfHide')) content.removeClass('wpfBlockAnimated');
				}, 400);
			} else {
				if (!content.hasClass('wpfHide')) content.removeClass('wpfBlockAnimated');
			}
		}
	});

	WpfFrontendPage.prototype.setPagination = (function (pagination) {
		var wrapper = jQuery('.wpfMainWrapper').first(),
			$queryVars = wrapper.attr('data-settings');
		try{
			var settings = JSON.parse($queryVars);
		}catch(e){
			var settings = false;
		}
		if(settings){
			settings.pagination = pagination;
			wrapper.attr('data-settings', JSON.stringify(settings) );
		}
	});
	WpfFrontendPage.prototype.setCurrentLocation = (function() {
		app.wpfOldUrl = window.location.href;
		app.wpfNewUrl = app.wpfOldUrl;
	});

	WpfFrontendPage.prototype.filtering = (function ($filterWrapper, clearAll, redirectLink, onlyRecalcFilter) {
		var _thisObj = this.$obj;
		_thisObj.chageRangeFieldWidth();
		if(_thisObj.isAdminPreview) return;

		if(typeof $filterWrapper == 'undefined' || $filterWrapper.length == 0) {
			$filterWrapper = jQuery('.wpfMainWrapper').first();
		}
		if (_thisObj.filterClick && $filterWrapper.length) {
			_thisObj.createOverlay($filterWrapper.attr('id'));
		}
		if (typeof (_thisObj.beforeFilteringPro) === 'function') {
			_thisObj.beforeFilteringPro($filterWrapper);
		}

		_thisObj.isSynchro = false;
		_thisObj.isStatistics = false;

		if ($filterWrapper.length !== 0) {
			_thisObj.filteringId++;
			_thisObj.lastFids[$filterWrapper.data('viewid')] = _thisObj.filteringId;

			var $filtersDataBackend = [],
				$filtersDataFrontend = [],
				noWooPage = _thisObj.noWoo,
				$generalSettings = _thisObj.getFilterMainSettings($filterWrapper);
			_thisObj.isSynchro = $generalSettings['settings']['use_filter_synchro'] && ($generalSettings['settings']['use_filter_synchro'] == '1') ? true : false;
			_thisObj.isStatistics = $filterWrapper.attr('data-is-stats') == 1;

			if(_thisObj.isSynchro) {
				_thisObj.syncronizeFilters($filterWrapper);
			}

			(_thisObj.isSynchro ? jQuery('.wpfMainWrapper') : $filterWrapper).find('.wpfFilterWrapper:not(.wpfNotActive), .wpfFilterWrapper.wpfPreselected').each(function () {
				var $filter = jQuery(this),
					filterType = $filter.attr('data-filter-type'),
					filterName = $filter.attr('data-get-attribute'),
					wrapper = $filter.closest('.wpfMainWrapper'),
					idFilter = wrapper.data('filter'),
					uniqId = $filter.attr('data-uniq-id'),
					allSettings = _thisObj.getFilterOptionsByType($filter, filterType),
					valueToPushBackend = {},
					valueToPushFrontend = {},
					logic = $filter.attr('data-query-logic'),
					isGroup = false,
					cgIndex = null;
				if(_thisObj.isStatistics && typeof (_thisObj.prepareStatisticsData) == 'function') {
					_thisObj.prepareStatisticsData($filter, allSettings);
				}

				try {
					var order = JSON.parse(_thisObj.getFilterMainSettings(wrapper).settings.filters.order);
					jQuery.each(order, function (i, v) {
						if (v.uniqId === uniqId) {
							jQuery.each(v.settings, function (i, v) {
								cgIndex = i.match(/^f_cglist\[(\d+)\]/);
								if (cgIndex !== null && typeof cgIndex[1] !== 'undefined' && v !== '') {
									isGroup = true;
								}
							});
						}
					});
				} catch (e) {
				}

				if (typeof logic === 'undefined') {
					logic = 'or';
				} else if (logic == 'not') {
					var notIds = $filter.attr('data-not-ids');
					if (notIds && notIds.length > 0) {
						allSettings = {backend: notIds.split(',')};
					}
				}
				var withChildren = $filter.attr('data-query-children');
				if (typeof withChildren === 'undefined') {
					withChildren = '1';
				}

				if (allSettings['backend'].length && typeof allSettings['backend'] !== 'undefined' || filterType === 'wpfSearchText'  || filterType === 'wpfSearchNumber') {
					valueToPushBackend['id'] = filterType;
					valueToPushBackend['uniqId'] = uniqId;
					valueToPushBackend['logic'] = logic;
					valueToPushBackend['children'] = withChildren;
					valueToPushBackend['settings'] = allSettings['backend'];
					valueToPushBackend['name'] = filterName;
					$filtersDataBackend.push(valueToPushBackend);
				}
				if ('frontend' in allSettings) {

					valueToPushFrontend['id'] = filterType;

					var logicDelimetrList = {
						or: '|',
						and: ',',
						not_in: ';'
					}

					valueToPushFrontend['delim'] = logicDelimetrList[logic];
					valueToPushFrontend['children'] = withChildren;
					valueToPushFrontend['settings'] = allSettings['frontend'];
					valueToPushFrontend['name'] = filterName;
					if (isGroup) {
						valueToPushFrontend['idFilter'] = idFilter;
						valueToPushFrontend['idBlock'] = uniqId.replace(/^wpf/, '');
					}
					$filtersDataFrontend.push(valueToPushFrontend);
				}
			});

			var redirectTerm = typeof redirectLink !== 'undefined';
			if (redirectTerm || (typeof $filterWrapper.data('redirect-page-url') !== 'undefined' && _thisObj.filterClick)) {
				history.pushState({state: 1, rand: Math.random(), wpf: true}, '', location.protocol + '//' + location.host + location.pathname);
			}

			var filterId = $filterWrapper.data('filter');
			if (typeof filterId !== 'undefined') {
				jQuery('span.wpfHidden').each(function () {
					var $span = jQuery(this),
						attribute = $span.data('shortcode-attribute'),
						fclass = 'wpf-filter-' + filterId;
					if ((attribute.class !== '' && fclass === attribute.class) || $span.closest('.'+fclass).length) {
						_thisObj.QStringWork('wpf_id', filterId, noWooPage, $filterWrapper, 'change');
					}
				});
			}

			_thisObj.changeUrlByFilterParams($filtersDataFrontend);
			_thisObj.QStringWork('wpf_reload', '', noWooPage, $filterWrapper, 'remove');

			//get paged params from html
			var $queryVars = $filterWrapper.attr('data-settings'),
				$defQuery = $filterWrapper.attr('data-default-query'),
				$queryVarsSettings = JSON.parse($queryVars);

			var $filterSettings = $generalSettings === false ? {} : {
				'wpf_fid': _thisObj.filteringId,
				'filter_recount': $generalSettings['settings']['filter_recount'] && ($generalSettings['settings']['filter_recount'] == '1') ? true : false,
				'filter_recount_price': $generalSettings['settings']['filter_recount_price'] && ($generalSettings['settings']['filter_recount_price'] == '1') ? true : false,
				'text_no_products': $generalSettings['settings']['text_no_products'] ? $generalSettings['settings']['text_no_products'] : '',
				'count_product_shop': $generalSettings['settings']['count_product_shop'] ? parseInt($generalSettings['settings']['count_product_shop']) : 0,
				'f_multi_logic': $generalSettings['settings']['f_multi_logic'] ? $generalSettings['settings']['f_multi_logic'] : 'and',
				'remove_actions': $generalSettings['settings']['remove_actions'] && ($generalSettings['settings']['remove_actions'] == '1') ? true : false,
				'filtering_by_variations': $generalSettings['settings']['filtering_by_variations'] && ($generalSettings['settings']['filtering_by_variations'] == '1') ? true : false,
				'form_filter_by_variations': $generalSettings['settings']['form_filter_by_variations'] && ($generalSettings['settings']['form_filter_by_variations'] == '1') ? true : false,
				'exclude_backorder_variations': $generalSettings['settings']['exclude_backorder_variations'] && ($generalSettings['settings']['exclude_backorder_variations'] == '1') ? true : false,
				'display_product_variations': $generalSettings['settings']['display_product_variations'] && ($generalSettings['settings']['display_product_variations'] == '1') ? true : false,
				'all_products_filtering': $generalSettings['settings']['all_products_filtering'] && ($generalSettings['settings']['all_products_filtering'] == '1') ? true : false,
				'do_not_use_shortcut': $generalSettings['settings']['do_not_use_shortcut'] && ($generalSettings['settings']['do_not_use_shortcut'] == '1') ? true : false,
				'use_category_filtration': $generalSettings['settings']['use_category_filtration'] ? $generalSettings['settings']['use_category_filtration'] : 1,
				'product_list_selector': $generalSettings['settings']['product_list_selector'] ? $generalSettings['settings']['product_list_selector'] : '',
				'product_container_selector': $generalSettings['settings']['product_container_selector'] ? $generalSettings['settings']['product_container_selector'] : '',
				'auto_update_filter' : Number($generalSettings['settings']['auto_update_filter']),
				'redirect_only_click' : Number($generalSettings['settings']['redirect_only_click']),
				'display_status_private' : $generalSettings['settings']['display_status_private'] && ($generalSettings['settings']['display_status_private'] == '1') ? true : false,
				'open_one_by_one': $generalSettings['settings']['open_one_by_one'] ? $generalSettings['settings']['open_one_by_one'] : '',
				'obo_only_children': $generalSettings['settings']['obo_only_children'] ? $generalSettings['settings']['obo_only_children'] : '',
				'display_only_children_category': $generalSettings['settings']['display_only_children_category'] ? $generalSettings['settings']['display_only_children_category'] : '',
			};
			var $withPerPage = $filterWrapper.find('select.wpfPerPageDD'),
				perPageCount = $withPerPage.length ? $withPerPage.find('option:selected') : '';
			if (perPageCount.length) {
				$filterSettings['count_product_shop'] = perPageCount.val();
			}

			$filterSettings['sort_by_title'] = ($generalSettings['settings']['sort_by_title'] != undefined && $generalSettings['settings']['sort_by_title'] == '1' ? true : false);
			if (typeof $defQuery !== 'undefined' && $defQuery.length) $filterSettings['default_query'] = JSON.parse($defQuery);

			// find woocommerce product loop type ( shorcode, loop )
			var productContainerSelector = _thisObj.fixSelector($filterSettings['product_container_selector'], ''),
				productContainerElem = (productContainerSelector !== '')
					? jQuery(productContainerSelector)
					: jQuery(document);

			$queryVars = JSON.parse($queryVars);
			var shortcode = jQuery('span[data-shortcode-attribute]', productContainerElem),
				$shortcodeAttr = {};

			if (shortcode.length) {
				$shortcodeAttr = shortcode.data('shortcode-attribute');

				if ($shortcodeAttr['limit'] > 0) {
					$queryVars['posts_per_page'] = $shortcodeAttr['limit'];
				}

				$queryVars['wc_loop_type'] = 'shortcode';
				$queryVars['paginate_type'] = 'shortcode';
				$queryVars['paginate_base'] = 'product-page';
			} else {
				$queryVars['wc_loop_type'] = 'loop';
			}

			// for plugin WooCommerce Products Per Page
			var $wppp = jQuery('.wppp-select').first();
			if ($wppp.length) {
				$queryVars['posts_per_page'] = $wppp.val();
			}

			$queryVars = JSON.stringify($queryVars);
			$shortcodeAttr = JSON.stringify($shortcodeAttr);

			if (clearAll) {
				var newUrl = getCurrentUrlPartsWpf();
				if (newUrl.search.length && newUrl.search.indexOf('wpf_')) clearAll = false;
			}

			if ($filterSettings['count_product_shop'] > 0 && !clearAll) {
				_thisObj.QStringWork('wpf_count', $filterSettings['count_product_shop'], noWooPage, $filterWrapper, 'change');
			}
			if ($filterSettings['sort_by_title']) {
				_thisObj.QStringWork('wpf_order', 'title', noWooPage, $filterWrapper, 'change');
			}
			if ($filterSettings['filtering_by_variations'] && !clearAll) {
				_thisObj.QStringWork('wpf_fbv', 1, noWooPage, $filterWrapper, 'change');
			}
			if ($filterSettings['exclude_backorder_variations'] && !clearAll) {
				_thisObj.QStringWork('wpf_ebv', 1, noWooPage, $filterWrapper, 'change');
			}
			if ($filterSettings['display_product_variations'] && !clearAll) {
				_thisObj.QStringWork('wpf_dpv', 1, noWooPage, $filterWrapper, 'change');
			}
			if ($filterSettings['all_products_filtering'] && !clearAll) {
				_thisObj.QStringWork('all_products_filtering', '1', noWooPage, $filterWrapper, 'change');
			}
			if ($filtersDataBackend.length === 0) {
				_thisObj.QStringWork('all_products_filtering', '', noWooPage, $filterWrapper, 'remove');
			}
			if ($filterSettings['f_multi_logic'] !== 'and' && !clearAll) {
				_thisObj.QStringWork('wpf_filter_tax_block_logic', $filterSettings['f_multi_logic'], noWooPage, $filterWrapper, 'change');
			}
			if ($filtersDataBackend.length === 0) {
				_thisObj.QStringWork('wpf_filter_tax_block_logic', '', noWooPage, $filterWrapper, 'remove');
			}
			// we always start from first page after filtering
			//if ($queryVarsSettings['paginate_type'] == 'query' || $queryVarsSettings['paginate_type'] == 'shortcode') {
			_thisObj.QStringWork($queryVarsSettings['paginate_base'], '', noWooPage, $filterWrapper, 'remove');
			_thisObj.QStringWork('product-page', '', noWooPage, $filterWrapper, 'remove');
			_thisObj.QStringWork('shopPage', '', noWooPage, $filterWrapper, 'remove');
			_thisObj.QStringWork('avia-element-paging', '', noWooPage, $filterWrapper, 'remove');

			var curUrl = getCurrentUrlPartsWpf();
            if (curUrl.search.length) {
                // Remove any URL parameters that start with 'e-page-'
                var ePageMatch = curUrl.search.match(/[?&](e-page-[^=&]+)=/);
                if (ePageMatch && ePageMatch[1]) {
                    _thisObj.QStringWork(ePageMatch[1], '', noWooPage, $filterWrapper, 'remove');
                }

				var $pages = curUrl.search.match(/query-\d+-page/i);
				if ($pages != null && $pages.length) _thisObj.QStringWork($pages[0], '', noWooPage, $filterWrapper, 'remove');
			}

			var $woocommerceSettings = {};
			if (jQuery('.wpfFilterWrapper[data-filter-type="wpfSortBy"]').length == 0) {
				var $wooCommerceSort = jQuery('.woocommerce-ordering select');
				if ($wooCommerceSort.length > 0) {
					$woocommerceSettings['woocommercefSortBy'] = $wooCommerceSort.eq(0).val();
				}
			}
			if (onlyRecalcFilter) {
				// this block for forced filter refresh without product filtering
				// you can call this with custom js: window.wpfFrontendPage.filtering(jQuery('.wpfMainWrapper[data-filter="1"]'),false,false,true);
				var requestData =_thisObj.getAjaxRequestData($filtersDataBackend, $queryVars, $filterSettings, $generalSettings, $shortcodeAttr, $woocommerceSettings);
				_thisObj.ajaxOnlyRecount(requestData, $filterWrapper.attr('id'));
				return;
			}
			var redirect = (typeof $filterWrapper.data('redirect-page-url') !== 'undefined'),
				$needUrl = $filterSettings['open_one_by_one'] == '1' && $filterSettings['obo_only_children'] == '1';

			if (history.pushState && app.wpfNewUrl != window.wpfOldUrl && ((!redirect && !redirectTerm) || $needUrl))  {
				var newUrl = app.wpfNewUrl.indexOf('pr_search_') > 0 ? app.wpfNewUrl.replace('+', '%2b') : app.wpfNewUrl;
				history.pushState({state: 1, rand: Math.random(), wpf: true}, '', newUrl);
				app.wpfOldUrl = app.wpfNewUrl;
				_thisObj.changeSlugByUrl();
			}

			if (redirectTerm || (redirect && _thisObj.filterClick)) {
				let queryString = app.wpfNewUrl.split('?')[1] || '';
				if (queryString !== '') {
					if (_thisObj.isStatistics) {
						var requestData =_thisObj.getAjaxRequestData($filtersDataBackend, $queryVars, $filterSettings, $generalSettings, $shortcodeAttr, $woocommerceSettings);
						wpfDoActionsAfterLoad(_thisObj.filteringId, -1, requestData);
					}
					var redLink = (redirectTerm ? redirectLink : $filterWrapper.data('redirect-page-url'));
					jQuery(location).attr('href', redLink + (redLink.indexOf('?') == -1 ? '?' : '&') + queryString + '&redirect');
				}
			} else {
				if ($generalSettings && $generalSettings.settings.enable_ajax !== '1') {
					if (_thisObj.isStatistics) {
						var requestData =_thisObj.getAjaxRequestData($filtersDataBackend, $queryVars, $filterSettings, $generalSettings, $shortcodeAttr, $woocommerceSettings);
						wpfDoActionsAfterLoad(_thisObj.filteringId, -1, requestData);
					}
					if (_thisObj.isSafari || navigator.userAgent.match(/firefox|fxios/i)) location.reload(true);
					else location.reload();
					return;
				}

				_thisObj.currentFilterBackend = $filtersDataBackend;
				// event for custom javascript hook
				var customEvent = document.createEvent('Event');
				customEvent.initEvent('wpfAjaxStart', false, true);
				document.dispatchEvent(customEvent);
				//ajax call to server
				_thisObj.currentLoadId = $filterWrapper.attr('id');

				var $wtbpFilterWrapper = $filterWrapper.closest('.wpfFilterForWtbp');
				if ($wtbpFilterWrapper.length == 0) {
					if ($filterWrapper.closest('.wpfFilterForWtbpFloating').length) {
						$wtbpFilterWrapper = $('.wpfFilterForWtbp[data-wpf-id="'+$filterWrapper.attr('data-filter')+'"]');
					}
				}
				if ($wtbpFilterWrapper.length == 1) {
					var wtbpTableId = $wtbpFilterWrapper.closest('.wtbpTableWrapper').attr('data-table-id'),
						wtbpTable = wtbpTableId ? window.woobewoo.WooTablepress.getTableInstanceById(wtbpTableId) : false;
					if (wtbpTable && wtbpTable.isSSP) {
						wtbpTable.ajax.reload();
						return;
					}
				}
				_thisObj.sendFiltersOptionsByAjax(_thisObj.currentFilterBackend, $queryVars, $filterSettings, $generalSettings, $woocommerceSettings, $shortcodeAttr);
				if (_thisObj.isSynchro) {
					jQuery('.wpfMainWrapper:not(#'+_thisObj.currentLoadId+')').each(function(){
						var $synchroWrapper = jQuery(this);

						$generalSettings = _thisObj.getFilterMainSettings($synchroWrapper);
						var requestData =_thisObj.getAjaxRequestData($filtersDataBackend, $queryVars, $filterSettings, $generalSettings, $shortcodeAttr, $woocommerceSettings);

						_thisObj.ajaxOnlyRecount(requestData, $synchroWrapper.attr('id'));

					});
				}
			}
		}
	});

	WpfFrontendPage.prototype.getAjaxRequestData = (function ($filtersDataBackend, $queryVars, $filterSettings, $generalSettings, $shortcodeAttr, $woocommerceSettings) {
		$generalSettings = $generalSettings ? $generalSettings['settings']['filters']['order'] : [];
		var typeSettings = typeof $generalSettings;
		if (typeSettings == 'undefined') $generalSettings = '[]';
		else if (typeSettings != 'string') $generalSettings = JSON.stringify($generalSettings);

		return {
			mod: 'woofilters',
			action: 'filtersFrontend',
			filtersDataBackend: JSON.stringify($filtersDataBackend),
			queryvars: $queryVars,
			filterSettings: JSON.stringify($filterSettings),
			generalSettings: $generalSettings,
			shortcodeAttr: $shortcodeAttr,
			woocommerceSettings: JSON.stringify($woocommerceSettings),
			currenturl: window.location.href,
		};
	});

	WpfFrontendPage.prototype.createOverlay = (function (filterId) {
		var selector = '#wpfOverlay';
		if (typeof filterId != 'undefined') selector += '[data-filter-for="'+filterId+'"]';
		jQuery(selector).css({'display':'block'});
	});

	WpfFrontendPage.prototype.removeOverlay = (function () {
		jQuery('#wpfOverlay').css({'display':'none'});
		jQuery('#wpfOverlay[data-filter-for]').css({'display':'none'});
	});

	WpfFrontendPage.prototype.syncronizeFilters = (function ($filterWrapper) {
		var _thisObj = this.$obj,
			filterId = $filterWrapper.attr('id');

		$filterWrapper.find('.wpfFilterWrapper').each(function () {
			var $filter = jQuery(this),
				filterType = $filter.attr('data-filter-type'),
				filterDisplay = $filter.attr('data-display-type'),
				filterSlug = $filter.attr('data-slug'),
				filterTaxonomy = $filter.attr('data-taxonomy'),
				selector = '.wpfMainWrapper:not(#'+filterId+') .wpfFilterWrapper[data-filter-type="'+filterType+'"]';
			if (filterDisplay) selector += '[data-display-type="'+filterDisplay+'"]';
			if (filterSlug) selector += '[data-slug="'+filterSlug+'"]';
			if (filterTaxonomy) selector += '[data-taxonomy="'+filterTaxonomy+'"]';
			var $synchroFilters = jQuery(selector);
			if ($synchroFilters.length) {
				$synchroFilters.find('input').prop('checked', false).trigger('wpf-synchro');
				$filter.find('input:checked').each(function(){
					var $li = jQuery(this).closest('[data-term-id]');
					if ($li) {
						var $synchroInput = $synchroFilters.find('[data-term-id="'+$li.attr('data-term-id')+'"] input');
						if ($synchroInput.length == 0 && filterDisplay == 'colors') $synchroInput = $synchroFilters.find('input[data-term-id="'+$li.attr('data-term-id')+'"]');
						if ($synchroInput.length) $synchroInput.prop('checked', true).trigger('wpf-synchro');
					}
				});
				if (filterType === 'wpfPrice'){
					var min = $filter.find('#wpfMinPrice').val(),
						max = $filter.find('#wpfMaxPrice').val();
					$synchroFilters.each(function() {
						var $slider = jQuery(this);
						$slider.find('#wpfMinPrice').val(min);
						$slider.find('#wpfMaxPrice').val(max);
						//$slider.find('#wpfSliderRange').slider("option", "values", [ min, max ]);
					});
				} else if (filterDisplay == 'mul_dropdown') {
					$synchroFilters.find('select').val($filter.find('select').val());
					$synchroFilters.find('select.jqmsLoaded').multiselect('reload');
				} else if(filterDisplay == 'text') {
					$synchroFilters.find('input').val($filter.find('input').val());
				} else {
					var value = $filter.find('select').val();
					$synchroFilters.find('select').each(function() {
						if (jQuery(this).find('option[value="'+value+'"]').length) jQuery(this).val(value);
					});
				}

				if(typeof(_thisObj.syncronizeFiltersPro) == 'function') {
					_thisObj.syncronizeFiltersPro($filter, $synchroFilters);
				}

				if ($filter.hasClass('wpfNotActive')) $synchroFilters.addClass('wpfNotActive');
				else $synchroFilters.removeClass('wpfNotActive');
			}



		});

		//_thisObj.markCheckboxSelected(filter);
	});

	WpfFrontendPage.prototype.clearFilters = (function (filter, clearAll) {
		var _thisObj = this.$obj,
			noWooPage = _thisObj.noWoo,
			clearAll = typeof clearAll == 'undefined' ? false : true;

		(filter ? filter : jQuery('.wpfFilterWrapper')).each(function () {
			var $filter = jQuery(this),
				$filterWrapper = $filter.closest('.wpfMainWrapper'),
				filterAttribute = $filter.attr('data-get-attribute'),
				filterType = $filter.attr('data-display-type'),
				defValue = $filter.attr('data-filter-default');
			if (typeof defValue === 'undefined') {
				defValue = '';
			}

			filterAttribute = filterAttribute.split(",");
			var count = filterAttribute.length;
			for(var i = 0; i < count; i++){
				_thisObj.QStringWork(filterAttribute[i], '', noWooPage, $filterWrapper, 'remove');
			}

			if($filter.hasClass('wpfHidden')){
				$filter.removeClass('wpfNotActive');
			} else {
				$filter.find('input').prop('checked', false);

				if(filterType == 'mul_dropdown') {
					$filter.find('select').val('');
					$filter.find('select.jqmsLoaded').multiselect('reload');
				} else if(filterType == 'text') {
					$filter.find('input').val('');
				} else {
					$filter.find("select").val($filter.find("select option:first").val());
				}
				if (defValue.length && clearAll) {
					if(filterType == 'dropdown') {
						$filter.find('select').val($filter.find('select option[data-term-slug="'+defValue+'"]').val());
					} else {
						$filter.find('li[data-term-slug="'+defValue+'"] input').prop('checked', true);
					}
					$filter.removeClass('wpfNotActive');
				} else {
					$filter.addClass('wpfNotActive');
				}
			}

			if($filter.attr('data-filter-type') === 'wpfPrice'){
				var min = $filter.find('#wpfMinPrice').attr('min'),
					max = $filter.find('#wpfMaxPrice').attr('max');
				$filter.find('#wpfMinPrice').val(min);
				$filter.find('#wpfMaxPrice').val(max);

				jQuery( "#wpfSliderRange" ).slider( "option", "values", [ min, max ] );
			}

			if(typeof(_thisObj.clearFiltersPro) == 'function') {
				_thisObj.clearFiltersPro($filter);
			}
			if(typeof(_thisObj.eventChangeFilterPro) == 'function') {
				_thisObj.eventChangeFilterPro($filter);
			}
			if(clearAll) {
				_thisObj.QStringWork('wpf_order', '', noWooPage, $filterWrapper, 'remove');
				_thisObj.QStringWork('wpf_count', '', noWooPage, $filterWrapper, 'remove');
				_thisObj.QStringWork('all_products_filtering', '', noWooPage, $filterWrapper, 'remove');
				_thisObj.QStringWork('wpf_oistock', '', noWooPage, $filterWrapper, 'remove');
				_thisObj.QStringWork('wpf_fbv', '', noWooPage, $filterWrapper, 'remove');
				_thisObj.QStringWork('wpf_dpv', '', noWooPage, $filterWrapper, 'remove');
				_thisObj.QStringWork('wpf_ebv', '', noWooPage, $filterWrapper, 'remove');
			}
		});

		_thisObj.markCheckboxSelected(filter);
	});

	WpfFrontendPage.prototype.getFilterMainSettings = (function ($selector) {
		var settingsStr = $selector.attr('data-filter-settings');
		try{
			var settings = JSON.parse(settingsStr);
		}catch(e){
			var settings = false;
		}
		if (settings === false) {
			settingsStr = settingsStr.replace('}]"', '}]').replace('"[{', '[{');
			try{
				settings = JSON.parse(settingsStr);
			}catch(e){
				settings = false;
			}
		}
		return settings;
	});

	WpfFrontendPage.prototype.getFilterParam = (function (paramSlug, mainWrapper, filterWrapper) {
		var paramValue = null,
			_thisObj = this.$obj,
			mainSettings = _thisObj.getFilterMainSettings(mainWrapper),
			orderKey = filterWrapper.attr('data-order-key');
		if (mainSettings.settings.filters.order && typeof orderKey !== undefined) {
			var filtersOderList = JSON.parse(mainSettings.settings.filters.order);
			if ( typeof filtersOderList[orderKey] !== undefined ) {
				var filterParamList = filtersOderList[orderKey].settings;
				if ( typeof filterParamList[paramSlug] !== undefined ) {
					paramValue = filterParamList[paramSlug];
				}
			}
		}
		return paramValue;
	});

	WpfFrontendPage.prototype.checkNoWooPage = (function () {
		var noWooPage = false;
		if(jQuery('.wpfMainWrapper').first().attr('data-nowoo')){
			noWooPage = true;
		}
		return noWooPage;
	});

	WpfFrontendPage.prototype.changeLmpButton = (function () {
		var lmpBtn = jQuery('.br_lmp_button_settings .lmp_button');
		if (lmpBtn.length) {
			var parentStyle = lmpBtn.parent().attr('style').replace(' ', '');
			if (parentStyle.indexOf('display:none') > -1) {
				return;
			}
			var url = lmpBtn.attr('href').split('?')[0];
			url += window.location.search;
			url = url.indexOf('/page/2') > -1 ? url : url.replace(/\/page\/[0-9]{1,}/ig, '/page/2');
			lmpBtn.attr('href', url);
			setTimeout(function(){
				jQuery('.woocommerce-pagination').addClass('wpfHidden');
			}, 1000);
		}
	});


	WpfFrontendPage.prototype.changeUrlByFilterParams = (function ($filtersDataFrontend) {
		var _thisObj = this.$obj,
			noWooPage = _thisObj.noWoo;
		if (typeof $filtersDataFrontend !== 'undefined' && $filtersDataFrontend.length > 0) {
			// the array is defined and has at least one element
			var count = $filtersDataFrontend.length,
				filterWrapper = jQuery('.wpfMainWrapper'),
				priceFlag = true;
			for(var i = 0; i < count; i++){
				switch ($filtersDataFrontend[i]['id']){
					case 'wpfPrice':
					case 'wpfPriceRange':
						if (priceFlag) {
							var minPrice = $filtersDataFrontend[i]['settings']['wpf_min_price'],
								maxPrice = $filtersDataFrontend[i]['settings']['wpf_max_price'],
								tax = $filtersDataFrontend[i]['settings']['tax'];

							if (typeof minPrice !== 'undefined' && minPrice.length > 0) {
								_thisObj.QStringWork('wpf_min_price', minPrice, noWooPage, filterWrapper, 'change');
							} else {
								_thisObj.QStringWork('wpf_min_price', '', noWooPage, filterWrapper, 'remove');
							}
							if (typeof maxPrice !== 'undefined' && maxPrice.length > 0) {
								_thisObj.QStringWork('wpf_max_price', maxPrice, noWooPage, filterWrapper, 'change');
							} else {
								_thisObj.QStringWork('wpf_max_price', '', noWooPage, filterWrapper, 'remove');
							}
							if (typeof tax !== 'undefined') {
								_thisObj.QStringWork('tax', tax, noWooPage, filterWrapper, 'change');
							} else {
								_thisObj.QStringWork('tax', '', noWooPage, filterWrapper, 'remove');
							}
							priceFlag = false;
						}
						break;
					case 'wpfSortBy':
						var orderby = $filtersDataFrontend[i]['settings']['orderby'],
							oistock = $filtersDataFrontend[i]['settings']['oistock'];
						if (typeof orderby !== 'undefined' && orderby.length > 0 ) {
							_thisObj.QStringWork('orderby', orderby, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork('orderby', '', noWooPage, filterWrapper, 'remove');
						}
						if (typeof oistock !== 'undefined' && oistock == 1 ) {
							_thisObj.QStringWork('wpf_oistock', 1, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork('wpf_oistock', '', noWooPage, filterWrapper, 'remove');
						}
						break;
					case 'wpfCategory':
					case 'wpfPerfectBrand':
						var product_cat = $filtersDataFrontend[i]['settings']['settings'],
							name = $filtersDataFrontend[i]['name'],
							delim = $filtersDataFrontend[i]['delim'];
						product_cat = product_cat.join(delim ? delim : '|');
						if (typeof product_cat !== 'undefined' && product_cat.length > 0) {
							_thisObj.QStringWork(name, product_cat, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork(name, '', noWooPage, filterWrapper, 'remove');
						}
						break;
					case 'wpfTags':
						var product_tag = $filtersDataFrontend[i]['settings']['settings'],
							name = $filtersDataFrontend[i]['name'],
							delim = $filtersDataFrontend[i]['delim'];
						product_tag = product_tag.join(delim ? delim : '|');
						if (typeof product_tag !== 'undefined' && product_tag.length > 0) {
							_thisObj.QStringWork(name, product_tag, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork(name, '', noWooPage, filterWrapper, 'remove');
						}
						break;
					case 'wpfAttribute':
						var product_taxonomy = $filtersDataFrontend[i]['settings']['taxonomy'],
							product_attr = $filtersDataFrontend[i]['settings']['settings'],
							delim = $filtersDataFrontend[i]['delim'];
						product_attr = product_attr.join(delim ? delim : '|');
						if (typeof product_attr !== 'undefined' && product_attr.length > 0) {
							_thisObj.QStringWork(product_taxonomy, product_attr, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork(product_taxonomy, '', noWooPage, filterWrapper, 'remove');
						}

						if (typeof $filtersDataFrontend[i]['idBlock']!== 'undefined' && typeof $filtersDataFrontend[i]['idFilter']!== 'undefined' ) {
							_thisObj.QStringWork('group' + product_taxonomy.replace('filter', '') + $filtersDataFrontend[i]['idBlock'], $filtersDataFrontend[i]['idFilter'], noWooPage, filterWrapper, 'change');
						}
						break;
					case 'wpfAuthor':
						var authorVal = $filtersDataFrontend[i]['settings']['settings'],
							name = $filtersDataFrontend[i]['name'],
							delim = $filtersDataFrontend[i]['delim'];
							authorVal = authorVal.join(delim ? delim : '|');
						if (typeof authorVal !== 'undefined' && authorVal.length > 0) {
							_thisObj.QStringWork('pr_author', authorVal, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork('pr_author', '', noWooPage, filterWrapper, 'remove');
						}
						break;
					case 'wpfFeatured':
						var featureVal = $filtersDataFrontend[i]['settings']['settings'];
						if (typeof featureVal !== 'undefined' && featureVal.length > 0) {
							_thisObj.QStringWork('pr_featured', featureVal, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork('pr_featured', '', noWooPage, filterWrapper, 'remove');
						}
						break;
					case 'wpfOnSale':
						var onSaleVal = $filtersDataFrontend[i]['settings']['settings'];
						if (typeof onSaleVal !== 'undefined' && onSaleVal.length > 0) {
							_thisObj.QStringWork('pr_onsale', onSaleVal, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork('pr_onsale', '', noWooPage, filterWrapper, 'remove');
						}
						break;
					case 'wpfInStock':
						var pr_stock = $filtersDataFrontend[i]['settings']['settings'];
						pr_stock = pr_stock.join(delim ? delim : '|');
						if (typeof pr_stock !== 'undefined' && pr_stock.length > 0 ) {
							_thisObj.QStringWork('pr_stock', pr_stock, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork('pr_stock', '', noWooPage, filterWrapper, 'remove');
						}
						break;
					case 'wpfRating':
						var ratingVal = $filtersDataFrontend[i]['settings']['settings'];
						if (typeof ratingVal !== 'undefined' && checkArray(ratingVal) && ratingVal.length > 0 ) {
							_thisObj.QStringWork('pr_rating', ratingVal, noWooPage, filterWrapper, 'change');
						}else{
							_thisObj.QStringWork('pr_rating', '', noWooPage, filterWrapper, 'remove');
						}
						break;
					default:
						if(typeof(_thisObj.changeUrlByFilterParamsPro) == 'function') {
							_thisObj.changeUrlByFilterParamsPro($filtersDataFrontend[i], noWooPage, filterWrapper);
						}
						break;
				}
			}
		}else{
			return false;
		}

	});

	/**
	 * changeSlugByUrl.
	 *
	 * @version 2.8.6
	 */
	WpfFrontendPage.prototype.changeSlugByUrl = (function () {
		jQuery('.wpfSlugWrapper .wpfSlug').remove();
		var _thisObj = this.$obj,
			noWooPage = _thisObj.noWoo,
			searchParams = jQuery.toQueryParams(window.location.search);
		if (noWooPage) {
			if( jQuery('.wpfMainWrapper').first().attr('data-hide-url')) {
				searchParams =  jQuery.toQueryParams(jQuery('.wpfMainWrapper').first().attr('data-hide-url'));
			}
		}

		var isRedirect = 'redirect' in searchParams && searchParams['redirect'] == 1;

		for (var key in searchParams) {
			if(key === 'wpf_min_price'){
				key = 'wpf_min_price,wpf_max_price,tax';
			}
			var $elem = jQuery('.wpfFilterWrapper[data-get-attribute="'+key+'"]');
			if (!$elem.length && isRedirect) {
				var parts = key.split('_'),
					cnt = parts.length;
				if (cnt > 2 && isNumber(parts[cnt-1])) {
					parts.pop();
					$elem = jQuery('.wpfFilterWrapper[data-get-attribute^="'+parts.join('_')+'"]');
					_thisObj.QStringWork(key, '', false, $elem.closest('.wpfMainWrapper'), 'remove');

				}
			}
			if($elem.length > 0) {
				var elem = $elem.first(),
					$slug = elem.attr('data-slug'),
					$label = elem.attr('data-label'),
					$title = elem.attr('data-title'),
					$getAttr = elem.attr('data-get-attribute'),
					$filterType = elem.attr('data-filter-type');
				if(typeof $title != 'undefined') $label = $title;
				else if(typeof $label == 'undefined') $label = $slug;

				var html = '';
				if(jQuery('.wpfSlugWrapper').length > 0){
					if( !jQuery('.wpfSlugWrapper .wpfSlug[data-slug="'+$slug+'"]').length > 0 ) {
						html += '<div class="wpfSlug" data-slug="'+$slug+'" data-get-attribute="'+$getAttr+'" data-filter-type="'+$filterType+'"><div class="wpfSlugTitle">'+$label+'</div><div class="wpfSlugDelete">x</div></div>';
						jQuery('.wpfSlugWrapper').append(html);
					}
				}else{
					if( !jQuery('.wpfSlugWrapper .wpfSlug[data-slug="'+$slug+'"]').length > 0 ) {
						html += '<div class="wpfSlugWrapper">';
						html += '<div class="wpfSlug" data-slug="'+$slug+'" data-get-attribute="'+$getAttr+'" data-filter-type="'+$filterType+'"><div class="wpfSlugTitle">'+$label+'</div><div class="wpfSlugDelete">x</div></div>';
						html += '</div>';
						jQuery('.storefront-sorting').append(html);
					}
				}
			}
			if (isRedirect && history.pushState && app.wpfNewUrl != window.wpfOldUrl)  {
				history.pushState({state: 1, rand: Math.random(), wpf: true}, '', app.wpfNewUrl);
				app.wpfOldUrl = app.wpfNewUrl;
			}
		}

	});

	WpfFrontendPage.prototype.sendFiltersOptionsByAjax = (function ($filtersDataBackend, $queryVars, $filterSettings, $generalSettings, $woocommerceSettings, $shortcodeAttr) {
		var _thisObj = this.$obj,
			$wrapperSettings = [];
		if ( window.wpfAdminPage ) {
			return false;
		}
		_thisObj.currentAjaxJSLoaded = false;
		_thisObj.currentProductBlock = false;

		if (_thisObj.filterLoadTypes[_thisObj.currentLoadId] && _thisObj.filterLoadTypes[_thisObj.currentLoadId] == 'reload') {
			location.reload();
			return;
		}
		var ajax_leave_products = $generalSettings && $generalSettings['settings'] && $generalSettings['settings']['ajax_leave_products'] == '1'

		if (typeof $generalSettings !== 'undefined') {
			$wrapperSettings = $generalSettings['settings'];
		}

		var customListSelector = $filterSettings['product_list_selector'],
			productListSelector = _thisObj.fixSelector(customListSelector, _thisObj.defaultProductSelector),
			productContainerSelector = _thisObj.fixSelector($filterSettings['product_container_selector'], ''),
			forceThemeTemplates = ($wrapperSettings['force_theme_templates'] == 1);

		var productContainerElem = (productContainerSelector !== '')
			? jQuery(productContainerSelector)
			: jQuery(document);

		if (productContainerElem.length === 0) {
			location.reload();
			return;
		}

		var productListElem = jQuery(productListSelector, productContainerElem);
		if (productListElem.hasClass('wpfNoWooPage') || productListElem.closest('.wpfNoWooPage').length) {
			forceThemeTemplates = false;
		}

		if (productListElem.length && (_thisObj.filterClick)) {
			if (_thisObj.enableFiltersLoaderPro && ajax_leave_products) {
				_thisObj.enableFiltersLoaderPro(_thisObj.currentLoadId, productListElem);
			} else {
				_thisObj.enableFiltersLoader(_thisObj.currentLoadId, productListElem);
			}
		}

		var onlyRecount = false;
		if ($filterSettings === undefined) {
			$filterSettings = [];
		} else {
			if (!_thisObj.filterClick && $filterSettings.auto_update_filter && !$filterSettings.redirect_only_click) {
				onlyRecount = true;
			}
		}

		var requestData =_thisObj.getAjaxRequestData($filtersDataBackend, $queryVars, $filterSettings, $generalSettings, $shortcodeAttr, $woocommerceSettings);

		if (onlyRecount) {
			_thisObj.ajaxOnlyRecount(requestData, _thisObj.currentLoadId, $wrapperSettings);
			return;
		} else if (forceThemeTemplates || _thisObj.filterLoadTypes[_thisObj.currentLoadId] == 'force') {
			_thisObj.ajaxForceThemeTemplates(productContainerSelector, productListSelector, requestData, $wrapperSettings);
			return;
		}

		/**
		 * There are some cases when we do not provide ajax functionlity
		 */
		if ( window.InfiniteScroll && window.InfiniteScroll.prototype ) {
			/**
			 * Flatsome theme compability
			 * We do not privide ajax functionlity with infinite scroll
			 *
			 * @link https://themeforest.net/item/flatsome-multipurpose-responsive-woocommerce-theme/5484319
			 */
			if (_thisObj.filterClick && jQuery('body').hasClass('theme-flatsome')) {
				location.reload();
				return;
			}
		}
		_thisObj.filterLoadTypes[_thisObj.currentLoadId] = 'ajax';

		jQuery.sendFormWpf({
			data: requestData,
			onSuccess: function(res) {
                if (!res.error) {
					if ('fid' in res.data && toeInArray(res.data['fid'], _thisObj.lastFids) == -1) {
						return false;
					}
					if ('optionsHtml' in res.data) {
                        var optionsHtml = res.data['optionsHtml'];

                        jQuery('.wpfMainWrapper .wpfFilterWrapper[data-order-key]').each(function() {
                            var $filter = jQuery(this),
                                orderKey = $filter.attr('data-order-key'),
                                $select = $filter.find('select');

                            if (typeof optionsHtml[orderKey] !== 'undefined' && $select.length){
                                var selectedValues = [];
                                $select.find('option:selected').each(function() {
                                    selectedValues.push(jQuery(this).val());
                                });

                                $select.html(optionsHtml[orderKey]);

                                if (selectedValues.length) {
                                    $select.val(selectedValues);
                                }

                            }
                        });
                    }
					if ('jscript' in res.data) {
						_thisObj.setAjaxJScript(res.data['jscript']);
					}
					if (_thisObj.filterClick) {
						if (customListSelector !== '' && productListElem.length) {
							var loopContainer = productListElem;
							loopContainer.html(res.data['productHtml']);
						} else {
							var catSelector = wpfGetSelector(res.data['categoryHtml'], true, _thisObj.defaultProductSelector, 1, false, productContainerElem),
								loopSelector = wpfGetSelector(res.data['loopStartHtml'], true, _thisObj.defaultProductSelector, 3, false, productContainerElem),
								loopContainer = jQuery(loopSelector, productContainerElem);

							if (!loopContainer.length) {
								// trying to obtain loop container without dynamically change classes with numbers in it names
								loopSelector = wpfGetSelector(res.data['loopStartHtml'], true, _thisObj.defaultProductSelector, 3, true, productContainerElem);
								loopContainer = jQuery(loopSelector, productContainerElem);
							}

							loopContainer.prev('.brand-description').remove();
							if (typeof res.data['beforeProductHtml'] !== 'undefined') {
								loopContainer.before('<div class="brand-description">' + res.data['beforeProductHtml'] + '</div>');
							}
							if (typeof jQuery('.product-categories-wrapper', productContainerElem) !== 'undefined' && jQuery('.product-categories-wrapper > ul.products', productContainerElem).length > 0) {
								//replace cats html
								if (res.data['categoryHtml'].length) {
									jQuery('.product-categories-wrapper > ul.products', productContainerElem).html(res.data['categoryHtml']);
								}
								//replace products html
								jQuery(_thisObj.defaultProductSelector, productContainerElem).eq(1).html(res.data['productHtml']);
							} else if (jQuery('.elementor-widget-container ul.products', productContainerElem).length > 0) {
								jQuery('.elementor-widget-container ul.products', productContainerElem).each(function () {
									if (!jQuery(this).find('.product-category').length) {
										jQuery(this).html(res.data['categoryHtml'] + res.data['productHtml']);
									}
								});
							} else if (jQuery('.woocommerce > .products[data-filterargs][data-innerargs]', productContainerElem).length > 0) {
								jQuery('.woocommerce > .products[data-filterargs][data-innerargs]', productContainerElem).html(res.data['categoryHtml'] + res.data['productHtml']);
							} else {
								if (!loopContainer.length) {
									if (!jQuery('.wpfMainWrapper', productContainerElem).attr('data-nowoo') !== typeof undefined
										&& !jQuery('.wpfMainWrapper', productContainerElem).attr('data-nowoo') !== false)
										_thisObj.ajaxForceThemeTemplates(productContainerSelector, productListSelector, requestData, $wrapperSettings);
									else {
										location.reload(true);
									}
									_thisObj.afterAjaxFiltering($wrapperSettings);
									return false;
								}

								loopContainer.each(function () {
									//replace cats html
									jQuery(this).html(res.data['categoryHtml']);
									//replace products html
									jQuery(this).append(res.data['productHtml']);
								});
							}
						}

						var countSelector = wpfGetSelector(res.data['resultCountHtml'], true, '.woocommerce-result-count', 1, false, productContainerElem),
							wooCount = jQuery(countSelector, productContainerElem);
						if (wooCount.length > 0) {
							wooCount.replaceWith(res.data['resultCountHtml']);
						}

						var isLeerPagination = res.data['paginationHtml'] == '',
							paginationSelector = wpfGetSelector(res.data[isLeerPagination ? 'paginationLeerHtml' : 'paginationHtml'], false, '.woocommerce-pagination', 1, false, productContainerElem),
							wooPagination = jQuery(paginationSelector, productContainerElem),
							newPagination = res.data['paginationHtml'];

						if (wooPagination.length > 0) {
							if (typeof _thisObj.paginationClasses == 'undefined') {
								_thisObj.paginationClasses = wooPagination.attr('class');
							}
							if (isLeerPagination) {
								wooPagination.css({'display': 'none'});
								wooPagination.empty();
							} else {
								wooPagination.replaceWith(newPagination);
							}
						} else if (!isLeerPagination) {
							var afterLoop = jQuery('.after-shop-loop', productContainerElem);
							if (afterLoop.length > 0) {
								afterLoop.prepend(newPagination);
							} else {
								wooCount = jQuery('.storefront-sorting ' + countSelector, productContainerElem);
								if (wooCount.length > 0) {
									wooCount.after(newPagination);
								} else {
									let shortcodeData = productContainerElem.find('span[data-shortcode-attribute]'),
										isPaginate = shortcodeData.length ? shortcodeData.data('shortcode-attribute').paginate : true;
									if (typeof isPaginate === 'undefined' || isPaginate) {
										loopContainer.eq(0).after(newPagination);
									}
								}
							}
						}
						if (typeof _thisObj.paginationClasses != 'undefined') {
							jQuery(paginationSelector, productContainerElem).attr('class', _thisObj.paginationClasses);
						}
						_thisObj.currentProductBlock = loopContainer.selector;
						if (!_thisObj.currentProductBlock) {
							loopContainer.addClass('wpfCurrentProductBlock');
							_thisObj.currentProductBlock = '.wpfCurrentProductBlock';
						}
                    }

					_thisObj.afterAjaxFiltering($wrapperSettings);
					jQuery('.wpfLoaderLayout').hide();
				}
			}
		});
	});

	WpfFrontendPage.prototype.ajaxForceThemeTemplates = (function (productContainerSelector, productListSelector, requestData, $wrapperSettings) {
		var _thisObj = this.$obj,
			curUrl = window.location.href,
			isContainer = (productContainerSelector != '');

		_thisObj.filterLoadTypes[_thisObj.currentLoadId] = 'force';

		jQuery.ajax({
			type: "GET",
			url: curUrl + (curUrl.indexOf('?') == -1 ? '?' : '&') + 'wpf_skip=1&wpf_fid=' + _thisObj.filteringId,
			cache: false,
			dataType: 'html',
			success: function(data){
				var block = '',
					foundContainer = false,
					noProducts = false,
					$loadedData = jQuery(data),
					$loadedJS = $loadedData.find('.wpfExistsTermsJS');
				if ($loadedJS.length) {
					if (toeInArray($loadedJS.eq(0).attr('data-fid'), _thisObj.lastFids) == -1) {
						return false;
					}
				}

				if (isContainer) {
					block = jQuery(data).find(productContainerSelector);
				}
				if (block.length) {
					foundContainer = true;
				} else {
					block = jQuery(data).find(productListSelector);
				}
				var pageBlock = jQuery(isContainer && (foundContainer || block.length == 0) ? productContainerSelector : productListSelector);
                if (block.length == 0 || pageBlock.length == 0) {
                    if ($wrapperSettings.recalculate_filters === '1') {
                        var existsTermsJS = jQuery(data).find('.wpfExistsTermsJS').html();
                        _thisObj.setAjaxJScript(existsTermsJS);
                    }
                    if ($wrapperSettings.no_redirect_by_no_products === '1' && pageBlock.length > 0) {
                        block = jQuery('<div><div class="wpfNoProducts">' + $wrapperSettings.text_no_products + '</div></div>');
                        noProducts = true;
                    } else {
                        _thisObj.filterLoadTypes[_thisObj.currentLoadId] = 'reload';
						location.reload();
						return;
                    }
                }
                if ($wrapperSettings.recalculate_filters !== '1') {
                    _thisObj.ajaxOnlyRecount(requestData);
                }

				_thisObj.currentProductBlock = (typeof pageBlock.selector !== 'undefined') ? pageBlock.selector : productListSelector ;

				block.each(function (index, value) {
					var blockWhere = pageBlock.eq(index),
						blockWhat = jQuery(value);

					if (!foundContainer && !noProducts) {
						blockWhere = blockWhere.parent();
						blockWhat = blockWhat.parent();
					}
					// some plugins save url parameters in input fields
					blockWhat.find('input[name="wpf_skip"]').remove();

					blockWhere.html(blockWhat.html().replace(/wpf_skip=1/g, '').replace(/wpf_fid=[0-9]*/g, '').replace(/&amp;&amp;/g, '&amp;'));

					// if pagination is not in the block to be replaced, we will try to find and replace it
					var paginationPageBlock = jQuery(blockWhere).find('.woocommerce-pagination');
					if (!paginationPageBlock.length) {
						var paginationPage = jQuery('.woocommerce-pagination');
						var paginationResponse = jQuery(data).find('.woocommerce-pagination');
						if (paginationResponse.length) {
							paginationResponse = paginationResponse.eq(0).html().replace(/wpf_skip=1/g, '').replace(/wpf_fid=[0-9]*/g, '').replace(/&amp;&amp;/g, '&amp;');
							if (paginationPage.length) {
								paginationPage.html(paginationResponse);
							} else {
								jQuery(blockWhere).parent().after('<nav class="woocommerce-pagination">' + paginationResponse + '</nav>');
							}
						} else if (paginationPage.length) {
							paginationPage.remove();
						}
					}

					// if result count is not in the block to be replaced, we will try to find and replace it
					var resultCountBlock = jQuery(blockWhere).find('.woocommerce-result-count');
					var resultCountResponse = jQuery(data).find('.woocommerce-result-count');
					if (!resultCountBlock.length && resultCountResponse.length) {
						var resultCount = jQuery('.woocommerce-result-count');
						if (resultCount.length) {
							resultCount.replaceWith(resultCountResponse);
						}
					}
				});
				// for Divi archive loop
				var $styleObj = jQuery('style[id^="et-builder-module-design-"]');
				if ($styleObj.length == 1) {
					var $styleObjLoaded = $loadedData.find('style[id^="et-builder-module-design-"]');
					if ($styleObjLoaded.length == 0) $styleObjLoaded = $loadedData.filter('style[id^="et-builder-module-design-"]');
					if ($styleObjLoaded.length == 1) $styleObj.html($styleObjLoaded.html());
				}

				_thisObj.afterAjaxFiltering($wrapperSettings);
				_thisObj.runReadyList();
				if ($wrapperSettings.recalculate_filters === '1') {
					var existsTermsJS = jQuery(data).find('.wpfExistsTermsJS').html();
					_thisObj.setAjaxJScript(existsTermsJS);
                }

    		}
		});

		return false;
	});

	WpfFrontendPage.prototype.ajaxOnlyRecount = (function (requestData, filterId, $wrapperSettings) {
		var _thisObj = this.$obj;

		if (!_thisObj.currentAjaxJSLoaded && requestData) {
			requestData['only_recound'] = 1;

			if (typeof filterId !==  'undefined') {
				requestData['synchro_filter_id'] = filterId;
			}

			jQuery.sendFormWpf({
				data: requestData,
				onSuccess: function (res) {

					if (typeof $wrapperSettings !== 'undefined'
						&& typeof $wrapperSettings.filter_loader_icon_onload_enable !== 'undefined'
						&& Number($wrapperSettings.filter_loader_icon_onload_enable)) {
						hideFilterLoader(jQuery('#' + filterId));
					}

					if (!res.error) {

						if ('jscript' in res.data) {
							_thisObj.setAjaxJScript(res.data['jscript'], filterId);
						}

					}
					_thisObj.removeOverlay();
				}
			});
		}

	});

	WpfFrontendPage.prototype.setAjaxJScript = (function(jscript, filterId){
		var _thisObj = this.$obj,
			filter = jQuery('#' + (typeof(filterId) == 'undefined' ? _thisObj.currentLoadId : filterId));
		if (filter.length && jscript != '') {
			var jsBlock = filter.find('.wpfAjaxJSBlock');
			if (jsBlock.length == 0) {
				jQuery('<div class="wpfAjaxJSBlock wpfHidden"></div>').appendTo(filter);
			}
			filter.find('.wpfAjaxJSBlock').html(jscript);
		}
		_thisObj.currentAjaxJSLoaded = true;
	});
	WpfFrontendPage.prototype.afterAjaxFiltering = (function($wrapperSettings){
		var _thisObj = this.$obj;

		//if changed min/max price and we have wpfPrice filter
		//we need change slider
		_thisObj.getUrlParamsChangeFiltersValues();

		_thisObj.disableFiltersLoader();
		_thisObj.removeOverlay();
		toggleClear();

        // fix for OceanWP theme
		if ( jQuery('body').find('.products').hasClass('oceanwp-row') ) {
			var products = jQuery('body').find('.products'),
				aligns = ['center', 'left', 'right'];
			products.find('li:first').addClass('col');
			for (var i = 1; i <= 7; i++) {
				if (products.find('li').hasClass('span_1_of_' + i)) {
					products.find('li:first').addClass('span_1_of_' + i);
					break;
				}
			}
			for (var j = 0; j < aligns.length; j++) {
    			if (products.find('li').hasClass('owp-content-' + aligns[j])) {
					products.find('li:first').addClass('owp-content-' + aligns[j]);
					break;
				}
			}
		}

		_thisObj.changeLmpButton();

		// for yith quick view
		jQuery(document).trigger('yith_wcqv_wcajaxnav_update');

		if (typeof (_thisObj.scrollToProductsPro) == 'function') {
			_thisObj.scrollToProductsPro($wrapperSettings);
		}

		if (jQuery(_thisObj.defaultProductSelector).closest('.et_pb_shop').length && jQuery(_thisObj.defaultProductSelector).find('[loading="lazy"]').length == 0) {
			heightIdenticalInRow('.et_pb_shop li.product');
		}

		// Improve compatibility with 'Woocommerce Products Per Page'
		jQuery('.form-wppp-select').each( function () {
			var $form = jQuery(this);
			$form.attr('action', jQuery('#'+_thisObj.currentLoadId).attr('data-hide-url'));
			$form.find('input[type="hidden"]').remove();
		});
		// Improve compatibility with Theme Bricks + Bricks Builder
		if (typeof(bricksLazyLoad) == 'function') {
			bricksLazyLoad();
		}

		// event for custom javascript hook, example: document.addEventListener('wpfAjaxSuccess', function(event) {console.log('Custom js');});
		//document.dispatchEvent(new Event('wpfAjaxSuccess')); - not work in IE11
		var customEvent = document.createEvent('Event');
		customEvent.initEvent('wpfAjaxSuccess', false, true);
		document.dispatchEvent(customEvent);
	});

	WpfFrontendPage.prototype.runReadyList = (function(){
		if (window.readyList && readyList.length) {
			var _thisObj = this.$obj;
			if (_thisObj.disableScrollJs) {
				jQuery(window).off("yith_infs_start").off("scroll touchstart");
				if (typeof(jQuery.fn) == 'object' && typeof(jQuery.fn.init_infinitescroll) == 'function') {
					jQuery.fn.init_infinitescroll();
				}
			}
			jQuery(window.readyList).each(function(i, el) {
				var strFunc = el['a'][0].toString();
				if (strFunc.indexOf('WpfFrontendPage') == -1) {
					for(var i = 0; i < _thisObj.readyFuncs.length; i++) {
						if (strFunc.indexOf(_thisObj.readyFuncs[i]) != -1 && (strFunc.indexOf('.ajaxComplete(') == -1 || i == 3 || i == 4)) {
							try {
								window.originalReadyMethod.apply(el['c'], el['a']);
							} catch(e) {
								console.log(e);
							}
							break;
						}
					}
				}
            });
		}
		jQuery(window).trigger("fusion-element-render-fusion_woo_product_grid");
		var fusionPrInfinite = jQuery(".fusion-products-container-infinite");
		if (fusionPrInfinite.length && typeof(fusionPrInfinite.infinitescroll) == 'function') {
			fusionPrInfinite.infinitescroll('unbind');
			fusionPrInfinite.infinitescroll('bind');
		}

        if (typeof elementorFrontend !== 'undefined') {
            jQuery(window).trigger('resize');

            var $eaPagination = jQuery('.elementor-widget-eicon-woocommerce');
            if ($eaPagination.length && window.elementorFrontend && window.elementorFrontend.hooks) {
                window.elementorFrontend.hooks.doAction('frontend/element_ready/eicon-woocommerce.default', $eaPagination, jQuery);
            }
        }

		// AVADA infinitescroll
		if (jQuery('.fusion-grid-container-infinite').length == 1) {
			jQuery(document).trigger('fusion-element-render-fusion_post_cards');
			jQuery('.fusion-grid-container-infinite').infinitescroll('unbind');
			jQuery('.fusion-grid-container-infinite').infinitescroll('bind');
        }
	});

	/*WpfFrontendPage.prototype.runReadyList = (function(){
		if (window.readyList && window.readyList.length) {

			jQuery(window.readyList).each(function(i, el) {
				var strFunc = el['a'][0].toString();
				if (strFunc.indexOf('WpfFrontendPage') == -1 && strFunc.indexOf('.ajaxComplete(') == -1) {
					try {
						window.originalReadyMethod.apply(el['c'], el['a']);
					} catch(e) {
						console.log(e);
					}
				}
			});
		}
	});*/

	WpfFrontendPage.prototype.enableFiltersLoader = (function(idWrapper, productListElem){
		var preview = jQuery('#' + idWrapper + ' .wpfPreviewLoader').first().clone().removeClass('wpfHidden');
		productListElem.html(preview);
	});

	WpfFrontendPage.prototype.disableFiltersLoader =(function(){
		jQuery('.wpfPreviewLoader').first().clone().addClass('wpfHidden');
	});

	//after load change price and price range filters params if find get params
	WpfFrontendPage.prototype.getUrlParamsChangeFiltersValues = (function(){
		var _thisObj = this.$obj,
			noWooPage = _thisObj.noWoo;
		if(noWooPage){
			var curUrl = jQuery('.wpfMainWrapper').first().attr('data-hide-url');
		}else{
			var curUrl = window.location.href;
		}
		if(!curUrl){
			return;
		}
		//get all get params
		var urlParams = _thisObj.findGetParameter(curUrl);
		jQuery('.wpfFilterWrapper').each(function () {
			var $filter = jQuery(this),
				filterType = $filter.attr('data-filter-type'),
				settings = _thisObj.getFilterMainSettings($filter.closest('.wpfMainWrapper'));

			switch(filterType){
				case 'wpfAttribute':
					if(typeof(_thisObj.eventChangeFilterPro) == 'function') {
						_thisObj.eventChangeFilterPro($filter, settings);
					}
					break;
				case 'wpfPrice':
					var rate = $filter.data('rate');
					urlParams = _thisObj.getConvertedPrices(urlParams, rate);
					var minPrice = urlParams.wpf_min_price ? urlParams.wpf_min_price : $filter.attr('data-minvalue'),
						maxPrice = urlParams.wpf_max_price ? urlParams.wpf_max_price : $filter.attr('data-maxvalue'),
						skin = 'default';

					if(minPrice){
						$filter.find('#wpfMinPrice').val(minPrice);
					}
					if(maxPrice){
						$filter.find('#wpfMaxPrice').val(maxPrice);
					}
					if(settings){
						skin = $filter.attr('data-price-skin');
					}
					if(skin === 'default'){
						var sliderWrapper = $filter.find("#wpfSliderRange");
					}else{
						var sliderCurBefore = ($filter.attr('data-slider-currency-before') != undefined) ? $filter.attr('data-slider-currency-before') : '';
						var sliderCurAfter = ($filter.attr('data-slider-currency-after') != undefined) ? $filter.attr('data-slider-currency-after') : '';
						var sliderWrapper = $filter.find('.ion-range-slider').data('ionRangeSlider');
						$filter.addClass('wpfNotActiveSlider');

						if (sliderCurBefore || sliderCurAfter) {
							setTimeout(function()
							{
								$filter.find('span.irs-min:first').html(sliderCurBefore + sliderWrapper['result'].min_pretty + sliderCurAfter);
								$filter.find('span.irs-max:first').html(sliderCurBefore + sliderWrapper['result'].max_pretty + sliderCurAfter);
								$filter.find('span.irs-from:first').html(sliderCurBefore + sliderWrapper['result'].from_pretty + sliderCurAfter);
								$filter.find('span.irs-to:first').html(sliderCurBefore + sliderWrapper['result'].to_pretty + sliderCurAfter);
							}, 500);
						}
					}
					if(minPrice && maxPrice){
						if(skin === 'default'){
							sliderWrapper.slider({
								values: [minPrice, maxPrice]
							});
						}else if(typeof(sliderWrapper) != 'undefined') {
							sliderWrapper.update({from: minPrice, to: maxPrice});
							$filter.removeClass('wpfNotActiveSlider');
						}
					}
					_thisObj.chageRangeFieldWidth();
					if(typeof(_thisObj.eventChangeFilterPro) == 'function') {
						_thisObj.eventChangeFilterPro($filter, settings);
					}
					break;
				case 'wpfPriceRange':
					var rate = $filter.data('rate');
					urlParams = _thisObj.getConvertedPrices(urlParams, rate);
					var minPrice = urlParams.wpf_min_price ? parseFloat(urlParams.wpf_min_price) : false,
						maxPrice = urlParams.wpf_max_price ? parseFloat(urlParams.wpf_max_price) : false,
						$options = $filter.find('li');
					$options.find('input[type="checkbox"]').prop('checked', false);
					$options.each(function () {
						var _this = jQuery(this),
							range = _this.attr('data-range');
						if(typeof range != 'undefined') {
							range = range.split(',');
							var minRange = range[0] == '' ? false : parseFloat(range[0]),
								maxRange = range[1] == '' ? false : parseFloat(range[1]),
								minPrices = [minPrice - 1, minPrice, minPrice + 1], // rounding error correction
								maxPrices = [maxPrice - 1, maxPrice, maxPrice + 1]; // rounding error correction

							if(minPrices.includes(minRange) && maxPrices.includes(maxRange)){
								_this.find('input[type="checkbox"]').prop('checked', true);
								return false;
							}
						}
					});
					if(typeof(_thisObj.eventChangeFilterPro) == 'function') {
						_thisObj.eventChangeFilterPro($filter, settings);
					}
					break;
			}
		});

	});

	WpfFrontendPage.prototype.findGetParameter =(function(url){
		// This function is anonymous, is executed immediately and
		// the return value is assigned to QueryString!
		var query_string = {},
			usefulParam = url.split("?")[1] || "",
			query = usefulParam || "",
			vars = query.split("&");

		for (var i = 0; i < vars.length; i++) {
			var pair = vars[i].split("=");

			// If first entry with this name
			if (typeof query_string[pair[0]] === "undefined") {
				query_string[pair[0]] = decodeURIComponent(pair[1]);
				// If second entry with this name
			} else if (typeof query_string[pair[0]] === "string") {
				var arr = [query_string[pair[0]], decodeURIComponent(pair[1])];
				query_string[pair[0]] = arr;
				// If third or later entry with this name
			} else {
				query_string[pair[0]].push(decodeURIComponent(pair[1]));
			}
		}
		return query_string;
	});

	WpfFrontendPage.prototype.getClearLabel = (function (label, withCount) {
		if(withCount) {
			var cnt = label.lastIndexOf('(');
			if(cnt == -1) cnt = label.lastIndexOf('<span');
			if(cnt != -1) label = label.substring(0, cnt).trim();
		}

		label = label.replace(/&nbsp;/g,'');

		return label;
	});

	WpfFrontendPage.prototype.getFilterOptionsByType = (function($filter, filterType){
		var _thisObj = this.$obj;
		return _thisObj['get' + filterType.replace('wpf', '') + 'FilterOptions']($filter);
	});

	WpfFrontendPage.prototype.getPriceFilterOptions = (function ($filter) {
		var _thisObj = this.$obj,
			optionsArray = [],
			options = [],
			minPrice = $filter.find('#wpfMinPrice').val(),
			maxPrice = $filter.find('#wpfMaxPrice').val(),
			tax = $filter.data('tax'),
			rate = $filter.data('rate'),
			str = '';

		[minPrice, maxPrice, tax] = _thisObj.getConvertedPrices([minPrice, maxPrice, tax], rate);
		str = minPrice + ',' + maxPrice;
		options.push(str);

		//options for frontend(change url)
		var frontendOptions = [],
			getParams = $filter.attr('data-get-attribute');
		frontendOptions['rate'] = rate;

		getParams = getParams.split(",");
		for (var i = 0; i < getParams.length; i++) {
			if (i === 0) {
				frontendOptions[getParams[i]] = minPrice;
			}
			if (i === 1) {
				frontendOptions[getParams[i]] = maxPrice;
			}
			if (i === 2 && tax !== '') {
				frontendOptions[getParams[i]] = tax;
			}
		}
		var symbol = $filter.find('.wpfCurrencySymbol'),
			symbolB = symbol.length && !symbol.is(':last-child') ? symbol.html() : '',
			symbolA = symbol.length && symbol.is(':last-child') ? symbol.html() : '',
			selectedOptions = {'is_one': true, 'list': [symbolB + minPrice + symbolA + ' - ' + symbolB + maxPrice + symbolA]};

		optionsArray['backend'] = options;
		optionsArray['frontend'] = frontendOptions;
		optionsArray['selected'] = selectedOptions;
		optionsArray['stats'] = [[minPrice, maxPrice]];

		return optionsArray;
	});

	WpfFrontendPage.prototype.getPriceRangeFilterOptions = (function ($filter) {
		var _thisObj = this.$obj,
			optionsArray = [],
			options = [],
			frontendOptions = [],
			selectedOptions = {'is_one': true, 'list': []},
			statistics = [],
			i = 0,
			rate = $filter.data('rate');

		if ($filter.attr('data-display-type') === 'list') {
			if ($filter.find("input:checked").length) {
				var li = $filter.find('input:checked').closest('li');
				options[i] = li.attr('data-range');
				selectedOptions['list'][i] = li.find('.wpfValue').html();
			}
		}else if($filter.attr('data-display-type') === 'dropdown'){
			if($filter.find(":selected").attr('data-range')){
				var option = $filter.find(":selected");
				options[i] = option.attr('data-range');
				selectedOptions['list'][i] = option.html();
			}

		}

		//options for frontend(change url)
		if (typeof options !== 'undefined' && options.length > 0) {
			var getParams = $filter.attr('data-get-attribute'),
				tax = $filter.data('tax');
			frontendOptions['rate'] = rate;
			getParams = getParams.split(",");
			if (typeof options[0] !== 'undefined' && options[0].length > 0) {
				var prices = options[0].split(',');
				[prices[0], prices[1], tax] = _thisObj.getConvertedPrices([prices[0], prices[1], tax], rate);
				frontendOptions[getParams[0]] = prices[0];
				frontendOptions[getParams[1]] = prices[1];
				if (typeof tax !== 'undefined') {
					frontendOptions[getParams[2]] = tax;
				}
			}
		}
		if (options.length == 0) {
			var defRange = $filter.attr('data-default');
			if (typeof defRange != 'undefined' && defRange.length) {
				options[i] = defRange;
			}
		}

		if (typeof rate !== 'undefined') {
			var minPrice = '',
				maxPrice = '';
			options = options.map(function (elem) {
				[minPrice, maxPrice] = _thisObj.getConvertedPrices(elem.split(","), rate);
				return minPrice + ',' + maxPrice;
			});
		}

		if (options.length) {
			statistics = options.map(function (elem) {
				return elem.split(",");
			});
		}
		optionsArray['backend'] = options;
		optionsArray['frontend'] = frontendOptions;
		optionsArray['selected'] = selectedOptions;
		optionsArray['stats'] = statistics;

		return optionsArray;
	});

	WpfFrontendPage.prototype.getSortByFilterOptions = (function ($filter) {
		var optionsArray = [],
			options = [],
			frontendOptions = [],
			selectedOptions = {'is_one': true, 'list': []},
			value = '';
		if ($filter.data('display-type') === 'radio') {
			var elem = $filter.find('input:checked').closest('li'),
				name = elem.find('.wpfFilterTaxNameWrapper').html();
		} else {
			var elem = $filter.find('select:not(.wpfPerPageDD)').find('option:selected'),
				name = elem.html();
		}
		if (elem.length) {
			value = elem.data('term-slug');
			//options for backend (filtering)
			options.push(value);

			//options for frontend(change url)
			var getParams = $filter.attr('data-get-attribute');
			frontendOptions[getParams] = value;
			selectedOptions['list'][0] = name;
			optionsArray['stats'] = [name];
		}
		if ($filter.data('first-instock') == '1') {
			frontendOptions['oistock'] = 1;
			options.push('oistock');
		}

		optionsArray['backend'] = options;
		optionsArray['frontend'] = frontendOptions;
		optionsArray['selected'] = selectedOptions;
		return optionsArray;
	});

	WpfFrontendPage.prototype.getInStockFilterOptions = (function ($filter) {
		var optionsArray = [],
			frontendOptions = [],
			options = [],
			filterType = $filter.attr('data-display-type'),
			selectedOptions = {'is_one': (filterType === 'dropdown'), 'list': []},
			statistics = [],
			i = 0;

		//options for backend (filtering)
		if(filterType === 'dropdown'){
			var option = $filter.find(":selected"),
				value = option.attr('data-slug');
			if(value != '') {
				options[i] = value;
				frontendOptions[i] = value;
				var name = option.html();
				selectedOptions['list'][i] = name;
				statistics.push(name);
			}
		} else {
			$filter.find('input:checked').each(function () {
				var li = jQuery(this).closest('li'),
					slug = li.attr('data-term-slug'),
					name = li.find('.wpfFilterTaxNameWrapper').length ? li.find('.wpfFilterTaxNameWrapper').html() : li.find('.wpfValue').html();
				options[i] = slug;
				frontendOptions[i] = slug;
				selectedOptions['list'][li.attr('data-term-id')] = name;
				statistics.push(name);
				i++;
			});
		}
		optionsArray['backend'] = options;

		//options for frontend(change url)
		var getParams = $filter.attr('data-get-attribute');

		optionsArray['frontend'] = [];
		optionsArray['frontend']['taxonomy'] = getParams;
		optionsArray['frontend']['settings'] = frontendOptions;
		optionsArray['selected'] = selectedOptions;
		optionsArray['stats'] = statistics;

		return optionsArray;
	});

	WpfFrontendPage.prototype.getCategoryFilterOptions = (function ($filter) {
		var _thisObj = this.$obj,
			optionsArray = [],
			frontendOptions = [],
			options = [],
			filterType = $filter.attr('data-display-type'),
			useSlugs = $filter.attr('data-use-slugs') == '1',
			selectedOptions = {'is_one': (filterType == 'list' || filterType == 'dropdown'), 'list': []},
			statistics = [],
			i = 0;

		//options for backend (filtering)
		if(filterType === 'dropdown'){
			var option = $filter.find(":selected"),
				id = option.attr('data-term-id'),
				value = option.val();
			if(value != '') {
				options[i] = value;
				var name = _thisObj.getClearLabel(option.html(), $filter.hasClass('wpfShowCount'));
				selectedOptions['list'][id] = name;
				statistics.push(name);
			}
			frontendOptions[i] = (useSlugs ? option.attr('data-term-slug') : id);
		} else if(filterType === 'mul_dropdown'){
			$filter.find(':selected').each(function () {
				var option = jQuery(this),
					id = option.attr('data-term-id');
				options[i] = option.val();
				frontendOptions[i] = (useSlugs ? option.attr('data-term-slug') : id);
				var name = _thisObj.getClearLabel(option.html(), $filter.hasClass('wpfShowCount'));
				selectedOptions['list'][id] = name;
				statistics.push(name);
				i++;
			});
		} else {
			var removeSelectedList = [];
			$filter.find('input').each(function () {
				var inputCurent = jQuery(this),
					liCurent = inputCurent.closest('li'),
					id = liCurent.data('term-id'),
					isParent = liCurent.children('ul').length > 0,
					isChecked = inputCurent.is(':checked'),
					hierarchicalLogic = $filter.attr('data-logic-hierarchical'),
					type = $filter.attr('data-display-type'),
					isHierarchical = $filter.attr('data-show-hierarchical'),
					isHierarchicalLogic = isHierarchical === 'true' && type === 'multi' || type === 'text';

				if ( isParent ) {
					var isAllChildChecked = true,
						childList = [];

					liCurent.find('ul li').each(function() {
						var childId = jQuery(this).data('term-id'),
							childLi = jQuery(this),
							childInput = childLi.find('input'),
							isChildChecked = childInput.prop('checked');

						childList.push(childId);

						if (!isChildChecked) {
							isAllChildChecked = false;
							return false;
						}
					});

					if (isChecked && isAllChildChecked) {
						removeSelectedList = removeSelectedList.concat(childList);

						var onlyUnique = function(value, index, self) {
							return self.indexOf(value) === index;
						}

						removeSelectedList = removeSelectedList.filter( onlyUnique );
						selectedOptions.removeSelected = removeSelectedList;
					}
				}

				if (jQuery(this).is(':checked')) {
					if ( isHierarchicalLogic && hierarchicalLogic == 'child' ) {
						var liElements = liCurent.find('li'),
							isChildChicked = false;

						for (var j = 0; j < liElements.length; ++j) {
							 var li = liElements[j];
							if (jQuery(li).find('input').prop('checked')) {
								isChildChicked = true;
							}
						}
						if (!isChildChicked) {
							options[i] = id;
							frontendOptions[i] = (useSlugs ? liCurent.attr('data-term-slug') : id);
						}
					} else if(isHierarchicalLogic && hierarchicalLogic == 'parent') {
						var parents = liCurent.parents('li'),
							isChildChicked = false;

						for (var j = 0; j < parents.length; ++j) {
							 var li = parents[j];
							if (jQuery(li).find('input').prop('checked')) {
								isChildChicked = true;
							}
						}
						if (!isChildChicked) {
							options[i] = id;
							frontendOptions[i] = (useSlugs ? liCurent.attr('data-term-slug') : id);
						}
					} else {
						options[i] = id;
						frontendOptions[i] = (useSlugs ? liCurent.attr('data-term-slug') : id);
					}
					var name = liCurent.find('.wpfValue').html();
					selectedOptions['list'][id] = name;
					statistics.push(liCurent.find('.wpfFilterTaxNameWrapper:first').length ? liCurent.find('.wpfFilterTaxNameWrapper:first').html() : name);
					i++;
				}
			});
		}

		var options = options.filter(function (el) {
			return el != null;
		});
		var frontendOptions = frontendOptions.filter(function (el) {
			return el != null;
		});


		optionsArray['backend'] = options;

		//options for frontend(change url)
		var getParams = $filter.attr('data-get-attribute');

		optionsArray['frontend'] = [];
		optionsArray['frontend']['taxonomy'] = getParams;
		optionsArray['frontend']['settings'] = frontendOptions;
		optionsArray['selected'] = selectedOptions;
		optionsArray['stats'] = statistics;

		return optionsArray;
	});

	WpfFrontendPage.prototype.getPerfectBrandFilterOptions = (function ($filter) {
		return this.$obj.getCategoryFilterOptions($filter);
	});

	WpfFrontendPage.prototype.getTagsFilterOptions = (function ($filter) {
		var _thisObj = this.$obj,
			optionsArray = [],
			options = [],
			frontendOptions = [],
			filterType = $filter.attr('data-display-type'),
			selectedOptions = {'is_one': (filterType == 'dropdown'), 'list': []},
			statistics = [],
			withCount = $filter.hasClass('wpfShowCount'),
			i = 0,
			proFilterType = [
				'colors'
			];

		//options for backend (filtering)
		if(filterType === 'dropdown'){
			var option = $filter.find(":selected"),
				value = option.val();
			if(value != '') {
				options[i] = value;
				frontendOptions[i] = option.attr('data-slug');
				var name = _thisObj.getClearLabel(option.html(), withCount);
				selectedOptions['list'][option.attr('data-term-id')] = name;
				statistics.push(name);
			}
		} else if (filterType === 'mul_dropdown'){
			$filter.find(':selected').each(function () {
				var option = jQuery(this);
				options[i] = option.val();
				frontendOptions[i] = option.attr('data-slug');
				var name = _thisObj.getClearLabel(option.html(), withCount);
				selectedOptions['list'][option.attr('data-term-id')] = name;
				statistics.push(name);
				i++;
			});
		} else if (jQuery.inArray(filterType, proFilterType) == -1 ) {
			$filter.find('input:checked').each(function () {
				var li = jQuery(this).closest('li'),
					id = li.attr('data-term-id');
				options[i] = id;
				frontendOptions[i] = li.attr('data-term-slug');
				var name = li.find('.wpfValue').html();
				selectedOptions['list'][id] = name;
				statistics.push(li.find('.wpfFilterTaxNameWrapper').length ? li.find('.wpfFilterTaxNameWrapper').html() : name);
				i++;
			});
		}

		var data = {
			options : options,
			frontendOptions : frontendOptions,
			selectedOptions : selectedOptions,
			statistics : statistics,
			i : i,
		}

		if (typeof window.wpfFrontendPage.getTagsFilterOptionsPro == 'function') {
			data = window.wpfFrontendPage.getTagsFilterOptionsPro($filter, data);
		}

		optionsArray['backend'] = data.options;

		//options for frontend(change url)
		var getParams = $filter.attr('data-get-attribute');

		optionsArray['frontend'] = [];
		optionsArray['frontend']['taxonomy'] = getParams;
		optionsArray['frontend']['settings'] = data.frontendOptions;
		optionsArray['selected'] = data.selectedOptions;
		optionsArray['stats'] = data.statistics;

		return optionsArray;
	});

	WpfFrontendPage.prototype.getAttributeFilterOptions = (function ($filter) {
		var _thisObj = this.$obj,
			optionsArray = [],
			options = [],
			frontendOptions = [],
			filterType = $filter.attr('data-display-type'),
			selectedOptions = {'is_one': (filterType == 'dropdown'), 'list': []},
			statistics = [],
			withCount = $filter.hasClass('wpfShowCount'),
			i = 0,
			proFilterType = [
				'slider',
				'colors'
			];

		//options for backend (filtering)
		if (filterType === 'dropdown'){
			var option = $filter.find(":selected"),
				value = option.val();
			if (value != '') {
				options[i] = value;
				frontendOptions[i] = option.attr('data-slug');
				var name = _thisObj.getClearLabel(option.html(), withCount);
				selectedOptions['list'][option.attr('data-term-id')] = name;
				statistics.push(name);
			}
		} else if (filterType === 'mul_dropdown'){
			$filter.find(':selected').each(function () {
				var option = jQuery(this);
				options[i] = option.val();
				frontendOptions[i] = option.attr('data-slug');
				var name = _thisObj.getClearLabel(option.html(), withCount);
				selectedOptions['list'][option.attr('data-term-id')] = name;
				statistics.push(name);
				i++;
			});
		} else if (jQuery.inArray(filterType, proFilterType) == -1 ) {
			$filter.find('input:checked').each(function () {
				var li = jQuery(this).closest('li'),
					id = li.attr('data-term-id');
				options[i] = id;
				frontendOptions[i] = li.attr('data-term-slug');
				var name = li.find('.wpfValue').html();
				selectedOptions['list'][id] = name;
				statistics.push(li.find('.wpfFilterTaxNameWrapper').length ? li.find('.wpfFilterTaxNameWrapper').html() : name);
				i++;
			});
		}

		var data = {
			options : options,
			frontendOptions : frontendOptions,
			selectedOptions : selectedOptions,
			statistics : statistics,
			i : i,
		}

		if (typeof window.wpfFrontendPage.getAttributeFilterOptionsPro == 'function') {
			data = window.wpfFrontendPage.getAttributeFilterOptionsPro($filter, data);
		}

		optionsArray['backend'] = data.options;

		//options for frontend(change url)
		var getParams = $filter.attr('data-get-attribute');

		optionsArray['frontend'] = [];
		optionsArray['frontend']['taxonomy'] = getParams;
		optionsArray['frontend']['settings'] = data.frontendOptions;
		optionsArray['selected'] = data.selectedOptions;
		optionsArray['stats'] = data.statistics;

		return optionsArray;
	});

	WpfFrontendPage.prototype.getAuthorFilterOptions = (function ($filter) {
		var _thisObj = this.$obj,
			optionsArray = [],
			options = [],
			frontendOptions = [],
			filterType = $filter.attr('data-display-type'),
			selectedOptions = {'is_one': (filterType == 'dropdown'), 'list': []},
			statistics = [],
			i = 0;

		//options for backend (filtering)
		if(filterType === 'list'){
			$filter.find('input:checked').each(function () {
				var li = jQuery(this).closest('li'),
					id = li.attr('data-term-id');
				options[i] = id;
				frontendOptions[i] = li.attr('data-term-slug');
				var name = li.find('.wpfValue').html();
				selectedOptions['list'][id] = name;
				statistics.push(li.find('.wpfFilterTaxNameWrapper').length ? li.find('.wpfFilterTaxNameWrapper').html() : name);
				i++;
			});
		} else if (filterType === 'mul_dropdown'){
			$filter.find(':selected').each(function () {
				var option = jQuery(this);
				options[i] = option.val();
				frontendOptions[i] = option.attr('data-slug');
				var name = _thisObj.getClearLabel(option.html());
				selectedOptions['list'][option.attr('data-term-id')] = name;
				statistics.push(name);
				i++;
			});
		} else if (filterType === 'dropdown'){
			var option = $filter.find(":selected"),
				value = option.val();
			options[i] = value;
			if(value != '') {
				frontendOptions[i] = option.attr('data-slug');
				var name = option.html();
				selectedOptions['list'][option.attr('data-term-id')] = name;
				statistics.push(name);
			}
		}
		optionsArray['backend'] = options;

		//options for frontend(change url)
		var getParams = $filter.attr('data-get-attribute');

		optionsArray['frontend'] = [];
		optionsArray['frontend']['taxonomy'] = getParams;
		optionsArray['frontend']['settings'] = frontendOptions;
		optionsArray['selected'] = selectedOptions;
		optionsArray['stats'] = statistics;

		return optionsArray;
	});

	WpfFrontendPage.prototype.getFeaturedFilterOptions = (function ($filter) {
		var optionsArray = [],
			options = [],
			frontendOptions = [],
			filterType = $filter.attr('data-display-type'),
			selectedOptions = {'is_one': (filterType == 'dropdown'), 'list': []},
			statistics = [],
			i = 0;

		$filter.find('input:checked').each(function () {
			var li = jQuery(this).closest('li'),
				id = li.attr('data-term-id');
			options[i] = id;
			frontendOptions[i] = li.attr('data-term-slug');
			var name = li.find('.wpfValue').html();
			selectedOptions['list'][id] = name;
			statistics.push(li.find('.wpfFilterTaxNameWrapper').length ? li.find('.wpfFilterTaxNameWrapper').html() : name);
			i++;
		});
		optionsArray['backend'] = options;

		//options for frontend(change url)
		var getParams = $filter.attr('data-get-attribute');

		optionsArray['frontend'] = [];
		optionsArray['frontend']['taxonomy'] = getParams;
		optionsArray['frontend']['settings'] = frontendOptions;
		optionsArray['selected'] = selectedOptions;
		optionsArray['stats'] = statistics;

		return optionsArray;
	});

	WpfFrontendPage.prototype.getOnSaleFilterOptions = (function ($filter) {
		var optionsArray = [],
			options = [],
			frontendOptions = [],
			filterType = $filter.attr('data-display-type'),
			selectedOptions = {'is_one': (filterType == 'dropdown'), 'list': []},
			statistics = [],
			i = 0;

		$filter.find('input:checked').each(function () {
			var li = jQuery(this).closest('li'),
				id = li.attr('data-term-id');
			options[i] = id;
			frontendOptions[i] = li.attr('data-term-slug');
			var name = li.find('.wpfValue').html();
			selectedOptions['list'][id] = name;
			statistics.push(li.find('.wpfFilterTaxNameWrapper').length ? li.find('.wpfFilterTaxNameWrapper').html() : name);
			i++;
		});
		optionsArray['backend'] = options;

		//options for frontend(change url)
		var getParams = $filter.attr('data-get-attribute');

		optionsArray['frontend'] = [];
		optionsArray['frontend']['taxonomy'] = getParams;
		optionsArray['frontend']['settings'] = frontendOptions;
		optionsArray['selected'] = selectedOptions;
		optionsArray['stats'] = statistics;

		return optionsArray;
	});

	WpfFrontendPage.prototype.getRatingFilterOptions = (function ($filter) {
		var optionsArray = [],
			frontendOptions = [],
			options = [],
			filterType = $filter.attr('data-display-type'),
			selectedOptions = {'is_one': true, 'list': []},
			statistics = [],
			i = 0;

		//options for backend (filtering)
		if(filterType == 'linestars' || filterType == 'liststars'){
			var input = $filter.find('input.wpfStarInput:checked'),
				rating = input.val();
			options[i] = rating;
			frontendOptions[i] = rating;
			var name = input.attr('data-label');
			selectedOptions['list'][i] = name;
			statistics.push(name);
		}else if(filterType == 'list'){
			$filter.find('input:checked').each(function () {
				var li = jQuery(this).closest('li'),
					id = li.attr('data-term-id');
				options[i] = id;
				frontendOptions[i] = li.attr('data-term-slug');
				var name = li.find('.wpfValue').html();
				selectedOptions['list'][id] = name;
				statistics.push(li.find('.wpfFilterTaxNameWrapper').length ? li.find('.wpfFilterTaxNameWrapper').html() : name);
				i++;
			});
		}else if(filterType == 'dropdown'){
			var option = $filter.find(":selected"),
				value = option.val();
			options[i] = value;
			if(value != '') {
				frontendOptions[i] = option.attr('data-slug');
				var name = option.html();
				selectedOptions['list'][option.attr('data-term-id')] = name;
				statistics.push(name);
			}
		}
		optionsArray['backend'] = options;

		//options for frontend(change url)
		var getParams = $filter.attr('data-get-attribute');

		optionsArray['frontend'] = [];
		optionsArray['frontend']['taxonomy'] = getParams;
		optionsArray['frontend']['settings'] = frontendOptions;
		optionsArray['selected'] = selectedOptions;
		optionsArray['stats'] = statistics;

		return optionsArray;
	});

	WpfFrontendPage.prototype.disableLeerOptions = (function () {
		var _thisObj = this.$obj;
		jQuery('.wpfMainWrapper').each(function () {
			var mainWrapper = jQuery(this),
				settings = _thisObj.getFilterMainSettings(mainWrapper);
			if(settings && settings.settings.filter_null_disabled === '1') {
				var filters = mainWrapper.find('.wpfFilterWrapper.wpfShowCount');
				if(filters.length) {
					filters.find('option[data-term-id]').prop('disabled', false);
					filters.find('option[data-term-id][data-count="0"]').prop('disabled', true);
					filters.find('.wpfCount').each(function(){
						var cntObj = jQuery(this),
							leer = cntObj.html() == '(0)',
							el = cntObj.closest('[data-term-id]'),
							input = false;
						if(el.length == 0) {
							el = cntObj.closest('[data-term-slug]');
						}
						if(el.length) {
							if(el.is('input')) {
								input = el;
								el = input.parent();
							} else {
								input = el.find('input');
							}
							input.prop('disabled', leer);
							if(leer) el.addClass('wpfOptionDisabled');
							else el.removeClass('wpfOptionDisabled');
						}
					});
				}
			}
		});
	});

	WpfFrontendPage.prototype.addSpecificPluginActions = (function() {
		// elementor plugin, action when widgets in admin panek are ready
		jQuery(window).on('load', function() {
			if ( window.elementorFrontend ) {
				if ( window.elementorFrontend.hooks ) {
					elementorFrontend.hooks.addAction( 'frontend/element_ready/widget', function( $scope ) {
						var wrapper = $scope.find('.wpfMainWrapper');

						hideFilterLoader(wrapper);
					});
				}
			} else if ( jQuery('.elementor .wpfMainWrapper').length ) {
				setTimeout(function() {
					jQuery('.elementor .wpfMainWrapper').each(function() {
						var wrapper = jQuery(this);
						hideFilterLoader(wrapper);
					});
				}, 2000);
			}
		});
	});

	WpfFrontendPage.prototype.fixSelector = (function(selector, defaultSelector) {
		if (typeof selector == 'undefined' || selector === '') {
			return typeof defaultSelector == 'undefined' ? '' : defaultSelector;
		}
		return (selector.search(/\.|#/) === -1) ? '.' + selector.replace(/(\s+)(\w+)/g, ' .$2')	: selector ;
	});

	WpfFrontendPage.prototype.markCheckboxSelected = (function ($filter, first) {
		if ($filter.length) {
			var settings = this.getFilterMainSettings($filter.closest('.wpfMainWrapper'));
			if(settings) {
				if (settings.settings.checked_items_bold == '1') {
					var wpfMainWrapper = $filter.closest('.wpfMainWrapper');
					wpfMainWrapper.find('.wpfDisplay').css('font-weight', '');
					wpfMainWrapper.find('.wpfAttrLabel').css('font-weight', '');
					// console.log(wpfMainWrapper.find('input:checked'))
					wpfMainWrapper.find('input:checked').each(function () {
						var wpfDisplay = jQuery(this).closest('.wpfLiLabel').find('.wpfDisplay'),
							wpfAttrLabel = jQuery(this).closest('.wpfColorsColBlock').find('.wpfAttrLabel');

						wpfDisplay.css('font-weight', 'bold');
						wpfAttrLabel.css('font-weight', 'bold');
					});
				}
			}
			if (first) {
				//need to delete automatic checked inputs by goback in browser Chrome/FF
				setTimeout(function () {
					$filter.find('input').each(function() {
						var input = this;
						if (input.type == "checkbox") {
							if (input.defaultChecked == true) {
								if (input.checked != true) input.checked = true;
							} else {
								if (input.checked == true) {
									input.checked = false;
									jQuery(input).closest('.wpfLiLabel').find('.wpfDisplay').removeClass('selected').css('font-weight', '');
								}
							}

						}
					});
				}, 100);
			}
		}
	});

	window.wpIinitialiseImmediately = typeof wpIinitialiseImmediately !== 'undefined' ? wpIinitialiseImmediately : 0;
	jQuery(document).ready(function () {
		if (!window.wpfFrontendPage) window.wpfFrontendPage = new WpfFrontendPage();
		if (typeof isElementorEditMode == 'undefined') {
			window.wpfFrontendPage.init();
		}
	});
	WpfFrontendPage.prototype.unserializeStr = function(serializedString){
		var str = decodeURI(serializedString);
        var pairs = str.split('&');
        var obj = {}, p, idx;
        for (var i=0, n=pairs.length; i < n; i++) {
            p = pairs[i].split('=');
            idx = p[0];
            if (obj[idx] === undefined) {
                obj[idx] = unescape(p[1]).replace ( /\+/g, ' ' );
            }else{
                if (typeof obj[idx] == "string") {
                    obj[idx]=[obj[idx]];
                }
                obj[idx].push(unescape(p[1]).replace ( /\+/g, ' ' ));
            }
        }
        return obj;
	};

	WpfFrontendPage.prototype.getConvertedPrices = function (data, rate) {
		if (typeof rate !== 'undefined' && rate !== 1 ) {
			if (typeof data[0] !== 'undefined') {
				data[0] = String(Math.round(data[0] / rate));
				if (typeof data[1] !== 'undefined') {
					data[1] = String(Math.round(data[1] / rate));
				}
				if (typeof data[2] !== 'undefined') {
					data[2] = String(Math.round(data[2] / rate));
				}
			} else {
				if (data.wpf_min_price) {
					data.wpf_min_price = String(Math.round(data.wpf_min_price * rate));
				}
				if (data.wpf_max_price) {
					data.wpf_max_price = String(Math.round(data.wpf_max_price * rate));
				}
				if (data.tax) {
					data.tax = String(Math.round(data.tax * rate));
				}
			}
		}
		return data;
	};

	if (jQuery('.variations_form').length > 0 && typeof $(this).wc_variation_form == 'function') {
		document.addEventListener('wpfAjaxSuccess', function (event) {
			$('.variations_form').each(function () {
				$(this).wc_variation_form();
			});
		});
	}

	if (jQuery('.jetpack-lazy-image').length > 0) {
		document.addEventListener('wpfAjaxSuccess', function (event) {
			jQuery('.jetpack-lazy-image').each(function () {
				jQuery(this).removeAttr('srcset');
			});
		});
	}
	if (jQuery('.jetpack-lazy-image').length > 0) {
		document.addEventListener('wpfAjaxSuccess', function (event) {
			jQuery('.jetpack-lazy-image').each(function () {
				jQuery(this).removeAttr('srcset');
			});
		});
	}

	// added for ElementorPro Loop Load More
	if (jQuery('div.elementor-widget[data-widget_type="loop-grid.product"]').length > 0) {
		document.addEventListener('wpfAjaxSuccess', function (event) {
			if (window.elementorFrontend && window.elementorFrontend.elementsHandler) {
				var $products = jQuery('div.elementor-widget[data-widget_type="loop-grid.product"]');
				if ($products.length == 1 && $products.find('.e-load-more-anchor').length == 1) {
					$products.removeClass('e-load-more-pagination-end');
					window.elementorFrontend.elementsHandler.runReadyTrigger($products);
				}
			}
		});
	}

	// added for plugin: WooCommerce Load More Products
	jQuery(document).ready(function () {
		if (jQuery('a.lmp_button').length > 0) {
			document.addEventListener('wpfAjaxSuccess', function (event) {
				if (!window.wpfDoNotLoadMore && typeof load_next_page == 'function') {
					load_next_page(true, decodeURIComponent(location.href));
				}
			});
		}
	});

	// plugin event woocommerce-product-search
	// https://docs.woocommerce.com/documentation/plugins/woocommerce/woocommerce-extensions/woocommerce-product-search/
	jQuery(document).on('ixProductFilterRequestProcessed', function (event) {
		window.wpfFrontendPage.filtering();
	});
	if (window.wpIinitialiseImmediately) {
		window.wpfFrontendPage = new WpfFrontendPage();
	}
}(window.jQuery, window));

var objQueryString={};

toggleClear();

// try to get jquery selector from random html string
function wpfGetSelector(html, controlExist, defSelector, countChilds, removeDynamicClasses, context) {
	if(html.length == 0) return defSelector;

	var elem     = (typeof context !== 'undefined') ? jQuery(html, context) : jQuery(html),
		selector = '',
		i = 0;

	if (typeof countChilds == 'undefined') {
		countChilds = 1;
	}

	while (i < countChilds && elem && elem.length) {
		if (i > 0) {
			selector += ' ';
		}

		i++;

		if (elem.length > 1) {
			elem = elem.last();
		}

		var elemId = elem.attr('id');
		if (typeof elemId != 'undefined') {
			selector += '#' + elemId;
		} else {
			var elemClass = elem.attr('class');

			// try to remove some dinamically changed classes that contains a number in it name
			if (removeDynamicClasses) {
				classList = elemClass.split(' ');
				classList.forEach( function( className, key ) {
					var hasNumber = /\d/.test(className);
					if ( hasNumber ) {
						classList.splice(key, 1);
					}
				});
				elemClass = classList.join(' ');
			}

			if ( typeof elemClass != 'undefined' && elemClass != '' ) {
				if (elemClass == 'container' && countChilds == 1) countChilds = 2;
				selector += elem.get(0).tagName + '.' + elemClass.trim().replace(/ +/g, '.');
			}
		}

		if (controlExist && selector != '' && jQuery(selector).length == 0) {
			selector = '';
			break;
		}

		var elem = elem.children();
	}

	return selector.length == 0 ? defSelector : selector;
}


function getUrlParams () {
	var params={};
	window.location.search
	  .replace(/[?&]+([^=&]+)=([^&]*)/gi, function(str,key,value) {
		params[key] = value;
	  }
	);
	return params;
}

function toggleClear() {
	var params = getUrlParams();
	jQuery(".wpfBlockClear").hide();
	jQuery(".wpfFilterWrapper").each(function(){
		var attr = jQuery(this).attr('data-get-attribute');
		if (attr in params) {
			jQuery(this).find(".wpfBlockClear").show();
		}
	});
	if ('wpf_min_price' in params || 'wpf_max_price' in params) {
		jQuery("[data-filter-type='wpfPrice']").find(".wpfBlockClear").show();
		jQuery("[data-filter-type='wpfPriceRange']").find(".wpfBlockClear").show();
	}
}

//Get querystring value
function getParameterByName(name, searchUrl) {
	name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
	var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
		results = regex.exec(searchUrl);
	return results === null ? "" : decodeURIComponent(results[1]);//decodeURIComponent(results[1].replace(/\+/g, " "));
}
function getCurrentUrlPartsWpf() {
	var parts = window.wpfNewUrl.split('?'),
		s = (parts[1] || '');
	return {href: window.wpfNewUrl, path: parts[0].replace(/#.*$/, ''), search: (s.length ? '?' + s : '')};
}

//Add or modify querystring
function changeUrl(filterSlug, filterValue, $wooPage, $filterWrapper) {
	removePageQString();
	//Get query string filterValue
	$wooPage = (typeof $wooPage != 'undefined' ? $wooPage: false);
	var curUrl = getCurrentUrlPartsWpf();
	if(!$wooPage){
		var searchUrl = decodeURIComponent(curUrl.search);
	}else{
		if($filterWrapper.attr('data-hide-url')){
			var searchUrl=$filterWrapper.attr('data-hide-url');
		} else {
			var searchUrl = '';
		}
	}

	if (searchUrl.indexOf("redirect")!==-1) {
		searchUrl = '';
	}

	if(searchUrl.indexOf("?")== "-1") {
		var urlValue = curUrl.path + '?'+filterSlug+'='+filterValue;
	} else {
		//Check for filterSlug in query string, if not present
		if(searchUrl.indexOf('&'+filterSlug+'=')== "-1" && searchUrl.indexOf('?'+filterSlug+'=')== "-1") {
			var urlValue=searchUrl+'&'+filterSlug+'='+filterValue;
		//If filterSlug present in query string
		} else {
			var oldValue = getParameterByName(filterSlug, searchUrl);
			if(searchUrl.indexOf("?"+filterSlug+"=")!= "-1") {
				var urlValue = searchUrl.replace('?'+filterSlug+'='+oldValue,'?'+filterSlug+'='+filterValue);
			// add existing in url filter with another option
			} else {
				var urlValue = searchUrl.replace('&'+filterSlug+'='+oldValue,'&'+filterSlug+'='+filterValue);
			}
		}
		urlValue = curUrl.path + urlValue;
		//history.pushState function is used to add history state.
		//It takes three parameters: a state object, a title (which is currently ignored), and (optionally) a URL.
	}
	if(!$wooPage){
		window.wpfNewUrl = encodeURI(urlValue).indexOf('%25') === -1 ? encodeURI(urlValue) : urlValue;
	}
	objQueryString.key=filterValue;
	return urlValue;
}

function removePageQString() {
	var curUrl = getCurrentUrlPartsWpf(),
		path = curUrl.path,
		page = path.indexOf('/page/');
	if(page != -1 && history.pushState) {
		window.wpfNewUrl = path.substr(0, page + 1) + curUrl.search;
	} else {
        window.wpfNewUrl = curUrl.path + removePagenum(curUrl.search);
    }
}

function removePagenum(url) {
    return url.replace(/([&?])pagenum=\d+(&|$)/g, function(match, prefix, suffix) {
        return prefix === '?' && suffix === '&' ? '?' :
               prefix === '&' && suffix === '' ? '' :
               prefix;
    });
}

//Function used to remove querystring
function removeQString(key, $wooPage, $filterWrapper) {
	removePageQString();
	//Get query string value
	var curUrl = getCurrentUrlPartsWpf(),
		urlValue=decodeURI(curUrl.href);
	$wooPage = (typeof $wooPage != 'undefined' ? $wooPage: false);
	if(!$wooPage){
		var searchUrl=decodeURIComponent(curUrl.search);
	}else{
		if($filterWrapper.attr('data-hide-url')){
			var searchUrl=decodeURI($filterWrapper.attr('data-hide-url'));
		}else {
			var searchUrl = '';
		}
		var urlValue=curUrl.href + searchUrl;
	}
	if(key!="") {
		var oldValue = getParameterByName(key, searchUrl),
			removeVal=key+"="+oldValue;

		if(searchUrl.indexOf('?'+removeVal+'&')!= "-1") {
			urlValue=urlValue.replace('?'+removeVal+'&','?');
		}
		else if(searchUrl.indexOf('&'+removeVal+'&')!= "-1") {
			urlValue=urlValue.replace('&'+removeVal+'&','&');
		}
		else if(searchUrl.indexOf('?'+removeVal)!= "-1") {
			urlValue=urlValue.replace('?'+removeVal,'');
		}
		else if(searchUrl.indexOf('&'+removeVal)!= "-1") {
			urlValue=urlValue.replace('&'+removeVal,'');
		}
		if($wooPage){
			urlValue = urlValue.replace(curUrl.href,'');
		}
	} else {
		if(!$wooPage){
			var searchUrl=decodeURIComponent(curUrl.search);
			urlValue=urlValue.replace(searchUrl,'');
		}else{
			var searchUrl=$filterWrapper.attr('data-hide-url');
			urlValue=urlValue.replace(searchUrl,'');
			urlValue = urlValue.replace(curUrl.href,'');
		}
	}
	if(!$wooPage){
		window.wpfNewUrl = encodeURI(urlValue).indexOf('%25') === -1 ? encodeURI(urlValue) : urlValue;
	}
	return urlValue.indexOf('%25') !== -1 ? decodeURI(urlValue) : urlValue;
}
function checkArray(my_arr){
	for(var i=0;i<my_arr.length;i++){
		if(my_arr[i] === "")
			return false;
	}
	return true;
}
jQuery.toQueryParams = function(str, separator) {
	separator = separator || '&';
	var obj = {};
	if (str.length == 0)
		return obj
	var c = str.substr(0,1),
		s = c=='?' || c=='#'  ? str.substr(1) : str,
		a = s.split(separator);
	for (var i=0; i<a.length; i++) {
		var p = a[i].indexOf('=');
		if (p < 0) {
			obj[a[i]] = '';
			continue
		}
		var k = decodeURIComponent(a[i].substr(0,p)),
			v = decodeURIComponent(a[i].substr(p+1)),
			bps = k.indexOf('[');
		if (bps < 0) {
			obj[k] = v
			continue;
		}

		var bpe = k.substr(bps+1).indexOf(']');
		if (bpe < 0) {
			obj[k] = v
			continue;
		}

		var bpv = k.substr(bps+1, bps+bpe-1),
			k = k.substr(0,bps);
		if (bpv.length <= 0) {
			if (typeof(obj[k]) != 'object') obj[k] = [];
			obj[k].push(v);
		} else {
			if (typeof(obj[k]) != 'object') obj[k] = {};
			obj[k][bpv] = v;
		}
	}
	return obj;
}
function wpfChangeFiltersCount (wpfExistTerms, synchroFilterId) {

	var wpfPage = window.wpfFrontendPage,
		curFilter = wpfPage.isSynchro ? (typeof(synchroFilterId) != 'undefined' && synchroFilterId.length ? '#'+synchroFilterId : '#'+wpfPage.currentLoadId) : 'body';

	jQuery(curFilter).find(".wpfShowCount").find(".wpfCount").html("(0)");
	jQuery(curFilter).find(".wpfShowCount select:not([multiple]) option[data-count]").each(function(){
		var attr = jQuery(this).attr("data-term-name");
		jQuery(this).attr('data-count', 0).html(attr+" (0)");
	});
	jQuery(curFilter).find(".wpfShowCount select[multiple]").find("option").each(function(){
		attr = jQuery(this).attr("data-term-name");
		jQuery(this).attr('data-count', 0).html(attr+" (0)");
	});

	jQuery(curFilter).find(".wpfShowCount").each(function(filterCounter){

		var filter = jQuery(this);
		if (filter.attr("data-filter-type").length > 0) {
			var taxonomy = filter.data('taxonomy');
			if(typeof taxonomy == 'undefined' || taxonomy.length == 0) taxonomy = filter.data('slug');

			if (taxonomy in wpfExistTerms) {

				if (typeof (wpfExistTerms[taxonomy].relation) !== 'undefined') {
					var htmlArray = {};
					jQuery(curFilter).find('div.wpfShowCount[data-taxonomy="' + taxonomy + '"] li').each(function () {
						htmlArray[jQuery(this).data('term-id')] = jQuery('.wpfFilterTaxNameWrapper', this).html();
					});

					jQuery.each(wpfExistTerms[taxonomy].relation, function (index, value) {
						jQuery.each(htmlArray, function (i, v) {
							if (v === value && i !== index) {
								wpfExistTerms[taxonomy][i] = wpfExistTerms[taxonomy][index];
								delete wpfExistTerms[taxonomy][index];
							}
						});
					});
				}

				jQuery.each(wpfExistTerms[taxonomy], function (index, value) {
					changeCount(taxonomy, index, value, curFilter);
				});

				delete wpfExistTerms[taxonomy];
			}

		}
	});

	window.wpfFrontendPage.disableLeerOptions();
	var multi = jQuery('.wpfShowCount').find('select[multiple]');
	if (multi.length) multi.multiselect('reload');
};

function changeCount(taxonomy, index, value, curFilter) {

	var oIndex = index;
	index = index.toLowerCase();
	var els = jQuery(curFilter).find('div.wpfShowCount[data-taxonomy="' + taxonomy + '"] [data-term-id="' + oIndex + '"]');
	if (els.length === 0) {
		els = jQuery(curFilter).find('div.wpfShowCount[data-slug="' + taxonomy + '"] [data-term-id="' + oIndex + '"]');
	}
	if (els.length === 0) {
		els = jQuery(curFilter).find('div.wpfShowCount[data-taxonomy="' + taxonomy + '"] [data-term-id="' + index + '"]');
	}
	if (els.length === 0) {
		els = jQuery(curFilter).find('div.wpfShowCount[data-slug="' + taxonomy + '"] [data-term-id="' + index + '"]');
	}
	if (els.length > 0) {
		els.each(function () {
			var el = jQuery(this);
			if (el.find('.wpfCount').length > 0) {
				el.find('.wpfCount:first').html('(' + value + ')');
			} else if (el.parent().attr('class') == 'wpfColorsColBlock') {
				el.parent().find('.wpfCount').html('(' + value + ')');
			} else {
				var attrname = el.attr('data-term-name');
				var tooltipstered = el.siblings('.tooltipstered');
				if (tooltipstered.length) {
					attrname = tooltipstered.attr('data-term-name');
					tooltipstered.tooltipster('content', '' + attrname + ' (' + value + ')');
					if (tooltipstered.attr('data-show-count')) tooltipstered.text(value);
				} else if (attrname !== undefined) {
					el.html('' + attrname + ' (' + value + ')');
				} else {
					el.html('' + index + ' (' + value + ')');
				}
			}
			if (el.is('option')) {
				el.attr('data-count', value);
			}
		});
	}
}

// show/hide individual filter items
function wpfShowHideFiltersAtts(wpfExistTerms, wpfExistUsers, synchroFilterId) {

	var wpfPage = window.wpfFrontendPage,
		curFilter = typeof(synchroFilterId) != 'undefined' && synchroFilterId.length ? synchroFilterId : wpfPage.currentLoadId;

	wpfPage.restoreSelectsForSafari();

	jQuery((curFilter ? '#' + curFilter + ' ' : '') + '.wpfFilterWrapper').each(function(){
		var filter = jQuery(this),
			filterType = filter.data('filter-type'),
			isTextFilter = filter.attr('data-display-type') == 'text',
			filterContentType = isTextFilter && filter.attr('data-control-products') == '1' ? 'taxonomy' : filter.data('content-type'),
			taxonomy = filter.data('taxonomy'),
			getAttr = filter.data('get-attribute'),
			isFilterCurentlyActivated = getParameterByName(getAttr, location.search),
			isHideActive = filter.data('hide-active'),
			isShowAll = Boolean(filter.data('show-all')),
			isNotInLogic = getAttr.indexOf('pr_filter') == -1 ? false : true,
			userExistIds = [];

		switch (filterContentType) {
			case 'user':
				jQuery.each(wpfExistUsers, function (index, value) {
					userExistIds.push(parseInt(value.ID));
				});
				if (!isFilterCurentlyActivated && userExistIds.length > 0) {
					filter.find('[data-term-id]').each(function () {
						var el = jQuery(this),
							userId = el.data('term-id');
						if (userExistIds.indexOf(userId) >= 0) {
							el.show();
						} else {
							el.hide();
						}
					});
				}
				break;
			case 'taxonomy':
				if (typeof taxonomy == 'undefined' || taxonomy.length == 0) {
					taxonomy = filter.data('slug');
				}

				if ((!isFilterCurentlyActivated || isHideActive) && !isNotInLogic && !isShowAll ) {
					if (taxonomy in wpfExistTerms || isFilterCurentlyActivated) {
						var termIds = wpfExistTerms[taxonomy] || {};
						filter.find('[data-term-id]').each(function () {
							var elem = jQuery(this),
								id = elem.data('term-id'),
								selected = isFilterCurentlyActivated && (elem.is('input:checked') || elem.find('input:checked').length || elem.is('option:selected'));
							if (id in termIds || selected) {
								if(elem.closest('.wpfButtonsFilter').length) elem.css('display', 'inline-block');
								else elem.show();
								if (elem.parent().hasClass('wpfColorsColBlock')) {
									elem.parent().parent().show();
								}
								if (elem.closest('.wpfColorsRow').length) {
									elem.parent().css('display', 'inline-block');
								}
							} else {
								elem.hide();
								elem.find('input').prop('checked', false);
								if (elem.parent().hasClass('wpfColorsColBlock')) {
									elem.parent().parent().hide();
								}
								if (elem.closest('.wpfColorsRow').length) {
									elem.parent().hide();
								}
							}
						});

						if (filter.attr('data-display-type') == 'slider' ) {
							if (typeof(wpfPage.updateAttrSlider) == 'function') {
								wpfPage.updateAttrSlider(filter, termIds);
							}
						} else {
							// all terms display block already has css display none
							var hideSingle = filter.attr('data-hide-single') == '1',
								preSelector = hideSingle && filter.find('select[multiple]').length ? 'option' : '',
								selector = hideSingle && filter.find('.wpfColorsFilter').length ? 'li[data-term-slug]' : '[data-term-id]',
								cntAll = isTextFilter ? 1 : filter.find(preSelector+selector).length,
								cntHidden = filter.find(preSelector+selector+'[style*="none"]').length,
								limit = hideSingle ? 1 : 0;
							if (cntAll-cntHidden <= limit) {
								filter.hide();
							} else {
								filter.show();
							}
						}
					} else {
						filter.find('input').prop('checked', false);
						filter.find('select').val('');
						filter.hide();
					}
				}
				var multi = filter.find('select[multiple]');
				if (multi.length) multi.multiselect('reload');

				if(typeof(window.wpfFrontendPage.wpfShowHideFiltersAttsPro) == 'function') {
					window.wpfFrontendPage.wpfShowHideFiltersAttsPro(filter);
				}
				break;
			default:
				// console.log(`Sorry, we are out of ${expr}.`);
		}
	});
	wpfPage.removeHiddenOptionsForSafari();
}

function wpfChangePriceFiltersCount(prices) {
	var _thisObj = window.wpfFrontendPage,
		noWooPage = _thisObj.noWoo,
		filterWrapper = jQuery('.wpfMainWrapper'),
		priceFilters = jQuery('.wpfFilterWrapper[data-filter-type="wpfPrice"].wpfNotActive');

	jQuery('.wpfFilterWrapper[data-filter-type="wpfPrice"]').each(function () {
		var wpfPrice = jQuery(this);
		wpfPrice.attr('data-minvalue', prices.wpf_min_price).attr('data-maxvalue', prices.wpf_max_price);

		wpfPrice.find('#wpfMinPrice').attr('min', prices.wpf_min_price).val(prices.wpf_min_price);
		wpfPrice.find('#wpfMaxPrice').attr('max', prices.wpf_max_price).val(prices.wpf_max_price);

		if (wpfPrice.find(".ion-range-slider").length) {
			wpfPrice.find(".ion-range-slider").each(function () {
				jQuery(this).attr('data-min', prices.wpf_min_price).attr('data-max', prices.wpf_max_price);
				var ionSlider = jQuery(this).data("ionRangeSlider");
				ionSlider.update({
					min: prices.wpf_min_price,
					max: prices.wpf_max_price,
				});
			});
		}
	});
	window.wpfFrontendPage.eventsPriceFilter();
	_thisObj.getUrlParamsChangeFiltersValues();
	/*if (typeof(window.wpfFrontendPage.eventsFrontendPro) == 'function') {
		window.wpfFrontendPage.eventsFrontendPro();
	}*/
}

function hideFilterLoader( wrapper ) {
	wrapper.find('.wpfLoaderLayout').hide();
	wrapper.css({
		position: ''
	});
	wrapper.find('.wpfFilterWrapper').css({
		visibility: 'inherit'
	});
}

function heightIdenticalInRow(selector) {
	var setMaxHeight = function (elements) {
		if (elements.length > 1) {
			var elementHeightMax = elements[0].height;
			for (var j = 1; j < elements.length; j++) {
				if (elements[j].height > elementHeightMax) {
					elementHeightMax = elements[j].height;
				}
			}
			for (var j = 0; j < elements.length; j++) {
				jQuery(elements[j].selector).height(elementHeightMax);
			}
		}
	};
	var elementsHeight = [];
	var rowIndex = 0;
	var elementIndex = 0;

	jQuery(selector).each(function (index, element) {
		if (!elementsHeight[rowIndex]) {
			elementsHeight[rowIndex] = [];
		}
		if (
			elementsHeight[rowIndex][elementsHeight[rowIndex].length - 1]
			&&
			elementsHeight[rowIndex][elementsHeight[rowIndex].length - 1].top
			&&
			elementsHeight[rowIndex][elementsHeight[rowIndex].length - 1].top !== jQuery(element).offset().top
		) {

			setMaxHeight(elementsHeight[rowIndex]);
			rowIndex++;
		}
		if (!elementsHeight[rowIndex]) {
			elementsHeight[rowIndex] = [];
		}
		elementsHeight[rowIndex].push({
			selector: selector + ':eq(' + elementIndex + ')',
			height: jQuery(element).height(),
			top: jQuery(element).offset().top
		});
		elementIndex++;
	});
	if (elementsHeight[rowIndex]) {
		setMaxHeight(elementsHeight[rowIndex]);
	}
}
function wpfDoActionsAfterLoad (fid, isFound, requestData) {
	if (typeof (window.wpfFrontendPage.saveStatistics) == 'function') {
		window.wpfFrontendPage.saveStatistics(fid, isFound, requestData);
	}
}
if (window.wpIinitialiseImmediately && window.wpfFrontendPage) {
	if (typeof isElementorEditMode == 'undefined') window.wpfFrontendPage.init();
}
