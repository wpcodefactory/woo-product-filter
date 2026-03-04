<?php
class MetaControllerWpf extends ControllerWpf {

	protected $_code = 'meta';
	
	public function doMetaIndexingFree() {
		return $this->doMetaIndexing(false);
	}

	public function doMetaIndexing( $realAjax = true ) {
		if ($realAjax) {
			check_ajax_referer('wpf-save-nonce', 'wpfNonce');
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}

		$res = new ResponseWpf();

		if (ReqWpf::getVar('inCron')) {
			if ( !wp_next_scheduled( 'wpf_calc_meta_indexing' ) ) {
				wp_schedule_single_event( time() + 3, 'wpf_calc_meta_indexing' );
			}
			$result = true;
		} else {
			$result = $this->getModel()->recalcMetaValues();
		}
		if ( false != $result ) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		return $res->ajaxExec();
	}
	public function doMetaOptimizing() {
		check_ajax_referer('wpf-save-nonce', 'wpfNonce');
		if (!current_user_can('manage_options')) {
			wp_die();
		}

		$res = new ResponseWpf();
		if ( $this->getModel()->optimizeMetaTables() ) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		return $res->ajaxExec();
	}
}
