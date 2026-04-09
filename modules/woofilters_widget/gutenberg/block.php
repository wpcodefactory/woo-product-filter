<?php
/**
 * Product Filter by WBW - Block
 *
 * @version 3.1.7
 * @since   3.1.7
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

add_action('init', function () {
	register_block_type('wbw/woofilters', array(
		'attributes' => array(
			'filter_id' => array(
				'type'    => 'string',
				'default' => '',
			),
		),

		// Dynamic render (same as widget)
		'render_callback' => function ($attributes) {

			if (empty($attributes['filter_id'])) {
				return '';
			}

			return do_shortcode(
				'[wpf-filters id="' . esc_attr($attributes['filter_id']) . '"]'
			);
		},
	));
});
