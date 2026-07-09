<?php
/**
 * Product Filter by WBW - DateWpf Class
 *
 * @version 3.2.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class DateWpf {
	public static function _( $time = null ) {
		if (is_null($time)) {
			$time = time();
		}
		return gmdate(WPF_DATE_FORMAT_HIS, $time);
	}
}
