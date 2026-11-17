<?php
/**
 * Product Filter by WBW - Avada (Fusion Builder) element
 *
 * fusion_builder_map() on fusion_builder_before_init with a shortcode handler.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Builder_Avada {

	public static function label() {
		return 'Avada';
	}

	public static function detect() {
		return defined( 'FUSION_BUILDER_VERSION' ) || function_exists( 'fusion_builder_map' ) || class_exists( 'FusionBuilder' );
	}

	public static function register() {
		if ( ! function_exists( 'fusion_builder_map' ) && ! class_exists( 'FusionBuilder' ) ) {
			return false;
		}
		add_action( 'fusion_builder_before_init', array( __CLASS__, 'map' ) );
		add_shortcode( 'fusion_wpf_product_filter', array( __CLASS__, 'shortcode' ) );
		return true;
	}

	public static function map() {
		if ( ! function_exists( 'fusion_builder_map' ) ) {
			return;
		}
		$choices = array( '0' => esc_html__( 'Pick a filter', 'woo-product-filter' ) );
		foreach ( WooBeWoo_PF_Builders::filterChoices() as $id => $title ) {
			$choices[ (string) $id ] = $title . ' (#' . $id . ')';
		}
		fusion_builder_map(
			array(
				'name'      => esc_html__( 'Product Filter (WBW)', 'woo-product-filter' ),
				'shortcode' => 'fusion_wpf_product_filter',
				'icon'      => 'fusiona-filter',
				'preview'   => '',
				'allow_generator' => true,
				'params'    => array(
					array(
						'type'        => 'select',
						'heading'     => esc_html__( 'Which filter', 'woo-product-filter' ),
						'description' => esc_html__( 'Filters are made under Product Filter > Filters.', 'woo-product-filter' ),
						'param_name'  => 'filter_id',
						'value'       => $choices,
						'default'     => '0',
					),
					array(
						'type'        => 'radio_button_set',
						'heading'     => esc_html__( 'Block theme mode', 'woo-product-filter' ),
						'param_name'  => 'block_theme',
						'value'       => array(
							'no'  => esc_html__( 'No', 'woo-product-filter' ),
							'yes' => esc_html__( 'Yes', 'woo-product-filter' ),
						),
						'default'     => 'no',
					),
				),
			)
		);
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'filter_id' => 0, 'block_theme' => 'no' ), (array) $atts, 'fusion_wpf_product_filter' );
		return WooBeWoo_PF_Builders::renderFilter( (int) $atts['filter_id'], array( 'blockTheme' => 'yes' === $atts['block_theme'] ) );
	}
}
