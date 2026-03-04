<?php
class PromoViewWpf extends ViewWpf {
	public function displayAdminFooter() {
		parent::display('adminFooter');
	}
	public function showAdditionalmainAdminShowOnOptions( $popup ) {
		$this->assign('promoLink', $this->getModule()->generateMainLink('utm_source=plugin&utm_medium=onexit&utm_campaign=popup'));
		parent::display('additionalmainAdminShowOnOptions');
	}
	public function getOverviewTabContent() {
		FrameWpf::_()->getModule('templates')->loadJqueryUi();
		
		FrameWpf::_()->getModule('templates')->loadSlimscroll();
		FrameWpf::_()->addScript('admin.overview', $this->getModule()->getModPath() . 'js/admin.overview.js');
		FrameWpf::_()->addStyle('admin.overview', $this->getModule()->getModPath() . 'css/admin.overview.css');
		$this->assign('mainLink', $this->getModule()->getMainLink());
		$this->assign('faqList', $this->getFaqList());
		$this->assign('serverSettings', $this->getServerSettings());
		$this->assign('news', $this->getNewsContent());
		$this->assign('contactFields', $this->getModule()->getContactFormFields());
		//return parent::getContent('overviewTabContent');
		return '';
	}
	public function getFaqList() {
		return array();
	}
	public function getMostFaqList() {
		/* translators: 1: url 2: url */
		$str1 = sprintf(esc_html__('By default all subscribers add to the WordPress. 
					To find your subscribers go to Users tab on the left navigation menu of WordPress admin area. 
					Also available subscription to the Aweber, MailChimp, MailPoet %1$s. 
					If you want to add another subscription service - just %2$s and provide URL of the subscription service.', 'woo-product-filter'),
					'<a href="' . $this->getModule()->getMainLink() . '#subscribe-to-email-popup-settings" target="_blank">' . esc_html__('and other', 'woo-product-filter') . '</a>',
					'<a href="' . $this->getModule()->getContactLink() . '" target="_blank">' . esc_html__('contact us', 'woo-product-filter') . '</a>');
		/* translators: %s: url */
		$str2 = sprintf(esc_html__("If you setup you're PopUp properly, and it still doesn't show on the page - there are can be conflict with your WordPress theme or other plugins. %s with the URL of the webpage you add popup and screenshots / text of the error messages, if you have one - and we will help you resolve your issue.", 'woo-product-filter'),
			'<a href="' . $this->getModule()->getContactLink() . '" target="_blank">' . esc_html__('Contact us', 'woo-product-filter') . '</a>');
		return array(
			esc_html__("Where's my subscribers?", 'woo-product-filter') 
				=> $str1,
			esc_html__("PopUp doesn't appear on the website", 'woo-product-filter') 
				=> $str2,
		);
	}
	public function getNewsContent() {
		$getData = wp_remote_get('https://' . WPF_WP_PLUGIN_URL . '/news/main.html');
		$content = '';
		if ($getData 
			&& is_array($getData) 
			&& isset($getData['response']) 
			&& isset($getData['response']['code']) 
			&& 200 == $getData['response']['code']
			&& isset($getData['body'])
			&& !empty($getData['body'])
		) {
			$content = $getData['body'];
		} else {
			/* translators: %s: url */
			$content = sprintf(esc_html__('There were some problems while trying to retrieve our news, but you can always check all list %s.', 'woo-product-filter'), '<a target="_blank" href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/news') . '">here</a>');
		}
		return $content;
	}
	public function getServerSettings() {
		global $wpdb;
		return array(
			'Operating System' => array('value' => PHP_OS),
			'PHP Version' => array('value' => PHP_VERSION),
			'Server Software' => array('value' => empty($_SERVER['SERVER_SOFTWARE']) ? '' : sanitize_text_field($_SERVER['SERVER_SOFTWARE'])),
			'MySQL' => array('value' =>  $wpdb->db_version()),
			'PHP Allow URL Fopen' => array('value' => ini_get('allow_url_fopen') ? esc_html__('Yes', 'woo-product-filter') : esc_html__('No', 'woo-product-filter')),
			'PHP Memory Limit' => array('value' => ini_get('memory_limit')),
			'PHP Max Post Size' => array('value' => ini_get('post_max_size')),
			'PHP Max Upload Filesize' => array('value' => ini_get('upload_max_filesize')),
			'PHP Max Script Execute Time' => array('value' => ini_get('max_execution_time')),
			'PHP EXIF Support' => array('value' => extension_loaded('exif') ? esc_html__('Yes', 'woo-product-filter') : esc_html__('No', 'woo-product-filter')),
			'PHP EXIF Version' => array('value' => phpversion('exif')),
			'PHP XML Support' => array('value' => extension_loaded('libxml') ? esc_html__('Yes', 'woo-product-filter') : esc_html__('No', 'woo-product-filter'), 'error' => !extension_loaded('libxml')),
			'PHP CURL Support' => array('value' => extension_loaded('curl') ? esc_html__('Yes', 'woo-product-filter') : esc_html__('No', 'woo-product-filter'), 'error' => !extension_loaded('curl')),
		);
	}
	public function getLayeredStylePromo() {
		$this->assign('promoLink', $this->getModule()->generateMainLink('utm_source=plugin&utm_medium=layered&utm_campaign=popup'));
		//return parent::getContent('layeredStylePromo');
		return '';
	}
	public function showWelcomePage() {
		FrameWpf::_()->getModule('templates')->loadJqueryUi();
		FrameWpf::_()->addStyle('admin.welcome', $this->getModule()->getModPath() . 'css/admin.welcome.css');
		$createNewLink = FrameWpf::_()->getModule('options')->getTabUrl('popup_add_new');
		$goToAdminLink = FrameWpf::_()->getModule('options')->getTabUrl('sliders');
		$skipTutorLink = UriWpf::_(array('baseUrl' => $goToAdminLink, 'skip_tutorial' => 1));
		$this->assign('createNewLink', $this->_makeWelcomeLink( $createNewLink ));
		$this->assign('skipTutorLink', $this->_makeWelcomeLink( $skipTutorLink ));
		$this->assign('faqList', $this->getMostFaqList());
		$this->assign('mainLink', $this->getModule()->getMainLink());
		//parent::display('welcomePage');
	}
	private function _makeWelcomeLink( $link ) {
		return UriWpf::_(array('baseUrl' => $link, 'from' => 'welcome-page', 'pl' => WPF_CODE));
	}
	public function getTourHtml() {
		$this->assign('contactFormLink', $this->getModule()->getContactLink());
		$this->assign('finishSiteLink', $this->getModule()->generateMainLink('utm_source=plugin&utm_medium=final_step_b_step&utm_campaign=popup'));
		//return parent::getContent('adminTour');
		return '';
	}
	public function showFeaturedPluginsPage() {
		FrameWpf::_()->getModule('templates')->loadBootstrapSimple();
		FrameWpf::_()->addStyle('admin.featured-plugins', $this->getModule()->getModPath() . 'css/admin.featured-plugins.css');
		FrameWpf::_()->getModule('templates')->loadGoogleFont('Montserrat');
		$siteUrl = 'https://' . WPF_WP_PLUGIN_URL . '/';
		$pluginsUrl = $siteUrl . 'plugins/';
		$uploadsUrl = $siteUrl . 'wp-content/uploads/';
		$downloadsUrl = 'https://downloads.wordpress.org/plugin/';
		$promoCampaign = 'popup';
		$this->assign('pluginsList', array(
				));
		foreach ($this->pluginsList as $i => $p) {
			$this->pluginsList[ $i ]['url'] = $this->pluginsList[ $i ]['url'] . '?utm_source=plugin&utm_medium=featured_plugins&utm_campaign=' . $promoCampaign;
		}
		$this->assign('bundleUrl', $siteUrl . 'product/plugins-bundle/?utm_source=plugin&utm_medium=featured_plugins&utm_campaign=' . $promoCampaign);
		return parent::getContent('featuredPlugins');
	}
	public function getPluginDeactivation() {
		return parent::getContent('pluginDeactivation');
	}
}
