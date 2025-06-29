<?php
class DateWpf {
	public static function _( $time = null ) {
		if (is_null($time)) {
			$time = time();
		}
		return gmdate(WPF_DATE_FORMAT_HIS, $time);
	}
}
