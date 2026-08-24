<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Templates Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Templates extends WooBeWoo_PF_Module {

	/**
	 * Properties.
	 */
	protected $_styles = array();

	/**
	 * Constructor.
	 *
	 * @version 3.3.0
	 */
	public function __construct( $d ) {
		parent::__construct( $d );
	}

	/**
	 * init.
	 *
	 * @version 3.3.2
	 */
	public function init() {
		if ( is_admin() ) {
			$isAdminPlugOptsPage = WooBeWoo_PF_Frame::_()->isAdminPlugOptsPage();
			if ( $isAdminPlugOptsPage ) {
				$this->loadCoreJs();
				$this->loadAdminCoreJs();
				$this->loadCoreCss();
				$this->loadChosenSelects();
				WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-options', WPF_JS_PATH . 'admin.options.js', array(), false, true );
				add_action( 'admin_enqueue_scripts', array( $this, 'loadMediaScripts' ) );
				add_action( 'init', array( $this, 'connectAdditionalAdminAssets' ) );
			}
			// Some common styles - that need to be on all admin pages - be careful with them
			WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-for-all-admin-' . WPF_CODE, WPF_CSS_PATH . 'woobewoo-for-all-admin.css' );
		}
		parent::init();
	}

	/**
	 * connectAdditionalAdminAssets.
	 *
	 * @version 3.3.2
	 */
	public function connectAdditionalAdminAssets() {
		if ( is_rtl() ) {
			WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-style-rtl', WPF_CSS_PATH . 'style-rtl.css' );
		}
	}

	/**
	 * loadMediaScripts.
	 */
	public function loadMediaScripts() {
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * loadAdminCoreJs.
	 *
	 * @version 3.3.2
	 */
	public function loadAdminCoreJs() {
		WooBeWoo_PF_Frame::_()->addScript( 'jquery-ui-dialog' );
		WooBeWoo_PF_Frame::_()->addScript( 'jquery-ui-slider' );
		WooBeWoo_PF_Frame::_()->addScript(
			'woobewoo-pf-icheck',
			WPF_JS_PATH . 'icheck.min.js',
			array(
				'wp-i18n',
				'jquery-ui-widget',
				'iris',
			)
		);
		WooBeWoo_PF_Frame::_()->addScript( 'wp-color-picker' );
	}

	/**
	 * loadCoreJs.
	 *
	 * @version 3.3.2
	 */
	public function loadCoreJs() {
		WooBeWoo_PF_Frame::_()->addScript( 'jquery' );

		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-common', WPF_JS_PATH . 'common.js', array( 'jquery' ) );
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-core', WPF_JS_PATH . 'core.js', array( 'jquery' ) );

		if ( 1 == WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->get( 'price_thousands_sep' ) ) {
			WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-price-thousands-sep', WPF_JS_PATH . 'price-thousands-sep.js', array( 'jquery' ) );
		}

		if ( 1 == WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->get( 'browser_compatibility' ) ) {
			WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-browser-compatibility', WPF_JS_PATH . 'browser-compatibility.js', array( 'jquery' ) );
		}

		$ajaxurl = admin_url( 'admin-ajax.php' );
		$jsData  = array(
			'siteUrl'  => WPF_SITE_URL,
			'imgPath'  => WPF_IMG_PATH,
			'cssPath'  => WPF_CSS_PATH,
			'loader'   => WPF_LOADER_IMG,
			'close'    => WPF_IMG_PATH . 'cross.gif',
			'ajaxurl'  => $ajaxurl,
			'options'  => WooBeWoo_PF_Frame::_()->getModule( 'options' )->getAllowedPublicOptions(),
			'WPF_CODE' => WPF_CODE,
			'jsPath'   => WPF_JS_PATH,
		);
		if ( is_admin() ) {
			$jsData['isWCLicense'] = WooBeWoo_PF_Frame::_()->isWCLicense();
		}
		$jsData = WooBeWoo_PF_Dispatcher::applyFilters( 'jsInitVariables', $jsData );
		WooBeWoo_PF_Frame::_()->addJSVar( 'woobewoo-pf-core', 'WPF_DATA', $jsData );
		$this->loadTooltipster();
	}

	/**
	 * loadTooltipster.
	 *
	 * @version 3.3.2
	 */
	public function loadTooltipster() {
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-tooltipster', WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'lib/tooltipster/jquery.tooltipster.min.js' );
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-tooltipster', WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'lib/tooltipster/tooltipster.css' );
	}

	/**
	 * loadCoreCss.
	 *
	 * @version 3.3.2
	 */
	public function loadCoreCss( $isElementorEditor = false ) {
		$this->_styles = array(
			'woobewoo-pf-style'            => array(
				'path' => WPF_CSS_PATH . 'style.css',
				'for'  => 'admin',
			),
			'woobewoo-pf-ui'               => array(
				'path' => WPF_CSS_PATH . 'woobewoo-ui' . ( WooBeWoo_PF_Frame::_()->isWCLicense() ? '-wc' : '' ) . '.css',
				'for'  => 'admin',
			),
			'dashicons'                    => array( 'for' => 'admin' ),
			'woobewoo-pf-bootstrap-alerts' => array(
				'path' => WPF_CSS_PATH . 'bootstrap-alerts.css',
				'for'  => 'admin',
			),
			'woobewoo-pf-icheck'           => array(
				'path' => WPF_CSS_PATH . 'jquery.icheck.css',
				'for'  => 'admin',
			),
			'wp-color-picker'              => array( 'for' => 'admin' ),
			'woobewoo-pf-admin-ui'         => array(
				'path' => WPF_CSS_PATH . 'admin.woofilters.beautify.design.css',
				'for'  => 'admin',
			),
		);
		foreach ( $this->_styles as $s => $sInfo ) {
			if ( $isElementorEditor ) {
				$sInfo['for'] = '';
			}
			if ( ! empty( $sInfo['path'] ) ) {
				WooBeWoo_PF_Frame::_()->addStyle( $s, $sInfo['path'] );
			} else {
				WooBeWoo_PF_Frame::_()->addStyle( $s );
			}
		}
		$this->loadFontAwesome();
	}

	/**
	 * loadJqueryUi.
	 *
	 * @version 3.3.2
	 */
	public function loadJqueryUi( $slider = true ) {
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-jquery-ui', WPF_CSS_PATH . 'jquery-ui.min.css' );
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-jquery-ui-structure', WPF_CSS_PATH . 'jquery-ui.structure.min.css' );
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-jquery-ui-theme', WPF_CSS_PATH . 'jquery-ui.theme.min.css' );
		if ( $slider ) {
			WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-jquery-slider', WPF_CSS_PATH . 'jquery-slider.css' );
		}
	}

	/**
	 * loadJqGrid.
	 *
	 * @version 3.3.2
	 */
	public function loadJqGrid() {
		static $loaded = false;
		if ( ! $loaded ) {
			$this->loadJqueryUi();
			WooBeWoo_PF_Frame::_()->addScript(
				'woobewoo-pf-jq-grid',
				WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'lib/jqgrid/jquery.jqGrid.min.js'
			);
			WooBeWoo_PF_Frame::_()->addStyle(
				'woobewoo-pf-jq-grid',
				WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'lib/jqgrid/ui.jqgrid.css'
			);
			$langToLoad = UtilsWpf::getLangCode2Letter();

			$availableLocales = array( 'ar', 'bg', 'bg1251', 'cat', 'cn', 'cs', 'da', 'de', 'dk', 'el', 'en', 'es', 'fa', 'fi', 'fr', 'gl', 'he', 'hr', 'hr1250', 'hu', 'id', 'is', 'it', 'ja', 'kr', 'lt', 'mne', 'nl', 'no', 'pl', 'pt', 'pt', 'ro', 'ru', 'sk', 'sr', 'sr', 'sv', 'th', 'tr', 'tw', 'ua', 'vi' );
			if ( ! in_array( $langToLoad, $availableLocales ) ) {
				$langToLoad = 'en';
			}
			WooBeWoo_PF_Frame::_()->addScript(
				'woobewoo-pf-jq-grid-lang',
				WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'lib/jqgrid/i18n/grid.locale-' . $langToLoad . '.js'
			);

			$loaded = true;
		}
	}

	/**
	 * loadFontAwesome.
	 *
	 * @version 3.3.2
	 */
	public function loadFontAwesome() {
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-font-awesome', WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'css/font-awesome.min.css' );
	}

	/**
	 * loadChosenSelects.
	 *
	 * @version 3.3.2
	 */
	public function loadChosenSelects() {
		$modPath = WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'lib/tom-select/';
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-tom-select', $modPath . 'tom-select.min.css' );
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-tom-select', $modPath . 'tom-select.complete.min.js' );
	}

	/**
	 * loadDatePicker.
	 *
	 * @version 3.3.2
	 */
	public function loadDatePicker() {
		WooBeWoo_PF_Frame::_()->addScript( 'jquery-ui-datepicker' );
	}

	/**
	 * loadSortable.
	 *
	 * @version 3.3.2
	 */
	public function loadSortable() {
		static $loaded = false;
		if ( ! $loaded ) {
			WooBeWoo_PF_Frame::_()->addScript( 'jquery-ui-core' );
			WooBeWoo_PF_Frame::_()->addScript( 'jquery-ui-widget' );
			WooBeWoo_PF_Frame::_()->addScript( 'jquery-ui-mouse' );

			WooBeWoo_PF_Frame::_()->addScript( 'jquery-ui-draggable' );
			WooBeWoo_PF_Frame::_()->addScript( 'jquery-ui-sortable' );
			$loaded = true;
		}
	}

	/**
	 * loadBootstrap.
	 *
	 * @version 3.3.2
	 */
	public function loadBootstrap() {
		static $loaded = false;
		if ( ! $loaded ) {
			WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-bootstrap', WPF_CSS_PATH . 'bootstrap.min.css' );
			$loaded = true;
		}
	}
}
