"use strict";
jQuery(document).ready(function(){
	var $deactivateLnk = jQuery('#the-list tr[data-plugin="'+ wpfPluginsData.plugName+ '"] .row-actions .deactivate a');
	if($deactivateLnk && $deactivateLnk.length) {
		var $deactivateForm = jQuery('#wpfDeactivateForm');
		var $deactivateWnd = jQuery('#wpfDeactivateWnd').dialog({
			modal:    true
		,	autoOpen: false
		,	width: 500
		,	height: 390
		,	buttons:  {
				'Submit & Deactivate': function() {
					$deactivateForm.submit();
				}
			}
		});
		var $wndButtonset = $deactivateWnd.parents('.ui-dialog:first')
			.find('.ui-dialog-buttonpane .ui-dialog-buttonset')
		,	$deactivateDlgBtn = $deactivateWnd.find('.wpfDeactivateSkipDataBtn')
		,	deactivateUrl = $deactivateLnk.attr('href');
		$deactivateDlgBtn.attr('href', deactivateUrl);
		$wndButtonset.append( $deactivateDlgBtn );
		$deactivateLnk.click(function(){
			$deactivateWnd.dialog('open');
			return false;
		});
		
		$deactivateForm.submit(function(){
			var $btn = $wndButtonset.find('button:first');
			$btn.width( $btn.width() );	// Ha:)
			$btn.showLoaderWpf();
			jQuery(this).sendFormWpf({
				btn: $btn
			,	onSuccess: function(res) {
					toeRedirect( deactivateUrl );
				}
			});
			return false;
		});
		$deactivateForm.find('[name="deactivate_reason"]').change(function(){
			jQuery('.wpfDeactivateDescShell').slideUp( g_wpfAnimationSpeed );
			if(jQuery(this).prop('checked')) {
				var $descShell = jQuery(this).parents('.wpfDeactivateReasonShell:first').find('.wpfDeactivateDescShell');
				if($descShell && $descShell.length) {
					$descShell.slideDown( g_wpfAnimationSpeed );
				}
			}
		});
	}
});