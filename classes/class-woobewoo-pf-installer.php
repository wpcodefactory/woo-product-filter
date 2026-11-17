<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Installer Class
 *
 * @version 4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Installer {

	/**
	 * update_to_version_method.
	 */
	public static $update_to_version_method = '';

	/**
	 * _firstTimeActivated.
	 */
	private static $_firstTimeActivated = false;

	/**
	 * init.
	 *
	 * @version 3.4.0
	 */
	public static function init( $isUpdate = false ) {
		global $wpdb;
		$wpPrefix = $wpdb->prefix; /* add to 0.0.3 Version */
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$current_version = get_option( $wpPrefix . WPF_DB_PREF . 'db_version', 0 );
		if ( ! $current_version ) {
			self::$_firstTimeActivated = true;
		}

		/**
		 * Table modules.
		 */
		if ( ! WooBeWoo_PF_Db::exist( '@__modules' ) ) {
			dbDelta(
				WooBeWoo_PF_Db::prepareQuery(
					"CREATE TABLE IF NOT EXISTS `@__modules` (
					  `id` smallint(3) NOT NULL AUTO_INCREMENT,
					  `code` varchar(32) NOT NULL,
					  `active` tinyint(1) NOT NULL DEFAULT '0',
					  `type_id` tinyint(1) NOT NULL DEFAULT '0',
					  `label` varchar(64) DEFAULT NULL,
					  `ex_plug_dir` varchar(255) DEFAULT NULL,
					  PRIMARY KEY (`id`),
					  UNIQUE INDEX `code` (`code`)
					) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
				)
			);
			WooBeWoo_PF_Db::query(
				"INSERT INTO `@__modules` (id, code, active, type_id, label) VALUES
				(NULL, 'adminmenu',1,1,'Admin Menu'),
				(NULL, 'options',1,1,'Options'),
				(NULL, 'user',1,1,'Users'),
				(NULL, 'templates',1,1,'templates'),
				(NULL, 'admin_nav',1,1,'admin_nav'),
				(NULL, 'woofilters',1,1,'woofilters'),
				(NULL, 'woofilters_widget',1,1,'woofilters_widget'),
				(NULL, 'meta',1,1,'meta'),
				(NULL, 'overview',1,1,'overview'),
				(NULL, 'setup',1,1,'setup'),
				(NULL, 'speed',1,1,'speed'),
				(NULL, 'help',1,1,'help'),
				(NULL, 'links',1,1,'links'),
				(NULL, 'schema',1,1,'schema'),
				(NULL, 'abilities',1,1,'abilities'),
				(NULL, 'cli',1,1,'cli'),
				(NULL, 'storeapi',1,1,'storeapi'),
				(NULL, 'builders',1,1,'builders'),
				(NULL, 'importers',1,1,'importers'),
				(NULL, 'brands',1,1,'brands');"
			);
		}
		/**
		 *  Table modules_type.
		 */
		if ( ! WooBeWoo_PF_Db::exist( '@__modules_type' ) ) {
			dbDelta(
				WooBeWoo_PF_Db::prepareQuery(
					'CREATE TABLE IF NOT EXISTS `@__modules_type` (
					  `id` smallint(3) NOT NULL AUTO_INCREMENT,
					  `label` varchar(32) NOT NULL,
					  PRIMARY KEY (`id`)
					) AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
				)
			);
			WooBeWoo_PF_Db::query(
				"INSERT INTO `@__modules_type` VALUES
				(1,'system'),
				(6,'addons');"
			);
		}
		/**
		 * Table filters.
		 */
		if ( ! WooBeWoo_PF_Db::exist( '@__filters' ) ) {
			dbDelta(
				WooBeWoo_PF_Db::prepareQuery(
					'CREATE TABLE IF NOT EXISTS `@__filters` (
						`id` INT(11) NOT NULL AUTO_INCREMENT,
						`title` VARCHAR(128) NULL DEFAULT NULL,
						`setting_data` MEDIUMTEXT NOT NULL,
						PRIMARY KEY (`id`)
					) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
				)
			);
		}

		if ( version_compare( $current_version, '1.3.6' ) != 1 ) {
			WooBeWoo_PF_Db::query( 'ALTER TABLE `@__filters` MODIFY setting_data MEDIUMTEXT;' );
		}
		/**
		 * Plugin usage statistwpf.
		 */
		if ( ! WooBeWoo_PF_Db::exist( '@__usage_stat' ) ) {
			dbDelta(
				WooBeWoo_PF_Db::prepareQuery(
					"CREATE TABLE `@__usage_stat` (
					  `id` int(11) NOT NULL AUTO_INCREMENT,
					  `code` varchar(64) NOT NULL,
					  `visits` int(11) NOT NULL DEFAULT '0',
					  `spent_time` int(11) NOT NULL DEFAULT '0',
					  `modify_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					  UNIQUE INDEX `code` (`code`),
					  PRIMARY KEY (`id`)
					) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
				)
			);
			WooBeWoo_PF_Db::query( "INSERT INTO `@__usage_stat` (code, visits) VALUES ('installed', 1)" );
		}
		/**
		 *  Table meta_keys.
		 */
		if ( ! WooBeWoo_PF_Db::exist( '@__meta_keys' ) ) {
			dbDelta(
				WooBeWoo_PF_Db::prepareQuery(
					'CREATE TABLE IF NOT EXISTS `@__meta_keys` (
					  `id` INT(11) NOT NULL AUTO_INCREMENT,
					  `meta_mode` smallint(3) NOT NULL,
					  `meta_key` varchar(255) NOT NULL,
					  `taxonomy` varchar(255) NOT NULL,
					  `meta_like` smallint(3) NOT NULL,
					  `parent` INT(11) NOT NULL,
					  `meta_type` smallint(3) NOT NULL,
					  `status` smallint(3) NOT NULL,
					  `added` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
					  `updated` TIMESTAMP,
					  `locked` TIMESTAMP,
					  `calculated` TIMESTAMP,
					  PRIMARY KEY (`id`),
					  UNIQUE INDEX `meta_key` (`meta_key`)
					) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
				)
			);
			WooBeWoo_PF_Db::query(
				"INSERT INTO `@__meta_keys` VALUES
				(NULL,0,'_wpf_product_type','',0,0,0,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_product_attributes','',0,0,8,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'attribute_%','',1,0,0,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_wc_average_rating','',0,0,3,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_stock_status','',0,0,0,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_price','',0,0,1,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_sale_price','',0,0,1,0,CURRENT_TIMESTAMP,NULL,NULL,NULL);"
			);
		}
		/**
		 *  Table meta_data.
		 */
		if ( ! WooBeWoo_PF_Db::exist( '@__meta_data' ) ) {
			dbDelta(
				WooBeWoo_PF_Db::prepareQuery(
					'CREATE TABLE IF NOT EXISTS `@__meta_data` (
					  `id` bigint NOT NULL AUTO_INCREMENT,
					  `product_id` bigint NOT NULL,
					  `is_var` smallint(3) NOT NULL DEFAULT 0,
					  `key_id` INT(11) NOT NULL,
					  `val_int` bigint,
					  `val_dec` decimal(19,4),
					  `val_id` bigint,
					  `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
					  PRIMARY KEY (`id`)
					) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
				)
			);
		}
		/**
		 *  Table meta_values.
		 */
		if ( ! WooBeWoo_PF_Db::exist( '@__meta_values' ) ) {
			dbDelta(
				WooBeWoo_PF_Db::prepareQuery(
					'CREATE TABLE IF NOT EXISTS `@__meta_values` (
					  `id` bigint NOT NULL AUTO_INCREMENT,
					  `key_id` INT(11) NOT NULL,
					  `key2` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
					  `key3` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
					  `key4` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
					  `value` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
					  `product_cnt` INT(11) NOT NULL DEFAULT 0,
					  `variation_cnt` INT(11) NOT NULL DEFAULT 0,
					  PRIMARY KEY (`id`)
					) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
				)
			);
		}
		/**
		 *  Table meta_values_bk.
		 */
		if ( ! WooBeWoo_PF_Db::exist( '@__meta_values_bk' ) ) {
			dbDelta(
				WooBeWoo_PF_Db::prepareQuery(
					'CREATE TABLE IF NOT EXISTS `@__meta_values_bk` (
					  `id` bigint NOT NULL,
					  `key_id` INT(11) NOT NULL,
					  `key2` varchar(32) NOT NULL,
					  `key3` varchar(32) NOT NULL,
					  `key4` varchar(32) NOT NULL,
					  `value` varchar(150),
					  PRIMARY KEY (`id`),
					  INDEX `key_id` (`key_id`)
					) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
				)
			);
		}

		/**
		 * Table filter_versions (version history and undo, since 4.0.0).
		 */
		if ( ! WooBeWoo_PF_Db::exist( '@__filter_versions' ) ) {
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

		WooBeWoo_PF_Installer_Db_Updater::runUpdate( $current_version );
		if ( $current_version && ! self::$_firstTimeActivated ) {
			self::setUsed();
			// For users that just updated our plugin - don't need to show step-by-step tutorial
			update_user_meta( get_current_user_id(), WPF_CODE . '-tour-hst', array( 'closed' => 1 ) );
		}
		update_option( $wpPrefix . WPF_DB_PREF . 'db_version', WPF_VERSION );
		update_option( 'wpf_links_flush_needed', '1', false ); // 4.0.0: modules/links re-registers its pretty URL rules and flushes once.
		add_option( $wpPrefix . WPF_DB_PREF . 'db_installed', 1 );
		add_option( 'wbw_delete_on_uninstall', '0' ); // 4.0.0: uninstall.php removes everything only when this is '1'.
		// 4.0.0: send the owner to the one click setup right after activation when no filter exists. Never after an
		// update (an automatic update from 3.x must not take over the next dashboard visit, on any site of a network).
		if ( self::$_firstTimeActivated || ( ! $isUpdate && ! WooBeWoo_PF_Db::get( 'SELECT COUNT(*) FROM `@__filters`', 'one' ) ) ) {
			set_transient( 'wpf_setup_redirect', 1, 5 * MINUTE_IN_SECONDS );
		}
		if ( ! wp_next_scheduled( 'wpf_calc_meta_indexing' ) ) {
			wp_schedule_single_event( time() + 5, 'wpf_calc_meta_indexing' );
		}
	}

	/**
	 * installDataByUid: called by the Pro licence model (3.4.x and 4.0) when the licence server answers
	 * with `db_install` data. No Free version since 3.4.0 had it, so that answer was a fatal error; the
	 * server data is not written any more (no table takes rows by uid), the call is logged and refused.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param string $table Table alias such as `@__options`.
	 * @param string $uid   Row uid from the licence server.
	 * @param mixed  $data  Row data.
	 * @return bool Always false.
	 */
	public static function installDataByUid( $table, $uid, $data ) {
		/**
		 * Fires instead of the removed licence server data install.
		 *
		 * @since 4.0.0
		 */
		do_action( 'woobewoo_pf_install_data_by_uid', $table, $uid, $data );
		return false;
	}

	/**
	 * setUsed.
	 */
	public static function setUsed() {
		update_option( WPF_DB_PREF . 'plug_was_used', 1 );
	}

	/**
	 * isUsed.
	 */
	public static function isUsed() {
		return (int) get_option( WPF_DB_PREF . 'plug_was_used' );
	}

	/**
	 * delete.
	 *
	 * @version 3.4.0
	 */
	public static function delete() {
		global $wpdb;
		$wpPrefix = $wpdb->prefix;
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . esc_sql( WPF_DB_PREF ) . 'modules`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . esc_sql( WPF_DB_PREF ) . 'modules_type`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . esc_sql( WPF_DB_PREF ) . 'usage_stat`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . esc_sql( WPF_DB_PREF ) . 'meta_data`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		delete_option( $wpPrefix . WPF_DB_PREF . 'db_version' );
		delete_option( $wpPrefix . WPF_DB_PREF . 'db_installed' );
	}

	/**
	 * update.
	 */
	public static function update() {
		global $wpdb;
		$wpPrefix       = $wpdb->prefix; /* add to 0.0.3 Version */
		$currentVersion = get_option( $wpPrefix . WPF_DB_PREF . 'db_version', 0 );
		if (
			! $currentVersion ||
			version_compare( WPF_VERSION, $currentVersion, '>' )
		) {
			// 4.0.1: one request at a time runs the upgrade. Right after an update several requests arrive at once;
			// without the lock each of them converted the same tables again (ALTER TABLE ... CONVERT TO copies the
			// whole table) and queued behind each other's metadata locks.
			$lockName = 'wpf_upgrade_' . md5( DB_NAME . $wpPrefix );
			$locked   = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lockName ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( '0' === $locked ) {
				return; // another request is upgrading; this one serves the page with what exists.
			}
			try {
				if ( version_compare( WPF_VERSION, (string) get_option( $wpPrefix . WPF_DB_PREF . 'db_version', 0 ), '>' ) || ! $currentVersion ) {
					self::init( true );
					update_option( $wpPrefix . WPF_DB_PREF . 'db_version', WPF_VERSION );
					// 4.0.0: pretty URL rules change with the version; modules/links flushes them on the next init.
					update_option( 'wpf_links_flush_needed', '1', false );
				}
			} finally {
				if ( '1' === $locked ) {
					$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lockName ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
			}
		} else {
			WooBeWoo_PF_Installer_Db_Updater::syncModules();
		}
	}
}
