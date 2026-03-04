<?php

class InstallerDbUpdaterWpf {
	public static function runUpdate( $current_version ) {
		if ( DbWpf::get( "SELECT 1 FROM `@__modules` WHERE code='meta'", 'one' ) != 1 ) {
			DbWpf::query( "INSERT INTO `@__modules` (id, code, active, type_id, label) VALUES (NULL, 'meta', 1, 1, 'meta');" );
		}
		if ( ! DbWpf::existsTableColumn( '@__filters', 'meta_keys' ) ) {
			DbWpf::query( 'ALTER TABLE `@__filters` ADD COLUMN `meta_keys` varchar(255) NULL DEFAULT NULL AFTER `setting_data`' );
		}
		if ( DbWpf::get( "SELECT 1 FROM `@__modules` WHERE code='overview'", 'one' ) != 1 ) {
			DbWpf::query( "INSERT INTO `@__modules` (id, code, active, type_id, label) VALUES (NULL, 'overview', 1, 1, 'overview');" );
		}
	}
}
