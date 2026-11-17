<?php
/**
 * Product Filter by WBW - Divi module
 *
 * An ET_Builder_Module subclass registered on et_builder_ready (Divi 4). Divi 5 keeps the Divi 4
 * module API through its compatibility layer, so the same class works there; when Divi 5 exposes
 * its conversion API the module declares itself as a shortcode module.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Builder_Divi {

	public static function label() {
		return 'Divi';
	}

	public static function detect() {
		return defined( 'ET_BUILDER_VERSION' ) || function_exists( 'et_setup_theme' ) || defined( 'ET_BUILDER_PLUGIN_VERSION' );
	}

	public static function register() {
		add_action( 'et_builder_ready', array( __CLASS__, 'load' ) );
		// Divi 5 (D5): declare the module as a shortcode based module so the conversion layer keeps it.
		add_filter( 'divi_module_library_modules_shortcode_module_names', array( __CLASS__, 'd5ShortcodeModules' ) );
		return true;
	}

	public static function d5ShortcodeModules( $names ) {
		$names   = (array) $names;
		$names[] = 'wpf_product_filter';
		return array_values( array_unique( $names ) );
	}

	public static function load() {
		if ( ! class_exists( 'ET_Builder_Module' ) ) {
			return;
		}
		$file = __DIR__ . '/divi/class-woobewoo-pf-divi-module.php';
		if ( file_exists( $file ) ) {
			require_once $file;
			if ( class_exists( 'WooBeWoo_PF_Divi_Module' ) ) {
				new WooBeWoo_PF_Divi_Module();
			}
		}
	}
}
