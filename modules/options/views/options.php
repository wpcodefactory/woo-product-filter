<?php
/**
 * Product Filter by WBW - OptionsViewWpf Class
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class OptionsViewWpf extends ViewWpf {

	private $_news = array();

	public function getNewFeatures() {
		$res = array();
		$readmePath = WPF_DIR . 'readme.txt';
		if (file_exists($readmePath)) {
			$readmeContent = @file_get_contents($readmePath);
			if (!empty($readmeContent)) {
				$matchedData = '';
				if (preg_match('/= ' . WPF_VERSION . ' =(.+)=.+=/isU', $readmeContent, $matches)) {
					$matchedData = $matches[1];
				} elseif (preg_match('/= ' . WPF_VERSION . ' =(.+)/is', $readmeContent, $matches)) {
					$matchedData = $matches[1];
				}
				$matchedData = trim($matchedData);
				if (!empty($matchedData)) {
					$res = array_map('trim', explode("\n", $matchedData));
				}
			}
		}
		return $res;
	}

	/**
	 * getAdminPage.
	 *
	 * @version 3.3.0
	 */
	public function getAdminPage() {
		$tabs = $this->getModule()->getTabs();
		$activeTab = $this->getModule()->getActiveTab();
		$content = 'No tab content found - ERROR';

		if (isset($tabs[ $activeTab ]) && isset($tabs[ $activeTab ]['callback'])) {
			$content = call_user_func($tabs[ $activeTab ]['callback']);
		}

		$activeParentTabs = array();
		foreach ($tabs as $tabKey => $tab) {
			if ($tabKey == $activeTab && isset($tab['child_of'])) {
				$activeTab = $tab['child_of'];
			}
		}

		FrameWpf::_()->addJSVar('woobewoo-pf-admin-options', 'wpfActiveTab', $activeTab);
		$this->assign('tabs', $tabs);
		$this->assign('activeTab', $activeTab);
		$this->assign('content', $content);
		$this->assign('mainUrl', $this->getModule()->getTabUrl());
		$this->assign('activeParentTabs', $activeParentTabs);
		$this->assign('breadcrumbs', FrameWpf::_()->getModule('admin_nav')->getView()->getBreadcrumbs());

		FrameWpf::_()->addScript('woobewoo-pf-admin-create-table', FrameWpf::_()->getModule('woofilters')->getModPath() . 'js/create-filter.js', array(), false, true);
		FrameWpf::_()->addJSVar('woobewoo-pf-admin-create-table', 'url', admin_url('admin-ajax.php'));
		FrameWpf::_()->addJSVar('woobewoo-pf-admin-create-table', 'wpfNonce', wp_create_nonce('woobewoo-pf-save-nonce'));

		parent::display('optionsAdminPage');
	}

	public function sortOptsSet( $a, $b ) {
		if ($a['weight'] > $b['weight']) {
			return -1;
		}
		if ($a['weight'] < $b['weight']) {
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
		$this->assign('systemInfo', array(
			'Operating System'            => array('value' => PHP_OS),
			'PHP Version'                 => array('value' => PHP_VERSION),
			'Server Software'             => array('value' => ( empty($_SERVER['SERVER_SOFTWARE']) ? '' : sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) )),
			'MySQL'                       => array('value' =>  $wpdb->db_version()),
			'PHP Allow URL Fopen'         => array('value' => ini_get('allow_url_fopen') ? 'Yes' : 'No'),
			'PHP Memory Limit'            => array('value' => ini_get('memory_limit')),
			'PHP Max Post Size'           => array('value' => ini_get('post_max_size')),
			'PHP Max Upload Filesize'     => array('value' => ini_get('upload_max_filesize')),
			'PHP Max Script Execute Time' => array('value' => ini_get('max_execution_time')),
			'PHP EXIF Support'            => array('value' => extension_loaded('exif') ? 'Yes' : 'No'),
			'PHP EXIF Version'            => array('value' => phpversion('exif')),
			'PHP XML Support'             => array('value' => extension_loaded('libxml') ? 'Yes' : 'No', 'error' => !extension_loaded('libxml')),
			'PHP CURL Support'            => array('value' => extension_loaded('curl') ? 'Yes' : 'No', 'error' => !extension_loaded('curl')),
		));
		return parent::display('_serverSettings');
	}

	/**
	 * getSettingsTabContent.
	 *
	 * @version 3.3.0
	 */
	public function getSettingsTabContent() {
		FrameWpf::_()->addScript('woobewoo-pf-admin-settings', $this->getModule()->getModPath() . 'js/admin.settings.js');
		FrameWpf::_()->addStyle('woobewoo-pf-admin-settings', $this->getModule()->getModPath() . 'css/admin.settings.css');
		FrameWpf::_()->getModule('templates')->loadJqueryUi();
		FrameWpf::_()->addScript('woobewoo-pf-notify', WPF_JS_PATH . 'notify.js', array(), false, true);
		if (FrameWpf::_()->isPro()) {
			FrameWpf::_()->addJSVar('wp-color-picker', 'wpColorPickerL10n', array());
			FrameWpf::_()->addScript('woobewoo-pf-admin-colorpicker-alhpa', WPF_JS_PATH . 'admin.wp.colorpicker.alpha.js');
			FrameWpf::_()->addStyle('woobewoo-pf-loaders', FrameWpf::_()->getModule('woofilters')->getModPath() . 'css/loaders.css');
		}

		$options = FrameWpf::_()->getModule('options')->getAll();
		$this->assign('options', $options);
		$this->assign('exportAllSubscribersUrl', UriWpf::mod('subscribe', 'getWpCsvList'));
		return parent::getContent('optionsSettingsTabContent');
	}

	/**
	 * getProTabContent.
	 *
	 * @version 3.3.0
	 */
	public function getProTabContent() {
		FrameWpf::_()->addScript('woobewoo-pf-admin-settings', $this->getModule()->getModPath() . 'js/admin.settings.js');
		FrameWpf::_()->addStyle('woobewoo-pf-admin-settings', $this->getModule()->getModPath() . 'css/admin.settings.css');
		FrameWpf::_()->getModule('templates')->loadBootstrap();
		FrameWpf::_()->getModule('templates')->loadJqueryUi();
		FrameWpf::_()->addScript('woobewoo-pf-notify', WPF_JS_PATH . 'notify.js', array(), false, true);

		return parent::getContent('optionsProTabContent');
	}
}
