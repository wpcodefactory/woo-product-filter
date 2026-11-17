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

		$this->assign( 'tabs', $tabs );
		$this->assign( 'activeTab', $activeTab );
		$this->assign( 'content', $content );
		$this->assign( 'mainUrl', $this->getModule()->getTabUrl() );
		$this->assign( 'activeParentTabs', $activeParentTabs );

		// 4.0: screens escape their own output; the shell is printed as is so iframes, buttons and SVG survive.
		echo parent::getContent( 'optionsAdminPage' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	 * getSettingsTabContent: the old Settings table lives inside Help as "Advanced settings".
	 *
	 * @version 4.0.0
	 */
	public function getSettingsTabContent() {
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		return $speed ? $speed->getView()->getTabContent( 'settings' ) : '';
	}

	/**
	 * getProTabContent: the Upgrade screen (modules/upgrade).
	 *
	 * @version 4.0.0
	 */
	public function getProTabContent() {
		$file = WPF_MODULES_DIR . 'upgrade' . WPF_DS . 'class-woobewoo-pf-upgrade-screen.php';
		if ( file_exists( $file ) ) {
			require_once $file;
			return WooBeWoo_PF_Upgrade_Screen::render();
		}
		return '';
	}
	/**
	 * Data for the skin diagnostic card (optionsAdminPage.php): our enqueued stylesheet and
	 * script URLs (Free and Pro), the active plugins and a few environment facts.
	 *
	 * @since 4.0.0
	 * @return array
	 */
	public function getDiagnosticData() {
		$assets    = array();
		$templates = WooBeWoo_PF_Frame::_()->getModule( 'templates' );
		if ( $templates && method_exists( $templates, 'skinUrl' ) ) {
			// The skin itself always goes first, even when something deregistered its handle.
			$assets[] = array( 'handle' => WooBeWoo_PF_Templates::SKIN_HANDLE, 'src' => add_query_arg( 'ver', WooBeWoo_PF_Frame::assetVer( $templates->skinUrl() ), $templates->skinUrl() ) );
		}
		foreach ( array( wp_styles(), wp_scripts() ) as $deps ) {
			$handles = array_unique( array_merge( (array) $deps->queue, (array) $deps->done ) );
			foreach ( $handles as $handle ) {
				if ( empty( $deps->registered[ $handle ] ) || empty( $deps->registered[ $handle ]->src ) ) {
					continue;
				}
				$src = (string) $deps->registered[ $handle ]->src;
				if ( ! WooBeWoo_PF_Templates::isOurAsset( $handle, $src ) ) {
					continue;
				}
				if ( WooBeWoo_PF_Templates::SKIN_HANDLE === $handle ) {
					continue;
				}
				$ver      = $deps->registered[ $handle ]->ver;
				$assets[] = array(
					'handle' => (string) $handle,
					'src'    => add_query_arg( 'ver', $ver ? $ver : $deps->default_version, $src ),
				);
			}
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = array();
		$all     = get_plugins();
		foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
			$plugins[] = isset( $all[ $file ]['Name'] ) ? $all[ $file ]['Name'] . ' ' . $all[ $file ]['Version'] : $file;
		}
		if ( is_multisite() ) {
			foreach ( array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) as $file ) {
				$plugins[] = isset( $all[ $file ]['Name'] ) ? $all[ $file ]['Name'] . ' ' . $all[ $file ]['Version'] . ' (network)' : $file;
			}
		}
		$theme = wp_get_theme();
		return array(
			'assets'  => $assets,
			'plugins' => $plugins,
			'env'     => array(
				'Product Filter' => WPF_VERSION . ' in ' . WPF_PLUG_NAME,
				'WordPress'      => get_bloginfo( 'version' ),
				'PHP'            => PHP_VERSION,
				'Theme'          => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
				'Locale'         => get_locale() . ( is_rtl() ? ' RTL' : '' ),
			),
		);
	}
}
