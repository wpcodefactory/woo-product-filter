<?php
/**
 * Product Filter by WBW - Woofilters_ElementorWidgetWpf Class
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Woofilters_ElementorWidgetWpf extends Widget_Base {

	public static $adPath        = '';
	public static $labelPro      = '';
	public static $scriptsLoaded = false;

	/**
	 * Constructor.
	 *
	 * @version 3.3.0
	 */
	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );

		$isWooCommercePluginActivated = FrameWpf::_()->getModule( 'woofilters' )->isWooCommercePluginActivated();
		if ( ! $isWooCommercePluginActivated ) {
			return;
		}

		if ( static::$scriptsLoaded ) {
			return;
		}

		if ( ! is_admin() && ! ( \Elementor\Plugin::$instance->preview->is_preview_mode() ) ) {
			return;
		}

		$isPro    = FrameWpf::_()->isPro();
		$modPath  = FrameWpf::_()->getModule( 'woofilters' )->getModPath();
		$tempPath = FrameWpf::_()->getModule( 'templates' )->getModPath();

		wp_register_script( 'woobewoo-pf-common', WPF_JS_PATH . 'common.js', array( 'jquery' ), WPF_VERSION, false );
		wp_register_script( 'woobewoo-pf-core', WPF_JS_PATH . 'core.js', array( 'jquery' ), WPF_VERSION, false );

		wp_register_script( 'woobewoo-pf-tooltipster', $tempPath . 'lib/tooltipster/jquery.tooltipster.min.js', false, WPF_VERSION, false );
		wp_register_style( 'woobewoo-pf-tooltipster', $tempPath . 'lib/tooltipster/tooltipster.css', false, WPF_VERSION );

		// addCommonAssets
		$options = FrameWpf::_()->getModule( 'options' )->getModel( 'options' )->getAll();
		wp_register_style( 'woobewoo-pf-frontend-filters', $modPath . 'css/frontend.woofilters.css', false, WPF_VERSION );
		wp_register_script( 'woobewoo-pf-frontend-filters', $modPath . 'js/frontend.woofilters.js', false, WPF_VERSION, false );
		if ( \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			$code = 'var isElementorPreview=1;';
			wp_add_inline_script( 'woobewoo-pf-frontend-filters', $code, 'before' );
		}

		if ( isset( $options['content_accessibility'] ) && '1' === $options['content_accessibility']['value'] ) {
			wp_register_style( 'woobewoo-pf-frontend-filters-accessibility', $modPath . 'css/frontend.woofilters.accessibility.css', false, WPF_VERSION );
		}

		wp_register_style( 'woobewoo-pf-frontend-multiselect', $modPath . 'css/frontend.multiselect.css', false, WPF_VERSION );
		wp_register_script( 'woobewoo-pf-frontend-multiselect', $modPath . 'js/frontend.multiselect.js', false, WPF_VERSION, false );
		$selectedTitle = esc_attr(
			( isset( $options['selected_title']['value'] ) && '' !== $options['selected_title']['value'] )
			? $options['selected_title']['value']
			: __( 'selected', 'woo-product-filter' )
		);
		wp_add_inline_script( 'woobewoo-pf-frontend-multiselect', "var wpfMultySelectedTraslate = '{$selectedTitle}';", 'before' );

		// loadJqueryUi
		wp_register_style( 'woobewoo-pf-jquery-ui', WPF_CSS_PATH . 'jquery-ui.min.css', false, WPF_VERSION );
		wp_register_style( 'woobewoo-pf-jquery-ui-structure', WPF_CSS_PATH . 'jquery-ui.structure.min.css', false, WPF_VERSION );
		wp_register_style( 'woobewoo-pf-jquery-ui-theme', WPF_CSS_PATH . 'jquery-ui.theme.min.css', false, WPF_VERSION );
		wp_register_style( 'woobewoo-pf-jquery-slider', WPF_CSS_PATH . 'jquery-slider.css', false, WPF_VERSION );
		wp_register_script( 'jquery-ui-slider', '', false, WPF_VERSION, false );

		// addPluginCustomStyles
		$params = ReqWpf::get( 'get' );
		if ( ! is_admin() || ( isset( $params['page'] ) && 'wpf-filters' === $params['page'] ) ) {
			wp_register_style( 'woobewoo-pf-custom-filters', $modPath . 'css/custom.woofilters.css', false, WPF_VERSION );
		}

		// addScriptsContent
		DispatcherWpf::doAction( 'woobewoo_pf_register_frontend_pro_assets' );

		if ( ! $isPro ) {
			static::$adPath   = FrameWpf::_()->getModule( 'woofilters' )->getModPath() . 'img/ad/';
			static::$labelPro = ' Pro';
		}
		static::$scriptsLoaded = true;
	}

	/**
	 * getFiltersSettings.
	 *
	 * @version 3.3.0
	 */
	protected function getFiltersSettings() {
		$filters            = FrameWpf::_()->getModule( 'woofilters' )->getModel()->getFromTbl();
		$filtersOpts        = array();
		$filtersOpts[0]     = 'Select';
		$filtersOpts['new'] = 'Create New';
		$filtersSettings    = array();
		foreach ( $filters as $filter ) {
			$filtersOpts[ $filter['id'] ]     = $filter['title'];
			$filtersSettings[ $filter['id'] ] = maybe_unserialize( $filter['setting_data'] );
		}

		return array( $filtersOpts, $filtersSettings );
	}

	/**
	 * get_script_depends.
	 *
	 * @version 3.3.0
	 */
	public function get_script_depends() {
		return array(
			'woobewoo-pf-common',
			'woobewoo-pf-core',
			'jquery-ui-slider',
			'woobewoo-pf-tooltipster',
			'woobewoo-pf-frontend-filters',
			'woobewoo-pf-frontend-multiselect',
			'woobewoo-pf-frontend-filters-pro',
			'woobewoo-pf-jquery-ui-autocomplete',
			'woobewoo-pf-ion-slider',
		);
	}

	/**
	 * get_style_depends.
	 *
	 * @version 3.3.0
	 */
	public function get_style_depends() {
		return array(
			'woobewoo-pf-frontend-filters',
			'woobewoo-pf-tooltipster',
			'woobewoo-pf-frontend-filters-accessibility',
			'woobewoo-pf-frontend-multiselect',
			'woobewoo-pf-frontend-filters-pro',
			'woobewoo-pf-jquery-ui',
			'woobewoo-pf-jquery-ui-structure',
			'woobewoo-pf-jquery-ui-theme',
			'woobewoo-pf-jquery-slider',
			'woobewoo-pf-custom-filters',
			'woobewoo-pf-custom-filters-pro',
			'woobewoo-pf-jquery-ui-autocomplete',
			'woobewoo-pf-ion-slider',
		);
	}

	public function get_name() {
		return 'woofilters';
	}

	public function get_title() {
		return __( 'Woofilters', 'woo-product-filter' );
	}

	public function get_icon() {
		return 'eicon-table-of-contents';
	}

	public function get_keywords() {
		return array( 'woofilters', 'filter', 'woocommerce' );
	}

	public function get_categories() {
		return array( 'general', 'woocommerce-elements' );
	}

	public function is_reload_preview_required() {
		return true;
	}

	protected function register_controls() {
		if ( ! is_admin() ) {
			return false;
		}
		list( $filtersOpts ) = $this->getFiltersSettings();

		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Select Woofilter', 'woo-product-filter' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'filter_id',
			array(
				'label'   => __( 'Select Filter', 'woo-product-filter' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $filtersOpts,
				'default' => 0,
			)
		);

		$this->add_control(
			'filter_name',
			array(
				'label'       => __( 'Filter Name', 'woo-product-filter' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => __( 'Enter product filter name', 'woo-product-filter' ),
				'default'     => '',
				'label_block' => true,
				'condition'   => array(
					'filter_id' => 'new',
				),
			)
		);

		$this->add_control(
			'filter_create',
			array(
				'label'       => __( 'Create Filter', 'woo-product-filter' ),
				'type'        => Controls_Manager::BUTTON,
				'separator'   => 'none',
				'text'        => __( 'Create', 'woo-product-filter' ),
				'button_type' => 'success',
				'event'       => 'createFilter',
				'condition'   => array(
					'filter_id' => 'new',
				),
			)
		);

		$this->end_controls_section();

		$this->addWooFilterContentTabControls();

		$this->addWooFilterStyleTabControls();

		$this->addWooFilterAndvancedTabControls();
	}

	/**
	 * render.
	 *
	 * @version 3.3.0
	 */
	protected function render() {
		$shortcode = $this->get_settings_for_display( 'filter_id' );
		?>
		<div class="elementor-woofilters">
			<?php
			echo $shortcode ?
				do_shortcode( '[wpf-filters id="' . absint( $shortcode ) . '"]' ) :
				'';
			?>
		</div>
		<?php
	}

	/**
	 * render_plain_content.
	 *
	 * @version 3.3.0
	 */
	public function render_plain_content() {
		$shortcode = $this->get_settings_for_display( 'filter_id' );
		echo $shortcode ? do_shortcode( '[wpf-filters id="' . absint( $shortcode ) . '"]' ) : '';
	}

	protected function content_template() {}

	public function addWooFilterContentTabControls() {
		$this->start_controls_section(
			'section_filters',
			array(
				'label' => 'Filters',
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'filter_trigger',
			array(
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => false,
			)
		);

		$this->add_control(
			'filters_raw',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => FrameWpf::_()->getModule( 'woofilters' )->getView()->getContent( 'woofiltersEditTabElementorFilters' ),
			)
		);

		$this->add_control(
			'filter_save',
			array(
				'type'        => Controls_Manager::BUTTON,
				'separator'   => 'none',
				'text'        => __( 'Save', 'woo-product-filter' ),
				'button_type' => 'success',
				'event'       => 'saveFilter',
				'label_block' => false,
				'condition'   => array(
					'filter_id!' => 'new',
				),
			)
		);

		$this->end_controls_section();
	}

	public function addWooFilterStyleTabControls() {

		$this->start_controls_section(
			'section_options',
			array(
				'label' => 'Options',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'filter_options_trigger',
			array(
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => false,
			)
		);

		$this->add_control(
			'filters_raw_options',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => FrameWpf::_()->getModule( 'woofilters' )->getView()->getContent( 'woofiltersEditTabElementorOptions' ),
			)
		);

		$this->add_control(
			'filter_save_options',
			array(
				'type'        => Controls_Manager::BUTTON,
				'separator'   => 'none',
				'text'        => __( 'Save', 'woo-product-filter' ),
				'button_type' => 'success',
				'event'       => 'saveFilter',
				'label_block' => false,
				'condition'   => array(
					'filter_id!' => 'new',
				),
			)
		);

		$this->end_controls_section();
	}

	public function addWooFilterAndvancedTabControls() {
		$this->start_controls_section(
			'section_design',
			array(
				'label' => __( 'Design', 'woo-product-filter' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$this->add_control(
			'filter_design_trigger',
			array(
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => false,
			)
		);

		$this->add_control(
			'filters_raw_design',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => FrameWpf::_()->getModule( 'woofilters' )->getView()->getContent( 'woofiltersEditTabElementorDesign' ),
			)
		);

		$this->add_control(
			'filter_save_design',
			array(
				'type'        => Controls_Manager::BUTTON,
				'separator'   => 'none',
				'text'        => __( 'Save', 'woo-product-filter' ),
				'button_type' => 'success',
				'event'       => 'saveFilter',
				'label_block' => false,
				'condition'   => array(
					'filter_id!' => 'new',
				),
			)
		);

		$this->end_controls_section();
	}
}
