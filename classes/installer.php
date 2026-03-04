<?php
class InstallerWpf {
	public static $update_to_version_method = '';
	private static $_firstTimeActivated = false;
	public static function init( $isUpdate = false ) {
		global $wpdb;
		$wpPrefix = $wpdb->prefix; /* add to 0.0.3 Versiom */
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$current_version = get_option($wpPrefix . WPF_DB_PREF . 'db_version', 0);
		if (!$current_version) {
			self::$_firstTimeActivated = true;
		}
		/**
		 * Table modules 
		 */
		if (!DbWpf::exist('@__modules')) {
			dbDelta(DbWpf::prepareQuery("CREATE TABLE IF NOT EXISTS `@__modules` (
			  `id` smallint(3) NOT NULL AUTO_INCREMENT,
			  `code` varchar(32) NOT NULL,
			  `active` tinyint(1) NOT NULL DEFAULT '0',
			  `type_id` tinyint(1) NOT NULL DEFAULT '0',
			  `label` varchar(64) DEFAULT NULL,
			  `ex_plug_dir` varchar(255) DEFAULT NULL,
			  PRIMARY KEY (`id`),
			  UNIQUE INDEX `code` (`code`)
			) DEFAULT CHARSET=utf8;"));
			DbWpf::query("INSERT INTO `@__modules` (id, code, active, type_id, label) VALUES
				(NULL, 'adminmenu',1,1,'Admin Menu'),
				(NULL, 'options',1,1,'Options'),
				(NULL, 'user',1,1,'Users'),
				(NULL, 'pages',1,1,'Pages'),
				(NULL, 'templates',1,1,'templates'),
				(NULL, 'promo',1,1,'promo'),
				(NULL, 'admin_nav',1,1,'admin_nav'),			  
				(NULL, 'woofilters',1,1,'woofilters'),
				(NULL, 'woofilters_widget',1,1,'woofilters_widget'),
				(NULL, 'mail',1,1,'mail'),
				(NULL, 'meta',1,1,'meta'),
				(NULL, 'overview',1,1,'overview');");
		}
		/**
		 *  Table modules_type 
		 */
		if (!DbWpf::exist('@__modules_type')) {
			dbDelta(DbWpf::prepareQuery('CREATE TABLE IF NOT EXISTS `@__modules_type` (
			  `id` smallint(3) NOT NULL AUTO_INCREMENT,
			  `label` varchar(32) NOT NULL,
			  PRIMARY KEY (`id`)
			) AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;'));
			DbWpf::query("INSERT INTO `@__modules_type` VALUES
				(1,'system'),
				(6,'addons');");
		}
		/**
		 * Table filters
		 */
		if (!DbWpf::exist('@__filters')) {
			dbDelta(DbWpf::prepareQuery('CREATE TABLE IF NOT EXISTS `@__filters` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`title` VARCHAR(128) NULL DEFAULT NULL,
				`setting_data` MEDIUMTEXT NOT NULL,
				PRIMARY KEY (`id`)
			) DEFAULT CHARSET=utf8;'));
		}
		if (version_compare($current_version, '1.3.6') != 1) {
			DbWpf::query('ALTER TABLE `@__filters` MODIFY setting_data MEDIUMTEXT;');
		}
		/**
		* Plugin usage statistwpf
		*/
		if (!DbWpf::exist('@__usage_stat')) {
			dbDelta(DbWpf::prepareQuery("CREATE TABLE `@__usage_stat` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `code` varchar(64) NOT NULL,
			  `visits` int(11) NOT NULL DEFAULT '0',
			  `spent_time` int(11) NOT NULL DEFAULT '0',
			  `modify_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			  UNIQUE INDEX `code` (`code`),
			  PRIMARY KEY (`id`)
			) DEFAULT CHARSET=utf8"));
			DbWpf::query("INSERT INTO `@__usage_stat` (code, visits) VALUES ('installed', 1)");
		}
		/**
		 *  Table meta_keys 
		 */
		if (!DbWpf::exist('@__meta_keys')) {
			dbDelta(DbWpf::prepareQuery('CREATE TABLE IF NOT EXISTS `@__meta_keys` (
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
			) DEFAULT CHARSET=utf8;'));
			DbWpf::query("INSERT INTO `@__meta_keys` VALUES
				(NULL,0,'_wpf_product_type','',0,0,0,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_product_attributes','',0,0,8,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'attribute_%','',1,0,0,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_wc_average_rating','',0,0,3,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_stock_status','',0,0,0,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_price','',0,0,1,0,CURRENT_TIMESTAMP,NULL,NULL,NULL),
				(NULL,0,'_sale_price','',0,0,1,0,CURRENT_TIMESTAMP,NULL,NULL,NULL);");
		}
		/**
		 *  Table meta_data 
		 */
		if (!DbWpf::exist('@__meta_data')) {
			dbDelta(DbWpf::prepareQuery('CREATE TABLE IF NOT EXISTS `@__meta_data` (
			  `id` bigint NOT NULL AUTO_INCREMENT,
			  `product_id` bigint NOT NULL,
			  `is_var` smallint(3) NOT NULL DEFAULT 0,
			  `key_id` INT(11) NOT NULL,
			  `val_int` bigint,
			  `val_dec` decimal(19,4),
			  `val_id` bigint,
			  `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  PRIMARY KEY (`id`)
			) DEFAULT CHARSET=utf8;'));
		}
		/**
		 *  Table meta_values 
		 */
		if (!DbWpf::exist('@__meta_values')) {
			dbDelta(DbWpf::prepareQuery('CREATE TABLE IF NOT EXISTS `@__meta_values` (
			  `id` bigint NOT NULL AUTO_INCREMENT,
			  `key_id` INT(11) NOT NULL,
			  `key2` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
			  `key3` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
			  `key4` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
			  `value` varchar(150) CHARACTER SET utf8 COLLATE utf8_bin,
			  `product_cnt` INT(11) NOT NULL DEFAULT 0,
			  `variation_cnt` INT(11) NOT NULL DEFAULT 0,
			  PRIMARY KEY (`id`)
			) DEFAULT CHARSET=utf8;'));
		}
		/**
		 *  Table meta_values_bk
		 */
		if (!DbWpf::exist('@__meta_values_bk')) {
			dbDelta(DbWpf::prepareQuery('CREATE TABLE IF NOT EXISTS `@__meta_values_bk` (
			  `id` bigint NOT NULL,
			  `key_id` INT(11) NOT NULL,
			  `key2` varchar(32) NOT NULL,
			  `key3` varchar(32) NOT NULL,
			  `key4` varchar(32) NOT NULL,
			  `value` varchar(150),
			  PRIMARY KEY (`id`),
			  INDEX `key_id` (`key_id`)
			) DEFAULT CHARSET=utf8;'));
		}
		InstallerDbUpdaterWpf::runUpdate($current_version);
		if ($current_version && !self::$_firstTimeActivated) {
			self::setUsed();
			// For users that just updated our plugin - don't need tp show step-by-step tutorial
			update_user_meta(get_current_user_id(), WPF_CODE . '-tour-hst', array('closed' => 1));
		}
		update_option($wpPrefix . WPF_DB_PREF . 'db_version', WPF_VERSION);
		add_option($wpPrefix . WPF_DB_PREF . 'db_installed', 1);
		if ( !wp_next_scheduled( 'wpf_calc_meta_indexing' ) ) {
			wp_schedule_single_event( time() + 5, 'wpf_calc_meta_indexing' );
		}
	}
	public static function setUsed() {
		update_option(WPF_DB_PREF . 'plug_was_used', 1);
	}
	public static function isUsed() {
		return (int) get_option(WPF_DB_PREF . 'plug_was_used');
	}
	public static function delete() {
		self::_checkSendStat('delete');
		global $wpdb;
		$wpPrefix = $wpdb->prefix;
		$wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . esc_sql(WPF_DB_PREF) . 'modules`');
		$wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . esc_sql(WPF_DB_PREF) . 'modules_type`');
		$wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . esc_sql(WPF_DB_PREF) . 'usage_stat`');
		$wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . esc_sql(WPF_DB_PREF) . 'meta_data`');
		delete_option($wpPrefix . WPF_DB_PREF . 'db_version');
		delete_option($wpPrefix . WPF_DB_PREF . 'db_installed');
	}
	public static function deactivate() {
		self::_checkSendStat('deactivate');
	}
	private static function _checkSendStat( $statCode ) {
		if (class_exists('FrameWpf') && FrameWpf::_()->getModule('promo') && FrameWpf::_()->getModule('options')) {
			FrameWpf::_()->getModule('promo')->getModel()->saveUsageStat( $statCode );
			FrameWpf::_()->getModule('promo')->getModel()->checkAndSend( true );
		}
	}
	public static function update() {
		global $wpdb;
		$wpPrefix = $wpdb->prefix; /* add to 0.0.3 Versiom */
		$currentVersion = get_option($wpPrefix . WPF_DB_PREF . 'db_version', 0);
		if (!$currentVersion || version_compare(WPF_VERSION, $currentVersion, '>')) {
			self::init( true );
			update_option($wpPrefix . WPF_DB_PREF . 'db_version', WPF_VERSION);
		}
	}
}
