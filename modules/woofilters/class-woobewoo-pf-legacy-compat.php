<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Legacy_Compat Class
 *
 * Keeps an installed Product Filter Pro 3.x (3.4.0 to 3.4.7) working on Free 4.0. This is the
 * auto update case: WordPress updates Free on its own while Pro stays at 3.4.7 until the owner
 * updates it, sometimes for days. The Pro 3.x modules keep running on the legacy engine; this
 * class restores the few Free 3.4.7 contracts that 4.0 changed and replaces the Pro 3.x
 * version notices with one plain notice (see woobewoo_pf_pro_incompatible_notice()).
 *
 * Loaded once from the woofilters module. Everything waits for `plugins_loaded`, because the
 * Free frame starts while the Free main file loads, before the Pro main file is included.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Legacy_Compat {

	/**
	 * First Pro version written for Free 4.0.
	 */
	const PRO_NEW = '4.0.0';

	/**
	 * Oldest Pro the Free 4.0 legacy engine still serves (class and file naming of 3.4.0).
	 */
	const PRO_OLDEST = '3.4.0';

	/**
	 * Booted once.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Cached version of the active Pro plugin, false when Pro is not active.
	 *
	 * @var string|false|null
	 */
	private static $proVersion = null;

	/**
	 * boot.
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		if ( did_action( 'plugins_loaded' ) ) {
			self::onPluginsLoaded();
		} else {
			add_action( 'plugins_loaded', array( __CLASS__, 'onPluginsLoaded' ), 1 );
		}
	}

	/**
	 * Version of the active Pro plugin, or false when Pro is not active (Pro defines
	 * woobeboo_pf_get_pro_plugin_full_path() from 3.4.0 on, Free reads the header).
	 *
	 * @return string|false
	 */
	public static function proVersion() {
		if ( null === self::$proVersion ) {
			self::$proVersion = false;
			$path             = function_exists( 'woobewoo_pf_get_pro_full_path' ) ? woobewoo_pf_get_pro_full_path() : false;
			if ( $path && is_readable( $path ) ) {
				$data = get_file_data( $path, array( 'Version' => 'Version' ) );
				if ( ! empty( $data['Version'] ) ) {
					self::$proVersion = (string) $data['Version'];
				}
			}
		}
		return self::$proVersion;
	}

	/**
	 * True when a Pro older than 4.0 is active next to Free 4.0.
	 *
	 * @return bool
	 */
	public static function isLegacyPro() {
		$version = self::proVersion();
		return false !== $version && version_compare( $version, self::PRO_NEW, '<' );
	}

	/**
	 * onPluginsLoaded.
	 */
	public static function onPluginsLoaded() {
		if ( false === self::proVersion() ) {
			return;
		}
		// Any Pro: the 4.0 builder does not post settings[filters][defaults] (the preselected values the 3.x
		// Pro edit screen computed in JS), so a save would drop them. Computed on the server instead.
		WooBeWoo_PF_Dispatcher::addFilter( 'woobewoo_pf_filter_settings_saving_data', array( __CLASS__, 'keepSavedPreselects' ), 30, 3 );
		if ( ! self::isLegacyPro() ) {
			return;
		}
		// Pro 3.x encodes whatever arrives in settings[css_editor] / [js_editor]; the 4.0 builder posts the stored
		// (already encoded) value back, so an unchanged save would encode it twice.
		WooBeWoo_PF_Dispatcher::addFilter( 'woobewoo_pf_filter_settings_saving_data', array( __CLASS__, 'keepStoredCustomCode' ), 30, 3 );
		// Pro 3.x prints its own "Please install latest PRO version (requires at least 4.0.0)" notice
		// and a plugins list row; Free prints the single plain notice instead.
		add_filter( 'woobewoo_pf_show_pro_notice', '__return_false', 99 );
		remove_action( 'after_plugin_row_woofilter-pro/woofilter-pro.php', 'woobewoo_pf_plugin_row', 5 );

		// Free 3.4.7 fired this on its Settings screen; Pro 3.x enqueues its option assets there.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'settingsScreenAssets' ), 5 );
		// 4.0.1: Pro 3.x registers its Licence and Statistics tabs without a rail group, so the 4.0 rail (and the
		// submenu mirrored from it) left them unreachable. Put them in the Pro group.
		WooBeWoo_PF_Dispatcher::addFilter( 'mainAdminTabs', array( __CLASS__, 'railLegacyProTabs' ), 50 );
		// 4.0.1: a filter made on Free 4.0 while Pro 3.x is installed runs on the 3.x engine, which is the one
		// Pro 3.x extends (its blocks, custom CSS/JS and hooks never reach the 4.0 engine).
		add_filter( 'woobewoo_pf_new_filter_engine', array( __CLASS__, 'legacyEngineForNewFilters' ) );
	}

	/**
	 * mainAdminTabs (Pro 3.x installed): rail group for the Pro 3.x tabs.
	 *
	 * @since 4.0.1
	 */
	public static function railLegacyProTabs( $tabs ) {
		foreach ( array( 'license', 'statistics' ) as $key ) {
			if ( isset( $tabs[ $key ] ) && is_array( $tabs[ $key ] ) && empty( $tabs[ $key ]['rail_group'] ) ) {
				$tabs[ $key ]['rail_group'] = 'pro';
			}
		}
		return $tabs;
	}

	/**
	 * @since 4.0.1
	 */
	public static function legacyEngineForNewFilters( $engine ) {
		return 'legacy';
	}

	/**
	 * Fires the Free 3.4.7 action `woobewoo_pf_enqueue_admin_option_pro_assets` on the 4.0 screens
	 * that show the old Settings options (Settings, Help "Advanced settings").
	 */
	public static function settingsScreenAssets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only screen check.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only screen check.
		if ( 'wpf-filters' !== $page || ! in_array( $tab, array( 'settings', 'help' ), true ) ) {
			return;
		}
		static $fired = false;
		if ( ! $fired ) {
			$fired = true;
			// The 3.4.7 Settings screen had the core colour picker; Pro 3.x adds its alpha extension on top of it.
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			WooBeWoo_PF_Dispatcher::doAction( 'woobewoo_pf_enqueue_admin_option_pro_assets' );
			// Pro 3.x registers the alpha extension without a dependency; it must print after the picker.
			$scripts = wp_scripts();
			$handle  = 'woobewoo-pf-admin-colorpicker-alhpa';
			if ( isset( $scripts->registered[ $handle ] ) && ! in_array( 'wp-color-picker', (array) $scripts->registered[ $handle ]->deps, true ) ) {
				$scripts->registered[ $handle ]->deps[] = 'wp-color-picker';
			}
		}
	}

	/**
	 * Saving filter (priority 30): when the request did not post settings[filters][defaults], compute it from
	 * the blocks exactly as the Pro 3.x edit screen did (saveFiltersPro() in admin.woofilters.pro.js), so the
	 * storefront keeps its preselected values ("wpf_filter_cat_1=37;pr_stock=instock").
	 *
	 * @param array $data     Data being saved.
	 * @param array $settings Posted settings.
	 * @param bool  $update   Update of an existing filter.
	 * @return array
	 */
	public static function keepSavedPreselects( $data, $settings = array(), $update = false ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only presence check, the save controller verified the nonce.
		if ( ! is_array( $data ) || ! isset( $data['settings']['filters'] ) || isset( $_POST['settings']['filters']['defaults'] ) ) {
			return $data;
		}
		$data['settings']['filters']['defaults'] = self::filterDefaults( isset( $data['settings']['filters']['order'] ) ? $data['settings']['filters']['order'] : '' );
		return $data;
	}

	/**
	 * The `defaults` string of a filter, from its blocks (JSON `order` or the decoded array).
	 *
	 * @param string|array $order Blocks.
	 * @return string
	 */
	public static function filterDefaults( $order ) {
		$blocks = is_array( $order ) ? $order : json_decode( (string) $order, true );
		if ( ! is_array( $blocks ) ) {
			return '';
		}
		$truthy     = function ( $v ) {
			return ! ( false === $v || null === $v || '' === $v || 0 === $v || 0.0 === $v );
		};
		$nameWithId = array( 'wpfCategory', 'wpfTags', 'wpfPerfectBrand' );
		$allowTypes = array( 'list', 'dropdown', 'radio', 'mul_dropdown', 'text', 'buttons', 'multi' );
		$defaults   = '';
		foreach ( array_values( $blocks ) as $i => $block ) {
			$s    = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
			$id   = isset( $block['id'] ) ? (string) $block['id'] : '';
			$name = isset( $block['name'] ) ? (string) $block['name'] : '';
			if ( array_key_exists( 'f_select_default_id', $s ) && '' !== $name ) {
				$pre = is_scalar( $s['f_select_default_id'] ) ? (string) $s['f_select_default_id'] : '';
				if ( '' !== $pre && isset( $s['f_frontend_type'] ) && in_array( $s['f_frontend_type'], $allowTypes, true )
					&& ! $truthy( isset( $s['f_hidden_categories'] ) ? $s['f_hidden_categories'] : null )
					&& ! $truthy( isset( $s['f_hidden_brands'] ) ? $s['f_hidden_brands'] : null )
					&& ! $truthy( isset( $s['f_hidden_tags'] ) ? $s['f_hidden_tags'] : null )
					&& ! $truthy( isset( $s['f_hidden_attributes'] ) ? $s['f_hidden_attributes'] : null ) ) {
					if ( in_array( $id, $nameWithId, true ) ) {
						$name .= '_' . $i;
					}
					if ( 'wpfCategory' === $id && isset( $s['f_multi_logic'] ) && 'or' === $s['f_multi_logic'] ) {
						$pre = str_replace( ',', '|', $pre );
					} elseif ( in_array( $id, array( 'wpfTags', 'wpfAttribute' ), true ) && isset( $s['f_query_logic'] ) && 'or' === $s['f_query_logic'] ) {
						$pre = str_replace( ',', '|', $pre );
					}
					$defaults .= $name . '=' . $pre . ';';
				}
			} elseif ( $truthy( isset( $s['f_default_stock'] ) ? $s['f_default_stock'] : null ) ) {
				$status = isset( $s['f_hidden_stock_status'] ) && is_scalar( $s['f_hidden_stock_status'] ) ? (string) $s['f_hidden_stock_status'] : '';
				$noPre  = array_key_exists( 'f_no_preselect', $s ) ? $s['f_no_preselect'] : null;
				if ( ! $truthy( isset( $s['f_hidden_stock'] ) ? $s['f_hidden_stock'] : null ) && '' !== $status && array_key_exists( 'f_no_preselect', $s ) && ! ( true === $noPre || '1' === $noPre || 1 === $noPre ) ) {
					$defaults .= 'pr_stock=' . $status . ';';
				}
			} elseif ( $truthy( isset( $s['f_default_sortby'] ) ? $s['f_default_sortby'] : null ) ) {
				$sortby = isset( $s['f_hidden_sortby'] ) && is_scalar( $s['f_hidden_sortby'] ) ? (string) $s['f_hidden_sortby'] : '';
				if ( ! $truthy( isset( $s['f_hidden_sort'] ) ? $s['f_hidden_sort'] : null ) && '' !== $sortby ) {
					$defaults .= 'pr_sortby=' . $sortby . ';';
					if ( $truthy( isset( $s['f_first_instock'] ) ? $s['f_first_instock'] : null ) ) {
						$defaults .= 'pr_oistock=1;';
					}
				}
			} elseif ( $truthy( isset( $s['f_default_onsale'] ) ? $s['f_default_onsale'] : null ) && ! $truthy( isset( $s['f_hidden_onsale'] ) ? $s['f_hidden_onsale'] : null ) ) {
				$defaults .= 'pr_onsale=1;';
			}
		}
		return '' === $defaults ? '' : substr( $defaults, 0, -1 );
	}

	/**
	 * Saving filter (priority 30, Pro 3.x only): when the builder posted the stored custom CSS / JS back
	 * unchanged (still base64), keep the stored value instead of Pro 3.x's second base64 layer.
	 *
	 * @param array $data     Data being saved.
	 * @param array $settings Posted settings.
	 * @param bool  $update   Update of an existing filter.
	 * @return array
	 */
	public static function keepStoredCustomCode( $data, $settings = array(), $update = false ) {
		if ( ! is_array( $data ) || empty( $data['id'] ) ) {
			return $data;
		}
		$stored = null;
		foreach ( array( 'css_editor', 'js_editor' ) as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the save controller verified the nonce.
			if ( ! isset( $_POST['settings'][ $field ] ) || ! is_string( $_POST['settings'][ $field ] ) || ! isset( $data['settings'][ $field ] ) ) {
				continue;
			}
			$posted = (string) wp_unslash( $_POST['settings'][ $field ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared only, never output.
			if ( '' === $posted || base64_encode( $posted ) !== $data['settings'][ $field ] ) {
				continue; // Pro 3.x did not encode this value (no rights, or it kept the stored one).
			}
			if ( null === $stored ) {
				$row    = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getById( (int) $data['id'] );
				$dec    = $row && isset( $row['setting_data'] ) ? woobewoo_pf_decode_settings( $row['setting_data'] ) : array();
				$stored = isset( $dec['settings'] ) && is_array( $dec['settings'] ) ? $dec['settings'] : array();
			}
			if ( isset( $stored[ $field ] ) && $posted === (string) $stored[ $field ] ) {
				$data['settings'][ $field ] = $stored[ $field ];
			}
		}
		return $data;
	}
}

WooBeWoo_PF_Legacy_Compat::boot();
