jQuery(document).ready(function(){
	jQuery('.wpfStarsRatingLine input').on('change', function() {
		var $this = jQuery(this),
			$block = $this.closest('.wpf-overview-block-body'),
			value = $this.val();
		if (value == 5) {
			jQuery(this).sendFormWpf({
				data: 'mod=overview&action=rating',
				appendData: {wpfNonce: window.wpfNonce, rate: 5},
				noError: true,
				onSuccess: function(res) {
					wpfOverviewSubmitSuccess(jQuery('.wpfStarsRatingLine input'), res);
					toeRedirect('https://wordpress.org/support/plugin/woo-product-filter/reviews/#new-post', true);
				}
			});
			$block.find('.wpf-overview-rating').addClass('wpf-overview-hidden');
		} else {
			$block.find('.wpf-overview-rating').removeClass('wpf-overview-hidden');
		}
	});
	jQuery('#wpfSubscribeSubmit').on('click', function(){
		var $button = jQuery(this),
			$email = $button.parent().find('input[name="wpf-email"]'),
			email = $email.val();
		if (email.length == 0) {
			jQuery.sNotify({
				'icon': 'fa fa-exclamation',
				'content': ' <span> '+$email.attr('data-error')+'</span>',
				'delay' : 2500
			});
		} else {
			jQuery(this).sendFormWpf({
				btn: $button,
				data: 'mod=overview&action=subscribe',
				appendData: {wpfNonce: window.wpfNonce, email: email},
				noError: true,
				onSuccess: function(res) {
					wpfOverviewSubmitSuccess($button, res, true);
				}
			});
		}
		return false;
	});
	jQuery('#wpfContactSubmit').on('click', function(){
		var $button = jQuery(this),
			$form = $button.parent(),
			$email = $form.find('input[name="wpf-email"]'),
			$desc = $form.find('textarea[name="wpf-desc"]'),
			$uname = $form.find('input[name="wpf-name"]'),
			$subject = $form.find('input[name="wpf-subject"]'),
			email = $email.val();
		if (email.length == 0) {
			jQuery.sNotify({
				'icon': 'fa fa-exclamation',
				'content': ' <span> '+$email.attr('data-error')+'</span>',
				'delay' : 2500
			});
		} else if ($desc.val().length == 0) {
			jQuery.sNotify({
				'icon': 'fa fa-exclamation',
				'content': ' <span> '+ $desc.attr('data-error')+'</span>',
				'delay' : 2500
			});
		} else {
			jQuery(this).sendFormWpf({
				btn: $button,
				data: 'mod=overview&action=contactus',
				appendData: {wpfNonce: window.wpfNonce, email: email, desc: $desc.val(), name: $uname.val(), subject: $subject.val()},
				noError: true,
				onSuccess: function(res) {
					if (!res['errors'][0]) {
						$desc.val('');
						$email.val('');
						$subject.val('');
						$uname.val('');
					}
					wpfOverviewSubmitSuccess($button, res, false);
				}
			});
		}
		return false;
	});
	jQuery('#wpfRatingSubmit').on('click', function(){
		var $button = jQuery(this),
			$block = $button.closest('.wpf-overview-block'),
			$email = $block.find('input[name="wpf-email"]'),
			email = $email.val(),
			$problem = $block.find('input[name="wpf-problem"]'),
			problem = $problem.val();
		if (email.length == 0) {
			jQuery.sNotify({
				'icon': 'fa fa-exclamation',
				'content': ' <span> '+$email.attr('placeholder')+'</span>',
				'delay' : 2500
			});
		} else if ($problem.length == 0) {
			jQuery.sNotify({
				'icon': 'fa fa-exclamation',
				'content': ' <span> '+$problem.attr('placeholder')+'</span>',
				'delay' : 2500
			});
		} else {
			jQuery(this).sendFormWpf({
				btn: $button,
				data: 'mod=overview&action=rating',
				appendData: {
					wpfNonce: window.wpfNonce,
					email: email, 
					problem: problem,
					rate: $block.find('input[name="wpfStarInput"]:checked').val()
				},
				noError: true,
				onSuccess: function(res) {
					wpfOverviewSubmitSuccess($button, res, true);
				}
			});
		}
		return false;
	});
	
});
function wpfOverviewSubmitSuccess($button, res, hidden) {
	if(!res.error) {
		if (hidden) {
			$button.attr('disabled', 'disabled');
			$button.closest('.wpf-overview-block').addClass('wpf-overview-hidden');
		}
		if (res['messages'][0]) {
			jQuery.sNotify({
				'icon': 'fa fa-check',
				'content': ' <span> '+res['messages'][0]+'</span>',
				'delay' : 2500
			});
		}
	} else {
		if (res['errors'][0]) {
			jQuery.sNotify({
				'icon': 'fa fa-exclamation',
				'content': ' <span> '+res['errors'][0]+'</span>',
				'delay' : 2500
			});
		}	
	}
}
