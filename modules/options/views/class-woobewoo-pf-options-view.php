<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Options_View Class
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Options_View extends WooBeWoo_PF_View {

	private $_news = array();

	public function getNewFeatures() {
		$res        = array();
		$readmePath = WPF_DIR . 'readme.txt';
		if ( file_exists( $readmePath ) ) {
			$readmeContent = @file_get_contents( $readmePath );
			if ( ! empty( $readmeContent ) ) {
				$matchedData = '';
				if ( preg_match( '/= ' . WPF_VERSION . ' =(.+)=.+=/isU', $readmeContent, $matches ) ) {
					$matchedData = $matches[1];
				} elseif ( preg_match( '/= ' . WPF_VERSION . ' =(.+)/is', $readmeContent, $matches ) ) {
					$matchedData = $matches[1];
				}
				$matchedData = trim( $matchedData );
				if ( ! empty( $matchedData ) ) {
					$res = array_map( 'trim', explode( "\n", $matchedData ) );
				}
			}
		}
		return $res;
	}

	/**
	 * getAdminPage.
	 *
	 * @version 3.4.0
	 */
	public function getAdminPage() {
		$tabs      = $this->getModule()->getTabs();
		$activeTab = $this->getModule()->getActiveTab();
		$content   = 'No tab content found - ERROR';

		if ( isset( $tabs[ $activeTab ] ) && isset( $tabs[ $activeTab ]['callback'] ) ) {
			$content = call_user_func( $tabs[ $activeTab ]['callback'] );
		}

		$activeParentTabs = array();
		foreach ( $tabs as $tabKey => $tab ) {
			if ( $tabKey == $activeTab && isset( $tab['child_of'] ) ) {
				$activeTab = $tab['child_of'];
			}
		}

		WooBeWoo_PF_Frame::_()->addJSVar( 'woobewoo-pf-admin-options', 'wpfActiveTab', $activeTab );
		$this->assign( 'tabs', $tabs );
		$this->assign( 'activeTab', $activeTab );
		$this->assign( 'content', $content );
		$this->assign( 'mainUrl', $this->getModule()->getTabUrl() );
		$this->assign( 'activeParentTabs', $activeParentTabs );
		$this->assign( 'breadcrumbs', WooBeWoo_PF_Frame::_()->getModule( 'admin_nav' )->getView()->getBreadcrumbs() );

		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-create-table', WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModPath() . 'js/create-filter.js', array(), false, true );
		WooBeWoo_PF_Frame::_()->addJSVar(
			'woobewoo-pf-admin-create-table',
			'woobewoo_pf_admin_ajax_object',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'woobewoo-pf-save-nonce' ),
				'wpfTblDataUrl' => WooBeWoo_PF_Uri::mod(
					'woofilters',
					'woobewoo_pf_get_list_for_table',
					array(
						'reqType'  => 'ajax',
						'wpfNonce' => wp_create_nonce( 'woobewoo-pf-save-nonce' ),
					),
				),
			)
		);

		parent::display( 'optionsAdminPage' );
	}

	public function sortOptsSet( $a, $b ) {
		if ( $a['weight'] > $b['weight'] ) {
			return -1;
		}
		if ( $a['weight'] < $b['weight'] ) {
			return 1;
		}
		return 0;
	}

	/**
	 * serverSettings.
	 *
	 * @version 3.1.8
	 */
	public function serverSettings() {
		global $wpdb;
		$this->assign(
			'systemInfo',
			array(
				'Operating System'            => array( 'value' => PHP_OS ),
				'PHP Version'                 => array( 'value' => PHP_VERSION ),
				'Server Software'             => array( 'value' => ( empty( $_SERVER['SERVER_SOFTWARE'] ) ? '' : sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) ),
				'MySQL'                       => array( 'value' => $wpdb->db_version() ),
				'PHP Allow URL Fopen'         => array( 'value' => ini_get( 'allow_url_fopen' ) ? 'Yes' : 'No' ),
				'PHP Memory Limit'            => array( 'value' => ini_get( 'memory_limit' ) ),
				'PHP Max Post Size'           => array( 'value' => ini_get( 'post_max_size' ) ),
				'PHP Max Upload Filesize'     => array( 'value' => ini_get( 'upload_max_filesize' ) ),
				'PHP Max Script Execute Time' => array( 'value' => ini_get( 'max_execution_time' ) ),
				'PHP EXIF Support'            => array( 'value' => extension_loaded( 'exif' ) ? 'Yes' : 'No' ),
				'PHP EXIF Version'            => array( 'value' => phpversion( 'exif' ) ),
				'PHP XML Support'             => array(
					'value' => extension_loaded( 'libxml' ) ? 'Yes' : 'No',
					'error' => ! extension_loaded( 'libxml' ),
				),
				'PHP CURL Support'            => array(
					'value' => extension_loaded( 'curl' ) ? 'Yes' : 'No',
					'error' => ! extension_loaded( 'curl' ),
				),
			)
		);
		return parent::display( '_serverSettings' );
	}

	/**
	 * getSettingsTabContent.
	 *
	 * @version 3.4.0
	 */
	public function getSettingsTabContent() {
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-settings', $this->getModule()->getModPath() . 'js/admin.settings.js' );
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-admin-settings', $this->getModule()->getModPath() . 'css/admin.settings.css' );
		WooBeWoo_PF_Frame::_()->getModule( 'templates' )->loadJqueryUi();
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-notify', WPF_JS_PATH . 'notify.js', array(), false, true );

		WooBeWoo_PF_Dispatcher::doAction( 'woobewoo_pf_enqueue_admin_option_pro_assets' );

		$options = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getAll();
		$this->assign( 'options', $options );
		$this->assign( 'exportAllSubscribersUrl', WooBeWoo_PF_Uri::mod( 'subscribe', 'getWpCsvList' ) );
		return parent::getContent( 'optionsSettingsTabContent' );
	}

	/**
	 * getProTabContent.
	 *
	 * @version 3.4.0
	 */
	public function getProTabContent() {
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-settings', $this->getModule()->getModPath() . 'js/admin.settings.js' );
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-admin-settings', $this->getModule()->getModPath() . 'css/admin.settings.css' );
		WooBeWoo_PF_Frame::_()->getModule( 'templates' )->loadBootstrap();
		WooBeWoo_PF_Frame::_()->getModule( 'templates' )->loadJqueryUi();
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-notify', WPF_JS_PATH . 'notify.js', array(), false, true );

		return parent::getContent( 'optionsProTabContent' );
	}
}
