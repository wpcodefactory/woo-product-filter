<?php
/**
 * Product Filter by WBW - OverviewWpf Class
 *
 * @version 2.8.6
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

class OverviewWpf extends ModuleWpf {
	public function init() {
		DispatcherWpf::addFilter('mainAdminTabs', array($this, 'addAdminTab'));
	}
	public function addAdminTab( $tabs ) {
		if (!FrameWpf::_()->isWCLicense()) {
			$tabs['overview'] = array(
				'label' => esc_html__('Overview', 'woo-product-filter'), 'callback' => array($this, 'getOverviewTabContent'), 'fa_icon' => 'fa-info-circle', 'sort_order' => 5, 'is_main' => true,
			);
		}
		return $tabs;
	}
	public function getOverviewTabContent() {
		return $this->getView()->getOverviewTabContent();
	}
	public function showAdminInfo() {
		return $this->getView()->showAdminInfo();
	}
}
