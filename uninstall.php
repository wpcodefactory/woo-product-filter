<?php
/**
 * Product Filter for WooCommerce by WBW - uninstall
 *
 * Runs when the plugin is deleted from the Plugins screen. Nothing is removed unless the owner
 * switched on "Delete all plugin data on uninstall" (option wbw_delete_on_uninstall = 1). Then every
 * plugin table, option, transient, cron hook and term meta is removed. Pro data (its own tables and
 * options) is left to Pro's uninstall.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @package WooBeWoo\WC_Product_Filter
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove everything for one site (the current blog).
 */
function woobewoo_pf_uninstall_site() {
	global $wpdb;

	if ( '1' !== (string) get_option( 'wbw_delete_on_uninstall', '0' ) ) {
		return;
	}

	// Tables: every wpf_ table of this plugin (Free 3.x and 4.0).
	$tables = array( 'modules', 'modules_type', 'filters', 'usage_stat', 'meta_keys', 'meta_data', 'meta_values', 'meta_values_bk', 'filter_versions', 'short_links', 'index_products', 'index_terms', 'index_meta', 'index_variations', 'index_prices' );
	foreach ( $tables as $t ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . 'wpf_' . $t ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
	}
	// Temporary tables the 3.x query path may have left behind.
	$temp = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'wpf_temp' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( (array) $temp as $t ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $t ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
	}

	// Options: fixed names and every wpf_ / wbw_ prefixed one (including the prefixed db_version).
	$fixed = array(
		$wpdb->prefix . 'wpf_db_version',
		$wpdb->prefix . 'wpf_db_installed',
		'wpf_plug_was_used',
		'wpf_full_installed',
		'wpf_opts_data',
		'wpf_slug_format_rewrite_active',
		'wpf_slug_format_rewrite_flush_needed',
		'wpf_slug_format_rewrite_option_bootstrapped',
		'wbw_delete_on_uninstall',
		'wbw_clean_links',
		'canonical_links',
		'woobewoo_pf_settings',
	);
	foreach ( $fixed as $o ) {
		delete_option( $o );
	}
	$like = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", 'wpf\_%', 'wpf4\_%', 'wbw\_%', 'woobewoo\_pf\_%', '\_transient\_wpf%', '\_transient\_timeout\_wpf%', 'widget\_woobewoo\_pf%', 'widget\_wpf%', 'wpf-%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( (array) $like as $name ) {
		if ( 0 === strpos( $name, '_transient_timeout_' ) ) {
			delete_transient( substr( $name, strlen( '_transient_timeout_' ) ) );
		} elseif ( 0 === strpos( $name, '_transient_' ) ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
		} else {
			delete_option( $name );
		}
	}
	// Site transients (multisite stores them in sitemeta).
	$siteTransients = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '\_site\_transient\_wpf\_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( (array) $siteTransients as $name ) {
		delete_site_transient( substr( $name, strlen( '_site_transient_' ) ) );
	}

	// Widget placements of the removed widget (WordPress keeps the ids in sidebars_widgets otherwise).
	$sidebars = get_option( 'sidebars_widgets' );
	if ( is_array( $sidebars ) ) {
		foreach ( $sidebars as $area => $widgets ) {
			if ( is_array( $widgets ) ) {
				$sidebars[ $area ] = array_values(
					array_filter(
						$widgets,
						function ( $w ) {
							return 0 !== strpos( (string) $w, 'wpfwoofilterswidget-' );
						}
					)
				);
			}
		}
		update_option( 'sidebars_widgets', $sidebars );
	}

	// Cron hooks.
	$hooks = array( 'wpf_calc_meta_indexing', 'wpf_setup_scan_batch', 'wpf_setup_first_week', 'wpf_index_batch', 'wpf_index_queue_run', 'wpf_bot_cache_warm', 'wpf_links_cleanup' );
	foreach ( $hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	$cron = _get_cron_array();
	if ( is_array( $cron ) ) {
		foreach ( $cron as $ts => $events ) {
			foreach ( (array) $events as $hook => $instances ) {
				if ( 0 === strpos( $hook, 'wpf_' ) || 0 === strpos( $hook, 'woobewoo_pf_' ) ) {
					wp_clear_scheduled_hook( $hook );
				}
			}
		}
	}

	// Term meta (brand logos and swatch colours written by the engine and the brands module).
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", 'wpf\_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	// User meta (tour state, dismissed notices).
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", 'wpf-%', 'wpf\_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	// llms.txt written by the readiness tool (only when it is ours: it carries our agent links).
	$llms = ABSPATH . 'llms.txt';
	if ( file_exists( $llms ) && is_writable( $llms ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		$content = (string) file_get_contents( $llms ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false !== strpos( $content, 'wpf/v1/' ) ) {
			wp_delete_file( $llms );
		}
	}
	flush_rewrite_rules( false );
}

if ( is_multisite() ) {
	$woobewoo_pf_blog_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $woobewoo_pf_blog_ids as $woobewoo_pf_blog_id ) {
		switch_to_blog( $woobewoo_pf_blog_id );
		woobewoo_pf_uninstall_site();
		restore_current_blog();
	}
} else {
	woobewoo_pf_uninstall_site();
}
