<?php
class Admin_NavViewWpf extends ViewWpf {
	public function getBreadcrumbs() {
		$this->assign('breadcrumbsList', DispatcherWpf::applyFilters('mainBreadcrumbs', $this->getModule()->getBreadcrumbsList()));
		return parent::getContent('adminNavBreadcrumbs');
	}
}
