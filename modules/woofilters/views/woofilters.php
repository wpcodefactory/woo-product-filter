<?php
class WoofiltersViewWpf extends ViewWpf {
	private static $uniqueBlockId  = 0;
	private static $filterOrderKey = 0;
	protected static $blockId      = '';
	protected static $filtersCss   = '';
	protected static $isLeerFilter = false;

	public $settings                       = array(
		'settings' => array(),
	);
	public $proLink                        = '';
	public $linkSetting                    = '';
	public $filter                         = array(
		'id' => 0,
	);
	protected static $currentSettings      = array();
	protected static $currentFilterRecount = false;

	/**
	 * Filters with Taxonomy base terms list
	 * We collect it with paying attention on a current wp_query and filtering results
	 *
	 * @var null|array
	 */
	public static $filterExistsTerms = null;

	/**
	 * Filters with Taxonomy base terms list
	 * We collect it without paying attention on a curren wp_query and filtering results
	 *
	 * @var null|array
	 */
	public static $filterExistsTermsWithotFiltering = null;

	/**
	 * Exist Filters with user list
	 * We collect it with paying attention on a current wp_query and filtering results
	 *
	 * @var null|array
	 */
	public static $filterExistsUsers;

	/**
	 * Exist Filters with prices
	 * We collect it with paying attention on a current wp_query and filtering results
	 *
	 * @var null|array
	 */
	public static $filterExistsPrices;

	/**
	 * Filter id
	 *
	 * @var int
	 */
	public $filterId;

	/**
	 * Post type product taxonomies dependency with standard product_cat as a keys.
	 *
	 * @var array
	 */
	public $taxonomyOptionality = array(
		'pwb-brand' => array(
			'thumbnail_id' => 'pwb_brand_image',
		),
	);

	/**
	 * Specifies filter blocks to add indexes to
	 *
	 * @var array
	 */
	public $needIndex = array();

	public static $filterExistsTermsJS = '';

	public static $leer;

	public function setCurrentSettings( $settings ) {
		if ( ! isset($settings['settings']) ) {
			$settings['settings'] = array();
		}
		self::$currentSettings = $settings;
	}
	public function getCurrentSettings( $id = 0 ) {
		if ( ! empty($id) ) {
			$settings = FrameWpf::_()->getModule('woofilters')->getModel('settings')->getFilterBlockSettings($id);
			if ( ! isset($settings['settings']) ) {
				$settings['settings'] = array();
			}
			self::$currentSettings = $settings;
		}
		return self::$currentSettings;
	}

	public function getTabContent() {
		$addWC = ( FrameWpf::_()->isWCLicense() ? '-wc' : '' );
		FrameWpf::_()->getModule('templates')->loadJqGrid();
		FrameWpf::_()->addScript('admin.woofilters.list', $this->getModule()->getModPath() . 'js/admin.woofilters.list.js');
		FrameWpf::_()->addScript('adminCreateTableWpf', $this->getModule()->getModPath() . 'js/create-filter.js', array(), false, true);
		FrameWpf::_()->getModule('templates')->loadFontAwesome();
		FrameWpf::_()->addJSVar('admin.woofilters.list', 'wpfTblDataUrl', UriWpf::mod('woofilters', 'getListForTbl', array('reqType' => 'ajax')));
		FrameWpf::_()->addJSVar('admin.woofilters.list', 'url', admin_url('admin-ajax.php'));
		FrameWpf::_()->getModule('templates')->loadBootstrap();
		FrameWpf::_()->addStyle('admin.filters', $this->getModule()->getModPath() . 'css/admin.woofilters' . $addWC . '.css');
		$proLink = FrameWpf::_()->getModule('adminmenu')->getMainLink() . '&tab=gopro';

		$this->assign('proLink', $proLink);
		$this->assign('addNewLink', FrameWpf::_()->getModule('options')->getTabUrl('woofilters#wpfadd'));

		return parent::getContent('woofiltersAdmin');
	}

	public function getEditTabContent( $idIn ) {
		
		$isWooCommercePluginActivated = $this->getModule()->isWooCommercePluginActivated();
		if ( ! $isWooCommercePluginActivated ) {
			return;
		}
		$idIn     = isset($idIn) ? (int) $idIn : 0;
		$filter   = $this->getModel('woofilters')->getById($idIn);
		$settings = unserialize($filter['setting_data']);
		$modPath  = $this->getModule()->getModPath();
		$addWC    = ( FrameWpf::_()->isWCLicense() ? '-wc' : '' );
		FrameWpf::_()->getModule('templates')->loadChosenSelects();
		FrameWpf::_()->getModule('templates')->loadBootstrap();
		FrameWpf::_()->addScript('notify-js', WPF_JS_PATH . 'notify.js', array(), false, true);
		FrameWpf::_()->addScript('chosen.order.jquery.min.js', $modPath . 'js/chosen.order.jquery.min.js');
		FrameWpf::_()->addScript('admin.filters', $modPath . 'js/admin.woofilters.js');
		FrameWpf::_()->addScript('admin.wp.colorpicker.alhpa.js', WPF_JS_PATH . 'admin.wp.colorpicker.alpha.js', array('wp-color-picker'));
		FrameWpf::_()->addJSVar('wp-color-picker', 'wpColorPickerL10n', array());
		FrameWpf::_()->addScript('adminCreateTableWpf', $modPath . 'js/create-filter.js', array(), false, true);
		FrameWpf::_()->addJSVar('admin.filters', 'url', admin_url('admin-ajax.php'));

		FrameWpf::_()->addStyle('admin.filters', $modPath . 'css/admin.woofilters' . $addWC . '.css');

		$this->addCommonAssets($modPath);
		$this->addPluginCustomStyles($modPath, true);

		FrameWpf::_()->addScript('jquery.slider.js.jshashtable', $modPath . 'js/jquery_slider/jshashtable-2.1_src.js');
		FrameWpf::_()->addScript('jquery.slider.js.numberformatter', $modPath . 'js/jquery_slider/jquery.numberformatter-1.2.3.js');
		FrameWpf::_()->addScript('jquery.slider.js.dependClass', $modPath . 'js/jquery_slider/jquery.dependClass-0.1.js');
		FrameWpf::_()->addScript('jquery.slider.js.draggable', $modPath . 'js/jquery_slider/draggable-0.1.js');
		FrameWpf::_()->addScript('jquery.slider.js', $modPath . 'js/jquery_slider/jquery.slider.js');
		FrameWpf::_()->addStyle('jquery.slider.css', $modPath . 'css/jquery.slider.min.css');

		FrameWpf::_()->addStyle('loaders', $modPath . 'css/loaders.css');

		DispatcherWpf::doAction('addScriptsContent', true, $settings);

		$link        = FrameWpf::_()->getModule('options')->getTabUrl( $this->getCode() );
		$linkSetting = FrameWpf::_()->getModule('options')->getTabUrl( 'settings' );
		$proLink     = FrameWpf::_()->getModule('adminmenu')->getMainLink() . '&tab=gopro';

		$this->assign('proLink', $proLink);
		$this->assign('link', $link);
		$this->assign('linkSetting', $linkSetting);
		$this->assign('settings', $settings);
		$this->assign('filter', $filter);
		$this->assign('is_pro', FrameWpf::_()->isPro());

		return parent::getContent('woofiltersEditAdmin');
	}

	public function renderHtml( $params ) {
		$isWooCommercePluginActivated = $this->getModule()->isWooCommercePluginActivated();

		if ( ! $isWooCommercePluginActivated ) {
			return;
		}

		$id = isset($params['id']) ? (int) $params['id'] : 0;
		if ( ! $id ) {
			return false;
		}

		FrameWpf::_()->getModule('meta')->calcNeededMetaValues(true);

		$this->assign('filterId', $id);
		$this->filter['id'] = $id;

		// preview case
		if ( isset( $params['settings'] ) ) {
			$params['settings']['filters']['order'] = stripcslashes( $params['settings']['filters']['order'] );
			if ( ! empty( $params['settings']['css_editor'] ) ) {
				$params['settings']['css_editor'] = base64_encode( $params['settings']['css_editor'] );
			}
			$settings = $params;
			// other
		} else {
			$settings = $this->getCurrentSettings( $id );
		}

		if ( defined( 'WPF_FREE_REQUIRES' ) && version_compare( '1.4.9', WPF_FREE_REQUIRES, '==' ) ) {
			$preselects = DispatcherWpf::applyFilters( 'addDefaultFilterData', array(), $id, $settings );
		} else {
			DispatcherWpf::doAction( 'addDefaultFilterData', $id, $settings );
		}

		$recalculateFilters = $this->getFilterSetting( $settings['settings'], 'recalculate_filters', false );
		if ( '1' === ReqWpf::getVar( 'wpf_skip' ) && ! $recalculateFilters ) {
			$fid = ReqWpf::getVar( 'wpf_fid' );
			if ( $fid ) {
				$this->assign('html', '<div class="wpfExistsTermsJS" data-fid="' . esc_attr($fid) . '"></div>');
				return parent::getContent('woofiltersHtml');
			}
			return false;
		}

		if ( ! $settings || empty($settings['settings']) ) {
			return false;
		}
		$isWidget = $this->getFilterSetting($params, 'mode', '') == 'widget';

		$module = $this->getModule();
		$mode   = $module->getRenderMode($id, $settings, $isWidget);
		if ( empty($mode) ) {
			return false;
		}

		$html = '';
		
		$modPath = $module->getModPath();
		$module->setCurrentFilter($id, $isWidget);

		$this->addRenderHtmlAssets($modPath, $settings);
		$this->addPluginCustomStyles($modPath, $this->isCustomStyle($settings['settings']));

		DispatcherWpf::doAction('addScriptsContent', false, $settings);

		$viewId = $id . '_' . mt_rand(0, 999999);

		switch ( $mode ) {
			case 1: //categoty page
				$catObj = get_queried_object();
				$html   = $this->generateFiltersHtml($settings, $viewId, $catObj->term_id);
				break;
			case 2: //shop page
				$html = $this->generateFiltersHtml($settings, $viewId);
				break;
			case 3: //tag page
				$catObj = get_queried_object();
				$html   = $this->generateFiltersHtml($settings, $viewId, false, false, array('product_tag' => $catObj->term_id));
				break;
			case 4: //brand page
				$catObj = get_queried_object();
				$html   = $this->generateFiltersHtml($settings, $viewId, false, false, array('product_brand' => $catObj->term_id));
				break;
			case 5: //perfect brand page
				$catObj = get_queried_object();
				$html   = $this->generateFiltersHtml($settings, $viewId, false, false, array('pwb-brand' => $catObj->term_id));
				break;
			case 6: //attribute page
				$catObj = get_queried_object();
				$html   = $this->generateFiltersHtml($settings, $viewId, false, false, array($catObj->taxonomy => $catObj->term_id));
				break;
			case 7: //vendor page
				$html = $this->generateFiltersHtml( $settings, $viewId, false, false, array( 'vendors' => $this->getVendor() ) );
				break;
			case 10: //shortcode and admin preview
			case 8: //product page
				$html = $this->generateFiltersHtml($settings, $viewId, false, true);
				break;
			case 12: //all pages
				$catId = false;
				if ( is_product_category() ) {
					$catObj = get_queried_object();
					$catId  = $catObj->term_id;
				}
				$html = $this->generateFiltersHtml($settings, $viewId, $catId, true);
				break;
			case 11: //brand page
				$catObj = get_queried_object();
				$html   = $this->generateFiltersHtml( $settings, $viewId, false, false, array( $catObj->taxonomy => $catObj->term_id ) );
				break;
		}
		$this->assign('viewId', $viewId);
		$this->assign('html', $html);

		return parent::getContent('woofiltersHtml');
	}

	private function getVendor() {
		if ( class_exists('WC_Vendors') ) {
			$vendor_shop = urldecode( get_query_var( 'vendor_shop' ) );
			return WCV_Vendors::get_vendor_id( $vendor_shop );
		}

		if ( is_plugin_active( 'dokan-lite/dokan.php' ) ) {
			$custom_store_url = dokan_get_option( 'custom_store_url', 'dokan_general', 'store' );
			return get_query_var( $custom_store_url );
		}
	}

	/**
	 * Find display custom style status
	 *
	 * @param array $settings
	 *
	 * @return bool
	 */
	public function isCustomStyle( $settings = array() ) {
		$isCustomStyle = false;

		if ( $settings ) {
			$isCustomStyle = $this->getFilterSetting( $settings , 'disable_plugin_styles', false );
			if ( $isCustomStyle ) {
				$isCustomStyle = false;
			} else {
				$isCustomStyle = true;
			}
		}

		return $isCustomStyle;
	}

	/**
	 * Add comon styles and scripts.
	 *
	 * @param string $modPath
	 */
	public function addCommonAssets( $modPath, $settings = array() ) {
		$options = FrameWpf::_()->getModule( 'options' )->getModel( 'options' )->getAll();
		FrameWpf::_()->addStyle( 'frontend.filters', $modPath . 'css/frontend.woofilters.css' );
		FrameWpf::_()->addScript( 'frontend.filters', $modPath . 'js/frontend.woofilters.js' );

		if ( isset( $options['content_accessibility'] ) && '1' === $options['content_accessibility']['value'] ) {
			FrameWpf::_()->addStyle( 'frontend.filters.accessibility', $modPath . 'css/frontend.woofilters.accessibility.css' );
		}

		$forFilter = ! empty($settings);
		$order     = empty($settings['filters']['order']) ? '' : $settings['filters']['order'];

		if ( is_admin() || ! $forFilter || strpos($order, 'mul_dropdown') ) {
			FrameWpf::_()->addStyle('frontend.multiselect', $modPath . 'css/frontend.multiselect.css');
			FrameWpf::_()->addScript('frontend.multiselect', $modPath . 'js/frontend.multiselect.js');
			$selectedTitle = ( isset($options['selected_title']['value']) && '' !== $options['selected_title']['value'] ) ? $options['selected_title']['value'] : 'selected';
			FrameWpf::_()->addJSVar( 'frontend.multiselect', 'wpfMultySelectedTraslate', ' ' . esc_attr__( $selectedTitle, 'woo-product-filter' ) );
		}

		$slider = ( is_admin() || ! $forFilter || ( strpos($order, '"wpfPrice"') || strpos($order, '"slider"') ) );
		FrameWpf::_()->getModule('templates')->loadJqueryUi($slider);
		if ( $forFilter && $this->getFilterSetting($settings, 'initialise_immediately', false, 1) == 1 ) {
			FrameWpf::_()->addJSVar('frontend.filters', 'wpIinitialiseImmediately', '1');
		}
	}

	/**
	 * Add assets  to render html(shortcode and widget)
	 *
	 * @param string $modPath
	 */
	public function addRenderHtmlAssets( $modPath, $settings = array() ) {
		FrameWpf::_()->getModule('templates')->loadCoreJs();

		$settings = $this->getFilterSetting($settings, 'settings', array());
		$order    = empty($settings['filters']['order']) ? '' : $settings['filters']['order'];

		$forFilter = ! empty($settings);
		if ( is_admin() || ! $forFilter || ( strpos($order, '"wpfPrice"') || strpos($order, '"slider"') ) ) {
			FrameWpf::_()->addScript('jquery-ui-slider');
		}
		FrameWpf::_()->addScript('jquery-touch-punch');

		$this->addCommonAssets($modPath, $settings);
		
		$options = FrameWpf::_()->getModule('options')->getModel('options')->getAll();
		$loader  = ( ( isset($options['loader_enable']) && isset($options['loader_enable']['value']) && ! empty($options['loader_enable']['value']) )
			|| ( $this->getFilterSetting($settings, 'filter_loader_icon_onload_enable') == 1 )
			|| ( ( $this->getFilterSetting($settings, 'enable_overlay') == 1 ) && ( $this->getFilterSetting($settings, 'enable_overlay_icon') == 1 ) ) );
		if ( $loader ) {
			FrameWpf::_()->addStyle('loaders', $modPath . 'css/loaders.css');
		}
		FrameWpf::_()->addJSVar('frontend.filters', 'url', admin_url('admin-ajax.php'));
		if ( $this->getFilterSetting($settings, 'disable_fontawesome_loading', false, 1) != 1 ) {
			FrameWpf::_()->getModule('templates')->loadFontAwesome();
		}

		if ( isset($options['move_sidebar']) && isset($options['move_sidebar']['value']) && ! empty($options['move_sidebar']['value']) ) {
			FrameWpf::_()->addStyle('move.sidebar.css', $modPath . 'css/move.sidebar.css');
		}
	}

	/**
	 * Add plugin custom styles.
	 *
	 * @param string $modPath
	 * @param bool $isCustomStyle
	 *
	 * @return void
	 */
	public function addPluginCustomStyles( $modPath, $isCustomStyle ) {
		$params = ReqWpf::get( 'get' );
		if ( $isCustomStyle && ( ! is_admin() || ( isset( $params['page'] ) && 'wpf-filters' === $params['page'] ) ) ) {
			FrameWpf::_()->addStyle( 'custom.filters', $modPath . 'css/custom.woofilters.css' );
		}
	}

	protected function setUniqueBlockId( $key ) {
		self::$filterOrderKey = $key;
		++self::$uniqueBlockId;
		self::$blockId = 'wpfBlock_' . self::$uniqueBlockId;
	}

