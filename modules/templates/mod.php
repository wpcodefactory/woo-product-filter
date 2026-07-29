<?php
/**
 * Product Filter by WBW - TemplatesWpf Class
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class TemplatesWpf extends ModuleWpf {

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
	 * @version 3.3.0
	 */
	public function init() {
		if ( is_admin() ) {
			$isAdminPlugOptsPage = FrameWpf::_()->isAdminPlugOptsPage();
			if ( $isAdminPlugOptsPage ) {
				$this->loadCoreJs();
				$this->loadAdminCoreJs();
				$this->loadCoreCss();
				$this->loadChosenSelects();
				FrameWpf::_()->addScript( 'woobewoo-pf-admin-options', WPF_JS_PATH . 'admin.options.js', array(), false, true );
				add_action( 'admin_enqueue_scripts', array( $this, 'loadMediaScripts' ) );
				add_action( 'init', array( $this, 'connectAdditionalAdminAssets' ) );
			}
			// Some common styles - that need to be on all admin pages - be careful with them
			FrameWpf::_()->addStyle( 'woobewoo-pf-for-all-admin-' . WPF_CODE, WPF_CSS_PATH . 'woobewoo-for-all-admin.css' );
		}
		parent::init();
	}

	/**
	 * connectAdditionalAdminAssets.
	 *
	 * @version 3.3.0
	 */
	public function connectAdditionalAdminAssets() {
		if ( is_rtl() ) {
			FrameWpf::_()->addStyle( 'woobewoo-pf-style-rtl', WPF_CSS_PATH . 'style-rtl.css' );
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
	 * @version 3.3.0
	 */
	public function loadAdminCoreJs() {
		FrameWpf::_()->addScript( 'jquery-ui-dialog' );
		FrameWpf::_()->addScript( 'jquery-ui-slider' );
		FrameWpf::_()->addScript(
			'woobewoo-pf-icheck',
			WPF_JS_PATH . 'icheck.min.js',
			array(
				'wp-i18n',
				'jquery-ui-widget',
				'iris',
			)
		);
		FrameWpf::_()->addScript( 'wp-color-picker' );
	}

	/**
	 * loadCoreJs.
	 *
	 * @version 3.3.0
	 */
	public function loadCoreJs() {
		FrameWpf::_()->addScript( 'jquery' );

		FrameWpf::_()->addScript( 'woobewoo-pf-common', WPF_JS_PATH . 'common.js', array( 'jquery' ) );
		FrameWpf::_()->addScript( 'woobewoo-pf-core', WPF_JS_PATH . 'core.js', array( 'jquery' ) );

		if ( 1 == FrameWpf::_()->getModule( 'options' )->getModel()->get( 'price_thousands_sep' ) ) {
			FrameWpf::_()->addScript( 'woobewoo-pf-price-thousands-sep', WPF_JS_PATH . 'price-thousands-sep.js', array( 'jquery' ) );
		}

		if ( 1 == FrameWpf::_()->getModule( 'options' )->getModel()->get( 'browser_compatibility' ) ) {
			FrameWpf::_()->addScript( 'woobewoo-pf-browser-compatibility', WPF_JS_PATH . 'browser-compatibility.js', array( 'jquery' ) );
		}

		$ajaxurl = admin_url( 'admin-ajax.php' );
		$jsData  = array(
			'siteUrl'  => WPF_SITE_URL,
			'imgPath'  => WPF_IMG_PATH,
			'cssPath'  => WPF_CSS_PATH,
			'loader'   => WPF_LOADER_IMG,
			'close'    => WPF_IMG_PATH . 'cross.gif',
			'ajaxurl'  => $ajaxurl,
			'options'  => FrameWpf::_()->getModule( 'options' )->getAllowedPublicOptions(),
			'WPF_CODE' => WPF_CODE,
			'jsPath'   => WPF_JS_PATH,
		);
		if ( is_admin() ) {
			$jsData['isWCLicense'] = FrameWpf::_()->isWCLicense();
		}
		$jsData = DispatcherWpf::applyFilters( 'jsInitVariables', $jsData );
		FrameWpf::_()->addJSVar( 'woobewoo-pf-core', 'WPF_DATA', $jsData );
		$this->loadTooltipster();
	}

	/**
	 * loadTooltipster.
	 *
	 * @version 3.3.0
	 */
	public function loadTooltipster() {
		FrameWpf::_()->addScript( 'woobewoo-pf-tooltipster', FrameWpf::_()->getModule( 'templates' )->getModPath() . 'lib/tooltipster/jquery.tooltipster.min.js' );
		FrameWpf::_()->addStyle( 'woobewoo-pf-tooltipster', FrameWpf::_()->getModule( 'templates' )->getModPath() . 'lib/tooltipster/tooltipster.css' );
	}

	/**
	 * loadCoreCss.
	 *
	 * @version 3.3.0
	 */
	public function loadCoreCss( $isElementorEditor = false ) {
		$this->_styles = array(
			'woobewoo-pf-style'            => array(
				'path' => WPF_CSS_PATH . 'style.css',
				'for'  => 'admin',
			),
			'woobewoo-pf-ui'               => array(
				'path' => WPF_CSS_PATH . 'woobewoo-ui' . ( FrameWpf::_()->isWCLicense() ? '-wc' : '' ) . '.css',
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
				FrameWpf::_()->addStyle( $s, $sInfo['path'] );
			} else {
				FrameWpf::_()->addStyle( $s );
			}
		}
		$this->loadFontAwesome();
	}

	/**
	 * loadJqueryUi.
	 *
	 * @version 3.3.0
	 */
	public function loadJqueryUi( $slider = true ) {
		FrameWpf::_()->addStyle( 'woobewoo-pf-jquery-ui', WPF_CSS_PATH . 'jquery-ui.min.css' );
		FrameWpf::_()->addStyle( 'woobewoo-pf-jquery-ui-structure', WPF_CSS_PATH . 'jquery-ui.structure.min.css' );
		FrameWpf::_()->addStyle( 'woobewoo-pf-jquery-ui-theme', WPF_CSS_PATH . 'jquery-ui.theme.min.css' );
		if ( $slider ) {
			FrameWpf::_()->addStyle( 'woobewoo-pf-jquery-slider', WPF_CSS_PATH . 'jquery-slider.css' );
		}
	}

	/**
	 * loadJqGrid.
	 *
	 * @version 3.3.0
	 */
	public function loadJqGrid() {
		static $loaded = false;
		if ( ! $loaded ) {
			$this->loadJqueryUi();
			FrameWpf::_()->addScript(
				'woobewoo-pf-jq-grid',
				FrameWpf::_()->getModule( 'templates' )->getModPath() . 'lib/jqgrid/jquery.jqGrid.min.js'
			);
			FrameWpf::_()->addStyle(
				'woobewoo-pf-jq-grid',
				FrameWpf::_()->getModule( 'templates' )->getModPath() . 'lib/jqgrid/ui.jqgrid.css'
			);
			$langToLoad = UtilsWpf::getLangCode2Letter();

			$availableLocales = array( 'ar', 'bg', 'bg1251', 'cat', 'cn', 'cs', 'da', 'de', 'dk', 'el', 'en', 'es', 'fa', 'fi', 'fr', 'gl', 'he', 'hr', 'hr1250', 'hu', 'id', 'is', 'it', 'ja', 'kr', 'lt', 'mne', 'nl', 'no', 'pl', 'pt', 'pt', 'ro', 'ru', 'sk', 'sr', 'sr', 'sv', 'th', 'tr', 'tw', 'ua', 'vi' );
			if ( ! in_array( $langToLoad, $availableLocales ) ) {
				$langToLoad = 'en';
			}
			FrameWpf::_()->addScript(
				'woobewoo-pf-jq-grid-lang',
				FrameWpf::_()->getModule( 'templates' )->getModPath() . 'lib/jqgrid/i18n/grid.locale-' . $langToLoad . '.js'
			);

			$loaded = true;
		}
	}

	/**
	 * loadFontAwesome.
	 *
	 * @version 3.3.0
	 */
	public function loadFontAwesome() {
		FrameWpf::_()->addStyle( 'woobewoo-pf-font-awesome', FrameWpf::_()->getModule( 'templates' )->getModPath() . 'css/font-awesome.min.css' );
	}

	/**
	 * loadChosenSelects.
	 *
	 * @version 3.3.0
	 */
	public function loadChosenSelects() {
		$modPath = FrameWpf::_()->getModule( 'templates' )->getModPath() . 'lib/tom-select/';
		FrameWpf::_()->addStyle( 'woobewoo-pf-tom-select', $modPath . 'tom-select.min.css' );
		FrameWpf::_()->addScript( 'woobewoo-pf-tom-select', $modPath . 'tom-select.complete.min.js' );
	}

	/**
	 * loadDatePicker.
	 */
	public function loadDatePicker() {
		FrameWpf::_()->addScript( 'jquery-ui-datepicker' );
	}

	/**
	 * loadSortable.
	 */
	public function loadSortable() {
		static $loaded = false;
		if ( ! $loaded ) {
			FrameWpf::_()->addScript( 'jquery-ui-core' );
			FrameWpf::_()->addScript( 'jquery-ui-widget' );
			FrameWpf::_()->addScript( 'jquery-ui-mouse' );

			FrameWpf::_()->addScript( 'jquery-ui-draggable' );
			FrameWpf::_()->addScript( 'jquery-ui-sortable' );
			$loaded = true;
		}
	}

	/**
	 * loadBootstrap.
	 *
	 * @version 3.3.0
	 */
	public function loadBootstrap() {
		static $loaded = false;
		if ( ! $loaded ) {
			FrameWpf::_()->addStyle( 'woobewoo-pf-bootstrap', WPF_CSS_PATH . 'bootstrap.min.css' );
			$loaded = true;
		}
	}
}
