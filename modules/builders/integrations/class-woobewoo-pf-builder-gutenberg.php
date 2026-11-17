<?php
/**
 * Product Filter by WBW - Gutenberg block woobewoo/product-filter
 *
 * block.json in modules/builders/block, server rendered, editor script in plain JS
 * (wp.element.createElement, filter picker fed by REST wpf/v1/filters-list), no build step.
 * "Block theme mode" renders the filter in a woocommerce/product-collection compatible wrapper.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Builder_Gutenberg {

	public static function label() {
		return __( 'Block editor', 'woo-product-filter' );
	}

	public static function detect() {
		return function_exists( 'register_block_type' );
	}

	public static function register() {
		$dir = dirname( __DIR__ ) . '/block';
		if ( ! file_exists( $dir . '/block.json' ) ) {
			return false;
		}
		$path = WooBeWoo_PF_Frame::_()->getModule( 'builders' )->getModPath() . 'block/';
		wp_register_script( 'woobewoo-pf-block-editor', $path . 'editor.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-api-fetch', 'wp-server-side-render' ), WPF_VERSION, true );
		wp_set_script_translations( 'woobewoo-pf-block-editor', 'woo-product-filter' );
		wp_register_style( 'woobewoo-pf-block-editor', $path . 'editor.css', array(), WPF_VERSION );
		$registered = register_block_type(
			$dir,
			array(
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
		add_action( 'rest_api_init', array( __CLASS__, 'restFilters' ) );
		return (bool) $registered;
	}

	/**
	 * REST list of filters for the picker (editors only).
	 */
	public static function restFilters() {
		register_rest_route(
			'wpf/v1',
			'/filters-list',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => function () {
					$out = array();
					foreach ( WooBeWoo_PF_Builders::filterChoices() as $id => $title ) {
						$out[] = array( 'id' => (int) $id, 'title' => $title );
					}
					return rest_ensure_response( $out );
				},
			)
		);
	}

	public static function render( $attributes, $content = '', $block = null ) {
		$id   = isset( $attributes['filterId'] ) ? (int) $attributes['filterId'] : 0;
		$atts = array(
			'blockTheme' => ! empty( $attributes['blockTheme'] ),
			'align'      => isset( $attributes['align'] ) ? $attributes['align'] : '',
			'className'  => isset( $attributes['className'] ) ? $attributes['className'] : '',
		);
		if ( ! $id ) {
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return '<div class="wpf-builder-empty">' . esc_html__( 'Pick a filter in the block settings.', 'woo-product-filter' ) . '</div>';
			}
			return '';
		}
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes( array( 'class' => 'wpf-block' . ( $atts['blockTheme'] ? ' wpf-block--theme' : '' ) ) ) : 'class="wpf-block"';
		return '<div ' . $wrapper . '>' . WooBeWoo_PF_Builders::renderFilter( $id, $atts ) . '</div>';
	}
}
