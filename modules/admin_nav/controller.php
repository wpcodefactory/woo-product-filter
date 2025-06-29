<?php
class Admin_NavControllerWpf extends ControllerWpf {
	public function getPermissions() {
		return array(
			WPF_USERLEVELS => array(
				WPF_ADMIN => array()
			),
		);
	}
}
