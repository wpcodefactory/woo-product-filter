<?php
/**
 * Plugin Name: Product Filter for WooCommerce by WBW
 * Plugin URI: https://woobewoo.com/product/woocommerce-filter/
 * Description: Filter products in your store in most efficient way
 * Version: 3.3.2-dev
 * Author: woobewoo
 * Author URI: https://woobewoo.com/
 * Requires at least: 5.0
 * Text Domain: woo-product-filter
 * Domain Path: /languages
 * WC tested up to: 11.0
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base config constants and functions.
 *
 * @version 3.3.0
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

/**
 * HPOS.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Connect all required core classes.
 *
 * @version 3.3.2
 */
if ( trueRequestWpf() ) {

	importClassWpf( 'WooBeWoo_PF_Db' );
	importClassWpf( 'WooBeWoo_PF_Installer' );
	importClassWpf( 'WooBeWoo_PF_Base_Object' );
	importClassWpf( 'WooBeWoo_PF_Module' );
	importClassWpf( 'WooBeWoo_PF_Model' );
	importClassWpf( 'WooBeWoo_PF_View' );
	importClassWpf( 'WooBeWoo_PF_Controller' );
	importClassWpf( 'WooBeWoo_PF_Helper' );
	importClassWpf( 'WooBeWoo_PF_Dispatcher' );
	importClassWpf( 'WooBeWoo_PF_Field' );
	importClassWpf( 'WooBeWoo_PF_Table' );
	importClassWpf( 'WooBeWoo_PF_Frame' );

	/**
	 * Deprecated classes.
	 *
	 * @deprecated since version 1.0.1
	 */
	importClassWpf( 'WooBeWoo_PF_Lang' );
	importClassWpf( 'WooBeWoo_PF_Req' );
	importClassWpf( 'WooBeWoo_PF_Uri' );
	importClassWpf( 'WooBeWoo_PF_Html' );
	importClassWpf( 'WooBeWoo_PF_Response' );
	importClassWpf( 'WooBeWoo_PF_Field_Adapter' );
	importClassWpf( 'WooBeWoo_PF_Validator' );
	importClassWpf( 'WooBeWoo_PF_Errors' );
	importClassWpf( 'WooBeWoo_PF_Utils' );
	importClassWpf( 'WooBeWoo_PF_Mod_Installer' );
	importClassWpf( 'WooBeWoo_PF_Installer_Db_Updater' );
	importClassWpf( 'WooBeWoo_PF_Date' );

	/**
	 * Check plugin version - maybe we need to update database, and check global errors in request.
	 */
	WooBeWoo_PF_Installer::update();
	WooBeWoo_PF_Errors::init();

	/**
	 * Start application.
	 */
	WooBeWoo_PF_Frame::_()->parseRoute();
	WooBeWoo_PF_Frame::_()->init();
	WooBeWoo_PF_Frame::_()->exec();

}
