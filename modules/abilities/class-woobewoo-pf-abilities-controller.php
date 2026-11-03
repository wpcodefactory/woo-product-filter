<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Abilities_Controller Class
 *
 * Admin AJAX: one click llms.txt creator (nonce + manage_options through verifyRequest()).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Abilities_Controller extends WooBeWoo_PF_Controller {

	protected $_code = 'abilities';

	/**
	 * Create (or refresh) llms.txt: a real file when the root is writable, else served through a rewrite.
	 */
	public function woobewoo_pf_create_llms() {
		WooBeWoo_PF_Req::verifyRequest();
		$res    = new WooBeWoo_PF_Response();
		$result = $this->getModel( 'readiness' )->createLlms();
		foreach ( $result as $k => $v ) {
			$res->addData( $k, $v );
		}
		$res->addMessage( $result['written'] ? esc_html__( 'llms.txt created at the site root.', 'woo-product-filter' ) : esc_html__( 'llms.txt is served by the plugin (the site root is not writable).', 'woo-product-filter' ) );
		return $res->ajaxExec();
	}

	/**
	 * Readiness score for admin screens (Free Help / Pro AI and agents).
	 */
	public function woobewoo_pf_readiness() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		foreach ( $this->getModel( 'readiness' )->score() as $k => $v ) {
			$res->addData( $k, $v );
		}
		return $res->ajaxExec();
	}

	public function getPermissions() {
		return array(
			WPF_METHODS => array(
				'woobewoo_pf_create_llms' => array( WPF_ADMIN ),
				'woobewoo_pf_readiness'   => array( WPF_ADMIN ),
			),
		);
	}
}
