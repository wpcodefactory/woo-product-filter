<?php
/**
 * Product Filter by WBW - OptionsWpf Class
 *
 * @version 3.0.9
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

class OptionsWpf extends ModuleWpf {

	/**
	 * _tabs.
	 */
	private $_tabs = array();

	/**
	 * _options.
	 */
	private $_options = array();

	/**
	 * _optionsToCategoires.
	 */
	private $_optionsToCategoires = array(); // For faster search

	/**
	 * init.
	 */
	public function init() {
		add_action('init', array($this, 'startSession'), -1);
		add_action('init', array($this, 'initAllOptValues'), 99); // It should be init after all languages was inited (frame::connectLang)
		DispatcherWpf::addFilter('mainAdminTabs', array($this, 'addAdminTab'));
	}

	/**
	 * startSession.
	 */
	public function startSession() {
		$isMultiLogicOr = false;
		$filters        = FrameWpf::_()->getModule( 'woofilters' )->getModel()->getFromTbl();

		foreach ( $filters as $filter ) {
			$filtersSettings = unserialize( $filter['setting_data'] );
			$multiLogic      = $this->getFilterSetting( $filtersSettings['settings'], 'f_multi_logic', 'and' );

			if ( 'or' === $multiLogic ) {
				$isMultiLogicOr = true;
				break;
			}

		}

		if ( $isMultiLogicOr ) {
			ReqWpf::startSession();
		}
	}

	/**
	 * initAllOptValues.
	 */
	public function initAllOptValues() {
		// Just to make sure - that we loaded all default options values
		$this->getAll();
	}

	/**
	 * This method provides fast access to options model method get.
	 *
	 * @see optionsModel::get($d)
	 */
	public function get( $code ) {
		return $this->getModel()->get($code);
	}

	/**
	 * This method provides fast access to options model method get.
	 *
	 * @see optionsModel::get($d)
	 */
	public function isEmpty( $code ) {
		return $this->getModel()->isEmpty($code);
	}

	/**
	 * getAllowedPublicOptions.
	 */
	public function getAllowedPublicOptions() {
		$allowKeys = array('add_love_link', 'disable_autosave');
		$res = array();
		foreach ($allowKeys as $k) {
			$res[ $k ] = $this->get($k);
		}
		return $res;
	}

	/**
	 * getAdminPage.
	 */
	public function getAdminPage() {
		if (!InstallerWpf::isUsed()) {
			InstallerWpf::setUsed(); // Show this welcome page - only one time
			FrameWpf::_()->getModule('promo')->getModel()->bigStatAdd('Welcome Show');
			FrameWpf::_()->getModule('options')->getModel()->save('plug_welcome_show', time()); // Remember this
		}
		return $this->getView()->getAdminPage();
	}

	/**
	 * addAdminTab.
	 */
	public function addAdminTab( $tabs ) {
		$tabs['settings'] = array(
			'label'      => esc_html__('Settings', 'woo-product-filter'),
			'callback'   => array($this, 'getSettingsTabContent'),
			'fa_icon'    => 'fa-gear',
			'sort_order' => 30,
		);
		if (
			!(FrameWpf::_()->moduleExists('license') && FrameWpf::_()->getModule('license')) &&
			!FrameWpf::_()->isWCLicense()
		) {
			$tabs['gopro'] = array(
				'label'      => esc_html__('Go PRO', 'woo-product-filter'),
				'callback'   => array($this, 'getProTabContent'),
				'fa_icon'    => 'fa-star',
				'sort_order' => 998,
			);
		}
		return $tabs;
	}

	/**
	 * getSettingsTabContent.
	 */
	public function getSettingsTabContent() {
		return $this->getView()->getSettingsTabContent();
	}

	/**
	 * getProTabContent.
	 */
	public function getProTabContent() {
		return $this->getView()->getProTabContent();
	}

	/**
	 * getTabs.
	 */
	public function getTabs() {
		if (empty($this->_tabs)) {
			$this->_tabs = DispatcherWpf::applyFilters('mainAdminTabs', array(
				// example: 'main_page' => array('label' => esc_html__('Main Page', 'woo-product-filter'), 'callback' => array($this, 'getTabContent'), 'wp_icon' => 'dashicons-admin-home', 'sort_order' => 0),
			));
			foreach ($this->_tabs as $tabKey => $tab) {
				if (!isset($this->_tabs[ $tabKey ]['url'])) {
					$this->_tabs[ $tabKey ]['url'] = is_array($tab['callback']) ? $this->getTabUrl( $tabKey ) : $tab['callback'];
				}
			}
			uasort($this->_tabs, array($this, 'sortTabsClb'));
		}
		return $this->_tabs;
	}

	/**
	 * sortTabsClb.
	 */
	public function sortTabsClb( $a, $b ) {
		if (isset($a['sort_order']) && isset($b['sort_order'])) {
			if ($a['sort_order'] > $b['sort_order']) {
				return 1;
			}
			if ($a['sort_order'] < $b['sort_order']) {
				return -1;
			}
		}
		return 0;
	}

	/**
	 * getTab.
	 */
	public function getTab( $tabKey ) {
		$this->getTabs();
		return isset($this->_tabs[ $tabKey ]) ? $this->_tabs[ $tabKey ] : false;
	}

	/**
	 * getTabContent.
	 */
	public function getTabContent() {
		return $this->getView()->getTabContent();
	}

	/**
	 * getActiveTab.
	 */
	public function getActiveTab() {
		$reqTab = sanitize_text_field(ReqWpf::getVar('tab'));
		return empty($reqTab) ? 'woofilters' : $reqTab;
	}

	/**
	 * getTabUrl.
	 */
	public function getTabUrl( $tab = '' ) {
		static $mainUrl;
		if (empty($mainUrl)) {
			$mainUrl = FrameWpf::_()->getModule('adminmenu')->getMainLink();
		}
		return empty($tab) ? $mainUrl : $mainUrl . '&tab=' . $tab;
	}

	/**
	 * getRolesList.
	 */
	public function getRolesList() {
		if (!function_exists('get_editable_roles')) {
			require_once( ABSPATH . '/wp-admin/includes/user.php' );
		}
		return get_editable_roles();
	}

	/**
	 * getAvailableUserRolesSelect.
	 */
	public function getAvailableUserRolesSelect() {
		$rolesList = $this->getRolesList();
		$rolesListForSelect = array();
		foreach ($rolesList as $rKey => $rData) {
			$rolesListForSelect[ $rKey ] = $rData['name'];
		}
		return $rolesListForSelect;
	}

	/**
	 * getAll.
	 *
	 * @version 3.0.9
	 */
	public function getAll() {
		if (empty($this->_options)) {
			$defSendmailPath = @ini_get('sendmail_path');
			if ( empty($defSendmailPath) && !stristr($defSendmailPath, 'sendmail') ) {
				$defSendmailPath = '/usr/sbin/sendmail';
			}
			$this->_options = DispatcherWpf::applyFilters('optionsDefine', array(
				'general' => array(
					'label' => esc_html__('General', 'woo-product-filter'),
					'opts' => array(
						'send_stats' => array(
							'label' => esc_html__('Send usage statistics', 'woo-product-filter'),
							'desc'  => esc_html__('Send information about what plugin options you prefer to use, this will help us make our solution better for You.', 'woo-product-filter'),
							'def'   => '0',
							'html'  => 'checkboxHiddenVal',
						),
						'count_product_shop' => array(
							'label' => esc_html__('Set number of displayed products', 'woo-product-filter'),
							'desc'  => esc_html__('Set number of displayed products. Leave blank for the default value.', 'woo-product-filter'),
							'def'   => '',
							'html'  => 'input',
						),
						'move_sidebar' => array(
							'label' => esc_html__('Move Sidebar To Top For Mobile', 'woo-product-filter'),
							'desc'  => esc_html__('Turn on if you want the sidebar to appear above content on mobile devices. Some themes do not have blocks required for this option.', 'woo-product-filter'),
							'def'   => '0',
							'html'  => 'checkboxHiddenVal',
						),
						'not_found_products_message' => array(
							'label' => esc_html__('Display a message about not found products', 'woo-product-filter'),
							'desc'  => esc_html__('If no products were found, display a message about it', 'woo-product-filter'),
							'def'   => '0',
							'html'  => 'checkboxHiddenVal',
						),
						'content_accessibility' => array(
							'label' => esc_html__( 'Generate HTML based on WCAG standards', 'woo-product-filter' ),
							'desc'  => esc_html__( 'Use Web Content Accessibility Guidelines', 'woo-product-filter' ),
							'def'   => '0',
							'html'  => 'checkboxHiddenVal',
						),
						'disable_clean_rocket_cache' => array(
							'label' => esc_html__( 'Disable clearing WP Rocket cache', 'woo-product-filter' ),
							'desc'  => esc_html__( 'Disable clearing WP Rocket cache by saving filter settings', 'woo-product-filter' ),
							'def'   => '0',
							'html'  => 'checkboxHiddenVal',
						),
						'disable_plugin_sorting' => array(
							'label' => esc_html__( 'Disable plugin sorting', 'woo-product-filter' ),
							'desc'  => esc_html__( 'If this option is enabled, then the Product Filter by WBW will not use its sorting functionality. Woocommerce or other plugins sorting algorithms will be used.', 'woo-product-filter' ),
							'def'   => '0',
							'html'  => 'checkboxHiddenVal',
						),
						'index_group_bundle' => array(
							'label' => esc_html__( 'Indexing stockstatus for Grouped+Bundle Products', 'woo-product-filter' ),
							'desc'  => esc_html__( 'If you have grouped products and have bundle products in them and you go to properly index the stockstatus of these grouped products, then enable this option. Attention: enable this option only if it is really necessary, as it can significantly slow down the indexing process.', 'woo-product-filter' ),
							'def'   => '0',
							'html'  => 'checkboxHiddenVal',
						),
						'browser_compatibility' => array(
							'label' => esc_html__('Floating mode browser compatibility', 'woo-product-filter'),
							'desc'  => esc_html__('This option improves compatibility with certain browsers (e.g. Safari). Enable it if you notice unexpected behavior.', 'woo-product-filter'),
							'def'   => '0',
							'html'  => 'checkboxHiddenVal',
						),
					),
				),
			));
			if (class_exists('WooCommerceB2B')) {
				$this->_options['general']['opts']['use_wcb2b_prices'] = array(
					'label' => esc_html__( 'Use WooCommerce B2B prices', 'woo-product-filter' ),
					'desc'  => esc_html__( 'If you are using the WooCommerce B2B plugin and want the WBW Filter plugin to work with price lists created by this plugin, enable this option and resave the filter with the price block and then start indexing.', 'woo-product-filter' ),
					'def'   => '0',
					'html'  => 'checkboxHiddenVal',
				);
			}
			$isPro = FrameWpf::_()->getModule('promo')->isPro();
			foreach ($this->_options as $catKey => $cData) {
				foreach ($cData['opts'] as $optKey => $opt) {
					$this->_optionsToCategoires[ $optKey ] = $catKey;
					if (isset($opt['pro']) && !$isPro) {
						$this->_options[ $catKey ]['opts'][ $optKey ]['pro'] = FrameWpf::_()->getModule('promo')->generateMainLink('utm_source=plugin&utm_medium=' . $optKey . '&utm_campaign=popup');
					}
				}
			}
			$this->getModel()->fillInValues( $this->_options );
		}
		return $this->_options;
	}

	/**
	 * getFullCat.
	 */
	public function getFullCat( $cat ) {
		$this->getAll();
		return isset($this->_options[ $cat ]) ? $this->_options[ $cat ] : false;
	}

	/**
	 * getCatOpts.
	 */
	public function getCatOpts( $cat ) {
		$opts = $this->getFullCat($cat);
		return $opts ? $opts['opts'] : false;
	}

}
