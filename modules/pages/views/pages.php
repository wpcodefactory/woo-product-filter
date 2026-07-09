<?php
/**
 * Product Filter by WBW - PagesViewWpf Class
 *
 * @version 3.1.9
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class PagesViewWpf extends ViewWpf {
	public function displayDeactivatePage() {
		$this->assign('GET', ReqWpf::get('get'));
		$this->assign('POST', ReqWpf::get('post'));
		$this->assign('REQUEST_METHOD', strtoupper(ReqWpf::getVar('REQUEST_METHOD', 'server')));
		$this->assign('REQUEST_URI', basename(ReqWpf::getVar('REQUEST_URI', 'server')));
		parent::display('deactivatePage');
	}
}
