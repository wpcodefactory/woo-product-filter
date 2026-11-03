<?php
/**
 * Product Filter by WBW - Bricks element class (loaded by Bricks through register_element()).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Bricks\Element' ) ) {
	return;
}

class WooBeWoo_PF_Bricks_Element extends \Bricks\Element {

	public $category = 'woocommerce';
	public $name     = 'wpf-product-filter';
	public $icon     = 'ti-filter';

	public function get_label() {
		return esc_html__( 'Product Filter (WBW)', 'woo-product-filter' );
	}

	public function get_keywords() {
		return array( 'filter', 'woocommerce', 'facets', 'woobewoo' );
	}

	public function set_controls() {
		$this->controls['filterId'] = array(
			'tab'         => 'content',
			'label'       => esc_html__( 'Which filter', 'woo-product-filter' ),
			'type'        => 'select',
			'options'     => WooBeWoo_PF_Builders::filterChoices(),
			'placeholder' => esc_html__( 'Pick a filter', 'woo-product-filter' ),
		);
		$this->controls['blockTheme'] = array(
			'tab'   => 'content',
			'label' => esc_html__( 'Block theme mode', 'woo-product-filter' ),
			'type'  => 'checkbox',
		);
	}

	public function render() {
		$settings = $this->settings;
		$id       = isset( $settings['filterId'] ) ? (int) $settings['filterId'] : 0;
		echo '<div ' . wp_kses_data( $this->render_attributes( '_root' ) ) . '>';
		echo WooBeWoo_PF_Builders::renderFilter( $id, array( 'blockTheme' => ! empty( $settings['blockTheme'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output, escaped by the filter renderer.
		echo '</div>';
	}
}
