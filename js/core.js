"use strict";
if(typeof(WPF_DATA) == 'undefined')
	var WPF_DATA = {};
if(isNumber(WPF_DATA.animationSpeed)) 
    WPF_DATA.animationSpeed = parseInt(WPF_DATA.animationSpeed);
else if(jQuery.inArray(WPF_DATA.animationSpeed, ['fast', 'slow']) == -1)
    WPF_DATA.animationSpeed = 'fast';
WPF_DATA.showSubscreenOnCenter = parseInt(WPF_DATA.showSubscreenOnCenter);
var sdLoaderImgWpf = '<img src="'+ WPF_DATA.loader+ '" />';
var g_wpfAnimationSpeed = 300;

jQuery.fn.showLoaderWpf = function() {
    return jQuery(this).html( sdLoaderImgWpf );
};
jQuery.fn.appendLoaderWpf = function() {
    jQuery(this).append( sdLoaderImgWpf );
};
jQuery.sendFormWpf = function(params) {
	// Any html element can be used here
	return jQuery('<br />').sendFormWpf(params);
};
/**
 * Send form or just data to server by ajax and route response
 * @param string params.fid form element ID, if empty - current element will be used
 * @param string params.msgElID element ID to store result messages, if empty - element with ID "msg" will be used. Can be "noMessages" to not use this feature
 * @param function params.onSuccess funstion to do after success receive response. Be advised - "success" means that ajax response will be success
 * @param array params.data data to send if You don't want to send Your form data, will be set instead of all form data
 * @param array params.appendData data to append to sending request. In contrast to params.data will not erase form data
 * @param string params.inputsWraper element ID for inputs wraper, will be used if it is not a form
 * @param string params.clearMsg clear msg element after receive data, if is number - will use it to set time for clearing, else - if true - will clear msg element after 5 seconds
 */
jQuery.fn.sendFormWpf = function(params) {
    var form = null;
    if(!params)
        params = {fid: false, msgElID: false, onSuccess: false};
    if(params.fid)
        form = jQuery('#'+ fid);
    else
        form = jQuery(this);
    
    /* This method can be used not only from form data sending, it can be used just to send some data and fill in response msg or errors*/
    var sentFromForm = (jQuery(form).tagName() == 'FORM');
    var data = new Array();
    if(params.data)
        data = params.data;
    else if(sentFromForm)
        data = jQuery(form).serialize();

    if(params.appendData) {
		var dataIsString = typeof(data) == 'string';
		var addStrData = [];
        for(var i in params.appendData) {
			if(dataIsString) {
				addStrData.push(i+ '='+ params.appendData[i]);
			} else
            data[i] = params.appendData[i];
        }
		if(dataIsString)
			data += '&'+ addStrData.join('&');
    }
    var msgEl = null;
    if(params.msgElID) {
        if(params.msgElID == 'noMessages')
            msgEl = false;
        else if(typeof(params.msgElID) == 'object')
           msgEl = params.msgElID;
       else
            msgEl = jQuery('#'+ params.msgElID);
    }
	if(typeof(params.inputsWraper) == 'string') {
		form = jQuery('#'+ params.inputsWraper);
		sentFromForm = true;
	}
	if(sentFromForm && form) {
        jQuery(form).find('.wpfInputError').removeClass('wpfInputError');
    }
	if(msgEl && !params.btn) {
		jQuery(msgEl)
			.removeClass('wpfSuccessMsg')
			.removeClass('wpfErrorMsg');
		if(!params.btn) {
			jQuery(msgEl).showLoaderWpf();
		}
	} 
	if(params.btn) {
		jQuery(params.btn).attr('disabled', 'disabled');
		// Font awesome usage
		params.btnIconElement = jQuery(params.btn).find('.fa').length ? jQuery(params.btn).find('.fa') : jQuery(params.btn);
		if(jQuery(params.btn).find('.fa').length) {
			params.btnIconElement
				.data('prev-class', params.btnIconElement.attr('class'))
				.attr('class', 'fa fa-spinner fa-spin');
		} else {
			params.btnIconElement.attr('data-prev-text', params.btnIconElement.html());
			params.btnIconElement.html('<i class="fa fa-spinner fa-spin"></i>');
		}
	}
    var url = '';
	if(typeof(params.url) != 'undefined')
		url = params.url;
    else if(typeof(ajaxurl) == 'undefined' || typeof(ajaxurl) !== 'string')
        url = WPF_DATA.ajaxurl;
    else
        url = ajaxurl;
    
    jQuery('.wpfErrorForField').hide(WPF_DATA.animationSpeed);
	var dataType = params.dataType ? params.dataType : 'json';
	// Set plugin orientation
	if(typeof(data) == 'string') {
		data += '&pl='+ WPF_DATA.WPF_CODE;
		data += '&reqType=ajax';
	} else {
		data['pl'] = WPF_DATA.WPF_CODE;
		data['reqType'] = 'ajax';
	}

    jQuery.ajax({
        url: url,
        data: data,
        type: 'POST',
        dataType: dataType,
        success: function(res) {
            toeProcessAjaxResponseWpf(res, msgEl, form, sentFromForm, params);
			if(params.clearMsg) {
				setTimeout(function(){
					if(msgEl)
						jQuery(msgEl).animateClear();
				}, typeof(params.clearMsg) == 'boolean' ? 5000 : params.clearMsg);
			}
        }
    });
};
/**
 * Hide content in element and then clear it
 */
