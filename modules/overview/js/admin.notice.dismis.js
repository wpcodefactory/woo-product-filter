"use strict";
jQuery(document).ready(function(){
	jQuery(document).on('click', '.wpf-notice-dismis .notice-dismiss', function(){
		jQuery.sendFormWpf({
			data: {mod: 'overview', action: 'dismissNotice', 'slug': jQuery(this).closest('.wpf-notice-dismis').attr('data-disslug')}
		});
	});
	jQuery(document).on('click', '.wpf-notice-dismis .button-dismiss', function(){
		jQuery(this).closest('.wpf-notice-dismis').find('.notice-dismiss').trigger('click');
	});
	jQuery(document).on('click', '.wpf-notice-dismis .button-approve', function(){
		var $wrapper = jQuery(this).closest('.wpf-notice-dismis');
		jQuery.sendFormWpf({
			data: {mod: 'overview', action: 'approveNotice', 'slug': $wrapper.attr('data-disslug')}
		});
		$wrapper.find('.notice-dismiss').trigger('click');
	});
});