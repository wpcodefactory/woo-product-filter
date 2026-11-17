<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Installer_Db_Updater Class
 *
 * Runs on version change: module rows, new tables, utf8mb4 conversion of every old `wpf_` table
 * (guarded by an option, with a row count check before and after), JSON settings migration flag.
 *
 * @version 4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Installer_Db_Updater {

	const UTF8MB4_OPTION = 'wpf_utf8mb4_migrated';
	const MODULES_OPTION = 'wpf_free_modules_rev';

	/**
	 * runUpdate.
	 *
	 * @version 4.0.0
	 */
	public static function runUpdate( $current_version ) {
		if ( WooBeWoo_PF_Db::get( "SELECT 1 FROM `@__modules` WHERE code='meta'", 'one' ) != 1 ) {
			WooBeWoo_PF_Db::query( "INSERT INTO `@__modules` (id, code, active, type_id, label) VALUES (NULL, 'meta', 1, 1, 'meta');" );
		}
		if ( ! WooBeWoo_PF_Db::existsTableColumn( '@__filters', 'meta_keys' ) ) {
			WooBeWoo_PF_Db::query( 'ALTER TABLE `@__filters` ADD COLUMN `meta_keys` varchar(255) NULL DEFAULT NULL AFTER `setting_data`' );
		}
		if ( WooBeWoo_PF_Db::get( "SELECT 1 FROM `@__modules` WHERE code='overview'", 'one' ) != 1 ) {
			WooBeWoo_PF_Db::query( "INSERT INTO `@__modules` (id, code, active, type_id, label) VALUES (NULL, 'overview', 1, 1, 'overview');" );
		}
		// 4.0.0 modules (smart setup, speed and SEO, help, links, schema, abilities, cli, store API, builders, importers, brands).
		self::syncModules( true );
		// 4.0.0: version history table on upgrade from older versions.
		if ( ! WooBeWoo_PF_Db::exist( '@__filter_versions' ) ) {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			dbDelta(
				WooBeWoo_PF_Db::prepareQuery(
					'CREATE TABLE IF NOT EXISTS `@__filter_versions` (
					  `id` bigint NOT NULL AUTO_INCREMENT,
					  `filter_id` INT(11) NOT NULL,
					  `title` VARCHAR(128) NULL DEFAULT NULL,
					  `setting_data` LONGTEXT NULL,
					  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
					  `user_id` bigint NOT NULL DEFAULT 0,
					  PRIMARY KEY (`id`),
					  INDEX `filter_id` (`filter_id`)
					) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
				)
			);
		}
		self::createShortLinksTable();
		self::migrateUtf8mb4();
	}

	/**
	 * Make sure every Free module has a row in the modules table. Cheap check on every request
	 * through the revision option; the full sync runs only when WPF_MODULES_REV changed.
	 *
	 * @since 4.0.0
	 */
	public static function syncModules( $force = false ) {
		$present = array();
		foreach ( explode( ',', WPF_FREE_MODULES ) as $code ) {
			$code = trim( $code );
			if ( '' !== $code && is_dir( WPF_MODULES_DIR . $code ) ) {
				$present[] = $code;
			}
		}
		// The revision also carries the module folders found, so a module added later is picked up.
		$rev = WPF_MODULES_REV . ':' . implode( ',', $present );
		if ( ! $force && get_option( self::MODULES_OPTION ) === $rev ) {
			return;
		}
		if ( ! WooBeWoo_PF_Db::exist( '@__modules' ) ) {
			return;
		}
		foreach ( $present as $code ) {
			if ( WooBeWoo_PF_Db::get( 'SELECT 1 FROM `@__modules` WHERE code=%s', 'one', ARRAY_A, array( $code ) ) != 1 ) {
				WooBeWoo_PF_Db::query( 'INSERT IGNORE INTO `@__modules` (id, code, active, type_id, label) VALUES (NULL, %s, 1, 1, %s);', false, array( $code, $code ) ); // 4.0.1: IGNORE, two requests may sync at once right after an update
			}
		}
		update_option( self::MODULES_OPTION, $rev, true );
	}

	/**
	 * Short shareable links table (modules/links).
	 *
	 * @since 4.0.0
	 */
	public static function createShortLinksTable() {
		if ( WooBeWoo_PF_Db::exist( '@__short_links' ) ) {
			return;
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta(
			WooBeWoo_PF_Db::prepareQuery(
				'CREATE TABLE IF NOT EXISTS `@__short_links` (
				  `id` bigint NOT NULL AUTO_INCREMENT,
				  `slug` VARCHAR(80) NOT NULL,
				  `filter_id` INT(11) NOT NULL DEFAULT 0,
				  `params` LONGTEXT NULL,
				  `base` VARCHAR(255) NULL,
				  `hits` INT(11) NOT NULL DEFAULT 0,
				  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				  PRIMARY KEY (`id`),
				  UNIQUE INDEX `slug` (`slug`),
				  INDEX `filter_id` (`filter_id`)
				) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
			)
		);
	}

	/**
	 * Every old `wpf_` table was created as utf8. Convert to utf8mb4 once, with a row count
	 * check before and after each ALTER; a mismatch stops the run and leaves the option unset.
	 *
	 * @since 4.0.0
	 * @return array report rows (table, before, after, ok)
	 */
	public static function migrateUtf8mb4( $force = false ) {
		global $wpdb;
		$report = array();
		if ( ! $force && get_option( self::UTF8MB4_OPTION ) ) {
			return $report;
		}
		$charset = $wpdb->charset ? $wpdb->charset : 'utf8mb4';
		if ( 'utf8mb4' !== $charset ) {
			// The site itself does not use utf8mb4: nothing to convert.
			update_option( self::UTF8MB4_OPTION, 'skipped:' . $charset, true );
			return $report;
		}
		$collate = $wpdb->collate ? $wpdb->collate : 'utf8mb4_unicode_ci';
		$tables  = array( 'modules', 'modules_type', 'filters', 'usage_stat', 'meta_keys', 'meta_data', 'meta_values', 'meta_values_bk', 'filter_versions', 'short_links' );
		$allOk   = true;
		foreach ( $tables as $t ) {
			$name = $wpdb->prefix . WPF_DB_PREF . $t;
			if ( ! WooBeWoo_PF_Db::exist( '@__' . $t ) ) {
				continue;
			}
			$status = $wpdb->get_row( $wpdb->prepare( 'SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s', DB_NAME, $name ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $status && 0 === strpos( (string) $status['TABLE_COLLATION'], 'utf8mb4' ) ) {
				$report[] = array( 'table' => $name, 'before' => null, 'after' => null, 'ok' => true, 'note' => 'already utf8mb4' );
				continue;
			}
			$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $name ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			// meta_values keeps binary comparison on its key columns (its only text columns): one ALTER sets the table
			// default and the columns to utf8mb4_bin. CONVERT TO would give them the case-insensitive table collation,
			// which reorders custom field values ("instock" before "Italy") and fails on the unique key when two values
			// differ only by case.
			if ( 'meta_data' === $t ) {
				// 4.0.1: no text columns, so only the table default changes (instant, no copy of what can be millions of rows).
				$ok = false !== $wpdb->query( 'ALTER TABLE `' . esc_sql( $name ) . '` DEFAULT CHARACTER SET utf8mb4 COLLATE ' . esc_sql( $collate ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			} elseif ( 'meta_values' === $t ) {
				$ok = false !== $wpdb->query( 'ALTER TABLE `' . esc_sql( $name ) . '` DEFAULT CHARACTER SET utf8mb4 COLLATE ' . esc_sql( $collate ) . ', MODIFY `key2` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, MODIFY `key3` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, MODIFY `key4` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, MODIFY `value` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			} else {
				$ok = false !== $wpdb->query( 'ALTER TABLE `' . esc_sql( $name ) . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE ' . esc_sql( $collate ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			}
			$after = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $name ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$altered  = $ok;
			$ok       = $ok && $before === $after;
			$allOk    = $allOk && $ok;
			$report[] = array( 'table' => $name, 'before' => $before, 'after' => $after, 'ok' => $ok, 'note' => $ok ? '' : $wpdb->last_error );
			if ( ! $ok && $altered ) {
				break; // rows changed under us: stop, the option stays unset and the run is retried later.
			}
			// 4.0.1: an ALTER the server refuses (for example "key too long" for meta_keys on hosts without large index
			// prefixes) leaves that table as it was; the other tables are still converted.
			if ( ! $ok ) {
				continue;
			}
		}
		if ( $allOk ) {
			update_option( self::UTF8MB4_OPTION, gmdate( 'c' ), true );
		}
		update_option( 'wpf_utf8mb4_report', $report, false );
		return $report;
	}
}
