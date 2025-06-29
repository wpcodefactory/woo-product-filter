<?php
class PromoWpf extends ModuleWpf {
	private $_mainLink = '';
	private $_minDataInStatToSend = 20;	// At least 20 points in table shuld be present before send stats
	private $_assetsUrl = '';
	public function __construct( $d ) {
		parent::__construct($d);
		$this->getMainLink();
		DispatcherWpf::addFilter('jsInitVariables', array($this, 'addMainOpts'));
	}
	public function init() {
		parent::init();
		/*add_action('admin_footer', array($this, 'displayAdminFooter'), 9);
		if (is_admin()) {
			add_action('init', array($this, 'checkWelcome'));
			add_action('init', array($this, 'checkStatisticStatus'));
			add_action('admin_footer', array($this, 'checkPluginDeactivation'));
		}
		$this->weLoveYou();
		DispatcherWpf::addFilter('mainAdminTabs', array($this, 'addAdminTab'));
		DispatcherWpf::addFilter('subDestList', array($this, 'addSubDestList'));
		DispatcherWpf::addAction('beforeSaveOpts', array($this, 'checkSaveOpts'));
		DispatcherWpf::addFilter('showTplsList', array($this, 'checkProTpls'));
		add_action('admin_notices', array($this, 'checkAdminPromoNotices'));
		// Admin tutorial
		add_action('admin_enqueue_scripts', array( $this, 'loadTutorial'));*/
	}
	public function checkAdminPromoNotices() {
		return;
		if (!FrameWpf::_()->isAdminPlugOptsPage()) {
			return;
		}
		$notices = array();
		// Start usage
		$startUsage = (int) FrameWpf::_()->getModule('options')->get('start_usage');
		$currTime = time();
		$day = 24 * 3600;
		if ($startUsage) {	// Already saved
			/* translators: %s: label */
			$rateMsg = '<h3>' . esc_html(sprintf(__('Hey, I noticed you just use %s over a week – that’s awesome!', 'woo-product-filter'), WPF_WP_PLUGIN_NAME)) . '</h3><p>' .
				esc_html__('Could you please do me a BIG favor and give it a 5-star rating on WordPress? Just to help us spread the word and boost our motivation.', 'woo-product-filter') . '</p>';
			$rateMsg .= '<p><a href="https://wordpress.org/support/plugin/woo-product-filter/reviews/?rate=5#new-post" target="_blank" class="button button-primary" data-statistic-code="done">' .
				esc_html__('Ok, you deserve it', 'woo-product-filter') . '</a>
				<a href="#" class="button" data-statistic-code="later">' . esc_html__('Nope, maybe later', 'woo-product-filter') . '</a>
				<a href="#" class="button" data-statistic-code="hide">' . esc_html__('I already did', 'woo-product-filter') . '</a></p>';
			/* translators: %s: label */
			$enbPromoLinkMsg = '<h3>' . esc_html(sprintf(__('More then eleven days with our %s plugin - Congratulations!', 'woo-product-filter'), WPF_WP_PLUGIN_NAME)) . '</h3>';
			/* translators: %s: url */
			$enbPromoLinkMsg .= '<p>' . sprintf(esc_html__('On behalf of the entire %s company I would like to thank you for been with us, and I really hope that our software helped you.', 'woo-product-filter'), '<a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/') . '" target="_blank">' . esc_html(WPF_WP_PLUGIN_URL) . '</a>') . '</p>';
			$enbPromoLinkMsg .= '<p>' . esc_html__('And today, if you want, - you can help us. This is really simple - you can just add small promo link to our site under your PopUps. This is small step for you, but a big help for us! Sure, if you don\'t want - just skip this and continue enjoy our software!', 'woo-product-filter') . '</p>';
			$enbPromoLinkMsg .= '<p><a href="#" class="button button-primary" data-statistic-code="done">' . esc_html__('Ok, you deserve it', 'woo-product-filter') . '</a>
				<a href="#" class="button" data-statistic-code="later">' . esc_html__('Nope, maybe later', 'woo-product-filter') . '</a>
				<a href="#" class="button" data-statistic-code="hide">' . esc_html__('Skip', 'woo-product-filter') . '</a></p>';
			$enbStatsMsg = '<p>' . esc_html__('You can help us improve our plugin - by', 'woo-product-filter') .
				' <a href="' . esc_url(FrameWpf::_()->getModule('options')->getTabUrl('settings')) . '" data-statistic-code="hide" class="button button-primary wpfEnbStatsAdBtn">' .
				esc_html__('enabling Usage Statistics', 'woo-product-filter') . '</a>.' .
				esc_html__('We will collect only our plugin usage statistic data - to understand Your needs and make our solution better for You.', 'woo-product-filter') . '</p>';
			/* translators: %s: url */
			$checkOtherPlugins = '<p>' . sprintf(esc_html__('Check out %s! Years of experience in WordPress plugins developers made those list unbreakable!', 'woo-product-filter'), '<a href="' . esc_url(FrameWpf::_()->getModule('options')->getTabUrl('featured-plugins')) . '" target="_blank" class="button button-primary" data-statistic-code="hide">our other Plugins</a>') . '</p>';
			$notices = array(
				'rate_msg' => array('html' => $rateMsg, 'show_after' => 7 * $day),
				'enb_promo_link_msg' => array('html' => $enbPromoLinkMsg, 'show_after' => 11 * $day),
				'enb_stats_msg' => array('html' => $enbStatsMsg, 'show_after' => 5 * $day),
			);
			foreach ($notices as $nKey => $n) {
				if ($currTime - $startUsage <= $n['show_after']) {
					unset($notices[ $nKey ]);
					continue;
				}
				$done = (int) FrameWpf::_()->getModule('options')->get('done_' . $nKey);
				if ($done) {
					unset($notices[ $nKey ]);
					continue;
				}
				$hide = (int) FrameWpf::_()->getModule('options')->get('hide_' . $nKey);
				if ($hide) {
					unset($notices[ $nKey ]);
					continue;
				}
				$later = (int) FrameWpf::_()->getModule('options')->get('later_' . $nKey);
				if ( $later && ( $currTime - $later ) <= 2 * $day ) {	// remember each 2 days
					unset($notices[ $nKey ]);
					continue;
				}
				if ('enb_promo_link_msg' == $nKey && ( (int) FrameWpf::_()->getModule('options')->get('add_love_link') )) {
					unset($notices[ $nKey ]);
					continue;
				}
			}
		} else {
			FrameWpf::_()->getModule('options')->getModel()->save('start_usage', $currTime);
		}
		if (!empty($notices)) {
			if (isset($notices['rate_msg']) && isset($notices['enb_promo_link_msg']) && !empty($notices['enb_promo_link_msg'])) {
				unset($notices['rate_msg']);	// Show only one from those messages
			}
			$html = '';
			foreach ($notices as $nKey => $n) {
				$this->getModel()->saveUsageStat($nKey . '.show', true);
				$html .= '<div class="updated notice is-dismissible woobewoo-admin-notice" data-code="' . $nKey . '">' . $n['html'] . '</div>';
			}
			HtmlWpf::echoEscapedHtml($html);
		}
	}
	public function addAdminTab( $tabs ) {
		return $tabs;
	}
	public function addSubDestList( $subDestList ) {
		if (!$this->isPro()) {
			$subDestList = array_merge($subDestList, array(
				'constantcontact' => array('label' => esc_html__('Constant Contact - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'campaignmonitor' => array('label' => esc_html__('Campaign Monitor - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'verticalresponse' => array('label' => esc_html__('Vertical Response - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'sendgrid' => array('label' => esc_html__('SendGrid - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'get_response' => array('label' => esc_html__('GetResponse - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'icontact' => array('label' => esc_html__('iContact - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'activecampaign' => array('label' => esc_html__('Active Campaign - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'mailrelay' => array('label' => esc_html__('Mailrelay - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'arpreach' => array('label' => esc_html__('arpReach - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'sgautorepondeur' => array('label' => esc_html__('SG Autorepondeur - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'benchmarkemail' => array('label' => esc_html__('Benchmark - PRO', 'woo-product-filter'), 'require_confirm' => true),
				'infusionsoft' => array('label' => esc_html__('InfusionSoft - PRO', 'woo-product-filter'), 'require_confirm' => false),
				'salesforce' => array('label' => esc_html__('SalesForce - Web-to-Lead - PRO', 'woo-product-filter'), 'require_confirm' => false),
				'convertkit' => array('label' => esc_html__('ConvertKit - PRO', 'woo-product-filter'), 'require_confirm' => false),
				'myemma' => array('label' => esc_html__('Emma - PRO', 'woo-product-filter'), 'require_confirm' => false),
				'sendinblue' => array('label' => esc_html__('SendinBlue - PRO', 'woo-product-filter'), 'require_confirm' => false),
				'vision6' => array('label' => esc_html__('Vision6 - PRO', 'woo-product-filter'), 'require_confirm' => false),
				'vtiger' => array('label' => esc_html__('Vtiger - PRO', 'woo-product-filter'), 'require_confirm' => false),
				'ymlp' => array('label' => esc_html__('Your Mailing List Provider (Ymlp) - PRO', 'woo-product-filter'), 'require_confirm' => false),
				'fourdem' => array('label' => esc_html__('4Dem.it - PRO', 'woo-product-filter'), 'require_confirm' => false),
			));
		}
		return $subDestList;
	}
	public function getOverviewTabContent() {
		return $this->getView()->getOverviewTabContent();
	}
	public function showWelcomePage() {
		$this->getView()->showWelcomePage();
	}
	public function displayAdminFooter() {
		if (FrameWpf::_()->isAdminPlugPage()) {
			$this->getView()->displayAdminFooter();
		}
	}
	private function _preparePromoLink( $link, $ref = '' ) {
		if (empty($ref)) {
			$ref = 'user';
		}
		return $link;
	}
	public function weLoveYou() {
		if (!$this->isPro()) {
			DispatcherWpf::addFilter('popupEditTabs', array($this, 'addUserExp'), 10, 2);
			DispatcherWpf::addFilter('popupEditDesignTabs', array($this, 'addUserExpDesign'));
			DispatcherWpf::addFilter('editPopupMainOptsShowOn', array($this, 'showAdditionalmainAdminShowOnOptions'));
		}
	}
	public function showAdditionalmainAdminShowOnOptions( $popup ) {
		$this->getView()->showAdditionalmainAdminShowOnOptions($popup);
	}
	public function addUserExp( $tabs, $popup ) {
		$modPath = '';
		$tabs['wpfPopupAbTesting'] = array(
			'title' => esc_html__('Testing', 'woo-product-filter'),
			'content' => '<a href="' . esc_url($this->generateMainLink('utm_source=plugin&utm_medium=abtesting&utm_campaign=popup')) . '" target="_blank" class="button button-primary">' .
				esc_html__('Get PRO', 'woo-product-filter') . '</a><br /><a href="' . $this->generateMainLink('utm_source=plugin&utm_medium=abtesting&utm_campaign=popup') . '" target="_blank">' .
				'<img class="woobewoo-maxwidth-full" src="' . $modPath . 'img/AB-testing-pro.jpg" /></a>',
			'icon_content' => '<b>A/B</b>',
			'avoid_hide_icon' => true,
			'sort_order' => 55,
		);
		if (!in_array($popup['type'], array(WPF_FB_LIKE, WPF_IFRAME, WPF_SIMPLE_HTML, WPF_PDF, WPF_AGE_VERIFY, WPF_FULL_SCREEN))) {
			$tabs['wpfLoginRegister'] = array(
				'title' => esc_html__('Login/Registration', 'woo-product-filter'),
				'content' => '<a href="' . $this->generateMainLink('utm_source=plugin&utm_medium=login_registration&utm_campaign=popup') . '" target="_blank" class="button button-primary">' .
					esc_html__('Get PRO', 'woo-product-filter') . '</a><br /><a href="' . $this->generateMainLink('utm_source=plugin&utm_medium=login_registration&utm_campaign=popup') . '" target="_blank">' .
					'<img class="woobewoo-maxwidth-full" src="' . $modPath . 'img/login-registration-1.jpg" /></a>',
				'fa_icon' => 'fa-sign-in',
				'sort_order' => 25,
			);
		}
		return $tabs;
	}
	public function addUserExpDesign( $tabs ) {
		$tabs['wpfPopupLayeredPopup'] = array(
			'title' => esc_html__('Popup Location', 'woo-product-filter'),
			'content' => $this->getView()->getLayeredStylePromo(),
			'fa_icon' => 'fa-arrows',
			'sort_order' => 15,
		);
		return $tabs;
	}
	/**
	 * Public shell for private method
	 */
	public function preparePromoLink( $link, $ref = '' ) {
		return $this->_preparePromoLink($link, $ref);
	}
	public function checkStatisticStatus() {
	}
	public function getMinStatSend() {
		return $this->_minDataInStatToSend;
	}
	public function getMainLink() {
		if (empty($this->_mainLink)) {
			$affiliateQueryString = '';
			$this->_mainLink = 'https://' . WPF_WP_PLUGIN_URL . '/plugins/popup-plugin/' . $affiliateQueryString;
		}
		return $this->_mainLink ;
	}
	public function getWooBeWooPluginLink() {
		return 'https://' . WPF_WP_PLUGIN_URL . '/plugins/woocommerce-filter/' ;
	}
	public function generateMainLink( $params = '' ) {
		$mainLink = $this->getMainLink();
		if (!empty($params)) {
			return $mainLink . ( strpos($mainLink , '?') ? '&' : '?' ) . $params;
		}
		return $mainLink;
	}
	public function getContactFormFields() {
		$fields = array(
			'name' => array('label' => esc_html__('Name', 'woo-product-filter'), 'valid' => 'notEmpty', 'html' => 'text'),
			'email' => array('label' => esc_html__('Email', 'woo-product-filter'), 'html' => 'email', 'valid' => array('notEmpty', 'email'), 'placeholder' => 'example@mail.com', 'def' => get_bloginfo('admin_email')),
			'website' => array('label' => esc_html__('Website', 'woo-product-filter'), 'html' => 'text', 'placeholder' => 'http://example.com', 'def' => get_bloginfo('url')),
			'subject' => array('label' => esc_html__('Subject', 'woo-product-filter'), 'valid' => 'notEmpty', 'html' => 'text'),
			'category' => array('label' => esc_html__('Topic', 'woo-product-filter'), 'valid' => 'notEmpty', 'html' => 'selectbox', 'options' => array(
				'plugins_options' => esc_html__('Plugin options', 'woo-product-filter'),
				'bug' => esc_html__('Report a bug', 'woo-product-filter'),
				'functionality_request' => esc_html__('Require a new functionality', 'woo-product-filter'),
				'other' => esc_html__('Other', 'woo-product-filter'),
			)),
			'message' => array('label' => esc_html__('Message', 'woo-product-filter'), 'valid' => 'notEmpty', 'html' => 'textarea', 'placeholder' => esc_attr__('Hello Woobewoo Team!', 'woo-product-filter')),
		);
		foreach ($fields as $k => $v) {
			if (isset($fields[ $k ]['valid']) && !is_array($fields[ $k ]['valid'])) {
				$fields[ $k ]['valid'] = array( $fields[ $k ]['valid'] );
			}
		}
		return $fields;
	}
	public function isPro() {
		static $isPro;
		if (is_null($isPro)) {
			// license is always active with PRO - even if license key was not entered,
			// add_options module was from the begining of the times in PRO, and will be active only once user will activate license on site
			$isPro = FrameWpf::_()->getModule('license') && FrameWpf::_()->getModule('on_exit');
		}
		return $isPro;
	}
	public function checkWelcome() {
		$from = ReqWpf::getVar('from', 'get');
		$pl = ReqWpf::getVar('pl', 'get');
		if ( 'welcome-page' == $from && WPF_CODE == $pl && FrameWpf::_()->getModule('user')->isAdmin() ) {
			$welcomeSent = (int) get_option(WPF_DB_PREF . 'welcome_sent');
			if (!$welcomeSent) {
				$this->getModel()->welcomePageSaveInfo();
				update_option(WPF_DB_PREF . 'welcome_sent', 1);
			}
			$skipTutorial = (int) ReqWpf::getVar('skip_tutorial', 'get');
			if ($skipTutorial) {
				$tourHst = $this->getModel()->getTourHst();
				$tourHst['closed'] = 1;
				$this->getModel()->setTourHst( $tourHst );
			}
		}
	}
	public function getContactLink() {
		return $this->getMainLink() . '#contact';
	}
	public function addMainOpts( $opts ) {
		$title = 'WordPress PopUp Plugin';
		$opts['options']['love_link_html'] = '<a title="' . $title . '" href="' . $this->generateMainLink('utm_source=plugin&utm_medium=love_link&utm_campaign=popup') . '" target="_blank">' . $title . '</a>';
		return $opts;
	}
	public function checkSaveOpts( $newValues ) {
		$loveLinkEnb = (int) FrameWpf::_()->getModule('options')->get('add_love_link');
		$loveLinkEnbNew = isset($newValues['opt_values']['add_love_link']) ? (int) $newValues['opt_values']['add_love_link'] : 0;
		if ($loveLinkEnb != $loveLinkEnbNew) {
			$this->getModel()->saveUsageStat('love_link.' . ( $loveLinkEnbNew ? 'enb' : 'dslb' ));
		}
	}
	public function checkProTpls( $list ) {
		if (!$this->isPro()) {
			$imgsPath = '';
			$promoList = array(
			);
			foreach ($promoList as $i => $t) {
				$promoList[ $i ]['img_preview_url'] = $imgsPath . $promoList[ $i ]['img_preview'];
				$promoList[ $i ]['promo'] = strtolower(str_replace(array(' ', '!'), '', $t['label']));
				$promoList[ $i ]['promo_link'] = $this->generateMainLink('utm_source=plugin&utm_medium=' . $promoList[ $i ]['promo'] . '&utm_campaign=popup');
			}
			foreach ($list as $i => $t) {
				if (isset($t['id']) && $t['id'] >= 50) {
					unset($list[ $i ]);
				}
			}
			$list = array_merge($list, $promoList);
		}
		return $list;
	}
	public function loadTutorial() {
		// Don't run on WP < 3.3
		if (get_bloginfo( 'version' ) < '3.3') {
			return;
		}
	}
	public function checkToShowTutorial() {
		if (ReqWpf::getVar('tour', 'get') == 'clear-hst') {
			$this->getModel()->clearTourHst();
		}
		$hst = $this->getModel()->getTourHst();
		if ( ( isset($hst['closed']) && $hst['closed'] ) || ( isset($hst['finished']) && $hst['finished'] ) ) {
			return;
		}
		$tourData = array();
		$tourData['tour'] = array(
			'welcome' => array(
				'points' => array(
					'first_welcome' => array(
						'target' => '#toplevel_page_popup-wp-woobewoo',
						'options' => array(
							'position' => array(
								'edge' => 'bottom',
								'align' => 'top',
							),
						),
						'show' => 'not_plugin',
					),
				),
			),
			'create_first' => array(
				'points' => array(
					'create_bar_btn' => array(
						'target' => '.woobewoo-content .woobewoo-navigation .woobewoo-tab-popup_add_new',
						'options' => array(
							'position' => array(
								'edge' => 'left',
								'align' => 'right',
							),
						),
						'show' => array('tab_popup', 'tab_settings', 'tab_overview'),
					),
					'enter_title' => array(
						'target' => '#wpfCreatePopupForm input[type=text]',
						'options' => array(
							'position' => array(
								'edge' => 'top',
								'align' => 'bottom',
							),
						),
						'show' => 'tab_popup_add_new',
					),
					'select_tpl' => array(
						'target' => '.popup-list',
						'options' => array(
							'position' => array(
								'edge' => 'bottom',
								'align' => 'top',
							),
						),
						'show' => 'tab_popup_add_new',
					),
					'save_first_popup' => array(
						'target' => '#wpfCreatePopupForm .button-primary',
						'options' => array(
							'position' => array(
								'edge' => 'left',
								'align' => 'right',
							),
						),
						'show' => 'tab_popup_add_new',
					),
				),
			),
			'first_edit' => array(
				'points' => array(
					'popup_main_opts' => array(
						'target' => '#wpfPopupEditForm',
						'options' => array(
							'position' => array(
								'edge' => 'right',
								'align' => 'left',
							),
							'pointerWidth' => 200,
						),
						'show' => 'tab_popup_edit',
					),
					'popup_design_opts' => array(
						'target' => '#wpfPopupEditForm',
						'options' => array(
							'position' => array(
								'edge' => 'right',
								'align' => 'top',
							),
							'pointerWidth' => 200,
						),
						'show' => 'tab_popup_edit',
						'sub_tab' => '#wpfPopupTpl',
					),
					'popup_subscribe_opts' => array(
						'target' => '#wpfPopupEditForm',
						'options' => array(
							'position' => array(
								'edge' => 'right',
								'align' => 'top',
							),
							'pointerWidth' => 200,
						),
						'show' => 'tab_popup_edit',
						'sub_tab' => '#wpfPopupSubscribe',
					),
					'popup_statistic_opts' => array(
						'target' => '#wpfPopupEditForm',
						'options' => array(
							'position' => array(
								'edge' => 'right',
								'align' => 'left',
							),
							'pointerWidth' => 200,
						),
						'show' => 'tab_popup_edit',
						'sub_tab' => '#wpfPopupStatistic',
					),
					'popup_code_opts' => array(
						'target' => '#wpfPopupEditForm',
						'options' => array(
							'position' => array(
								'edge' => 'right',
								'align' => 'left',
							),
							'pointerWidth' => 200,
						),
						'show' => 'tab_popup_edit',
						'sub_tab' => '#wpfPopupEditors',
					),
					'final' => array(
						'target' => '#wpfPopupMainControllsShell .wpfPopupSaveBtn',
						'options' => array(
							'position' => array(
								'edge' => 'top',
								'align' => 'bottom',
							),
							'pointerWidth' => 500,
						),
						'show' => 'tab_popup_edit',
					),
				),
			),
		);
		$isAdminPage = FrameWpf::_()->isAdminPlugOptsPage();
		$activeTab = FrameWpf::_()->getModule('options')->getActiveTab();
		foreach ($tourData['tour'] as $stepId => $step) {
			foreach ($step['points'] as $pointId => $point) {
				$pointKey = $stepId . '-' . $pointId;
				if (isset($hst['passed'][ $pointKey ]) && $hst['passed'][ $pointKey ]) {
					unset($tourData['tour'][ $stepId ]['points'][ $pointId ]);
					continue;
				}
				$show = isset($point['show']) ? $point['show'] : 'plugin';
				if (!is_array($show)) {
					$show = array( $show );
				}
				if ( ( in_array('plugin', $show) && !$isAdminPage ) || ( in_array('not_plugin', $show) && $isAdminPage ) ) {
					unset($tourData['tour'][ $stepId ]['points'][ $pointId ]);
					continue;
				}
				$showForTabs = false;
				$hideForTabs = false;
				foreach ($show as $s) {
					if (strpos($s, 'tab_') === 0) {
						$showForTabs = true;
					}
					if (strpos($s, 'tab_not_') === 0) {
						$showForTabs = true;
					}
				}
				if ( $showForTabs && ( !in_array('tab_' . $activeTab, $show) || !$isAdminPage ) ) {
					unset($tourData['tour'][ $stepId ]['points'][ $pointId ]);
					continue;
				}
				if ( $hideForTabs && ( in_array('tab_not_' . $activeTab, $show) || !$isAdminPage ) ) {
					unset($tourData['tour'][ $stepId ]['points'][ $pointId ]);
					continue;
				}
			}
		}
		foreach ($tourData['tour'] as $stepId => $step) {
			if (!isset($step['points']) || empty($step['points'])) {
				unset($tourData['tour'][ $stepId ]);
			}
		}
		if (empty($tourData['tour'])) {
			return;
		}
		$tourData['html'] = $this->getView()->getTourHtml();
		//FrameWpf::_()->getModule('templates')->loadCoreJs();
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_script( 'wp-pointer' );
		FrameWpf::_()->addScript(WPF_CODE . 'admin.tour', $this->getModPath() . 'js/admin.tour.js');
		FrameWpf::_()->addJSVar(WPF_CODE . 'admin.tour', 'wpfAdminTourData', $tourData);
	}
	public function getContactFormPlgUrl() {
		return 'http://wordpress.org/support/plugin/contact-form-by-woobewoo';
	}
	public function showFeaturedPluginsPage() {
		return $this->getView()->showFeaturedPluginsPage();
	}
	public function checkPluginDeactivation() {
		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if ($screen && isset($screen->base) && 'plugins' == $screen->base) {
				FrameWpf::_()->getModule('templates')->loadCoreJs();
				FrameWpf::_()->getModule('templates')->loadCoreCss();
				wp_enqueue_style('jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css', array(), '1.0');
				FrameWpf::_()->addScript('jquery-ui-dialog');
				FrameWpf::_()->addScript(WPF_CODE . '.admin.plugins', $this->getModPath() . 'js/admin.plugins.js');
				FrameWpf::_()->addJSVar(WPF_CODE . '.admin.plugins', 'wpfPluginsData', array(
					'plugName' => WPF_PLUG_NAME . '/' . WPF_MAIN_FILE,
				));
				HtmlWpf::echoEscapedHtml($this->getView()->getPluginDeactivation());
			}
		}
	}
	public function connectItemEditStats() {
		FrameWpf::_()->addScript(WPF_CODE . '.admin.item.edit.stats', $this->getModPath() . 'js/admin.item.edit.stats.js');
	}
}
