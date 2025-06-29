"use strict";
jQuery(document).ready(function(){
	jQuery('#wpfSettingsSaveBtn').click(function(){
		jQuery('#wpfSettingsForm').submit();
		return false;
	});
	jQuery('#wpfSettingsForm').submit(function(){
		jQuery(this).sendFormWpf({
			btn: jQuery('#wpfSettingsSaveBtn'),
			appendData: {wpfNonce: window.wpfNonce},

			onSuccess: function(res) {
				if(res['messages'][0]) {
					jQuery.sNotify({
						'icon': 'fa fa-check',
						'content': ' <span> '+res['messages'][0]+'</span>',
						'delay' : 2500
					});
				}
			}
		});
		return false;
	});
	/*Connected options: some options need to be visible  only if in other options selected special value (e.g. if send engine SMTP - show SMTP options)*/
	var $connectOpts = jQuery('#wpfSettingsForm').find('[data-connect]');
	if($connectOpts && $connectOpts.length) {
		var $connectedTo = {};
		$connectOpts.each(function(){
			var connectToData = jQuery(this).data('connect').split(':')
			,	$connectTo = jQuery('#wpfSettingsForm').find('[name="opt_values['+ connectToData[ 0 ]+ ']"]')
			,	connected = $connectTo.data('connected');
			if(!connected) connected = {};
			if(!connected[ connectToData[1] ]) connected[ connectToData[1] ] = [];
			connected[ connectToData[1] ].push( this );
			$connectTo.data('connected', connected);
			if(!$connectTo.data('binded')) {
				$connectTo.change(function(){
					var connected = jQuery(this).data('connected')
					,	value = jQuery(this).val();
					if(connected) {
						for(var connectVal in connected) {
							if(connected[ connectVal ] && connected[ connectVal ].length) {
								var show = connectVal == value;
								for(var i = 0; i < connected[ connectVal ].length; i++) {
									show 
									? jQuery(connected[ connectVal ][ i ]).removeClass('woobewoo-hidden')
									: jQuery(connected[ connectVal ][ i ]).addClass('woobewoo-hidden');
								}
							}
						}
					}
				});
				$connectTo.data('binded', 1);
			}
			$connectedTo[ connectToData[ 0 ] ] = $connectTo;
		});
		for(var connectedName in $connectedTo) {
			$connectedTo[ connectedName ].change();
		}
	}

	jQuery('.chooseLoaderIcon').on('click', function(e){
		e.preventDefault();
		wpfChooseIconPopup();
	});
	jQuery('#wpfFormOptDetails_loader_icon').removeClass('wpfOptDetailsShell');

	jQuery('.woobewoo-panel .woobewoo-color-result').wpColorPicker({
		hide: true,
		defaultColor: false,
		width: 200,
		border: false,
		change: function(event, ui) {
			var color = ui.color.toString(),
				wrapper = jQuery(event.target).closest('.woobewoo-color-picker'),
				result = wrapper.find('.woobewoo-color-result-text');

				result.val(color);
				wrapper.find('.button').css('color', color);
				jQuery('.woobewoo-filter-loader').css({color:color});
			}
	});
	jQuery('.woobewoo-color-result-text').on('change', function() {
		var $this = jQuery(this);
		$this.closest('.woobewoo-color-picker').find('.woobewoo-color-result').wpColorPicker('color', $this.val());		
	}).trigger('change');

	function wpfChooseIconPopup() {
		var colorInput = jQuery('input[name="opt_values[loader_icon_color]"]');
		jQuery('body').on('click', '#chooseIconPopup .item-inner', function (e) {
			e.preventDefault();
			var el = jQuery(this)
			,	name = el.find('.preicon_img').attr('data-name')
			,	color = colorInput.val()
			,	countDiv = el.find('.preicon_img').attr('data-items');

			jQuery('input[name="opt_values[loader_icon]"]').val(name+'|'+countDiv);
			jQuery('.wpfIconPreview').html('');
			if (name === 'default') {
				jQuery('.wpfIconPreview').html('<div class="woobewoo-filter-loader" style="color: ' + color + ';"><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i></div>');
			} else {
				var htmlIcon = ' <div class="woobewoo-filter-loader la-' + name + ' la-2x" style="color: ' + color + ';">';
				for (var i = 0; i < countDiv; i++) {
					htmlIcon += '<div></div>';
				}
				htmlIcon += '</div>';
				jQuery('.wpfIconPreview').html(htmlIcon);
			}
			$container.empty();
			$container.dialog('close');
		});
		var $container = jQuery('<div id="chooseIconPopup" style="display: none;" title="" /></div>').dialog({
			modal: true
			, autoOpen: false
			, width: 900
			, height: 750
			, buttons: {
				OK: function () {
					$container.empty();
					$container.dialog('close');
				}
				, Cancel: function () {
					$container.empty();
					$container.dialog('close');
				}
			},
			create:function () {
				jQuery(this).closest(".ui-dialog").addClass('woobewoo-plugin');
				if (WPF_DATA.isWCLicense) jQuery(this).closest('.ui-dialog').find('.ui-dialog-buttonset button').addClass('button');
			}
		});

		var contentHtml = jQuery('.wpfLoaderIconTemplate').clone().removeClass('wpfHidden');
		contentHtml.find('div.preicon_img').css({'color':colorInput.val()});
		$container.append(contentHtml);

		var title = jQuery('.chooseLoaderIcon').text();
		$container.dialog("option", "title", title);
		$container.dialog('open');
		return false;
	}
	jQuery('#wpfFormOptDetails_start_indexing').show();

	jQuery('#wpfStartMetaIndexing').click(function(){
		jQuery.sendFormWpf({
			data: {
			mod: 'meta',
			action: 'doMetaIndexing',
			},
			btn: jQuery('#wpfStartMetaIndexing'),
			appendData: {wpfNonce: window.wpfNonce, inCron: jQuery('#wpfStartIndexingCron').is(':checked') ? 1 : 0},
			onSuccess: function(res) {
				if (!res.error && res['messages'] && res['messages'].length) {
					jQuery.sNotify({
						'icon': 'fa fa-check',
						'content': ' <span> '+res['messages'][0]+'</span>',
						'delay' : 2500
					});
				}
			}
		});
		return false;
	});
	jQuery('#wpfStartOptimizing').click(function(){
		jQuery.sendFormWpf({
			data: {
			mod: 'meta',
			action: 'doMetaOptimizing',
			},
			btn: jQuery('#wpfStartOptimizing'),
			appendData: {wpfNonce: window.wpfNonce},
			onSuccess: function(res) {
				if (!res.error && res['messages'] && res['messages'].length) {
					jQuery.sNotify({
						'icon': 'fa fa-check',
						'content': ' <span> '+res['messages'][0]+'</span>',
						'delay' : 2500
					});
				}
			}
		});
		return false;
	});
	jQuery('.wpf-gopro-switch input').on('change', function() {
		var $this = jQuery(this),
			$parent = $this.closest('.wpf-gopro-wrapper'),
			num = $this.is(':checked') ? 1 : 0;
		$parent.find('.wpf-gopro-text').removeClass('active').eq(num).addClass('active');
		$parent.find('.wpf-gopro-block-row').addClass('wpf-gopro-hidden').eq(num).removeClass('wpf-gopro-hidden');
	});
});
