<?php
/**
 * Product Filter by WBW - Breakdance element
 *
 * Breakdance elements are PHP classes extending \Breakdance\Elements\Element, registered with
 * \Breakdance\Elements\register_element(). Written against the public Breakdance element API
 * (uiIcon, name, category, controls, defaultProperties, render). Guarded by function and class existence.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Builder_Breakdance {

	public static function label() {
		return 'Breakdance';
	}

	public static function detect() {
		return defined( '__BREAKDANCE_VERSION' ) || function_exists( '\Breakdance\Elements\register_element' );
	}

	public static function register() {
		if ( ! function_exists( '\Breakdance\Elements\register_element' ) || ! class_exists( '\Breakdance\Elements\Element' ) ) {
			return false;
		}
		$file = __DIR__ . '/breakdance/class-woobewoo-pf-breakdance-element.php';
		if ( ! file_exists( $file ) ) {
			return false;
		}
		require_once $file;
		if ( ! class_exists( 'WooBeWoo_PF_Breakdance_Element' ) ) {
			return false;
		}
		\Breakdance\Elements\register_element( 'WooBeWoo_PF_Breakdance_Element' );
		return true;
	}
}
