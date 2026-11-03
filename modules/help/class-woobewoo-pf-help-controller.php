<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Help_Controller Class
 *
 * Every handler verifies the nonce and manage_options through WooBeWoo_PF_Req::verifyRequest().
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Help_Controller extends WooBeWoo_PF_Controller {

	protected $_code = 'help';

	/**
	 * Diagnostics JSON download.
	 */
	public function woobewoo_pf_help_diagnostics() {
		WooBeWoo_PF_Req::verifyRequest();
		$data = $this->getModule()->diagnostics();
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wbw-product-filter-diagnostics-' . gmdate( 'Ymd-His' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Compatibility self test.
	 */
	public function woobewoo_pf_help_selftest() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		$res->addData( 'rows', $this->getModule()->selfTest() );
		return $res->ajaxExec();
	}

	public function getPermissions() {
		return array(
			WPF_METHODS => array(
				'woobewoo_pf_help_diagnostics' => array( WPF_ADMIN ),
				'woobewoo_pf_help_selftest'    => array( WPF_ADMIN ),
			),
		);
	}
}
