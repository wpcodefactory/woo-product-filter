<?php
/**
 * Product Filter by WBW - PagesWpf Class
 *
 * @version 3.1.8
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class PagesWpf extends ModuleWpf {

	/**
	 * Check if current page is Login page.
	 *
	 * @version 3.1.8
	 */
	public function isLogin() {
		$name = empty($_SERVER['SCRIPT_NAME']) ? '' : sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME']));
		$url  = empty($_SERVER['REQUEST_URI']) ? '' : sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
		return ( basename($name) == 'wp-login.php' || strpos($url, '/login/') === 0 ); // Some plugins create login page by this address
	}
}
