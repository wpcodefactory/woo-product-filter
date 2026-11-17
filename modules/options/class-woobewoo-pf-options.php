<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Options Class
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Options extends WooBeWoo_PF_Module {

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
	 *
	 * @version 3.4.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'startSession' ), -1 );
		add_action( 'init', array( $this, 'initAllOptValues' ), 99 ); // It should be init after all languages was inited
		WooBeWoo_PF_Dispatcher::addFilter( 'mainAdminTabs', array( $this, 'addAdminTab' ) );
		// 4.0: keep the rail and the WordPress submenu identical (Setup, Filters, Speed and SEO, Help, Upgrade, Pro).
		WooBeWoo_PF_Dispatcher::addFilter( 'mainAdminTabs', array( $this, 'hideLegacyTabs' ), 999 );
		WooBeWoo_PF_Dispatcher::addFilter( 'adminMenuOptions', array( $this, 'mirrorRailInSubmenu' ), 999 );
	}

	/**
	 * startSession.
	 *
	 * @version 3.4.0
	 */
	public function startSession() {
		$isMultiLogicOr = false;
		$filters        = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel()->getFromTbl();

		foreach ( $filters as $filter ) {
			$filtersSettings = function_exists( 'woobewoo_pf_decode_settings' ) ? woobewoo_pf_decode_settings( $filter['setting_data'] ) : maybe_unserialize( $filter['setting_data'] );
			if ( ! is_array( $filtersSettings ) || empty( $filtersSettings['settings'] ) || ! is_array( $filtersSettings['settings'] ) ) {
				continue;
			}
			$multiLogic      = $this->getFilterSetting( $filtersSettings['settings'], 'f_multi_logic', 'and' );

			if ( 'or' === $multiLogic ) {
				$isMultiLogicOr = true;
				break;
			}
		}

		if ( $isMultiLogicOr ) {
			WooBeWoo_PF_Req::startSession();
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
		return $this->getModel()->get( $code );
	}

	/**
	 * This method provides fast access to options model method get.
	 *
	 * @see optionsModel::get($d)
	 */
	public function isEmpty( $code ) {
		return $this->getModel()->isEmpty( $code );
	}

	/**
	 * getAllowedPublicOptions.
	 */
	public function getAllowedPublicOptions() {
		$allowKeys = array( 'add_love_link', 'disable_autosave' );
		$res       = array();
		foreach ( $allowKeys as $k ) {
			$res[ $k ] = $this->get( $k );
		}
		return $res;
	}

	/**
	 * getAdminPage.
	 *
	 * @version 3.4.0
	 */
	public function getAdminPage() {
		if ( ! WooBeWoo_PF_Installer::isUsed() ) {
			WooBeWoo_PF_Installer::setUsed(); // Show this welcome page - only one time
			WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->save( 'plug_welcome_show', time() ); // Remember this
		}
		return $this->getView()->getAdminPage();
	}

	/**
	 * addAdminTab.
	 *
	 * @version 3.4.0
	 */
	public function addAdminTab( $tabs ) {
		// 4.0: the old Settings screen lives inside Help (Advanced settings); the tab stays for old links.
		$tabs['settings'] = array(
			'label'      => esc_html__( 'Settings', 'woo-product-filter' ),
			'callback'   => array( $this, 'getSettingsTabContent' ),
			'fa_icon'    => 'fa-gear',
			'sort_order' => 30,
			'hidden'     => 1,
		);
		if (
			! ( WooBeWoo_PF_Frame::_()->moduleExists( 'license' ) && WooBeWoo_PF_Frame::_()->getModule( 'license' ) ) &&
			! WooBeWoo_PF_Frame::_()->isWCLicense()
		) {
			$tabs['gopro'] = array(
				'label'      => esc_html__( 'Upgrade', 'woo-product-filter' ),
				'callback'   => array( $this, 'getProTabContent' ),
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
		$file = WPF_MODULES_DIR . 'upgrade' . WPF_DS . 'class-woobewoo-pf-upgrade-screen.php';
		if ( file_exists( $file ) ) {
			require_once $file;
			return WooBeWoo_PF_Upgrade_Screen::render();
		}
		return $this->getView()->getProTabContent();
	}

	/**
	 * Tabs that are not screens in 4.0 (Overview, Add New Filter) are kept callable but hidden
	 * from the rail and the WordPress submenu.
	 *
	 * @since 4.0.0
	 */
	public function hideLegacyTabs( $tabs ) {
		foreach ( array( 'overview', 'woofilters#wpfadd', 'settings' ) as $key ) {
			if ( isset( $tabs[ $key ] ) ) {
				$tabs[ $key ]['hidden'] = 1;
			}
		}
		if ( isset( $tabs['woofilters'] ) ) {
			$tabs['woofilters']['label'] = esc_html__( 'Filters', 'woo-product-filter' );
		}
		if ( isset( $tabs['woofilters_edit'] ) ) {
			$tabs['woofilters_edit']['full_width'] = true;
		}
		return $tabs;
	}

	/**
	 * Rail order and groups. Main: Setup, tabs with rail_group "setup", Filters, Speed and SEO,
	 * Help, Upgrade. Pro: tabs with rail_group "pro" in the order they were registered.
	 *
	 * @since 4.0.0
	 */
	public function getRailGroups( $tabs = null ) {
		if ( is_null( $tabs ) ) {
			$tabs = $this->getTabs();
		}
		$main = array();
		$pro  = array();
		$add  = function ( $key ) use ( &$main, $tabs ) {
			if ( isset( $tabs[ $key ] ) && empty( $tabs[ $key ]['hidden'] ) ) {
				$main[ $key ] = $tabs[ $key ];
			}
		};
		$add( 'setup' );
		foreach ( $tabs as $key => $t ) {
			if ( isset( $t['rail_group'] ) && 'setup' === $t['rail_group'] && empty( $t['hidden'] ) ) {
				$main[ $key ] = $t;
			}
		}
		$add( 'woofilters' );
		$add( 'speed' );
		$add( 'help' );
		$add( 'gopro' );
		foreach ( $tabs as $key => $t ) {
			if ( isset( $t['rail_group'] ) && 'pro' === $t['rail_group'] && empty( $t['hidden'] ) && ! isset( $main[ $key ] ) ) {
				$pro[ $key ] = $t;
			}
		}
		foreach ( $main as $key => $t ) {
			if ( ! isset( $main[ $key ]['url'] ) ) {
				$main[ $key ]['url'] = $this->getTabUrl( $key );
			}
		}
		foreach ( $pro as $key => $t ) {
			if ( ! isset( $pro[ $key ]['url'] ) ) {
				$pro[ $key ]['url'] = $this->getTabUrl( $key );
			}
		}
		return array( 'main' => $main, 'pro' => $pro );
	}

	/**
	 * Which rail item is lit for the active tab (children light their parent).
	 *
	 * @since 4.0.0
	 */
	public function getRailActiveKey( $activeTab, $tabs = null ) {
		if ( is_null( $tabs ) ) {
			$tabs = $this->getTabs();
		}
		if ( 'woofilters_edit' === $activeTab || 'woofilters#wpfadd' === $activeTab ) {
			return 'woofilters';
		}
		if ( 'settings' === $activeTab || 'overview' === $activeTab ) {
			return 'help';
		}
		if ( isset( $tabs[ $activeTab ]['child_of'] ) ) {
			return $tabs[ $activeTab ]['child_of'];
		}
		return $activeTab;
	}

	/**
	 * Inline SVG icons for the rail (24 grid, 1.8 stroke, currentColor).
	 *
	 * @since 4.0.0
	 */
	public function getRailIcons() {
		return array(
			'setup'      => '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4z"/><path d="M5 17l.7 1.6L7.3 19.3l-1.6.7L5 21.6l-.7-1.6-1.6-.7 1.6-.7z"/><path d="M18.5 16l.6 1.4 1.4.6-1.4.6-.6 1.4-.6-1.4-1.4-.6 1.4-.6z"/></svg>',
			'woofilters' => '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16"/><path d="M7 12h10"/><path d="M10 19h4"/></svg>',
			'speed'      => '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 14a8 8 0 1 1 16 0"/><path d="M12 14l4-5"/><path d="M4 20h16"/></svg>',
			'help'       => '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>',
			'gopro'      => '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2.5"/><path d="M8 11V8a4 4 0 0 1 7.5-1.9"/></svg>',
			'importers'  => '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h12"/><path d="M12 8l4 4-4 4"/><path d="M20 4v16"/></svg>',
			'default'    => '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/></svg>',
		);
	}

	/**
	 * wp_kses allow list for inline SVG icons passed by other modules.
	 *
	 * @since 4.0.0
	 */
	public function getSvgKses() {
		return array(
			'svg'      => array( 'class' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true, 'xmlns' => true, 'focusable' => true, 'role' => true ),
			'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'g'        => array( 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true ),
		);
	}

	/**
	 * The WordPress submenu under "Product Filter" mirrors the rail exactly.
	 *
	 * @since 4.0.0
	 */
	public function mirrorRailInSubmenu( $subMenus ) {
		$groups = $this->getRailGroups();
		$slug   = WooBeWoo_PF_Frame::_()->getModule( 'adminmenu' )->getMainSlug();
		$out    = array();
		foreach ( array_merge( $groups['main'], $groups['pro'] ) as $key => $t ) {
			$out[] = array(
				'title'      => $t['label'],
				'capability' => WooBeWoo_PF_Frame::_()->getModule( 'adminmenu' )->getMainCap(),
				'menu_slug'  => 'admin.php?page=' . $slug . '&tab=' . $key,
				'function'   => '',
			);
		}
		return $out;
	}

	/**
	 * getTabs.
	 *
	 * @version 3.4.0
	 */
	public function getTabs() {
		if ( empty( $this->_tabs ) ) {
			$this->_tabs = WooBeWoo_PF_Dispatcher::applyFilters( 'mainAdminTabs', array() );
			foreach ( $this->_tabs as $tabKey => $tab ) {
				if ( ! isset( $this->_tabs[ $tabKey ]['url'] ) ) {
					$this->_tabs[ $tabKey ]['url'] = is_array( $tab['callback'] ) ? $this->getTabUrl( $tabKey ) : $tab['callback'];
				}
			}
			uasort( $this->_tabs, array( $this, 'sortTabsClb' ) );
		}

		return $this->_tabs;
	}

	/**
	 * sortTabsClb.
	 */
	public function sortTabsClb( $a, $b ) {
		if ( isset( $a['sort_order'] ) && isset( $b['sort_order'] ) ) {
			if ( $a['sort_order'] > $b['sort_order'] ) {
				return 1;
			}
			if ( $a['sort_order'] < $b['sort_order'] ) {
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
		return isset( $this->_tabs[ $tabKey ] ) ? $this->_tabs[ $tabKey ] : false;
	}

	/**
	 * getActiveTab.
	 *
	 * @version 3.4.0
	 */
	public function getActiveTab() {
		$reqTab = sanitize_text_field( WooBeWoo_PF_Req::getVar( 'tab' ) );
		return empty( $reqTab ) ? 'woofilters' : $reqTab;
	}

	/**
	 * getTabUrl.
	 *
	 * @version 3.4.0
	 */
	public function getTabUrl( $tab = '' ) {
		static $mainUrl;
		if ( empty( $mainUrl ) ) {
			$mainUrl = WooBeWoo_PF_Frame::_()->getModule( 'adminmenu' )->getMainLink();
		}
		return empty( $tab ) ? $mainUrl : $mainUrl . '&tab=' . $tab;
	}

	/**
	 * getRolesList.
	 */
	public function getRolesList() {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . '/wp-admin/includes/user.php';
		}
		return get_editable_roles();
	}

	/**
	 * getAvailableUserRolesSelect.
	 */
	public function getAvailableUserRolesSelect() {
		$rolesList          = $this->getRolesList();
		$rolesListForSelect = array();
		foreach ( $rolesList as $rKey => $rData ) {
			$rolesListForSelect[ $rKey ] = $rData['name'];
		}
		return $rolesListForSelect;
	}

	/**
	 * getAll.
	 *
	 * @version 3.4.0
	 */
	public function getAll() {
		if ( empty( $this->_options ) ) {
			$defSendmailPath = @ini_get( 'sendmail_path' );
			if ( empty( $defSendmailPath ) && ! stristr( $defSendmailPath, 'sendmail' ) ) {
				$defSendmailPath = '/usr/sbin/sendmail';
			}
			$this->_options = WooBeWoo_PF_Dispatcher::applyFilters(
				'optionsDefine',
				array(
					'general' => array(
						'label' => esc_html__( 'General', 'woo-product-filter' ),
						'opts'  => array(
							'count_product_shop'         => array(
								'label' => esc_html__( 'Set number of displayed products', 'woo-product-filter' ),
								'desc'  => esc_html__( 'Set number of displayed products. Leave blank for the default value.', 'woo-product-filter' ),
								'def'   => '',
								'html'  => 'input',
							),
							'move_sidebar'               => array(
								'label' => esc_html__( 'Move sidebar to top for mobile', 'woo-product-filter' ),
								'desc'  => esc_html__( 'Turn on if you want the sidebar to appear above content on mobile devices. Some themes do not have blocks required for this option.', 'woo-product-filter' ),
								'def'   => '0',
								'html'  => 'checkboxHiddenVal',
							),
							'not_found_products_message' => array(
								'label' => esc_html__( 'Display a message about not found products', 'woo-product-filter' ),
								'desc'  => esc_html__( 'If no products were found, display a message about it', 'woo-product-filter' ),
								'def'   => '0',
								'html'  => 'checkboxHiddenVal',
							),
							'content_accessibility'      => array(
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
							'disable_plugin_sorting'     => array(
								'label' => esc_html__( 'Disable plugin sorting', 'woo-product-filter' ),
								'desc'  => esc_html__( 'If this option is enabled, then the Product Filter by WBW will not use its sorting functionality. Woocommerce or other plugins sorting algorithms will be used.', 'woo-product-filter' ),
								'def'   => '0',
								'html'  => 'checkboxHiddenVal',
							),
							'index_group_bundle'         => array(
								'label' => esc_html__( 'Indexing stockstatus for grouped+bundle products', 'woo-product-filter' ),
								'desc'  => esc_html__( 'If you have grouped products and have bundle products in them and you go to properly index the stockstatus of these grouped products, then enable this option. Attention: enable this option only if it is really necessary, as it can significantly slow down the indexing process.', 'woo-product-filter' ),
								'def'   => '0',
								'html'  => 'checkboxHiddenVal',
							),
							'browser_compatibility'      => array(
								'label' => esc_html__( 'Floating mode browser compatibility', 'woo-product-filter' ),
								'desc'  => esc_html__( 'This option improves compatibility with certain browsers (e.g. Safari). Enable it if you notice unexpected behavior.', 'woo-product-filter' ),
								'def'   => '0',
								'html'  => 'checkboxHiddenVal',
							),
						),
					),
				)
			);
			if ( class_exists( 'WooCommerceB2B' ) ) {
				$this->_options['general']['opts']['use_wcb2b_prices'] = array(
					'label' => esc_html__( 'Use WooCommerce B2B prices', 'woo-product-filter' ),
					'desc'  => esc_html__( 'If you are using the WooCommerce B2B plugin and want the WBW Filter plugin to work with price lists created by this plugin, enable this option and resave the filter with the price block and then start indexing.', 'woo-product-filter' ),
					'def'   => '0',
					'html'  => 'checkboxHiddenVal',
				);
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
		return isset( $this->_options[ $cat ] ) ? $this->_options[ $cat ] : false;
	}

	/**
	 * getCatOpts.
	 */
	public function getCatOpts( $cat ) {
		$opts = $this->getFullCat( $cat );
		return $opts ? $opts['opts'] : false;
	}
}
