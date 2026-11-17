<?php
/**
 * Product Filter by WBW - Beaver Builder module markup.
 *
 * @var object $settings Module settings (filter_id, block_theme).
 *
 * @version 4.0.0
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

echo WooBeWoo_PF_Builders::renderFilter( isset( $settings->filter_id ) ? (int) $settings->filter_id : 0, array( 'blockTheme' => isset( $settings->block_theme ) && 'yes' === $settings->block_theme ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output, escaped by the filter renderer.
