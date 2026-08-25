<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Overview_View Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Overview_View extends WooBeWoo_PF_View {

	/**
	 * getOverviewTabContent.
	 *
	 * @version 3.3.2
	 */
	public function getOverviewTabContent() {
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-overview', $this->getModule()->getModPath() . 'js/admin.overview.js' );

		WooBeWoo_PF_Frame::_()->getModule( 'templates' )->loadJqueryUi();
		WooBeWoo_PF_Frame::_()->getModule( 'templates' )->loadBootstrap();
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-notify', WPF_JS_PATH . 'notify.js', array(), false, true );
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-admin-overview', $this->getModule()->getModPath() . 'css/admin.overview.css' );

		$this->assign( 'isWeek', ( time() - $this->getModel()->getFirstOverview() ) > 608800 );
		return parent::getContent( 'overviewTabContent' );
	}

	/**
	 * showRestApiInfo.
	 *
	 * @version 3.3.2
	 */
	public function showRestApiInfo() {
		$dismiss = (int) WooBeWoo_PF_Frame::_()->getModule( 'options' )->get( 'dismiss_wpf-rest-api' );
		if ( $dismiss ) {
			return; // it was already dismissed by user - no need to show it again
		}
		global $wpdb;
		$api = $wpdb->get_var( "SELECT 1 FROM {$wpdb->prefix}woocommerce_api_keys" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( 1 != $api ) {
			return;
		}
		if ( WooBeWoo_PF_Frame::_()->getModule( 'options' )->get( 'disable_autoindexing' ) == 1 && WooBeWoo_PF_Frame::_()->getModule( 'options' )->get( 'disable_autoindexing_by_ss' ) == 1 && WooBeWoo_PF_Frame::_()->getModule( 'options' )->get( 'indexing_schedule' ) == 1 ) {
			WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->save( 'dismiss_wpf-rest-api', 1 );
			return;
		}

		WooBeWoo_PF_Frame::_()->getModule( 'templates' )->loadCoreJs();
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-notice-dismis', $this->getModule()->getModPath() . 'js/admin.notice.dismis.js' );

		$this->assign(
			'message',
			'<b>' . esc_html__( 'We have detected that you are using REST API to update products.', 'woo-product-filter' ) . '</b><br/><br/>' .
			esc_html__( 'To correctly interact with this functionality, you need to change the plugin settings.', 'woo-product-filter' ) . '<br/><br/>' .
			esc_html__( 'Please activate the "Disable automatic calculation of index tables after editing products" and "Disable automatic calculation of index tables after product stock changes" options', 'woo-product-filter' ) .
			' <a href="' . esc_url( WooBeWoo_PF_Frame::_()->getModule( 'options' )->getTabUrl( 'settings' ) ) . '">' . esc_html__( 'in the general plugin settings', 'woo-product-filter' ) . '</a>, ' . esc_html__( 'and set "Start indexing on schedule" at a time convenient for you.', 'woo-product-filter' ) . '<br/><br/>' .
			esc_html__( 'Configure these options for you?', 'woo-product-filter' ) .
			' <a href="#" class="button button-primary button-approve">' . esc_html__( 'Yes', 'woo-product-filter' ) . '</a>' .
			' <a href="#" class="button button-dismiss">' . esc_html__( 'No, thanks', 'woo-product-filter' ) . '</a>'
		);
		$this->assign( 'msgSlug', 'wpf-rest-api' );
		WooBeWoo_PF_Html::echoEscapedHtml( $this->getContent( 'showAdminInfo' ) );
	}
}
