<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Admin_Nav_View Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Admin_Nav_View extends ViewWpf {
	public function getBreadcrumbs() {
		$this->assign( 'breadcrumbsList', DispatcherWpf::applyFilters( 'mainBreadcrumbs', $this->getModule()->getBreadcrumbsList() ) );
		return parent::getContent( 'adminNavBreadcrumbs' );
	}
}
