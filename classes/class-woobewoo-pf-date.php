<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Date Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Date {
	public static function _( $time = null ) {
		if ( is_null( $time ) ) {
			$time = time();
		}

		return gmdate( WPF_DATE_FORMAT_HIS, $time );
	}
}
