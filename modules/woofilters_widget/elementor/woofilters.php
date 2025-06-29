<?php

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Woofilters_ElementorWidgetWpf extends Widget_Base {
	
	public static $adPath = '';
	public static $labelPro = '';
	public static $scriptsLoaded = false;
	
	public function __construct ( $data = array(), $args = null ) {
		parent::__construct($data, $args);
		
		$isWooCommercePluginActivated = FrameWpf::_()->getModule('woofilters')->isWooCommercePluginActivated();
		if (!$isWooCommercePluginActivated) {
			return;
		}

		if (static::$scriptsLoaded) {
			return;
		}
		
		if ( !is_admin() && !( \Elementor\Plugin::$instance->preview->is_preview_mode() ) ) {
			return;
		}

		$isPro = FrameWpf::_()->isPro();
		$modPath = FrameWpf::_()->getModule('woofilters')->getModPath();
		$tempPath = FrameWpf::_()->getModule('templates')->getModPath();

		wp_register_script('commonWpf', WPF_JS_PATH . 'common.js', array('jquery'), WPF_VERSION);
		wp_register_script('coreWpf', WPF_JS_PATH . 'core.js', array('jquery'), WPF_VERSION);

		wp_register_script('tooltipster', $tempPath . 'lib/tooltipster/jquery.tooltipster.min.js', false, WPF_VERSION);
		wp_register_style('tooltipster', $tempPath . 'lib/tooltipster/tooltipster.css', false, WPF_VERSION);

		//addCommonAssets
		$options = FrameWpf::_()->getModule( 'options' )->getModel( 'options' )->getAll();
		wp_register_style('frontend.filters', $modPath . 'css/frontend.woofilters.css', false, WPF_VERSION);
		wp_register_script('frontend.filters', $modPath . 'js/frontend.woofilters.js', false, WPF_VERSION);
		if ( \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			$code = 'var isElementorPreview=1;';
			wp_add_inline_script('frontend.filters', $code, 'before');
		}

		if ( isset( $options['content_accessibility'] ) && '1' === $options['content_accessibility']['value'] ) {
			wp_register_style('frontend.filters.accessibility', $modPath . 'css/frontend.woofilters.accessibility.css', false, WPF_VERSION);
		}

		wp_register_style('frontend.multiselect', $modPath . 'css/frontend.multiselect.css', false, WPF_VERSION);
		wp_register_script('frontend.multiselect', $modPath . 'js/frontend.multiselect.js', false, WPF_VERSION);
		$selectedTitle = esc_attr__(( isset($options['selected_title']['value']) && ''!==$options['selected_title']['value'] ) ? $options['selected_title']['value'] : 'selected', 'woo-product-filter');
		wp_add_inline_script( 'frontend.multiselect', "var wpfMultySelectedTraslate = '{$selectedTitle}';", 'before' );
		
		//loadJqueryUi
		wp_register_style('jquery-ui', WPF_CSS_PATH . 'jquery-ui.min.css', false, WPF_VERSION);
		wp_register_style('jquery-ui.structure', WPF_CSS_PATH . 'jquery-ui.structure.min.css', false, WPF_VERSION);
		wp_register_style('jquery-ui.theme', WPF_CSS_PATH . 'jquery-ui.theme.min.css', false, WPF_VERSION);
		wp_register_style('jquery-slider', WPF_CSS_PATH . 'jquery-slider.css', false, WPF_VERSION);
		wp_register_script('jquery-ui-slider', '', false, WPF_VERSION);
			
		//addPluginCustomStyles
		$params = ReqWpf::get( 'get' );
		if ( !is_admin() || ( isset($params['page']) && 'wpf-filters' === $params['page'] ) ) {
			wp_register_style('custom.filters', $modPath . 'css/custom.woofilters.css', false, WPF_VERSION);
		}

		//addScriptsContent
		if ( $isPro ) {
			$modPathPRO = FrameWpf::_()->getModule('woofilterpro')->getModPath();		
			wp_register_script('frontend.filters.pro', $modPathPRO . 'js/frontend.woofilters.pro.js', array('frontend.filters'), WPF_VERSION, true);
			wp_localize_script('frontend.filters.pro', 'wpfTraslate', array(
				'ShowMore'  => __( 'Show More', 'woo-product-filter' ),
				'ShowFewer' => __( 'Show Fewer', 'woo-product-filter' ),
			));
			wp_register_style('frontend.filters.pro', $modPathPRO . 'css/frontend.woofilters.pro.css', false, WPF_VERSION);
			wp_register_style('custom.filters.pro', $modPathPRO . 'css/custom.woofilters.pro.css', false, WPF_VERSION);
			wp_register_script('jquery-ui-autocomplete', '', false, WPF_VERSION);
			wp_register_style('jquery-ui-autocomplete', $modPathPRO . 'css/jquery-ui-autocomplete.css', false, WPF_VERSION);
			wp_register_script('ion.slider', $modPathPRO . 'js/ion.rangeSlider.min.js', false, WPF_VERSION);
			wp_register_style('ion.slider', $modPathPRO . 'css/ion.rangeSlider.css', false, WPF_VERSION);
		
		}
		
		if (!$isPro) {
			static::$adPath = FrameWpf::_()->getModule('woofilters')->getModPath() . 'img/ad/';
			static::$labelPro = ' Pro';
		}
		static::$scriptsLoaded = true;	
	}
	
	protected function getFiltersSettings() {
		$filters = FrameWpf::_()->getModule('woofilters')->getModel()->getFromTbl();
		$filtersOpts = array();
		$filtersOpts[0] = 'Select';
		$filtersOpts['new'] = 'Create New';
		$filtersSettings = array();
		foreach ($filters as $filter) {
			$filtersOpts[ $filter['id'] ] = $filter['title'];
			$filtersSettings[ $filter['id'] ] = unserialize($filter['setting_data']);
		}
		
		return array( $filtersOpts, $filtersSettings );
	}

	public function get_script_depends() {
		return array('commonWpf', 'coreWpf', 'jquery-ui-slider', 'tooltipster', 'frontend.filters', 'frontend.multiselect', 'frontend.filters.pro', 'jquery-ui-autocomplete', 'ion.slider');
	}

	public function get_style_depends() {
		return array('frontend.filters', 'tooltipster', 'frontend.filters.accessibility', 'frontend.multiselect', 'frontend.filters.pro',
			 'jquery-ui', 'jquery-ui.structure', 'jquery-ui.theme', 'jquery-slider', 'custom.filters', 'custom.filters.pro', 'jquery-ui-autocomplete', 'ion.slider');
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
		if (!is_admin()) {
			return false;
		}
		list( $filtersOpts ) = $this->getFiltersSettings();
		
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Select Woofilter', 'woo-product-filter' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			)
		);
		
		$this->add_control(
			'filter_id',
			array(
				'label' => __( 'Select Filter', 'woo-product-filter' ),
				'type' => Controls_Manager::SELECT,
				'options' => $filtersOpts,
				'default' => 0,
			)
		);
		
		$this->add_control(
			'filter_name',
			array(
				'label' => __( 'Filter Name', 'woo-product-filter' ),
				'type' => Controls_Manager::TEXT,
				'placeholder' => __( 'Enter product filter name', 'woo-product-filter' ),
				'default' => '',
				'label_block' => true,
				'condition' => array(
					'filter_id' => 'new',
				),
			)
		);
		
		$this->add_control(
			'filter_create',
			array(
				'label' => __( 'Create Filter', 'woo-product-filter' ),
				'type' => Controls_Manager::BUTTON,
				'separator' => 'none',
				'text' => __( 'Create', 'woo-product-filter' ),
				'button_type' => 'success',
				'event' => 'createFilter',
				'condition' => array(
					'filter_id' => 'new',
				),
			)
		);

		$this->end_controls_section();
		
		$this->addWooFilterContentTabControls();
		
		$this->addWooFilterStyleTabControls();
		
		$this->addWooFilterAndvancedTabControls();
	}
	
	protected function render() {
		$shortcode = $this->get_settings_for_display( 'filter_id' );
		?>
		<div class="elementor-woofilters"><?php echo $shortcode ? do_shortcode( '[wpf-filters id="' . $shortcode . '"]' ) : ''; ?></div>
		<?php
	}
	
	public function render_plain_content() {
		$shortcode = $this->get_settings_for_display( 'filter_id' );
		echo $shortcode ? do_shortcode( '[wpf-filters id="' . $shortcode . '"]' ) : '';
	}
	
	protected function content_template() {}
	
	public function addWooFilterContentTabControls() {
		$this->start_controls_section(
			'section_filters',
			array(
				'label' => 'Filters',
				'tab' => Controls_Manager::TAB_CONTENT,
			)
		);
		
		$this->add_control(
			'filter_trigger',
			array(
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'label_block' => false,
			)
		);
		
		$this->add_control(
			'filters_raw',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw' => FrameWpf::_()->getModule('woofilters')->getView()->getContent('woofiltersEditTabElementorFilters'),
			)
		);
		
		$this->add_control(
			'filter_save',
			array(
				'type' => Controls_Manager::BUTTON,
				'separator' => 'none',
				'text' => __( 'Save', 'woo-product-filter' ),
				'button_type' => 'success',
				'event' => 'saveFilter',
				'label_block' => false,
				'condition' => array(
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
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);
		
		$this->add_control(
			'filter_options_trigger',
			array(
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'label_block' => false,
			)
		);
		
		$this->add_control(
			'filters_raw_options',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw' => FrameWpf::_()->getModule('woofilters')->getView()->getContent('woofiltersEditTabElementorOptions'),
			)
		);
		
		$this->add_control(
			'filter_save_options',
			array(
				'type' => Controls_Manager::BUTTON,
				'separator' => 'none',
				'text' => __( 'Save', 'woo-product-filter' ),
				'button_type' => 'success',
				'event' => 'saveFilter',
				'label_block' => false,
				'condition' => array(
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
				'tab' => Controls_Manager::TAB_ADVANCED,
			)
		);
		
		$this->add_control(
			'filter_design_trigger',
			array(
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'label_block' => false,
			)
		);
		
		$this->add_control(
			'filters_raw_design',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw' => FrameWpf::_()->getModule('woofilters')->getView()->getContent('woofiltersEditTabElementorDesign'),
			)
		);
		
		$this->add_control(
			'filter_save_design',
			array(
				'type' => Controls_Manager::BUTTON,
				'separator' => 'none',
				'text' => __( 'Save', 'woo-product-filter' ),
				'button_type' => 'success',
				'event' => 'saveFilter',
				'label_block' => false,
				'condition' => array(
					'filter_id!' => 'new',
				),
			)
		);
		
		$this->end_controls_section();
	}
}
