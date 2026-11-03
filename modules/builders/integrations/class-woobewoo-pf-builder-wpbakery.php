<?php
/**
 * Product Filter by WBW - WPBakery Page Builder element
 *
 * vc_map() on vc_before_init; the element renders the [wpf-filters] shortcode through a thin
 * wrapper shortcode so the WPBakery params map to our attributes.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Builder_Wpbakery {

	public static function label() {
		return 'WPBakery';
	}

	public static function detect() {
		return defined( 'WPB_VC_VERSION' ) || function_exists( 'vc_map' );
	}

	public static function register() {
		if ( ! function_exists( 'vc_map' ) ) {
			return false;
		}
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
		add_shortcode( 'wpf_product_filter', array( __CLASS__, 'shortcode' ) );
		return true;
	}

	public static function map() {
		$choices = array( esc_html__( 'Pick a filter', 'woo-product-filter' ) => '0' );
		foreach ( WooBeWoo_PF_Builders::filterChoices() as $id => $title ) {
			$choices[ $title . ' (#' . $id . ')' ] = (string) $id;
		}
		vc_map(
			array(
				'name'        => esc_html__( 'Product Filter (WBW)', 'woo-product-filter' ),
				'base'        => 'wpf_product_filter',
				'category'    => esc_html__( 'WooCommerce', 'woo-product-filter' ),
				'description' => esc_html__( 'A WooBeWoo product filter', 'woo-product-filter' ),
				'icon'        => WPF_IMG_PATH . 'woobewoologo.png',
				'params'      => array(
					array(
						'type'        => 'dropdown',
						'heading'     => esc_html__( 'Which filter', 'woo-product-filter' ),
						'param_name'  => 'filter_id',
						'value'       => $choices,
						'admin_label' => true,
					),
					array(
						'type'       => 'checkbox',
						'heading'    => esc_html__( 'Block theme mode', 'woo-product-filter' ),
						'param_name' => 'block_theme',
						'value'      => array( esc_html__( 'Yes', 'woo-product-filter' ) => 'yes' ),
					),
				),
			)
		);
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'filter_id' => 0, 'block_theme' => '' ), (array) $atts, 'wpf_product_filter' );
		return WooBeWoo_PF_Builders::renderFilter( (int) $atts['filter_id'], array( 'blockTheme' => 'yes' === $atts['block_theme'] ) );
	}
}
