<?php
/**
 * Product Filter by WBW - Oxygen OXY_Element subclass.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'OXY_Element' ) ) {
	return;
}

class WooBeWoo_PF_Oxygen_Element extends OXY_Element {

	public $css_added = false;

	public function __construct() {
		// Oxygen calls init_actions() in the parent constructor when the "+" section exists.
		$this->El = new OXY_Element_Config( $this );
	}

	public function name() {
		return esc_html__( 'Product Filter (WBW)', 'woo-product-filter' );
	}

	public function slug() {
		return 'wpf-product-filter';
	}

	public function icon() {
		return WPF_IMG_PATH . 'woobewoologo.png';
	}

	public function button_place() {
		return 'woocommerce';
	}

	public function button_priority() {
		return 10;
	}

	public function controls() {
		$choices = WooBeWoo_PF_Builders::filterChoices();
		$select  = $this->addOptionControl(
			array(
				'type'    => 'dropdown',
				'name'    => esc_html__( 'Which filter', 'woo-product-filter' ),
				'slug'    => 'filter_id',
				'value'   => array_map( 'strval', array_combine( array_keys( $choices ), array_values( $choices ) ) ),
				'default' => (string) key( $choices ),
			)
		);
		$select->rebuildElementOnChange();
		$toggle = $this->addOptionControl(
			array(
				'type'    => 'checkbox',
				'name'    => esc_html__( 'Block theme mode', 'woo-product-filter' ),
				'slug'    => 'block_theme',
				'value'   => 'true',
				'default' => 'false',
			)
		);
		$toggle->rebuildElementOnChange();
	}

	public function render( $options, $defaults, $content ) {
		echo WooBeWoo_PF_Builders::renderFilter( isset( $options['filter_id'] ) ? (int) $options['filter_id'] : 0, array( 'blockTheme' => ! empty( $options['block_theme'] ) && 'false' !== $options['block_theme'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output, escaped by the filter renderer.
	}
}
