<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Admin_Nav_Controller Class
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Admin_Nav_Controller extends ControllerWpf {
	public function getPermissions() {
		return array(
			WPF_USERLEVELS => array(
				WPF_ADMIN => array(),
			),
		);
	}
}
