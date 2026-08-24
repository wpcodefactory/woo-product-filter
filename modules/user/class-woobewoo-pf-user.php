<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_User Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_User extends ModuleWpf {

	protected $_data       = array();
	protected $_curentID   = 0;
	protected $_dataLoaded = false;

	public function loadUserData() {
		return $this->getCurrent();
	}

	/**
	 * isAdmin.
	 *
	 * @version 3.3.0
	 */
	public function isAdmin() {
		return current_user_can( FrameWpf::_()->getModule( 'adminmenu' )->getMainCap() );
	}

	public function getCurrentUserPosition() {
		if ( $this->isAdmin() ) {
			return WPF_ADMIN;
		} elseif ( $this->getCurrentID() ) {
			return WPF_LOGGED;
		} else {
			return WPF_GUEST;
		}
	}

	public function getCurrent() {
		return wp_get_current_user();
	}

	public function getCurrentID() {
		$this->_loadUserData();

		return $this->_curentID;
	}

	/**
	 * _loadUserData.
	 *
	 * @version 3.3.0
	 */
	protected function _loadUserData() {
		if ( ! $this->_dataLoaded ) {
			$user              = wp_get_current_user();
			$this->_data       = $user->data;
			$this->_curentID   = $user->ID;
			$this->_dataLoaded = true;
		}
	}

	public function getAdminsList() {
		global $wpdb;
		$admins = DbWpf::get(
			'SELECT * FROM #__users
			INNER JOIN #__usermeta ON #__users.ID = #__usermeta.user_id
			WHERE #__usermeta.meta_key = "#__capabilities" AND #__usermeta.meta_value LIKE "%administrator%"'
		);

		return $admins;
	}

	public function isLoggedIn() {
		return is_user_logged_in();
	}
}
