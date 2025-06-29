<?php
class OverviewControllerWpf extends ControllerWpf {
	public function subscribe() {
		$res = new ResponseWpf();
		if ($this->getModel()->subscribe(ReqWpf::get('post'))) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		$res->ajaxExec();
	}
	public function contactus() {
		$res = new ResponseWpf();
		if ($this->getModel()->contactus(ReqWpf::get('post'))) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		$res->ajaxExec();
	}
	public function rating() {
		$res = new ResponseWpf();
		if ($this->getModel()->rating(ReqWpf::get('post'))) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		$res->ajaxExec();
	}
	public function dismissNotice() {
		$res = new ResponseWpf();
		$slug = ReqWpf::getVar('slug');
		if (!empty($slug) && !is_null($slug)) {
			FrameWpf::_()->getModule('options')->getModel()->save('dismiss_' . $slug, 1);
		}
		$res->ajaxExec();
	}
	public function approveNotice() {
		$res = new ResponseWpf();
		$slug = ReqWpf::getVar('slug');
		if ('wpf-rest-api' == $slug) {
			$opts = array('opt_values' => array(
					'disable_autoindexing' => 1,
					'disable_autoindexing_by_ss' => 1
				)
			);
			if (FrameWpf::_()->getModule('options')->get('indexing_schedule') != 1) {
				$opts['opt_values']['indexing_schedule'] = 1;
				$opts['opt_values']['shedule_hour'] = 1;
				$opts['opt_values']['shedule_day'] = 0;
			}
			if (FrameWpf::_()->getModule('options')->getModel()->saveGroup($opts)) {
				FrameWpf::_()->getModule('options')->getModel()->save('dismiss_' . $slug, 1);
			}
		}
		$res->ajaxExec();
	}
}
