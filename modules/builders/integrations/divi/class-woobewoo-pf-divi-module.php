<?php
/**
 * Product Filter by WBW - Divi ET_Builder_Module subclass.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ET_Builder_Module' ) ) {
	return;
}

class WooBeWoo_PF_Divi_Module extends ET_Builder_Module {

	public $slug       = 'wpf_product_filter';
	public $vb_support = 'on';

	protected $module_credits = array(
		'module_uri' => 'https://woobewoo.com/plugins/woocommerce-filter/',
		'author'     => 'woobewoo',
		'author_uri' => 'https://woobewoo.com/',
	);

	public function init() {
		$this->name             = esc_html__( 'Product Filter (WBW)', 'woo-product-filter' );
		$this->icon             = 'E';
		$this->main_css_element = '%%order_class%%';
		$this->settings_modal_toggles = array(
			'general' => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Filter', 'woo-product-filter' ),
				),
			),
		);
	}

	public function get_fields() {
		$choices = array( '0' => esc_html__( 'Pick a filter', 'woo-product-filter' ) );
		foreach ( WooBeWoo_PF_Builders::filterChoices() as $id => $title ) {
			$choices[ (string) $id ] = $title;
		}
		return array(
			'filter_id'   => array(
				'label'           => esc_html__( 'Which filter', 'woo-product-filter' ),
				'type'            => 'select',
				'options'         => $choices,
				'default'         => '0',
				'toggle_slug'     => 'main_content',
				'description'     => esc_html__( 'Filters are made under Product Filter > Filters.', 'woo-product-filter' ),
				'computed_affects' => array( '__filter_html' ),
			),
			'block_theme' => array(
				'label'       => esc_html__( 'Block theme mode', 'woo-product-filter' ),
				'type'        => 'yes_no_button',
				'options'     => array(
					'off' => esc_html__( 'No', 'woo-product-filter' ),
					'on'  => esc_html__( 'Yes', 'woo-product-filter' ),
				),
				'default'     => 'off',
				'toggle_slug' => 'main_content',
			),
			'__filter_html' => array(
				'type'                => 'computed',
				'computed_callback'   => array( 'WooBeWoo_PF_Divi_Module', 'computedHtml' ),
				'computed_depends_on' => array( 'filter_id', 'block_theme' ),
			),
		);
	}

	/**
	 * Visual builder preview.
	 */
	public static function computedHtml( $args = array() ) {
		return WooBeWoo_PF_Builders::renderFilter( isset( $args['filter_id'] ) ? (int) $args['filter_id'] : 0, array( 'blockTheme' => isset( $args['block_theme'] ) && 'on' === $args['block_theme'] ) );
	}

	public function render( $attrs, $content, $render_slug ) {
		return WooBeWoo_PF_Builders::renderFilter( isset( $this->props['filter_id'] ) ? (int) $this->props['filter_id'] : 0, array( 'blockTheme' => isset( $this->props['block_theme'] ) && 'on' === $this->props['block_theme'] ) );
	}
}
