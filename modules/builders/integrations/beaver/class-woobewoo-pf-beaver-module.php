<?php
/**
 * Product Filter by WBW - Beaver Builder FLBuilderModule subclass.
 *
 * Beaver Builder loads includes/frontend.php from the module directory for the markup.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'FLBuilderModule' ) ) {
	return;
}

class WooBeWoo_PF_Beaver_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct(
			array(
				'name'            => esc_html__( 'Product Filter (WBW)', 'woo-product-filter' ),
				'description'     => esc_html__( 'A WooBeWoo product filter', 'woo-product-filter' ),
				'category'        => esc_html__( 'WooCommerce', 'woo-product-filter' ),
				'dir'             => __DIR__ . '/',
				'url'             => WooBeWoo_PF_Frame::_()->getModule( 'builders' )->getModPath() . 'integrations/beaver/',
				'editor_export'   => true,
				'enabled'         => true,
				'partial_refresh' => true,
			)
		);
	}
}
