<?php
/**
 * Product Filter by WBW - Woofilters_WidgetWpf Class
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class Woofilters_WidgetWpf extends ModuleWpf {

	/**
	 * Init.
	 *
	 * @version 3.1.7
	 */
	public function init() {
		parent::init();
		add_action( 'widgets_init', array( $this, 'registerWidget' ) );
		if ( did_action( 'elementor/loaded' ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'registerElementorWidget' ) );
		}
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'woofiltersElementorEditorScripts' ) );
		// gutenberg block
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueueGutenbergEditorAssets' ) );
		add_action( 'widgets_init', array( $this, 'gutenbergregisterWidget' ) );
		// gutenberg block
	}

	/**
	 * gutenbergregisterWidget.
	 *
	 * @version 3.1.7
	 * @since   3.1.7
	 */
	public function gutenbergregisterWidget() {
		// gutenberg block
		require_once __DIR__ . '/gutenberg/block.php';
		// gutenberg block
	}

	/**
	 * registerWidget.
	 */
	public function registerWidget() {
		require_once __DIR__ . '/elementor/widget.php';
		return register_widget( 'WpfWoofiltersWidget' );
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
	 * @version 3.3.0
	 */
	public function woofiltersElementorEditorScripts() {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$modPath  = FrameWpf::_()->getModule( 'woofilters' )->getModPath();
			$modPathW = FrameWpf::_()->getModule( 'woofilters_widget' )->getModPath();

			FrameWpf::_()->getModule( 'templates' )->loadCoreJs();
			FrameWpf::_()->getModule( 'templates' )->loadAdminCoreJs();
			wp_enqueue_style( 'wp-color-picker' );

			FrameWpf::_()->getModule( 'templates' )->loadCoreCss();
			FrameWpf::_()->getModule( 'templates' )->loadChosenSelects();
			FrameWpf::_()->addScript( 'woobewoo-pf-notify', WPF_JS_PATH . 'notify.js', array(), false, true );
			FrameWpf::_()->addJSVar( 'wp-color-picker', 'wpColorPickerL10n', array() );
			FrameWpf::_()->addScript( 'woobewoo-pf-admin-filters', $modPath . 'js/admin.woofilters.js', array( 'wp-color-picker' ) );
			FrameWpf::_()->addScript( 'woobewoo-pf-admin-colorpicker-alhpa', WPF_JS_PATH . 'admin.wp.colorpicker.alpha.js', array( 'wp-color-picker' ), WPF_VERSION );

			FrameWpf::_()->addStyle( 'woobewoo-pf-admin-filters', $modPath . 'css/admin.woofilters.css' );
			FrameWpf::_()->addStyle( 'woobewoo-pf-frontend-multiselect', $modPath . 'css/frontend.multiselect.css' );
			FrameWpf::_()->addScript( 'woobewoo-pf-frontend-multiselect', $modPath . 'js/frontend.multiselect.js' );
			FrameWpf::_()->addJSVar( 'woobewoo-pf-admin-filters', 'wpfI18n', array( 'edit_category_label' => esc_html__( 'Enter custom category name', 'woo-product-filter' ) ) );

			DispatcherWpf::doAction( 'woobewoo_pf_enqueue_admin_pro_assets' );

			FrameWpf::_()->addStyle( 'woobewoo-pf-admin-woofilters-elementor', $modPathW . 'css/admin.woofilters.elementor.css', false, WPF_VERSION );
			FrameWpf::_()->addScript( 'woobewoo-pf-admin-woofilters-elementor', $modPathW . 'js/admin.woofilters.elementor.js', array( 'woobewoo-pf-admin-filters' ), WPF_VERSION, true );

			FrameWpf::_()->addJSVar( 'woobewoo-pf-admin-filters', 'isElementorEditMode', '1' );

			FrameWpf::_()->addJSVar( 'woobewoo-pf-admin-filters', 'url', admin_url( 'admin-ajax.php' ) );
			list( $filtersOpts, $filtersSettings ) = $this->getFiltersSettings();
			FrameWpf::_()->addJSVar( 'woobewoo-pf-admin-filters', 'filtersSettings', $filtersSettings );
			FrameWpf::_()->addJSVar( 'woobewoo-pf-admin-filters', 'wpfNonce', wp_create_nonce( 'woobewoo-pf-save-nonce' ) );
		}
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
	 * gutenberg block.
	 *
	 * @version 3.1.7
	 * @since   3.1.7
	 */
	public function enqueueGutenbergEditorAssets() {
		// Admin only
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
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
		if ( empty( $screen->post_type ) || $screen->base !== 'post' ) {
			return;
		}
		$modPath = FrameWpf::_()->getModule( 'woofilters_widget' )->getModPath();

		wp_enqueue_script(
			'woobewoo-pf-admin-woofilters-block',
			$modPath . 'js/block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-components',
				'wp-block-editor',
			),
			WPF_VERSION,
			true
		);

		// ✅ REUSE YOUR EXISTING LOGIC
		list($filtersOpts) = $this->getFiltersSettings();
		if ( isset( $filtersOpts['new'] ) ) {
			unset( $filtersOpts['new'] );
		}
		wp_localize_script(
			'woobewoo-pf-admin-woofilters-block',
			'wbwFiltersData',
			array(
				'filters' => $filtersOpts,
			)
		);
	}
	// gutenberg block
}
