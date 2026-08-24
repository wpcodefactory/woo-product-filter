<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Options_Controller Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Options_Controller extends WooBeWoo_PF_Controller {

	/**
	 * woobewoo_pf_save_group.
	 *
	 * @version 3.3.2
	 */
	public function woobewoo_pf_save_group() {
		WooBeWoo_PF_Req::verifyRequest();

		$res = new ResponseWpf();
		if ( $this->getModel()->woobewoo_pf_save_group( WooBeWoo_PF_Req::get( 'post' ) ) ) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel( 'options' )->getErrors() );
		}

		return $res->ajaxExec();
	}

	/**
	 * getPermissions.
	 *
	 * @version 3.3.0
	 */
	public function getPermissions() {
		return array(
			WPF_USERLEVELS => array(
				WPF_ADMIN => array( 'woobewoo_pf_save_group' ),
			),
		);
	}
}
