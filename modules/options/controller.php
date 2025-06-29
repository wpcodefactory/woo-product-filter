<?php
class OptionsControllerWpf extends ControllerWpf {
	public function saveGroup() {
		check_ajax_referer('wpf-save-nonce', 'wpfNonce');
		if (!current_user_can('manage_options')) {
			wp_die();
		}
		
		$res = new ResponseWpf();
		if ($this->getModel()->saveGroup(ReqWpf::get('post'))) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
		} else {
			$res->pushError ($this->getModel('options')->getErrors());
		}
		return $res->ajaxExec();
	}
	public function getPermissions() {
		return array(
			WPF_USERLEVELS => array(
				WPF_ADMIN => array('saveGroup')
			),
		);
	}
}
