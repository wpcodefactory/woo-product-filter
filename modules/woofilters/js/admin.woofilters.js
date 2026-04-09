(function ($, app) {
"use strict";
	function goTo(item) {
		jQuery('html,body').animate({'scrollTop': jQuery(item).offset().top - 30}, 1000);

		return false;
	}

	function AdminPage() {
		this.$obj = this;
		this.$allowMultipleFilters = ['wpfAttribute', 'wpfBrand', 'wpfCustomMeta'];
		this.$multiSelectFields = ['f_mlist[]', 'f_cglist[][]', 'f_search_by_attributes_list[]', 'f_additional_attributes_list[]'];
		this.$noOptionsFilters = [''];
		this.filtersSettings = [];
		return this.$obj;
	}

	AdminPage.prototype.init = (function () {
		var _thisObj = this.$obj;
		_thisObj.wpfWaitLoad = true;
		_thisObj.wpfWaitResponse = false;
		_thisObj.wpfNeedPreview = false;
		_thisObj.isElementorEditMode = typeof isElementorEditMode !== 'undefined' ? isElementorEditMode : 0;
		_thisObj.eventsAdminPage();
		_thisObj.eventsFilters();
		_thisObj.setupPriceByHands();
		_thisObj.wpfWaitLoad = false;
		_thisObj.filterIterator = 0;
		setTimeout(function() {_thisObj.getPreviewAjax();}, 500);
		if(typeof(_thisObj.initPro) == 'function') _thisObj.initPro();
	});

	AdminPage.prototype.setupPriceByHands = (function () {
		var _this = this.$obj,
			options = {
			modal: true
			, autoOpen: false
			, width: 600
			, height: 400
			, buttons: {
				OK: function () {
					var emptyInput = false,
						options = '',
						range = jQuery('#wpfSetupPriceRangeByHand .wpfRangeByHand');

					//check if input is empty
					range.find('input').removeClass('wpfWarning').each(function () {
						var value = jQuery(this).val();
						if(!value || (value != 'i' && isNaN(value))) {
							jQuery(this).addClass('wpfWarning');
							emptyInput = true;
						}
					});

					if(!emptyInput){
						var rangeCount = range.length,
							i = 1,
							minValue = false,
							maxValue = false;
						range.each(function () {
							var el = jQuery(this),
								from = el.find('.wpfRangeByHandHandlerFrom input').val(),
								to = el.find('.wpfRangeByHandHandlerTo input').val();

							if(isNaN(from)) {from = 'i';}
							else {from = Number(from);}
							if(isNaN(to)) {to = 'i';}
							else {to = Number(to);}

							if(minValue != 'i' && (minValue === false || from == 'i' || from < minValue)) minValue = from;
							if(maxValue != 'i' && (maxValue === false || to == 'i' || to > maxValue)) maxValue = to;
							options += from + ',' + to;
							if(i != rangeCount) options += ',';

							i++;
						});

						var filterBlock = jQuery('.wpfFiltersBlock .wpfFilter[data-filter="wpfPriceRange"]'),
							preselect = (minValue == 'i' || minValue === false ? '' : 'wpf_min_price=' + minValue);
						filterBlock.find('input[name="f_range_by_hands_values"]').val(options);
						if(maxValue != 'i' && maxValue !== false) preselect += (preselect.length ? ';' : '') + 'wpf_max_price=' + maxValue;
						filterBlock.find('input[name="f_preselect"]').val(preselect);
						filterBlock.find('input[name="f_range_by_hands_default"]').val(minValue + ',' + maxValue);
						$container.empty();
						$container.dialog('close');
						_this.saveFilters();
						_this.getPreviewAjax();
					}

				}
				, Cancel: function () {
					$container.empty();
					$container.dialog('close');

				}
			},
			create:function () {
				$(this).closest('.ui-dialog').addClass('woobewoo-plugin');
			}
		};
		var $container = jQuery('<div id="wpfSetupPriceRangeByHand"></div>').dialog( options );

		jQuery('body').off('click', '.wpfRangeByHandSetup').on('click', '.wpfRangeByHandSetup', function (e) {
			e.preventDefault();
			var appendTemplate = '',
				priceRange = jQuery('input[name="f_range_by_hands_values"]').val(),
				template = jQuery('.wpfRangeByHandTemplate').clone().html(),
				templAddButton = jQuery('.wpfRangeByHandTemplateAddButton').clone().html();
			$container.empty();

			if(priceRange.length <= 0){
				for(var i = 1; i < 2; i++ ){
					appendTemplate += template;
				}
				appendTemplate += templAddButton;
				$container.append(appendTemplate);
				$container.dialog("option", "title", 'Price Range');
				$container.dialog('open');
			}else{
				var priceRangeArray = priceRange.split(",");
				for(var i = 0; i < priceRangeArray.length/2; i++ ){
					appendTemplate += template;
				}

				appendTemplate += templAddButton;
				$container.append(appendTemplate);
				$container.dialog("option", "title", 'Price Range');
				$container.dialog('open');

				var k = 0;
				jQuery('#wpfSetupPriceRangeByHand input').each(function(){
					var input = jQuery(this);
					if(k < priceRangeArray.length){
						input.val(priceRangeArray[k]);
						k++;
					}else{
						input.closest('.wpfRangeByHand').remove();
					}
				});
			}

		});

		jQuery('body').off('click', '.wpfAddPriceRange').on('click', '.wpfAddPriceRange', function (e) {
			e.preventDefault();
			var templates = jQuery('.wpfRangeByHandTemplate').clone().html();
			jQuery(templates).insertBefore('.wpfRangeByHandAddButton');
			sortablePrice();
		});

		jQuery('body').off('click', '.wpfRangeByHandRemove').on('click', '.wpfRangeByHandRemove', function(e){
			e.preventDefault();
			var _this = jQuery(this);
			_this.closest('.wpfRangeByHand').remove();
		});

		//make properties sortable
		function sortablePrice(){
			jQuery("#wpfSetupPriceRangeByHand").sortable({
				//containment: "parent",
				cursor: "move",
				axis: "y",
				handle: ".wpfRangeByHandHandler"
			});
		}
		sortablePrice();

	});

	AdminPage.prototype.initColorPicker = (function (colorResult) {
		colorResult.wpColorPicker({
			hide: true,
			defaultColor: false,
			width: 200,
			border: false,
			change: function(event, ui) {
				var color = ui.color.toString(),
					wrapper = jQuery(event.target).closest('.woobewoo-color-picker'),
					result = wrapper.find('.woobewoo-color-result-text');

				if(result.val() != color) result.val(color).trigger('color-change');
				wrapper.find('.button').css('color', color);
			}
		});
	});

	AdminPage.prototype.eventsAdminPage = (function () {
		var _thisObj = this.$obj,
			$mainTabsContent = jQuery('.row-tab'),
			$mainTabs = jQuery('.wpfSub.tabs-wrapper.wpfMainTabs .button'),
			$currentTab = $mainTabs.filter('.current').attr('href'),
			$form = jQuery('#wpfFiltersEditForm');

		$mainTabsContent.filter($currentTab).addClass('active');

		$mainTabs.off('click').on('click', function (e) {
			e.preventDefault();
			var $this = jQuery(this),
				$curTab = $this.attr('href');

			$mainTabsContent.removeClass('active');
			$mainTabs.filter('.current').removeClass('current');
			$this.addClass('current');

			var $curTabContent = $mainTabsContent.filter($curTab);
			$curTabContent.addClass('active');
			activateSubTab($curTabContent);
		});

		jQuery('.sub-tab a').off('click').on('click', function (e) {
			e.preventDefault();
			var $this = jQuery(this),
				$mainTab = $(this).closest('.row-tab');
			$mainTab.find('.sub-tab a').addClass('disabled');
			$this.removeClass('disabled');
			activateSubTab($mainTab);
		});

		function activateSubTab($tab) {
			var $subTabs = $tab.find('.sub-tab a');

			if($subTabs.length) {
				var $curSubTab = $subTabs.filter(':not(.disabled)'),
					$subTabsContent = $tab.find('.sub-tab-content');
				$subTabsContent.removeClass('active');
				if($curSubTab.length != 1) {
					$subTabs.addClass('disabled');
					$curSubTab = $subTabs.get(0).removeClass('disabled');
				}
				$subTabsContent.filter($curSubTab.attr('href')).addClass('active');
			}
		}

		_thisObj.initColorPicker(jQuery('.row-tab:not(#row-tab-filters) .woobewoo-color-result'));

		jQuery('.wpfFiltersTabContents').off('change', '.woobewoo-color-result-text').on('change', '.woobewoo-color-result-text', function() {
			var $this = jQuery(this),
				color = $this.val(),
				$picker = $this.closest('.woobewoo-color-picker');
			if(color == '')	$picker.find('.wp-picker-clear').trigger('click');
			else $picker.find('.woobewoo-color-result').wpColorPicker('color', $this.val());
		});

		$form.find('input[name="settings[filter_loader_icon_color]"]').off('color-change').on('color-change', function(e){
			jQuery('.woobewoo-filter-loader').css({color:jQuery(this).val()});
		});


		jQuery('.chooseLoaderIcon').off('click').on('click', function(e){
			e.preventDefault();
			if(typeof(_thisObj.chooseIconPopup) == 'function') {
				_thisObj.chooseIconPopup();
			}
		});

		$form.off('submit').on('submit', function (e) {
			e.preventDefault();
			_thisObj.saveFilters();
			var _this = jQuery(this);

			setTimeout(function() {
				_this.sendFormWpf({
					btn: jQuery('#buttonSave'),
					data: _this.serializeAnythingWpf(), 
					appendData: {wpfNonce: window.wpfNonce},
					onSuccess: function (res) {
						var currentUrl = window.location.href;
						if (!res.error && res.data.edit_link && currentUrl !== res.data.edit_link && !_thisObj.isElementorEditMode) {
							toeRedirect(res.data.edit_link);
						} else if(!res.error && res['messages'][0]) {
							jQuery.sNotify({
								'icon': 'fa fa-check',
								'content': ' <span> '+res['messages'][0]+'</span>',
								'delay' : 2500
							});
						}
					}
				});
			}, 200);

			return false;

		});

		jQuery('body').off('click', '#buttonDelete').on('click', '#buttonDelete', function (e) {
			e.preventDefault();
			var deleteForm = confirm("Are you sure you want to delete filter?");
			if (deleteForm) {
				var id = jQuery('#wpfFiltersEditForm').attr('data-table-id');

				if (id) {
					var data = {
						mod: 'woofilters',
						action: 'deleteByID',
						id: id,
						pl: 'wpf',
						reqType: "ajax",
						wpfNonce: window.wpfNonce
					};
					jQuery.ajax({
						url: url,
						data: data,
						type: 'POST',
						success: function (res) {
							var redirectUrl = jQuery('#wpfFiltersEditForm').attr('data-href');
							if (!res.error) {
								toeRedirect(redirectUrl);
							}
						}
					});
				}
			} else {
				return false;
			}
			return false;


		});

		// Work with shortcode copy text
		jQuery('#wpfCopyTextCodeExamples').on('change', function (e) {
			var optName = jQuery(this).val();
			switch (optName) {
				case 'shortcode' :
					jQuery('.wpfCopyTextCodeShowBlock').addClass('wpfHidden');
					jQuery('.wpfCopyTextCodeShowBlock.shortcode').removeClass('wpfHidden');
					break;
				case 'phpcode' :
					jQuery('.wpfCopyTextCodeShowBlock').addClass('wpfHidden');
					jQuery('.wpfCopyTextCodeShowBlock.phpcode').removeClass('wpfHidden');
					break;
				case 'shortcode_product' :
					jQuery('.wpfCopyTextCodeShowBlock').addClass('wpfHidden');
					jQuery('.wpfCopyTextCodeShowBlock.shortcode_product').removeClass('wpfHidden');
					break;
				case 'phpcode_product' :
					jQuery('.wpfCopyTextCodeShowBlock').addClass('wpfHidden');
					jQuery('.wpfCopyTextCodeShowBlock.phpcode_product').removeClass('wpfHidden');
					break;
			}
		});

		//-- Work with title --//
		$('#wpfFilterTitleShell').on('click', function(){
			$('#wpfFilterTitleLabel').addClass('wpfHidden');
			$('#wpfFilterTitleTxt').removeClass('wpfHidden');
		});

		$('#wpfFilterTitleTxt').on('focusout', function(){
			var filterTitle = $(this).val();
			$('#wpfFilterTitleLabel').text(filterTitle);
			$('#wpfFilterTitleTxt').addClass('wpfHidden');
			$('#wpfFilterTitleLabel').removeClass('wpfHidden');
			$('#buttonSave').trigger('click');
		});
		//-- Work with title --//

		jQuery('body').off('focus', '.wpfFilter div > input').on('focus', '.wpfFilter div > input', function() {
			if( typeof jQuery(this).attr('placeholder') !== 'undefined' && jQuery(this).attr('placeholder').length > 0){
				jQuery(this).attr('data-placeholder', jQuery(this).attr('placeholder') );
				jQuery(this).attr('placeholder', '');
			}
		});
		jQuery('body').off('blur', '.wpfFilter div > input').on('blur', '.wpfFilter div > input', function() {
			jQuery(this).attr('placeholder', jQuery(this).attr('data-placeholder'));
		});

		var settingsValues = jQuery('.wpfFiltersTabContents');

		settingsValues.off('change wpf-change', 'input[type="checkbox"],select').on('change wpf-change', 'input[type="checkbox"],select', function () {
			var elem = jQuery(this),
				valueWrapper = elem.closest('.settings-value'),
				name = elem.attr('name'),
				filterBlock = elem.closest('.wpfFilter'),
				block = filterBlock.length ? filterBlock : settingsValues,
				childrens = block.find('.row-settings-block[data-parent="' + name + '"], .settings-value[data-parent="' + name + '"]');
			if(childrens.length > 0) {
				childrens.each(function(){
					var child = jQuery(this),
						values = child.data('values'),
						noValues = child.data('no-values');
					values = typeof values !== 'undefined' ? values.split(' ') : [];
					noValues = typeof noValues !== 'undefined' ? noValues.split(' ') : [];
					if (elem.prop('nodeName').toLowerCase() == 'select') {
						if (('' == values || toeInArrayWpf(elem.val(), values)) && ('' == noValues || !toeInArrayWpf(elem.val(), noValues))) {
							child.removeClass('wpfHidden');
						} else {
							child.addClass('wpfHidden');
						}
					} else {
						if ((elem.is(':checked')) && (valueWrapper.length == 0 || !valueWrapper.hasClass('wpfHidden'))) child.removeClass('wpfHidden');
						else child.addClass('wpfHidden');
					}
				});
				childrens.find('select,input[type="checkbox"]').trigger('wpf-change');
			}
			// for many parents
			childrens = block.find('.row-settings-block[data-parents*="' + name + '"], .settings-value[data-parents="' + name + '"]');
			if(childrens.length > 0){
				childrens.each(function(){
					var child = jQuery(this),
						values = child.data('values'),
						noValue = child.data('no-value'),
						parentsNames = child.data('parents')
					if (elem.prop('nodeName').toLowerCase() == 'select') {
						var jq_selector = parentsNames // 'settings[display_hide_button] settings[display_hide_button_mobile]'
								.split(' ')
								.map(el_name => `select[name="${el_name}"]>option[value="${noValue}"]:checked`)
								.join(',');
						var all_no = jQuery(jq_selector).length === parentsNames.split(' ').length
						if (all_no) {
							child.addClass('wpfHidden');
						} else {
							child.removeClass('wpfHidden');
						}
					} else {
						// here should be code for checkboxes and radio
					}
				});
				childrens.find('select,input[type="checkbox"]').trigger('wpf-change');
			}
		});
		settingsValues.off('change wpf-change', 'select').on('change wpf-change', 'select', function () {
			var elem = jQuery(this),
				value = elem.val(),
				hidden = elem.closest('.settings-value').hasClass('wpfHidden') || ( elem.closest('.wpfHidden', '.sub-tab-content').length && !elem.closest('.wpfHidden', '.sub-tab-content').is('.wpfOptions')),
				name = elem.attr('name'),
				filterBlock = elem.closest('.wpfFilter'),
				block = filterBlock.length ? filterBlock : settingsValues,
				//subOptions = block.find('.row-settings-block[data-select="' + name + '"], .settings-value[data-select="' + name + '"]');
				subOptions = block.find('[data-select="' + name + '"]');
			if(subOptions.length) {
				subOptions.addClass('wpfHidden');
				if(!hidden) {
					subOptions.filter('[data-select-value*="'+value+'"]').removeClass('wpfHidden');
					subOptions.filter('[data-not-value][data-not-value!="'+value+'"]').removeClass('wpfHidden');
				}
			}
		});

		if (_thisObj.isElementorEditMode) {
			settingsValues.find('input[type="checkbox"],select').trigger('wpf-change');
		}

		// remove unnecessary levels for brands
		jQuery('div[data-filter="wpfBrand"] div[data-parent="f_custom_tags"]').each(function () {
			var name  = jQuery('select', this).attr('name');
			let level = /.*?(\d+)]$/.exec(name);
			if (level !== null && level[1] > 1) {
				jQuery(this).hide();
			}
		});
		
		$(document).keydown(function(e) {
			if (e.keyCode == 65 && e.ctrlKey) {
				var $multiBlock = $('.wpfFiltersBlock .wpfFilter .wpfOptions:not(.wpfHidden) .wpf-multi-select');
				if ($multiBlock.length == 1 && $multiBlock.find('.chosen-container-multi').hasClass('chosen-container-active')) {
					var ctrlAttr = $multiBlock.attr('data-ctrl-a') != '1',
						$select = $multiBlock.find('select');
					$select.find('option').prop('selected', ctrlAttr);
					$select.trigger('chosen:updated');
					$multiBlock.attr('data-ctrl-a', ctrlAttr ? '1' : '0');
				}
			}
		});

	});

	AdminPage.prototype.getPreviewAjax = (function (wait) {
		var _this = this.$obj;
		if(_this.wpfWaitLoad) return;
		if(_this.isElementorEditMode) return;

		if(_this.wpfWaitResponse) {
			if(!_this.wpfNeedPreview || wait) {
				_this.wpfNeedPreview = true;
				setTimeout(function() {	_this.getPreviewAjax(true); }, 2000);
			}
			return;
		}
		_this.wpfWaitResponse = true;
		_this.wpfNeedPreview = false;

		var $form = jQuery('#wpfFiltersEditForm');
		if (!$form || $form.length == 0) return;
		
		_this.saveFilters();

		$form.sendFormWpf({
			data: $form.serializeAnythingWpf()
		,	appendData: {mod: 'woofilters', action: 'drawFilterAjax'}
		,	onSuccess: function(res) {
				if(!res.error) {
					var container = jQuery('.wpfFiltersBlockPreview');
					container.html(res.html);
					container.find("input").attr("name",'');
					container.find("select").attr("name",'');
					container.find("input[type=number]").attr("type",'');
					container.find("select").attr("type",'');
					container.find('.wpfFilterWrapper').css({
						visibility: 'visible'
					});
					container.css({visibility: container.find('.wpfFilterWrapper').length ? 'visible' : 'hidden'});
				}
				_this.wpfWaitResponse = false;
			},
		});

	});

	AdminPage.prototype.eventsFilters = (function () {
		var _this = this.$obj,
			_noOptionsFilters = this.$noOptionsFilters,
			wpfGetPreviewInit = false;

		jQuery('.wpfMainWrapper').find('select[multiple]').multiselect({
			columns: 1,
			placeholder: 'Select options'
		});

		jQuery('document').ready(function(){
			jQuery(".chosen-choices").sortable();
			jQuery('.color-group').trigger('color-group');
		});
		if (WPF_DATA.isWCLicense) {
			jQuery('.wpfProLabel a').attr('href','');
		}


		jQuery("body").off('change', "[name='f_show_inputs']").on('change', "[name='f_show_inputs']", function (e) {
			e.preventDefault();
			if(jQuery(this).prop('checked')) {
				if (jQuery("[name='f_skin_type']").val() == 'default') {
					jQuery(".f_show_inputs_enabled_tooltip").show();
				} else {
					jQuery(".f_show_inputs_enabled_tooltip").hide();
				}
				jQuery(".f_show_inputs_enabled_position").show();
				jQuery(".f_show_inputs_enabled_currency").show();
			} else {
				var curSlider = jQuery('[name="f_price_show_currency_slider"]');
				jQuery(".f_show_inputs_enabled_tooltip").hide();
				jQuery(".f_show_inputs_enabled_position").hide();
				jQuery(".f_show_inputs_enabled_currency").hide();
				if (curSlider.length == 0 || !curSlider.prop('checked')) {
					jQuery("[name='f_currency_position']").val('before');
					jQuery("[name='f_currency_show_as']").val('symbol');
				}
				jQuery("[name='f_price_tooltip_show_as']").prop("checked",false);
				jQuery("[name='f_price_tooltip_show_as']").attr("checked",false);
			}
		});

		jQuery("body").off('change', "[name='f_skin_type']").on('change', "[name='f_skin_type']", function (e) {
			e.preventDefault();
			if(jQuery(this).val() == 'default') {
				if ( jQuery("[name='f_show_inputs']").prop("checked") ) {
					jQuery(".f_show_inputs_enabled_tooltip").show();
				} else {
					jQuery(".f_show_inputs_enabled_tooltip").hide();
				}
			} else {
				jQuery(".f_show_inputs_enabled_tooltip").hide();
				jQuery("[name='f_price_tooltip_show_as']").prop("checked",false);
				jQuery("[name='f_price_tooltip_show_as']").attr("checked",false);
			}
		});

		jQuery('body').off('change wpf-update', '.wpfFiltersTabContents select, .wpfFiltersTabContents input').on('change wpf-update', '.wpfFiltersTabContents select, .wpfFiltersTabContents input', function(e) {
			if(jQuery(this).closest('div[data-no-preview="1"]').length == 0) {
			   _this.getPreviewAjax();
			}
		});

		jQuery("body").off("change", '.wpfFiltersTabContents [name="f_hidden_attributes"]').on("change", '.wpfFiltersTabContents [name="f_hidden_attributes"]', function(e) {
			if(! jQuery(this).closest(".wpfFiltersBlockPreview").length ) {
				_this.getPreviewAjax();
			}
		});

		jQuery('#wpfFiltersEditForm select[name="f_mlist[]"]').off('chosen:updated').on('chosen:updated',function() {
			if(! jQuery(this).closest(".wpfFiltersBlockPreview").length ) {
				_this.getPreviewAjax();
			}
		});

		jQuery("body").off("change", '#wpfFiltersEditForm [name="f_hide_taxonomy"]').on("change", '#wpfFiltersEditForm [name="f_hide_taxonomy"]', function(e) {
			var mList = jQuery(this).closest('table').find('select[name="f_mlist[]"]'),
				parentCats = mList.data('parents');

			mList.find('option').show();
			if (jQuery(this).is(':checked')){
				mList.find('option').each(function(){
					var optVal = jQuery(this).val();
					if(toeInArray(optVal, parentCats) == -1){
						jQuery(this).hide();
					}
				});
			}
			mList.trigger("chosen:updated");
		});

		jQuery('#wpfChooseFilters').off('change').on('change', function(){
			var option = jQuery('#wpfChooseFilters option:selected'),
				variants = jQuery('#wpfChooseFiltersBlock [data-option]').addClass('wpfHidden');
			variants.filter('[data-option="'+option.attr('data-available')+'"]').removeClass('wpfHidden');
		});

		function resetEnabledFilters() {
			var filterSelect = jQuery('#wpfChooseFilters'),
				filtersBlock = jQuery('.wpfFiltersBlock');
			filterSelect.find('option').each(function(){
				var option = jQuery(this),
					data = 'add';
				if (option.attr('data-enabled') != '1') data = 'pro';
				else if (option.attr('data-unique') == 1) {
					if(filtersBlock.find('.wpfFilter[data-filter="'+option.attr('value')+'"]').length) data = 'uniq';
					else {
						var group = option.attr('data-group');
						if(group && group.length && filtersBlock.find('.wpfFilter[data-filter="'+group+'"]').length) data = 'group';
					}
				}
				option.attr('data-available', data);
			});
			var firstEnabled = filterSelect.find('[data-available="add"]');
			if(firstEnabled.length) firstEnabled.first().prop('selected', true);
			filterSelect.trigger('change');

			if(filtersBlock.find('.wpfFilter').length) filtersBlock.removeClass('wpfHidden');
			else filtersBlock.addClass('wpfHidden');

			filtersBlock.find('.wpfDependencyHidden').each(function() {
				let $this = jQuery(this),
					name  = $this.data('parent-switch');
				if (!$this.closest('.wpfFilter').find('[name="' + name + '"]').prop('checked')) {
					$this.addClass('wpfHidden');
				}
			});
		}

		jQuery('#wpfAddFilterButton').off('click').on('click', function(e){
			e.preventDefault();
			var option = jQuery('#wpfChooseFilters option:selected');
			if(option.length == 0 || option.attr('data-enabled') != '1' || option.attr('data-available') != 'add') return;

			_this.wpfAddFilter(option.attr('value'));
			resetEnabledFilters();
			_this.getPreviewAjax();
		});

		//remove existing filter
		jQuery('.wpfFiltersBlock').off('click', '.wpfFilter a.wpfDelete').on('click', '.wpfFilter a.wpfDelete', function(e){
			e.preventDefault();
			jQuery(this).closest('.wpfFilter').remove();
			resetEnabledFilters();
			_this.getPreviewAjax();
		});

		//show / hide filter options
		jQuery('.wpfFiltersBlock').off('click', '.wpfFilter a.wpfToggle').on('click', '.wpfFilter a.wpfToggle', function(e){
			e.preventDefault();
			var el = jQuery(this),
				i = el.find('i'),
				options = el.closest('.wpfFilter').find('.wpfOptions');

			if (i.hasClass('fa-chevron-down')){
				var elOther = jQuery('.wpfFilter a.wpfToggle i.fa-chevron-up'),
					optionsOther = elOther.closest('.wpfFilter').find('.wpfOptions');
				elOther.removeClass('fa-chevron-up').addClass('fa-chevron-down');
				optionsOther.addClass('wpfHidden');
				i.removeClass('fa-chevron-down').addClass('fa-chevron-up');
				options.removeClass('wpfHidden');
				options.find('select[name="f_mlist[]"]').trigger('chosen:updated');
			}else{
				i.removeClass('fa-chevron-up').addClass('fa-chevron-down');
				options.addClass('wpfHidden');
			}
		});

		//make properties sortable
		var startFilterPosition = null;
		jQuery('.wpfFiltersBlock').sortable({
			cursor: "move",
			axis: "y",
			placeholder: "sortable-placeholder",
			stop: function (e, ui) {
				if(ui.item.index() != startFilterPosition) {
					_this.getPreviewAjax();
				}
			},
			start: function (e, ui) {
				startFilterPosition = ui.item.index();
			},
		});

		jQuery(document).off('change wpf-dependent', '.wpfFiltersBlock input.toggle, #row-tab-options input.toggle').on('change wpf-dependent', '.wpfFiltersBlock input.toggle, #row-tab-options input.toggle', function() {
			var parent   = jQuery(this).closest('.wpfFilter, #row-tab-options'),
				name     = jQuery(this).attr('name'),
				isActive = jQuery(this).is(':checked'),
				elem     = parent.find('[data-parent-switch~="'+name+'"]');
			if (elem.length) {
				if (isActive) {
					if (elem.hasClass('wpfDependencyHidden')) {
						var hide = false;
						if (elem.closest('.wpfAddTypeControl').length) {
							var $blockType = elem.closest('.wpfFilterOptions').find('select[name="f_frontend_type"]');
							if ($blockType.length && !elem.closest('.wpfAddTypeControl').is('[data-type~="'+$blockType.val()+'"]')) hide = true;
						}
						if (!hide) elem.removeClass('wpfHidden');
					} else {
						elem.each(function() {
							var el = jQuery(this);
							el.css('display', (el.hasClass('settings-value') ? 'flex' : 'block')); 

						});
					}
				} else {
					//uncheck dependent checkboxes and runs trigger
					let dependentCheckboxes = elem.find('input[type="checkbox"]');
					if (dependentCheckboxes.length>0) {
						dependentCheckboxes.each(function() {
							jQuery(this).prop('checked', false).trigger('wpf-dependent');
						});
					}

					if (elem.hasClass('wpfDependencyHidden')) {
						elem.addClass('wpfHidden');
					} else {
						elem.hide();
					}
				}
			}
		});

		// resets the checkboxes if the value 0 is selected
		jQuery('.wpfFiltersTabContents').off('change', '.wpfTypeSwitchable select').on('change', '.wpfTypeSwitchable select', function (e) {
			var el = $(this),
				name = el.attr('name');
			parent = el.closest('.wpfTypeSwitchable');
			if (el.val() === "0") {
				parent.find('div[data-parent-switch="' + name + '"] input[type="checkbox"]').prop('checked', false);
			}
		});

		jQuery('.wpfFiltersBlock').off('change wpf-change', 'select[name="f_frontend_type"]').on('change wpf-change', 'select[name="f_frontend_type"]', function(e){
			e.preventDefault();
			var el = $(this),
				value = el.val(),
				filter = el.closest('.wpfFilter');

			filter.find('.wpfTypeSwitchable').addClass('wpfHidden');


			filter.find('.wpfTypeSwitchable[data-type~="'+value+'"]').each(function() {
				//if there is a checkbox on which the field depends, then first check it
				if (jQuery(this).hasClass('wpfDependencyHidden')) {
					let parentSwitch = jQuery(this).data('parent-switch');
					if (filter.find('input[name=' + parentSwitch + ']').prop('checked')) {
						$(this).removeClass('wpfHidden');
					}
				} else {
					if (!$(this).is('[data-parent]') || $(this).hasClass('dataParentIgnore') || value !== $(this).data('no-values')) {
						$(this).removeClass('wpfHidden');
					}
				}
			});

			filter.find('.wpfTypeSwitchable[data-not-type]:not([data-not-type~="'+value+'"])').removeClass('wpfHidden');

			var isParentActive = filter.find('input[name="f_show_hierarchical"]').is(':checked');
			if (!isParentActive) {
				filter.find('[data-parent-switch="f_show_hierarchical"]').hide();
			}

			if(el.hasClass('wpfWithProAd')) {
				filter.find('.wpfFilterTypePro').addClass('wpfHidden');
				filter.find('.wpfFilterTypePro[data-type="'+value+'"]').removeClass('wpfHidden');
			}

			if (value==='colors') {
				jQuery('.color-group', filter).trigger('color-group');
			}
		});

		//after load page display filters tab
		if (!_this.isElementorEditMode) displayFiltersTab();
		else resetEnabledFilters();

		function displayFiltersTab(){
			jQuery('.wpfFiltersBlock').html('');
			try{
				var filters = JSON.parse(jQuery('input[name="settings[filters][order]"]').val()),
					cntFilters = filters.length;
				_this.filtersSettings = filters;
			}catch(e){
				var filters = [];
			}
			_this.filterIterator = 0;

			filters.forEach(function (value) {
				var settings = value.settings;
				if (typeof settings == 'undefined' || !settings['f_enable']) return true;

				_this.wpfAddFilter(value.id, value.uniqId, settings);
			});
			resetEnabledFilters();
		}

		jQuery("body").off('change', 'select[name="f_list"]').on('change', 'select[name="f_list"]', function (e) {
			e.preventDefault();
			_this.fListChanged(jQuery(this), 'change');
		});

		jQuery('.wpfFiltersBlock').off('change', '.wpfAutomaticOrByHand input[type="checkbox"]').on('change', '.wpfAutomaticOrByHand input[type="checkbox"]', function(){
			var _this = jQuery(this),
				checked = _this.prop('checked');

			jQuery('.wpfAutomaticOrByHand').not('[data-value="'+_this.closest('.wpfAutomaticOrByHand').attr('data-value')+'"]').find('input').prop('checked', !checked);
			jQuery('.wpfAutomaticOrByHand input[type="checkbox"]').trigger('wpf-change');
		});

	});

	AdminPage.prototype.setAttrTerms = (function(mlist, slug){
		var _this = this,
			options = jQuery('.wpfAttributesTerms input[name="attr-'+slug+'"]');
		if(typeof(options) == 'undefined' || options.length == 0) return;

		try {
			var terms = JSON.parse(options.val()),
				keys = JSON.parse(options.attr('data-order'));
		}catch(e){
			var terms = [],
				keys = [];
		}

		var filterId = mlist.closest('.wpfFilter').attr('id'),
			settings = [],
			name = mlist.attr('name');
		if(filterId) {
			var filterNum = filterId.replace('wpfFilter', '');
			settings = (_this.filtersSettings && filterNum in _this.filtersSettings ? _this.filtersSettings[filterNum]['settings'] : []);
		}

		keys.forEach(function (value) {
			if(value in terms) {
				mlist.append('<option value="'+value+'">'+terms[value]+'</option>');
			}
		});
		var selectedArr = settings[name] && settings[name] ? settings[name].split(',') : [];
		jQuery.each(selectedArr, function (i, e) {
			var option = mlist.find("option[value='" + e + "']");
			if(option.length) {
				mlist.append(option.prop('selected', true));
			}
		});

		if(mlist.find('option').length > 1) {
			mlist.closest('.row-settings-block').removeClass('wpfHidden');
		}
		mlist.trigger('chosen:updated');
		mlist.trigger('change');
		if(typeof(_this.changeAttributeTermsPro) == 'function') {
			_this.changeAttributeTermsPro(mlist.closest('.wpfFilter'), settings);
		}
		_this.getPreviewAjax();
	});

	AdminPage.prototype.fListChanged = (function(_this, event){
		var _thisObj = this,
			attrSlug = _this.val(),
			changedName = attrSlug == 0 ? '' : ' - ' + _this.find('option:selected').text(),
			startName = _this.closest('.wpfFilter').attr('data-title'),
			fullTitle = startName + changedName;
		_this.closest('.wpfFilter').find('.wpfFilterTitle').text(fullTitle);

		var attr_terms = _this.closest('.wpfOptions').find('[name="f_mlist[]"]');
		attr_terms.closest('tr').addClass('wpfHidden');
		attr_terms.find('option').remove();

		if(attrSlug != 0) {
			var terms = jQuery('.wpfAttributesTerms input[name="attr-'+attrSlug+'"]');
			if(typeof(terms) == 'undefined' || terms.length == 0) {
				jQuery.sendFormWpf({
					data: {
						mod: 'woofilters',
						action: 'getTaxonomyTerms',
						slug: attrSlug
					},
					onSuccess: function(res) {
						if(!res.error && res.data.terms && res.data.keys) {
							jQuery('.wpfAttributesTerms').append('<input type="hidden" name="attr-'+attrSlug+'" data-order="'+res.data.keys+'" value="'+res.data.terms+'">');
							_thisObj.setAttrTerms(attr_terms, attrSlug);
						}
					}
				});
			} else {
				_thisObj.setAttrTerms(attr_terms, attrSlug);
			}
		} else {
			attr_terms.val('').trigger('chosen:updated');
			attr_terms.trigger('change');
		}

		// temporarily disabled for custom meta field
		var	frontendType = _this.closest('.wpfFilter').find('select[name="f_frontend_type"]');
		if (frontendType.val() === 'colors') {
			jQuery('.color-group', _this.closest('.wpfFilter')).trigger('color-group');
		}
		if (attrSlug === 'custom_meta_field_check' ) {
			if (event === 'change') {
				frontendType.val(frontendType.find('option:first').val());
			}
			frontendType.find('option[value="colors"]').addClass('wpfHidden');
		} else {
			frontendType.find('option[value="colors"]').removeClass('wpfHidden');
		}
	});

	AdminPage.prototype.wpfAddFilter = (function(id, uniqId, settings) {
		var _this = this,
			_noOptionsFilters = this.$noOptionsFilters,
			wpfGetPreviewInit = false,
			template = jQuery('.wpfOptionsTemplate .wpfFilterOptions[data-filter="'+id+'"]');
		if(template.length == 0) return true;

		_this.wpfWaitLoad = true;
		var optionsTemplate = template.clone(),
			text = optionsTemplate.find('input[name=f_name]').val();

		template.find('[id]').each(function() {
			var $this = jQuery(this);
			$this.attr('data-id', $this.attr('id'));
			$this.removeAttr('id');
		});
		optionsTemplate.find('[data-id]').each(function() {
			var $this = jQuery(this),
				tempId = $this.attr('data-id'),
				newId = 'f' + _this.filterIterator + '_' + tempId;
			$this.attr('id', newId);
			optionsTemplate.find('label[for="' + tempId + '"]').attr('for', newId);
			$this.removeAttr('data-id');
		});

		if(typeof settings !== 'undefined') {
			optionsTemplate.find('input, select').map(function (index, elm) {
				var name = elm.name,
					$elm = jQuery(elm);
				if (elm.type === 'checkbox') {
					if (elm.name === 'f_options[]') {
						$elm.prop("checked", false);
						if (settings[name]) {
							var checkedArr = settings[name].split(',');
							if (checkedArr.includes(elm.value)) {
								$elm.prop("checked", true);
							}
						}
					} else {
						$elm.prop("checked", settings[name]);
					}

				} else if (elm.type === 'select-multiple') {
					if (_this.$multiSelectFields.includes(elm.name)) {
						if (settings[name]) {
							var selectedArr = settings[name].split(',');
							jQuery.each(selectedArr, function (i, e) {
								var option = $elm.find("option[value='" + e + "']");
								option.remove();
								$elm.append(option);
								$elm.find("option[value='" + e + "']").prop("selected", true);
							});
						}
					}
				} else {
					if(typeof settings[name] !== 'undefined') {
						elm.value = settings[name];
					}
					if ($elm.hasClass('woobewoo-color-result-text')) {
						$elm.closest('.woobewoo-color-picker').find('.woobewoo-color-result').val(elm.value);
					}
				}
			});
		}

		var filterUniqId = uniqId ? uniqId : 'wpf_' + Math.random().toString(36).substr(2, 9),
			filterId = 'wpfFilter' + _this.filterIterator,
			blockTemplate = jQuery('.wpfTemplates .wpfFiltersBlockTemplate')
				.clone()
				.removeClass('wpfFiltersBlockTemplate')
				.attr('data-filter', id)
				.attr('data-title', text)
				.attr('data-uniq-id', filterUniqId)
				.attr('id', filterId),
			title = text;
		blockTemplate.find('.wpfOptions').html(optionsTemplate);
		if( id === 'wpfAttribute' ){
			title = blockTemplate.find('select[name="f_list"] option:selected').text();
			text = text + ' - ' + title;
			if (blockTemplate.find('select[name="f_list"]').val() != '0') {
				blockTemplate.find('select[name="f_mlist[]"]').closest('tr').removeClass('wpfHidden');
				_this.fListChanged( blockTemplate.find('select[name="f_list"]') );
			}
		}
		if(_noOptionsFilters.includes(id)){
			blockTemplate.find('.wpfToggle').css({'visibility':'hidden'});
		}
		blockTemplate.find('.wpfFilterTitle').text(text);
		if(typeof settings !== 'undefined'){
			blockTemplate.find('.wpfFilterFrontDescOpt input').val(settings['f_description']);
			blockTemplate.find('input[name="f_enable"]').prop('checked', true);
			if(typeof settings['f_title'] !== 'undefined' && settings['f_title'].length > 0) {
				title = settings['f_title'];
			}
		}
		blockTemplate.find('.wpfFilterFrontTitleOpt input').val(title);
		jQuery('.wpfFiltersBlock').append(blockTemplate);

		_this.filterIterator++;

		blockTemplate.trigger('changeTooltips');
		blockTemplate.find('select[name="f_mlist[]"]').chosen({ width:"95%" });

		blockTemplate.find('input,select').trigger('wpf-change');

		if(id == 'wpfPrice') {
			var defaultSlider = blockTemplate.find('#wpfSliderRange'),
				minValue = 200,
				maxValue = 600,
				minSelector = blockTemplate.find('#wpfMinPrice').val(minValue),
				maxSelector = blockTemplate.find('#wpfMaxPrice').val(maxValue);
			defaultSlider.slider({
				range: true,
				orientation: 'horizontal',
				min: 0,
				max: 1000,
				values: [minValue, maxValue],
				step: 1,
				slide: function (event, ui) {
					minSelector.val(ui.values[0]);
					maxSelector.val(ui.values[1]);
				}
			});
			blockTemplate.find('input[name="f_show_inputs"]').on('change', function(e){
				e.preventDefault();
				if($(this).prop('checked')) {
					blockTemplate.find('.wpfPriceInputs').show();
				} else {
					blockTemplate.find('.wpfPriceInputs').hide();
				}
			}).trigger('change');

			minSelector.on('change', function(e){
				e.preventDefault();
				defaultSlider.slider('values', 0, $(this).val());
			});
			maxSelector.on('change', function(e){
				e.preventDefault();
				defaultSlider.slider('values', 1, $(this).val());
			});
			blockTemplate.find('select[name="f_skin_type"].wpfWithProAd').on('change', function(e){
				e.preventDefault();
				var proLabel = $(this).closest('.row-settings-block').find('.wpfProLabel'),
					isPro = $('option:selected', this).text().match(/Pro$/);
				if (isPro) {
					proLabel.removeClass('wpfHidden');
				} else {
					proLabel.addClass('wpfHidden');
				}
				blockTemplate.find('.wpfPriceSkinPro').addClass('wpfHidden');
				blockTemplate.find('.wpfPriceSkinPro[data-type="'+$(this).val()+'"]').removeClass('wpfHidden');
			}).trigger('change');
			
		} else if(id == 'wpfSortBy') {
			jQuery('#wpfContainerSortBy a.js-wpfMove').on('click', function(e) {
				wpfSortBy_MoveRow(e, this);
			});
		} else if( id === 'wpfTags' ){
			if(typeof(_this.changeAttributeTermsPro) == 'function') {
				_this.changeAttributeTermsPro(blockTemplate);
			}
		}
		if(typeof(_this.eventsFiltersPro) == 'function') {
			_this.eventsFiltersPro(blockTemplate, settings);
		}
		_this.wpfWaitLoad = false;
	});

	AdminPage.prototype.saveFilters = (function () {
		var _this = this.$obj,
			filtersArr = [],
			i = 0,
			preselect = '',
			attrNames = jQuery('.wpfAttributesTerms input[name="attr_filternames"]').val();
		if(typeof(attrNames) == 'undefined' || attrNames.length == 0) return;
		try {
			attrNames = JSON.parse(attrNames);
		}catch(e){
			attrNames = [];
		}

		jQuery('.wpfFilter').not('.wpfFiltersBlockTemplate').each(function () {
			var valueToPush = {},
				filter = jQuery(this),
				id = 'wpfFilter'+i,
				items = {},
				title = filter.find('input[name="f_title"]'),
				filterId = filter.attr('data-filter'),
				filterUniqId = filter.attr('data-uniq-id'),
				filterName = jQuery('#wpfChooseFilters option[value="' + filterId + '"]').attr('data-filtername');

			filter.attr('id', id);

			if(title.val() == '') {
				title.val(filter.find('.wpfFilterTitle').text());
			}

			filter.find('input, select').map(function(index, elm) {
				var $elm = jQuery(elm),
					value = $elm.val();

				if(elm.type === 'checkbox'){
					//for multi checkbox
					if(elm.name === 'f_options[]'){
						if(elm.checked){
							if(typeof items[elm.name] !== 'undefined'){
								var temp = items[elm.name];
								temp = temp + ',' + $elm.val();
								items[elm.name] = temp;
							}else{
								items[elm.name] = $elm.val();
							}
						}
					}else{
						items[elm.name] = elm.checked;
					}
				}else if(elm.type === 'select-multiple'){
					var elmNameClear = elm.name.replace(/\[(\d+)\]/g, "[]");
					if( _this.$multiSelectFields.includes(elmNameClear) ){
						//add more filter for this type
						var arrayValues = typeof ChosenOrder !== 'undefined' && ChosenOrder.isChosenified(elm) ? $elm.getSelectionOrder() : $elm.val();
						//var arrayValues = $elm.getSelectionOrder();
						if(arrayValues){
							items[elm.name] = arrayValues.toString();
						}
					}
				}else if (value !== '' && elm.name) {
					items[elm.name] = $elm.val();
				}
			});
			if(filterId == 'wpfCategory') {
				if(!items['f_hide_taxonomy']) {
					var type = items['f_frontend_type'];
					if (type == 'multi' || type == 'buttons' || type == 'text') filterName += '_list';
				}
			}
			if(filterId == 'wpfAttribute') {
				if('f_list' in items && items['f_list'] in attrNames) {
					filterName = attrNames[items['f_list']];
				}
			}

			filter.find('input[data-preselect-flag="1"]').each(function(){
				var elm = this;
				if(elm.type === 'checkbox' && elm.checked) {
					var preValue = '';
					if('f_preselect' in items) {
						preValue = items['f_preselect'];
					} else if(filterName.length) {
						var mlist = typeof items['f_mlist[]'] != 'undefined' ? items['f_mlist[]'] : '';
						if(mlist.length) {
							switch (filterId) {
								case 'wpfCategory':
									preValue = filterName + (items['f_extend_parent_filtering'] ? '' : '_list') + '=' + (items['f_multi_logic'] == 'or' ? mlist.replace(/,/g, '|') : mlist);
									break;
								case 'wpfTags':
								case 'wpfAttribute':
									var fn = (items['f_query_logic'] == 'not_in' ? 'pr_'+filterName : filterName);
									preValue = fn + '=' + (items['f_query_logic'] == 'or' ? mlist.replace(/,/g, '|') : mlist);
									break;
							}
						} else {
							var exclude = typeof items['f_exclude_terms'] != 'undefined' ? items['f_exclude_terms'] : '';
							if(exclude.length) {
								preValue = filterName + '=!' + exclude;
							}
						}
					}
					if(preValue && preValue.length) {
						preselect += preValue + ';';
					}
					if(typeof(_this.addPreselectsPro) == 'function') preselect = _this.addPreselectsPro(preselect, items, filterId);
				}
			});

			valueToPush['id'] = filterId;
			valueToPush['uniqId'] = filterUniqId;
			valueToPush['name'] = filterName;
			valueToPush['settings'] = items;
			
			/*if (filterId == 'wpfSortBy') {
				var list = wpfGetOrderElementsSortBy();
				if (Object.keys(list).length > 0) {
					valueToPush['settings_sortby_order_elements'] = list;
				}
			}*/
			filtersArr.push(valueToPush);
			i++;
		});
		
		_this.filtersSettings = filtersArr;
		var filtersJson = JSON.stringify(filtersArr);
		jQuery('input[name="settings[filters][order]"]').val(filtersJson);
		jQuery('input[name="settings[filters][preselect]"]').val(preselect.length ? preselect.slice(0, -1) : '');
		if(typeof(_this.saveFiltersPro) == 'function') _this.saveFiltersPro();

	});
	jQuery(document).ready(function () {
		window.wpfAdminPage = new AdminPage();
		if(typeof(window.wpfAdminPagePro) == 'function') window.wpfAdminPagePro();
		window.wpfAdminPage.init();
	});
	
	function wpfGetOrderElementsSortBy()
	{
		var _container = document.getElementById('wpfContainerSortBy'), list = {};
		
		if(_container != undefined)
		{
			_container.querySelectorAll('input[type="text"].js-sortby-item').forEach((_item)=>
			{
				list[_item.getAttribute('data-name')] = _item.getAttribute('data-name');
			});
		}

		return list;
	}

	function wpfSortBy_MoveRow(e, obj)
	{
		if(e != undefined)
		{
			e.preventDefault();
		}

		var $row = jQuery(obj).closest('.js-wpf-row-move'), _container = $row[0].parentNode, _insertBeforeElement = null, $element = null, strLastClass = 'js-last-item';

		if(obj.classList.contains('wpfMoveUp'))
		{
			$element = $row.prev();
			_insertBeforeElement =($element.length == 0) ? null : $element[0];
		}
		else
		{
			$element = $row.next();
			($element.length > 0) ? $element = $element.next() : $row.addClass(strLastClass);

			_insertBeforeElement = ($element.length == 0 && $row.hasClass(strLastClass)) ? _container.children[0] : $element[0];
			($element.length == 0) ? $row.addClass(strLastClass) : '';
		}

		_container.insertBefore($row[0], _insertBeforeElement);
		($row.next().length > 0) ? $row.removeClass(strLastClass) : $row.addClass(strLastClass);
		$row = null, _container = null, _insertBeforeElement = null, $element = null;
	}
	
}(window.jQuery, window.woobewoo));
