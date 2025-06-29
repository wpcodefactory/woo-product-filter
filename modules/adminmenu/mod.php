<?php
class AdminmenuWpf extends ModuleWpf {
	protected $_mainSlug = 'wpf-filters';
	private $_mainCap = 'manage_options';
	public function init() {
		parent::init();
		add_action('admin_menu', array($this, 'initMenu'), 90);
		$plugName = plugin_basename(WPF_DIR . WPF_MAIN_FILE);
		add_filter('plugin_action_links_' . $plugName, array($this, 'addSettingsLinkForPlug') );
	}
	public function addSettingsLinkForPlug( $links ) {
		$mainLink = WPF_WP_PLUGIN_URL;
		/* translators: %s: plugin name */
		$twitterStatus = sprintf(esc_html__('Cool WordPress plugins from woobewoo.com developers. I tried %s - and this was what I need! #woobewoo.com', 'woo-product-filter'), WPF_WP_PLUGIN_NAME);
		array_unshift($links, '<a href="' . esc_url($this->getMainLink()) . '">' . esc_html__('Settings', 'woo-product-filter') . '</a>');
		array_push($links, '<a title="' . esc_attr__('More plugins for your WordPress site here!', 'woo-product-filter') . '" href="' . esc_url($mainLink) . '" target="_blank">' . esc_html(WPF_WP_PLUGIN_URL) . '</a>');
		array_push($links, '<a title="' . esc_attr__('Spread the word!', 'woo-product-filter') . '" href="https://www.facebook.com/sharer/sharer.php?u=' . urlencode($mainLink) . '" target="_blank" class="dashicons-before dashicons-facebook-alt"></a>');
		array_push($links, '<a title="' . esc_attr__('Spread the word!', 'woo-product-filter') . '" href="https://twitter.com/home?status=' . urlencode($twitterStatus) . '" target="_blank" class="dashicons-before dashicons-twitter"></a>');
		array_push($links, '<a title="' . esc_attr__('Spread the word!', 'woo-product-filter') . '" href="https://plus.google.com/share?url=' . urlencode($mainLink) . '" target="_blank" class="dashicons-before dashicons-googleplus"></a>');
		return $links;
	}
	public function initMenu() {
		
		$mainCap = $this->getMainCap();
		$mainSlug = DispatcherWpf::applyFilters('adminMenuMainSlug', $this->_mainSlug);
		$mainMenuPageOptions = array(
			'page_title' => WPF_WP_PLUGIN_NAME, 
			'menu_title' => WPF_WP_PLUGIN_NAME, 
			'capability' => $mainCap,
			'menu_slug' => $mainSlug,
			'function' => array(FrameWpf::_()->getModule('options'), 'getAdminPage'));
		$mainMenuPageOptions = DispatcherWpf::applyFilters('adminMenuMainOption', $mainMenuPageOptions);
		if (FrameWpf::_()->isWCLicense()) {
			add_submenu_page('woocommerce', $mainMenuPageOptions['menu_title'], $mainMenuPageOptions['menu_title'], $mainMenuPageOptions['capability'], $mainMenuPageOptions['menu_slug'], $mainMenuPageOptions['function']);
			return;
		}
		
		add_menu_page($mainMenuPageOptions['page_title'], $mainMenuPageOptions['menu_title'], $mainMenuPageOptions['capability'], $mainMenuPageOptions['menu_slug'], $mainMenuPageOptions['function'], 'dashicons-list-view');
		
		//remove duplicated WP menu item
		//add_submenu_page($mainMenuPageOptions['menu_slug'], '', '', $mainMenuPageOptions['capability'], $mainMenuPageOptions['menu_slug'], $mainMenuPageOptions['function']);
		$tabs = FrameWpf::_()->getModule('options')->getTabs();
		$subMenus = array();
		foreach ($tabs as $tKey => $tab) {
			if ('main_page' == $tKey) {
				continue;	// Top level menu item - is main page, avoid place it 2 times
			}

			if ( ( isset($tab['hidden']) && $tab['hidden'] )
				|| ( isset($tab['hidden_for_main']) && $tab['hidden_for_main'] )	// Hidden for WP main
				/*|| ( isset($tab['is_main']) && $tab['is_main'] )*/ ) {
				continue;
			}

			if ('gopro' == $tKey) {
				$subMenus[] = array(
					'title' => esc_html__('Upgrade To Pro', 'woo-product-filter'), 'capability' => $mainCap, 'menu_slug' => 'admin.php?page=' . $mainSlug . '&tab=' . $tKey, 'function' => '',
				);
			} else {
				$subMenus[] = array(
					'title' => $tab['label'], 'capability' => $mainCap, 'menu_slug' => 'admin.php?page=' . $mainSlug . '&tab=' . $tKey, 'function' => '',
				);
			}
		}
		$subMenus = DispatcherWpf::applyFilters('adminMenuOptions', $subMenus);
		foreach ($subMenus as $opt) {
			add_submenu_page($mainSlug, $opt['title'], $opt['title'], $opt['capability'], $opt['menu_slug'], $opt['function']);
		}
		remove_submenu_page($mainSlug, $mainSlug);
	}
	public function getMainLink() {
		return UriWpf::_(array('baseUrl' => admin_url('admin.php'), 'page' => $this->getMainSlug()));
	}
	public function getMainSlug() {
		return $this->_mainSlug;
	}
	public function getMainCap() {
		return DispatcherWpf::applyFilters('adminMenuAccessCap', $this->_mainCap);
	}
}