jQuery.fn.animateClear = function() {
	var newContent = jQuery('<span>'+ jQuery(this).html()+ '</span>');
	jQuery(this).html( newContent );
	jQuery(newContent).hide(WPF_DATA.animationSpeed, function(){
		jQuery(newContent).remove();
	});
};
/**
 * Hide content in element and then remove it
 */
jQuery.fn.animateRemoveWpf = function(animationSpeed, onSuccess) {
	animationSpeed = animationSpeed == undefined ? WPF_DATA.animationSpeed : animationSpeed;
	jQuery(this).hide(animationSpeed, function(){
		jQuery(this).remove();
		if(typeof(onSuccess) === 'function')
			onSuccess();
	});
};
function toeProcessAjaxResponseWpf(res, msgEl, form, sentFromForm, params) {
    if(typeof(params) == 'undefined')
        params = {};
    if(typeof(msgEl) == 'string')
        msgEl = jQuery('#'+ msgEl);
    if(msgEl)
        jQuery(msgEl).html('');
	if(params.btn) {
		jQuery(params.btn).removeAttr('disabled');
		if(params.btnIconElement) {
			params.btnIconElement.attr('class', params.btnIconElement.data('prev-class'));
			var prevText = params.btnIconElement.attr('data-prev-text');
			if (prevText && prevText.length) params.btnIconElement.html(prevText);
		}
	}
    /*if(sentFromForm) {
        jQuery(form).find('*').removeClass('wpfInputError');
    }*/
    if(typeof(res) == 'object') {
        if(res.error) {
            if(msgEl) {
                jQuery(msgEl)
					.removeClass('wpfSuccessMsg')
					.addClass('wpfErrorMsg');
            }
			var errorsArr = [];
            for(var name in res.errors) {
                if(sentFromForm) {
					var inputError = jQuery(form).find('[name*="'+ name+ '"]');
                    inputError.addClass('wpfInputError');
					if(!inputError.data('keyup-error-remove-binded')) {
						inputError.keydown(function(){
							jQuery(this).removeClass('wpfInputError');
						}).data('keyup-error-remove-binded', 1);
					}
                }
                if(jQuery('.wpfErrorForField.toe_'+ nameToClassId(name)+ '').exists())
                    jQuery('.wpfErrorForField.toe_'+ nameToClassId(name)+ '').show().html(res.errors[name]);
                else if(msgEl)
                    jQuery(msgEl).append(res.errors[name]).append('<br />');
				else
					errorsArr.push( res.errors[name] );
            }
			if(errorsArr.length && params.btn && jQuery.fn.dialog && !msgEl && !params.noError) {
				jQuery('<div title="'+ toeLangWpf("Really small warning :)")+ '" />').html( errorsArr.join('<br />') ).appendTo('body').dialog({
					modal: true
				,	width: '500px'
				});
			}
        } else if(res.messages.length) {
            if(msgEl) {
                jQuery(msgEl)
					.removeClass('wpfErrorMsg')
					.addClass('wpfSuccessMsg');
                for(var i = 0; i < res.messages.length; i++) {
                    jQuery(msgEl).append(res.messages[i]).append('<br />');
                }
            }
        }
    }
    if(params.onSuccess && typeof(params.onSuccess) == 'function') {
        params.onSuccess(res);
    }
}

