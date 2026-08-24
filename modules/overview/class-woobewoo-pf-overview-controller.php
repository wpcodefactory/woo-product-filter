<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Overview_Controller Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Overview_Controller extends WooBeWoo_PF_Controller {

	/**
	 * woobewoo_pf_subscribe.
	 *
	 * @version 3.3.0
	 */
	public function woobewoo_pf_subscribe() {
		$res = new ResponseWpf();
		if ( $this->getModel()->subscribe( ReqWpf::get( 'post' ) ) ) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel()->getErrors() );
		}
		$res->ajaxExec();
	}

	/**
	 * woobewoo_pf_contactus.
	 *
	 * @version 3.3.0
	 */
	public function woobewoo_pf_contactus() {
		$res = new ResponseWpf();
		if ( $this->getModel()->contactus( ReqWpf::get( 'post' ) ) ) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel()->getErrors() );
		}
		$res->ajaxExec();
	}

	/**
	 * woobewoo_pf_rating.
	 *
	 * @version 3.3.0
	 */
	public function woobewoo_pf_rating() {
		$res = new ResponseWpf();
		if ( $this->getModel()->rating( ReqWpf::get( 'post' ) ) ) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel()->getErrors() );
		}
		$res->ajaxExec();
	}

	/**
	 * woobewoo_pf_dismiss_notice.
	 *
	 * @version 3.3.2
	 */
	public function woobewoo_pf_dismiss_notice() {
		$res  = new ResponseWpf();
		$slug = ReqWpf::getVar( 'slug' );
		if (
			! empty( $slug ) &&
			! is_null( $slug ) &&
			current_user_can( 'manage_woocommerce' )
		) {
			WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->save( 'dismiss_' . $slug, 1 );
		}
		$res->ajaxExec();
	}

	/**
	 * woobewoo_pf_approve_notice.
	 *
	 * @version 3.3.2
	 */
	public function woobewoo_pf_approve_notice() {
		$res  = new ResponseWpf();
		$slug = ReqWpf::getVar( 'slug' );
		if (
			'wpf-rest-api' == $slug &&
			current_user_can( 'manage_woocommerce' )
		) {
			$opts = array(
				'opt_values' => array(
					'disable_autoindexing'       => 1,
					'disable_autoindexing_by_ss' => 1,
				),
			);
			if ( WooBeWoo_PF_Frame::_()->getModule( 'options' )->get( 'indexing_schedule' ) != 1 ) {
				$opts['opt_values']['indexing_schedule'] = 1;
				$opts['opt_values']['shedule_hour']      = 1;
				$opts['opt_values']['shedule_day']       = 0;
			}
			if ( WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->woobewoo_pf_save_group( $opts ) ) {
				WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->save( 'dismiss_' . $slug, 1 );
			}
		}
		$res->ajaxExec();
	}

	/**
	 * getPermissions.
	 *
	 * @version 3.3.0
	 * @since   3.3.0
	 *
	 * @return array
	 */
	public function getPermissions() {
		return array(
			WPF_METHODS => array(
				'woobewoo_pf_dismiss_notice' => array(
					WPF_ADMIN,
				),
				'woobewoo_pf_approve_notice' => array(
					WPF_ADMIN,
				),
				'woobewoo_pf_subscribe'      => array(
					WPF_ADMIN,
				),
				'woobewoo_pf_contactus'      => array(
					WPF_ADMIN,
				),
				'woobewoo_pf_rating'         => array(
					WPF_ADMIN,
				),
			),
		);
	}
}
