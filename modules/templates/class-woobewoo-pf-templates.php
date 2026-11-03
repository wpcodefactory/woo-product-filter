<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Templates Class
 *
 * @version 4.0.0
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
	 * @version 3.4.0
	 */
	public function init() {
		if ( is_admin() ) {
			// 4.0.0 hardening: the skin handle is registered on admin_init (priority 1) so Pro's
			// wp_style_is() check finds it whatever the plugin load order, enqueued directly on
			// admin_enqueue_scripts (priority 1) on every plugin page, printed inline when the
			// handle still is not in the page at admin_print_styles, and kept out of CSS/JS
			// optimizers (Autoptimize, Perfmatters, LiteSpeed) through tag attributes and filters.
			add_action( 'admin_init', array( $this, 'registerSkin' ), 1 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueueSkin' ), 1 );
			add_action( 'admin_print_styles', array( $this, 'printSkinFallback' ), 100 );
			add_filter( 'style_loader_tag', array( $this, 'markStyleTag' ), 10, 3 );
			add_filter( 'script_loader_tag', array( $this, 'markScriptTag' ), 10, 3 );
			add_filter( 'autoptimize_filter_css_exclude', array( $this, 'optimizerExclude' ) );
			add_filter( 'autoptimize_filter_js_exclude', array( $this, 'optimizerExclude' ) );
			$isAdminPlugOptsPage = self::isPluginAdminPage();
			if ( $isAdminPlugOptsPage ) {
				// 4.0: plugin pages load only jQuery, the tiny core glue and the 4.0 skin.
				// No Bootstrap, jqGrid, Font Awesome, tom-select, icheck, tooltipster or the old admin CSS.
				$this->loadCoreJs( false );
				WooBeWoo_PF_Frame::_()->addStyle( self::SKIN_HANDLE, $this->getModPath() . 'css/wbw-admin.css' );
				add_filter( 'admin_body_class', array( $this, 'adminBodyClass' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'loadMediaScripts' ) );
				// 4.0 plain words: swap the most confusing labels for plain ones on plugin admin pages only.
				$this->loadPlainLabels();
				add_filter( 'gettext', array( $this, 'plainLabel' ), 20, 3 );
			}
			// Some common styles - that need to be on all admin pages - be careful with them
			WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-for-all-admin-' . WPF_CODE, WPF_CSS_PATH . 'woobewoo-for-all-admin.css' );
		}
		parent::init();
	}

	/**
	 * Style handle of the 4.0 admin skin. Pro looks this handle up with wp_style_is().
	 *
	 * @since 4.0.0
	 */
	const SKIN_HANDLE = 'woobewoo-pf-wbw-admin';

	/**
	 * Is the current request one of our admin screens? The `page` query var starts with the
	 * main menu slug: the top level menu, the WooCommerce submenu (isWCLicense) and the builder
	 * (`tab=woofilters_edit`) all share it. Safe to call before the adminmenu module exists.
	 *
	 * @since 4.0.0
	 * @return bool
	 */
	public static function isPluginAdminPage() {
		if ( ! is_admin() ) {
			return false;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, picks which admin screen we are on
		if ( '' === $page ) {
			return false;
		}
		$slug = 'wpf-filters';
		$menu = WooBeWoo_PF_Frame::_()->getModule( 'adminmenu' );
		if ( $menu && method_exists( $menu, 'getMainSlug' ) ) {
			$slug = (string) $menu->getMainSlug();
		}
		return 0 === strpos( $page, $slug );
	}

	/**
	 * Skin stylesheet URL and path.
	 *
	 * @since 4.0.0
	 */
	public function skinUrl() {
		return $this->getModPath() . 'css/wbw-admin.css';
	}

	public function skinPath() {
		return $this->getModDir() . 'css' . WPF_DS . 'wbw-admin.css';
	}

	/**
	 * admin_init (priority 1): register the skin handle early, with a file time version so a
	 * new build never fights a cached copy of the previous one under the same URL.
	 *
	 * @since 4.0.0
	 */
	public function registerSkin() {
		if ( ! wp_style_is( self::SKIN_HANDLE, 'registered' ) ) {
			wp_register_style( self::SKIN_HANDLE, $this->skinUrl(), array(), WooBeWoo_PF_Frame::assetVer( $this->skinUrl() ) );
		}
	}

	/**
	 * admin_enqueue_scripts (priority 1): enqueue the skin on every plugin screen directly,
	 * independent of the Frame's deferred style queue.
	 *
	 * @since 4.0.0
	 */
	public function enqueueSkin() {
		if ( ! self::isPluginAdminPage() ) {
			return;
		}
		$this->registerSkin();
		wp_enqueue_style( self::SKIN_HANDLE );
	}

	/**
	 * admin_print_styles (priority 100, after WordPress printed the queue): if the skin did not
	 * make it into the head on one of our screens, print it inline. Font URLs are made absolute.
	 *
	 * @since 4.0.0
	 */
	public function printSkinFallback() {
		if ( ! self::isPluginAdminPage() || wp_style_is( self::SKIN_HANDLE, 'done' ) ) {
			return;
		}
		/**
		 * Lets support turn the inline fallback off to see the diagnostic card instead.
		 *
		 * @since 4.0.0
		 * @param bool $print Print the skin inline when the stylesheet is missing. Default true.
		 */
		if ( ! apply_filters( 'woobewoo_pf_skin_inline_fallback', true ) ) {
			return;
		}
		$path = $this->skinPath();
		if ( ! is_readable( $path ) ) {
			return;
		}
		$css = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, read once as a fallback
		$css = str_replace( '../../../fonts/', WPF_PLUGINS_URL . 'fonts/', $css );
		echo '<style id="wpf-skin-inline" data-noptimize="1" data-no-minify="1" data-no-optimize="1">' . "\n" . $css . "\n" . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own stylesheet file, not user data; escaping would break the CSS
	}

	/**
	 * Is this one of our asset URLs or handles (Free or Pro)?
	 *
	 * @since 4.0.0
	 */
	public static function isOurAsset( $handle, $src ) {
		if ( 0 === strpos( (string) $handle, 'woobewoo-pf-' ) || 0 === strpos( (string) $handle, 'wpf-' ) ) {
			return true;
		}
		$src = (string) $src;
		if ( '' === $src ) {
			return false;
		}
		if ( 0 === strpos( $src, WPF_PLUGINS_URL ) || false !== strpos( $src, '/' . WPF_PLUG_NAME . '/' ) ) {
			return true;
		}
		if ( defined( 'WOOBEWOO_PF_PRO_URL' ) && 0 === strpos( $src, WOOBEWOO_PF_PRO_URL ) ) {
			return true;
		}
		return false;
	}

	/**
	 * style_loader_tag: tell Autoptimize, Perfmatters, LiteSpeed and friends to leave our tags alone.
	 *
	 * @since 4.0.0
	 */
	public function markStyleTag( $tag, $handle, $href = '' ) {
		if ( self::isOurAsset( $handle, $href ) && false === strpos( $tag, 'data-noptimize' ) ) {
			$tag = str_replace( '<link ', '<link data-noptimize="1" data-no-minify="1" data-no-optimize="1" ', $tag );
		}
		return $tag;
	}

	/**
	 * script_loader_tag: same for scripts.
	 *
	 * @since 4.0.0
	 */
	public function markScriptTag( $tag, $handle, $src = '' ) {
		// 4.0 (26/10): the classic filter's price slider is jQuery UI (WordPress core handles). When a cache plugin
		// delays jQuery UI but not our scripts, initDefaultSlider() threw and every click on the filter went dead,
		// so the three handles our storefront script needs are marked the same way as our own files.
		$coreDeps = array( 'jquery-ui-core', 'jquery-ui-mouse', 'jquery-ui-slider' );
		$ours     = self::isOurAsset( $handle, $src ) || ( in_array( $handle, $coreDeps, true ) && wp_script_is( 'woobewoo-pf-frontend-filters', 'enqueued' ) );
		if ( $ours && false === strpos( $tag, 'data-noptimize' ) ) {
			$tag = preg_replace( '/<script\b(?![^>]*data-noptimize)/', '<script data-noptimize="1" data-no-minify="1" data-no-optimize="1" data-no-defer="1"', $tag );
		}
		return $tag;
	}

	/**
	 * autoptimize_filter_css_exclude / autoptimize_filter_js_exclude: exclude both plugin folders.
	 *
	 * @since 4.0.0
	 */
	public function optimizerExclude( $exclude ) {
		$ours = array( WPF_PLUG_NAME . '/', 'woo-product-filter', 'woofilter-pro', 'wbw-admin.css' );
		if ( defined( 'WOOBEWOO_PF_PRO_URL' ) ) {
			$ours[] = basename( untrailingslashit( WOOBEWOO_PF_PRO_URL ) ) . '/';
		}
		$exclude = is_string( $exclude ) ? trim( $exclude ) : '';
		return ( '' === $exclude ? '' : $exclude . ', ' ) . implode( ', ', array_unique( $ours ) );
	}

	/**
	 * Plain label map (original label => plain words), loaded from modules/woofilters/plain-labels.php.
	 *
	 * @since 4.0.0
	 */
	protected $_plainLabels = null;

	/**
	 * loadPlainLabels.
	 *
	 * @since 4.0.0
	 */
	public function loadPlainLabels() {
		if ( is_null( $this->_plainLabels ) ) {
			$this->_plainLabels = array();
			$file               = WPF_MODULES_DIR . 'woofilters' . WPF_DS . 'plain-labels.php';
			if ( file_exists( $file ) ) {
				$map = include $file;
				if ( is_array( $map ) ) {
					$this->_plainLabels = $map;
				}
			}
			$this->_plainLabels = apply_filters( 'woobewoo_pf_plain_labels', $this->_plainLabels );
		}
		return $this->_plainLabels;
	}

	/**
	 * gettext filter: replace a known label with its plain version. Scoped to our text domain.
	 *
	 * @since 4.0.0
	 */
	public function plainLabel( $translation, $text, $domain ) {
		if ( 'woo-product-filter' !== $domain || empty( $this->_plainLabels ) ) {
			return $translation;
		}
		// Only replace untranslated labels, so real translations stay untouched.
		if ( $translation === $text && isset( $this->_plainLabels[ $text ] ) ) {
			return $this->_plainLabels[ $text ];
		}
		return $translation;
	}

	/**
	 * connectAdditionalAdminAssets.
	 *
	 * @version 3.4.0
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
	 * @version 3.4.0
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
	 * @version 3.4.0
	 */
	public function loadCoreJs( $withTooltips = true ) {
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
		if ( $withTooltips ) {
			$this->loadTooltipster();
		}
	}

	/**
	 * Body class for the 4.0 frame (full width content, no WP footer).
	 *
	 * @since 4.0.0
	 */
	public function adminBodyClass( $classes ) {
		return $classes . ' wbw-body';
	}

	/**
	 * loadTooltipster.
	 *
	 * @version 3.4.0
	 */
	public function loadTooltipster() {
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-tooltipster', WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'lib/tooltipster/jquery.tooltipster.min.js' );
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-tooltipster', WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'lib/tooltipster/tooltipster.css' );
	}

	/**
	 * loadCoreCss.
	 *
	 * @version 3.4.0
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
	 * @version 3.4.0
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
	 * @version 3.4.0
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
			$langToLoad = WooBeWoo_PF_Utils::getLangCode2Letter();

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
	 * @version 3.4.0
	 */
	public function loadFontAwesome() {
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-font-awesome', WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'css/font-awesome.min.css' );
	}

	/**
	 * loadChosenSelects.
	 *
	 * @version 3.4.0
	 */
	public function loadChosenSelects() {
		$modPath = WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'lib/tom-select/';
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-tom-select', $modPath . 'tom-select.min.css' );
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-tom-select', $modPath . 'tom-select.complete.min.js' );
	}

	/**
	 * loadDatePicker.
	 *
	 * @version 3.4.0
	 */
	public function loadDatePicker() {
		WooBeWoo_PF_Frame::_()->addScript( 'jquery-ui-datepicker' );
	}

	/**
	 * loadSortable.
	 *
	 * @version 3.4.0
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
	 * @version 3.4.0
	 */
	public function loadBootstrap() {
		static $loaded = false;
		if ( ! $loaded ) {
			WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-bootstrap', WPF_CSS_PATH . 'bootstrap.min.css' );
			$loaded = true;
		}
	}
}
