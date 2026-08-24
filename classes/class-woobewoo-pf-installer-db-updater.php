<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Installer_Db_Updater Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Installer_Db_Updater {

	/**
	 * runUpdate.
	 *
	 * @version 3.3.2
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
	}
}
