<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Admin_Nav_Controller Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Admin_Nav_Controller extends WooBeWoo_PF_Controller {
	public function getPermissions() {
		return array(
			WPF_USERLEVELS => array(
				WPF_ADMIN => array(),
			),
		);
	}
}
