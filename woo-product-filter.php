<?php
/**
 * Plugin Name: Product Filter for WooCommerce by WBW
 * Plugin URI: https://woobewoo.com/product/woocommerce-filter/
 * Description: Filter products in your store in most efficient way
 * Version: 3.3.0-dev
 * Author: woobewoo
 * Author URI: https://woobewoo.com/
 * Requires at least: 5.0
 * Text Domain: woo-product-filter
 * Domain Path: /languages
 * WC requires at least: 3.4
 * WC tested up to: 10.9
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base config constants and functions.
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
 * @version 3.3.0
 */
if ( woobewoo_pf_request() ) {

	woobewoo_pf_import_class( 'DbWpf' );
	woobewoo_pf_import_class( 'InstallerWpf' );
	woobewoo_pf_import_class( 'BaseObjectWpf' );
	woobewoo_pf_import_class( 'ModuleWpf' );
	woobewoo_pf_import_class( 'ModelWpf' );
	woobewoo_pf_import_class( 'ViewWpf' );
	woobewoo_pf_import_class( 'ControllerWpf' );
	woobewoo_pf_import_class( 'HelperWpf' );
	woobewoo_pf_import_class( 'DispatcherWpf' );
	woobewoo_pf_import_class( 'FieldWpf' );
	woobewoo_pf_import_class( 'TableWpf' );
	woobewoo_pf_import_class( 'FrameWpf' );

	/**
	 * Deprecated classes.
	 *
	 * @version 3.3.0
	 *
	 * @deprecated since version 1.0.1
	 */
	woobewoo_pf_import_class( 'LangWpf' );
	woobewoo_pf_import_class( 'ReqWpf' );
	woobewoo_pf_import_class( 'UriWpf' );
	woobewoo_pf_import_class( 'HtmlWpf' );
	woobewoo_pf_import_class( 'ResponseWpf' );
	woobewoo_pf_import_class( 'FieldAdapterWpf' );
	woobewoo_pf_import_class( 'ValidatorWpf' );
	woobewoo_pf_import_class( 'ErrorsWpf' );
	woobewoo_pf_import_class( 'UtilsWpf' );
	woobewoo_pf_import_class( 'ModInstallerWpf' );
	woobewoo_pf_import_class( 'InstallerDbUpdaterWpf' );
	woobewoo_pf_import_class( 'DateWpf' );

	/**
	 * Check plugin version - maybe we need to update database, and check global errors in request.
	 */
	InstallerWpf::update();
	ErrorsWpf::init();

	/**
	 * Start application.
	 */
	FrameWpf::_()->parseRoute();
	FrameWpf::_()->init();
	FrameWpf::_()->exec();

}
