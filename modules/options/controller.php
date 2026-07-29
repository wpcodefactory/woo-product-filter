<?php
/**
 * Product Filter by WBW - OptionsControllerWpf Class
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

class OptionsControllerWpf extends ControllerWpf {

	/**
	 * woobewoo_pf_save_group.
	 *
	 * @version 3.3.0
	 */
	public function woobewoo_pf_save_group() {
		ReqWpf::verifyRequest();

		$res = new ResponseWpf();
		if ( $this->getModel()->woobewoo_pf_save_group( ReqWpf::get( 'post' ) ) ) {
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
				WPF_ADMIN => array( 'woobewoo_pf_save_group' )
			),
		);
	}
}
