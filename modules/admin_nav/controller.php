<?php
/**
 * Product Filter by WBW - Admin_NavControllerWpf Class
 *
 * @version 3.1.9
 * @author  woobewoo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin_NavControllerWpf extends ControllerWpf {
	public function getPermissions() {
		return array(
			WPF_USERLEVELS => array(
				WPF_ADMIN => array()
			),
		);
	}
}
