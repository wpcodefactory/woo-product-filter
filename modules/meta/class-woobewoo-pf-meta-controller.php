<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Meta_Controller Class
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Meta_Controller extends WooBeWoo_PF_Controller {

	protected $_code = 'meta';

	/**
	 * woobewoo_pf_do_meta_indexing_free.
	 *
	 * @version 3.3.0
	 */
	public function woobewoo_pf_do_meta_indexing_free() {
		return $this->woobewoo_pf_do_meta_indexing( false );
	}

	/**
	 * woobewoo_pf_do_meta_indexing.
	 *
	 * @version 3.4.0
	 */
	public function woobewoo_pf_do_meta_indexing( $realAjax = true ) {
		if ( $realAjax ) {
			WooBeWoo_PF_Req::verifyRequest();
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Sorry, you are not allowed to perform this action.', 'woo-product-filter' ),
				),
				403
			);
		}

		$res = new WooBeWoo_PF_Response();

		if ( WooBeWoo_PF_Req::getVar( 'inCron' ) ) {
			if ( ! wp_next_scheduled( 'wpf_calc_meta_indexing' ) ) {
				wp_schedule_single_event( time() + 3, 'wpf_calc_meta_indexing' );
			}
			$result = true;
		} else {
			$result = $this->getModel()->recalcMetaValues();
		}
		if ( false != $result ) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel()->getErrors() );
		}

		return $res->ajaxExec();
	}

	/**
	 * woobewoo_pf_do_meta_optimizing.
	 *
	 * @version 3.4.0
	 */
	public function woobewoo_pf_do_meta_optimizing() {
		WooBeWoo_PF_Req::verifyRequest();

		$res = new WooBeWoo_PF_Response();
		if ( $this->getModel()->optimizeMetaTables() ) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel()->getErrors() );
		}

		return $res->ajaxExec();
	}

	/**
	 * getPermissions.
	 *
	 * @version 3.3.0
	 * @since   3.1.3
	 *
	 * @return array
	 */
	public function getPermissions() {
		return array(
			WPF_USERLEVELS => array(
				WPF_ADMIN => array(
					'woobewoo_pf_do_meta_indexing_free',
					'woobewoo_pf_do_meta_indexing',
					'woobewoo_pf_do_meta_optimizing',
				),
			),
		);
	}
}
