<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Overview Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Overview extends ModuleWpf {

	/**
	 * init.
	 *
	 * @version 3.3.2
	 */
	public function init() {
		WooBeWoo_PF_Dispatcher::addFilter( 'mainAdminTabs', array( $this, 'addAdminTab' ) );
	}

	/**
	 * addAdminTab.
	 *
	 * @version 3.3.2
	 */
	public function addAdminTab( $tabs ) {
		if ( ! WooBeWoo_PF_Frame::_()->isWCLicense() ) {
			$tabs['overview'] = array(
				'label'      => esc_html__( 'Overview', 'woo-product-filter' ),
				'callback'   => array( $this, 'getOverviewTabContent' ),
				'fa_icon'    => 'fa-info-circle',
				'sort_order' => 5,
				'is_main'    => true,
			);
		}
		return $tabs;
	}

	/**
	 * getOverviewTabContent.
	 */
	public function getOverviewTabContent() {
		return $this->getView()->getOverviewTabContent();
	}

	/**
	 * showAdminInfo.
	 */
	public function showAdminInfo() {
		return $this->getView()->showAdminInfo();
	}
}
