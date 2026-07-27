<?php
/**
 * Product Filter by WBW - OverviewControllerWpf Class
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class OverviewControllerWpf extends ControllerWpf {

	/**
	 * subscribe.
	 */
	public function subscribe() {
		$res = new ResponseWpf();
		if ( $this->getModel()->subscribe( ReqWpf::get( 'post' ) ) ) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel()->getErrors() );
		}
		$res->ajaxExec();
	}

	/**
	 * contactus.
	 */
	public function contactus() {
		$res = new ResponseWpf();
		if ( $this->getModel()->contactus( ReqWpf::get( 'post' ) ) ) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel()->getErrors() );
		}
		$res->ajaxExec();
	}

	/**
	 * rating.
	 */
	public function rating() {
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
	 * @version 3.3.0
	 */
	public function woobewoo_pf_dismiss_notice() {
		$res  = new ResponseWpf();
		$slug = ReqWpf::getVar( 'slug' );
		if (
			! empty( $slug ) &&
			! is_null( $slug ) &&
			current_user_can( 'manage_woocommerce' )
		) {
			FrameWpf::_()->getModule( 'options' )->getModel()->save( 'dismiss_' . $slug, 1 );
		}
		$res->ajaxExec();
	}

	/**
	 * approveNotice.
	 *
	 * @version 3.3.0
	 */
	public function approveNotice() {
		$res  = new ResponseWpf();
		$slug = ReqWpf::getVar( 'slug' );
		if (
			'wpf-rest-api' == $slug &&
			current_user_can( 'manage_woocommerce' )
		) {
			$opts = array(
				'opt_values' => array(
					'disable_autoindexing'       => 1,
					'disable_autoindexing_by_ss' => 1
				)
			);
			if ( FrameWpf::_()->getModule( 'options' )->get( 'indexing_schedule' ) != 1 ) {
				$opts['opt_values']['indexing_schedule'] = 1;
				$opts['opt_values']['shedule_hour']      = 1;
				$opts['opt_values']['shedule_day']       = 0;
			}
			if ( FrameWpf::_()->getModule( 'options' )->getModel()->woobewoo_pf_save_group( $opts ) ) {
				FrameWpf::_()->getModule( 'options' )->getModel()->save( 'dismiss_' . $slug, 1 );
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
			),
		);
	}

}