	//for now after render we run once filtering, in order to display products on custom page.
	public function renderProductsListHtml( $params ) {
		$html      = '<div class="woocommerce wpfNoWooPage">';
			$html .= '<p class="woocommerce-result-count"></p>';
			$html .= '<ul class="products columns-4"></ul>';
			$html .= '<nav class="woocommerce-pagination"></nav>';
		if ( ReqWpf::getVar( 'wpf_skip' ) != '1' ) {
			$html .= '<script>jQuery(document).ready(function() { setTimeout(function() {jQuery("body").trigger("wpffiltering"); }, 1000); })</script>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Set existing individual filter items to appropriate properties
	 *
	 * @param array $order
	 * @param array $prodCatId
	 * @param array $querySettings
	 */
	public function setFilterExistsItems( $order, $prodCatId = false, $querySettings = array(), $settings = array() ) {
		if ( is_null(self::$filterExistsTerms) ) {
			$module          = $this->getModule();
			$currentSettings = $this->getCurrentSettings();
			$taxonomies      = $module->getFilterTaxonomies($order, false, $currentSettings['settings']);
			$itemList        = $module->getFilterExistsItems(null, $taxonomies, null, $prodCatId, $order, false, $currentSettings['settings'], $settings);

			self::$filterExistsTermsJS              = isset( $itemList['existsTermsJS'] ) ? $itemList['existsTermsJS'] : false;
			self::$filterExistsTerms                = isset($itemList['exists']) ? $itemList['exists'] : false;
			self::$filterExistsTermsWithotFiltering = isset($itemList['all']) ? $itemList['all'] : self::$filterExistsTerms;
			self::$filterExistsUsers                = isset($itemList['existsUsers']) ? $itemList['existsUsers'] : array();
			self::$filterExistsPrices               = isset($itemList['existsPrices']) ? $itemList['existsPrices'] : array();
			self::$currentFilterRecount             = $module->isFiltered(false) ? $this->getFilterSetting($currentSettings['settings'], 'filter_recount', false) : true;

		}

		return self::$filterExistsTerms;
	}
	public function resetFilterExistsTerms() {
		self::$filterExistsTerms = null;
	}

	public function setFilterCss( $css ) {
		self::$filtersCss .= $css;
	}
	public function resetFiltersCss() {
		self::$filtersCss = '';
	}
	public function setLeerFilter( $leer ) {
		self::$isLeerFilter = $leer;
	}

	public function generateFiltersHtml( $filterSettings, $viewId, $prodCatId = false, $noWooPage = false, $taxonomies = array() ) {
		$customCss = '';
		if ( ! empty($filterSettings['settings']['css_editor']) ) {
			$customCss = stripslashes(base64_decode($filterSettings['settings']['css_editor']));
			unset($filterSettings['settings']['css_editor']);
		}
		if ( ! empty($filterSettings['settings']['js_editor']) ) {
			$filterSettings['settings']['js_editor'] = stripslashes(base64_decode($filterSettings['settings']['js_editor']));
		}
		$this->setCurrentSettings($filterSettings);
		$this->resetFiltersCss();

		$settingsOriginal = $filterSettings;
		$filtersOrder     = UtilsWpf::jsonDecode($filterSettings['settings']['filters']['order']);

		// determines if there are identical names of blocks that need to be added an index
		$filterName = array();
		foreach ( $filtersOrder as $filter ) {
			$name = ( isset($filter['name']) ) ? $filter['name'] : '';
			if ( '' !== $name ) {
				if ( in_array( $name, $filterName ) ) {
					$this->needIndex[] = $name;
				} else {
					$filterName[] = $name;
				}
			}
		}

		$buttonsPosition        = ( ! empty($filterSettings['settings']['main_buttons_position']) ) ? $filterSettings['settings']['main_buttons_position'] : 'bottom' ;
		$buttonsOrder           = ( ! empty($filterSettings['settings']['main_buttons_order']) ) ? $filterSettings['settings']['main_buttons_order'] : 'left' ;
		$showCleanButton        = ( ! empty($filterSettings['settings']['show_clean_button']) ) ? $filterSettings['settings']['show_clean_button'] : false ;
		$showFilteringButton    = ( ! empty($filterSettings['settings']['show_filtering_button']) ) ? $filterSettings['settings']['show_filtering_button'] : false ;
		$filterButtonWord       = ( ! empty($filterSettings['settings']['filtering_button_word']) ) ? $filterSettings['settings']['filtering_button_word'] : 'Filter' ;
		$clearButtonWord        = ( $showCleanButton && ! empty($filterSettings['settings']['show_clean_button_word']) ) ? $filterSettings['settings']['show_clean_button_word'] : 'Clear' ;
		$enableAjax             = ( ! empty($filterSettings['settings']['enable_ajax']) ) ? $filterSettings['settings']['enable_ajax'] : 0 ;
		$forceShowCurrentFilter = ( isset($filterSettings['settings']['force_show_current']) ) ? $filterSettings['settings']['force_show_current'] : 0 ;

		global $wp_query;

		$postPerPage = function_exists( 'wc_get_default_products_per_row' )
			? apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() )
			: get_option( 'posts_per_page' );
		$options     = FrameWpf::_()->getModule('options')->getModel('options')->getAll();
		if ( isset($options['count_product_shop']) && isset($options['count_product_shop']['value']) && ! empty($options['count_product_shop']['value']) ) {
			$postPerPage = $options['count_product_shop']['value'];
		}

		$paged = isset($wp_query->query_vars['paged']) ? $wp_query->query_vars['paged'] : 1;
		//get all link
		if ( empty($this->getModule()->mainWCQuery) ) {
			$base   = esc_url_raw( add_query_arg( 'product-page', '%#%', false ) );
			$format = '?product-page=%#%';
		} else {
			$base   = esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ));
			$format = '';
		}
		//get only base link, remove all query params
		$base = explode( '?', $base );
		$base = $base[0];
		if ( ! empty($format) ) {
			$base .= $format;
		}

		$paginationBase = $this->getPaginationBase();

		$settings = $this->getFilterSetting($settingsOriginal, 'settings', array());

		$querySettings = array(
			'posts_per_page' => $postPerPage,
			'posts_per_row'  => $this->getFilterSetting($settings, 'columns_product_shop', '', true),
			'paged'          => $paged,
			'base'           => $base,
			'format '        => $format,
			'page_id'        => $this->wpfGetPageId(),
			'paginate_base'  => empty($paginationBase['base']) ? '' : $paginationBase['base'],
			'paginate_type'  => empty($paginationBase['type']) ? '' : $paginationBase['type'],
		);

		$allProductsFiltering = $this->getFilterSetting($settings, 'all_products_filtering', false);

		if ( $prodCatId ) {
			$querySettings['product_category_id'] = $prodCatId;
		}
		if ( $allProductsFiltering ) {
			$prodCatId = false;
		}
		$isPro = FrameWpf::_()->isPro();

		if ( ! $allProductsFiltering && ! empty($taxonomies) ) {
			foreach ( $taxonomies as $tax => $value ) {
				switch ( $tax ) {
					case 'product_tag':
						$querySettings['product_tag'] = $value;
						break;
					case 'product_brand':
						if ( $isPro ) {
							$querySettings['product_brand'] = $value;
						}
						break;
					case 'pwb-brand':
						$querySettings['pwb-brand'] = $value;
						break;
					case 'vendors':
						$querySettings['vendors'] = $value;
						break;
					default:
						if ( ! empty($value) ) {
							$querySettings['tax_page'] = array('taxonomy' => $tax, 'term' => $value);
						}
						break;
				}
			}
		}
		$querySettingsStr = htmlentities(UtilsWpf::jsonEncode($querySettings), ENT_COMPAT);
		unset($filterSettings['settings']['styles']);
		$filterSettings = htmlentities(UtilsWpf::jsonEncode($filterSettings), ENT_COMPAT);
		$noWooPageData  = '';
		if ( $noWooPage ) {
			$noWooPageData = 'data-nowoo="true"';
		}

		$isMobile = UtilsWpf::isMobile();
		$filterId = 'wpfMainWrapper-' . $viewId;

		if ( 'right' === $buttonsOrder ) {
			$this->setFilterCss('#' . $filterId . ' .wpfFilterButtons:after{content:"";display:table;width:100%;clear:both;}');
			$this->setFilterCss('#' . $filterId . ' .wpfFilterButton.wpfButton,#' . $filterId . ' .wpfClearButton.wpfButton{float:right;}');
		}

		$paddingChildList = (int) $this->getFilterSetting( $settings, 'padding_child_list', false );
		if ( $paddingChildList ) {
			$this->setFilterCss( "#{$filterId} .wpfFilterContent ul ul {padding-inline-start:{$paddingChildList}px;}" );
		}

		$proAttributes  = DispatcherWpf::applyFilters('getProAttributes', '', $settings);
		$defaultWCQuery = $this->getFilterSetting($settings, 'third_party_prefilter', false, true) ? $this->getModule()->defaultWCQuery : '';
	
		$html = '<div class="wpfMainWrapper" id="' . $filterId .
				'" data-viewid="' . $viewId .
				'" data-filter="' . explode( '_', $viewId )[0] .
				( $forceShowCurrentFilter ? '" data-force="' . $forceShowCurrentFilter : '' ) .
				'" data-settings="' . $querySettingsStr .
				'" data-default-query="' . ( empty( $defaultWCQuery ) || is_null( $defaultWCQuery ) ? '' : htmlentities( UtilsWpf::jsonEncode( $defaultWCQuery ), ENT_COMPAT ) ) .
				'" data-filter-settings="' . $filterSettings . '" ' . $noWooPageData . $proAttributes .
				'>';

		$html = DispatcherWpf::applyFilters('addHtmlBeforeFilter', $html, $settings, $viewId);

		if ( ( 'top' === $buttonsPosition || 'both' === $buttonsPosition ) && ( $showFilteringButton || $showCleanButton ) ) {
			$html .= '<div class="wpfFilterButtons">';

			if ( $showFilteringButton ) {
				$html .= '<button class="wpfFilterButton wpfButton">' . __($filterButtonWord, 'woo-product-filter') . '</button>';
			}
			if ( $showCleanButton ) {
				$html .= '<button class="wpfClearButton wpfButton">' . __($clearButtonWord, 'woo-product-filter') . '</button>';
			}
			$html .= '</div>';
		}
		if ( ! empty( $settings['styles']['button_block_float'] ) ) {
			$html .= '<div class="wpfClear"></div>';
		}
		$width = false;
		$units = false;
		if ( $isMobile ) {
			$width = $this->getFilterSetting($settings, 'filter_width_mobile', false, true);
			$units = $this->getFilterSetting($settings, 'filter_width_in_mobile', false, false, array('%', 'px'));
		}
		if ( ! $width || ! $units ) {
			$width = $this->getFilterSetting($settings, 'filter_width', '100', true);
			$units = $this->getFilterSetting($settings, 'filter_width_in', '%', false, array('%', 'px'));
		}

		$mobileBreakpointWidth = $this->getMobileBreakpointValue($settings);
		if ( $mobileBreakpointWidth ) {
			$width = '100';
			$units = '%';
		}

		$this->setFilterCss('#' . $filterId . '{position:relative;width:' . $width . $units . ';}');

		$width = false;
		$units = false;
		if ( $isMobile ) {
			$width = $this->getFilterSetting($settings, 'filter_block_width_mobile', false, true);
			$units = $this->getFilterSetting($settings, 'filter_block_width_in_mobile', false, false, array('%', 'px'));
		}
		if ( ! $width || ! $units ) {
			$width = $this->getFilterSetting($settings, 'filter_block_width', '100', true);
			$units = $this->getFilterSetting($settings, 'filter_block_width_in', '%', false, array('%', 'px'));
		}

		if ( $mobileBreakpointWidth ) {
			$blockWidth = '100%';
		} else {
			$blockWidth = $width . $units;
		}

		$blockHeight = $this->getFilterSetting($settingsOriginal['settings'], 'filter_block_height', false, true);
		//$blockStyle  = 'visibility:hidden; width:' . $blockWidth . '; float:left; ' . ( $blockHeight ? 'height:' . $blockHeight . 'px;overflow: hidden;' : '' );
		$showImmediately = $this->getFilterSetting($settingsOriginal['settings'], 'show_filter_immediately') == '1';
		$blockStyle      =
			( $showImmediately ? '' : 'visibility:hidden;' ) . 'width:' . $blockWidth . ';' .
			( '100%' == $blockWidth ? '' : 'float:left;' ) .
			( $blockHeight ? 'height:' . $blockHeight . 'px;overflow: hidden;' : '' );

		$this->setFilterCss('#' . $filterId . ' .wpfFilterWrapper {' . $blockStyle . '}');

		$blockStyle = '';
		if ( $isPro ) {
			$proView = FrameWpf::_()->getModule('woofilterpro')->getView();
		}

		$this->setFilterExistsItems($filtersOrder, $prodCatId, $querySettings, $settings);
		$useTitleAsSlug = $this->getFilterSetting($settingsOriginal['settings'], 'use_title_as_slug', false);

		foreach ( $filtersOrder as $key => $filter ) {
			if ( empty($filter['settings']['f_enable']) || true !== $filter['settings']['f_enable'] ) {
				continue;
			}
			$this->setUniqueBlockId($key);
			$this->setLeerFilter(false);

			$filter                    = DispatcherWpf::applyFilters('controlFilterSettings', $filter);
			$filter['blockAttributes'] = empty($filter['blockAttributes']) ? '' : ' ' . $filter['blockAttributes'];
			if ( $useTitleAsSlug ) {
				$filter['blockAttributes'] .= ' data-title="' . $this->getFilterSetting($filter['settings'], 'f_title', '') . '"';
			}

			$method = 'generate' . str_replace('wpf', '', $filter['id']) . 'FilterHtml';
			if ( 'wpfCategory' !== $filter['id'] ) {
				$settingsOriginal['page_taxonomies'] = $taxonomies;
				if ( $isPro && method_exists($proView, $method) ) {
					$html .= $proView->{$method}($filter, $settingsOriginal, $blockStyle, $key, $viewId);
				} elseif ( method_exists($this, $method) ) {
					$html .= $this->{$method}($filter, $settingsOriginal, $blockStyle, $key);
				}
			} else {
				
				if ( ! $prodCatId && isset( $querySettings['product_category_id'] ) ) {
					$prodCatId = $querySettings['product_category_id'];
				}
				$html .= $this->{$method}($filter, $settingsOriginal, $blockStyle, $prodCatId, $key);
			}
		}

		if ( ( 'bottom' === $buttonsPosition || 'both' === $buttonsPosition ) && ( $showFilteringButton || $showCleanButton ) ) {
			$html .= '<div class="wpfFilterButtons">';

			if ( $showFilteringButton ) {
				$html .= '<button class="wpfFilterButton wpfButton">' . esc_html__($filterButtonWord, 'woo-product-filter') . '</button>';
			}
			if ( $showCleanButton ) {
				$html .= '<button class="wpfClearButton wpfButton">' . esc_html__($clearButtonWord, 'woo-product-filter') . '</button>';
			}
			$html .= '</div>';
		}
		if ( ! empty($settings['styles']['button_block_float']) ) {
			$html .= '<div class="wpfClear"></div>';
		}

		if ( ! $showImmediately ) {
			if ( $isPro && method_exists($proView, 'generateLoaderLayoutHtml') ) {
				$html .= $proView->generateLoaderLayoutHtml($options);
			} else {
				$this->setFilterCss('#' . $filterId . ' .wpfLoaderLayout {position:absolute;top:0;bottom:0;left:0;right:0;background-color: rgba(255, 255, 255, 0.9);z-index: 999;}');
				$this->setFilterCss('#' . $filterId . ' .wpfLoaderLayout i {position:absolute;z-index:9;top:50%;left:50%;margin-top:-30px;margin-left:-30px;color:rgba(0,0,0,.9);}');
				$html .= '<div class="wpfLoaderLayout"><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i></div>';
			}
		}

		//if loader enable on load
		if ( ! empty($settingsOriginal['settings']['filter_loader_icon_onload_enable']) ) {
			$html .= $this->generateLoaderHtml($filterId, $settingsOriginal);
		}
		//if loader enable on filtering
		if ( ! empty($settingsOriginal['settings']['enable_overlay']) ) {
			$html .= $this->generateOverlayHtml($settingsOriginal);
		}

		$html  = DispatcherWpf::applyFilters('addHtmlAfterFilter', $html, $settings, $this->filter['id']);
		$html .= '</div>';
		$html .= self::$filterExistsTermsJS;
		$html  = '<style type="text/css" id="wpfCustomCss-' . $viewId . '">' . DispatcherWpf::applyFilters('addCustomCss', $customCss . self::$filtersCss, $settings, $filterId) . '</style>' . $html;

		$this->resetFilterExistsTerms();

