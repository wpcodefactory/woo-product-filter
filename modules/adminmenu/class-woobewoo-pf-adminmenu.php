<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Adminmenu Class
 *
 * @version 4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Adminmenu extends WooBeWoo_PF_Module {

	protected $_mainSlug = 'wpf-filters';

	private $_mainCap = 'manage_options';

	public function init() {
		parent::init();
		add_action( 'admin_menu', array( $this, 'initMenu' ), 90 );
		// Always directly under WooCommerce in the admin sidebar (WooCommerce puts Products there at priority 10).
		add_filter( 'custom_menu_order', '__return_true', 99 );
		add_filter( 'menu_order', array( $this, 'placeUnderWoo' ), 99 );
		add_action( 'admin_head', array( $this, 'upgradeButtonCss' ) );
		$plugName = plugin_basename( WPF_DIR . WPF_MAIN_FILE );
		add_filter( 'plugin_action_links_' . $plugName, array( $this, 'addSettingsLinkForPlug' ) );
	}

	public function addSettingsLinkForPlug( $links ) {
		$mainLink = WPF_WP_PLUGIN_URL;
		/* translators: %s: plugin name */
		$twitterStatus = sprintf( esc_html__( 'Cool WordPress plugins from woobewoo.com developers. I tried %s - and this was what I need! #woobewoo.com', 'woo-product-filter' ), WPF_WP_PLUGIN_NAME );
		array_unshift( $links, '<a href="' . esc_url( $this->getMainLink() ) . '">' . esc_html__( 'Settings', 'woo-product-filter' ) . '</a>' );
		array_push( $links, '<a title="' . esc_attr__( 'More plugins for your WordPress site here!', 'woo-product-filter' ) . '" href="' . esc_url( $mainLink ) . '" target="_blank">' . esc_html( WPF_WP_PLUGIN_URL ) . '</a>' );
		array_push( $links, '<a title="' . esc_attr__( 'Spread the word!', 'woo-product-filter' ) . '" href="https://www.facebook.com/sharer/sharer.php?u=' . urlencode( $mainLink ) . '" target="_blank" class="dashicons-before dashicons-facebook-alt"></a>' );
		array_push( $links, '<a title="' . esc_attr__( 'Spread the word!', 'woo-product-filter' ) . '" href="https://twitter.com/home?status=' . urlencode( $twitterStatus ) . '" target="_blank" class="dashicons-before dashicons-twitter"></a>' );
		return $links;
	}

	/**
	 * initMenu.
	 *
	 * @version 3.4.0
	 */
	public function initMenu() {
		$mainCap             = $this->getMainCap();
		$mainSlug            = WooBeWoo_PF_Dispatcher::applyFilters( 'adminMenuMainSlug', $this->_mainSlug );
		$mainMenuPageOptions = array(
			'page_title' => WPF_WP_PLUGIN_NAME,
			'menu_title' => WPF_WP_PLUGIN_NAME,
			'capability' => $mainCap,
			'menu_slug'  => $mainSlug,
			'function'   => array( WooBeWoo_PF_Frame::_()->getModule( 'options' ), 'getAdminPage' ),
		);
		$mainMenuPageOptions = WooBeWoo_PF_Dispatcher::applyFilters( 'adminMenuMainOption', $mainMenuPageOptions );
		if ( WooBeWoo_PF_Frame::_()->isWCLicense() ) {
			add_submenu_page( 'woocommerce', $mainMenuPageOptions['menu_title'], $mainMenuPageOptions['menu_title'], $mainMenuPageOptions['capability'], $mainMenuPageOptions['menu_slug'], $mainMenuPageOptions['function'] );
			return;
		}

		add_menu_page( $mainMenuPageOptions['page_title'], $mainMenuPageOptions['menu_title'], $mainMenuPageOptions['capability'], $mainMenuPageOptions['menu_slug'], $mainMenuPageOptions['function'], $this->menuIcon(), '55.55' );

		// remove duplicated WP menu item
		$tabs     = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getTabs();
		$subMenus = array();
		foreach ( $tabs as $tKey => $tab ) {
			if ( 'main_page' == $tKey ) {
				continue; // Top level menu item - is main page, avoid place it 2 times
			}

			if (
				( isset( $tab['hidden'] ) && $tab['hidden'] ) ||
				( isset( $tab['hidden_for_main'] ) && $tab['hidden_for_main'] ) // Hidden for WP main
			) {
				continue;
			}

			if ( 'gopro' == $tKey ) {
				$subMenus[] = array(
					'title'      => esc_html__( 'Upgrade To Pro', 'woo-product-filter' ),
					'capability' => $mainCap,
					'menu_slug'  => 'admin.php?page=' . $mainSlug . '&tab=' . $tKey,
					'function'   => '',
				);
			} else {
				$subMenus[] = array(
					'title'      => $tab['label'],
					'capability' => $mainCap,
					'menu_slug'  => 'admin.php?page=' . $mainSlug . '&tab=' . $tKey,
					'function'   => '',
				);
			}
		}
		$subMenus = WooBeWoo_PF_Dispatcher::applyFilters( 'adminMenuOptions', $subMenus );
		foreach ( $subMenus as $opt ) {
			add_submenu_page( $mainSlug, $opt['title'], $opt['title'], $opt['capability'], $opt['menu_slug'], $opt['function'] );
		}
		remove_submenu_page( $mainSlug, $mainSlug );
	}

	/**
	 * The WooBeWoo mark as the menu icon. WordPress paints the fill in the admin colour scheme.
	 *
	 * @since 4.0.0
	 */
	public function menuIcon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -6.5 48 48"><path fill="black" d="M37.9799 1.02244C38.7399 -0.334244 40.6897 -0.342452 41.4613 1.00779L47.7074 11.9385H46.3314C45.6334 11.9385 44.9873 12.2973 44.6293 12.8965C41.123 18.7635 35.6081 28.8301 32.8676 33.8721C32.1291 35.2304 30.1966 35.2725 29.3939 33.9512L22.7982 23.0888C20.5178 27.1784 18.3089 31.2203 16.8676 33.8721C16.1291 35.2304 14.1966 35.2725 13.3939 33.9512L6.81872 22.9385L0.279662 11.9043C-0.0759926 11.3041 -0.09367 10.5613 0.233763 9.94529L4.47888 1.95994C5.23893 0.603345 7.18878 0.595078 7.96033 1.94529L13.2074 10.5C13.565 11.153 13.5305 11.9508 13.1176 12.5703L8.04333 20.9336C7.82433 21.2621 7.3799 22.2097 7.37988 22.6045V23.5H8.20703C8.93094 23.5 9.92596 22.5466 10.2797 21.915L21.9799 1.02244C22.7399 -0.334243 24.6897 -0.342451 25.4613 1.00779L31.8781 11.9181L30.3314 11.9385C29.6334 11.9385 28.9873 12.2973 28.6293 12.8965C26.9899 15.6397 24.9129 19.3019 22.8842 22.9385H24.5345C25.2585 22.9385 25.926 22.5466 26.2797 21.915L37.9799 1.02244Z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the admin menu takes an icon as a data URI
	}

	/**
	 * Free version: the last item of our menu ("Upgrade") is a filled button, in the open menu and in the
	 * fly-out. The item only exists while Pro is not active (WooBeWoo_PF_Options::addAdminTab).
	 *
	 * @since 4.0.0
	 */
	public function upgradeButtonCss() {
		if ( WooBeWoo_PF_Frame::_()->isWCLicense() || ( WooBeWoo_PF_Frame::_()->moduleExists( 'license' ) && WooBeWoo_PF_Frame::_()->getModule( 'license' ) ) ) {
			return;
		}
		$a = '#adminmenu .toplevel_page_' . sanitize_html_class( WooBeWoo_PF_Dispatcher::applyFilters( 'adminMenuMainSlug', $this->_mainSlug ) ) . ' .wp-submenu a[href$="tab=gopro"]';
		echo '<style id="wpf-upgrade-menu">'
			. $a . '{display:block;margin:8px 10px 6px;padding:8px 10px;border-radius:6px;background:#F5B82E;color:#171509;font-weight:700;text-align:center;line-height:1.3;box-shadow:none}' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS, slug passed through sanitize_html_class
			. $a . ':hover,' . $a . ':focus{background:#FFC94D;color:#171509;box-shadow:none}' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</style>';
	}

	/**
	 * menu_order: our menu item right after WooCommerce (before Products), whatever position other plugins took.
	 *
	 * @since 4.0.0
	 */
	public function placeUnderWoo( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}
		$slug = WooBeWoo_PF_Dispatcher::applyFilters( 'adminMenuMainSlug', $this->_mainSlug );
		$mine = array_search( $slug, $order, true );
		$woo  = array_search( 'woocommerce', $order, true );
		if ( false === $mine || false === $woo ) {
			return $order;
		}
		unset( $order[ $mine ] );
		$order = array_values( $order );
		array_splice( $order, (int) array_search( 'woocommerce', $order, true ) + 1, 0, array( $slug ) );
		return $order;
	}

	/**
	 * getMainLink.
	 *
	 * @version 3.4.0
	 */
	public function getMainLink() {
		return WooBeWoo_PF_Uri::_(
			array(
				'baseUrl' => admin_url( 'admin.php' ),
				'page'    => $this->getMainSlug(),
			)
		);
	}

	public function getMainSlug() {
		return $this->_mainSlug;
	}

	/**
	 * getMainCap.
	 *
	 * @version 3.4.0
	 */
	public function getMainCap() {
		return WooBeWoo_PF_Dispatcher::applyFilters( 'adminMenuAccessCap', $this->_mainCap );
	}
}
