<?php
/**
 * Product Filter by WBW - Admin_NavViewWpf Class
 *
 * @version 3.1.9
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class Admin_NavViewWpf extends ViewWpf {
	public function getBreadcrumbs() {
		$this->assign('breadcrumbsList', DispatcherWpf::applyFilters('mainBreadcrumbs', $this->getModule()->getBreadcrumbsList()));
		return parent::getContent('adminNavBreadcrumbs');
	}
}
