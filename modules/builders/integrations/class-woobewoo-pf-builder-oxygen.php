<?php
/**
 * Product Filter by WBW - Oxygen element
 *
 * Oxygen Builder (classic, 4.x): an OXY_Element subclass registered through the
 * oxygen_add_plus_sections / oxygen_add_plus_<section>_section hooks, or the
 * oxygen_vsb_register_element() helper when present. Guarded by class existence.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Builder_Oxygen {

	public static function label() {
		return 'Oxygen';
	}

	public static function detect() {
		return defined( 'CT_VERSION' ) || class_exists( 'OXY_Element' ) || function_exists( 'oxygen_vsb_register_element' );
	}

	public static function register() {
		if ( class_exists( 'OXY_Element' ) ) {
			$file = __DIR__ . '/oxygen/class-woobewoo-pf-oxygen-element.php';
			if ( file_exists( $file ) ) {
				require_once $file;
				if ( class_exists( 'WooBeWoo_PF_Oxygen_Element' ) ) {
					new WooBeWoo_PF_Oxygen_Element();
					return true;
				}
			}
		}
		if ( function_exists( 'oxygen_vsb_register_element' ) ) {
			oxygen_vsb_register_element(
				'wpf-product-filter',
				array(
					'name'     => esc_html__( 'Product Filter (WBW)', 'woo-product-filter' ),
					'callback' => array( __CLASS__, 'shortcodeRender' ),
					'options'  => array( 'filter_id' => array( 'type' => 'select', 'options' => WooBeWoo_PF_Builders::filterChoices() ) ),
				)
			);
			return true;
		}
		return false;
	}

	public static function shortcodeRender( $options ) {
		return WooBeWoo_PF_Builders::renderFilter( isset( $options['filter_id'] ) ? (int) $options['filter_id'] : 0 );
	}
}
