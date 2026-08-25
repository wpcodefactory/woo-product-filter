<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Admin_Nav_View Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Admin_Nav_View extends WooBeWoo_PF_View {

	/**
	 * getBreadcrumbs.
	 *
	 * @version 3.3.2
	 */
	public function getBreadcrumbs() {
		$this->assign( 'breadcrumbsList', WooBeWoo_PF_Dispatcher::applyFilters( 'mainBreadcrumbs', $this->getModule()->getBreadcrumbsList() ) );
		return parent::getContent( 'adminNavBreadcrumbs' );
	}
}
