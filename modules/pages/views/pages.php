<?php
class PagesViewWpf extends ViewWpf {
	public function displayDeactivatePage() {
		$this->assign('GET', ReqWpf::get('get'));
		$this->assign('POST', ReqWpf::get('post'));
		$this->assign('REQUEST_METHOD', strtoupper(ReqWpf::getVar('REQUEST_METHOD', 'server')));
		$this->assign('REQUEST_URI', basename(ReqWpf::getVar('REQUEST_URI', 'server')));
		parent::display('deactivatePage');
	}
}
