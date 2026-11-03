<?php
/**
 * Product Filter by WBW - Beaver Builder module
 *
 * An FLBuilderModule subclass registered with FLBuilder::register_module() on init.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Builder_Beaver {

	public static function label() {
		return 'Beaver Builder';
	}

	public static function detect() {
		return defined( 'FL_BUILDER_VERSION' ) || class_exists( 'FLBuilder' );
	}

	public static function register() {
		if ( ! class_exists( 'FLBuilderModule' ) || ! class_exists( 'FLBuilder' ) ) {
			return false;
		}
		$file = __DIR__ . '/beaver/class-woobewoo-pf-beaver-module.php';
		if ( ! file_exists( $file ) ) {
			return false;
		}
		require_once $file;
		if ( ! class_exists( 'WooBeWoo_PF_Beaver_Module' ) ) {
			return false;
		}
		$choices = array( '0' => esc_html__( 'Pick a filter', 'woo-product-filter' ) );
		foreach ( WooBeWoo_PF_Builders::filterChoices() as $id => $title ) {
			$choices[ (string) $id ] = $title . ' (#' . $id . ')';
		}
		FLBuilder::register_module(
			'WooBeWoo_PF_Beaver_Module',
			array(
				'general' => array(
					'title'    => esc_html__( 'Filter', 'woo-product-filter' ),
					'sections' => array(
						'general' => array(
							'title'  => '',
							'fields' => array(
								'filter_id'   => array(
									'type'    => 'select',
									'label'   => esc_html__( 'Which filter', 'woo-product-filter' ),
									'default' => '0',
									'options' => $choices,
								),
								'block_theme' => array(
									'type'    => 'select',
									'label'   => esc_html__( 'Block theme mode', 'woo-product-filter' ),
									'default' => 'no',
									'options' => array(
										'no'  => esc_html__( 'No', 'woo-product-filter' ),
										'yes' => esc_html__( 'Yes', 'woo-product-filter' ),
									),
								),
							),
						),
					),
				),
			)
		);
		return true;
	}
}