		return $html;
	}

	public function generateOverlayHtml( $settings ) {
		$settings          = $this->getFilterSetting($settings, 'settings', array());
		$overlayBackground = $this->getFilterSetting($settings, 'overlay_background', 'rgba(0,0,0,.5)');

		$this->setFilterCss('#wpfOverlay {background-color:' . $overlayBackground . '!important;}');

		$html  = '';
		$html .= '<div id="wpfOverlay">';
		$html .= '<div id="wpfOverlayText">';

		if ( ! empty($settings['enable_overlay_word']) && ! empty($settings['overlay_word']) ) {
			$html .= __($settings['overlay_word'], 'woo-product-filter');
		}
		if ( ! empty($settings['enable_overlay_icon']) ) {
			$colorPreview = $this->getFilterSetting($settings, 'filter_loader_icon_color', 'black');
			$iconName     = $this->getFilterSetting($settings, 'filter_loader_icon_name', 'default');
			$iconNumber   = $this->getFilterSetting($settings, 'filter_loader_icon_number', '0');

			if ( ! FrameWpf::_()->isPro() ) {
				$iconName = 'default';
			}

			$html .= '<div class="wpfPreview">';
			if ( 'custom' === $iconName ) {
				$settings['is_overlay'] = true;
				$html                   = DispatcherWpf::applyFilters('getCustomLoaderHtml', $html, $settings);

			} elseif ( 'default' === $iconName || 'spinner' === $iconName ) {
				$html .= '<div class="woobewoo-filter-loader spinner"></div>';
			} else {
				$this->setFilterCss('#wpfOverlay .woobewoo-filter-loader {color: ' . $colorPreview . ';}');
				$html .= '<div class="woobewoo-filter-loader la-' . $iconName . ' la-2x">';
				for ( $i = 1; $i <= $iconNumber; $i++ ) {
					$html .= '<div></div>';
				}
				$html .= '</div>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= '</div>';
		return $html;
	}
	public function generateIconCloseOpenTitleHtml( $filter, $filterSettings, $showTitle ) {
		if ( empty($filter['settings']) || empty($filterSettings['settings']['hide_filter_icon']) ) {
			return '';
		}

		// deprecated fallback for previous settings
		if ( UtilsWpf::isMobile() && empty($showTitle) ) {
			$showTitle =
				$this->getFilterSetting($filter['settings'], 'f_enable_title');
		}

		if ( 'yes_open' === $showTitle ) {
			$iconClass = DispatcherWpf::applyFilters('getIconHtml', 'fa-minus', 'title_icons', $filterSettings);
			$icon      = '<i class="fa ' . $iconClass . ' wpfTitleToggle"></i>';
		} elseif ( 'yes_close' === $showTitle ) {
			$iconClass = DispatcherWpf::applyFilters('getIconHtml', 'fa-plus', 'title_icons', $filterSettings);
			$icon      = '<i class="fa ' . $iconClass . ' wpfTitleToggle"></i>';
		} else {
			$icon = '';
		}
		return $icon;
	}
	public function generateDescriptionHtml( $filter ) {
		$description = $this->getFilterSetting($filter['settings'], 'f_description', false);
		if ( $description ) {
			$html = '<div class="wfpDescription">' . __($description, 'woo-product-filter') . '</div>';
		} else {
			$html = '';
		}
		return $html;
	}
	public function generateBlockClearHtml( $filter, $filterSettings ) {
		$html = '';
		if ( $this->getFilterSetting($filterSettings['settings'], 'show_clean_block', false) ) {
			$clearWord = $this->getFilterSetting($filterSettings['settings'], 'show_clean_block_word', false);
			$clearWord = $clearWord ? $clearWord : esc_attr__('clear', 'woo-product-filter');
			$html      = ' <label class="wpfBlockClear">' . esc_html($clearWord) . '</label>';
		}
		return $html;
	}
	public function generateFilterHeaderHtml( $filter, $filterSettings, $noActive = true ) {

		$showTitle = $this->getFilterSetting( $filter['settings'], 'f_enable_title' . ( UtilsWpf::isMobile() ? '_mobile' : '' ) );
		if ( ! $noActive && 'yes_close' === $showTitle ) {
			$showTitle = 'yes_open';
		}

		// deprecated fallback for previous settings
		if ( UtilsWpf::isMobile() && empty($showTitle) ) {
			$showTitle =
				$this->getFilterSetting($filter['settings'], 'f_enable_title');
		}

		$showCustomTags = $this->getFilterSetting($filter['settings'], 'f_custom_tags', false);
		$headerTag      = $this->getFilterSetting($filter['settings'], 'f_custom_tags_settings[header]', 0);
		$headerTag      = $headerTag && $showCustomTags ? FrameWpf::_()->getModule('woofilters')->getFilterTagsList()[$headerTag] : 'div';

		$titleMobileBreakpointData = $this->getMobileBreakpointOptionData($filter, $filterSettings);
		if ( $titleMobileBreakpointData ) {
			$title = $this->getFilterSetting($filter['settings'], 'f_title', false);
		} else {
			$title = 'no' == $showTitle ? false : $this->getFilterSetting($filter['settings'], 'f_title', false);
		}

		$html = '';
		if ( $title ) {
			$icon  = $this->generateIconCloseOpenTitleHtml( $filter, $filterSettings, $showTitle );
			$html .= '<div class="wpfFilterTitle" ' . $titleMobileBreakpointData . '><' . $headerTag . ' class="wfpTitle';
			$html .= ( (int) $this->getFilterSetting($filterSettings['settings'], 'hide_filter_icon', 0) ? ' wfpClickable' : '' );
			$html .= '">';
			$html .= esc_html__($title, 'woo-product-filter');
			$html .= '</' . $headerTag . '>';
			$html .= $icon;
		}
		$html .= $this->generateBlockClearHtml($filter, $filterSettings);

		if ( $title ) {
			$html .= '</div>';
		}

		$html .= '<div class="wpfFilterContent' . ( 'yes_close' == $showTitle ? ' wpfBlockAnimated wpfHide' : '' ) . '"';
		$html .= '>';

		return $html;
	}
	public function getMobileBreakpointOptionData( $filter, $filterSettings ) {
		$titleMobileBreakpointData = '';

		$mobileBreakpointWidth = $this->getMobileBreakpointValue($filterSettings['settings']);
		if ( $mobileBreakpointWidth ) {
			$showTitleDesctop =
				$this->getFilterSetting(
					$filter['settings'],
					'f_enable_title',
					'no'
			);
			$showTitleMobile  =
				$this->getFilterSetting(
					$filter['settings'],
					'f_enable_title_mobile',
					'no'
			);

			if ( 'no' != $showTitleDesctop || 'no' != $showTitleMobile ) {
				$titleMobileBreakpointData =
					' data-show-on-mobile="' . esc_html($showTitleMobile)
					. '" data-show-on-desctop="' . esc_html($showTitleDesctop) . '" ';
			}
		}

		return $titleMobileBreakpointData;
	}

	public function generatePriceFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		// Find min and max price in current result set.
		
		$prices = self::$filterExistsPrices;

		$settings   = $this->getFilterSetting($filter, 'settings', array());
		$filterName = 'wpf_min_price,wpf_max_price,tax';

		$settings['minPrice'] = '0' === $prices->wpfMinPrice ? '0.01' : $prices->wpfMinPrice;
		$settings['maxPrice'] = $prices->wpfMaxPrice;
		$noActive             = ( ReqWpf::getVar('wpf_min_price', 'all', -1) >= 0 && ReqWpf::getVar('wpf_max_price', 'all', -1) >= 0 ) ? '' : 'wpfNotActive';
		
		$html =
			'<div class="wpfFilterWrapper ' . $noActive . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, '') .

				' data-price-skin="default' .
				'" data-minvalue="' . $prices->wpfMinPrice .
				'" data-maxvalue="' . $prices->wpfMaxPrice .
				( ( isset( $prices->tax ) ) ? '" data-tax="' . $prices->tax : '' ) .
				'"' . $filter['blockAttributes'] .
			'>' .

			'<div class="wpfVisibleBufferMin wpfVisibilityHidden"></div>' .
			'<div class="wpfVisibleBufferMax wpfVisibilityHidden"></div>' .

			$this->generateFilterHeaderHtml($filter, $filterSettings, $noActive) .
			$this->generateDescriptionHtml($filter) .
			'<div id="wpfSliderRange" class="wpfPriceFilterRange"></div>' .
			$this->generatePriceInputsHtml($settings) .
			'</div>';
		$html .= '</div>';
		return $html;
	}

	public function generatePriceInputsHtml( $settings ) {
		$dataStep = 1;

		if ( class_exists('frameWcu') ) {
			$currencySwitcher = frameWcu::_()->getModule('currency');
			if ( isset($currencySwitcher) ) {
				$currentCurrency    = $currencySwitcher->getCurrentCurrency();
				$cryptoCurrencyList = $currencySwitcher->getCryptoCurrencyList();
				if ( array_key_exists($currentCurrency, $cryptoCurrencyList) ) {
					$dataStep = 0.001;
				}
			}
		}
		if ( ! isset($settings['minValue']) || is_null($settings['minValue']) ) {
			$settings['minValue'] = $settings['minPrice'];
		}
		if ( ! isset($settings['maxValue']) || is_null($settings['maxValue']) || -1 === $settings['maxValue'] ) {
			$settings['maxValue'] = $settings['maxPrice'];
		}

		$dec = $this->getFilterSetting($settings, 'decimal', 0, true);
		if ( FrameWpf::_()->isPro() ) {
			$settings = DispatcherWpf::applyFilters('checkPriceArgs', $settings);
		}

		if ( $this->getFilterSetting($settings, 'f_currency_show_as', '') === 'symbol' ) {
			$currencyShowAs = get_woocommerce_currency_symbol();
		} else {
			$currencyShowAs = get_woocommerce_currency();
		}

		if ( $this->getFilterSetting($settings, 'f_currency_position', '') === 'before' ) {
			$currencySymbolBefore = '<span class="wpfCurrencySymbol">' . $currencyShowAs . '</span>';
			$currencySymbolAfter  = '';
		} else {
			$currencySymbolAfter  = '<span class="wpfCurrencySymbol">' . $currencyShowAs . '</span>';
			$currencySymbolBefore = '';
		}

		if ( ! empty($settings['f_price_tooltip_show_as']) ) {
			$priceTooltip['class']    = 'wpfPriceTooltipShowAsText';
			$priceTooltip['readonly'] = 'readonly';
		}

		$priceTooltip['class']    = isset($priceTooltip['class']) ? $priceTooltip['class'] : '';
		$priceTooltip['readonly'] = isset($priceTooltip['readonly']) ? $priceTooltip['readonly'] : '';
		$hideInputs               = ( $this->getFilterSetting($settings, 'f_show_inputs') ? '' : ' wpfHidden' );

		return '<div class="wpfPriceInputs' . $hideInputs . '">' .
				$currencySymbolBefore .
				'<input ' .
					$priceTooltip['readonly'] .
					' type="number' .
					'" min="' . $settings['minPrice'] .
					'" max="' . ( $settings['maxPrice'] - 1 ) .
					'" id="wpfMinPrice' .
					'" class="wpfPriceRangeField ' . $priceTooltip['class'] .
					'" value="' . number_format($settings['minValue'], $dec, '.', '') .
				'" />' .
				'<span class="wpfFilterDelimeter"> - </span>' .

				'<input ' .
					$priceTooltip['readonly'] .
					' type="number" ' .
					' min="' . $settings['minPrice'] .
					'" max="' . $settings['maxPrice'] .
					'" id="wpfMaxPrice"' .
					' class="wpfPriceRangeField ' . $priceTooltip['class'] .
					'" value="' . number_format($settings['maxValue'], $dec, '.', '') .
				'" /> ' .
				'<input ' . $priceTooltip['readonly'] . ' type="hidden" id="wpfDataStep" value="' . $dataStep . '" />' .
				$currencySymbolAfter .
			'</div>';
	}

	public function getFilterLayout( $settings, $options, $isVertical = true, $cnt = 1 ) {
		$addClass = '';
		if ( isset($settings['f_layout']) ) {
			$isVertical = $this->getFilterSetting($settings, 'f_layout', 'ver') == 'ver';
			if ( $isVertical ) {
				$cnt = $this->getFilterSetting($settings, 'f_ver_columns', 1, true);
			}
		} elseif ( $isVertical ) {
			if ( $this->getFilterSetting($options['settings'], 'display_items_in_a_row', false) ) {
				$cnt = $this->getFilterSetting($options['settings'], 'display_cols_in_a_row', 1, true);
			}
		}
		if ( $isVertical ) {
			if ( $cnt > 1 ) {
				$addClass = ' wpfFilterLayoutVer';
			}
		} else {
			$addClass = ' wpfFilterLayoutHor';
		}

		return array('is_ver' => $isVertical, 'cnt' => $cnt, 'class' => $addClass);
	}
	public function generatePriceRangeFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings  = $this->getFilterSetting($filter, 'settings', array());
		$layout    = $this->getFilterLayout($settings, $filterSettings);
		$type      = $this->getFilterSetting($settings, 'f_frontend_type', 'list');
		$underOver = FrameWpf::_()->isPro() && $this->getFilterSetting($settings, 'f_under_over', false);

		$defaultRange = '';
		$module       = FrameWpf::_()->getModule('woofilters');

		if ( $filter['settings']['f_range_by_hands'] ) {
			$ranges  = array_chunk(explode(',', $this->getFilterSetting($settings, 'f_range_by_hands_values', '')), 2);
			$htmlOpt = $this->generatePriceRangeOptionsHtml($filter, $ranges, $layout);
			$default = explode(',', $this->getFilterSetting($settings, 'f_range_by_hands_default', ''));
			if ( count($default) == 2 && ( 'i' != $default[0] || 'i' != $default[1] ) ) {
				$defaultRange = ' data-default="' . ( 'i' == $default[0] ? '' : $module->getCurrencyPrice($default[0]) ) . ',' . ( 'i' == $default[1] ? '' : $module->getCurrencyPrice($default[1]) ) . '"';
			}       
		} elseif ( $filter['settings']['f_range_automatic'] ) {
			$prices = self::$filterExistsPrices;

			$minPrice = '0' === $prices->wpfMinPrice && ! $underOver ? '0.01' : $prices->wpfMinPrice;
			$maxPrice = $prices->wpfMaxPrice;
			$step     = ! empty($filter['settings']['f_step']) ? $filter['settings']['f_step'] : 50;

			$priceRange    = $maxPrice - $minPrice;
			$countElements = ceil($priceRange / $step);
			if ( $countElements > 100 ) {
				$step          = ceil($priceRange / 1000) * 10;
				$countElements = ceil($priceRange / $step);
			}
			if ( 1 == $countElements ) {
				$ranges[0] = array( ( $underOver ? 'i' : $minPrice ), $maxPrice );
			} else {
				$ranges       = array();
				$priceTempOld = 0;
				for ( $i = 0; $i < $countElements; $i++ ) {
					if ( 0 === $i ) {
						$priceTemp    = $minPrice + $step;
						$ranges[ $i ] = array( ( $underOver ? 'i' : $minPrice ), $priceTemp - 0.01 );
						$priceTempOld = $priceTemp;
					} elseif ( ( $priceTempOld + $step ) < $maxPrice ) {
						$priceTemp    = $priceTempOld + $step;
						$ranges[ $i ] = array( $priceTempOld, $priceTemp - 0.01 );
						$priceTempOld = $priceTemp;
					} else {
						$ranges[ $i ] = array( $priceTempOld, ( $underOver ? 'i' : $maxPrice ) );
					}
				}
			}
			$htmlOpt = $this->generatePriceRangeOptionsHtml($filter, $ranges, $layout);
		}
		if ( ! $htmlOpt ) {
			$htmlOpt = esc_html__('Price range filter is empty. Please setup filter correctly.', 'woo-product-filter');
		}
		$noActive = ReqWpf::getVar('wpf_min_price') && ReqWpf::getVar('wpf_max_price') ? '' : 'wpfNotActive';

		$html =
			'<div class="wpfFilterWrapper ' . $noActive . ( empty($defaultRange) ? '' : ' wpfPreselected' ) . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, 'wpf_min_price,wpf_max_price,tax', $filter['settings']['f_frontend_type']) .

				' data-radio="' . ( 'list' == $type ? '1' : '0' ) . '"' .
				$defaultRange .
			( ( isset( $prices->tax ) ) ? ' data-tax="' . $prices->tax . '"' : '' ) .
				$filter['blockAttributes'] .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= '<div class="wpfCheckboxHier">';
		if ( 'list' === $type ) {
			$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
			if ( $maxHeight > 0 ) {
				$this->setFilterCss('#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}');
			}
			$html .= '<ul class="wpfFilterVerScroll' . $layout['class'] . '">';
		}
		$html .= $htmlOpt;
		if ( 'list' === $type ) {
			$html .= '</ul>';
		}
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>'; //end wpfFilterWrapper

		return $html;
	}

	public function generateSortByFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$optionsAll = FrameWpf::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getFilterLabels( 'SortBy' );
		$settings   = $this->getFilterSetting( $filter, 'settings', array() );
		foreach ( $optionsAll as $key => $value ) {
			$optionsAll[ $key ] = $this->getFilterSetting( $settings, 'f_option_labels[' . $key . ']', $value );
		}
		$options = $this->getFilterSetting( $settings, 'f_options[]', false );
		$options = explode( ',', $options );

		if ( ReqWpf::getVar('orderby') ) {
			$optionsSelected = ReqWpf::getVar('orderby');
		} else {
			$optionsSelected = $this->getFilterUrlData('pr_sortby');
		}

		$frontendTypes                         = array('dropdown', 'radio');
		$type                                  = $this->getFilterSetting($settings, 'f_frontend_type', 'dropdown', false, DispatcherWpf::applyFilters('getFrontendFilterTypes', $frontendTypes, $filter['id']));
		$filter['settings']['f_frontend_type'] = $type;

		$productSortBy  = array();
		$sortBySelected = array($optionsSelected);
		$obj            = new stdClass();

		foreach ( $options as $key => $option ) {
			if ( ! empty($option) ) {
				$obj->term_id    = $option;
				$obj->slug       = 'default' == $option ? '' : $option;
				$obj->name       = $optionsAll[$option];
				$productSortBy[] = clone $obj;
			}
		}

		$noActive = $optionsSelected ? '' : 'wpfNotActive';
		
		$html =
			'<div class="wpfFilterWrapper ' . $noActive . '"' .
				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, 'orderby', '') .
				' data-radio="' . ( 'radio' === $type ? '1' : '0' ) . '"' .
				' data-display-type="' . $type . '"' .
				' data-first-instock="' . ( $this->getFilterSetting($settings, 'f_first_instock', false) ? 1 : 0 ) . '"' .
				$filter['blockAttributes'] .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		
		$perPage     = '';
		$perPageLeft = false;
		if ( $this->getFilterSetting($settings, 'f_show_per_page', false) ) {
			$perPageList = explode(',', $this->getFilterSetting($settings, 'f_per_page_list', '48,24,12'));
			$perPageLeft = $this->getFilterSetting($settings, 'f_per_page_position', 'left') == 'left';
			$perPage     = '<select class="wpfPerPageDD">';
			if ( ReqWpf::getVar('wpf_count') ) {
				$countSelected = ReqWpf::getVar('wpf_count');
			} else {
				$countSelected = empty($perPageList) ? 0 : $perPageList[0];
			}
		
			foreach ( $perPageList as $l ) {
				$n = (int) trim($l);
				if ( ! empty($n) ) {
					$perPage .= '<option value="' . $n . '"' . ( $n == $countSelected ? ' selected' : '' ) . '>' . $n . '</option>';
				}
			}
			$perPage .= '</select>';
			$this->setFilterCss('#' . self::$blockId . ' .wpfFilterContent {display:flex}');
			$this->setFilterCss('#' . self::$blockId . ' .wpfPerPageDD {margin-' . ( $perPageLeft ? 'right' : 'left' ) . ': 2px;}');
		}
		

		self::$leer = true;
		$htmlOpt    = $this->generateTaxonomyOptionsHtml( $productSortBy, $sortBySelected, $filter );
		switch ( $type ) {
			case 'radio':
				$html .= '<ul class="wpfFilterVerScroll">' . $htmlOpt . '</ul>';
				break;
			case 'dropdown':
				$html .= ( $perPageLeft ? $perPage : '' ) . '<select>' . $htmlOpt . '</select>' . ( $perPageLeft ? '' : $perPage );
				break;
			case 'mul_dropdown':
				$settings['f_single_select']              = true;
				$settings['f_hide_checkboxes']            = true;
				$settings['f_dropdown_first_option_text'] = $productSortBy[0]->name;
				$html                                    .= $this->getMultiSelectHtml( $htmlOpt, $settings );
				break;
		}
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>'; //end wpfFilterWrapper

		return $html;
	}

	public function notChangeCategoryCount( $tax ) {
		if ( is_array($tax) ) {
			foreach ( $tax as $key => $value ) {
				if ( 'product_cat' == $value ) {
					unset($tax[$key]);
					break;
				}
			}
		}
		return $tax;
	}

	public function generateCategoryFilterHtml( $filter, $filterSettings, $blockStyle, $prodCatId = false, $key = 1, $viewId = '' ) {
		$settings                = $this->getFilterSetting($filter, 'settings', array());
		$labels                  = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('Category');
		$hidden_categories       = isset($settings['f_hidden_categories']) ? $settings['f_hidden_categories'] : false;
		$includeCategoryId       = ( ! empty($settings['f_mlist[]']) ) ? explode(',', $settings['f_mlist[]']) : false;
		$includeCategoryChildren = $this->getFilterSetting($settings, 'f_mlist_with_children', false);
		$excludeIds              = ! empty($settings['f_exclude_terms']) ? $settings['f_exclude_terms'] : false;
		$frontendTypes           = array('list', 'dropdown');
		$type                    = $hidden_categories ? 'list' : $this->getFilterSetting($settings, 'f_frontend_type', 'list', false, DispatcherWpf::applyFilters('getFrontendFilterTypes', $frontendTypes, $filter['id']));
		$isHierarchical          = ! empty($settings['f_show_hierarchical']) ? true : false;
		$hideChild               = ! empty($settings['f_hide_taxonomy']) ? true : false;
		$isExtendParentFiltering = ! empty($settings['f_extend_parent_filtering']) ? true : false;
		$isIncludeChildren       = $this->findTaxonomyIncludeChildrenStatus($hideChild, $isExtendParentFiltering, $type);
		$hideEmpty               = $this->getFilterSetting($settings, 'f_hide_empty', false);
		$hideEmptyActive         = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_empty_active', false);
		$useSlugs                = $this->getFilterSetting($filterSettings['settings'], 'use_category_slug', false);
		$allProductsFiltering    = $this->getFilterSetting($filterSettings['settings'], 'all_products_filtering', false);

		$taxonomy = 'product_cat';
		if ( ! empty($includeCategoryId) && $includeCategoryChildren ) {
			$includeCategoryId = $this->getChildrenOfIncludedCategories($taxonomy, $includeCategoryId);
		}

		$args = array(
			'parent'     => 0,
			'hide_empty' => $hideEmpty,
			'include'    => $includeCategoryId,
		);

		$isCustomOrder  = $includeCategoryId && ! empty($settings['f_order_custom']);
		$order          = ! $isCustomOrder && ! empty($settings['f_sort_by']) ? $settings['f_sort_by'] : 'default';
		$isDefaultOrder = ( 'default' === $order );

		if ( $isCustomOrder || ! $isDefaultOrder ) {
			$args = array_merge($args, array(
				'orderby'         => ( $isCustomOrder ? 'include' : 'name' ),
				'order'           => ( $isDefaultOrder ? 'asc' : $order ),
				'sort_as_numbers' => ( $isDefaultOrder ? false : $this->getFilterSetting($settings, 'f_sort_as_numbers', false) ),
			));
		}

		if ( $hideChild ) {
			$args['only_parent'] = $hideChild;
		}
		$showAllCats = $this->getFilterSetting($settings, 'f_show_all_categories', false);

		list($showedTerms, $countsTerms, $showFilter, $allTerms) = $this->getShowedTerms($taxonomy, $showAllCats);

		if ( $this->getFilterSetting($settings, 'f_show_count', false) && ! $this->getFilterSetting($settings, 'f_show_count_parent_with_children', false) ) {
			add_filter('woocommerce_change_term_counts', array($this, 'notChangeCategoryCount'));
		}

		$onlyNeeded = false;
		if ( empty($includeCategoryId) && ! $allProductsFiltering && ! empty($allTerms) && empty($args['only_parent']) ) {
			$args['include'] = $allTerms;
			$onlyNeeded      = true;
		}
		$productCategory = $this->getTaxonomyHierarchy($taxonomy, $args);

		if ( ! empty($args['only_parent']) ) {
			foreach ( $productCategory as $id => $cat ) {
				if ( ! empty($cat->children) ) {
					$productCategory[$id]->children = array();
				}
			}
		}
		remove_filter('woocommerce_change_term_counts', array($this, 'notChangeCategoryCount'));

		if ( ! $productCategory ) {
			return '';
		}
		if ( ( $includeCategoryId || $onlyNeeded ) && $isHierarchical ) {
			$productCategory = $this->getCustomHierarchicalCategories($productCategory);
		}

		$filter['settings']['f_frontend_type'] = $type;
		$isMulti                               = ( 'multi' == $type || 'mul_dropdown' == $type );

		$defFilterName = $this->getFilterSetting($filter, 'name', 'wpf_filter_cat');
		$filterName    = 'wpf_filter_cat';
		if ( ! $isIncludeChildren ) {
			$filterName .= '_list';
		}
		$filterName    .= '_' . $key;
		$defFilterName .= '_' . $key;

		if ( $useSlugs ) {
			$filterName .= 's';
		}

		$defSelected = $this->getFilterUrlData($filterName, $defFilterName);
		$catSelected = $defSelected;
		if ( $catSelected ) {
			if ( $catSelected == $this->getFilterSetting($settings, 'f_select_default_id') ) {
				$filter['is_ids'] = true;
			} elseif ( $useSlugs ) {
				$filter['is_slugs'] = true;
			}
			$ids = explode('|', $catSelected);
			if ( count($ids) <= 1 ) {
				$ids = explode(',', $catSelected);
			}
			$catSelected = $ids;
		} elseif ( $hidden_categories && $includeCategoryId ) {
			$catSelected      = $includeCategoryId;
			$filter['is_ids'] = true;
		} else {
			$catSelected = array();
		}

		$currentCategoryId = 0;
		if ( $this->getFilterSetting( $settings, 'f_hide_page_category', false ) ) {
			$path     = parse_url( ReqWpf::getVar( 'REQUEST_URI', 'server' ), PHP_URL_PATH );
			$parts    = explode( '/', $path );
			$slug     = $parts[ count( $parts ) - 1 ];
			$category = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $category ) {
				$currentCategoryId = $category->term_id;
			}
		}
		
		$setCurCat = false;
		if ( $prodCatId && $this->getFilterSetting($settings, 'f_set_page_category', false) ) {
			$isEmpty       = empty($catSelected);
			$catSelected[] = $prodCatId;
			if ( $this->getFilterSetting($settings, 'f_set_parent_page_category', false) ) {
				$catSelected = array_merge($catSelected, get_ancestors($prodCatId, 'product_cat'));
			}
			/*$childs = get_term_children($prodCatId, 'product_cat');
			if (is_array($childs)) {
				$catSelected = array_merge($catSelected, $childs);
			}*/
			if ( $isEmpty && $includeCategoryId ) {
				foreach ( $catSelected as $cat ) {
					if ( in_array($cat, $includeCategoryId) ) {
						$setCurCat = true;
						break;
					}
				}
			} else {
				$setCurCat = true;
			}
		} else if (is_product() && $this->getFilterSetting($settings, 'f_set_product_category', false)) {
			global $product;
			if ($product) {
				$terms = get_the_terms($product->get_id(), 'product_cat');
				if ($terms) {
					$prodCategories = array();
					$isOne = ( 'multi' !== $type );
					foreach ($terms as $term) {
						$prodCategories[] = $term->term_id;
						if ($isOne) {
							break;
						}
					}
					if (!empty($prodCategories)) {
						$catSelected = array_merge($catSelected, $prodCategories);
						$setCurCat = true;
					}
				}
			}
		}

		$layout      = $this->getFilterLayout($settings, $filterSettings);
		$inLineClass = $layout['class'];

		$htmlOpt = '';

		if ( $defSelected && ! $hideEmptyActive ) {
			$showedTerms = $allTerms;
			$showFilter  = true;
		}

		if ( in_array($type, $frontendTypes) || $isMulti ) {
			self::$leer = true;

			$htmlOpt = $this->generateTaxonomyOptionsHtml( $productCategory, $catSelected, $filter, $excludeIds, '', $layout, $includeCategoryId, $showedTerms, $countsTerms, 0, $currentCategoryId );
			if ( 'list' === $type || 'multi' === $type ) {
				$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
				if ( $maxHeight > 0 ) {
					$this->setFilterCss('#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}');
				}
				$htmlOpt = '<ul class="wpfFilterVerScroll' . $inLineClass . '">' . $htmlOpt . '</ul>';
			} elseif ( 'dropdown' === $type ) {
				$htmlOpt = '<select><option value="" data-slug="">' . esc_html__($this->getFilterSetting($settings, 'f_dropdown_first_option_text', __('Select all', 'woo-product-filter')), 'woo-product-filter') . '</option>' . $htmlOpt . '</select>';
			} elseif ( 'mul_dropdown' === $type ) {
				$htmlOpt = $this->getMultiSelectHtml( $htmlOpt, $settings );
			}
		} else {
			$htmlOpt = DispatcherWpf::applyFilters('getTaxonomyOptionsHtml', $htmlOpt, array(
				'type'     => $type,
				'settings' => $filter,
				'terms'    => $productCategory,
				'selected' => $catSelected,
				'showed'   => $showedTerms,
				'counts'   => $countsTerms,
				'excludes' => $excludeIds,
				'includes' => $includeCategoryId,
				'display'  => $layout,
				'class'    => $inLineClass,
			));
		}

		$noActive    = $defSelected || $setCurCat ? '' : 'wpfNotActive';
		$noActive    = $hidden_categories ? 'wpfHidden' : $noActive;
		$preselected = $hidden_categories ? ' wpfPreselected' : '';

		$blockStyle = ( ! $showFilter || ( ! $showAllCats && self::$isLeerFilter ) ? 'display:none;' : '' ) . $blockStyle;
		if ( ! empty($blockStyle) ) {
			$this->setFilterCss('#' . self::$blockId . ' {' . $blockStyle . '}');
		}

		$logic     = $this->getFilterSetting($settings, 'f_multi_logic', 'or');
		$notIds    = '';
		$notValues = '';
		if ( $hidden_categories ) {
			if ( ! $includeCategoryId && $excludeIds ) {
				$logic     = 'not';
				$notValues = '" data-not-ids="' . esc_attr($excludeIds);
			}
		}

		$showCount    = $this->getFilterSetting($settings, 'f_show_count', false) ? ' wpfShowCount' : '';
		$hierarchical = $isHierarchical ? 'true' : 'false';
		//$iniqId       = empty($filter['uniqId']) ? '' : $filter['uniqId'];
		$html =
			'<div class="wpfFilterWrapper ' . $noActive . $showCount . $preselected . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, $type) .

				//' data-uniq-id="' . $iniqId .
				' data-radio="' . ( 'list' === $type ? '1' : '0' ) .
				'" data-query-logic="' . $logic . $notValues .
				'" data-logic-hierarchical="' . $this->getFilterSetting($settings, 'f_multi_logic_hierarchical', 'any') .
				'" data-query-children="' . ( $isIncludeChildren ? '1' : '0' ) .
				'" data-show-hierarchical="' . $hierarchical .
				'" data-taxonomy="product_cat' .
				'" data-hide-active="' . ( $hideEmptyActive ? '1' : '0' ) .
				'" data-show-all="' . ( (int) $showAllCats ) .
				'" data-use-slugs="' . ( $useSlugs ? '1' : '0' ) . '"' .

				$filter['blockAttributes'] .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		if ( 'list' === $type && $this->getFilterSetting($settings, 'f_show_search_input', false) ) {
			$html .= '<div class="wpfSearchWrapper"><input class="wpfSearchFieldsFilter passiveFilter" type="text" placeholder="' . esc_html__($this->getFilterSetting($settings, 'f_search_label', $labels['search']), 'woo-product-filter') . '"' .
				( $this->getFilterSetting($settings, 'f_unfolding_by_search', false) ? ' data-unfolding="1"' : '' ) .
				( $this->getFilterSetting($settings, 'f_collapse_by_delete', false) ? ' data-collapse-search="1"' : '' ) . '></div>';
		}
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $htmlOpt;
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	public function getCustomHierarchicalCategories( $productCategory ) {
		$moveCat = array();
		foreach ( $productCategory as $id => $cat ) {
			$parentId = $cat->parent;
			if ( 0 != $parentId ) {
				if ( isset($productCategory[$parentId]) ) {
					$moveCat[$id] = $parentId;
				}
			}
		}
		while ( count($moveCat) > 0 ) {
			reset($moveCat);
			$id = key($moveCat);
			do {
				$found = array_search($id, $moveCat);
				if ( $found ) {
					$id = $found;
				}
			} while ( $found );

			$parentId = $moveCat[$id];
			$parent   = $productCategory[$parentId];
			if ( property_exists($parent, 'children') && is_array($parent->children) ) {
				if ( ! isset($parent->children[$id]) ) {
					$parent->children[$id] = $productCategory[$id];
				}
			} else {
				$parent->children = array($id => $productCategory[$id]);
			}
			$productCategory[$parentId] = $parent;
			unset($productCategory[$id], $moveCat[$id]);
		}
		return $productCategory;
	}

	/**
	 * Generate custom taxonomy filter for a specific plugin
	 *
	 * @link https://wordpress.org/plugins/perfect-woocommerce-brands/
	 *
	 * @param array $filter
	 * @param array $filterSettingss
	 * @param string $blockStyles
	 * @param int $keys
	 * @param int $viewIds
	 *
	 * @return string
	 */
	public function generatePerfectBrandFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings             = $this->getFilterSetting($filter, 'settings', array());
		$labels               = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('PerfectBrand');
		$hiddenBrands         = $this->getFilterSetting($settings, 'f_hidden_brands', false);
		$includeBrandId       = ( ! empty($settings['f_mlist[]']) ) ? explode(',', $settings['f_mlist[]']) : false;
		$includeBrandChildren = $this->getFilterSetting($settings, 'f_mlist_with_children', false);
		$isHierarchical       = $this->getFilterSetting($settings, 'f_show_hierarchical', false);
		$frontendTypes        = array('list', 'dropdown', 'mul_dropdown');
		$type                 = $hiddenBrands ? 'list' : $this->getFilterSetting($settings, 'f_frontend_type', 'list', false, DispatcherWpf::applyFilters('getFrontendFilterTypes', $frontendTypes, $filter['id']));
		$excludeIds           = ! empty($settings['f_exclude_terms']) ? $settings['f_exclude_terms'] : false;
		$hideChild            = ! empty($settings['f_hide_taxonomy']) ? true : false;
		$isIncludeChildren    = $this->findTaxonomyIncludeChildrenStatus($hideChild, false, $type);
		$hideEmpty            = $this->getFilterSetting($settings, 'f_hide_empty', false);
		$hideEmptyActive      = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_empty_active', false);
		$allProductsFiltering = $this->getFilterSetting($filterSettings['settings'], 'all_products_filtering', false);

		$taxonomy = 'pwb-brand';
		if ( ! empty($includeBrandId) && $includeBrandChildren ) {
			$includeBrandId = $this->getChildrenOfIncludedCategories($taxonomy, $includeBrandId);
		}
		$args = array(
			'parent'     => 0,
			'hide_empty' => $hideEmpty,
			'include'    => $includeBrandId,
		);

		$isCustomOrder  = $includeBrandId && ! empty($settings['f_order_custom']);
		$order          = ! $isCustomOrder && ! empty($settings['f_sort_by']) ? $settings['f_sort_by'] : 'default';
		$isDefaultOrder = ( 'default' === $order );

		if ( $isCustomOrder || ! $isDefaultOrder ) {
			$args = array_merge($args, array(
				'orderby'         => ( $isCustomOrder ? 'include' : 'name' ),
				'order'           => ( $isDefaultOrder ? 'asc' : $order ),
				'sort_as_numbers' => ( $isDefaultOrder ? false : $this->getFilterSetting($settings, 'f_sort_as_numbers', false) ),
			));
		}

		if ( $hideChild ) {
			$args['only_parent'] = $hideChild;
		}
		$showAllBrands = $this->getFilterSetting($settings, 'f_show_all_brands', false);

		list($showedTerms, $countsTerms, $showFilter, $allTerms) = $this->getShowedTerms($taxonomy, $showAllBrands);
		
		$onlyNeeded = false;
		if ( ! $allProductsFiltering && ! empty($allTerms) ) {
			$args['include'] = empty($includeBrandId) ? $allTerms : array_intersect($allTerms, $includeBrandId);
			$onlyNeeded      = true;
		}

		$productBrand = $this->getTaxonomyHierarchy($taxonomy, $args);
		if ( ! $productBrand ) {
			return '';
		}
		if ( ( $includeBrandId || $onlyNeeded ) && $isHierarchical ) {
			$productBrand = $this->getCustomHierarchicalCategories($productBrand);
		}

		$filter['settings']['f_frontend_type'] = $type;

		$isMulti = ( 'multi' == $type );

		$filterName = 'wpf_filter_pwb';
		if ( ! $isIncludeChildren ) {
			$filterName .= '_list';
		}
		$filterName .= '_' . $key;

		$defSelected   = $this->getFilterUrlData($filterName);
		$brandSelected = $defSelected;
		if ( $brandSelected ) {
			if ( $brandSelected == $this->getFilterSetting($settings, 'f_select_default_id') ) {
				$filter['is_ids'] = true;
			}
			$ids = explode('|', $brandSelected);
			if ( count($ids) <= 1 ) {
				$ids = explode(',', $brandSelected);
			}
			$brandSelected = $ids;
		} elseif ( $hiddenBrands && $includeBrandId ) {
			$brandSelected    = $includeBrandId;
			$filter['is_ids'] = true;
		}

		$layout      = $this->getFilterLayout($settings, $filterSettings);
		$inLineClass = $layout['class'];

		$logic     = $this->getFilterSetting($settings, 'f_query_logic', 'or', false, array('or', 'and'));
		$notIds    = '';
		$notValues = '';
		if ( $hiddenBrands ) {
			if ( ! $includeBrandId && $excludeIds ) {
				$logic     = 'not';
				$notValues = '" data-not-ids="' . esc_attr($excludeIds);
			}
		}

		$htmlOpt = '';
		if ( $defSelected && ! $hideEmptyActive ) {
			$showedTerms = $allTerms;
			$showFilter  = true;
		}

		if ( in_array($type, $frontendTypes) || $isMulti ) {
			self::$leer = true;
			$htmlOpt    = $this->generateTaxonomyOptionsHtml( $productBrand, $brandSelected, $filter, $excludeIds, '', $layout, $includeBrandId, $showedTerms, $countsTerms );
			if ( 'list' === $type || 'multi' === $type ) {
				$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
				if ( $maxHeight > 0 ) {
					$this->setFilterCss('#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}');
				}
				$htmlOpt = '<ul class="wpfFilterVerScroll' . $inLineClass . '">' . $htmlOpt . '</ul>';

			} elseif ( 'dropdown' === $type ) {
				$htmlOpt =
					'<select><option value="" data-slug="">' .
					esc_html__($this->getFilterSetting($settings, 'f_dropdown_first_option_text', 'Select all'), 'woo-product-filter') .
					'</option>' . $htmlOpt . '</select>';
			} elseif ( 'mul_dropdown' === $type ) {
				$htmlOpt = $this->getMultiSelectHtml( $htmlOpt, $settings );
			}
		} else {
			$htmlOpt = DispatcherWpf::applyFilters('getTaxonomyOptionsHtml', $htmlOpt, array(
				'type'     => $type,
				'settings' => $filter,
				'terms'    => $productBrand,
				'selected' => $brandSelected,
				'showed'   => $showedTerms,
				'counts'   => $countsTerms,
				'excludes' => $excludeIds,
				'includes' => $includeBrandId,
				'display'  => $layout,
				'class'    => $inLineClass,
			));
		}

		$noActive    = $defSelected ? '' : 'wpfNotActive';
		$noActive    = $hiddenBrands ? 'wpfHidden' : $noActive;
		$preselected = $hiddenBrands ? ' wpfPreselected' : '';

		$blockStyle = ( ! $showFilter || ( ! $showAllBrands && self::$isLeerFilter ) ? 'display:none;' : '' ) . $blockStyle;
		if ( ! empty($blockStyle) ) {
			$this->setFilterCss('#' . self::$blockId . ' {' . $blockStyle . '}');
		}

		$description  = DispatcherWpf::applyFilters( 'productLoopStart', '', $settings );
		$showCount    = $this->getFilterSetting($settings, 'f_show_count', false) ? ' wpfShowCount' : '';
		$hierarchical = $isHierarchical ? 'true' : 'false';
		$html         =
			'<div class="wpfFilterWrapper ' . $noActive . $showCount . $preselected . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, $type) .

				' data-radio="' . ( 'list' === $type ? '1' : '0' ) .
				'" data-query-logic="' . $logic . $notValues .
				'" data-query-children="' . ( $isIncludeChildren ? '1' : '0' ) .
				'" data-show-hierarchical="' . $hierarchical .
				'" data-taxonomy="pwb-brand' .
				'" data-hide-active="' . ( $hideEmptyActive ? '1' : '0' ) .
				'" data-show-all="' . ( (int) $showAllBrands ) . '"' .
				$filter['blockAttributes'] .
			'>';

		if ( '' !== $description ) {
			$html .= '<div class="data-brand-description" style="display:none;">' . $description . '</div>';
		}

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		if ( 'list' === $type && $this->getFilterSetting($settings, 'f_show_search_input', false) ) {
			$html .= '<div class="wpfSearchWrapper"><input class="wpfSearchFieldsFilter" type="text" placeholder="' . esc_html__($this->getFilterSetting($settings, 'f_search_label', $labels['search']), 'woo-product-filter') . '"></div>';
		}
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $htmlOpt;
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	public function generateTagsFilterHtml( $filter, $filterSettings, $blockStyle, $key = 0, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$labels   = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('Tags');

		$hidden_tags     = isset($filter['settings']['f_hidden_tags']) ? $filter['settings']['f_hidden_tags'] : false;
		$includeTagsId   = ! empty($filter['settings']['f_mlist[]']) ? explode(',', $filter['settings']['f_mlist[]']) : false;
		$excludeIds      = ! empty($filter['settings']['f_exclude_terms']) ? $filter['settings']['f_exclude_terms'] : false;
		$hideEmpty       = $this->getFilterSetting($settings, 'f_hide_empty', false);
		$hideEmptyActive = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_empty_active', false);

		$args = array(
			'parent'     => 0,
			'hide_empty' => $hideEmpty,
			'include'    => $includeTagsId,
		);

		$isCustomOrder  = $includeTagsId && ! empty($settings['f_order_custom']);
		$order          = ! $isCustomOrder && ! empty($settings['f_sort_by']) ? $settings['f_sort_by'] : 'default';
		$isDefaultOrder = ( 'default' === $order );

		if ( $isCustomOrder || ! $isDefaultOrder ) {
			$args = array_merge($args, array(
				'orderby'         => ( $isCustomOrder ? 'include' : 'name' ),
				'order'           => ( $isDefaultOrder ? 'asc' : $order ),
				'sort_as_numbers' => ( $isDefaultOrder ? false : $this->getFilterSetting($settings, 'f_sort_as_numbers', false) ),
			));
		}

		$show_all_tags = isset($filter['settings']['f_show_all_tags']) ? $filter['settings']['f_show_all_tags'] : false;
		$taxonomy      = 'product_tag';
		$filterName    = $this->getFilterSetting($filter, 'name', 'product_tag');
		$filterName   .= '_' . $key;
		list($showedTerms, $countsTerms, $showFilter, $allTerms) = $this->getShowedTerms($taxonomy, $show_all_tags, $filterName);

		$productTag = $this->getTaxonomyHierarchy($taxonomy, $args);
		if ( ! $productTag ) {
			return '';
		}

		$defSelected = $this->getFilterUrlData($filterName);

		$tagSelected = $defSelected;
		if ( $tagSelected ) {
			if ( $tagSelected == $this->getFilterSetting($settings, 'f_select_default_id') ) {
				$filter['is_ids'] = true;
			}
			$ids = explode('|', $tagSelected);
			if ( count($ids) <= 1 ) {
				$ids = explode(',', $tagSelected);
			}
			$tagSelected = $ids;

		} elseif ( $hidden_tags && $includeTagsId ) {
			$tagSelected      = $includeTagsId;
			$filter['is_ids'] = true;
		}
		
		$setCurTag = false;
		if ( $this->getFilterSetting($settings, 'f_set_page_tag', false) ) {
			$pageTaxonomies = $this->getFilterSetting($filterSettings, 'page_taxonomies', array());
			if ( ! empty($pageTaxonomies) && is_array($pageTaxonomies) ) {
				$pageTagId = $this->getFilterSetting($pageTaxonomies, 'product_tag', 0, 1);
				if ( $pageTagId ) {
					if ( empty($tagSelected) ) {
						$tagSelected      = array($pageTagId);
						$filter['is_ids'] = true;
					} elseif ( $this->getFilterSetting($filter, 'is_ids', false) ) {
							$tagSelected[] = $pageTagId;
					} else {
						$tagElem = get_term_by('id', $pageTagId, 'product_tag', ARRAY_A);
						if ( $tagElem ) {
							$tagSelected[] = $tagElem['slug'];
						}
					}
					$setCurTag = true;
				}
			}
		}
		$layout      = $this->getFilterLayout($settings, $filterSettings);
		$inLineClass = $layout['class'];

		$frontendTypes = array('list', 'dropdown', 'mul_dropdown');
		$type          = $this->getFilterSetting($settings, 'f_frontend_type', 'list', null, DispatcherWpf::applyFilters('getFrontendFilterTypes', $frontendTypes, $filter['id']));
		$logic         = $this->getFilterSetting($settings, 'f_query_logic', 'or', false, array('or', 'and'));
		$notIds        = '';
		$notValues     = '';
		if ( $hidden_tags ) {
			$type = 'list';
			if ( ! $includeTagsId && $excludeIds ) {
				$logic     = 'not';
				$notValues = '" data-not-ids="' . esc_attr($excludeIds);
			}
		}
		$filter['settings']['f_frontend_type'] = $type;

		$htmlOpt = '';
		if ( $defSelected && ! $hideEmptyActive ) {
			$showedTerms = $allTerms;
			$showFilter  = true;
		}

		if ( in_array($type, $frontendTypes) ) {
			self::$leer = true;
			$htmlOpt    = $this->generateTaxonomyOptionsHtml( $productTag, $tagSelected, $filter, $excludeIds, '', $layout, false, $showedTerms, $countsTerms );
			if ( 'list' === $type ) {
				$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
				if ( $maxHeight > 0 ) {
					$this->setFilterCss('#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}');
				}
				$htmlOpt = '<ul class="wpfFilterVerScroll' . $inLineClass . '">' . $htmlOpt . '</ul>';
			} elseif ( 'dropdown' === $type ) {
				if ( ! empty($filter['settings']['f_dropdown_first_option_text']) ) {
					$htmlOpt = '<option value="" data-slug="">' . esc_html__($filter['settings']['f_dropdown_first_option_text'], 'woo-product-filter') . '</option>' . $htmlOpt;
				} else {
					$htmlOpt = '<option value="" data-slug="">' . esc_html__('Select all', 'woo-product-filter') . '</option>' . $htmlOpt;
				}
				$htmlOpt = '<select>' . $htmlOpt . '</select>';
				$logic   = 'or';
			} elseif ( 'mul_dropdown' === $type ) {
				$htmlOpt = $this->getMultiSelectHtml( $htmlOpt, $settings );
			}
		} else {
			$htmlOpt = DispatcherWpf::applyFilters('getTaxonomyOptionsHtml', $htmlOpt, array(
				'type'     => $type,
				'settings' => $filter,
				'terms'    => $productTag,
				'selected' => $tagSelected,
				'showed'   => $showedTerms,
				'counts'   => $countsTerms,
				'excludes' => $excludeIds,
				'includes' => false,
				'display'  => $layout,
				'class'    => $inLineClass,
				'show_all' => $show_all_tags,
				'view_id'  => $viewId,
			));
		}

		//$noActive    = $defSelected ? '' : 'wpfNotActive';
		$noActive    = $defSelected || $setCurTag ? '' : 'wpfNotActive';
		$noActive    = $hidden_tags ? 'wpfHidden' : $noActive;
		$preselected = $hidden_tags ? ' wpfPreselected' : '';

		$blockStyle = ( ! $showFilter || ( ! $show_all_tags && self::$isLeerFilter ) ? 'display:none;' : '' ) . $blockStyle;
		if ( ! empty($blockStyle) ) {
			$this->setFilterCss('#' . self::$blockId . ' {' . $blockStyle . '}');
		}

		$showCount = $filter['settings']['f_show_count'] ? ' wpfShowCount' : '';
		$html      =
			'<div class="wpfFilterWrapper ' . $noActive . $showCount . $preselected . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, $type) .

				' data-query-logic="' . $logic . $notValues .
				'" data-taxonomy="product_tag' .
				'" data-hide-active="' . ( $hideEmptyActive ? '1' : '0' ) .
				'" data-show-all="' . ( (int) $show_all_tags ) . '"' .

				$filter['blockAttributes'] .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		if ( 'list' === $type && $this->getFilterSetting($settings, 'f_show_search_input', false) ) {
			$html .= '<div class="wpfSearchWrapper"><input class="wpfSearchFieldsFilter" type="text" placeholder="' . esc_html__($this->getFilterSetting($settings, 'f_search_label', $labels['search']), 'woo-product-filter') . '"></div>';
		}
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $htmlOpt;
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	public function generateAuthorFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$labels   = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('Author');

		$roleNames  = ! empty($filter['settings']['f_mlist[]']) ? explode(',', $filter['settings']['f_mlist[]']) : false;
		$filterName = 'pr_author';

		//show all roles if user not make choise
		if ( ! $roleNames ) {
			if ( ! function_exists( 'get_editable_roles' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			$rolesMain = get_editable_roles();
			$roleNames = array();
			foreach ( $rolesMain as $key => $role ) {
				$roleNames[] = $key;
			}
		}

		$userExistList = array();
		foreach ( self::$filterExistsUsers as $userData ) {
			$userExistList[] = $userData['ID'];
		}

		$args = array(
			'role__in' => $roleNames,
			'fields'   => array('ID', 'display_name', 'user_nicename'),
		);

		$usersMain = get_users( $args );

		$users             = array();
		$userSelectedSlugs = array();
		$userShowedIds     = array();

		foreach ( $usersMain as $key => $user ) {
			$u          = new stdClass();
			$u->term_id = $user->ID;
			$u->name    = $user->display_name;
			$u->slug    = $user->user_nicename;

			if ( $userExistList && in_array( $user->ID, $userExistList ) ) {
				$userShowedIds[] = $user->ID;
				$storeName       = get_user_meta( $user->ID, 'store_name', true );
				if ( '' !== $storeName ) {
					$u->name = $storeName;
				}
			}

			$users[] = $u;

			if ( strpos( ReqWpf::getVar( 'pr_author', 'all', '' ), $user->user_nicename ) !== false ) {
				$userSelectedSlugs[] = $user->user_nicename;
				$filter['is_slugs']  = true;
			}
		}

		$layout = $this->getFilterLayout($settings, $filterSettings);

		if ( $layout['is_ver'] ) {
			$this->setFilterCss('#' . self::$blockId . ' {display: inline-block; min-width: auto;}');
		}

		self::$leer = true;
		$htmlOpt    = $this->generateTaxonomyOptionsHtml( $users, $userSelectedSlugs, $filter, false, '', $layout, false, $userShowedIds );
		$type       = $filter['settings']['f_frontend_type'];

		if ( 'list' === $type ) {
			$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
			if ( $maxHeight > 0 ) {
				$this->setFilterCss('#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}');
			}
			$wrapperStart = '<ul class="wpfFilterVerScroll' . $layout['class'] . '">';
			$wrapperEnd   = '</ul>';
		} elseif ( 'dropdown' === $type ) {
			$wrapperStart = '<select>';
			if ( ! empty($filter['settings']['f_dropdown_first_option_text']) ) {
				$htmlOpt = '<option value="" data-slug="">' . esc_html__($filter['settings']['f_dropdown_first_option_text'], 'woo-product-filter') . '</option>' . $htmlOpt;
			} else {
				$htmlOpt = '<option value="" data-slug="">' . esc_html__('Select all', 'woo-product-filter') . '</option>' . $htmlOpt;
			}
			$wrapperEnd = '</select>';
		} elseif ( 'mul_dropdown' === $type ) {
			$wrapperStart = '';
			$wrapperEnd   = '';
			$htmlOpt      = $this->getMultiSelectHtml( $htmlOpt, $settings );
		}

		$noActive = ReqWpf::getVar('pr_author') ? '' : 'wpfNotActive';

		$html =
			'<div class="wpfFilterWrapper ' . $noActive . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, $type) .

				$filter['blockAttributes'] .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		if ( 'list' === $type && $this->getFilterSetting($settings, 'f_show_search_input', false) ) {
			$html .=
				'<div class="wpfSearchWrapper">' .
					'<input class="wpfSearchFieldsFilter" type="text" placeholder="' . esc_html__($this->getFilterSetting($settings, 'f_search_label', $labels['search']), 'woo-product-filter') . '">' .
				'</div>';
		}
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $wrapperStart;
		$html .= $htmlOpt;
		$html .= $wrapperEnd;
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	public function generateFeaturedFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$filterName = 'pr_featured';
		$settings   = $this->getFilterSetting($filter, 'settings', array());

		$layout      = $this->getFilterLayout($settings, $filterSettings);
		$inLineClass = $layout['class'];

		if ( $layout['is_ver'] ) {
			$this->setFilterCss('#' . self::$blockId . ' {display: inline-block; min-width: auto;}');
		}

		$u          = new stdClass();
		$u->term_id = '1';
		$u->name    = __($this->getFilterSetting($filter['settings'], 'f_custom_title', 'Featured'), 'woo-product-filter');
		$u->slug    = '1';
		$feature[]  = $u;

		$featureSelected = array(ReqWpf::getVar($filterName));

		$frontendTypes                         = array('list');
		$type                                  = $this->getFilterSetting($filter['settings'], 'f_frontend_type', 'list', null, DispatcherWpf::applyFilters('getFrontendFilterTypes', $frontendTypes, $filter['id']));
		$filter['settings']['f_frontend_type'] = $type;

		$htmlOpt = '';

		$selectDefaultId = DispatcherWpf::applyFilters('getDefaultId', $filter['settings']);
		if ( is_numeric($selectDefaultId) ) {
			$featureSelected[] = $selectDefaultId;
		}

		if ( in_array($type, $frontendTypes) ) {
			self::$leer = true;
			$htmlOpt    = $this->generateTaxonomyOptionsHtml( $feature, $featureSelected, $filter, false, '', $layout );
			$htmlOpt    = '<ul class="wpfFilterVerScroll' . $inLineClass . '">' . $htmlOpt . '</ul>';
		} else {
			$htmlOpt = DispatcherWpf::applyFilters('getTaxonomyOptionsHtml', $htmlOpt, array(
				'type'     => $type,
				'settings' => $filter,
				'terms'    => $feature,
				'selected' => $featureSelected,
				'display'  => $layout,
				'class'    => $inLineClass,
			));
		}

		$noActive = ReqWpf::getVar('pr_featured') ? '' : 'wpfNotActive';
		$html     =
			'<div class="wpfFilterWrapper ' . $noActive . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, $filter['settings']['f_frontend_type']) .

				$filter['blockAttributes'] .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $htmlOpt;
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	public function generateOnSaleFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$filterName = 'pr_onsale';
		$settings   = $this->getFilterSetting($filter, 'settings', array());

		$defaultOnsale = FrameWpf::_()->isPro() ? $this->getFilterSetting($settings, 'f_default_onsale', false) : false;
		$hiddenOnsale  = $defaultOnsale && $this->getFilterSetting($settings, 'f_hidden_onsale', false);

		$layout      = $this->getFilterLayout($settings, $filterSettings);
		$inLineClass = $layout['class'];

		if ( $layout['is_ver'] ) {
			$this->setFilterCss('#' . self::$blockId . ' {display: inline-block; min-width: auto;}');
		}

		$labels = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('OnSale');

		$label = __($this->getFilterSetting($settings, 'f_checkbox_label', $labels['onsale']), 'woo-product-filter');

		$u          = new stdClass();
		$u->term_id = '1';
		$u->name    = $label;
		$u->slug    = '1';
		$onSale[]   = $u;

		$defSelected                           = $this->getFilterUrlData($filterName);
		$onSaleSelected                        = $hiddenOnsale ? array(1) : array($defSelected);
		$frontendTypes                         = array('list');
		$type                                  = $this->getFilterSetting($filter['settings'], 'f_frontend_type', 'list', null, DispatcherWpf::applyFilters('getFrontendFilterTypes', $frontendTypes, $filter['id']));
		$filter['settings']['f_frontend_type'] = $type;
		$htmlOpt                               = '';

		if ( in_array($type, $frontendTypes) ) {
			self::$leer = true;
			$htmlOpt    = $this->generateTaxonomyOptionsHtml( $onSale, $onSaleSelected, $filter, false, '', $layout );
			$htmlOpt    = '<ul class="wpfFilterVerScroll' . $inLineClass . '">' . $htmlOpt . '</ul>';
		} else {
			$htmlOpt = DispatcherWpf::applyFilters('getTaxonomyOptionsHtml', $htmlOpt, array(
				'type'     => $type,
				'settings' => $filter,
				'terms'    => $onSale,
				'selected' => $onSaleSelected,
				'display'  => $layout,
				'class'    => $inLineClass,
			));
		}

		$noActive = $defSelected ? '' : 'wpfNotActive';
		if ( $hiddenOnsale ) {
			$noActive = 'wpfHidden wpfPreselected';
		}
		$html =
			'<div class="wpfFilterWrapper ' . $noActive . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, $filter['settings']['f_frontend_type']) .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $htmlOpt;
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	public function generateInStockFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$optionsAll = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('InStock');

		$settings = $this->getFilterSetting($filter, 'settings', array());
		$options  = $this->getFilterSetting($settings, 'f_options[]', '');
		$options  = explode(',', $options);

		$defSelected = $this->getFilterUrlData('pr_stock');
		if ( empty($defSelected) ) {
			if ( $this->getFilterSetting($settings, 'f_default_stock', false) && $this->getFilterSetting($settings, 'f_no_preselect', false) ) {
				$defSelected = $this->getFilterSetting($settings, 'f_hidden_stock_status');
			}
		}
		
		$stockSelected = $defSelected;
		if ( $stockSelected ) {
			$stockSelected = explode('|', $stockSelected);
		}
		
		$inStock = array();

		$changeNames = ( $this->getFilterSetting($settings, 'f_status_names', '') == 'on' );
		$names       = array('instock' => 'in', 'outofstock' => 'out', 'onbackorder' => 'on');
		$i           = 0;
		foreach ( $options as $key ) {
			if ( isset($optionsAll[$key]) ) {
				++$i;
				$u          = new stdClass();
				$u->term_id = $i;
				$u->name    = $changeNames ? $this->getFilterSetting($settings, 'f_stock_statuses[' . $names[$key] . ']', $optionsAll[$key]) : $optionsAll[$key];
				$u->name    = __($u->name, 'woo-product-filter');
				$u->slug    = $key;
				$inStock[]  = $u;
			}
		}

		$frontendTypes                         = array('dropdown', 'list');
		$type                                  = $this->getFilterSetting($filter['settings'], 'f_frontend_type', 'dropdown', null, DispatcherWpf::applyFilters('getFrontendFilterTypes', $frontendTypes, $filter['id']));
		$filter['settings']['f_frontend_type'] = $type;
		$filter['is_slugs']                    = true;
		$htmlOpt                               = '';

		if ( in_array($type, $frontendTypes) ) {
			self::$leer = true;
			$htmlOpt    = $this->generateTaxonomyOptionsHtml( $inStock, $stockSelected, $filter, false, '', false );
			if ( 'list' === $type ) {
				$htmlOpt = '<ul class="wpfFilterVerScroll">' . $htmlOpt . '</ul>';
			} elseif ( 'dropdown' === $type ) {
				$htmlOpt = '<option value="" data-slug="">' . esc_html__($this->getFilterSetting($settings, 'f_dropdown_first_option_text', 'Select all'), 'woo-product-filter') . '</option>' . $htmlOpt;
				$htmlOpt = '<select>' . $htmlOpt . '</select>';
				$logic   = 'or';
			}
		} else {
			$htmlOpt = DispatcherWpf::applyFilters('getTaxonomyOptionsHtml', $htmlOpt, array(
				'type'     => $type,
				'settings' => $filter,
				'terms'    => $inStock,
				'selected' => $stockSelected,
			));
		}

		$noActive = $defSelected ? '' : 'wpfNotActive';
		$html     =
			'<div class="wpfFilterWrapper ' . $noActive . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, 'pr_stock', $filter['settings']['f_frontend_type']) .

				$filter['blockAttributes'] .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= $htmlOpt;
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>'; //end wpfFilterWrapper

		return $html;
	}
	public function generateRatingFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$filterName     = 'pr_rating';
		$ratingSelected = ReqWpf::getVar($filterName);

		$settings                              = $this->getFilterSetting($filter, 'settings', array());
		$type                                  = $this->getFilterSetting($settings, 'f_frontend_type', 'list', null, array('list', 'dropdown', 'mul_dropdown'));
		$filter['settings']['f_frontend_type'] = $type;
		$addText                               = __($this->getFilterSetting($settings, 'f_add_text', 'and up'), 'woo-product-filter');
		$addText5                              = __($this->getFilterSetting($settings, 'f_add_text5', '5 only'), 'woo-product-filter');
		$useExactValues                        = $this->getFilterSetting($settings, 'f_use_exact_values', false);

		$wrapperStart = '<ul class="wpfFilterVerScroll">';
		$wrapperEnd   = '</ul>';

		if ( ! $useExactValues ) {
			$ratingItems = array(
				array('1', $addText5, '5-5'),
				array('2', '4 ' . $addText, '4-5'),
				array('3', '3 ' . $addText, '3-5'),
				array('4', '2 ' . $addText, '2-5'),
				array('5', '1 ' . $addText, '1-5'),
			);
		} else {
			$ratingItems = array(
				array('1', '5', '5'),
				array('2', '4', '4'),
				array('3', '3', '3'),
				array('4', '2', '2'),
				array('5', '1', '1'),
			);
		}

		$rating = array();

		foreach ( $ratingItems as $item ) {
			$u          = new stdClass();
			$u->term_id = $item[2];
			$u->name    = $item[1];
			$u->slug    = $item[2];
			$rating[]   = $u;
		}
		$layout = $this->getFilterLayout($settings, $filterSettings);

		if ( ! is_array($ratingSelected) ) {
			$ratingSelected = array($ratingSelected);
		}

		self::$leer = true;
		$htmlOpt    = $this->generateTaxonomyOptionsHtml( $rating, $ratingSelected, $filter, false, '', $layout );

		if ( 'list' === $type ) {
			$wrapperStart = '<ul class="wpfFilterVerScroll' . $layout['class'] . '">';
			$wrapperEnd   = '</ul>';
		} elseif ( 'dropdown' === $type ) {
			$wrapperStart = '<select>';
			$text         = $this->getFilterSetting($settings, 'f_dropdown_first_option_text');

			if ( ! empty($text) ) {
				$htmlOpt = '<option value="" data-slug="">' . esc_html__($text, 'woo-product-filter') . '</option>' . $htmlOpt;
			} else {
				$htmlOpt = '<option value="" data-slug="">' . esc_html__('Select all', 'woo-product-filter') . '</option>' . $htmlOpt;
			}
			$wrapperEnd = '</select>';
		} elseif ( 'mul_dropdown' === $type ) {
			$wrapperStart = '<select multiple>';
			$wrapperEnd   = '</select>';
		}

		$noActive = ReqWpf::getVar($filterName) ? '' : 'wpfNotActive';

		$html =
			'<div class="wpfFilterWrapper ' . $noActive . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, $type) .

				$filter['blockAttributes'] .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $wrapperStart;
		$html .= $htmlOpt;
		$html .= $wrapperEnd;
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	public function generateAttributeFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings                              = $this->getFilterSetting($filter, 'settings', array());
		$labels                                = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('Attribute');
		$frontendTypes                         = array('list', 'radio', 'dropdown', 'mul_dropdown');
		$hidden_atts                           = $this->getFilterSetting($settings, 'f_hidden_attributes', false);
		$type                                  = $hidden_atts ? 'list' : $this->getFilterSetting($settings, 'f_frontend_type', 'list', null, DispatcherWpf::applyFilters('getFrontendFilterTypes', $frontendTypes, $filter['id']));
		$filter['settings']['f_frontend_type'] = $type;

		$includeAttsId   = ( ! empty($settings['f_mlist[]']) ) ? explode(',', $settings['f_mlist[]']) : false;
		$attrId          = $this->getFilterSetting($settings, 'f_list', 0, true);
		$excludeIds      = $this->getFilterSetting($settings, 'f_exclude_terms', false);
		$hideEmpty       = $this->getFilterSetting($settings, 'f_hide_empty', false);
		$hideEmptyActive = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_empty_active', false);
		$hideBySingle    = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_by_single', false);

		$args = array(
			'parent'     => 0,
			'hide_empty' => $hideEmpty,
			'include'    => $includeAttsId,
		);

		$isCustomOrder  = $includeAttsId && ! empty($settings['f_order_custom']);
		$order          = ! $isCustomOrder && ! empty($settings['f_sort_by']) ? $settings['f_sort_by'] : 'default';
		$isDefaultOrder = ( 'default' === $order );

		if ( $isCustomOrder || ! $isDefaultOrder ) {
			$args = array_merge($args, array(
				'orderby'         => ( $isCustomOrder ? 'include' : 'name' ),
				'order'           => ( $isDefaultOrder ? 'asc' : $order ),
				'sort_as_numbers' => ( $isDefaultOrder ? false : $this->getFilterSetting($settings, 'f_sort_as_numbers', false) ),
			));
		}

		$needIndex = FrameWpf::_()->getModule( 'woofilters' )->getView()->needIndex;
		$index     = ( in_array( $filter['name'], $needIndex ) ) ? "_{$key}" : '';

		$isCustom = ! empty($filter['custom_taxonomy']);
		if ( $isCustom ) {
			$customTaxonomy = $filter['custom_taxonomy'];
			$attrName       = $customTaxonomy->attribute_name;
			$attrSlug       = $customTaxonomy->attribute_slug;
			$attrLabel      = strtolower($customTaxonomy->attribute_label);
			$filterNameSlug = $attrSlug;
			$filterName     = "{$customTaxonomy->filter_name}{$index}";
		} else {
			$attrName       = wc_attribute_taxonomy_name_by_id( (int) $attrId);
			$attrLabel      = strtolower(wc_attribute_label($attrName));
			$filterNameSlug = str_replace('pa_', '', $attrName);
			$filterName     = "wpf_filter_{$filterNameSlug}{$index}";
		}
		$attrLabel = strip_tags($attrLabel);

		$logic     = FrameWpf::_()->getModule('woofilters')->getAttrFilterLogic();
		$logicSlug = $this->getFilterSetting($settings, 'f_query_logic', 'or', false, array_keys($logic['loop']));
		$notIds    = '';
		$notValues = '';
		if ( $hidden_atts ) {
			if ( ! $includeAttsId && $excludeIds ) {
				$logicSlug = 'not';
				$notValues = '" data-not-ids="' . esc_attr($excludeIds);
			}
		}

		$show_all_atts = isset($filter['settings']['f_show_all_attributes']) ? $filter['settings']['f_show_all_attributes'] : false;

		// add additional slug for filter name if logic is out of standard woocmmerce filter
		if ( 'not_in' == $logicSlug ) {
			$filterName    = 'pr_' . $filterName;
			$show_all_atts = true;
		}
		
		$openOneByOne                    = $this->getFilterSetting($filterSettings['settings'], 'open_one_by_one');
		$displayOnlyDescendantsСategory = $this->getFilterSetting($filterSettings['settings'], 'display_only_descendants_category');

		if ( $openOneByOne && $displayOnlyDescendantsСategory ) {
			list( $showedTerms, $countsTerms, $showFilter, $allTerms ) = $this->getShowedTermsByLevel( $attrName, $show_all_atts, $key );
			$productAttr = $this->getTaxonomyByShowedTerms($showedTerms, $attrName);           
		} else {
			list($showedTerms, $countsTerms, $showFilter, $allTerms) = $this->getShowedTerms($attrName, $show_all_atts, $filterName);
		}

		//doing the sorting through the hook while some themes/plugins impose their own
		if ( $isCustomOrder ) {
			$args['wpf_orderby'] = implode(',', $includeAttsId);
			add_filter('terms_clauses', array($this, 'wpfAddTermsClauses'), 99, 3);
		}
		if ( $isCustom ) {
			$custArgs = array(
				'wpf_fbv'   => $this->getFilterSetting($filterSettings['settings'], 'filtering_by_variations'),
				'all_attrs' => $this->getFilterSetting($settings, 'f_show_all_attributes', false),
			);
		}

		if ( ! isset($productAttr) ) {
			$productAttr = $isCustom ? DispatcherWpf::applyFilters('getCustomTerms', array(), $attrSlug, array_merge($args, $custArgs)) : $this->getTaxonomyHierarchy($attrName, $args);
			remove_filter('terms_clauses', array($this, 'wpfAddTermsClauses'), 99, 3);

			if ( ! $productAttr ) {
				return '';
			}
		}        

		$defSelected  = $this->getFilterUrlData($filterName);
		$attrSelected = $defSelected;
		if ( $attrSelected ) {
			if ( $attrSelected == $this->getFilterSetting($settings, 'f_select_default_id') ) {
				$attrSelected     = explode( '|', $attrSelected );
				$filter['is_ids'] = true;
			} else {
				$delimetrList = array_values( $logic['delimetr'] );
				foreach ( $delimetrList as $delimetr ) {
					$slugs = explode( $delimetr, $attrSelected );
					if ( count( $slugs ) > 1 ) {
						break;
					}
				}
			}
		} elseif ( $hidden_atts && $includeAttsId ) {
			$attrSelected     = $includeAttsId;
			$filter['is_ids'] = true;
		}
		if ( ! empty( $slugs ) ) {
			$changeToId = false;
			$module     = FrameWpf::_()->getModule( 'woofilterpro' );

			if ( isset( $module->acf_prefix ) && 0 === strpos( $filterName, $module->acf_prefix ) ) {
				$key        = str_replace( $module->acf_prefix, '', $filterName );
				$keyId      = FrameWpf::_()->getModule('woofilters')->getMetaKeyId( $key );
				$changeToId = true;
			}

			foreach ( $slugs as &$value ) {
				$value = ( $changeToId && ! is_numeric( $value ) )
					? FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getMetaValueId( $keyId, $value )
					: strtolower( urlencode( $value ) );
			}

			$attrSelected = $slugs;
		}

		$layout      = $this->getFilterLayout($settings, $filterSettings);
		$inLineClass = $layout['class'];

		$htmlOpt = '';
		if ( $defSelected && ! $hideEmptyActive ) {
			$showedTerms = ( ! empty($allTerms) ) ? $allTerms : false;
			$showFilter  = true;
		}

		if ( in_array($type, $frontendTypes) ) {

			self::$leer = true;
			$htmlOpt    = ( ! empty($productAttr) ) ? $this->generateTaxonomyOptionsHtml( $productAttr, $attrSelected, $filter, $excludeIds, '', $layout, false, $showedTerms, $countsTerms ) : ''; 

			if ( 'list' == $type || 'radio' == $type ) {
				$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
				if ( $maxHeight > 0 ) {
					$this->setFilterCss('#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}');
				}
				$htmlOpt = '<ul class="wpfFilterVerScroll' . $inLineClass . '">' . $htmlOpt . '</ul>';
			} elseif ( 'dropdown' == $type ) {
				$htmlOpt =
					'<select><option value="" data-slug="">' .
					esc_html__($this->getFilterSetting($settings, 'f_dropdown_first_option_text', 'Select all'), 'woo-product-filter') .
					'</option>' . $htmlOpt . '</select>';
			} elseif ( 'mul_dropdown' == $type ) {
				$htmlOpt = $this->getMultiSelectHtml( $htmlOpt, $settings );
			}
		} else {
			$htmlOpt = DispatcherWpf::applyFilters('getTaxonomyOptionsHtml', $htmlOpt, array(
				'type'     => $type,
				'settings' => $filter,
				'terms'    => $productAttr,
				'selected' => $attrSelected,
				'showed'   => $showedTerms,
				'counts'   => $countsTerms,
				'excludes' => $excludeIds,
				'includes' => false,
				'display'  => $layout,
				'class'    => $inLineClass,
			));
		}

		$blockStyle = ( ! $showFilter || ( ! $show_all_atts && self::$isLeerFilter ) ? 'display:none;' : '' ) . $blockStyle;
		if ( ! empty($blockStyle) ) {
			$this->setFilterCss('#' . self::$blockId . ' {' . $blockStyle . '}');
		}

		$noActive  = $defSelected ? '' : 'wpfNotActive';
		$noActive  = $hidden_atts ? 'wpfHidden' : $noActive;
		$showCount = $filter['settings']['f_show_count'] ? ' wpfShowCount' : '';

		$preselected = $hidden_atts ? ' wpfPreselected' : '';

		$html =
			'<div class="wpfFilterWrapper ' . $noActive . $showCount . $preselected . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, $type, esc_attr($filterNameSlug)) .

				' data-query-logic="' . $logicSlug . $notValues .
				'" data-radio="' . ( 'radio' === $type ? '1' : '0' ) .
				'" data-taxonomy="' . $attrName .
				'" data-label="' . ( '' !== $attrLabel ? $attrLabel : $filterNameSlug ) .
				'" data-hide-active="' . ( $hideEmptyActive ? '1' : '0' ) .
				'" data-show-all="' . ( (int) $show_all_atts ) .
				'" data-hide-single="' . ( (int) $hideBySingle ) . '"' .
				$filter['blockAttributes'] .
			'>';
		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= $this->generateSearchFieldList('<div class="wpfCheckboxHier">' . $htmlOpt . '</div>', $settings, $labels);
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	public function generateSearchFieldList( $html, $settings, $labels ) {
		$type = $this->getFilterSetting($settings, 'f_frontend_type', 'list');
		if ( ( 'list' != $type && 'radio' != $type ) ||
			! $this->getFilterSetting($settings, 'f_show_search_input', false
		) ) {
			return $html;
		}
		$isPro = FrameWpf::_()->isPro();

		$search = '<div class="wpfSearchWrapper"><input class="wpfSearchFieldsFilter passiveFilter" type="text" placeholder="' .
			esc_html__($this->getFilterSetting($settings, 'f_search_label', $labels['search']), 'woo-product-filter') . '">';

		if ( $isPro && $this->getFilterSetting($settings, 'f_show_search_button', false) ) {
			$search .= '<button></button>';
		}
		$search .= '</div>';
		if ( $isPro && $this->getFilterSetting($settings, 'f_search_position', 'before') == 'after' ) {
			$html .= $search;
		} else {
			$html = $search . $html;
		}
		return $html;
	}

	/**
	 * Get curent taxonomy terms (as exeption right now we use it also with acf meta)
	 *
	 * @param string $taxonomy
	 * @param bool $showAll
	 *
	 * @return array
	 */
	public function getShowedTerms( $taxonomy, $showAll, $filterName = '' ) {
		$showFilter       = true;
		$showedTerms      = false;
		$countsTerms      = false;
		$allTerms         = false;
		$terms            = self::$filterExistsTerms;
		$withoutFiltering = self::$filterExistsTermsWithotFiltering;

		if ( is_array($terms) ) {
			$countsTerms = array();

			if ( isset($terms[$taxonomy]) ) {
				if ( ! $showAll ) {
						$showedTerms = array_keys($terms[$taxonomy]);
				}
				$countsTerms = $terms[$taxonomy];

			} elseif ( ! $showAll ) {
				$showedTerms = array();
			}
			if ( ! $showAll && empty($showedTerms) ) {
				$showFilter = false;
			}
			if ( ! self::$currentFilterRecount && isset($withoutFiltering[$taxonomy]) ) {
				$countsTerms = $withoutFiltering[$taxonomy];
			}
		}

		if ( ! $showAll ) {
			$allTerms = empty($withoutFiltering[$taxonomy]) ? array() : array_keys($withoutFiltering[$taxonomy]);
		}       
		
		return array($showedTerms, $countsTerms, $showFilter, $allTerms);
	}

	public function getShowedTermsByLevel( $taxonomy, $showAll, $level ) {
		$showFilter       = true;
		$showedTerms      = false;
		$countsTerms      = false;
		$allTerms         = false;
		$terms            = self::$filterExistsTerms;
		$withoutFiltering = self::$filterExistsTermsWithotFiltering;

		if ( is_array($terms) ) {
			$countsTerms = array();

			if ( isset($terms[$taxonomy . '_hierarchical']) && isset($terms[$taxonomy . '_hierarchical'][$level]) ) {
				if ( ! $showAll ) {
					$showedTerms = array_keys($terms[$taxonomy . '_hierarchical'][$level]);
				} 
				
				$countsTerms = $terms[$taxonomy . '_hierarchical'][$level];
			}

			if ( ! self::$currentFilterRecount && isset($withoutFiltering[$taxonomy . '_hierarchical'][$level]) ) {
				$countsTerms = $withoutFiltering[$taxonomy . '_hierarchical'][$level];
			}
		}

		if ( ! $showAll && isset($withoutFiltering[$taxonomy . '_hierarchical']) && isset($withoutFiltering[$taxonomy . '_hierarchical'][$level]) ) {
			$allTerms = array_keys($withoutFiltering[$taxonomy . '_hierarchical'][$level]);
		}         

		return array($showedTerms, $countsTerms, $showFilter, $allTerms);
	}

	public function getTaxonomyByShowedTerms( $showedTerms, $taxonomy ) {
		if ( empty($showedTerms) || ! is_array($showedTerms) || empty($taxonomy) ) {
			return array();
		}

		$args = array(
			'include'    => $showedTerms,
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		);

		$terms = get_terms($args);

		if ( is_wp_error($terms) || empty($terms) ) {
			return array();
		}

		$result = array();
		foreach ( $terms as $term ) {
			$result[$term->term_id] = $term;
		}

		$this->setLeerFilter(false);      
		return $result;
	}

	public function wpfAddTermsClauses( $clauses, $taxonomies, $args ) {
		if ( isset($args['wpf_orderby']) ) {
			$clauses['orderby'] = ' ORDER BY FIELD( t.term_id, ' . $args['wpf_orderby'] . ')';
		}
		return $clauses;
	}

	public function wpfGetTermsOrderby( $orderby, $args ) {
		return isset($args['wpf_orderby']) ? 'FIELD( t.term_id, ' . $args['wpf_orderby'] . ')' : $orderby;
	}


	/**
	 * Recursively get taxonomy and its children
	 *
	 * @param string $taxonomy
	 * @param int $parent - parent term id
	 * @return array
	 */
	public function getTaxonomyHierarchy( $taxonomy, $argsIn, $parent = true ) {
		// only 1 taxonomy
		$taxonomy = is_array( $taxonomy ) ? array_shift( $taxonomy ) : $taxonomy;
		// get all direct decendants of the $parent
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => $argsIn['hide_empty'],
		);
		if ( isset($argsIn['order']) ) {
			$args['orderby'] = ! empty($argsIn['orderby']) ? $argsIn['orderby'] : 'name';
			$args['order']   = $argsIn['order'];
		}

		if ( ! empty($argsIn['include']) ) {
			$args['include'] = $argsIn['include'];
		}

		if ( ! empty($argsIn['parent']) && 0 !== $argsIn['parent'] ) {
			$args['parent'] = $argsIn['parent'];
		} else {
			$args['parent'] = 0;
		}

		if ( '' === $taxonomy ) {
			return false;
		}

		if ( 'product_cat' === $taxonomy && $parent ) {
			$args['parent'] = 0;
		}

		if ( ! empty($argsIn['include']) ) {
			$args['include']       = $argsIn['include'];
			$args['parent']        = '';
			$argsIn['only_parent'] = true;
			if ( ! empty($argsIn['wpf_orderby']) ) {
				$args['wpf_orderby'] = $argsIn['wpf_orderby'];
			}
		}

		$terms = get_terms( $args );
		// prepare a new array.  these are the children of $parent
		// we'll ultimately copy all the $terms into this new array, but only after they
		// find their own children

		if ( isset($argsIn['sort_as_numbers']) && $argsIn['sort_as_numbers'] ) {
			$terms = DispatcherWpf::applyFilters('sortAsNumbers', $terms, $args['order']);
		}

		$children = array();
		// go through all the direct decendants of $parent, and gather their children
		if ( ! is_wp_error($terms) ) {
			foreach ( $terms as $term ) {
				if ( empty($argsIn['only_parent']) ) {
					if ( ! empty($term->term_id) ) {
						$args = array(
							'hide_empty' => $argsIn['hide_empty'],
							'parent'     => $term->term_id,
						);
						if ( isset($argsIn['order']) ) {
							$args['order']   = $argsIn['order'];
							$args['orderby'] = ! empty($argsIn['orderby']) ? $argsIn['orderby'] : 'name';
						}

						// recurse to get the direct decendants of "this" term
						$term->children = $this->getTaxonomyHierarchy( $taxonomy, $args, false );
					}
				}
				// add the term to our new array
				$children[ $term->term_id ] = $term;
			}
		}
		// send the results back to the caller
		return $children;
	}

	public function wpfGetFilteredPriceFromProductList( $settings, $listTable, $convert = true, $dec = 0, $tax = '' ) {
		global $wpdb;

		list( $tax, $taxSql ) = DispatcherWpf::applyFilters( 'priceTax', array( '', '' ), 'add', $settings );
		$module               = FrameWpf::_()->getModule('woofilters');
		$metas                = apply_filters( 'woocommerce_price_filter_meta_keys', array( '_price' ));
		$metaIds              = array();
		$wpfMeta              = true;
		
		$query = false;
		if ( class_exists('WooCommerceB2B') && FrameWpf::_()->getModule('options')->getModel()->get('use_wcb2b_prices') == 1 ) {
			$user   = wp_get_current_user();
			$userId = $user ? $user->ID : 0;
			if ( $userId ) {
				$groupId = get_user_meta($userId, 'wcb2b_group', true);
				if ( ! empty($groupId) ) {
					$metaId = $module->getMetaKeyId('wcb2b_product_group_prices');
					if ( $metaId ) {
						if ( empty($dec) ) {
							$sql = "SELECT min( FLOOR( value{$taxSql} ) ) as wpfMinPrice, max( CEILING( value{$taxSql} ) ) as wpfMaxPrice ";
						} else {
							$sql = "SELECT min( ROUND( value{$taxSql}, {$dec} ) ) as wpfMinPrice, max( ROUND( value{$taxSql}, {$dec} ) ) as wpfMaxPrice";
						}
						$query = $sql . ' FROM ' . $listTable . ' AS wpf_temp ' .
							' INNER JOIN @__meta_data pm ON (pm.product_id=wpf_temp.ID)' .
							' INNER JOIN @__meta_values vm ON (vm.id=pm.val_id)' .
							' WHERE pm.key_id=' . $metaId . ' AND vm.key_id=' . $metaId . " AND vm.key2='" . $groupId . "'";

					}
				}
			}
		}
		if ( empty($query) ) {
			foreach ( $metas as $key ) {
				$id = $module->getMetaKeyId($key);
				if ( $id ) {
					$metaIds[] = $id;
				} else {
					$wpfMeta = false;
					break;
				}
			}
			if ( $wpfMeta ) {
				if ( empty($dec) ) {
					$sql = "SELECT min( FLOOR( val_dec{$taxSql} ) ) as wpfMinPrice, max( CEILING( val_dec{$taxSql} ) ) as wpfMaxPrice ";
				} else {
					$sql = "SELECT min( ROUND( val_dec{$taxSql}, {$dec} ) ) as wpfMinPrice, max( ROUND( val_dec{$taxSql}, {$dec} ) ) as wpfMaxPrice";
				}
				$query = $sql . ' FROM ' . $listTable . ' AS wpf_temp ' .
					' INNER JOIN @__meta_data pm ON (pm.product_id=wpf_temp.ID)
					WHERE key_id IN (' . implode( ',', $metaIds ) . ')';

			} else {
				if ( empty($dec) ) {
					$sql = "SELECT min( FLOOR( meta_value{$taxSql} ) ) as wpfMinPrice, max( CEILING( meta_value{$taxSql} ) ) as wpfMaxPrice ";
				} else {
					$sql = "SELECT min( ROUND( meta_value{$taxSql}, {$dec} ) ) as wpfMinPrice, max( ROUND( meta_value{$taxSql}, {$dec} ) ) as wpfMaxPrice";
				}
				$query = $sql . ' FROM ' . $listTable . ' AS wpf_temp ' .
					' INNER JOIN ' . $wpdb->postmeta . ' pm ON (pm.post_id=wpf_temp.ID)
					WHERE meta_key IN ("' . implode( "','", array_map( 'esc_sql', $metas ) ) . '")';
			}
		}
		$price = DbWpf::get($query, 'row', OBJECT);

		if ( $price && $convert ) {
			$price->wpfMaxPrice = $module->getCurrencyPrice( (float) $price->wpfMaxPrice, $dec );
			$price->wpfMinPrice = $module->getCurrencyPrice( (float) $price->wpfMinPrice, $dec );
		}

		if ( '' !== $tax ) {
			$price->tax = $tax;
		}

		return $price;
	}

	public function wpfGetFilteredPrice( $convert = true, $args = null, $dec = 0 ) {
		return self::$filterExistsPrices;
	}

	protected function generateTaxonomyOptionsHtmlFromPro( $filterItemList, $selectedElem, $filter = false, $excludeIds = false, $pre = '', $layout = 0, $includeIds = false, $showedTerms = false, $countsTerms = false ) {
		self::$leer = true;
		return $this->generateTaxonomyOptionsHtml($filterItemList, $selectedElem, $filter, $excludeIds, $pre, $layout, $includeIds, $showedTerms, $countsTerms);
	}

	private function generateTaxonomyOptionsHtml( $filterItemList, $selectedElem, $filter = false, $excludeIds = false, $pre = '', $layout = 0, $includeIds = false, $showedTerms = false, $countsTerms = false, $itemLevel = 0, $currentCategoryId = 0 ) {
		$html     = '';
		$settings = $this->getCurrentSettings();
		$options  = FrameWpf::_()->getModule( 'options' )->getModel( 'options' )->getAll();

		if ( $excludeIds && ! is_array($excludeIds) ) {
			$excludeIds = explode(',', $excludeIds);
		}
		if ( $includeIds && ! is_array($includeIds) ) {
			$includeIds = explode(',', $includeIds);
		}
		$showCount            = $this->getFilterSetting($filter['settings'], 'f_show_count');
		$showImage            = FrameWpf::_()->isPro() && $this->getFilterSetting($filter['settings'], 'f_show_images', false);
		$allProductsFiltering = $this->getFilterSetting($settings['settings'], 'all_products_filtering', false);
		if ( $allProductsFiltering && ( ! empty($filter['custom_taxonomy'] ) || ! empty($filter['custom_meta']) ) ) {
			$allProductsFiltering = false;
		}

		if ( $showImage ) {
			$imgSize = array($this->getFilterSetting($filter['settings'], 'f_images_width', 20), $this->getFilterSetting($filter['settings'], 'f_images_height', 20));
		}
		$type    = $this->getFilterSetting($filter['settings'], 'f_frontend_type', 'list');
		$isMulti = ( 'multi' === $type );

		if ( FrameWpf::_()->isPro() ) {
			if ( method_exists(FrameWpf::_()->getModule('woofilterpro'), 'getCollapsibleFiltreOptions') ) {
				$collapsibleList = FrameWpf::_()->getModule('woofilterpro')->getCollapsibleFiltreOptions($filter['id']);
			} else {
				$collapsibleList = array('multi');
			}
			if ( in_array($type, $collapsibleList) ) {
				$isCollapsible = $this->getFilterSetting($filter['settings'], 'f_multi_collapsible', false);
			}
		}

		$isHierarchical = $this->getFilterSetting($filter['settings'], 'f_show_hierarchical', false);
		$hideParent     = $isHierarchical && $this->getFilterSetting($filter['settings'], 'f_hide_parent', false);
		$hideAllParent  = $isHierarchical && $this->getFilterSetting($filter['settings'], 'f_hide_all_parent', false);
		$showCustomTags = $this->getFilterSetting($filter['settings'], 'f_custom_tags', false);
		$titleTag       = $this->getFilterSetting($filter['settings'], 'f_custom_tags_settings[title_1]', 0);
		$menuMode       = ( 'mul_dropdown' == $type || 'multi' == $type ? false : $this->getFilterSetting($filter['settings'], 'f_menu_mode', false) );
		if ( $pre ) {
			$preCount = count( explode(';', $pre) );

			if ( 9 <= $preCount ) {
				$preCount = 4;
			} elseif ( 6 <= $preCount ) {
				$preCount = 3;
			} else {
				$preCount = 2;
			}

			$titleTag = $this->getFilterSetting($filter['settings'], 'f_custom_tags_settings[title_' . $preCount . ']', 0);
		}
		$titleTag = $titleTag && $showCustomTags ? FrameWpf::_()->getModule('woofilters')->getFilterTagsList()[$titleTag] : 'span';

		if ( is_array($layout) && 'dropdown' != $type && 'mul_dropdown' != $type ) {
			if ( $layout['is_ver'] ) {
				if ( $layout['cnt'] > 1 ) {
					$width = number_format(100 / $layout['cnt'], 4, '.', '');
					$this->setFilterCss('#' . self::$blockId . ' .wpfFilterLayoutVer>li {width:' . $width . '% !important;}');
				}
			}
		}
		$filterName = $this->getFilterSetting($filter, 'name', '');
		$isSlug     = $this->getFilterSetting($filter, 'is_slugs', false) || ( ! $this->getFilterSetting($filter, 'is_ids', false) && ( 'product_tag' == $filterName || 'product_brand' == $filterName || ( strpos($filterName, 'filter_') >= 0 && $this->getFilterSetting($filter, 'id', '') == 'wpfAttribute' ) ) );

		$iconCollapsible = '<span class="wpfCollapsible">' . DispatcherWpf::applyFilters('getIconHtml', '+', 'categories_icon', $settings) . '</span>';
		$collapseLevel   = $this->getFilterSetting($filter['settings'], 'f_collapse_level', false);

		$openOneByOne                = $this->getFilterSetting($settings['settings'], 'open_one_by_one');        
		$displayOnlyChildrenCategory = $this->getFilterSetting($settings['settings'], 'display_only_children_category');
		$loadViaAjax = ( $openOneByOne && $displayOnlyChildrenCategory ) ? true : false;
		
		foreach ( $filterItemList as $filterItem ) {
			if ( ! empty($excludeIds) && in_array($filterItem->term_id, $excludeIds) ) {
				continue;
			}

			if ( ! empty($includeIds) && ! in_array($filterItem->term_id, $includeIds) ) {
				continue;
			}
			if ( ! isset($filterItem->parent) ) {
				$filterItem->parent = 0;
			}
			$termId      = isset($filterItem->term_id) ? $filterItem->term_id : '';
			$style       = '';
			$displayName = DispatcherWpf::applyFilters('getItemDisplayName', empty($filterItem->name_label) ? $filterItem->name : $filterItem->name_label, $filterItem);

			$addAttrs = $menuMode ? ' data-link="' . get_term_link($termId, 'product_cat') . '"' : '';

			$hasChildren = ! empty( $filterItem->children );

			$isChild = 0 != $filterItem->parent;

			if ( 'dropdown' === $type || 'mul_dropdown' === $type ) {

				$selected = '';
				if ( is_array($selectedElem) && in_array($isSlug ? $filterItem->slug : $filterItem->term_id, $selectedElem) ) {
					$selected = 'selected';
				}

				if ( is_array($showedTerms) && ( empty($showedTerms) || ! in_array($filterItem->term_id, $showedTerms) ) ) {
					$style = ' display:none;';
				} elseif ( ! $hideParent || ( $isChild && ( ! $hideAllParent || ! $hasChildren ) ) ) {
					self::$leer = false;
				}

				$slug = isset($filterItem->slug) ? urldecode($filterItem->slug) : '';
				$name = isset($filterItem->name) ? urldecode($filterItem->name) : '';

				$count = isset($filterItem->count) ? $filterItem->count : '';
				if ( ! $allProductsFiltering ) {
					$count = isset($countsTerms[$termId]) ? $countsTerms[$termId] : 0;
				}

				$countHtml = $showCount ? '<span class="wpfCount">(' . $count . ')</span>' : '';
				if ( ! $hideParent || ( $isChild && ( ! $hideAllParent || ! $hasChildren ) ) ) {
					$img = '';
					if ( $showImage ) {
						$metaSlug     = $this->getTaxonomyOptionality($filterItem->taxonomy, 'thumbnail_id');
						$thumbnail_id = get_term_meta($filterItem->term_id, $metaSlug, true);
						$img          = wp_get_attachment_url($thumbnail_id);
					}
					if ( ! empty($style) ) {
						$this->setFilterCss('#' . self::$blockId . ' [data-term-id="' . $termId . '"] {' . $style . '}');
					}
					$html .=
						'<option data-term-name="' . $name .
						'" value="' . $termId .
						'" data-term-slug="' . $slug .
						'" data-count="' . $count .
						'" data-term-id="' . $termId .
						'" data-slug="' . $slug .
						'" ' . $selected .
						' data-img="' . $img . 
						'"' . $addAttrs . '>' . $pre . $displayName . ' ' . $countHtml . '</option>';
				}
				if ( $hasChildren && ! $loadViaAjax ) {
					$tmpPre = ( $isHierarchical && ! $hideAllParent ) ? $pre . '&nbsp;&nbsp;&nbsp;' : $pre;
					$html  .= $this->generateTaxonomyOptionsHtml($filterItem->children, $selectedElem, $filter, false, $tmpPre, false, $includeIds, $showedTerms, $countsTerms);
				}
			} else {

				if ( is_array($showedTerms) && ( empty($showedTerms) || ! in_array($filterItem->term_id, $showedTerms) ) ) {
					$style .= 'display:none;';
				} elseif ( ! $hideParent || ( $isChild && ( ! $hideAllParent || ! $hasChildren ) ) ) {
					self::$leer = false;
				}

				$tagWrapper = ( 'text' !== $type && isset( $options['content_accessibility'] ) && '1' === $options['content_accessibility']['value'] ) ? 'div' : 'label';

				if ( ! $hideParent || ( $isChild && ( ! $hideAllParent || ! $hasChildren ) ) ) {

					if ( ! empty($style) ) {
						$this->setFilterCss('#' . self::$blockId . ' li[data-term-id="' . $filterItem->term_id . '"] {' . $style . '}');
					}

					$hidePageCategoryClass = ( $filterItem->term_id === $currentCategoryId ) ? ' class="hidePageCategory"' : '';

					$html .= '<li data-term-id="' . $filterItem->term_id . '" data-parent="' . $filterItem->parent . '" data-term-slug="' . urldecode($filterItem->slug) . '"' . $addAttrs . $hidePageCategoryClass . '>';
					$html .= "<{$tagWrapper}" . ' class="wpfLiLabel">';

					$checked = '';

					if ( is_array($selectedElem) && in_array($isSlug ? $filterItem->slug : $filterItem->term_id, $selectedElem) ) {
						$checked = 'checked';
					}

					$rand    = rand(1, 99999);
					$checkId = 'wpfTaxonomyInputCheckbox' . $filterItem->term_id . $rand;

					$checkbox = '<span class="wpfCheckbox' . ( $isMulti ? ' wpfMulti' : '' ) . '"><input type="checkbox" id="' . $checkId . '" ' . $checked . '><label aria-label="' . esc_attr( $displayName ) . '" for="' . $checkId . '"></label></span>';
					$html    .= DispatcherWpf::applyFilters('getOneTaxonomyOptionHtml', $checkbox, array('type' => $type, 'id' => $checkId, 'checked' => $checked));

					$html .= '<span class="wpfDisplay">';
					$img   = '';
					if ( $showImage ) {
						$metaSlug     = $this->getTaxonomyOptionality($filterItem->taxonomy, 'thumbnail_id');
						$thumbnail_id = get_term_meta($filterItem->term_id, $metaSlug, true);

						$img = wp_get_attachment_image($thumbnail_id, $imgSize, false, array('alt' => $displayName));
						$img = '<div class="wpfFilterTaxImgWrapper">' . $img . '</div>';
					}

					$displayName = '<' . $titleTag . ' class="wpfFilterTaxNameWrapper">' . $displayName . '</' . $titleTag . '>';

					$html .= '<span class="wpfValue">' . $img . $displayName . '</span>';

					if ( $showCount ) {
						$count = isset($filterItem->count) ? $filterItem->count : '';
						if ( ! $allProductsFiltering ) {
							$count = isset($countsTerms[$termId]) ? $countsTerms[$termId] : 0;
						}

						$html .= '<span class="wpfCount">(' . $count . ')</span>';
					}
					$html .= '</span>';

					if ( ( ! $collapseLevel || $itemLevel >= $collapseLevel ) && ! empty( $isCollapsible ) && $hasChildren && $isHierarchical ) {
						$html .= $iconCollapsible;
					}

					$html .= "</{$tagWrapper}>";
				}
				if ( $hasChildren ) {
					$tmpPre = ( $isHierarchical && ! $hideAllParent ) ? $pre . '&nbsp;&nbsp;&nbsp;' : $pre;
					if ( $isHierarchical && ! $hideParent ) {
						$html .= '<ul' . ( ( ( $collapseLevel && $itemLevel < $collapseLevel ) || empty( $isCollapsible ) || $hideAllParent ) ? '' : ' class="wpfHidden"' ) . '>';
					} elseif ( $isHierarchical && $hideParent && 0 != $filterItem->parent ) {
						$html .= '<ul class="wpfHideParent' . ( $hideAllParent ? ' wpfHideAllParent' : ( empty( $isCollapsible ) ? '' : ' wpfHidden' ) ) . '">';
					}
					$childrenLevel = $itemLevel + 1;
					$html         .= $this->generateTaxonomyOptionsHtml($filterItem->children, $selectedElem, $filter, $excludeIds, $tmpPre, false, $includeIds, $showedTerms, $countsTerms, $childrenLevel);
					if ( $isHierarchical && ! $hideParent ) {
						$html .= '</ul>';
					} elseif ( $isHierarchical && $hideParent && 0 != $filterItem->parent ) {
						$html .= '</ul>';
					}
				}
				if ( ! $hideParent || ( $isChild && ( ! $hideAllParent || ! $hasChildren ) ) ) {
					$html .= '</li>';
				}
			}
		}
		$this->setLeerFilter(self::$leer);
		return $html;
	}

	private function generatePriceRangeOptionsHtml( $filter, $ranges, $layout ) {
		$html    = '';
		$isPro   = FrameWpf::_()->isPro();
		$options = FrameWpf::_()->getModule( 'options' )->getModel( 'options' )->getAll();

		$minValue  = ReqWpf::getVar('wpf_min_price');
		$maxValue  = ReqWpf::getVar('wpf_max_price');
		$urlRange  = $minValue . ',' . $maxValue;
		$type      = $filter['settings']['f_frontend_type'];
		$underOver = $isPro && $this->getFilterSetting($filter['settings'], 'f_under_over', false);
		if ( $underOver ) {
			$underText = $this->getFilterSetting($filter['settings'], 'f_under_text', esc_attr__('Under', 'woo-product-filter')) . ' ';
			$overText  = $this->getFilterSetting($filter['settings'], 'f_over_text', esc_attr__('Over', 'woo-product-filter')) . ' ';
		}

		if ( 'list' === $type ) {
			if ( $layout['is_ver'] ) {
				if ( $layout['cnt'] > 1 ) {
					$width = number_format(100 / $layout['cnt'], 4, '.', '');
					$this->setFilterCss('#' . self::$blockId . ' .wpfFilterLayoutVer li {width:' . $width . '%;}');
				}
			}
			$isList = true;
		} elseif ( 'dropdown' === $type ) {
			$html .= '<select>';

			if ( ! empty($filter['settings']['f_dropdown_first_option_text']) ) {
				$html .= '<option value="" data-slug="">' . esc_html__($filter['settings']['f_dropdown_first_option_text'], 'woo-product-filter') . '</option>';
			} else {
				$html .= '<option value="" data-slug="">' . esc_html__('Select all', 'woo-product-filter') . '</option>';
			}
			$isList = false;
		} else {
			return '';
		}

		$module   = FrameWpf::_()->getModule('woofilters');
		$isCustom = true;
		if ( function_exists( 'alg_wc_currency_switcher_plugin' ) ) {
			add_filter( 'raw_woocommerce_price', array(alg_wc_currency_switcher_plugin()->core, 'change_price_by_currency'));
		}

		$tagWrapper = ( isset( $options['content_accessibility'] ) && '1' === $options['content_accessibility']['value'] ) ? 'div' : 'label';

		foreach ( $ranges as $range ) {
			if ( ! empty($range['1']) && isset($range['0']) ) {
				if ( 'i' === $range[0] && ! $underOver ) {
					$range[0] = 0;
				}
				if ( 'i' === $range[1] && ( ! $underOver || 'i' === $range[0] ) ) {
					$price    = self::$filterExistsPrices;
					$range[1] = $price->wpfMaxPrice;
				}

				// some plugin uses a different hook, use it if the standard one did not change the price
				if ( is_plugin_active( 'woocommerce-currency-switcher/index.php' ) || is_plugin_active( 'woocommerce-multicurrency/woocommerce-multicurrency.php' ) ) {
					$wc_price[0] = apply_filters( 'woocommerce_product_get_regular_price', $range[0], null );
					$wc_price[1] = apply_filters( 'woocommerce_product_get_regular_price', $range[1], null );
				} else {
					$wc_price = $range;
				}

				$priceRange      = ( 'i' === $wc_price[0] ? $underText . wc_price($wc_price[1] + 0.01) : ( 'i' === $wc_price[1] ? $overText . wc_price( $wc_price[0] - 0.01 ) : wc_price( $wc_price[0] ) . ' - ' . wc_price( $wc_price[1] ) ) );
				$priceRangeLabel = ( 'i' === $wc_price[0] ? $wc_price[1] + 0.01 : ( 'i' === $wc_price[1] ? $wc_price[0] - 0.01 : $wc_price[0] . ' - ' . $wc_price[1] ) );
				$dataRange       = ( 'i' === $range[0] ? '' : $module->getCurrencyPrice($range[0]) ) . ',' . ( 'i' === $range[1] ? '' : $module->getCurrencyPrice($range[1]) );
				$selected        = ( $dataRange === $urlRange );
				
				if ( $isList ) {
					$html   .= '<li data-range="' . $dataRange . '"><' . $tagWrapper . ' class="wpfLiLabel">';
					$checkId = 'wpfPriceRangeCheckbox' . rand(1, 99999);
					$html   .= '<span class="wpfCheckbox"><input type="checkbox" id="' . $checkId . '"' . ( $selected ? ' checked' : '' ) . '><label aria-label="' . esc_attr( $priceRangeLabel ) . '" for="' . $checkId . '"></label></span>';
					$html   .= '<span class="wpfDisplay"><span class="wpfValue">' . $priceRange . '</span></span>';
					$html   .= '</' . $tagWrapper . '></li>';
					if ( $selected ) {
						$isCustom = false;
					}
				} else {
					$html .= '<option data-range="' . $dataRange . '"' . ( $selected ? ' selected' : '' ) . '>' . $priceRange . '</option>';
				}
			}
		}
		if ( function_exists( 'alg_wc_currency_switcher_plugin' ) ) {
			remove_filter( 'raw_woocommerce_price', array(alg_wc_currency_switcher_plugin()->core, 'change_price_by_currency'));
		}
		if ( $isList ) {
			if ( $isPro && $this->getFilterSetting($filter['settings'], 'f_custom_fields', false) ) {
				$customText = $this->getFilterSetting($filter['settings'], 'f_custom_text', esc_attr__('Custom', 'woo-product-filter')) . ' ';
				$selected   = ( $isCustom && ( ',' != $urlRange ) );
				$checkId    = 'wpfPriceRangeCheckbox' . rand(1, 99999);
				$html      .= '<li data-range="' . ( $selected ? $urlRange : '' ) . '"><' . $tagWrapper . ' class="wpfLiLabel">';
				$html      .= '<span class="wpfCheckbox wpfPriceCheckboxCustom"><input type="checkbox" id="' . $checkId . '"' . ( $selected ? ' checked' : '' ) . '><label aria-label="' . esc_attr( $customText ) . '" for="' . $checkId . '"></label></span>';
				$html      .= '<span class="wpfDisplay"><span class="wpfValue">' . $customText . '</span></span>';
				$html      .= '<span class="wpfPriceRangeCustom"><input class="passiveFilter" type="text" name="wpf_custom_min" value="' . ( $selected ? ReqWpf::getVar('wpf_min_price') : '' ) . '"> - <input class="passiveFilter" type="text" name="wpf_custom_max"  value="' . ( $selected ? ReqWpf::getVar('wpf_max_price') : '' ) . '"><i class="fa fa-chevron-right"></i></span>';
				$html      .= '</' . $tagWrapper . '></li>';
			}
		} else {
			$html .= '</select>';
		}

		return $html;
	}

	private function generateLoaderHtml( $filterId, $settings ) {
		$settings     = $this->getFilterSetting($settings, 'settings', array());
		$colorPreview = $this->getFilterSetting($settings, 'filter_loader_icon_color', 'black');
		$iconName     = $this->getFilterSetting($settings, 'filter_loader_icon_name', 'default');
		$iconNumber   = $this->getFilterSetting($settings, 'filter_loader_icon_number', '0');
		if ( ! FrameWpf::_()->isPro() ) {
			$iconName = 'default';
		}
		$htmlPreview = '<div class="wpfPreview wpfPreviewLoader wpfHidden">';
		if ( 'custom' === $iconName ) {
			$settings['is_overlay'] = false;
			$htmlPreview            = DispatcherWpf::applyFilters('getCustomLoaderHtml', $htmlPreview, $settings);
		} elseif ( 'spinner' === $iconName || 'default' === $iconName ) {
			$htmlPreview .= '<div class="woobewoo-filter-loader spinner" ></div>';
		} else {
			$this->setFilterCss('.wpfPreviewLoader .woobewoo-filter-loader {color: ' . $colorPreview . ';}');
			$htmlPreview .= '<div class="woobewoo-filter-loader la-' . $iconName . ' la-2x">';
			for ( $i = 1; $i <= $iconNumber; $i++ ) {
				$htmlPreview .= '<div></div>';
			}
			$htmlPreview .= '</div>';
		}
		$htmlPreview .= '</div>';
		return $htmlPreview;
	}

	public function wpfGetPageId() {
		global $wp_query, $post;
		$page_id = false;
		if ( is_home() && get_option('page_for_posts') ) {
			$page_id = get_option('page_for_posts');
		} elseif ( is_front_page() && get_option('page_on_front') ) {
			$page_id = get_option('page_on_front');
		} elseif ( function_exists('is_shop') && is_shop() && get_option('woocommerce_shop_page_id') != '' ) {
				$page_id = get_option('woocommerce_shop_page_id');
		} elseif ( function_exists('is_cart') && is_cart() && get_option('woocommerce_cart_page_id') != '' ) {
				$page_id = get_option('woocommerce_cart_page_id');
		} elseif ( function_exists('is_checkout') && is_checkout() && get_option('woocommerce_checkout_page_id') != '' ) {
				$page_id = get_option('woocommerce_checkout_page_id');
		} elseif ( function_exists('is_account_page') && is_account_page() && get_option('woocommerce_myaccount_page_id') != '' ) {
				$page_id = get_option('woocommerce_myaccount_page_id');
		} elseif ( $wp_query && ! empty($wp_query->queried_object) && ! empty($wp_query->queried_object->ID) ) {
				$page_id = $wp_query->queried_object->ID;
		} elseif ( ! empty($post->ID) ) {
				$page_id = $post->ID;
		}
		return $page_id;
	}
	public function wpfCurrentLocation() {
		if ( empty($_SERVER['HTTP_HOST']) ) {
			return '';
		}
		if ( ( isset($_SERVER['HTTPS']) &&
			( ( 'on' == $_SERVER['HTTPS'] ) || ( 1 == $_SERVER['HTTPS'] ) ) ) ||
			( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
			( 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) {
			$protocol = 'https://';
		} else {
			$protocol = 'http://';
		}
		$uri_parts = explode('?', ( empty($_SERVER['REQUEST_URI']) ? '' : sanitize_text_field($_SERVER['REQUEST_URI']) ), 2);
		return $protocol . sanitize_text_field($_SERVER['HTTP_HOST']) . $uri_parts[0];
	}

	protected function getCatsByGetVar( $getVars, $slugs = true ) {
		$cats = array();
		foreach ( $getVars as $getVar => $items ) {
			if ( strpos($getVar, 'wpf_filter_cat') !== false ) {
				$ids = explode('|', $items);
				if ( count($ids) <= 1 ) {
					$ids = explode(',', $items);
				}
				if ( $slugs ) {
					$cats = array_merge($cats, array_map(function ( $id ) {
						return get_term_by('id', $id, 'product_cat', 'ARRAY_A')['slug'];
					}, $ids));
				} else {
					$cats = array_merge($cats, $ids);
				}
			}
		}

		return $cats;
	}

	protected function existGetVarLike( $getVars, $field ) {
		foreach ( $getVars as $getVar => $items ) {
			if ( strpos($getVar, $field) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve post type product taxonomies dependency with standard product_cat
	 *
	 * @param string $taxonomySlug
	 * @param string $metaSlug
	 *
	 * @return mix
	 */
	public function getTaxonomyOptionality( $taxonomySlug, $metaSlug ) {
		$meta_value = '';

		if ( 'product_cat' == $taxonomySlug ) {
			$meta_value = $metaSlug;
		} elseif ( ! empty($this->taxonomyOptionality[$taxonomySlug][$metaSlug]) ) {
				$meta_value = $this->taxonomyOptionality[$taxonomySlug][$metaSlug];
		}

		return $meta_value;
	}

	/**
	 * Resolce depricated settings optionality
	 *
	 * @param string $settings
	 *
	 * @return string
	 */
	public function resolveDepricatedOptionality( $orderTab ) {
		$orderTab = json_decode($orderTab);

		if ( is_array($orderTab) ) {
			foreach ( $orderTab as $key => $tab ) {
				if ( empty($tab->settings->f_enable_title_mobile) && ! empty($tab->settings->f_enable_title) ) {
					$orderTab[$key]->settings->f_enable_title_mobile = $tab->settings->f_enable_title;
				}
			}
		}

		return json_encode($orderTab);
	}

	/**
	 * Get filter settings with mobile breakpoin value
	 *
	 * @param array $settings
	 *
	 * @return string
	 */
	public function getMobileBreakpointValue( $filterSettings ) {
		$mobileBreakpointWidth = '';

		$isMobileBreakpointWidth =
			$this->getFilterSetting(
				$filterSettings,
				'desctop_mobile_breakpoint_switcher',
				false
		);

		if ( $isMobileBreakpointWidth ) {
			$mobileBreakpointWidth =
				$this->getFilterSetting(
					$filterSettings,
					'desctop_mobile_breakpoint_width',
					''
			);
		}

		return $mobileBreakpointWidth;
	}

	/**
	 * Find if we need include children in taxonomy.
	 *
	 * @param bool $isHideChild
	 * @param bool $isExtendParentFiltering
	 * @param string $type
	 *
	 * @return bool
	 */
	public function findTaxonomyIncludeChildrenStatus( $isHideChild, $isExtendParentFiltering, $type ) {
		$typeWIthotHierarchyList = array(
			'mul_dropdown',
			'multi',
			'buttons',
			'text',
		);

		$typeWithExtendParametrFilteringOption = array(
			'list',
			'dropdown',
		);

		if ( ! $isHideChild && in_array($type, $typeWIthotHierarchyList) ) {
			$isIncludeChildren = false;
		} else {
			$isIncludeChildren = true;
		}

		if ( in_array($type, $typeWithExtendParametrFilteringOption) ) {
			$isIncludeChildren = $isExtendParentFiltering;
		}

		return $isIncludeChildren;
	}

	public function renderSelectedFiltersHtml( $params ) {
		$id = isset($params['id']) ? (int) $params['id'] : 0;
		if ( ! $id ) {
			return false;
		}

		$filter = $this->getModel('woofilters')->getById($id);
		if ( ! $filter ) {
			return false;
		}

		$settings        = unserialize($filter['setting_data'])['settings'];
		$isDisplayParams = $this->getFilterSetting($settings, 'display_selected_params', false);

		return $isDisplayParams ? '<div class="wpfSelectedParameters wpfHidden" data-filter="' . $id . '"></div>' : '';
	}

	public function getChildrenOfIncludedCategories( $taxonomy, $categoryIds ) {
		//remove_all_filters('get_terms');
		$output = array();
		foreach ( $categoryIds as $categoryId ) {
			/*$args  = array(
				'child_of' => $categoryId,
				'fields' => 'ids'
			);
			$terms = get_terms($taxonomy, $args);*/
			$terms = get_term_children($categoryId, $taxonomy); //this function is more faster
			if ( ! empty($terms) ) {
				$output = array_merge($output, $terms);
			}
		}

		return array_merge($categoryIds, $output);
	}

	/**
	 * Get pagination base structure
	 *
	 * @return array
	 */
	public function getPaginationBase() {
		$paginationBase = array();
		$isShortcode    = false;

		global $post;
		if ( ! empty($post->post_content) ) {
			$isShortcode = has_shortcode( $post->post_content, 'products' );
		}

		/**
		 * Plugin compatibility
		 *
		 * @link https://www.elegantthemes.com/gallery/divi/
		 */
		$theme = wp_get_theme();
		if ( 'Divi' == $theme->name || 'Divi' == $theme->parent_theme ) {
			if ( ! empty($post->post_content) && strpos($post->post_content, 'wp:divi/placeholder') !== false ) {
				$paginationBase['base'] = 'product-page';
				$paginationBase['type'] = 'shortcode';
				return $paginationBase;
			}
		}

		if ( $isShortcode ) {
			$paginationBase['base'] = 'product-page';
			$paginationBase['type'] = 'shortcode';
			return $paginationBase;
		} else {
			$base = get_pagenum_link(999999999, false);
		}

		// pagination in a main url structure like /page/999999999/
		if ( strpos($base, '/999999999') !== false ) {
			$queryUrlList = explode('/', $base);

			for ( $i = 0; $i < count($queryUrlList); $i++ ) {
				if ( strpos($queryUrlList[$i], '999999999') !== false ) {
					$paginationBase['type'] = 'url';
					$paginationBase['base'] = $queryUrlList[$i - 1];
				}
			}
			// pagination in a query url structure like &paged=999999999
		} else {
			$queryUrl     = parse_url($base, PHP_URL_QUERY);
			$queryUrlList = explode('&', $queryUrl);

			foreach ( $queryUrlList as $query ) {
				if ( strpos($query, '999999999') !== false ) {
					$queryDelimiter         = explode('=', $query);
					$paginationBase['base'] = $queryDelimiter[0];
					$paginationBase['type'] = 'query';
				}
			}
		}

		return $paginationBase;
	}

	/**
	 * Set filter id
	 */
	public function setFitlerId() {
		return ' id="' . self::$blockId . '" data-order-key="' . self::$filterOrderKey . '" ';
	}

	/**
	 * Set filter common data attributes
	 *
	 * @param array $filter
	 * @param string $filterName
	 * @param string $displayType
	 * @param string $filterSlug
	 *
	 * @return void
	 */
	public function setCommonFitlerDataAttr( $filter, $filterName, $displayType, $filterSlug = '' ) {
		$filerList         = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getAllFilters();
		$filterSlug        = $filterSlug ? $filterSlug : $filerList[$filter['id']]['slug'];
		$filterContentType = $filerList[$filter['id']]['content_type'];

		return ' data-filter-type="' . $filter['id'] . '"' .
			' data-get-attribute="' . $filterName . '"' .
			( $displayType ? ' data-display-type="' . $displayType . '"' : '' ) .
			( $filterContentType ? ' data-content-type="' . $filterContentType . '"' : '' ) .
			( $filterSlug ? ' data-slug="' . $filterSlug . '"' : '' ) .
			( empty($filter['uniqId']) ? '' : ' data-uniq-id="' . $filter['uniqId'] . '"' ) ;
	}

	public function getFilterUrlData( $filterName, $defFilterName = '' ) {
		$data = ReqWpf::getVar( $filterName );
		if ( is_null($data) ) {
			preg_match( '/(filter_cat|filter_pwb|product_tag).*/', $filterName, $matches );
			if ( isset( $matches[1] ) ) {
				$data = ReqWpf::getFilterRedirect( $matches[1] );
			}
		}
		if ( empty($data) || is_null($data) ) {
			$data = DispatcherWpf::applyFilters('getFilterDefault', $data, empty($defFilterName) ? $filterName : $defFilterName);
		}
		return $data;
	}

	public function getMultiSelectHtml( $htmlOpt, $settings ) {
		$search         = array(
			'show'        => $this->getFilterSetting( $settings, 'f_dropdown_search', 0 ),
			'placeholder' => $this->getFilterSetting( $settings, 'f_dropdown_search_text', 'Search' ),
		);
		$singleSelect   = ( $this->getFilterSetting( $settings, 'f_single_select', false ) ) ? 'data-single-select' : '';
		$hideCheckboxes = ( $this->getFilterSetting( $settings, 'f_hide_checkboxes', false ) ) ? 'data-hide-checkboxes' : '';

		return '<select class="needsclick" multiple data-placeholder="' . esc_attr__( $this->getFilterSetting( $settings, 'f_dropdown_first_option_text', 'Select all' ), 'woo-product-filter' ) . '"
				data-search="' . esc_attr( json_encode( $search, JSON_UNESCAPED_UNICODE ) ) . '" ' . $singleSelect . ' ' . $hideCheckboxes . '>' . $htmlOpt . '</select>';
	}
}
