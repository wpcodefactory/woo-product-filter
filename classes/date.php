<?php
/**
 * Product Filter by WBW - DateWpf Class
 *
 * @version 3.1.9
 * @author  woobewoo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DateWpf {
	public static function _( $time = null ) {
		if (is_null($time)) {
			$time = time();
		}
		return gmdate(WPF_DATE_FORMAT_HIS, $time);
	}
}
