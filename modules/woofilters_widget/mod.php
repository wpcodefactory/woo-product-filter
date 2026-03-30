<?php
/**
 * Product Filter by WBW - Woofilters_WidgetWpf Class
 *
 * @version 3.1.4
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

class Woofilters_WidgetWpf extends ModuleWpf {

	/**
	 * Init.
	 *
	 * @version 3.1.4
	 */
	public function init() {
		parent::init();
		add_action('widgets_init', array($this, 'registerWidget'));
		if (did_action('elementor/loaded')) {
			add_action('elementor/widgets/register', array($this, 'registerElementorWidget'));
		}
		add_action( 'elementor/editor/before_enqueue_scripts', array($this, 'woofiltersElementorEditorScripts') );
		//gutenberg block
		add_action('enqueue_block_editor_assets',array($this, 'enqueueGutenbergEditorAssets'));
		add_action('widgets_init', array($this, 'gutenbergregisterWidget'));
		//gutenberg block
	}

	/**
	 * gutenbergregisterWidget.
	 *
	 * @version 3.1.4
	 * @since   3.1.4
	 */
	public function gutenbergregisterWidget() {
		//gutenberg block
		require_once __DIR__ . '/gutenberg/block.php';
		//gutenberg block
	}

	/**
	 * registerWidget.
	 */
	public function registerWidget() {
		require_once __DIR__ . '/elementor/widget.php';
		return register_widget('WpfWoofiltersWidget');
	}

	/**
	 * includeElementorWidgetsFiles.
	 */
	private function includeElementorWidgetsFiles() {
		require_once __DIR__ . '/elementor/woofilters.php';
	}

	/**
	 * registerElementorWidget.
	 */
	public function registerElementorWidget() {
		$this->includeElementorWidgetsFiles();
		\Elementor\Plugin::instance()->widgets_manager->register( new Woofilters_ElementorWidgetWpf() );
	}

	/**
	 * woofiltersElementorEditorScripts.
	 *
	 * @version 3.1.4
	 */
	public function woofiltersElementorEditorScripts() {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$isPro = FrameWpf::_()->isPro();
			$modPath = FrameWpf::_()->getModule('woofilters')->getModPath();
			$modPathW = FrameWpf::_()->getModule('woofilters_widget')->getModPath();

			FrameWpf::_()->getModule('templates')->loadCoreJs();
			FrameWpf::_()->getModule('templates')->loadAdminCoreJs();
			wp_enqueue_style( 'wp-color-picker' );

			FrameWpf::_()->getModule('templates')->loadCoreCss();
			FrameWpf::_()->getModule('templates')->loadChosenSelects();
			FrameWpf::_()->addScript('notify-js', WPF_JS_PATH . 'notify.js', array(), false, true);
			FrameWpf::_()->addScript('chosen.order.jquery.min.js', $modPath . 'js/chosen.order.jquery.min.js');
			FrameWpf::_()->addJSVar('wp-color-picker', 'wpColorPickerL10n', array());
			FrameWpf::_()->addScript('admin.filters', $modPath . 'js/admin.woofilters.js', array('wp-color-picker'));
			FrameWpf::_()->addScript('admin.wp.colorpicker.alhpa.js', WPF_JS_PATH . 'admin.wp.colorpicker.alpha.js', array('wp-color-picker'), WPF_VERSION);

			FrameWpf::_()->addStyle('admin.filters', $modPath . 'css/admin.woofilters.css');
			FrameWpf::_()->addStyle('frontend.multiselect', $modPath . 'css/frontend.multiselect.css');
			FrameWpf::_()->addScript('frontend.multiselect', $modPath . 'js/frontend.multiselect.js');
			FrameWpf::_()->addJSVar( 'admin.filters', 'wpfI18n', array('edit_category_label' => esc_html__('Enter custom category name', 'woo-product-filter')));
			if ( $isPro ) {
				$modPathPRO = FrameWpf::_()->getModule('woofilterpro')->getModPath();
				$modDirPRO = FrameWpf::_()->getModule('woofilterpro')->getModDir();
				FrameWpf::_()->addScript('admin.filters.pro', $modPathPRO . 'js/admin.woofilters.pro.js', array('jquery'));
				FrameWpf::_()->addStyle('admin.filters.pro', $modPathPRO . 'css/admin.woofilters.pro.css');
				$jsData = file_exists($modDirPRO . 'files/fontAwesomeList.txt') ? file($modDirPRO . 'files/fontAwesomeList.txt') : array();
				if (!empty($jsData)) {
					$jsData = array_map(function( $item ) {
						return 'fa-' . trim($item);
					}, $jsData);
				}
				FrameWpf::_()->addJSVar('admin.filters.pro', 'FONT_AWESOME_DATA', $jsData);
			}

			FrameWpf::_()->addStyle('admin.woofilters.elementor', $modPathW . 'css/admin.woofilters.elementor.css', false, WPF_VERSION);
			FrameWpf::_()->addScript('admin.woofilters.elementor', $modPathW . 'js/admin.woofilters.elementor.js', array('admin.filters'), WPF_VERSION, true);

			FrameWpf::_()->addJSVar('admin.filters', 'isElementorEditMode', '1');

			FrameWpf::_()->addJSVar('admin.filters', 'url', admin_url('admin-ajax.php'));
			list( $filtersOpts, $filtersSettings ) = $this->getFiltersSettings();
			FrameWpf::_()->addJSVar('admin.filters', 'filtersSettings', $filtersSettings);
			FrameWpf::_()->addJSVar('admin.filters', 'wpfNonce', wp_create_nonce('wpf-save-nonce'));
		}

	}

	/**
	 * getFiltersSettings.
	 */
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

	/**
	 * gutenberg block.
	 *
	 * @version 3.1.4
	 * @since   3.1.4
	 */
	public function enqueueGutenbergEditorAssets() {
		// Admin only
		if (! is_admin() || ! function_exists('get_current_screen')) {
			return;
		}
		$screen = get_current_screen();
		/**
		 * LOAD ONLY ON:
		 * Page/Post Add & Edit
		 *
		 * BLOCK ON:
		 * - widgets
		 * - customize
		 * - site editor
		 */
		if (empty($screen->post_type) ||$screen->base !== 'post') {
			return;
		}
		$modPath = FrameWpf::_()->getModule('woofilters_widget')->getModPath();

		wp_enqueue_script(
			'wbw-woofilters-block', $modPath . 'js/block.js',
			array('wp-blocks','wp-element','wp-components', 'wp-block-editor'
			),WPF_VERSION,true);

		// ✅ REUSE YOUR EXISTING LOGIC
		list($filtersOpts) = $this->getFiltersSettings();
		if (isset($filtersOpts['new'])) {
			unset($filtersOpts['new']);
		}
		wp_localize_script(
			'wbw-woofilters-block',
			'wbwFiltersData',
			array(
				'filters' => $filtersOpts,
			)
		);
	}
	//gutenberg block
}