function getDialogElementWpf() {
	return jQuery('<div/>').appendTo(jQuery('body'));
}

function toeOptionWpf(key) {
	if(WPF_DATA.options && WPF_DATA.options[ key ])
		return WPF_DATA.options[ key ];
	return false;
}
function toeLangWpf(key) {
	if(WPF_DATA.siteLang && WPF_DATA.siteLang[key])
		return WPF_DATA.siteLang[key];
	return key;
}
function toePagesWpf(key) {
	if(typeof(WPF_DATA) != 'undefined' && WPF_DATA[key])
		return WPF_DATA[key];
	return false;;
}
/**
 * This function will help us not to hide desc right now, but wait - maybe user will want to select some text or click on some link in it.
 */
function toeOptTimeoutHideDescriptionWpf() {
	jQuery('#wpfOptDescription').removeAttr('toeFixTip');
	setTimeout(function(){
		if(!jQuery('#wpfOptDescription').attr('toeFixTip'))
			toeOptHideDescriptionWpf();
	}, 500);
}
/**
 * Show description for options
 */
function toeOptShowDescriptionWpf(description, x, y, moveToLeft) {
    if(typeof(description) != 'undefined' && description != '') {
        if(!jQuery('#wpfOptDescription').length) {
            jQuery('body').append('<div id="wpfOptDescription"></div>');
        }
		if(moveToLeft)
			jQuery('#wpfOptDescription').css('right', jQuery(window).width() - (x - 10));	// Show it on left side of target
		else
			jQuery('#wpfOptDescription').css('left', x + 10);
        jQuery('#wpfOptDescription').css('top', y);
        jQuery('#wpfOptDescription').show(200);
        jQuery('#wpfOptDescription').html(description);
    }
}
/**
 * Hide description for options
 */
function toeOptHideDescriptionWpf() {
	jQuery('#wpfOptDescription').removeAttr('toeFixTip');
    jQuery('#wpfOptDescription').hide(200);
}
function toeInArrayWpf(needle, haystack) {
	if(haystack) {
		for(var i in haystack) {
			if(haystack[i] == needle)
				return true;
		}
	}
	return false;
}
function toeShowDialogCustomized(element, options) {
	options = jQuery.extend({
		resizable: false
	,	width: 500
	,	height: 300
	,	closeOnEscape: true
	,	open: function(event, ui) {
			jQuery('.ui-dialog-titlebar').css({
				'background-color': '#222222'
			,	'background-image': 'none'
			,	'border': 'none'
			,	'margin': '0'
			,	'padding': '0'
			,	'border-radius': '0'
			,	'color': '#CFCFCF'
			,	'height': '27px'
			});
			jQuery('.ui-dialog-titlebar-close').css({
				'background': 'url("'+ WPF_DATA.cssPath+ 'img/tb-close.png") no-repeat scroll 0 0 transparent'
			,	'border': '0'
			,	'width': '15px'
			,	'height': '15px'
			,	'padding': '0'
			,	'border-radius': '0'
			,	'margin': '7px 7px 0'
			}).html('');
			jQuery('.ui-dialog').css({
				'border-radius': '3px'
			,	'background-color': '#FFFFFF'
			,	'background-image': 'none'
			,	'padding': '1px'
			,	'z-index': '300000'
			,	'position': 'fixed'
			,	'top': '60px'
			});
			jQuery('.ui-dialog-buttonpane').css({
				'background-color': '#FFFFFF'
			});
			jQuery('.ui-dialog-title').css({
				'color': '#CFCFCF'
			,	'font': '12px sans-serif'
			,	'padding': '6px 10px 0'
			});
			if(options.openCallback && typeof(options.openCallback) == 'function') {
				options.openCallback(event, ui);
			}
			jQuery('.ui-widget-overlay').css({
				'z-index': jQuery( event.target ).parents('.ui-dialog:first').css('z-index') - 1
			,	'background-image': 'none'
			});
			if(options.modal && options.closeOnBg) {
				jQuery('.ui-widget-overlay').unbind('click').bind('click', function() {
					jQuery( element ).dialog('close');
				});
			}
		}
	}, options);
	return jQuery(element).dialog(options);
}
/**
 * @see html::slider();
 **/
