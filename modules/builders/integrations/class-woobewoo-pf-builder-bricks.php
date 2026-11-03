<?php
/**
 * Product Filter by WBW - Bricks element
 *
 * Registers a \Bricks\Element subclass on init through \Bricks\Elements::register_element() when
 * Bricks is the active theme. Written against the public Bricks element API (name, icon, category,
 * set_controls(), render()).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Builder_Bricks {

	public static function label() {
		return 'Bricks';
	}

	public static function detect() {
		return defined( 'BRICKS_VERSION' ) || class_exists( '\Bricks\Elements' );
	}

	public static function register() {
		if ( ! class_exists( '\Bricks\Elements' ) || ! class_exists( '\Bricks\Element' ) || ! method_exists( '\Bricks\Elements', 'register_element' ) ) {
			return false;
		}
		$file = __DIR__ . '/bricks/class-woobewoo-pf-bricks-element.php';
		if ( ! file_exists( $file ) ) {
			return false;
		}
		// Bricks reads the file itself and expects the class name as second argument.
		\Bricks\Elements::register_element( $file, 'wpf-product-filter', 'WooBeWoo_PF_Bricks_Element' );
		return true;
	}
}
