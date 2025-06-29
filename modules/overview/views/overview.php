<?php
class OverviewViewWpf extends ViewWpf {
	public function getOverviewTabContent() {
		FrameWpf::_()->addScript('admin.overview', $this->getModule()->getModPath() . 'js/admin.overview.js');
		
		FrameWpf::_()->getModule('templates')->loadJqueryUi();
		FrameWpf::_()->getModule('templates')->loadBootstrap();
		FrameWpf::_()->addScript('notify-js', WPF_JS_PATH . 'notify.js', array(), false, true);
		FrameWpf::_()->addStyle('admin.overview.css', $this->getModule()->getModPath() . 'css/admin.overview.css');
		
		$this->assign('isWeek', ( time() - $this->getModel()->getFirstOverview() ) > 608800);
		return parent::getContent('overviewTabContent');
	}
	public function showAdminInfo() {
		if (FrameWpf::_()->isWCLicense()) {
			return;
		}
		$dismiss = (int) FrameWpf::_()->getModule('options')->get('dismiss_wpf-ads-reward');
		if ($dismiss) {
			return;	// it was already dismissed by user - no need to show it again
		}
		FrameWpf::_()->getModule('templates')->loadCoreJs();
		FrameWpf::_()->addScript('wpf.admin.notice.dismis', $this->getModule()->getModPath() . 'js/admin.notice.dismis.js');

		$this->assign( 'message',
			'<b>' . esc_html__('New! Reward points and loyalty plugin from WBW', 'woo-product-filter') . '</b><br/>' .
			esc_html__('Set rewards in the form of bonus points for the purchase of good, signup, writing review and more. Create delayed campaigns with automatic reward points accrual based on triggers/conditions.', 'woo-product-filter') .
			' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/plugins/reward-points-for-woocommerce/') . '" target="_blank">' . esc_html__('More Info', 'woo-product-filter') . '</a>'
		);
		$this->assign('msgSlug', 'wpf-ads-reward');
		HtmlWpf::echoEscapedHtml($this->getContent('showAdminInfo'));
	}
	public function showRestApiInfo() {
		$dismiss = (int) FrameWpf::_()->getModule('options')->get('dismiss_wpf-rest-api');
		if ($dismiss) {
			return;	// it was already dismissed by user - no need to show it again
		}
		global $wpdb;
		$api = $wpdb->get_var("SELECT 1 FROM {$wpdb->prefix}woocommerce_api_keys");
		if (1 != $api) {
			return;
		}
		if (FrameWpf::_()->getModule('options')->get('disable_autoindexing') == 1 && FrameWpf::_()->getModule('options')->get('disable_autoindexing_by_ss') == 1 && FrameWpf::_()->getModule('options')->get('indexing_schedule') == 1) {
			FrameWpf::_()->getModule('options')->getModel()->save('dismiss_wpf-rest-api', 1);
			return;
		}

		FrameWpf::_()->getModule('templates')->loadCoreJs();
		FrameWpf::_()->addScript('wpf.admin.notice.dismis', $this->getModule()->getModPath() . 'js/admin.notice.dismis.js');

		$this->assign( 'message',
			'<b>' . esc_html__('We have detected that you are using REST API to update products.', 'woo-product-filter') . '</b><br/><br/>' .
			esc_html__('To correctly interact with this functionality, you need to change the plugin settings.', 'woo-product-filter') . '<br/><br/>' .
			esc_html__('Please activate the "Disable automatic calculation of index tables after editing products" and "Disable automatic calculation of index tables after product stock changes" options', 'woo-product-filter') . 
			' <a href="' . esc_url(FrameWpf::_()->getModule('options')->getTabUrl('settings')) . '">' . esc_html__('in the general plugin settings', 'woo-product-filter') . '</a>, ' . esc_html__('and set "Start indexing on schedule" at a time convenient for you.', 'woo-product-filter') . '<br/><br/>' .
			esc_html__('Configure these options for you?', 'woo-product-filter') . 
			' <a href="#" class="button button-primary button-approve">' . esc_html__('Yes', 'woo-product-filter') . '</a>' .
			' <a href="#" class="button button-dismiss">' . esc_html__('No, thanks', 'woo-product-filter') . '</a>'
		);
		$this->assign('msgSlug', 'wpf-rest-api');
		HtmlWpf::echoEscapedHtml($this->getContent('showAdminInfo'));
	}
}
