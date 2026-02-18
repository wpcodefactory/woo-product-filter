<?php
/**
 * Plugin Name: Product Filter for WooCommerce by WBW
 * Plugin URI: https://woobewoo.com/product/woocommerce-filter/
 * Description: Filter products in your store in most efficient way
 * Version: 3.1.1
 * Author: woobewoo
 * Author URI: https://woobewoo.com/
 * Requires at least: 3.4.0
 * Text Domain: woo-product-filter
 * Domain Path: /languages
 * WC requires at least: 3.4.1
 * WC tested up to: 10.5
 * Requires Plugins: woocommerce
 **/

defined( 'ABSPATH' ) || exit;

/**
 * Base config constants and functions.
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'functions.php';

/**
 * HPOS.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Connect all required core classes.
 */
if ( trueRequestWpf() ) {

	importClassWpf( 'DbWpf' );
	importClassWpf( 'InstallerWpf' );
	importClassWpf( 'BaseObjectWpf' );
	importClassWpf( 'ModuleWpf' );
	importClassWpf( 'ModelWpf' );
	importClassWpf( 'ViewWpf' );
	importClassWpf( 'ControllerWpf' );
	importClassWpf( 'HelperWpf' );
	importClassWpf( 'DispatcherWpf' );
	importClassWpf( 'FieldWpf' );
	importClassWpf( 'TableWpf' );
	importClassWpf( 'FrameWpf' );

	/**
	 * Deprecated classes.
	 *
	 * @deprecated since version 1.0.1
	 */
	importClassWpf( 'LangWpf' );
	importClassWpf( 'ReqWpf' );
	importClassWpf( 'UriWpf' );
	importClassWpf( 'HtmlWpf' );
	importClassWpf( 'ResponseWpf' );
	importClassWpf( 'FieldAdapterWpf' );
	importClassWpf( 'ValidatorWpf' );
	importClassWpf( 'ErrorsWpf' );
	importClassWpf( 'UtilsWpf' );
	importClassWpf( 'ModInstallerWpf' );
	importClassWpf( 'InstallerDbUpdaterWpf' );
	importClassWpf( 'DateWpf' );

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
