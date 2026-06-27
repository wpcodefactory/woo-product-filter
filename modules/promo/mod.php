<?php
/**
 * Product Filter by WBW - PromoWpf Class
 *
 * @version 3.1.9
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class PromoWpf extends ModuleWpf {

	private $_mainLink = '';

	private $_minDataInStatToSend = 20; // At least 20 points in table should be present before send stats

	private $_assetsUrl = '';

	public function __construct( $d ) {
		parent::__construct($d);
		$this->getMainLink();
		DispatcherWpf::addFilter('jsInitVariables', array($this, 'addMainOpts'));
	}

	public function init() {
		parent::init();
	}

	/**
	 * checkAdminPromoNotices.
	 *
	 * @version 3.1.8
	 */
	public function checkAdminPromoNotices() {
		return;
	}

	public function addAdminTab( $tabs ) {
		return $tabs;
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
	 * Public shell for private method.
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
			'name'     => array('label' => esc_html__('Name', 'woo-product-filter'), 'valid' => 'notEmpty', 'html' => 'text'),
			'email'    => array('label' => esc_html__('Email', 'woo-product-filter'), 'html' => 'email', 'valid' => array('notEmpty', 'email'), 'placeholder' => 'example@mail.com', 'def' => get_bloginfo('admin_email')),
			'website'  => array('label' => esc_html__('Website', 'woo-product-filter'), 'html' => 'text', 'placeholder' => 'http://example.com', 'def' => get_bloginfo('url')),
			'subject'  => array('label' => esc_html__('Subject', 'woo-product-filter'), 'valid' => 'notEmpty', 'html' => 'text'),
			'category' => array('label' => esc_html__('Topic', 'woo-product-filter'), 'valid' => 'notEmpty', 'html' => 'selectbox', 'options' => array(
				'plugins_options'       => esc_html__('Plugin options', 'woo-product-filter'),
				'bug'                   => esc_html__('Report a bug', 'woo-product-filter'),
				'functionality_request' => esc_html__('Require a new functionality', 'woo-product-filter'),
				'other'                 => esc_html__('Other', 'woo-product-filter'),
			)),
			'message'  => array('label' => esc_html__('Message', 'woo-product-filter'), 'valid' => 'notEmpty', 'html' => 'textarea', 'placeholder' => esc_attr__('Hello Woobewoo Team!', 'woo-product-filter')),
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
			// add_options module was from the beginning of the times in PRO, and will be active only once user will activate license on site
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

	/**
	 * checkPluginDeactivation.
	 *
	 * @version 3.1.9
	 */
	public function checkPluginDeactivation() {
		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if ($screen && isset($screen->base) && 'plugins' == $screen->base) {
				FrameWpf::_()->getModule('templates')->loadCoreJs();
				FrameWpf::_()->getModule('templates')->loadCoreCss();
				wp_enqueue_style('jquery-ui', $this->getModPath() . 'css/jquery-ui.css', array(), '1.0');
				FrameWpf::_()->addScript('jquery-ui-dialog');
				FrameWpf::_()->addScript(WPF_CODE . '.admin.plugins', $this->getModPath() . 'js/admin.plugins.js');
				FrameWpf::_()->addJSVar(WPF_CODE . '.admin.plugins', 'wpfPluginsData', array(
					'plugName' => WPF_PLUG_NAME . '/' . WPF_MAIN_FILE,
				));
				echo wp_kses( $this->getView()->getPluginDeactivation(), HtmlWpf::getAllowedHtmlTags() );
			}
		}
	}

	public function connectItemEditStats() {
		FrameWpf::_()->addScript(WPF_CODE . '.admin.item.edit.stats', $this->getModPath() . 'js/admin.item.edit.stats.js');
	}
}