function toeSliderMove(event, ui) {
    var id = jQuery(event.target).attr('id');
    jQuery('#toeSliderDisplay_'+ id).html( ui.value );
    jQuery('#toeSliderInput_'+ id).val( ui.value ).change();
}
function wpfCorrectJqueryUsed() {
	return (typeof(jQuery.fn.sendFormWpf) === 'function');
}
function wpfReloadCoreJs(clb, params) {
	var scriptsHtml = ''
	,	coreScripts = ['common.js', 'core.js'];
	for(var i = 0; i < coreScripts.length; i++) {
		scriptsHtml += '<script type="text/javascript" class="wpfReloadedScript" src="'+ WPF_DATA.jsPath+ coreScripts[ i ]+ '"></script>';
	}
	jQuery('head').append( scriptsHtml );
	if(clb) {
		_wpfRunClbAfterCoreReload( clb, params );
	}
}
function _wpfRunClbAfterCoreReload(clb, params) {
	if(wpfCorrectJqueryUsed()) {
		callUserFuncArray(clb, params);
		return;
	}
	setTimeout(function(){
		wpfCorrectJqueryUsed(clb, params);
	}, 500);
}
function wpfGetStyleSheetRule(sheetId, rule, isLike) {
	var obj = document.getElementById(sheetId),
		sheet = obj.sheet || obj.styleSheet,
		rules = sheet.cssRules || sheet.rules,
		isLike = typeof isLike == 'undefined' ? false : isLike;
	for (var r = 0; r < rules.length; r++) {
		if(isLike) {
			if(rules[r].selectorText.indexOf(rule) === 0) return rules[r];
		} else if(rules[r].selectorText == rule) return rules[r];
	}
	return false;
}
function wpfGetColorText(bg) {
	if(typeof bg !== 'undefined' && bg.length >= 7) {
		var rgb = (/^#[0-9A-F]{6}$/i.test(bg))
			? [0, parseInt(bg.substring(1,3),16), parseInt(bg.substring(3,5),16), parseInt(bg.substring(5,7),16)]
			: bg.replace(/\s/g,'').match(/^rgba?\((\d+),(\d+),(\d+)/i);
		if(rgb.length >= 4 && (1 - (0.299 * rgb[1] + 0.587 * rgb[2] + 0.114 * rgb[3]) / 255) > 0.5) return '#dddddd';
	}
	return '#444444';
}
function wpfLightenDarkenColor(col, amt) {
	var usePound = false,
		r = 255,
		g = 255,
		b = 255;
	if(typeof col !== 'undefined' && col.length >= 7) {
		if(col.indexOf('rgb') == -1) {
			if(col[0] == "#") {
				col = col.slice(1);
				usePound = true;
			}
 			var num = parseInt(col, 16);
			r = (num >> 16);
			b = ((num >> 8) & 0x00FF);
			g = (num & 0x0000FF);
		} else {
			var withA = col.indexOf('rgba') != -1,
				rgb = withA ? col.replace(/\s/g,'').match(/^rgba?\((\d+),(\d+),(\d+),(\d+)/i) : col.replace(/\s/g,'').match(/^rgba?\((\d+),(\d+),(\d+)/i);
			if(rgb.length >= 4) {
				var a = withA ? rgb[3] : 1,
					bg = (1 - a) * 255;
				r = rgb[1] * a + bg;
				g = rgb[2] * a + bg;
				b = rgb[3] * a + bg;
			}
			usePound = true;
		}
	}
	r = r + amt;
	b = b + amt;
	g = g + amt;
	if(r > 255) r = 255;
	else if(r < 0) r = 0;
	if(b > 255) b = 255;
	else if(b < 0) b = 0;
	if(g > 255) g = 255;
	else if(g < 0) g = 0;
	var res = (g | (b << 8) | (r << 16)).toString(16);
	return (usePound?"#":"") + '0'.repeat(6 - res.length) + res;
}
