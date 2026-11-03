<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Cli_Controller Class
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Cli_Controller extends WooBeWoo_PF_Controller {

	protected $_code = 'cli';

	public function getPermissions() {
		return array();
	}
}
