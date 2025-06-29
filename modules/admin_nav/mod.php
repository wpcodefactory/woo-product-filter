<?php
class Admin_NavWpf extends ModuleWpf {
	public function getBreadcrumbsList() {
		$res = array(
			array('label' => WPF_WP_PLUGIN_NAME, 'url' => FrameWpf::_()->getModule('adminmenu')->getMainLink()),
		);
		// Try to get current tab breadcrumb
		$activeTab = FrameWpf::_()->getModule('options')->getActiveTab();
		if ( !empty($activeTab) && 'main_page' != $activeTab ) {
			$tabs = FrameWpf::_()->getModule('options')->getTabs();
			if (!empty($tabs) && isset($tabs[ $activeTab ])) {
				if (isset($tabs[ $activeTab ]['add_bread']) && !empty($tabs[ $activeTab ]['add_bread'])) {
					if (!is_array($tabs[ $activeTab ]['add_bread'])) {
						$tabs[ $activeTab ]['add_bread'] = array( $tabs[ $activeTab ]['add_bread'] );
					}
					foreach ($tabs[ $activeTab ]['add_bread'] as $addForBread) {
						$res[] = array('label' => $tabs[ $addForBread ]['label'], 'url' => $tabs[ $addForBread ]['url']);
					}
				}
				if ('comparison_edit' == $activeTab) {
					$id = (int) ReqWpf::getVar('id', 'get');
					if ($id) {
						$tabs[ $activeTab ]['url'] .= '&id=' . $id;
					}
				}
				$res[] = array('label' => $tabs[ $activeTab ]['label'], 'url' => $tabs[ $activeTab ]['url']);
				if ('statistwpf' == $activeTab) {
					$statTabs = FrameWpf::_()->getModule('statistwpf')->getStatTabs();
					$currentStatTab = FrameWpf::_()->getModule('statistwpf')->getCurrentStatTab();
					if (isset($statTabs[ $currentStatTab ])) {
						$res[] = array('label' => $statTabs[ $currentStatTab ]['label'], 'url' => $statTabs[ $currentStatTab ]['url']);
					}
				}
			}
		}
		return $res;
	}
}
