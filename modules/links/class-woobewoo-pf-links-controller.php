<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Links_Controller Class
 *
 * The links module has no admin AJAX: short links are created over REST (see the module class).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Links_Controller extends WooBeWoo_PF_Controller {

	protected $_code = 'links';

	public function getPermissions() {
		return array();
	}
}
